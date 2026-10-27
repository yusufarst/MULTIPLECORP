<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Data\StoredUpload;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Jobs\ProcessImage;
use App\Modules\DOC\Services\UploadLimited;
use App\Modules\DOC\Services\UploadRefused;
use App\Modules\DOC\Services\Uploads;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Queries\CompanyDirectory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * `DOC StoreUpload` (ROUTE-228; INT-013, INT-228; IP-16 steps 1–2): the one upload of every upload field, each purpose
 * under its capability and allowlist — evidence `evidence.upload` with the family's view capability in its company,
 * or for pool evidence the pool's (PJ-08); a company's identity asset `companies.manage`; a product image
 * `products.edit`; an import source `import.prepare` with every batch company in scope and a legacy archive item with
 * its company (PJ-20) —, running TASK-022's pipeline outside every business transaction (FL-01–FL-06; FL-11) with
 * WS-12's bounds, carrying no `command_id` (RB-03). It answers the stored file for the command that follows, whose
 * actor must be this uploader (DIR-089 (5)); a company outside the account's scope answers as unknown (AZ-11).
 */
final class StoreUpload
{
    public function __construct(
        private readonly Uploads $uploads,
        private readonly EffectiveCapabilities $capabilities,
        private readonly CompanyDirectory $companies,
    ) {}

    /**
     * @param  list<string>  $companies  the public identifiers of the file's company, or of an import batch's companies
     */
    public function run(int $uploaderId, string $path, string $clientName, FilePurpose $purpose, array $companies, bool $pool, ?string $evidenceType): StoredUpload
    {
        $actor = $this->capabilities->for($uploaderId);
        $scoped = $this->companies->idsOf($actor->scope, $companies);

        if (count($scoped) !== count(array_unique($companies))) {
            throw new ModelNotFoundException;
        }

        $companyId = $this->authorize($actor, $purpose, $scoped, $pool, $evidenceType);

        try {
            $file = $this->uploads->accept($path, $clientName, $purpose, $companyId, $uploaderId);
        } catch (UploadRefused) {
            return StoredUpload::refused($purpose);
        } catch (UploadLimited $limited) {
            return StoredUpload::limited($purpose, $limited->minutes);
        }

        ProcessImage::owedFor($file);

        return StoredUpload::stored($file->public_id, $file->original_filename, $purpose);
    }

    /**
     * The purpose's capability (PJ-08; PJ-20), and the company its file belongs to (DATABASE §22; C-32).
     *
     * @param  list<int>  $companies
     */
    private function authorize(Authority $actor, FilePurpose $purpose, array $companies, bool $pool, ?string $evidenceType): ?int
    {
        $one = count($companies) === 1 ? $companies[0] : null;
        $allowed = match ($purpose) {
            FilePurpose::Upload => $pool
                ? $companies === [] && EvidenceTypes::mayHandle($actor, $evidenceType ?? EvidenceTypes::POOL_TYPES[0], null)
                : $one !== null && ($evidenceType !== null ? EvidenceTypes::mayHandle($actor, $evidenceType, $one) : EvidenceTypes::choosable($actor, $one) !== []),
            FilePurpose::IdentityAsset => $one !== null && $actor->allows('companies.manage', $one),
            FilePurpose::ProductImage => $companies === [] && $actor->allows('products.edit'),
            FilePurpose::ImportSource => $companies !== [] && $actor->has('import.prepare'),
            FilePurpose::Archive => $one !== null && $actor->allows('import.prepare', $one),
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException;
        }

        return in_array($purpose, [FilePurpose::Upload, FilePurpose::IdentityAsset, FilePurpose::Archive], true) && ! $pool ? $one : null;
    }
}
