<?php

namespace App\Modules\FIN\Data;

use App\Support\Money;

/**
 * Which project each rupiah of a source's rest counts for (WF-FIN-06; `DIR-104`; APPR-028), derived by replaying the source's own rows in recorded order and never stored —
 * credit itself stays per client and company (CALC-06), spendable on any of their invoices or by refund:
 * - before its first invoice-target application a source's rest counts for no project; the rest standing when that command ends counts for that invoice's project;
 * - an invoice-target application ended by its invoice's correction makes its amount the credit of that invoice's project; what a reallocation does not re-apply counts as
 *   released; one ended by its source's contra or by its re-recording makes none, the money being gone;
 * - later spending — a new application, a refund's backing, the unapplied part of a source's contra — takes the oldest first, each amount remembering its project;
 * - a refund's contra gives back what the refund took to the projects it took it from, which counts as arising again.
 * What has arisen for a project is the sum of every amount this rule ever made its credit; spending lowers what is left, never what has arisen. Pure.
 */
final class CreditAttribution
{
    /** @var list<array{project: ?int, amount: Money}> the source's rest by project, oldest first */
    private array $pool = [];

    /** @var array<int, Money> */
    private array $arisen = [];

    /** @var array<int, list<array{project: ?int, amount: Money}>> what each application took, by its id */
    private array $taken = [];

    private Money $unattributed;

    private bool $attributed = false;

    private function __construct(string $gross)
    {
        $this->unattributed = Money::of($gross);
    }

    /**
     * @param  list<array{contra: string, ends: list<array{id: int, reason: string, project: ?int, amount: string, reapplied: string}>, creates: list<array{id: int, target: string, project: ?int, amount: string, successor: bool}>}>  $commands  the source's commands, oldest first
     * @return array{projects: array<int, array{arisen: string, left: string}>, unattributed: string}
     */
    public static function replay(string $gross, array $commands): array
    {
        $replay = new self($gross);

        foreach ($commands as $command) {
            $replay->command($command);
        }

        return $replay->result();
    }

    /** @param  array{contra: string, ends: list<array{id: int, reason: string, project: ?int, amount: string, reapplied: string}>, creates: list<array{id: int, target: string, project: ?int, amount: string, successor: bool}>}  $command */
    private function command(array $command): void
    {
        // A source's contra takes its applied money with the applications it ends; the rest of it is unapplied money gone, the oldest first.
        $gone = Money::of($command['contra']);

        foreach ($command['ends'] as $end) {
            if ($end['reason'] === 'SOURCE_CONTRA') {
                $gone = $gone->minus(Money::of($end['amount']));
            }
        }

        if ($gone->isPositive()) {
            $this->spend($gone);
        }

        foreach ($command['ends'] as $end) {
            if ($end['reason'] === 'INVOICE_CORRECTED') {
                $this->arise($end['project'], Money::of($end['amount']));
            } elseif ($end['reason'] === 'REALLOCATED') {
                $this->arise($end['project'], Money::of($end['amount'])->minus(Money::of($end['reapplied'])));
            } elseif ($end['reason'] === 'REFUND_CONTRA') {
                foreach ($this->taken[$end['id']] ?? [] as $part) {
                    $this->arise($part['project'], $part['amount']);
                }
            }
        }

        $invoiced = false;
        $first = null;

        foreach ($command['creates'] as $create) {
            // A reallocation's successor carries the money its ended row held: nothing is spent again.
            if ($create['successor']) {
                continue;
            }

            $this->taken[$create['id']] = $this->spend(Money::of($create['amount']));

            if ($create['target'] === 'INVOICE' && ! $invoiced) {
                $invoiced = true;
                $first = $create['project'];
            }
        }

        // The rest standing when the command of the earliest invoice-target application ends counts for that invoice's project.
        if (! $this->attributed && $invoiced) {
            $this->attributed = true;
            $rest = $this->unattributed;
            $this->unattributed = Money::zero();
            $this->arise($first, $rest);
        }
    }

    /** @return list<array{project: ?int, amount: Money}> */
    private function spend(Money $amount): array
    {
        if (! $this->attributed) {
            $part = $amount->compareTo($this->unattributed) > 0 ? $this->unattributed : $amount;
            $this->unattributed = $this->unattributed->minus($part);

            return [['project' => null, 'amount' => $part]];
        }

        $parts = [];

        while ($amount->isPositive() && $this->pool !== []) {
            $head = $this->pool[0];
            $part = $amount->compareTo($head['amount']) > 0 ? $head['amount'] : $amount;
            $parts[] = ['project' => $head['project'], 'amount' => $part];
            $amount = $amount->minus($part);
            $rest = $head['amount']->minus($part);

            if ($rest->isPositive()) {
                $this->pool[0] = ['project' => $head['project'], 'amount' => $rest];
            } else {
                array_shift($this->pool);
            }
        }

        return $parts;
    }

    private function arise(?int $project, Money $amount): void
    {
        if (! $amount->isPositive()) {
            return;
        }

        $this->pool[] = ['project' => $project, 'amount' => $amount];

        if ($project !== null) {
            $this->arisen[$project] = ($this->arisen[$project] ?? Money::zero())->plus($amount);
        }
    }

    /** @return array{projects: array<int, array{arisen: string, left: string}>, unattributed: string} */
    private function result(): array
    {
        $left = [];
        $unattributed = $this->unattributed;

        foreach ($this->pool as $bucket) {
            if ($bucket['project'] === null) {
                $unattributed = $unattributed->plus($bucket['amount']);
            } else {
                $left[$bucket['project']] = ($left[$bucket['project']] ?? Money::zero())->plus($bucket['amount']);
            }
        }

        $projects = [];

        foreach ($this->arisen as $project => $amount) {
            $projects[$project] = ['arisen' => $amount->toString(), 'left' => ($left[$project] ?? Money::zero())->toString()];
        }

        return ['projects' => $projects, 'unattributed' => $unattributed->toString()];
    }
}
