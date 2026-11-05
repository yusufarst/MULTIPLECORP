<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use App\Modules\ImportOrchestration\Actions\DryRunImport;
use App\Modules\ImportOrchestration\Actions\SignOffImport;
use App\Modules\ImportOrchestration\Jobs\CommitImportRows;
use App\Modules\ImportOrchestration\Jobs\ValidateImportBatch;
use App\Modules\ImportOrchestration\Models\Batch as ImportBatch;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Services\NumberingState;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\ImportBatchFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;

/**
 * TL-09–TL-12 (TASK-057; J-MIG-01; UXS-19; CJ-16's steps on the CI stack): a company of the run whose PROJECT and PURCHASE schemes cover the cutoff — the last
 * day of the previous year — with the seeds a batch must carry; an Admin of the run signing off; the owed job of a batch run here, since the CI stack runs no
 * worker; and a committed twin of another company for the collision outside scope. Synthetic data only.
 */
final class ImportFixture
{
    public static function create(Request $request, NumberingState $numbering): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $year = (string) ((int) CarbonImmutable::now()->format('Y') - 1);
        $cutoff = "{$year}-12-31";

        foreach ([[SequenceType::Project, 'UJI/PRJ'], [SequenceType::Purchase, 'UJI/PO']] as [$type, $prefix]) {
            if (DB::table('numbering_schemes')->where('company_id', $company->id)->where('sequence_type', $type->value)->where('effective_from', '<=', $cutoff)->doesntExist()) {
                Numbering::scheme($company, $type, "{$year}-01-01", 'YEAR', "{$prefix}/{tahun}/{nomor:4}");
            }
        }

        // An account of the run preparing in this company only (PJ-20), for the collision outside scope.
        if (is_string($request->input('preparer'))) {
            $preparer = User::query()->where('public_id', $request->input('preparer'))->firstOrFail();
            Authorization::grantCompany($preparer, $company->id);

            foreach (['import.prepare', 'projects.view', 'stock.view'] as $capability) {
                Authorization::grant($preparer, $capability);
            }
        }

        $seeds = [];

        foreach (SequenceType::cases() as $type) {
            foreach (NumberingState::periodsOf($numbering->schemes($company->id, $type), $cutoff) as $period) {
                $seeds[] = ['jenis' => $type->value, 'periode' => $period->key];
            }
        }

        return response()->json(['code' => $company->code, 'cutoff' => $cutoff, 'seeds' => $seeds]);
    }

    /** The Admin's sign-off of the run's batch, with a pool upload of the Admin's own. */
    public static function signOff(Request $request, SignOffImport $signOff): JsonResponse
    {
        $batch = ImportBatch::query()->where('public_id', (string) $request->input('batch'))->firstOrFail();
        // An Admin of the run's own: the factories' sequences start again in every request (C-49), so a factory's
        // e-mail of a second run in one database may already be taken.
        $admin = User::factory()->create(['email' => 'admin-impor-'.Str::lower(Str::random(12)).'@contoh.test']);
        DB::table('user_roles')->insert(['user_id' => $admin->id, 'role_id' => DB::table('roles')->where('code', 'ADMIN_OPERASIONAL')->value('id'), 'granted_at' => now()]);

        foreach ($batch->company_scope as $companyId) {
            Authorization::grantCompany($admin, $companyId);
        }

        foreach (['import.prepare', 'projects.view', 'evidence.upload'] as $capability) {
            Authorization::grant($admin, $capability);
        }

        $upload = FileObject::factory()->create(['company_id' => null, 'purpose' => 'UPLOAD', 'uploaded_by' => $admin->id]);
        $outcome = $signOff->run((string) Str::uuid(), $admin->id, $batch, $upload->public_id);

        return response()->json(['kind' => $outcome->kind, 'code' => $outcome->code]);
    }

    /** The batch's owed job — its dry run or its commit — run here, as a worker would (JB-05; JB-07). */
    public static function work(Request $request): JsonResponse
    {
        $batch = ImportBatch::query()->where('public_id', (string) $request->input('batch'))->firstOrFail();
        $report = is_array($batch->dry_run_report) ? $batch->dry_run_report : [];

        if ($batch->state === 'PREPARED' && ($report['state'] ?? null) === DryRunImport::PENDING && is_string($report['request'] ?? null)) {
            ValidateImportBatch::dispatchSync($batch->id, $report['request']);
        }

        for ($chunk = 0; $chunk < 10 && $batch->refresh()->state === 'SIGNED_OFF' && $batch->committed_at !== null && ! CommitImportRows::stopped($batch->id); $chunk++) {
            CommitImportRows::dispatchSync($batch->id);
        }

        return response()->json(['state' => $batch->refresh()->state]);
    }

    /** A committed opening stock row of another company under the identity given, the twin a batch of this company collides with (UXS-19). */
    public static function twin(Request $request): JsonResponse
    {
        // Their own identities: the factories' sequences start again in every request (C-49).
        $tag = Str::upper(Str::random(8));
        $company = Company::factory()->create(['code' => "K{$tag}", 'legal_name' => "CV Kembar {$tag}"]);
        // The factory of the batch's own table: the orchestration's Batch inherits no factory attribute.
        $batch = ImportBatchFactory::new()->createOne(['import_kind' => 'OPENING_STOCK', 'source_sha256' => hash('sha256', "kembar-{$tag}"), 'company_scope' => [$company->id], 'state' => 'COMMITTED']);
        DB::table('import_rows')->insert([
            'import_batch_id' => $batch->id, 'row_number' => 2, 'import_kind' => 'OPENING_STOCK', 'import_identity' => (string) $request->input('identity'), 'company_id' => $company->id,
            'normalized_payload' => '{}', 'validation_state' => 'COMMITTED', 'recorded_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['company' => $company->code]);
    }
}
