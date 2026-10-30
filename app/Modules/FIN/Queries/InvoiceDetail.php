<?php

namespace App\Modules\FIN\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Data\InvoicePosition;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceCorrections;
use App\Modules\FIN\Services\InvoiceSubjects;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PG-043 *Detail Invoice* (ROUTE-043; NAV-043; SCR-39; J-FIN-01; J-FIN-08): the lines and tax of its current version, the position block keeping sales value,
 * billed sum, applied, settled and outstanding apart (PT-24; CALC-05; CALC-08), the billing act, the applications and settlements a correction must dispose
 * (ST-07), its documents and pre-payment Kuitansi. Each correction's control with `invoice.correct`, the Kuitansi's with `documents.issue` and `payment.record`.
 * Amounts from the record, never a payload (DATABASE §10). In at most eight statements, two groups (QB-09).
 */
final class InvoiceDetail
{
    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** The invoice by public id within the viewer's scope and `finance.view`, else null (AZ-11; PJ-23). */
    public function find(int $viewerId, string $publicId): ?Invoice
    {
        $viewer = $this->capabilities->for($viewerId);
        $invoice = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? Invoice::query()->where('public_id', strtolower($publicId))->first() : null;

        return $invoice !== null && $viewer->allows('finance.view', $invoice->company_id) ? $invoice : null;
    }

    /** @return array<string, mixed> */
    public function of(int $viewerId, Invoice $invoice): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $company = $invoice->company_id;
        $today = BusinessDate::today();
        $header = DB::table('invoices as i')->where('i.id', $invoice->id)
            ->join('invoice_versions as v', 'v.id', '=', 'i.current_version_id')->join('companies as c', 'c.id', '=', 'i.company_id')->join('client_organizations as o', 'o.id', '=', 'i.client_organization_id')
            ->leftJoin('projects as p', 'p.id', '=', 'i.project_id')->leftJoin('invoice_balances as b', 'b.invoice_id', '=', 'i.id')->leftJoin('invoice_billing_acts as a', 'a.id', '=', 'b.current_billing_act_id')
            ->leftJoin('invoices as r', 'r.id', '=', 'i.replaces_invoice_id')->leftJoin('invoices as n', 'n.replaces_invoice_id', '=', 'i.id')
            ->selectRaw('i.state, i.voided_reason, i.void_basis, i.lock_version, i.origin, v.id as version, v.version_no, v.kind, v.business_date, v.sales_value, v.output_tax_value, v.gross_value, v.client_snapshot, c.code as company, o.name as client, p.public_id as project, p.project_number, p.title,
                b.applied_amount, b.fee_settled_amount, b.tax_settled_amount, b.written_off_amount, b.outstanding, b.current_billing_act_id, b.current_due_date, a.billed_at, a.kind as act_kind, r.public_id as replaces, n.public_id as replaced_by,
                exists (select 1 from receivable_dispute_holds h where h.invoice_id = i.id and h.cleared_at is null) as on_hold')
            ->first();
        $issued = $header !== null && Row::text($header, 'state') === 'ISSUED';
        $correct = $issued && $invoice->origin === 'LIVE' && $viewer->allows('invoice.correct', $company);
        $version = $header === null ? 0 : (int) Row::text($header, 'version');

        return [
            'invoice' => $header === null ? null : [
                'id' => $invoice->public_id,
                'number' => InvoiceSubjects::number($invoice->id),
                'state' => InvoicePosition::of(Row::text($header, 'state'), Row::nullable($header, 'current_billing_act_id') !== null, Row::nullable($header, 'current_due_date'), $today, Row::nullable($header, 'outstanding') ?? '0.00', Row::nullable($header, 'written_off_amount') ?? '0.00', ($header->on_hold ?? false) === true),
                'lock_version' => (int) Row::text($header, 'lock_version'),
                'version_no' => (int) Row::text($header, 'version_no'),
                'kind' => Row::text($header, 'kind'),
                'date' => Row::text($header, 'business_date'),
                'company' => Row::text($header, 'company'),
                'client' => Row::text($header, 'client'),
                'project' => Row::nullable($header, 'project'),
                'project_number' => Row::nullable($header, 'project_number'),
                'title' => Row::nullable($header, 'title'),
                'void_basis' => Row::nullable($header, 'void_basis'),
                'void_reason' => Row::nullable($header, 'voided_reason'),
                'replaces' => Row::nullable($header, 'replaces'),
                'replaced_by' => Row::nullable($header, 'replaced_by'),
                'billed_at' => Row::nullable($header, 'billed_at'),
                'due_date' => Row::nullable($header, 'current_due_date'),
                'inherited' => Row::nullable($header, 'act_kind') === 'INHERITED',
            ],
            'position' => $header === null ? null : [
                'sales' => Row::text($header, 'sales_value'),
                'tax' => Row::text($header, 'output_tax_value'),
                'gross' => Row::text($header, 'gross_value'),
                'applied' => Row::nullable($header, 'applied_amount') ?? '0.00',
                'fee_settled' => Row::nullable($header, 'fee_settled_amount') ?? '0.00',
                'tax_settled' => Row::nullable($header, 'tax_settled_amount') ?? '0.00',
                'written_off' => Row::nullable($header, 'written_off_amount') ?? '0.00',
                'outstanding' => Row::nullable($header, 'outstanding') ?? '0.00',
                'billed' => Row::nullable($header, 'current_billing_act_id') !== null,
            ],
            'lines' => array_values(array_map(fn (object $line): array => [
                'description' => Row::text($line, 'description'),
                'quantity' => Row::nullable($line, 'entered_qty'),
                'unit' => Row::nullable($line, 'code'),
                'price' => Row::nullable($line, 'unit_price'),
                'value' => Row::text($line, 'line_sales_value'),
            ], DB::table('invoice_version_lines as l')->leftJoin('units as u', 'u.id', '=', 'l.entered_unit_id')->where('l.invoice_version_id', $version)->orderBy('l.id')->get(['l.description', 'l.entered_qty', 'u.code', 'l.unit_price', 'l.line_sales_value'])->all())),
            'taxes' => array_values(array_map(fn (object $tax): array => ['code' => Row::text($tax, 'code'), 'rate' => Row::text($tax, 'rate_percent'), 'base' => Row::text($tax, 'base_amount'), 'amount' => Row::text($tax, 'amount')],
                DB::table('invoice_tax_components as t')->join('tax_types as y', 'y.id', '=', 't.tax_type_id')->where('t.invoice_version_id', $version)->orderBy('y.code')->get(['y.code', 't.rate_percent', 't.base_amount', 't.amount'])->all())),
            'reductions' => self::reductions($invoice->id),
            'documents' => self::documents($invoice->id),
            'kuitansi' => self::kuitansi($invoice->id),
            'payments' => $viewer->allows('payment.record', $company) ? self::linkable($invoice->id) : [],
            'bases' => $correct ? self::bases($invoice) : ['cases' => [], 'confirmations' => [], 'cancellations' => [], 'transitions' => []],
            'evidence' => $correct ? self::evidence($viewer, $company, $invoice->project_id ?? 0) : [],
            'today' => $today,
            'can' => [
                'correct' => $correct,
                'supersede' => $correct && $viewer->allows('writeoff.supersede', $company),
                'kuitansi' => $issued && DocumentAccess::mayIssue($viewer, InvoiceSubjects::KUITANSI, $company),
                'link' => $issued && $viewer->allows('payment.record', $company),
                'nota' => $issued && DocumentAccess::mayIssue($viewer, InvoiceSubjects::NOTA, $company),
            ],
        ];
    }

    /**
     * What a correction must dispose, each by the handle PG-043 shows (ST-07).
     *
     * @return array{applications: list<array<string, mixed>>, settlements: list<array<string, mixed>>, writeoffs: list<array<string, mixed>>}
     */
    public static function reductions(int $invoiceId): array
    {
        $applications = [];

        foreach (DB::table('payment_applications as a')->where('a.invoice_id', $invoiceId)->where('a.state', 'ACTIVE')->leftJoin('payments as p', 'p.id', '=', 'a.payment_id')->orderBy('a.id')->get(['a.id', 'a.amount', 'a.business_date', 'p.public_id as payment']) as $row) {
            $applications[] = ['handle' => RowHandle::of('payment_applications', (int) Row::text($row, 'id')), 'amount' => Row::text($row, 'amount'), 'date' => Row::text($row, 'business_date'), 'payment' => Row::nullable($row, 'payment')];
        }

        $settlements = [];

        foreach (DB::table('deduction_settlements')->where('invoice_id', $invoiceId)->where('state', 'ACTIVE')->orderBy('id')->get(['id', 'amount', 'business_date', 'settlement_class', 'tax_direction']) as $row) {
            $settlements[] = ['handle' => RowHandle::of('deduction_settlements', (int) Row::text($row, 'id')), 'amount' => Row::text($row, 'amount'), 'date' => Row::text($row, 'business_date'), 'class' => Row::text($row, 'settlement_class'), 'direction' => Row::nullable($row, 'tax_direction')];
        }

        $writeoffs = [];

        foreach (array_keys(InvoiceCorrections::reductions($invoiceId)['writeoffs']) as $id) {
            $row = DB::table('receivable_writeoffs')->where('id', $id)->first(['amount', 'business_date']);
            $writeoffs[] = ['handle' => RowHandle::of('receivable_writeoffs', $id), 'amount' => is_object($row) ? Row::text($row, 'amount') : '0.00', 'date' => is_object($row) ? Row::text($row, 'business_date') : ''];
        }

        return ['applications' => $applications, 'settlements' => $settlements, 'writeoffs' => $writeoffs];
    }

    /** @return list<array<string, mixed>> The layouts' documents with their current state. */
    private static function documents(int $invoiceId): array
    {
        $documents = [];

        foreach (DB::table('documents as d')->where('d.invoice_id', $invoiceId)->whereIn('d.document_type_code', InvoiceSubjects::LAYOUTS)
            ->leftJoin('document_versions as v', fn (JoinClause $join) => $join->on('v.document_id', '=', 'd.id')->whereIn('v.state', ['ISSUED', 'DRAFT']))->orderBy('d.id')
            ->get(['d.public_id', 'd.document_type_code', 'v.state', 'v.formatted_number']) as $row) {
            $documents[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'document_type_code'), 'state' => Row::nullable($row, 'state') ?? 'VOIDED', 'number' => Row::nullable($row, 'formatted_number')];
        }

        return $documents;
    }

    /** @return list<array<string, mixed>> The invoice's pre-payment Kuitansi: open or linked, and the cause of an unlink (QS-19; CM-26). */
    private static function kuitansi(int $invoiceId): array
    {
        $rows = [];

        foreach (DB::table('documents as d')->where('d.invoice_id', $invoiceId)->where('d.document_type_code', 'DOC-05')->where('d.mode', 'PRE_PAYMENT')
            ->join('document_versions as v', 'v.document_id', '=', 'd.id')->whereIn('v.state', ['ISSUED', 'DRAFT'])->orderBy('d.id')
            ->selectRaw("d.public_id, v.state, v.formatted_number, v.kuitansi_amount,
                exists (select 1 from prepayment_kuitansi_links k where k.document_id = d.id and k.state = 'LINKED') as linked,
                (select k.unlink_reason from prepayment_kuitansi_links k where k.document_id = d.id and k.state = 'UNLINKED' order by k.id desc limit 1) as cause")
            ->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'state' => Row::text($row, 'state'), 'number' => Row::nullable($row, 'formatted_number'), 'amount' => Row::nullable($row, 'kuitansi_amount'), 'linked' => ($row->linked ?? false) === true, 'cause' => Row::nullable($row, 'cause')];
        }

        return $rows;
    }

    /** @return list<array{id: string, amount: string, date: string}> The payments applied to the invoice with no Kuitansi yet (AX-31). */
    private static function linkable(int $invoiceId): array
    {
        $rows = [];

        foreach (DB::table('payment_applications as a')->where('a.invoice_id', $invoiceId)->where('a.state', 'ACTIVE')->join('payments as p', 'p.id', '=', 'a.payment_id')
            ->whereRaw("not exists (select 1 from prepayment_kuitansi_links k where k.invoice_id = a.invoice_id and k.payment_id = a.payment_id and k.state = 'LINKED')")
            ->groupBy('p.public_id')->selectRaw('p.public_id, sum(a.amount) as amount, min(a.business_date) as date')->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'amount' => Row::text($row, 'amount'), 'date' => Row::text($row, 'date')];
        }

        return $rows;
    }

    /**
     * The rows a typed basis may name, of the invoice's company or project (C-56).
     *
     * @return array{cases: list<array{handle: string, label: string}>, confirmations: list<array{handle: string, label: string}>, cancellations: list<array{handle: string, label: string}>, transitions: list<array{handle: string, label: string}>}
     */
    private static function bases(Invoice $invoice): array
    {
        $project = $invoice->project_id ?? 0;
        $rows = function (string $table, Collection $found, Closure $label): array {
            $listed = [];

            /** @var object $row */
            foreach ($found as $row) {
                $text = $label($row);
                $listed[] = ['handle' => RowHandle::of($table, (int) Row::text($row, 'id')), 'label' => is_string($text) ? $text : ''];
            }

            return $listed;
        };

        return [
            'cases' => $rows('correction_cases', DB::table('correction_cases')->where('company_id', $invoice->company_id)->where('project_id', $project)->whereIn('case_kind', InvoiceCorrection::RETURN_CASES)->whereIn('state', ['OPEN', 'CLOSED'])->orderByDesc('id')->limit(20)->get(['id', 'case_kind', 'recorded_at']), fn (object $row): string => Row::text($row, 'case_kind').' · '.substr(Row::text($row, 'recorded_at'), 0, 10)),
            'confirmations' => $rows('commercial_confirmations', DB::table('commercial_confirmations')->where('project_id', $project)->orderByDesc('id')->limit(20)->get(['id', 'kind', 'business_date']), fn (object $row): string => Row::text($row, 'kind').' · '.Row::text($row, 'business_date')),
            'cancellations' => $rows('line_scope_cancellations', DB::table('line_scope_cancellations as c')->join('project_lines as l', 'l.id', '=', 'c.project_line_id')->where('l.project_id', $project)->orderByDesc('c.id')->limit(20)->get(['c.id', 'c.qty_base', 'c.business_date', 'l.line_no']), fn (object $row): string => '#'.Row::text($row, 'line_no').' · '.Row::text($row, 'qty_base').' · '.Row::text($row, 'business_date')),
            'transitions' => $rows('project_state_transitions', DB::table('project_state_transitions')->where('project_id', $project)->where('transition_kind', 'CANCEL')->orderByDesc('id')->limit(5)->get(['id', 'recorded_at']), fn (object $row): string => substr(Row::text($row, 'recorded_at'), 0, 10)),
        ];
    }

    /** @return list<array{id: string, type: string, issuer: string, reference: ?string, date: string}> The evidence linked to the invoice's records an entry error may rest on (DIR-092 (2); PJ-08). */
    private static function evidence(Authority $viewer, int $company, int $project): array
    {
        $options = [];

        foreach (DB::table('evidence_documents as e')->where('e.company_id', $company)->whereIn('e.evidence_type', InvoiceCorrections::EVIDENCE)
            ->whereExists(fn (Builder $link) => $link->from('evidence_links as k')->whereColumn('k.evidence_document_id', 'e.id')->whereNull('k.unlinked_at')
                ->where(fn (Builder $target) => $target->where('k.project_id', $project)->orWhereIn('k.commercial_confirmation_id', DB::table('commercial_confirmations')->where('project_id', $project)->select('id'))))
            ->orderByDesc('e.id')->limit(100)->get(['public_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date']) as $row) {
            if (EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), $company)) {
                $options[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type'), 'issuer' => Row::text($row, 'declared_issuer'), 'reference' => Row::nullable($row, 'issuer_reference'), 'date' => Row::text($row, 'document_date')];
            }
        }

        return $options;
    }
}
