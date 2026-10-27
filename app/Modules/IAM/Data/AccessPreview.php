<?php

namespace App\Modules\IAM\Data;

/**
 * "Pratinjau Akses" (PT-30; D-DS-09; UXS-27; RG-06; RG-07; GAP-032): every capability the account holds or will hold
 * against every company it holds or will hold, each cell *Dapat* (kept), *Baru* (added), *Dicabut* (removed) or
 * empty, because a capability applies to every granted company; a capability whose prerequisite is not effective
 * after the change reads "Nonaktif — prasyarat dicabut" — its grant kept, effective again once the prerequisite is
 * granted (RG-06). Derived on the server, which decides what the grants allow; the save's confirmation lists the
 * same changes. A pure derivation over codes and company keys, for an account of the ADMIN_OPERASIONAL role: the
 * OWNER role carries every capability through `role_capabilities` (RG-02).
 */
final class AccessPreview
{
    public const KEPT = 'kept';

    public const ADDED = 'added';

    public const REMOVED = 'removed';

    public const INACTIVE = 'inactive';

    /**
     * @param  list<string>  $capabilitiesBefore
     * @param  list<string>  $companiesBefore
     * @param  list<string>  $capabilitiesAfter
     * @param  list<string>  $companiesAfter
     */
    public function __construct(
        private readonly array $capabilitiesBefore,
        private readonly array $companiesBefore,
        private readonly array $capabilitiesAfter,
        private readonly array $companiesAfter,
    ) {}

    /**
     * The capabilities of a set that are effective: each held with every prerequisite effective (RG-06), transitively.
     *
     * @param  list<string>  $held
     * @return list<string>
     */
    public static function effective(array $held): array
    {
        $effective = array_values(array_filter($held, fn (string $code): bool => CapabilityCatalogue::has($code) && ! CapabilityCatalogue::isOwnerOnly($code)));

        do {
            $before = count($effective);
            $effective = array_values(array_filter($effective, fn (string $code): bool => array_diff(CapabilityCatalogue::prerequisites($code), $effective) === []));
        } while (count($effective) !== $before);

        return $effective;
    }

    /**
     * The capabilities held after the change that a prerequisite missing from it disables.
     *
     * @return list<string>
     */
    public function disabled(): array
    {
        return array_values(array_diff($this->held($this->capabilitiesAfter), self::effective($this->capabilitiesAfter)));
    }

    /**
     * The capabilities added after the change that lack a prerequisite: such a grant is refused (RG-06).
     *
     * @return list<string>
     */
    public function addedWithoutPrerequisite(): array
    {
        $added = array_diff($this->held($this->capabilitiesAfter), $this->capabilitiesBefore);
        $effective = self::effective($this->capabilitiesAfter);

        return array_values(array_filter($added, fn (string $code): bool => array_diff(CapabilityCatalogue::prerequisites($code), $effective) !== []));
    }

    /**
     * The company columns: those held after the change, then those it removes.
     *
     * @return list<string>
     */
    public function companies(): array
    {
        return array_values(array_unique([...$this->companiesAfter, ...array_diff($this->companiesBefore, $this->companiesAfter)]));
    }

    /**
     * The matrix rows, by area in the page's order: each capability with its mark per company.
     *
     * @return list<array{code: string, area: string, marks: array<array-key, string|null>}>
     */
    public function rows(): array
    {
        $codes = array_unique([...$this->held($this->capabilitiesBefore), ...$this->held($this->capabilitiesAfter)]);
        $effective = self::effective($this->capabilitiesAfter);
        $rows = [];

        foreach (CapabilityAreas::grantable() as $area => $inArea) {
            foreach ($inArea as $code) {
                if (! in_array($code, $codes, true)) {
                    continue;
                }

                $marks = [];

                foreach ($this->companies() as $company) {
                    $marks[$company] = $this->mark($code, $company, $effective);
                }

                $rows[] = ['code' => $code, 'area' => $area, 'marks' => $marks];
            }
        }

        return $rows;
    }

    /**
     * The number of capabilities effective in each company after the change — the summary sentence per company.
     *
     * @return array<array-key, int>
     */
    public function summaries(): array
    {
        $effective = count(self::effective($this->capabilitiesAfter));
        $summaries = [];

        foreach ($this->companies() as $company) {
            $summaries[$company] = in_array($company, $this->companiesAfter, true) ? $effective : 0;
        }

        return $summaries;
    }

    /**
     * What the save changes, for its confirmation (PT-30: "lists each change and the companies it applies to").
     *
     * @return array{capabilities_added: list<string>, capabilities_removed: list<string>, companies_added: list<string>, companies_removed: list<string>, disabled: list<string>}
     */
    public function changes(): array
    {
        return [
            'capabilities_added' => array_values(array_diff($this->held($this->capabilitiesAfter), $this->capabilitiesBefore)),
            'capabilities_removed' => array_values(array_diff($this->held($this->capabilitiesBefore), $this->capabilitiesAfter)),
            'companies_added' => array_values(array_diff($this->companiesAfter, $this->companiesBefore)),
            'companies_removed' => array_values(array_diff($this->companiesBefore, $this->companiesAfter)),
            'disabled' => $this->disabled(),
        ];
    }

    /** @param  list<string>  $effective */
    private function mark(string $code, string $company, array $effective): ?string
    {
        $after = in_array($code, $this->capabilitiesAfter, true) && in_array($company, $this->companiesAfter, true);
        $before = in_array($code, $this->capabilitiesBefore, true) && in_array($company, $this->companiesBefore, true);

        return match (true) {
            $after && ! in_array($code, $effective, true) => self::INACTIVE,
            $after => $before ? self::KEPT : self::ADDED,
            $before => self::REMOVED,
            default => null,
        };
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function held(array $codes): array
    {
        return array_values(array_filter($codes, fn (string $code): bool => CapabilityCatalogue::has($code) && CapabilityAreas::of($code) !== null));
    }
}
