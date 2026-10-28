<?php

namespace App\Modules\PRJ\Data;

/**
 * One open item of a project's journey — an unmet condition, a blocker or the next step — named by its catalogue
 * key and parameters, never by a stored status (L-01). A money item is one of QS-15's money items, which a viewer
 * without `finance.view` never receives (PJ-13); a link is present only where its page exists and its capability is
 * held, the server deciding (PL-3; AZ-01).
 */
final class JourneyItem
{
    /** @param  array<string, string>  $params */
    public function __construct(
        public readonly string $key,
        public readonly array $params = [],
        public readonly ?string $stage = null,
        public readonly bool $blocker = false,
        public readonly bool $money = false,
        public readonly ?string $href = null,
    ) {}

    /** @return array{key: string, params: array<string, string>, stage: ?string, blocker: bool, href: ?string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'params' => $this->params, 'stage' => $this->stage, 'blocker' => $this->blocker, 'href' => $this->href];
    }
}
