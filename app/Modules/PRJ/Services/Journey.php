<?php

namespace App\Modules\PRJ\Services;

use App\Modules\PRJ\Data\JourneyFacts;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;

/**
 * A project's journey derived each time from its facts, decisions and completion predicates, never stored (L-01;
 * BR-XD-05; the P7 gate record's journey model PL-1–PL-3): its applicable stages in one fixed order, each complete,
 * current, upcoming or under attention; the stages that do not apply, only named (PL-2); the blockers; the one next
 * recommended step from one deterministic precedence (PL-3); and the unmet predicates "Yang tersisa sebelum selesai"
 * lists. A completed or cancelled project shows its outcome, not open stages (L-11). Without `finance.view` QS-15's
 * money items are omitted and the stages derive from the rest (PJ-13).
 */
final class Journey
{
    public const STAGES = ['agreement', 'warehouse', 'direct', 'delivery', 'service', 'documents', 'invoice', 'billing', 'payment', 'completion'];

    public const TERMINAL = ['CANCELLED', 'COMPLETED_NORMAL', 'COMPLETED_FORCED'];

    /**
     * @param  array<string, PredicateState>  $predicates
     * @param  ?string  $editHref  "Ubah proyek" for an account that may amend the project, which a DRAFT's missing details lead to
     * @return array{outcome: ?string, stages: list<array{key: string, state: string}>, not_needed: list<string>, current: ?string, position: ?int, blocked: bool, blockers: list<array<string, mixed>>, next: ?array<string, mixed>, remaining: list<array{predicate: string, items: list<array<string, mixed>>}>}
     */
    public static function derive(JourneyFacts $facts, array $predicates, bool $money, ?string $editHref = null): array
    {
        $states = [];

        foreach (Predicates::ALL as $key) {
            $state = $predicates[$key] ?? PredicateState::none();
            $states[$key] = $money ? $state : $state->withoutMoney();
        }

        $remaining = [];

        foreach ($states as $key => $state) {
            if (! $state->met()) {
                $remaining[] = ['predicate' => $key, 'items' => array_map(fn (JourneyItem $item): array => $item->toArray(), $state->items)];
            }
        }

        if (in_array($facts->state, self::TERMINAL, true)) {
            return ['outcome' => $facts->state, 'stages' => [], 'not_needed' => [], 'current' => null, 'position' => null, 'blocked' => false, 'blockers' => [], 'next' => null, 'remaining' => $remaining];
        }

        $complete = self::complete($facts, $states, $money);
        $applicable = array_keys($complete);
        $current = null;

        foreach ($complete as $key => $done) {
            if (! $done) {
                $current = $key;

                break;
            }
        }

        $blockers = [];

        foreach ($states as $state) {
            foreach ($state->items as $item) {
                if ($item->blocker) {
                    // A blocker of a predicate without a stage of its own overlays the stage it touches — or the current one (P7 gate record §a).
                    $blockers[] = new JourneyItem($item->key, $item->params, $item->stage !== null && in_array($item->stage, $applicable, true) ? $item->stage : $current, true, $item->money, $item->href);
                }
            }
        }

        $attention = array_values(array_unique(array_filter(array_map(fn (JourneyItem $item): ?string => $item->stage, $blockers))));
        $stages = [];

        foreach ($complete as $key => $done) {
            $stages[] = ['key' => $key, 'state' => $done ? 'complete' : (in_array($key, $attention, true) ? 'attention' : ($key === $current ? 'current' : 'upcoming'))];
        }

        $position = $current === null ? null : array_search($current, $applicable, true) + 1;
        $next = self::next($facts, $states, $current, $money, $editHref);

        return [
            'outcome' => null,
            'stages' => $stages,
            'not_needed' => array_values(array_diff(self::STAGES, $applicable)),
            'current' => $current,
            'position' => is_int($position) ? $position : null,
            'blocked' => $blockers !== [],
            'blockers' => array_map(fn (JourneyItem $item): array => $item->toArray(), $blockers),
            'next' => $next?->toArray(),
            'remaining' => $remaining,
        ];
    }

    /**
     * The applicable stages in track order, each with whether it is *Tuntas*.
     *
     * @param  array<string, PredicateState>  $p
     * @return array<string, bool>
     */
    private static function complete(JourneyFacts $facts, array $p, bool $money): array
    {
        $modes = $facts->modes;
        $goods = array_values(array_filter([$modes['WAREHOUSE'] ?? null, $modes['DROPSHIP'] ?? null]));
        $stages = ['agreement' => $facts->confirmed];

        if (isset($modes['WAREHOUSE'])) {
            $stages['warehouse'] = $modes['WAREHOUSE']['done'];
        }

        if (isset($modes['DROPSHIP'])) {
            $stages['direct'] = $modes['DROPSHIP']['done'];
        }

        if ($goods !== []) {
            $stages['delivery'] = array_reduce($goods, fn (bool $all, array $mode): bool => $all && $mode['delivered'], true) && $p[Predicates::DELIVERY]->met();
        }

        if (isset($modes['SERVICE'])) {
            $stages['service'] = $modes['SERVICE']['done'];
        }

        if ($p[Predicates::DOCUMENTS]->applies || $p[Predicates::ADMINISTRATION]->applies) {
            $stages['documents'] = $p[Predicates::DOCUMENTS]->met() && $p[Predicates::ADMINISTRATION]->met();
        }

        if ($facts->confirmed) {
            // L-27 compares the invoiced value with the confirmed value — a money fact; without `finance.view` the stage
            // is complete once an invoice is issued, which the billing predicate's facts show.
            $stages['invoice'] = $money ? $facts->invoiced === true : $p[Predicates::BILLING]->applies;
        }

        if ($p[Predicates::BILLING]->applies) {
            $stages['billing'] = $p[Predicates::BILLING]->met();
        }

        if ($p[Predicates::RECEIVABLE]->applies || $p[Predicates::PAYMENT]->applies) {
            $stages['payment'] = $p[Predicates::RECEIVABLE]->met() && $p[Predicates::PAYMENT]->met();
        }

        $stages['completion'] = false;

        return $stages;
    }

    /**
     * PL-3's precedence: a DRAFT's missing details; then the current stage's first open item, oldest first; a
     * stage's own step where no predicate names one.
     *
     * @param  array<string, PredicateState>  $p
     */
    private static function next(JourneyFacts $facts, array $p, ?string $current, bool $money, ?string $editHref): ?JourneyItem
    {
        if ($facts->state === 'DRAFT' && $facts->detailsMissing) {
            return new JourneyItem('projects.next.details', [], 'agreement', false, false, $editHref);
        }

        $first = function (string ...$keys) use ($p): ?JourneyItem {
            foreach ($keys as $key) {
                foreach ($p[$key]->items as $item) {
                    return $item;
                }
            }

            return null;
        };

        $item = match ($current) {
            'agreement' => new JourneyItem(match ($facts->quotation) {
                'SENT' => 'projects.next.answer',
                'REJECTED' => 'projects.next.revise',
                'APPROVED' => 'projects.next.confirm',
                default => 'projects.next.quotation',
            }, [], 'agreement'),
            'warehouse' => $first(Predicates::FULFILMENT, Predicates::STOCK) ?? new JourneyItem('projects.next.warehouse', [], 'warehouse'),
            'direct' => new JourneyItem('projects.next.direct', [], 'direct'),
            'delivery' => $first(Predicates::DELIVERY) ?? new JourneyItem('projects.next.delivery', [], 'delivery'),
            'service' => new JourneyItem('projects.next.service', [], 'service'),
            'documents' => $first(Predicates::DOCUMENTS, Predicates::ADMINISTRATION) ?? new JourneyItem('projects.next.documents', [], 'documents'),
            'invoice' => new JourneyItem('projects.next.invoice', [], 'invoice', false, true),
            'billing' => $first(Predicates::BILLING) ?? new JourneyItem('projects.next.billing', [], 'billing'),
            'payment' => $first(Predicates::RECEIVABLE, Predicates::PAYMENT) ?? new JourneyItem('projects.next.payment', [], 'payment'),
            'completion' => $first(...Predicates::ALL) ?? new JourneyItem('projects.next.complete', [], 'completion'),
            default => null,
        };

        return $item === null || ($item->money && ! $money) ? null : $item;
    }
}
