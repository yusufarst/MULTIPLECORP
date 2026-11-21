<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;

/**
 * Which verified generations the retention keeps (BK-05; BK-06; BK-12; BKR-04; DIR-079 CB-11): the newest of each of
 * the last `daily` days, `weekly` ISO weeks and `monthly` months that have one, counted in the business's time zone;
 * the newest verified generation always — the last verified recovery point —; the latest pre-deployment generation
 * until the next release's, and a pre-import generation until a pre-deployment generation newer than it verifies
 * (the go-live deployment's). Only a verified generation can expire: an unverified one is never cleaned up here.
 */
final readonly class RetentionRule
{
    public function __construct(public int $daily, public int $weekly, public int $monthly, public string $timezone) {}

    public static function configured(): self
    {
        return new self(config()->integer('backup.retention.daily'), config()->integer('backup.retention.weekly'),
            config()->integer('backup.retention.monthly'), config()->string('backup.retention.timezone'));
    }

    /**
     * @param  list<array{name: string, at: CarbonImmutable, kind: string, verified: bool}>  $generations
     * @return array{keep: list<string>, expire: list<string>}
     */
    public function apply(array $generations): array
    {
        $verified = array_values(array_filter($generations, fn (array $generation): bool => $generation['verified']));
        usort($verified, fn (array $a, array $b): int => $b['at'] <=> $a['at'] ?: strcmp($b['name'], $a['name']));
        $keep = [];

        if ($verified !== []) {
            $keep[$verified[0]['name']] = true;
        }

        foreach ([['Y-m-d', $this->daily], ['o-\WW', $this->weekly], ['Y-m', $this->monthly]] as [$format, $count]) {
            $seen = [];

            foreach ($verified as $generation) {
                $period = $generation['at']->setTimezone($this->timezone)->format($format);

                if (! isset($seen[$period]) && count($seen) < $count) {
                    $seen[$period] = true;
                    $keep[$generation['name']] = true;
                }
            }
        }

        $latestPredeploy = null;

        foreach ($verified as $generation) {
            if ($generation['kind'] === 'predeploy') {
                $latestPredeploy ??= $generation;
            }
        }

        foreach ($verified as $generation) {
            if ($generation === $latestPredeploy || ($generation['kind'] === 'preimport' && ($latestPredeploy === null || $latestPredeploy['at'] <= $generation['at']))) {
                $keep[$generation['name']] = true;
            }
        }

        $expire = [];

        foreach ($verified as $generation) {
            if (! isset($keep[$generation['name']])) {
                $expire[] = $generation['name'];
            }
        }

        return ['keep' => array_keys($keep), 'expire' => $expire];
    }
}
