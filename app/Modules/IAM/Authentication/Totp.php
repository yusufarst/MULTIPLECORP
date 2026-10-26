<?php

namespace App\Modules\IAM\Authentication;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Laravel\Fortify\RecoveryCode;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * SECURITY AU-16's TOTP, on the TOTP library Fortify installs: six digits, a 30-second period and one step of
 * tolerance either side, each set here rather than left to the library's defaults; a new secret of 32 base32
 * characters (160 bits); a code accepted only at a time step later than the account's last accepted one, the
 * step found against the project's clock (RV-12). The QR code is rendered here, never by a remote service, and
 * the recovery codes are Fortify's format — two random 10-character halves (AU-19).
 */
final class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    public const WINDOW = 1;

    public const SECRET_LENGTH = 32;

    public const RECOVERY_CODES = 8;

    private const ISSUER = 'MultipleCorp';

    private Google2FA $library;

    public function __construct()
    {
        $this->library = new Google2FA;
        $this->library->setOneTimePasswordLength(self::DIGITS);
        $this->library->setKeyRegeneration(self::PERIOD);
        $this->library->setWindow(self::WINDOW);
    }

    public function newSecret(): string
    {
        return $this->library->generateSecretKey(self::SECRET_LENGTH);
    }

    /** The time step of now on the project's clock, which tests travel. */
    public static function currentStep(): int
    {
        return intdiv(now()->getTimestamp(), self::PERIOD);
    }

    /**
     * The time step at which the code matches, within one step either side of now and later than the last
     * accepted step; null for any code that matches none — a wrong, reused or stale one alike.
     */
    public function stepOf(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code, ?int $lastStep): ?int
    {
        if (preg_match('/^\d{'.self::DIGITS.'}$/', $code) !== 1) {
            return null;
        }

        try {
            $step = $this->library->verifyKeyNewer($secret, $code, $lastStep, self::WINDOW, self::currentStep());
        } catch (Throwable) {
            return null;
        }

        return is_int($step) ? $step : null;
    }

    /** The code of a secret at a step — the tests' authenticator. */
    public function codeAt(#[\SensitiveParameter] string $secret, int $step): string
    {
        return $this->library->oathTotp($secret, $step);
    }

    /**
     * The QR code of the enrolment, encoded here and never by a remote service (AU-16): its rows of dark (1) and
     * light (0) modules, which the page draws as SVG shapes — no raw markup reaches it (WS-09).
     *
     * @return list<string>
     */
    public function qrModules(string $accountName, #[\SensitiveParameter] string $secret): array
    {
        $matrix = Encoder::encode($this->library->getQRCodeUrl(self::ISSUER, $accountName, $secret), ErrorCorrectionLevel::M())->getMatrix();
        $rows = [];

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $row = '';

            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $row .= $matrix->get($x, $y) === 1 ? '1' : '0';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<string> eight new recovery codes (AU-19) */
    public function newRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $codes[] = RecoveryCode::generate();
        }

        return $codes;
    }
}
