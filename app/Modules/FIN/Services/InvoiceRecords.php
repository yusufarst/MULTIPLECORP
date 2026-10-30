<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Data\InvoiceValues;
use App\Modules\FIN\Models\Invoice;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Services\DemandPosting;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * The invoice record (WF-FIN-01; L-16; L-26; DATABASE §4.12, §11.2): a draft's version with its lines and tax components, composed
 * from the project's confirmed lines or as a value-only DP or termin, each save at the root's `lock_version` (ST-01; M-38); and the
 * move to ISSUED — the values fixed at the business date, the guard initialized, the project's invoiced value raised within its cap
 * (C-31). Issuing records the agreed value only: no Cash-In, no active receivable, no stock (BR-FIN-02; BR-XD-04).
 */
final class InvoiceRecords
{
    public function __construct(
        private readonly InvoiceTaxes $taxes,
        private readonly ReceivablePosting $receivable,
        private readonly DemandPosting $demand,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * A new DRAFT invoice of the project with its first version; `replaces` names the invoice a correction replaces, set only here.
     *
     * @param  array{kind: string, business_date: string, lines: list<array<string, mixed>>, taxes: array<string, string>}  $entered
     */
    public function create(CommandContext $context, ProjectRef $project, int $clientId, array $entered, ?int $replaces = null, string $basis = 'INITIAL', int $commandLine = 1): int
    {
        $now = $context->now();
        $invoice = new Invoice;
        $invoice->forceFill([
            'company_id' => $project->companyId, 'project_id' => $project->id, 'client_organization_id' => $clientId, 'origin' => 'LIVE', 'state' => 'DRAFT',
            'replaces_invoice_id' => $replaces, 'command_id' => $context->request->commandId, 'command_line_no' => $commandLine, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
        ])->save();
        $version = DB::table('invoice_versions')->insertGetId([
            'invoice_id' => $invoice->id, 'company_id' => $project->companyId, 'version_no' => 1, 'state' => 'DRAFT', 'kind' => $entered['kind'], 'business_date' => $entered['business_date'],
            'sales_value' => '0.00', 'output_tax_value' => '0.00', 'client_snapshot' => json_encode(self::clientSnapshot($project->id), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'revision_basis' => 'INITIAL',
            'command_id' => $context->request->commandId, 'command_line_no' => $commandLine, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
        ]);
        DB::table('invoices')->where('id', $invoice->id)->update(['current_version_id' => $version, 'updated_at' => $now]);
        $this->writeContent($context, $project, $invoice->id, $version, $entered, $commandLine * 1000);

        return $invoice->id;
    }

    /**
     * ROUTE-241's save of an open draft, its lines and taxes replaced, the root's version moved.
     *
     * @param  array{kind: string, business_date: string, lines: list<array<string, mixed>>, taxes: array<string, string>}  $entered
     */
    public function saveDraft(CommandContext $context, ProjectRef $project, int $invoiceId, int $versionId, int $lockVersion, array $entered): void
    {
        $now = $context->now();
        DB::table('invoice_tax_components')->where('invoice_version_id', $versionId)->delete();
        DB::table('invoice_version_lines')->where('invoice_version_id', $versionId)->delete();
        DB::table('invoice_versions')->where('id', $versionId)->where('state', 'DRAFT')->update(['kind' => $entered['kind'], 'business_date' => $entered['business_date'], 'updated_at' => $now]);
        $this->writeContent($context, $project, $invoiceId, $versionId, $entered, 1000);
        $saved = DB::table('invoices')->where('id', $invoiceId)->where('lock_version', $lockVersion)->update(['lock_version' => $lockVersion + 1, 'updated_at' => $now]);

        if ($saved !== 1) {
            throw Refused::conflict('ST-01');
        }
    }

    /**
     * The version's lines and tax components, its values written from them; the first line's number leaves room for several rows.
     *
     * @param  array{kind: string, business_date: string, lines: list<array<string, mixed>>, taxes: array<string, string>}  $entered
     */
    public function writeContent(CommandContext $context, ProjectRef $project, int $invoiceId, int $versionId, array $entered, int $firstLine): void
    {
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $rows = [];
        $sales = Money::zero();

        foreach ($entered['kind'] === 'ITEMIZED' ? self::itemized($project->id, $entered['lines']) : self::valueOnly($entered['lines']) as $index => $line) {
            $sales = $sales->plus(Money::of(is_string($line['line_sales_value'] ?? null) ? $line['line_sales_value'] : '0'));
            $rows[] = [...$line, 'invoice_version_id' => $versionId, 'company_id' => $project->companyId, 'command_id' => $commandId, 'command_line_no' => $firstLine + $index, 'recorded_at' => $now, 'recorded_by' => $actor];
        }

        if ($rows !== []) {
            DB::table('invoice_version_lines')->insert($rows);
        }

        $components = $this->taxes->components($project->companyId, $project->id, $entered['kind'], $sales->toString(), $entered['business_date'], $entered['taxes'], $invoiceId);
        $tax = Money::zero();
        $taxRows = [];

        foreach ($components as $index => $component) {
            $tax = $tax->plus(Money::of($component['amount']));
            $taxRows[] = ['invoice_version_id' => $versionId, 'tax_type_id' => $component['tax_type_id'], 'treatment_rule_id' => $component['treatment_rule_id'], 'treatment' => $component['treatment'],
                'rate_percent' => $component['rate_percent'], 'base_amount' => $component['base_amount'], 'amount' => $component['amount'],
                'command_id' => $commandId, 'command_line_no' => $firstLine + 500 + $index, 'recorded_at' => $now, 'recorded_by' => $actor];
        }

        if ($taxRows !== []) {
            DB::table('invoice_tax_components')->insert($taxRows);
        }

        DB::table('invoice_versions')->where('id', $versionId)->update(['sales_value' => $sales->toString(), 'output_tax_value' => $tax->toString(), 'updated_at' => $now]);
    }

    /**
     * The move to ISSUED: the business date's treatment resolved again where the date moved, the cap re-read under its lock (C-31), the
     * version and the root ISSUED, the guard born, the invoiced value raised. The act of billing stays apart (TASK-050).
     *
     * @return array{sales: string, tax: string, gross: string}
     */
    public function markIssued(CommandContext $context, ProjectRef $project, int $invoiceId, int $versionId, int $lockVersion, string $businessDate, bool $prefilled, ?int $actId = null, ?string $dueDate = null, string $released = '0.00'): array
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $columns = ['kind', 'business_date', 'sales_value', 'output_tax_value', 'gross_value'];
        $values = DB::table('invoice_versions')->where('id', $versionId)->first($columns);

        if (is_object($values) && Row::text($values, 'business_date') !== $businessDate) {
            $this->redate($context, $project, $invoiceId, $versionId, Row::text($values, 'kind'), $businessDate);
            $values = DB::table('invoice_versions')->where('id', $versionId)->first($columns);
        }

        $sales = is_object($values) ? Row::text($values, 'sales_value') : '0.00';
        $tax = is_object($values) ? Row::text($values, 'output_tax_value') : '0.00';
        $gross = is_object($values) ? Row::text($values, 'gross_value') : '0.00';

        if (! Money::of($sales)->isPositive()) {
            throw Refused::rejected('L-16', ['reason' => 'no_value']);
        }

        $commercial = self::commercial($project->id);

        if (! InvoiceValues::withinCap($commercial['confirmed'], $commercial['invoiced'], $sales, $released)) {
            throw Refused::rejected('C-31', ['remaining' => InvoiceValues::remaining($commercial['confirmed'], Money::of($commercial['invoiced'])->minus(Money::of($released))->toString())]);
        }

        $now = $context->now();
        DB::table('invoice_versions')->where('id', $versionId)->update(['state' => 'ISSUED', 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now]);
        $issued = DB::table('invoices')->where('id', $invoiceId)->where('state', 'DRAFT')->where('lock_version', $lockVersion)->update(['state' => 'ISSUED', 'lock_version' => $lockVersion + 1, 'updated_at' => $now]);

        if ($issued !== 1) {
            throw Refused::conflict('ST-01');
        }

        $this->receivable->bear($context, $invoiceId, $project->companyId, $sales, $tax, $gross, $actId, $dueDate);
        // One move of the project's guard, net of the invoice it replaces (GU-01; C-31).
        $this->demand->invoiced($context, $project->id, Money::of($sales)->minus(Money::of($released))->toString());

        return ['sales' => $sales, 'tax' => $tax, 'gross' => $gross];
    }

    /**
     * The project's confirmed and invoiced value under LK-20; none before its confirmation.
     *
     * @return array{confirmed: string, invoiced: string}
     */
    public static function commercial(int $projectId): array
    {
        $row = DB::table('project_commercial_balances')->where('project_id', $projectId)->first(['confirmed_sales_value', 'invoiced_sales_value']);

        return ['confirmed' => $row === null ? '0.00' : Row::text($row, 'confirmed_sales_value'), 'invoiced' => $row === null ? '0.00' : Row::text($row, 'invoiced_sales_value')];
    }

    /** @return array<string, ?string> The organization, unit, address and PIC as used (AC-02; DATABASE §14). */
    public static function clientSnapshot(int $projectId): array
    {
        $header = DB::table('projects as p')->where('p.id', $projectId)
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->leftJoin('client_units as u', 'u.id', '=', 'p.client_unit_id')
            ->leftJoin('client_addresses as a', 'a.id', '=', 'p.client_address_id')
            ->leftJoin('client_contacts as k', 'k.id', '=', 'p.client_contact_id')
            ->first(['o.name as organization', 'u.name as unit', 'a.address_lines', 'a.city', 'k.name as contact']);

        return $header === null ? ['organization' => null] : [
            'organization' => Row::nullable($header, 'organization'), 'unit' => Row::nullable($header, 'unit'), 'address' => Row::nullable($header, 'address_lines'),
            'city' => Row::nullable($header, 'city'), 'contact' => Row::nullable($header, 'contact'),
        ];
    }

    /** The draft re-resolved at the date the issue takes: the rules of that day, a stated amount kept where its rule did not change. */
    private function redate(CommandContext $context, ProjectRef $project, int $invoiceId, int $versionId, string $kind, string $businessDate): void
    {
        $stated = [];

        foreach (DB::table('invoice_tax_components as t')->join('tax_types as y', 'y.id', '=', 't.tax_type_id')->where('t.invoice_version_id', $versionId)->get(['y.code', 't.amount', 't.treatment_rule_id']) as $row) {
            $stated[Row::text($row, 'code')] = ['amount' => Row::text($row, 'amount'), 'rule' => (int) Row::text($row, 'treatment_rule_id')];
        }

        $value = DB::table('invoice_versions')->where('id', $versionId)->value('sales_value');
        $sales = is_string($value) ? $value : '0.00';
        $proposed = $this->taxes->components($project->companyId, $project->id, $kind, $sales, $businessDate, [], $invoiceId);
        $kept = [];

        foreach ($proposed as $component) {
            if (($stated[$component['code']]['rule'] ?? null) === $component['treatment_rule_id']) {
                $kept[$component['code']] = $stated[$component['code']]['amount'];
            }
        }

        $components = $this->taxes->components($project->companyId, $project->id, $kind, $sales, $businessDate, $kept, $invoiceId);
        $now = $context->now();
        DB::table('invoice_tax_components')->where('invoice_version_id', $versionId)->delete();
        $tax = Money::zero();

        foreach ($components as $index => $component) {
            $tax = $tax->plus(Money::of($component['amount']));
            DB::table('invoice_tax_components')->insert(['invoice_version_id' => $versionId, 'tax_type_id' => $component['tax_type_id'], 'treatment_rule_id' => $component['treatment_rule_id'], 'treatment' => $component['treatment'],
                'rate_percent' => $component['rate_percent'], 'base_amount' => $component['base_amount'], 'amount' => $component['amount'],
                'command_id' => $context->request->commandId, 'command_line_no' => 9000 + $index, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId]);
        }

        DB::table('invoice_versions')->where('id', $versionId)->update(['business_date' => $businessDate, 'output_tax_value' => $tax->toString(), 'updated_at' => $now]);
    }

    /**
     * Confirmed lines as the invoice's itemized lines: the quantity at the pin's unit and price, its value exact on the pin's whole.
     *
     * @param  list<array<string, mixed>>  $lines  each `line` (project line) and `quantity`
     * @return list<array<string, mixed>>
     */
    private static function itemized(int $projectId, array $lines): array
    {
        $ids = array_map(fn (array $line): int => is_numeric($line['line'] ?? null) ? (int) $line['line'] : 0, $lines);
        $pins = [];

        foreach (DB::table('project_lines as l')->where('l.project_id', $projectId)->whereIn('l.id', $ids)->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')
            ->get(['l.id', 'l.product_id', 'l.quantity_scale', 'p.description', 'p.entered_qty', 'p.entered_unit_id', 'p.ratio_to_base', 'p.unit_price', 'p.sales_value']) as $pin) {
            $pins[(int) Row::text($pin, 'id')] = $pin;
        }

        $rows = [];

        foreach ($lines as $line) {
            $id = is_numeric($line['line'] ?? null) ? (int) $line['line'] : 0;
            $pin = $pins[$id] ?? throw Refused::rejected('L-16', ['reason' => 'unconfirmed_line']);
            $quantity = is_string($line['quantity'] ?? null) ? $line['quantity'] : '0';
            $base = BigDecimal::of($quantity)->multipliedBy(Row::text($pin, 'ratio_to_base'));
            $whole = BigDecimal::of($quantity)->isEqualTo(Row::text($pin, 'entered_qty'));
            $value = $whole ? Row::text($pin, 'sales_value') : (string) BigDecimal::of($quantity)->multipliedBy(Row::text($pin, 'unit_price'))->toScale(2, RoundingMode::HalfUp);
            $rows[] = [
                'project_line_id' => $id, 'product_id' => (int) Row::text($pin, 'product_id'), 'quantity_scale' => (int) Row::text($pin, 'quantity_scale'),
                'description' => is_string($line['description'] ?? null) && trim($line['description']) !== '' ? trim($line['description']) : Row::text($pin, 'description'),
                'entered_qty' => $quantity, 'entered_unit_id' => (int) Row::text($pin, 'entered_unit_id'), 'ratio_to_base' => (int) Row::text($pin, 'ratio_to_base'),
                'qty_base' => (string) $base, 'unit_price' => Row::text($pin, 'unit_price'), 'line_sales_value' => $value,
            ];
        }

        return $rows;
    }

    /**
     * A value-only DP or termin: a description and a value, no quantity (L-16).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private static function valueOnly(array $lines): array
    {
        return array_map(fn (array $line): array => [
            'project_line_id' => null, 'product_id' => null, 'quantity_scale' => null,
            'description' => is_string($line['description'] ?? null) ? trim($line['description']) : '',
            'entered_qty' => null, 'entered_unit_id' => null, 'ratio_to_base' => null, 'qty_base' => null, 'unit_price' => null,
            'line_sales_value' => Money::of(is_string($line['value'] ?? null) ? $line['value'] : '0')->toString(),
        ], $lines);
    }

    /**
     * Each invoice command's business audit event, under its authority (LG-01; AZ-10).
     *
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $before
     */
    public function record(CommandContext $context, string $action, AuthorityClass $class, int $invoiceId, ?string $reason, ?string $businessDate, array $after, array $before = [], ?AuditLinkage $linkage = null): void
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first(['public_id', 'company_id']);
        $this->audit->record(
            action: $action,
            authorityClass: $class,
            actorUserId: $context->request->actorUserId,
            companyId: is_object($invoice) ? (int) Row::text($invoice, 'company_id') : null,
            entityType: 'invoice',
            entityId: $invoiceId,
            entityPublicId: is_object($invoice) ? Row::text($invoice, 'public_id') : null,
            reason: $reason,
            commandId: $context->request->commandId,
            change: $before === [] ? AuditChange::created($after) : AuditChange::updated($before, $after),
            linkage: $linkage,
            businessDate: $businessDate,
        );
    }
}
