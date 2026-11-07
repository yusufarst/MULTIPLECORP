<?php

namespace App\Modules\Reporting\Exports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CompanyScope;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Report;

/**
 * An export's recorded context (DP-07; H6-04; RV-07; RV-08): the company set its filters left, the view capabilities
 * that opened its report and the masking context — the capabilities that widened what it showed —, as the requester
 * held them when asking. The job and the download compare the requester's current authority with it, and the job reads
 * under it alone, so the file never holds more than its recorded context allowed, a capability gained since included.
 */
final class ExportContext
{
    /**
     * @param  list<int>  $companies
     * @param  list<string>  $capabilities
     * @param  list<string>  $masking
     */
    public function __construct(public readonly array $companies, public readonly array $capabilities, public readonly array $masking) {}

    public static function of(Report $report, Authority $viewer, Filters $filters): self
    {
        return new self($filters->companyIds(), $report->opens($viewer), $report->masking($viewer));
    }

    /** From a request's columns, PostgreSQL arrays. */
    public static function stored(mixed $companies, mixed $capabilities, mixed $masking): self
    {
        return new self(array_map('intval', self::array($companies)), self::array($capabilities), self::array($masking));
    }

    public function coveredBy(Authority $current): bool
    {
        return $current->covers(array_map('strval', $this->companies), $this->capabilities, $this->masking);
    }

    /** The requester's authority narrowed to the recorded context: its companies, and only its recorded capabilities. */
    public function narrow(Authority $current): Authority
    {
        $kept = array_values(array_intersect($current->capabilities, [...$this->capabilities, ...$this->masking]));

        return new Authority($current->userId, $current->active, $kept, CompanyScope::of(array_filter($this->companies, $current->scope->includes(...))), $current->ownerRole);
    }

    /** @return array{company_ids: string, capability_codes: string, masking_context: string} */
    public function columns(): array
    {
        return ['company_ids' => self::literal(array_map('strval', $this->companies)), 'capability_codes' => self::literal($this->capabilities), 'masking_context' => self::literal($this->masking)];
    }

    /**
     * A PostgreSQL array literal of catalogue codes and ids, which hold no quote, comma or brace.
     *
     * @param  list<string>  $values
     */
    private static function literal(array $values): string
    {
        return '{'.implode(',', array_map(fn (string $value): string => '"'.$value.'"', $values)).'}';
    }

    /** @return list<string> */
    private static function array(mixed $value): array
    {
        if (! is_string($value) || trim($value, '{}') === '') {
            return [];
        }

        return array_map(fn (string $item): string => trim($item, '"'), explode(',', trim($value, '{}')));
    }
}
