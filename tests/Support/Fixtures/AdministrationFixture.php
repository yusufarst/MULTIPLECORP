<?php

namespace Tests\Support\Fixtures;

use App\Modules\PRJ\Models\Project;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\DOC\EvidenceDocumentVersionFactory;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-035's browser fixture (TL-09–TL-12; ROUTE-039; ROUTE-164–ROUTE-169; J-ADM-01): TASK-034's confirmed mixed project
 * of the run's company — its confirmation adding the default invoice item —, a required SPK of the company's own
 * documents, the school's order letter to be uploaded, and the client's SPK registered as an authentic upload ready to be
 * linked. Local only; synthetic names.
 */
final class AdministrationFixture
{
    public static function create(Request $request): JsonResponse
    {
        $confirmed = ConfirmationFixture::create(Request::create('/', 'POST', ['company' => $request->input('company'), 'confirmed' => true])->setUserResolver($request->getUserResolver()));

        if ($confirmed->getStatusCode() !== 200) {
            return $confirmed;
        }

        /** @var array{code: string, project: string, number: string, goods: string, service: string} $run */
        $run = $confirmed->getData(true);
        $project = Project::query()->where('public_id', $run['project'])->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        DB::table('admin_requirements')->insert([
            'project_id' => $project->id,
            'company_id' => $project->company_id,
            'requirement_kind' => 'CATALOG_DOCUMENT',
            'document_type_code' => DocumentTypeFactory::ensure('DOC-09'),
            'description' => 'SPK perusahaan untuk sekolah',
            'is_required' => true,
            'satisfaction_mode' => 'GENERATED',
            'recorded_at' => now(),
            'recorded_by' => is_int($actor) ? $actor : null,
        ]);

        DB::table('admin_requirements')->insert([
            'project_id' => $project->id,
            'company_id' => $project->company_id,
            'requirement_kind' => 'EXTERNAL_DOCUMENT',
            'evidence_type' => 'CLIENT_ORDER',
            'description' => 'Surat pesanan dari sekolah',
            'is_required' => true,
            'satisfaction_mode' => 'UPLOADED',
            'recorded_at' => now(),
            'recorded_by' => is_int($actor) ? $actor : null,
        ]);

        $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $project->company_id, 'evidence_type' => 'CLIENT_SPK', 'declared_issuer' => 'SMA Asli '.$run['code']]);
        $file = FileObjectFactory::new()->createOne(['company_id' => $project->company_id, 'sha256' => hash('sha256', 'spk-asli-'.Str::uuid())]);
        // The signed-in Owner uploaded it: the factory's own account would repeat its sequence's address across requests.
        $version = EvidenceDocumentVersionFactory::new()->createOne(['evidence_document_id' => $evidence->id, 'file_object_id' => $file->id, 'uploaded_by' => is_int($actor) ? $actor : null]);
        DB::table('evidence_documents')->where('id', $evidence->id)->update(['current_version_id' => $version->id]);

        return response()->json([...$run, 'issuer' => 'SMA Asli '.$run['code']]);
    }
}
