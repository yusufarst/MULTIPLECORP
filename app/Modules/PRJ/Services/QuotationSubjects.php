<?php

namespace App\Modules\PRJ\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\PaperFormat;
use App\Modules\IAM\Data\Authority;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PRJ's `DocumentSubjectProvider` (ARCHITECTURE §3; WF-DOC-01's table; TASK-033) for the project's own documents: DOC-01
 * *Penawaran* renders the project's quotation revision — issued only by "Terbitkan Penawaran" (ROUTE-155), which makes
 * that revision *Terkirim* in the same transaction —, DOC-11 the company-prepared HPS from the project's lines, issued
 * on PG-036 like any selectable document with its truthful company issuer (BR-DOC-04; REF-033). Both print the client
 * as the project names it at the issue, frozen in the payload from then on (BR-SN-01; architecture challenge 1b), and
 * the record's own values — nothing typed again (O-01).
 */
final class QuotationSubjects implements DocumentSubjectProvider
{
    public const QUOTATION = 'DOC-01';

    public const HPS = 'DOC-11';

    public function types(): array
    {
        return [self::QUOTATION => ['STANDARD'], self::HPS => ['STANDARD']];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        $publicId = $records['project_id'] ?? null;
        $project = is_string($publicId) && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1
            ? DB::table('projects')->where('public_id', strtolower($publicId))->first(['id', 'company_id']) : null;

        if ($project === null || ! $viewer->allows('projects.view', (int) Row::text($project, 'company_id'))) {
            return null;
        }

        return new DocumentSubject($typeCode, $mode, (int) Row::text($project, 'company_id'), ['project_id' => (int) Row::text($project, 'id')]);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        return (new LockSet)->add('projects', ['id' => self::project($subject)]);
    }

    /**
     * DOC-01: the project's latest revision a DRAFT with lines and a validity not before today (WF-QUO-01; L-25); DOC-11:
     * a project with lines, not closed.
     */
    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        $project = self::project($subject);
        $state = DB::table('projects')->where('id', $project)->value('state');

        if (! in_array($state, ['DRAFT', 'ACTIVE'], true)) {
            throw Refused::rejected('L-11', ['reason' => 'closed']);
        }

        if ($subject->typeCode === self::HPS) {
            if (! DB::table('project_lines')->where('project_id', $project)->exists()) {
                throw Refused::rejected('WF-DOC-01', ['reason' => 'no_lines']);
            }

            return;
        }

        $revision = QuotationRecords::latest($project);

        if ($revision === null || $revision['state'] !== 'DRAFT') {
            throw Refused::rejected('WF-QUO-01', ['reason' => 'no_draft']);
        }

        if (! DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->exists()) {
            throw Refused::rejected('WF-QUO-01', ['reason' => 'no_lines']);
        }

        if ($revision['valid_until'] === null || $revision['valid_until'] < BusinessDate::today()) {
            throw Refused::rejected('L-25', ['reason' => 'validity']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        $project = self::project($subject);
        $header = DB::table('projects as p')->where('p.id', $project)
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->leftJoin('client_units as u', 'u.id', '=', 'p.client_unit_id')
            ->leftJoin('client_addresses as a', 'a.id', '=', 'p.client_address_id')
            ->leftJoin('client_contacts as k', 'k.id', '=', 'p.client_contact_id')
            ->first(['p.project_number', 'p.title', 'o.name as client', 'u.name as unit', 'a.address_lines', 'a.city', 'k.name as contact']);

        if ($header === null) {
            return new DocumentFacts;
        }

        $recipient = ['name' => Row::text($header, 'client'), 'lines' => array_values(array_filter([
            Row::nullable($header, 'unit'),
            Row::nullable($header, 'contact') === null ? null : 'u.p. '.Row::text($header, 'contact'),
            Row::nullable($header, 'address_lines'),
            Row::nullable($header, 'city'),
        ], fn (?string $line): bool => $line !== null && $line !== ''))];
        $references = [['label' => 'No. Proyek', 'value' => Row::text($header, 'project_number')]];

        if ($subject->typeCode === self::HPS) {
            $lines = DB::table('project_lines as l')->join('units as n', 'n.id', '=', 'l.entered_unit_id')->where('l.project_id', $project)->orderBy('l.line_no')
                ->selectRaw('l.description, l.entered_qty, n.code, coalesce(l.unit_price, 0) as unit_price, round(l.entered_qty * coalesce(l.unit_price, 0), 2) as amount')->get()->all();
            $total = DB::table('project_lines')->where('project_id', $project)->selectRaw('coalesce(sum(round(entered_qty * coalesce(unit_price, 0), 2)), 0) as total')->value('total');

            return new DocumentFacts(
                recipient: $recipient,
                references: $references,
                subject: Row::text($header, 'title'),
                lines: self::lines($lines),
                totals: [['label' => 'Total', 'amount' => self::amount($total), 'grand' => true]],
            );
        }

        $revision = QuotationRecords::latest($project);

        if ($revision === null) {
            return new DocumentFacts(recipient: $recipient, references: $references, subject: Row::text($header, 'title'));
        }

        $lines = DB::table('quotation_revision_lines as l')->join('units as n', 'n.id', '=', 'l.entered_unit_id')->where('l.quotation_revision_id', $revision['id'])->orderBy('l.id')
            ->selectRaw('l.description, l.entered_qty, n.code, l.unit_price, l.sales_value as amount')->get()->all();
        $subtotal = DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->selectRaw('coalesce(sum(sales_value), 0) as total')->value('total');
        $taxRows = DB::select(<<<'SQL'
            select c->>'type' as type, c->>'rate' as rate, sum((c->>'amount')::numeric) as amount
            from quotation_revision_lines l, jsonb_array_elements(l.tax_components) c
            where l.quotation_revision_id = ?
            group by 1, 2 order by 1
            SQL, [$revision['id']]);
        $taxes = [];

        foreach ($taxRows as $tax) {
            if (is_object($tax)) {
                $taxes[] = ['label' => Row::text($tax, 'type').' '.self::percent(Row::text($tax, 'rate')), 'amount' => self::amount(Row::text($tax, 'amount')), 'grand' => false];
            }
        }

        $grand = DB::selectOne(<<<'SQL'
            select coalesce(sum(l.sales_value), 0) + coalesce((select sum((c->>'amount')::numeric) from quotation_revision_lines t, jsonb_array_elements(t.tax_components) c where t.quotation_revision_id = ?), 0) as total
            from quotation_revision_lines l where l.quotation_revision_id = ?
            SQL, [$revision['id'], $revision['id']]);
        $references[] = ['label' => 'Revisi', 'value' => (string) $revision['revision_no']];

        if ($revision['valid_until'] !== null) {
            $references[] = ['label' => 'Berlaku sampai', 'value' => PaperFormat::date($revision['valid_until'])];
        }

        return new DocumentFacts(
            recipient: $recipient,
            references: $references,
            subject: Row::text($header, 'title'),
            lines: self::lines($lines),
            totals: [
                ['label' => 'Subtotal', 'amount' => self::amount($subtotal), 'grand' => false],
                ...$taxes,
                ['label' => 'Total', 'amount' => self::amount(is_object($grand) ? Row::text($grand, 'total') : '0'), 'grand' => true],
            ],
            renders: ['quotation_revision_id' => $revision['id']],
        );
    }

    private static function project(DocumentSubject $subject): int
    {
        return $subject->records['project_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }

    /**
     * @param  array<int, object>  $lines
     * @return list<array{description: string, quantity: ?string, unit: ?string, unit_price: ?string, amount: ?string, note: ?string}>
     */
    private static function lines(array $lines): array
    {
        return array_map(fn (object $line): array => [
            'description' => Row::text($line, 'description'),
            'quantity' => Row::text($line, 'entered_qty'),
            'unit' => Row::text($line, 'code'),
            'unit_price' => self::amount(Row::text($line, 'unit_price')),
            'amount' => self::amount(Row::text($line, 'amount')),
            'note' => null,
        ], array_values($lines));
    }

    /** An amount as the record computed it, two decimals. */
    private static function amount(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '0';
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        return ($whole === '' ? '0' : $whole).'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private static function percent(string $rate): string
    {
        return (str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate).'%';
    }
}
