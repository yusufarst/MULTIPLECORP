<?php

namespace App\Modules\Reporting\Signals;

use App\Modules\IAM\Data\Authority;

/**
 * One read of a home page (PG-006, PG-007): the viewer, the companies of the viewer's scope inside the page's view —
 * read once, in the page's first response (QB-10) — and the business date of the WIB day (BR-DT-06).
 */
final readonly class Context
{
    /**
     * @param  array<int, array{code: string, id: string}>  $companies  by row id: the code and the public id
     */
    public function __construct(
        public Authority $viewer,
        public array $companies,
        public string $today,
    ) {}

    /**
     * The companies of the view where the viewer holds one of the capabilities (AZ-01; PJ-13).
     *
     * @return list<int>
     */
    public function holding(string ...$capabilities): array
    {
        $held = [];

        foreach (array_keys($this->companies) as $company) {
            foreach ($capabilities as $capability) {
                if ($this->viewer->allows($capability, $company)) {
                    $held[] = $company;

                    break;
                }
            }
        }

        return $held;
    }

    public function code(?int $company): ?string
    {
        return $company === null ? null : ($this->companies[$company]['code'] ?? null);
    }

    public function publicId(int $company): ?string
    {
        return $this->companies[$company]['id'] ?? null;
    }
}
