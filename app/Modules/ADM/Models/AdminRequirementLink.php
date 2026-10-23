<?php

namespace App\Modules\ADM\Models;

use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ADM\AdminRequirementLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What satisfies a requirement — a document version or an evidence of its company (DATABASE §4.11
 * `admin_requirement_links`, SR, unlink set once): a client original only by an evidence (FS-12).
 *
 * @property int $id
 * @property int $admin_requirement_id
 * @property int $company_id
 * @property int|null $document_version_id
 * @property int|null $evidence_document_id
 * @property int $linked_by
 * @property CarbonImmutable|null $unlinked_at
 * @property int|null $unlinked_by
 * @property string|null $unlink_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(AdminRequirementLinkFactory::class)]
class AdminRequirementLink extends Model
{
    /** @use HasFactory<AdminRequirementLinkFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<AdminRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(AdminRequirement::class, 'admin_requirement_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'evidence_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    /** @return BelongsTo<User, $this> */
    public function unlinkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlinked_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'admin_requirement_id' => 'integer',
            'company_id' => 'integer',
            'document_version_id' => 'integer',
            'evidence_document_id' => 'integer',
            'linked_by' => 'integer',
            'unlinked_at' => 'immutable_datetime',
            'unlinked_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
