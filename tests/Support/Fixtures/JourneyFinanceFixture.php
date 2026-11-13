<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Actions\ConfirmWithoutQuotation;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;

/**
 * TL-09 (TASK-063): the starting preconditions of CJ-06's backdating (UXS-14; UXS-29) that no module fixture holds — numbering and
 * master data only, never a step the journey walks. Synthetic data only.
 *
 * - `period`: for the run's company — whose go-live the run armed on the first day of the previous month —, PROJECT numbering live
 *   for the year, the Invoice (DOC-06) numbering monthly from go-live and started on `boundary`, so the boundary's month is not live
 *   and has no counter (NM-03) while every later month is live, and a confirmed service project of ten at Rp250,000.
 * - `admin`: the signed-in plain account made an Admin of that company alone with what an invoice's issue needs and nothing of the
 *   numbering (SECURITY_TEST_MATRIX §2).
 */
final class JourneyFinanceFixture
{
    public const ADMIN = ['projects.view', 'finance.view', 'invoice.issue'];

    public static function period(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $goLive = (string) $request->input('go_live');
        $boundary = (string) $request->input('boundary');
        $today = BusinessDate::today();

        if (preg_match('/^\d{4}-\d{2}-01$/', $goLive) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $boundary) !== 1 || $boundary < $goLive || $boundary >= substr($today, 0, 7).'-01') {
            return response()->json(['error' => 'go_live is a first of month, boundary a day of that month before this one'], 422);
        }

        $year = substr($today, 0, 4);
        Numbering::goLive($goLive);
        Numbering::scheme($company, SequenceType::Project, "{$year}-01-01", 'YEAR', 'UJI/PRJ/{tahun}/{nomor:4}');
        Numbering::started($company, SequenceType::Project, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        Numbering::scheme($company, SequenceType::Doc06, $goLive, 'MONTH', 'UJI/INV/{tahun}/{bulan}/{nomor:4}');
        Numbering::started($company, SequenceType::Doc06, CarbonImmutable::parse("{$boundary} 03:00:00", 'UTC'));
        DocumentTypeFactory::ensure('DOC-06');

        $client = Projects::client();
        $client['organization']->forceFill(['name' => 'SMA Uji '.$company->code])->save();
        $service = Projects::product('SERVICE', '250000.00');
        $service['product']->forceFill(['name' => 'Instalasi Uji '.$company->code])->save();
        Authorization::forget();
        $payload = Projects::payload($company, $client, [$service]);
        $lines = array_map(fn (array $line): array => [...$line, 'mode' => 'SERVICE', 'jumlah' => '10', 'harga' => '250000'], is_array($payload['baris']) ? $payload['baris'] : []);
        $created = Projects::create($actor, $company, $client, [$service], null, ['baris' => $lines]);

        if (! str_starts_with($created, 'COMMITTED')) {
            return response()->json(['error' => $created], 500);
        }

        $project = Project::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $order = EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'CLIENT_SPK', 'declared_issuer' => 'SMA Uji '.$company->code]);
        $outcome = app(ConfirmWithoutQuotation::class)->run((string) Str::uuid(), $actor, $project, $project->lock_version, $order->id, $order->public_id, null, $today, false);

        if ($outcome->kind !== 'COMMITTED') {
            return response()->json(['error' => "confirm {$outcome->kind} {$outcome->code}"], 500);
        }

        return response()->json(['code' => $company->code, 'project' => $project->public_id, 'number' => $project->project_number]);
    }

    public static function admin(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        Authorization::grantCompany($user, $company->id);

        foreach (self::ADMIN as $capability) {
            Authorization::grant($user, $capability);
        }

        return response()->json(['ok' => true]);
    }
}
