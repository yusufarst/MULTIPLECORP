<?php

namespace App\Modules\DOC\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Data\FileFamilies;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Data\Authority;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * DP-05's authorization of a download or thumbnail (SECURITY FL-07; ROUTE-226, ROUTE-227): a file is served
 * only through its owning record, at request time, within the viewer's scope and family — a rendition by its
 * document's family (PJ-15), an evidence file by `evidence.view` and its type's family, pool evidence by the
 * pool's (PJ-08), a company's logo to its viewers and its stamp and signature to the Owner only (PJ-19), a
 * product image to every account (row 27), an import source by the import rule (PJ-20), an archive item's file
 * to `finance.view` with `evidence.view` (row 124). An export is served by its own route only (ROUTE-284), and a
 * file no record owns is served to no one. A denied file answers as an unknown one, the denial security-logged.
 */
final class FileAccess
{
    public function __construct(private readonly SecurityEventRecorder $events) {}

    public function find(Authority $viewer, string $publicId): FileObject
    {
        $valid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $publicId) === 1;
        $file = $valid ? FileObject::query()->where('public_id', strtolower($publicId))->first() : null;

        if ($file !== null && $viewer->active && $this->allows($viewer, $file)) {
            return $file;
        }

        if ($file !== null) {
            defer(fn () => $this->events->record(SecurityEventKind::AccessDenied, userId: $viewer->userId, reasonCode: 'FILE_NOT_ALLOWED', targetPublicId: $file->public_id))->always();
        }

        throw (new ModelNotFoundException)->setModel(FileObject::class, [$publicId]);
    }

    private function allows(Authority $viewer, FileObject $file): bool
    {
        return match (FilePurpose::tryFrom($file->purpose)) {
            FilePurpose::Rendition => $this->rendition($viewer, $file),
            FilePurpose::Upload => $this->evidence($viewer, $file),
            FilePurpose::IdentityAsset => $this->identityAsset($viewer, $file),
            FilePurpose::ProductImage => DB::table('product_images')->where('file_object_id', $file->id)->exists(),
            FilePurpose::ImportSource => $this->importSource($viewer, $file),
            FilePurpose::Archive => $this->archive($viewer, $file),
            default => false,
        };
    }

    private function rendition(Authority $viewer, FileObject $file): bool
    {
        $owner = DB::table('document_renditions as r')->join('document_versions as v', 'v.id', '=', 'r.document_version_id')
            ->where('r.file_object_id', $file->id)->where('r.state', 'READY')->first(['v.company_id', 'v.document_type_code']);
        $capability = $owner === null ? null : FileFamilies::document(self::text($owner->document_type_code));

        return $owner !== null && $capability !== null && $viewer->allows($capability, self::int($owner->company_id));
    }

    private function evidence(Authority $viewer, FileObject $file): bool
    {
        $owner = DB::table('evidence_document_versions as ev')->join('evidence_documents as e', 'e.id', '=', 'ev.evidence_document_id')
            ->where('ev.file_object_id', $file->id)->first(['e.company_id', 'e.evidence_type']);

        if ($owner === null) {
            return false;
        }

        return EvidenceTypes::mayView($viewer, self::text($owner->evidence_type), $owner->company_id === null ? null : self::int($owner->company_id));
    }

    private function identityAsset(Authority $viewer, FileObject $file): bool
    {
        $company = DB::table('companies')->where('logo_file_id', $file->id)->orWhere('stamp_file_id', $file->id)->orWhere('signature_file_id', $file->id)
            ->first(['id', 'logo_file_id']);

        if ($company === null) {
            return false;
        }

        if (self::int($company->logo_file_id) !== $file->id) {
            // The raw stamp and signature files: the Owner only; the renderer reads them in system context (PJ-19).
            return $viewer->ownerRole;
        }

        foreach (['projects.view', 'finance.view', 'cost.view'] as $capability) {
            if ($viewer->allows($capability, self::int($company->id))) {
                return true;
            }
        }

        return false;
    }

    private function importSource(Authority $viewer, FileObject $file): bool
    {
        $scope = DB::table('import_batches')->where('source_file_id', $file->id)->value('company_scope');

        if ($scope === null) {
            return false;
        }

        if ($viewer->ownerRole) {
            return true;
        }

        $companies = array_filter(explode(',', trim(self::text($scope), '{}')), is_numeric(...));

        foreach ($companies as $companyId) {
            if (! $viewer->allows('import.prepare', self::int($companyId))) {
                return false;
            }
        }

        return $companies !== [];
    }

    private function archive(Authority $viewer, FileObject $file): bool
    {
        $companyId = DB::table('legacy_archive_items')->where('file_object_id', $file->id)->value('company_id');

        return $companyId !== null && $viewer->allows('finance.view', self::int($companyId)) && $viewer->allows('evidence.view', self::int($companyId));
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
