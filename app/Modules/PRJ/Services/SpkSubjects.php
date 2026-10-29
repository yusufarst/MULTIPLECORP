<?php

namespace App\Modules\PRJ\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\PaperFormat;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PRJ's `DocumentSubjectProvider` for DOC-09, the company-issued SPK (WF-PRJ-02's optional output; WF-DOC-01; TASK-034):
 * the confirmed project as its subject, drafted and issued on PG-036 through ROUTE-221 and ROUTE-222 (TASK-029). Its
 * precondition is the commercial confirmation; its paper the confirmed lines as pinned — quantity, unit, price and value
 * as confirmed, nothing typed again (O-01; BR-SN-01) —, the company as its truthful issuer and the draft marked until
 * issued, never standing for a client-issued SPK, which stays uploaded evidence (BR-DOC-04; FS-12). Generating it moves
 * no stock, cash or obligation (L-19).
 */
final class SpkSubjects implements DocumentSubjectProvider
{
    public const SPK = 'DOC-09';

    public function types(): array
    {
        return [self::SPK => ['STANDARD']];
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

    /** The project confirmed and not closed: the SPK renders confirmed work only (WF-PRJ-02). */
    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        $project = self::project($subject);

        if (! in_array(DB::table('projects')->where('id', $project)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
            throw Refused::rejected('L-11', ['reason' => 'closed']);
        }

        if (! CommercialConfirmation::confirmed($project)) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'unconfirmed']);
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
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }

        $confirmed = DB::table('commercial_confirmations')->where('project_id', $project)->orderByDesc('id')->value('business_date');
        $lines = DB::table('project_lines as l')->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')->join('units as n', 'n.id', '=', 'p.entered_unit_id')
            ->where('l.project_id', $project)->where('p.qty_base', '>', 0)->orderBy('l.line_no')
            ->get(['p.description', 'p.entered_qty', 'n.code', 'p.unit_price', 'p.sales_value'])->all();
        $total = DB::table('project_lines as l')->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')->where('l.project_id', $project)
            ->selectRaw('coalesce(sum(p.sales_value), 0) as total')->value('total');

        return new DocumentFacts(
            recipient: ['name' => Row::text($header, 'client'), 'lines' => array_values(array_filter([
                Row::nullable($header, 'unit'),
                Row::nullable($header, 'contact') === null ? null : 'u.p. '.Row::text($header, 'contact'),
                Row::nullable($header, 'address_lines'),
                Row::nullable($header, 'city'),
            ], fn (?string $line): bool => $line !== null && $line !== ''))],
            references: [
                ['label' => 'No. Proyek', 'value' => Row::text($header, 'project_number')],
                ...(is_string($confirmed) ? [['label' => 'Dikonfirmasi', 'value' => PaperFormat::date($confirmed)]] : []),
            ],
            subject: Row::text($header, 'title'),
            lines: array_map(fn (object $line): array => [
                'description' => Row::text($line, 'description'),
                'quantity' => Row::text($line, 'entered_qty'),
                'unit' => Row::text($line, 'code'),
                'unit_price' => self::amount(Row::text($line, 'unit_price')),
                'amount' => self::amount(Row::text($line, 'sales_value')),
                'note' => null,
            ], array_values($lines)),
            totals: [['label' => 'Total', 'amount' => self::amount(is_scalar($total) ? (string) $total : '0'), 'grand' => true]],
        );
    }

    private static function project(DocumentSubject $subject): int
    {
        return $subject->records['project_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }

    private static function amount(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ($whole === '' ? '0' : $whole).'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
