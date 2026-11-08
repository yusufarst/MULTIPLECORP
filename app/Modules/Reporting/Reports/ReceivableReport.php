<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\ReadClass;
use App\Support\Finance\AgingCalculator;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The receivable report (RD-04; CALC-05; CALC-07; BR-FIN-14; BR-FIN-15; D-IA-15; GR-23): as of today, each company's
 * ageing — the outstanding amount per due-date bucket, the *migrasi* bucket of opening receivables included, a
 * disputed amount kept with its indicator; the chart's table — and its open invoices with their bucket. A position:
 * the period does not bound it. The buckets are AgingCalculator's one rule (CALC-07), never copied. Two statements.
 */
final class ReceivableReport implements Report
{
    private const DISPUTED = 'exists (select 1 from receivable_dispute_holds h where h.invoice_id = b.invoice_id and h.cleared_at is null)';

    public function code(): string
    {
        return 'piutang';
    }

    public function opens(Authority $viewer): array
    {
        return ReadClass::SalesReceivablesCash->admits($viewer) ? ['finance.view'] : [];
    }

    public function masking(Authority $viewer): array
    {
        return [];
    }

    public function tables(Authority $viewer, Filters $filters): array
    {
        $companies = $filters->companyIds();
        // AgingCalculator builds it from a column name it checks against a pattern and a day it parses, so no request text reaches it.
        /** @var literal-string $bucket */
        $bucket = AgingCalculator::bucketSql('b.current_due_date', $filters->today);
        $open = fn (): Builder => DB::table('invoice_balances as b')->whereIn('b.company_id', $companies)->where('b.outstanding', '>', 0)->whereNotNull('b.current_billing_act_id');
        $ages = $open()->groupByRaw('b.company_id, 2')->select(['b.company_id', DB::raw($bucket.' as bucket'), DB::raw('sum(b.outstanding) as outstanding'), DB::raw('coalesce(sum(b.outstanding) filter (where '.self::DISPUTED.'), 0) as disputed')]);
        $invoices = DB::query()->fromSub($open()->join('invoices as i', 'i.id', '=', 'b.invoice_id')->leftJoin('client_organizations as o', 'o.id', '=', 'i.client_organization_id')
            ->select(['b.invoice_id as id', 'b.company_id', 'b.current_due_date', DB::raw("coalesce(b.current_due_date, date '9999-12-31') as due_key"), 'b.outstanding', 'o.name as client', DB::raw($bucket.' as bucket'), DB::raw(self::DISPUTED.' as disputed'), DB::raw(SalesReport::NUMBER)]), 'q')
            ->select('q.*');

        return [
            new Table('umur', [new Column('bucket', 'label'), new Column('outstanding', 'money', true), new Column('disputed', 'money', true)], $companies,
                function () use ($ages, $companies): iterable {
                    $found = [];

                    foreach ($ages->get() as $row) {
                        $found[(int) Row::text($row, 'company_id')][Row::text($row, 'bucket')] = [Row::text($row, 'outstanding'), Row::text($row, 'disputed')];
                    }

                    foreach ($companies as $company) {
                        foreach (AgingCalculator::BUCKETS as $name) {
                            [$outstanding, $disputed] = $found[$company][$name] ?? ['0.00', '0.00'];
                            yield [$company, [self::label($name), $outstanding, $disputed]];
                        }
                    }
                },
                fn (): int => count($companies) * count(AgingCalculator::BUCKETS)),
            new Table('rincian', [new Column('number'), new Column('client'), new Column('due_date', 'date'), new Column('bucket', 'label'), new Column('outstanding', 'money', true), new Column('disputed', 'label')], $companies,
                function () use ($invoices): iterable {
                    foreach (Keyed::rows($invoices, ['q.company_id' => 'company_id', 'q.due_key' => 'due_key', 'q.id' => 'id']) as $row) {
                        yield [(int) Row::text($row, 'company_id'), [Row::nullable($row, 'number'), Row::nullable($row, 'client'), Row::nullable($row, 'current_due_date'), self::label(Row::text($row, 'bucket')),
                            Row::text($row, 'outstanding'), in_array(Row::text($row, 'disputed'), ['1', 'true', 't'], true) ? 'pages.pg_051.disputed' : null]];
                    }
                },
                fn (): int => (clone $invoices)->count()),
        ];
    }

    /** A bucket's catalogue key, *Beranda*'s own (PG-006). */
    public static function label(string $bucket): string
    {
        return match ($bucket) {
            AgingCalculator::CURRENT => 'pages.pg_006.bucket_current',
            AgingCalculator::DAYS_1_30 => 'pages.pg_006.bucket_1_30',
            AgingCalculator::DAYS_31_60 => 'pages.pg_006.bucket_31_60',
            AgingCalculator::DAYS_61_90 => 'pages.pg_006.bucket_61_90',
            AgingCalculator::OVER_90 => 'pages.pg_006.bucket_90',
            default => 'pages.pg_006.bucket_migrasi',
        };
    }
}
