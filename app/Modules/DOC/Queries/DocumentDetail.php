<?php

namespace App\Modules\DOC\Queries;

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Modules\DOC\Services\IssuerIdentity;
use App\Modules\DOC\Services\Renditions;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PG-036 *Detail Dokumen*'s reads (ROUTE-036; SCR-35; PJ-15; PJ-19; DC-1–DC-12): a document's metadata — type,
 * numbers, states, dates — with `projects.view` within its company, and with its family's view capability its draft
 * composer's props, its versions' renditions and their reasons. The draft's props are the paper its layout's template
 * composes in the browser (option A): the record's facts its provider supplies, PJ-19's identity assets the viewer may
 * see — the logo, never the stamp, the signature or a number (DC-4; WS-07) —, the entered date and the bank
 * destination, which only the finance family's viewer sees (FV(c)). A voided version is never offered as current.
 */
final class DocumentDetail
{
    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly DocumentSubjects $subjects,
        private readonly IssuerIdentity $identity,
    ) {}

    /** @return array<string, mixed> */
    public function of(int $viewerId, Document $document): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $content = DocumentAccess::mayViewContent($viewer, $document->document_type_code, $document->company_id);
        $company = DB::table('companies')->where('id', $document->company_id)->first(['public_id', 'code', 'legal_name']);
        $versions = DocumentVersion::query()->where('document_id', $document->id)->orderByDesc('version_no')->get();
        $names = DB::table('users')->whereIn('id', $versions->pluck('issued_by')->merge($versions->pluck('voided_by'))->filter()->unique()->values()->all())->pluck('name', 'id')->all();
        $draft = $versions->firstWhere('state', 'DRAFT');
        $issued = $versions->firstWhere('state', 'ISSUED');
        $type = DB::table('document_types')->where('code', $document->document_type_code)->value('name');

        return [
            'document' => [
                'id' => $document->public_id,
                'type' => $document->document_type_code,
                'type_name' => is_string($type) ? $type : $document->document_type_code,
                'mode' => $document->mode,
                'company' => ['id' => is_object($company) ? Row::nullable($company, 'public_id') : null, 'code' => is_object($company) ? Row::nullable($company, 'code') : null, 'name' => is_object($company) ? Row::nullable($company, 'legal_name') : null],
                'prepayment' => $this->prepaymentState($document, $issued),
            ],
            'versions' => $versions->map(fn (DocumentVersion $version): array => $this->version($version, $content, $names))->values()->all(),
            'current' => $issued?->public_id,
            'draft' => $draft === null || ! $content ? null : $this->draft($viewer, $document, $draft),
            'content' => $content,
            'today' => BusinessDate::today(),
            'can' => [
                'issue' => DocumentAccess::mayIssue($viewer, $document->document_type_code, $document->company_id),
                'correct' => DocumentAccess::mayCorrect($viewer, $document->document_type_code, $document->company_id),
            ],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $names
     * @return array<string, mixed>
     */
    private function version(DocumentVersion $version, bool $content, array $names): array
    {
        $latest = $version->state === 'DRAFT' ? null : DocumentRendition::query()->where('document_version_id', $version->id)->orderByDesc('attempt_no')->first();
        $file = $latest !== null && $latest->state === 'READY' && $content
            ? DB::table('file_objects')->where('id', $latest->file_object_id)->value('public_id') : null;
        $name = fn (?int $id): ?string => $id !== null && is_string($names[$id] ?? null) ? $names[$id] : null;

        return [
            'id' => $version->public_id,
            'version_no' => $version->version_no,
            'state' => $version->state,
            'number' => $version->formatted_number,
            'business_date' => $version->business_date?->format('Y-m-d'),
            'issued_by' => $name($version->issued_by),
            'voided_at' => $version->voided_at?->toIso8601String(),
            'voided_by' => $name($version->voided_by),
            'voided_reason' => $content ? $version->voided_reason : null,
            'supersedes' => $version->supersedes_version_id === null ? null : DocumentVersion::query()->whereKey($version->supersedes_version_id)->value('formatted_number'),
            'rendition' => $latest === null ? null : [
                'state' => $latest->state,
                'attempt' => $latest->attempt_no,
                'attempts' => Renditions::attempts(),
                'file' => is_string($file) ? $file : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function draft(Authority $viewer, Document $document, DocumentVersion $draft): array
    {
        $subject = DocumentSubject::of($document);
        $provider = $this->subjects->provider($subject->typeCode, $subject->mode);
        $identity = $this->identity->of($document->company_id);
        $finance = $viewer->allows('finance.view', $document->company_id);
        $banks = $finance && in_array($document->document_type_code, DocumentPaper::BANK_LAYOUTS, true) ? $this->identity->bankAccounts($document->company_id) : [];
        $bank = null;

        foreach ($banks as $account) {
            if ($account['id'] === $draft->company_bank_account_id) {
                $bank = $account;
            }
        }

        // PJ-19: the logo through its owning company at request time (ROUTE-226), never the stamp or the signature.
        $paper = $provider === null ? null : DocumentPaper::compose($document->document_type_code, $document->mode, $provider->facts($subject), $identity,
            $bank === null ? null : ['bank_name' => $bank['bank_name'], 'account_number' => $bank['account_number'], 'account_holder' => $bank['account_holder'], 'branch' => $bank['branch']], $draft->business_date?->format('Y-m-d') ?? BusinessDate::today(), null, true,
            $identity['assets']['logo'] === null ? null : '/berkas/'.$identity['assets']['logo']['public_id'].'/unduh');

        return [
            'id' => $draft->public_id,
            'version_no' => $draft->version_no,
            'lock_version' => $draft->lock_version,
            'business_date' => $draft->business_date?->format('Y-m-d'),
            'bank_account' => $bank['handle'] ?? null,
            'bank_accounts' => array_map(fn (array $account): array => array_diff_key($account, ['id' => true]), $banks),
            'states_bank' => in_array($document->document_type_code, DocumentPaper::BANK_LAYOUTS, true) && $finance,
            'revision_of' => $draft->supersedes_version_id === null ? null : DocumentVersion::query()->whereKey($draft->supersedes_version_id)->value('formatted_number'),
            'paper' => $paper,
        ];
    }

    private function prepaymentState(Document $document, ?DocumentVersion $issued): ?string
    {
        if ($document->document_type_code !== 'DOC-05' || $document->mode !== 'PRE_PAYMENT' || $issued === null) {
            return null;
        }

        return DB::table('prepayment_kuitansi_links')->where('document_id', $document->id)->where('state', 'LINKED')->exists() ? 'linked' : 'open_not_linked';
    }
}
