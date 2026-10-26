<?php

use App\Modules\IAM\Recovery\IncidentRun;
use App\Modules\IAM\Recovery\IncidentRuns;
use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\RecoveryHalted;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Carbon\CarbonImmutable;

// TL-03 (TASK-020; SECURITY AU-14, AU-25; DIR-083 OE-10): the window and halt rules — a window start is an ISO 8601
// instant with its offset, never in the future, recorded in UTC with microseconds so that a restart and every
// recovery step compare it exactly; a halt names the Owner's incident decision outside the application, first on a
// trusted state; the operator states a name of one line.

it('reads a window start with its offset, in UTC, and refuses any other form or a future instant (AU-25)', function () {
    expect(IncidentRuns::format(IncidentRuns::parse('2026-10-01T08:00:00+07:00')))->toBe('2026-10-01T01:00:00.000000Z')
        ->and(IncidentRuns::format(IncidentRuns::parse('2026-10-01T01:00:00.123456Z')))->toBe('2026-10-01T01:00:00.123456Z')
        ->and(IncidentRuns::parse('2026-10-01T08:00+07:00')->equalTo(IncidentRuns::parse('2026-10-01T01:00:00Z')))->toBeTrue();

    foreach (['2026-10-01', '2026-10-01 08:00:00', '01/10/2026 08:00', 'kemarin', '', '2026-13-01T00:00:00Z'] as $input) {
        expect(fn () => IncidentRuns::parse($input))->toThrow(RecoveryRefused::class);
    }

    expect(fn () => IncidentRuns::parse(CarbonImmutable::now()->addHour()->toIso8601String()))->toThrow(RecoveryRefused::class, 'future');
});

it('compares a run\'s recorded window start exactly, the value every event of the run carries (AU-25; RV-17)', function () {
    $run = new IncidentRun('run', IncidentRuns::parse('2026-10-01T08:00:00+07:00'), CarbonImmutable::now(), null, false);

    expect($run->reference())->toBe(['incident_run' => 'run', 'window_start' => '2026-10-01T01:00:00.000000Z'])
        ->and($run->completed())->toBeFalse()
        ->and($run->windowStart->equalTo(IncidentRuns::parse('2026-10-01T01:00:00.000001Z')))->toBeFalse();
});

it('names the Owner\'s incident decision outside the application in every halt, first on a trusted state (DIR-083 OE-10; criterion 5)', function () {
    $halt = RecoveryHalted::because('no account is a pre-window Owner');

    expect($halt)->toBeInstanceOf(RecoveryRefused::class)
        ->and($halt->getMessage())->toContain('HALT (AU-25): no account is a pre-window Owner')
        ->toContain('On a trusted state this goes first to the Owner\'s incident decision outside the application, with the client')
        ->toContain('on a restored state it ends in that decision')
        ->toContain('no Owner is created, promoted or guessed');
});

it('records the operator by the stated name and the host account, a name of one line (AU-14; SM-24)', function () {
    $operator = OperatorIdentity::stated('  Yusuf Operator ');

    expect($operator->name)->toBe('Yusuf Operator')
        ->and($operator->hostAccount)->not->toBe('')
        ->and($operator->toArray())->toHaveKeys(['host_account', 'name']);

    foreach ([null, '', '   ', "Yusuf\nPalsu", str_repeat('a', 121)] as $name) {
        expect(fn () => OperatorIdentity::stated($name))->toThrow(RecoveryRefused::class, '--operator');
    }
});
