<?php

namespace App\Modules\IAM\Recovery;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AU-21's re-encryption command for a rotation through the framework's previous-keys list (`APP_PREVIOUS_KEYS`: the
 * current key encrypts; decryption tries the current key, then each previous one; RV-17; INFRASTRUCTURE §7.4): each
 * enrolled account in id order, one TX-07 transaction under its row lock (LK-03), its TOTP secret and recovery-code
 * set re-read and written again under the current key by an update that matches only while the stored ciphertext is
 * the one read, with its audit event. The verified count compares those updates with the enrolled accounts; only an
 * equal count lets the old key be retired. TOTP_SECRETS_REENCRYPTED follows the run and alerts (LG-07). No secret is
 * read out, printed or logged.
 */
final class Reencryption
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SecurityEventRecorder $events,
    ) {}

    /**
     * @return array{reencrypted: int, enrolled: int, verified: bool}
     *
     * @throws RecoveryRefused
     */
    public function run(OperatorIdentity $operator): array
    {
        OperatorIdentity::ensureConsole();
        $reencrypted = 0;

        foreach (AccountHistory::ids(DB::table('users')->whereNotNull('two_factor_secret')->orderBy('id')->pluck('id')->all()) as $userId) {
            $reencrypted += $this->account($userId, $operator) ? 1 : 0;
        }

        $enrolled = DB::table('users')->whereNotNull('two_factor_secret')->count();
        $verified = $reencrypted === $enrolled;
        $this->events->record(SecurityEventKind::TotpSecretsReencrypted, reasonCode: $verified ? 'COUNT_VERIFIED' : 'COUNT_MISMATCH');
        Log::alert('TOTP secrets were re-encrypted under the current key (AU-21; LG-07).', ['reencrypted' => $reencrypted, 'enrolled' => $enrolled, 'operator' => $operator->describe()]);

        return ['reencrypted' => $reencrypted, 'enrolled' => $enrolled, 'verified' => $verified];
    }

    /** @throws RecoveryRefused */
    private function account(int $userId, OperatorIdentity $operator): bool
    {
        return DB::transaction(function () use ($userId, $operator): bool {
            $row = DB::table('users')->where('id', $userId)->lock('for no key update')->first(['public_id', 'two_factor_secret', 'two_factor_recovery_codes']);

            if ($row === null || ! is_string($row->two_factor_secret) || ! is_string($row->two_factor_recovery_codes)) {
                return false;
            }

            try {
                $secret = Crypt::encryptString(Crypt::decryptString($row->two_factor_secret));
                $codes = Crypt::encryptString(Crypt::decryptString($row->two_factor_recovery_codes));
            } catch (DecryptException) {
                throw new RecoveryRefused('Account '.(is_string($row->public_id) ? $row->public_id : '')."'s TOTP data decrypts under neither the current key nor a listed previous key; "
                    .'list the key it was encrypted with in APP_PREVIOUS_KEYS and run again — no key is retired (AU-21).');
            }

            $updated = DB::table('users')->where('id', $userId)
                ->where('two_factor_secret', $row->two_factor_secret)->where('two_factor_recovery_codes', $row->two_factor_recovery_codes)
                ->update(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $codes]);

            if ($updated !== 1) {
                return false;
            }

            $this->audit->record(
                action: 'IAM.TotpSecretsReencrypted',
                authorityClass: AuthorityClass::System,
                actorUserId: null,
                companyId: null,
                entityType: 'user',
                entityId: $userId,
                entityPublicId: is_string($row->public_id) ? $row->public_id : null,
                change: AuditChange::none(),
                metadata: ['operator' => $operator->toArray()],
            );

            return true;
        });
    }
}
