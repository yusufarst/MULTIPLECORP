<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\CAT\Data\Quantity;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\DOC\Services\IssuerIdentity;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Modules\PRJ\Services\QuotationSubjects;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PG-014 *Penawaran*'s reads (ROUTE-014; SCR-13; NAV-014; WF-QUO-01; J-QUO-01): the project's quotation in the document
 * composer — its revision chain, each revision's state with *Kedaluwarsa* derived from its validity (L-25) and its
 * issued DOC-01 version, the current revision's lines and the paper of its draft beside the form (D-DS-23; INT-012).
 * `quotation.manage` with `finance.view` edits and issues; `projects.confirm` alone reads and records the client's
 * approval, the values only with `finance.view` (RG-06; PJ-15). A quotation implies no reservation or payment (BR-QUO-03).
 */
final class QuotationWorkspace
{
    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly QuotationSubjects $subjects,
        private readonly IssuerIdentity $identity,
        private readonly ProjectWorkspace $projects,
    ) {}

    public function viewer(int $viewerId): Authority
    {
        return $this->capabilities->for($viewerId);
    }

    /**
     * The project PG-014 and its commands name: unknown to a viewer who may not see it (AZ-11), refused to one who may
     * not open its quotation (ROUTE-014).
     */
    public function open(int $viewerId, string $publicId): Project
    {
        $project = $this->projects->lookup($viewerId, $publicId) ?? throw (new ModelNotFoundException)->setModel(Project::class, [$publicId]);

        return $this->mayOpen($viewerId, $project) ? $project : throw new AuthorizationException;
    }

    /** Whether the viewer may open PG-014: `quotation.manage` with `finance.view`, or `projects.confirm` for the answer (ROUTE-014). */
    public function mayOpen(int $viewerId, Project $project): bool
    {
        $viewer = $this->viewer($viewerId);

        return ($viewer->allows('quotation.manage', $project->company_id) && $viewer->allows('finance.view', $project->company_id))
            || $viewer->allows('projects.confirm', $project->company_id);
    }

    /** @return array<string, mixed> */
    public function screen(int $viewerId, Project $project): array
    {
        $viewer = $this->viewer($viewerId);
        $money = $viewer->allows('finance.view', $project->company_id);
        $manage = $money && $viewer->allows('quotation.manage', $project->company_id);
        $today = BusinessDate::today();
        $header = DB::table('projects as p')->where('p.id', $project->id)->join('companies as c', 'c.id', '=', 'p.company_id')
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->first(['p.public_id', 'p.project_number', 'p.title', 'p.state', 'c.code', 'o.name as client']);
        $revisions = DB::table('quotation_revisions as r')->where('r.project_id', $project->id)
            ->leftJoin('users as u', 'u.id', '=', 'r.decided_by')
            ->leftJoin('document_versions as v', 'v.quotation_revision_id', '=', 'r.id')
            ->leftJoin('documents as d', 'd.id', '=', 'v.document_id')
            ->orderByDesc('r.revision_no')
            ->get(['r.id', 'r.public_id', 'r.revision_no', 'r.state', 'r.valid_until', 'r.decided_at', 'r.decision_reason', 'r.lock_version', 'u.name as decided_by',
                'v.formatted_number', 'v.state as version_state', 'd.public_id as document'])->all();
        $current = $revisions[0] ?? null;
        $state = $header === null ? 'DRAFT' : Row::text($header, 'state');

        return [
            'project' => $header === null ? null : [
                'id' => Row::text($header, 'public_id'),
                'number' => Row::text($header, 'project_number'),
                'title' => Row::text($header, 'title'),
                'company' => Row::text($header, 'code'),
                'client' => Row::text($header, 'client'),
                'state' => $state,
            ],
            'revisions' => array_map(fn (object $row): array => [
                'id' => Row::text($row, 'public_id'),
                'no' => (int) Row::text($row, 'revision_no'),
                'state' => QuotationRecords::shown(Row::text($row, 'state'), Row::nullable($row, 'valid_until'), $today),
                'valid_until' => Row::nullable($row, 'valid_until'),
                'decided_at' => Row::nullable($row, 'decided_at'),
                'decided_by' => Row::nullable($row, 'decided_by'),
                'reason' => Row::nullable($row, 'decision_reason'),
                'number' => Row::nullable($row, 'formatted_number'),
                'document' => Row::nullable($row, 'document'),
            ], $revisions),
            'current' => $current === null ? null : $this->current($current, $money, $project),
            'taxes' => $money ? QuotationRecords::rules($project->company_id) : [],
            'today' => $today,
            'can' => [
                'manage' => $manage && in_array($state, ['DRAFT', 'ACTIVE'], true),
                'approve' => $viewer->allows('projects.confirm', $project->company_id) && in_array($state, ['DRAFT', 'ACTIVE'], true),
                'reject' => $manage && in_array($state, ['DRAFT', 'ACTIVE'], true),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function current(object $revision, bool $money, Project $project): array
    {
        $id = (int) Row::text($revision, 'id');
        $lines = DB::table('quotation_revision_lines as l')->where('l.quotation_revision_id', $id)
            ->join('products as p', 'p.id', '=', 'l.product_id')->join('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->orderBy('l.id')
            ->get(['l.id', 'l.description', 'l.entered_qty', 'l.ratio_to_base', 'l.quantity_scale', 'l.unit_price', 'l.sales_value', 'l.tax_components', 'p.name as product', 'u.code as unit'])->all();
        $paper = null;
        $digest = null;

        if ($money && Row::text($revision, 'state') === 'DRAFT') {
            // The draft's paper from the project's client as it is now; the logo through its company (PJ-19; ROUTE-226).
            $subject = new DocumentSubject(QuotationSubjects::QUOTATION, 'STANDARD', $project->company_id, ['project_id' => $project->id]);
            $identity = $this->identity->of($project->company_id);
            $facts = $this->subjects->facts($subject);
            // The issue carries the digest of the facts this paper shows: a client changed since is a CONFLICT (architecture challenge 15a).
            $digest = DocumentChain::factsDigest($facts);
            $paper = DocumentPaper::compose(QuotationSubjects::QUOTATION, 'STANDARD', $facts, $identity, null, BusinessDate::today(), null, true,
                $identity['assets']['logo'] === null ? null : '/berkas/'.$identity['assets']['logo']['public_id'].'/unduh');
        } elseif ($money) {
            // An issued revision shows the paper it was issued with, never one recomposed from today's client (BR-SN-01; architecture challenge 1b).
            $stored = DB::table('document_versions')->where('quotation_revision_id', $id)->value('payload');
            $payload = is_string($stored) ? json_decode($stored, true) : null;
            $paper = is_array($payload) && is_array($payload['paper'] ?? null) ? $payload['paper'] : null;
        }

        return [
            'id' => Row::text($revision, 'public_id'),
            'no' => (int) Row::text($revision, 'revision_no'),
            'state' => Row::text($revision, 'state'),
            'valid_until' => Row::nullable($revision, 'valid_until'),
            'lock_version' => (int) Row::text($revision, 'lock_version'),
            'lines' => array_map(fn (object $line): array => [
                'handle' => RowHandle::of('quotation_revision_lines', (int) Row::text($line, 'id')),
                'product' => Row::text($line, 'product'),
                'description' => Row::text($line, 'description'),
                'quantity' => Row::text($line, 'entered_qty'),
                'unit' => Row::text($line, 'unit'),
                'ratio' => (int) Row::text($line, 'ratio_to_base'),
                'scale' => (int) Row::text($line, 'quantity_scale'),
                ...($money ? ['price' => Row::text($line, 'unit_price'), 'value' => Row::text($line, 'sales_value'), 'taxes' => self::taxes(Row::text($line, 'tax_components'))] : []),
            ], $lines),
            'paper' => $paper,
            'facts' => $digest,
        ];
    }

    /**
     * The draft's lines the form sent, each by the handle the page showed among the revision's lines — a line left out
     * is removed, so a partial acceptance is the revision to the accepted subset (L-20) —, its quantity in its entered
     * unit converted at its ratio and checked at its product's scale; a price only with `finance.view` (PJ-15).
     *
     * @param  array<int, mixed>  $entered
     * @return array<int, array{quantity: string, quantity_base: string, price: ?string, description: string}>
     */
    public function resolve(int $viewerId, Project $project, string $revision, array $entered): array
    {
        $money = $this->viewer($viewerId)->allows('finance.view', $project->company_id);
        $rows = DB::table('quotation_revision_lines as l')->join('quotation_revisions as r', 'r.id', '=', 'l.quotation_revision_id')
            ->where('r.project_id', $project->id)->where('r.public_id', strtolower($revision))
            ->get(['l.id', 'l.ratio_to_base', 'l.quantity_scale', 'l.description'])->all();
        $known = [];

        foreach ($rows as $row) {
            $known[RowHandle::of('quotation_revision_lines', (int) Row::text($row, 'id'))] = $row;
        }

        $lines = [];
        $errors = [];

        foreach (array_values($entered) as $index => $line) {
            $line = is_array($line) ? $line : [];
            $row = $known[is_string($line['baris'] ?? null) ? $line['baris'] : ''] ?? null;

            if ($row === null) {
                $errors["baris.{$index}.baris"] = self::say('quotations.validation.line');

                continue;
            }

            $quantity = is_string($line['jumlah'] ?? null) ? $line['jumlah'] : '';
            $base = preg_match('/^\d{1,15}(\.\d{1,3})?$/', $quantity) === 1 ? Quantity::toBase($quantity, (int) Row::text($row, 'ratio_to_base')) : null;

            if ($base === null || preg_match('/^0+(\.0+)?$/', $base) === 1 || ! Quantity::atScale($base, (int) Row::text($row, 'quantity_scale'))) {
                $errors["baris.{$index}.jumlah"] = self::say($base !== null && preg_match('/^0+(\.0+)?$/', $base) !== 1 ? 'projects.validation.scale' : 'projects.validation.quantity');

                continue;
            }

            $price = $money && is_string($line['harga'] ?? null) && $line['harga'] !== '' ? $line['harga'] : null;

            if ($money && $price === null) {
                $errors["baris.{$index}.harga"] = self::say('quotations.validation.price');

                continue;
            }

            $description = is_string($line['keterangan'] ?? null) ? trim($line['keterangan']) : '';
            $lines[(int) Row::text($row, 'id')] = ['quantity' => $quantity, 'quantity_base' => $base, 'price' => $price, 'description' => $description === '' ? Row::text($row, 'description') : $description];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // A draft keeps at least one line: one with none could neither be issued nor revised (WF-QUO-01).
        if ($lines === []) {
            throw ValidationException::withMessages(['baris' => self::say('quotations.validation.lines')]);
        }

        return $lines;
    }

    /**
     * MSG-17's refusal of an issue in a period whose DOC-01 numbering has not started: the company's name and, for the
     * Owner, PG-061's address to start it (NM-03).
     *
     * @return array{company: string, numbering: ?string}
     */
    public function numberingOf(int $viewerId, string $project): array
    {
        $company = DB::table('projects as p')->where('p.public_id', strtolower($project))->join('companies as c', 'c.id', '=', 'p.company_id')->first(['c.public_id', 'c.legal_name']);

        return [
            'company' => $company === null ? '' : Row::text($company, 'legal_name'),
            'numbering' => $this->viewer($viewerId)->ownerRole && $company !== null
                ? '/pengaturan/penomoran?'.http_build_query(['perusahaan' => Row::text($company, 'public_id'), 'jenis' => 'doc-01']) : null,
        ];
    }

    private static function say(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    /** @return list<array{type: string, rate: string, amount: string}> */
    private static function taxes(string $json): array
    {
        $decoded = json_decode($json, true);
        $taxes = [];

        foreach (is_array($decoded) ? $decoded : [] as $component) {
            if (is_array($component)) {
                $taxes[] = ['type' => is_string($component['type'] ?? null) ? $component['type'] : '', 'rate' => is_string($component['rate'] ?? null) ? $component['rate'] : '',
                    'amount' => is_string($component['amount'] ?? null) ? $component['amount'] : '0'];
            }
        }

        return $taxes;
    }
}
