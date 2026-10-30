<?php

namespace Tests\Support\Fixtures;

use App\Modules\FIN\Actions\IssueInvoice;
use App\Modules\FIN\Actions\SaveInvoiceDraft;
use App\Modules\FIN\Models\Invoice;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Actions\ConfirmWithoutQuotation;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\Projects;

/**
 * TASK-049's browser fixture (TL-09–TL-12; ROUTE-041–ROUTE-043; J-FIN-01; J-FIN-08): for the run's company — whose go-live the numbering fixture armed —, a project of `lines` service lines of ten at Rp250,000 confirmed through ROUTE-158's own action, the layouts' numbering started, a
 * registered evidence and, by `stage`, a draft of Rp1,000,000 or that invoice issued, with `payment` a synthetic payment applied (until TASK-051). Local only; synthetic names.
 */
final class InvoiceFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        NumberingFixture::arm(Request::create('/', 'POST', ['go_live' => "{$year}-01-01"]));
        $start = CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC');
        Numbering::scheme($company, SequenceType::Project, "{$year}-01-01", 'YEAR', 'UJI/PRJ/{tahun}/{nomor:4}');
        Numbering::started($company, SequenceType::Project, $start);

        foreach ([[SequenceType::Doc06, 'INV'], [SequenceType::Doc04, 'NOTA'], [SequenceType::Doc05, 'KW']] as [$type, $prefix]) {
            Numbering::scheme($company, $type, "{$year}-01-01", 'YEAR', "UJI/{$prefix}/{tahun}/{nomor:4}");
            Numbering::started($company, $type, $start);
        }

        foreach (['DOC-04', 'DOC-05', 'DOC-06'] as $layout) {
            DocumentTypeFactory::ensure($layout);
        }

        $client = Projects::client();
        $client['organization']->forceFill(['name' => 'SMA Uji '.$company->code])->save();
        $service = Projects::product('SERVICE', '250000.00');
        $service['product']->forceFill(['name' => 'Instalasi Uji '.$company->code])->save();
        Authorization::forget();
        $products = array_fill(0, max(1, (int) $request->input('lines', 1)), $service);
        $lines = array_map(fn (array $line): array => [...$line, 'mode' => 'SERVICE', 'jumlah' => '10', 'harga' => '250000'], Projects::payload($company, $client, $products)['baris']);
        $created = Projects::create($actor, $company, $client, $products, null, ['baris' => $lines]);

        if (! str_starts_with($created, 'COMMITTED')) {
            return response()->json(['error' => $created], 500);
        }

        $project = Project::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $order = EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'CLIENT_SPK', 'declared_issuer' => 'SMA Uji '.$company->code]);
        $outcome = app(ConfirmWithoutQuotation::class)->run((string) Str::uuid(), $actor, $project, $project->lock_version, $order->id, $order->public_id, null, BusinessDate::today(), false);

        if ($outcome->kind !== 'COMMITTED') {
            return response()->json(['error' => "confirm {$outcome->kind} {$outcome->code}"], 500);
        }

        $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'OTHER', 'declared_issuer' => 'Berita Acara '.$company->code]);
        $stage = (string) $request->input('stage', 'none');
        $invoice = null;
        $ref = new ProjectRef($project->id, $project->company_id, $project->public_id);

        if ($stage !== 'none') {
            $saved = app(SaveInvoiceDraft::class)->run((string) Str::uuid(), $actor, $ref, null, null, InvoiceWorld::valueOnly('1000000'), ['fixture' => 'draft']);

            if ($saved->kind !== 'COMMITTED') {
                return response()->json(['error' => "draft {$saved->kind} {$saved->code}"], 500);
            }

            $invoice = Invoice::query()->findOrFail($saved->resultId);
        }

        if ($stage === 'issued' && $invoice !== null) {
            $issued = app(IssueInvoice::class)->run((string) Str::uuid(), $actor, $ref, $invoice, $invoice->lock_version, 'DOC-06', BusinessDate::today(), false);

            if ($issued->kind !== 'COMMITTED') {
                return response()->json(['error' => "issue {$issued->kind} {$issued->code}"], 500);
            }

            if ($request->boolean('payment')) {
                InvoiceWorld::apply($invoice->id, InvoiceWorld::payment($invoice->id, '400000'), '400000');
            }
        }

        return response()->json(['code' => $company->code, 'project' => $project->public_id, 'number' => $project->project_number, 'invoice' => $invoice?->public_id, 'evidence' => $evidence->public_id]);
    }
}
