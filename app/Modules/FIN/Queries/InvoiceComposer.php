<?php

namespace App\Modules\FIN\Queries;

use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\DOC\Services\IssuerIdentity;
use App\Modules\FIN\Data\InvoiceValues;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\InvoiceSubjects;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Queries\TaxConfiguration;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PG-042 *Buat Invoice* (ROUTE-042; NAV-042; J-FIN-01; D-DS-23): the composer's "Diambil dari proyek" — the project, its client as used and its confirmed
 * lines at their pinned quantity and price — and "Diisi sekarang"; the value left under the cap (L-16), the output taxes in force with their treatment for the
 * browser's live paper, whose paper here carries no number, stamp or signature (PJ-19; WS-07). An open draft comes back with what was entered, at its root's
 * `lock_version` and with the digest of its facts for the issue (architecture challenge 15a).
 */
final class InvoiceComposer
{
    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly IssuerIdentity $identity,
        private readonly TaxConfiguration $taxes,
        private readonly InvoiceSubjects $subjects,
    ) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ProjectRef $project, ?Invoice $draft): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $header = DB::table('projects as p')->where('p.id', $project->id)->join('companies as c', 'c.id', '=', 'p.company_id')->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->first(['p.project_number', 'p.title', 'p.state', 'c.code as company', 'o.name as client']);
        $lines = DB::table('project_lines as l')->where('l.project_id', $project->id)->whereNotNull('l.current_pin_id')
            ->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')->join('units as u', 'u.id', '=', 'p.entered_unit_id')
            ->where('p.qty_base', '>', 0)->orderBy('l.line_no')
            ->get(['l.id', 'l.line_no', 'l.quantity_scale', 'p.description', 'p.entered_qty', 'p.unit_price', 'p.sales_value', 'u.code as unit'])->all();
        $commercial = InvoiceRecords::commercial($project->id);
        $today = BusinessDate::today();
        $identity = $this->identity->of($project->companyId);
        $client = InvoiceRecords::clientSnapshot($project->id);
        $recipient = ['name' => $client['organization'] ?? '', 'lines' => array_values(array_filter([$client['unit'] ?? null, ($client['contact'] ?? null) === null ? null : 'u.p. '.$client['contact'], $client['address'] ?? null, $client['city'] ?? null], fn (?string $line): bool => $line !== null && $line !== ''))];
        $facts = new DocumentFacts(recipient: $recipient, references: [['label' => 'No. Proyek', 'value' => $header === null ? '' : Row::text($header, 'project_number')]], subject: $header === null ? null : Row::text($header, 'title'));
        $paper = DocumentPaper::compose(InvoiceSubjects::INVOICE, 'STANDARD', $facts, $identity, null, $today, null, true, $identity['assets']['logo'] === null ? null : '/berkas/'.$identity['assets']['logo']['public_id'].'/unduh');

        return [
            'project' => $header === null ? null : ['id' => $project->publicId, 'number' => Row::text($header, 'project_number'), 'title' => Row::text($header, 'title'), 'company' => Row::text($header, 'company'), 'client' => Row::text($header, 'client'), 'state' => Row::text($header, 'state')],
            'lines' => array_values(array_map(fn (object $line): array => [
                'handle' => RowHandle::of('project_lines', (int) Row::text($line, 'id')),
                'no' => (int) Row::text($line, 'line_no'),
                'description' => Row::text($line, 'description'),
                'quantity' => Row::text($line, 'entered_qty'),
                'unit' => Row::text($line, 'unit'),
                'scale' => (int) Row::text($line, 'quantity_scale'),
                'price' => Row::text($line, 'unit_price'),
                'value' => Row::text($line, 'sales_value'),
            ], $lines)),
            'confirmed' => $commercial['confirmed'],
            'remaining' => InvoiceValues::remaining($commercial['confirmed'], $commercial['invoiced']),
            'taxes' => array_map(fn (array $rule): array => ['code' => $rule['code'], 'rate' => $rule['rate_percent'], 'numerator' => $rule['numerator'], 'denominator' => $rule['denominator'], 'rounding' => $rule['rounding'], 'evidence_required' => $rule['evidence_required']], $this->taxes->outputTaxes($project->companyId, $today)),
            'paper' => $paper,
            'draft' => $draft === null || $draft->state !== 'DRAFT' ? null : $this->draft($draft),
            'issued' => $draft === null || $draft->state === 'DRAFT' ? null : ['id' => $draft->public_id, 'number' => InvoiceSubjects::number($draft->id)],
            'today' => $today,
            'can' => ['issue' => $viewer->allows('invoice.issue', $project->companyId)],
        ];
    }

    /** @return array<string, mixed> */
    private function draft(Invoice $invoice): array
    {
        $version = DB::table('invoice_versions')->where('id', $invoice->current_version_id)->first(['kind', 'business_date', 'sales_value', 'output_tax_value']);
        $lines = DB::table('invoice_version_lines')->where('invoice_version_id', $invoice->current_version_id)->orderBy('id')->get(['project_line_id', 'description', 'entered_qty', 'line_sales_value'])->all();
        $taxes = DB::table('invoice_tax_components as t')->join('tax_types as y', 'y.id', '=', 't.tax_type_id')->where('t.invoice_version_id', $invoice->current_version_id)->pluck('t.amount', 'y.code')->all();

        return [
            'id' => $invoice->public_id,
            'lock_version' => $invoice->lock_version,
            'kind' => $version === null ? 'ITEMIZED' : Row::text($version, 'kind'),
            'business_date' => $version === null ? BusinessDate::today() : Row::text($version, 'business_date'),
            'sales' => $version === null ? '0.00' : Row::text($version, 'sales_value'),
            'tax' => $version === null ? '0.00' : Row::text($version, 'output_tax_value'),
            'lines' => array_values(array_map(fn (object $line): array => [
                'handle' => Row::nullable($line, 'project_line_id') === null ? null : RowHandle::of('project_lines', (int) Row::text($line, 'project_line_id')),
                'description' => Row::text($line, 'description'),
                'quantity' => Row::nullable($line, 'entered_qty'),
                'value' => Row::text($line, 'line_sales_value'),
            ], $lines)),
            'taxes' => $taxes,
            'facts' => DocumentChain::factsDigest($this->subjects->facts(new DocumentSubject(InvoiceSubjects::INVOICE, 'STANDARD', $invoice->company_id, ['invoice_id' => $invoice->id]))),
        ];
    }
}
