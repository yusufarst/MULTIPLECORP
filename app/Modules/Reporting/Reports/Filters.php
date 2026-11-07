<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * A report's whitelisted filters (PF-27; DP-02; CS-07; INT-007): the period and the company filter, both address keys
 * — `perusahaan` a company's code —, and the companies they leave: the viewer's scope narrowed to the company asked
 * for, so a company outside the scope is ignored and never widens the result. An export records these keys and its
 * company set, and the job reads them back (DP-07).
 */
final class Filters
{
    /**
     * @param  array<int, array{code: string, name: string}>  $companies  in scope and filter, by id, ordered by code
     * @param  list<array{code: string, name: string}>  $choices  every company of the scope, for the filter control
     */
    private function __construct(
        public readonly Period $period,
        public readonly ?string $company,
        public readonly array $companies,
        public readonly array $choices,
        public readonly string $today,
    ) {}

    /**
     * @param  array<mixed>  $keys  the request's address keys
     */
    public static function of(Authority $viewer, array $keys, string $today): self
    {
        $period = Period::of(self::text($keys['periode'] ?? null), self::text($keys['dari'] ?? null), self::text($keys['sampai'] ?? null), $today);
        $all = [];

        foreach ($viewer->scope->apply(DB::table('companies as c'), 'c.id')->orderBy('c.code')->get(['c.id', 'c.code', 'c.legal_name']) as $row) {
            $all[(int) Row::text($row, 'id')] = ['code' => Row::text($row, 'code'), 'name' => Row::text($row, 'legal_name')];
        }

        $asked = self::text($keys['perusahaan'] ?? null);
        $chosen = array_filter($all, fn (array $company): bool => $company['code'] === $asked);

        return new self($period, $chosen === [] ? null : $asked, $chosen === [] ? $all : $chosen, array_values($all), $today);
    }

    /** @return list<int> */
    public function companyIds(): array
    {
        return array_keys($this->companies);
    }

    public function code(int $companyId): ?string
    {
        return $this->companies[$companyId]['code'] ?? null;
    }

    /**
     * The keys an export records and a link repeats.
     *
     * @return array{periode: string, dari: string, sampai: string, perusahaan?: string}
     */
    public function keys(): array
    {
        return [...$this->period->keys(), ...($this->company === null ? [] : ['perusahaan' => $this->company])];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= 40 ? $value : null;
    }
}
