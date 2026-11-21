<?php

use App\Support\Monitoring\AlertSignals;

// TL-03 (TASK-075; HO-25; LG-03; LG-07): which records are alerts and the neutral name each sends — every CRITICAL or
// higher record by its sentence alone, HO-25's named signals below it, a FAILED access change by its command's type —,
// never a context value, an address or a long number.

it('names every CRITICAL record by its sentence alone and HO-25\'s signals below it (HO-25; BK-10; LG-07)', function () {
    expect(AlertSignals::classify(500, 'Backup alert (BK-10): the backup run failed', ['reason' => 'disk penuh', 'generation' => 'x']))->toBe('Backup alert (BK-10): the backup run failed')
        ->and(AlertSignals::classify(550, 'A second-factor cooldown started (AU-05; LG-07).', ['user_id' => 7]))->toBe('A second-factor cooldown started (AU-05; LG-07).')
        ->and(AlertSignals::classify(400, 'A command met a deadlock and runs again (SQ-13; RY-01).', []))->toBe('HO-25 deadlock (40P01)')
        ->and(AlertSignals::classify(300, 'A guard differs from its facts (ReconcileGuards; GAP-031).', ['table' => 'invoice_balances']))->toBe('HO-25 guard reconciliation difference')
        ->and(AlertSignals::classify(400, 'A command failed (CONCURRENCY_IDEMPOTENCY §12).', ['command_type' => 'IAM.AssignRole']))->toBe('HO-25 failed access change')
        ->and(AlertSignals::classify(400, 'A command failed (CONCURRENCY_IDEMPOTENCY §12).', ['command_type' => 'PRJ.CreateProject']))->toBeNull()
        ->and(AlertSignals::classify(300, 'A statement was slower than its bound (SZ-06).', []))->toBeNull()
        ->and(AlertSignals::classify(400, 'Something ordinary failed.', []))->toBeNull();
});

it('masks an address and a long number a sentence was built with, and bounds its length (LG-03)', function () {
    expect(AlertSignals::neutral("Akun budi@contoh.test gagal masuk 1234567890 kali\nlagi"))->toBe('Akun [address] gagal masuk [number] kali\nlagi')
        ->and(mb_strlen(AlertSignals::neutral(str_repeat('a', 400))))->toBe(160);
});
