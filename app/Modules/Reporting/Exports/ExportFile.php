<?php

namespace App\Modules\Reporting\Exports;

use App\Modules\DOC\Contracts\DocumentRenderer;
use App\Modules\IAM\Data\Authority;
use App\Modules\Reporting\Reports\Column;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\Reports;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/**
 * An export's bytes (JB-03; PF-28; FL-09; LOCALIZATION §3.4; AC-23): the report's own tables read through their keyed
 * chunks — the page's query class and filters, so it never shows more than the page —, written in Indonesian whatever
 * the interface language, its headers included: a title, the filters as a line of text, then each table's header and
 * its sections, a company's rows under its code with the section's totals, never a total across companies. Excel through
 * the streaming writer; PDF as one table document through FL-08's renderer. Called inside the job's snapshot.
 */
final class ExportFile
{
    public function __construct(private readonly DocumentRenderer $renderer) {}

    /** Writes the file into `$directory`; answers its path. */
    public function write(Report $report, Authority $viewer, Filters $filters, string $format, string $directory, CarbonImmutable $at): string
    {
        $target = $directory.'/export.'.$format;

        if ($format === 'pdf') {
            File::put($target, $this->renderer->render($this->html($report, $viewer, $filters, $at)));

            return $target;
        }

        $writer = new XlsxWriter($directory);

        foreach ($report->tables($viewer, $filters) as $table) {
            $writer->sheet(self::text('pages.pg_051.table.'.$table->key));
            $writer->row([self::text('pages.pg_051.report.'.$report->code())]);
            $writer->row([self::filterLine($filters, $at)]);
            $writer->row([]);
            $pooled = $table->sections === null;
            $writer->row([...($pooled ? [] : [self::text('pages.pg_051.export.company')]), ...array_map(fn (Column $column): string => self::text('pages.pg_051.col.'.$column->key), $table->columns)]);
            $numeric = [...($pooled ? [] : [false]), ...array_map(fn (Column $column): bool => $column->numeric(), $table->columns)];
            $section = false;
            $rows = [];

            foreach ($table->rows() as [$company, $cells]) {
                if ($section !== false && $section !== $company) {
                    $this->totals($writer, $table->columns, $rows, $pooled, $filters, $section, $numeric);
                    $rows = [];
                }

                $section = $company;
                $rows[] = self::totalsOnly($table->columns, $cells);
                $writer->row([...($pooled ? [] : [$company === null ? null : $filters->code($company)]), ...self::cells($table->columns, $cells)], $numeric);
            }

            if ($section !== false) {
                $this->totals($writer, $table->columns, $rows, $pooled, $filters, $section, $numeric);
            }
        }

        $writer->finish($target);

        return $target;
    }

    /**
     * A section's total row, the label in its first text column.
     *
     * @param  list<Column>  $columns
     * @param  list<list<?string>>  $rows
     * @param  list<bool>  $numeric
     */
    private function totals(XlsxWriter $writer, array $columns, array $rows, bool $pooled, Filters $filters, ?int $company, array $numeric): void
    {
        $total = Reports::total($columns, $rows);

        if ($total === null) {
            return;
        }

        $total[0] ??= self::text('pages.pg_051.export.total');
        $writer->row([...($pooled ? [] : [$company === null ? null : $filters->code($company)]), ...$total], $numeric);
    }

    /**
     * Only the cells a total reads, so a section's memory holds amounts, not its rows' text.
     *
     * @param  list<Column>  $columns
     * @param  list<?string>  $cells
     * @return list<?string>
     */
    private static function totalsOnly(array $columns, array $cells): array
    {
        return array_map(fn (Column $column, int $index): ?string => $column->total ? ($cells[$index] ?? null) : null, $columns, array_keys($columns));
    }

    /**
     * The cells as an Indonesian reader reads them: a label translated, a date as dd/mm/yyyy, *Gabungan* named.
     *
     * @param  list<Column>  $columns
     * @param  list<?string>  $cells
     * @return list<?string>
     */
    public static function cells(array $columns, array $cells): array
    {
        $out = [];

        foreach ($columns as $index => $column) {
            $cell = $cells[$index] ?? null;
            $out[] = match ($column->kind) {
                'label' => $cell === null ? null : self::text($cell),
                'company' => $cell ?? self::text('pages.pg_006.view.gabungan'),
                'date' => $cell === null || $cell === '' ? null : CarbonImmutable::parse($cell)->format('d/m/Y'),
                default => $cell,
            };
        }

        return $out;
    }

    public static function filterLine(Filters $filters, CarbonImmutable $at): string
    {
        $companies = implode(', ', array_map(fn (array $company): string => $company['code'], $filters->companies));

        return self::text('pages.pg_051.export.period').': '.CarbonImmutable::parse($filters->period->from)->format('d/m/Y').' – '.CarbonImmutable::parse($filters->period->to)->format('d/m/Y')
            .($companies === '' ? '' : ' · '.self::text('pages.pg_051.export.company').': '.$companies)
            .' · '.self::text('pages.pg_051.export.generated').': '.$at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB';
    }

    /** The Indonesian text of a catalogue key (LOCALIZATION §3.4). */
    public static function text(string $key): string
    {
        $text = Lang::get($key, [], 'id');

        return is_string($text) ? $text : $key;
    }

    private function html(Report $report, Authority $viewer, Filters $filters, CarbonImmutable $at): string
    {
        $e = fn (?string $text): string => htmlspecialchars($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><style>@page{size:A4 landscape;margin:12mm}body{font-family:sans-serif;font-size:8pt;color:#111}h1{font-size:12pt;margin:0 0 2mm}h2{font-size:10pt;margin:5mm 0 1mm}p{margin:0 0 3mm}table{width:100%;border-collapse:collapse}th,td{border-bottom:0.5pt solid #bbb;padding:1mm;text-align:left;vertical-align:top}td.n{text-align:right;white-space:nowrap}tr.t td{font-weight:bold}</style></head><body>';
        $html .= '<h1>'.$e(self::text('pages.pg_051.report.'.$report->code())).'</h1><p>'.$e(self::filterLine($filters, $at)).'</p>';

        foreach ($report->tables($viewer, $filters) as $table) {
            $pooled = $table->sections === null;
            $html .= '<h2>'.$e(self::text('pages.pg_051.table.'.$table->key)).'</h2><table><thead><tr>'.($pooled ? '' : '<th>'.$e(self::text('pages.pg_051.export.company')).'</th>');

            foreach ($table->columns as $column) {
                $html .= '<th>'.$e(self::text('pages.pg_051.col.'.$column->key)).'</th>';
            }

            $html .= '</tr></thead><tbody>';
            $section = false;
            $rows = [];
            $close = function (?int $company) use (&$rows, &$html, $table, $pooled, $filters, $e): void {
                $total = Reports::total($table->columns, $rows);

                if ($total !== null) {
                    $total[0] ??= self::text('pages.pg_051.export.total');
                    $html .= '<tr class="t">'.($pooled ? '' : '<td>'.$e($company === null ? null : $filters->code($company)).'</td>');

                    foreach ($total as $index => $cell) {
                        $html .= '<td'.($table->columns[$index]->numeric() ? ' class="n"' : '').'>'.$e($cell).'</td>';
                    }

                    $html .= '</tr>';
                }

                $rows = [];
            };

            foreach ($table->rows() as [$company, $cells]) {
                if ($section !== false && $section !== $company) {
                    $close($section);
                }

                $section = $company;
                $rows[] = self::totalsOnly($table->columns, $cells);
                $html .= '<tr>'.($pooled ? '' : '<td>'.$e($company === null ? null : $filters->code($company)).'</td>');

                foreach (self::cells($table->columns, $cells) as $index => $cell) {
                    $html .= '<td'.($table->columns[$index]->numeric() ? ' class="n"' : '').'>'.$e($cell).'</td>';
                }

                $html .= '</tr>';
            }

            if ($section !== false) {
                $close($section);
            }

            $html .= '</tbody></table>';
        }

        return $html.'</body></html>';
    }
}
