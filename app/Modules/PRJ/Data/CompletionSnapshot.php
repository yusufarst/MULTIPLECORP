<?php

namespace App\Modules\PRJ\Data;

use App\Support\Command\Fingerprint;

/**
 * The completion snapshot of ST-04 (AX-25; AX-26; UXS-37; C-38): the ten conditions — each met or not, with its unmet items by catalogue key and
 * parameters — and the residual obligations, in a format version, its keys sorted, no timestamp, amounts and quantities as their decimal strings, so
 * the same facts always give the same SHA-256 and any change of a blocker or an amount gives another. Pure.
 */
final class CompletionSnapshot
{
    public const FORMAT = 1;

    /**
     * @param  array<string, PredicateState>  $states  the project's states by predicate; a predicate left out has no facts to judge
     * @param  list<array{kind: string, ref: string, label: string, amount: ?string, quantity: ?string}>  $obligations
     * @return array{format: int, conditions: array<string, array{applies: bool, met: bool, items: list<array{key: string, params: array<string, string>}>}>, obligations: list<array{kind: string, ref: string, label: string, amount: ?string, quantity: ?string}>}
     */
    public static function of(array $states, array $obligations): array
    {
        $conditions = [];

        foreach (Predicates::ALL as $predicate) {
            $state = $states[$predicate] ?? PredicateState::none();
            $conditions[$predicate] = ['applies' => $state->applies, 'met' => $state->met(), 'items' => array_map(fn (JourneyItem $item): array => ['key' => $item->key, 'params' => $item->params], $state->items)];
        }

        return ['format' => self::FORMAT, 'conditions' => $conditions, 'obligations' => $obligations];
    }

    /**
     * The predicates not met, in WF-PRJ-03's order (C-39).
     *
     * @param  array<string, PredicateState>  $states
     * @return list<string>
     */
    public static function unmet(array $states): array
    {
        return array_values(array_filter(Predicates::ALL, fn (string $predicate): bool => ! ($states[$predicate] ?? PredicateState::none())->met()));
    }

    /** @param  array<string, mixed>  $snapshot */
    public static function hash(array $snapshot): string
    {
        return hash('sha256', Fingerprint::canonicalJson($snapshot));
    }
}
