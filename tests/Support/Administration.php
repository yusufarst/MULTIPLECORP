<?php

namespace Tests\Support;

use App\Modules\ADM\Services\Readiness;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\RowHandle;
use Database\Factories\DOC\DocumentFactory;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\DocumentVersionFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\DOC\EvidenceDocumentVersionFactory;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-035's records for the administrative checklist's tests: a project of the company, a requirement written as the
 * checklist's own row, an issued document version of a type with its rendition, a company evidence whose current file
 * has a chosen digest — a reproduction when it equals a rendition's (FS-12) — and the handles the pages show.
 */
final class Administration
{
    /** A project of the company created through PG-013, its lines a goods and a service product. */
    public static function project(int $actorId, Company $company): Project
    {
        $before = Project::query()->pluck('id')->all();
        expect(Projects::create($actorId, $company, Projects::client(), [Projects::product(), Projects::product('SERVICE', '250000.00')]))->toStartWith('COMMITTED');

        return Project::query()->whereNotIn('id', $before)->sole();
    }

    /**
     * A requirement of the project, written as its row (DATABASE §4.11).
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function requirement(Project $project, array $overrides = []): int
    {
        return (int) DB::table('admin_requirements')->insertGetId([
            'project_id' => $project->id,
            'company_id' => $project->company_id,
            'requirement_kind' => 'CATALOG_DOCUMENT',
            'document_type_code' => DocumentTypeFactory::ensure('DOC-09'),
            'description' => 'SPK perusahaan',
            'is_required' => true,
            'satisfaction_mode' => 'GENERATED',
            'recorded_at' => now(),
            'recorded_by' => DB::table('users')->value('id'),
            ...$overrides,
        ]);
    }

    /** The requirement's handle as PG-011 and PG-039 show it. */
    public static function handle(int $requirementId): string
    {
        return RowHandle::of('admin_requirements', $requirementId);
    }

    /** A document of the project's type with one version — DRAFT, or ISSUED with its rendition in the given state. */
    public static function document(Project $project, string $type = 'DOC-09', ?string $rendition = 'READY', bool $issued = true): DocumentVersion
    {
        $document = DocumentFactory::new()->createOne(['company_id' => $project->company_id, 'project_id' => $project->id, 'document_type_code' => DocumentTypeFactory::ensure($type)]);
        $factory = DocumentVersionFactory::new();
        $version = ($issued ? $factory->issued() : $factory)->createOne(['document_id' => $document->id]);

        if ($issued && $rendition !== null) {
            self::rendition($version, $rendition, hash('sha256', 'rendisi-'.Str::uuid()));
        }

        return $version;
    }

    /** A rendition of the version in its state, its file a RENDITION of the given digest when READY. */
    public static function rendition(DocumentVersion $version, string $state, string $sha256): void
    {
        $file = $state === 'READY' ? FileObjectFactory::new()->createOne(['company_id' => $version->company_id, 'purpose' => 'RENDITION', 'sha256' => $sha256])->id : null;
        DB::table('document_renditions')->insert(['document_version_id' => $version->id, 'attempt_no' => 1, 'state' => $state, 'file_object_id' => $file, 'recorded_at' => now()]);
    }

    /** The version's pending rendition rendered: READY with its RENDITION file, as the render job leaves it. */
    public static function rendered(DocumentVersion $version): void
    {
        $file = FileObjectFactory::new()->createOne(['company_id' => $version->company_id, 'purpose' => 'RENDITION', 'sha256' => hash('sha256', 'rendisi-'.Str::uuid())])->id;
        DB::table('document_renditions')->where('document_version_id', $version->id)->update(['state' => 'READY', 'file_object_id' => $file, 'updated_at' => now()]);
    }

    /** The digest of the version's ready rendition, which a reproduction's upload shares (FS-12). */
    public static function renditionDigest(DocumentVersion $version): string
    {
        return (string) DB::table('document_renditions as r')->join('file_objects as f', 'f.id', '=', 'r.file_object_id')->where('r.document_version_id', $version->id)->value('f.sha256');
    }

    /** A company evidence of the type whose current version's file has the digest, by default a unique upload, from its issuer. */
    public static function evidence(Company $company, string $type = 'CLIENT_SPK', ?string $sha256 = null, ?string $issuer = null): EvidenceDocument
    {
        $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => $type, ...($issuer === null ? [] : ['declared_issuer' => $issuer])]);
        $file = FileObjectFactory::new()->createOne(['company_id' => $company->id, 'sha256' => $sha256 ?? hash('sha256', 'unggahan-'.Str::uuid())]);
        $version = EvidenceDocumentVersionFactory::new()->createOne(['evidence_document_id' => $evidence->id, 'file_object_id' => $file->id]);
        DB::table('evidence_documents')->where('id', $evidence->id)->update(['current_version_id' => $version->id]);

        return $evidence->refresh();
    }

    /** The document's public identifier, which ROUTE-165 names. */
    public static function publicIdOf(DocumentVersion $version): string
    {
        return (string) Document::query()->whereKey($version->document_id)->value('public_id');
    }

    /** The requirement's derived status as the checklist reads it. */
    public static function status(Project $project, int $requirementId): ?string
    {
        foreach (Readiness::of([$project->id])[$project->id] ?? [] as $item) {
            if ($item['id'] === $requirementId) {
                return $item['status'];
            }
        }

        return null;
    }
}
