<?php

namespace App\Modules\OPS\Data;

/**
 * BR-CR-06's legality matrix, the one table every correcting command consults before it acts: for each lifecycle,
 * which correction primitive is allowed, prohibited, allowed only with its coupled companion correction (BR-CR-03), or
 * allowed in its restricted form — a draft edited, a reversal linked, a return as a sales return. The step-by-step
 * procedure is the correction matrix's (WORKFLOWS §8); this fixes legality only, and its unit test holds it equal to
 * BUSINESS_RULES's table.
 */
final class CorrectionLegality
{
    public const ALLOWED = 'ALLOWED';

    public const PROHIBITED = 'PROHIBITED';

    public const COMPANION = 'COMPANION';

    public const PRIMITIVES = ['EDIT', 'REVISION', 'VOID', 'REVERSAL', 'RETURN', 'REALLOC', 'COMPENS', 'ARCHIVE'];

    /**
     * Each lifecycle's row, one cell a primitive: ALLOWED, PROHIBITED, COMPANION, or the restricted form the cell names.
     *
     * @var array<string, array<string, string>>
     */
    public const MATRIX = [
        'QUOTATION' => ['EDIT' => 'draft only', 'REVISION' => 're-approval', 'VOID' => self::PROHIBITED, 'REVERSAL' => self::PROHIBITED, 'RETURN' => self::PROHIBITED, 'REALLOC' => self::PROHIBITED, 'COMPENS' => self::PROHIBITED, 'ARCHIVE' => self::PROHIBITED],
        'DOCUMENT' => ['EDIT' => 'draft only', 'REVISION' => 'new version', 'VOID' => 'number retired', 'REVERSAL' => self::PROHIBITED, 'RETURN' => self::PROHIBITED, 'REALLOC' => self::PROHIBITED, 'COMPENS' => self::PROHIBITED, 'ARCHIVE' => self::PROHIBITED],
        'INVOICE_UNBILLED' => ['EDIT' => 'draft only', 'REVISION' => self::ALLOWED, 'VOID' => self::ALLOWED, 'REVERSAL' => self::PROHIBITED, 'RETURN' => self::PROHIBITED, 'REALLOC' => self::PROHIBITED, 'COMPENS' => self::PROHIBITED, 'ARCHIVE' => self::PROHIBITED],
        'INVOICE_BILLED' => ['EDIT' => self::PROHIBITED, 'REVISION' => self::COMPANION, 'VOID' => self::COMPANION, 'REVERSAL' => self::PROHIBITED, 'RETURN' => 'via sales return', 'REALLOC' => self::PROHIBITED, 'COMPENS' => self::ALLOWED, 'ARCHIVE' => self::PROHIBITED],
        'MONEY_FACT' => ['EDIT' => self::PROHIBITED, 'REVISION' => self::PROHIBITED, 'VOID' => self::PROHIBITED, 'REVERSAL' => 'contra-fact', 'RETURN' => self::PROHIBITED, 'REALLOC' => 'applications only', 'COMPENS' => self::ALLOWED, 'ARCHIVE' => self::PROHIBITED],
        'STOCK_MOVEMENT' => ['EDIT' => self::PROHIBITED, 'REVISION' => self::PROHIBITED, 'VOID' => self::PROHIBITED, 'REVERSAL' => 'linked', 'RETURN' => 'commercial', 'REALLOC' => self::PROHIBITED, 'COMPENS' => self::ALLOWED, 'ARCHIVE' => self::PROHIBITED],
        'RESERVATION' => ['EDIT' => self::PROHIBITED, 'REVISION' => 'adjust (audited)', 'VOID' => 'release', 'REVERSAL' => self::PROHIBITED, 'RETURN' => self::PROHIBITED, 'REALLOC' => self::PROHIBITED, 'COMPENS' => self::PROHIBITED, 'ARCHIVE' => self::PROHIBITED],
        'DELIVERY' => ['EDIT' => 'pre-commit', 'REVISION' => self::PROHIBITED, 'VOID' => self::PROHIBITED, 'REVERSAL' => 'correction record', 'RETURN' => 'sales return', 'REALLOC' => self::PROHIBITED, 'COMPENS' => self::ALLOWED, 'ARCHIVE' => self::PROHIBITED],
        'ADMIN_REQUIREMENT' => ['EDIT' => 'pre-satisfaction', 'REVISION' => self::PROHIBITED, 'VOID' => 'N/A waiver (eligible only)', 'REVERSAL' => self::PROHIBITED, 'RETURN' => self::PROHIBITED, 'REALLOC' => self::PROHIBITED, 'COMPENS' => 'reopen', 'ARCHIVE' => self::PROHIBITED],
        'MASTER_DATA' => ['EDIT' => self::ALLOWED, 'REVISION' => self::PROHIBITED, 'VOID' => self::PROHIBITED, 'REVERSAL' => self::PROHIBITED, 'RETURN' => self::PROHIBITED, 'REALLOC' => self::PROHIBITED, 'COMPENS' => self::PROHIBITED, 'ARCHIVE' => 'carve-out BR-ACC-03'],
    ];

    /** The cell of a lifecycle and primitive; an unknown pair is prohibited. */
    public static function of(string $lifecycle, string $primitive): string
    {
        return self::MATRIX[$lifecycle][$primitive] ?? self::PROHIBITED;
    }

    /** Whether a correcting command may use the primitive — a COMPANION cell only with its companion in the same action. */
    public static function allows(string $lifecycle, string $primitive, bool $withCompanion = false): bool
    {
        $cell = self::of($lifecycle, $primitive);

        return $cell !== self::PROHIBITED && ($cell !== self::COMPANION || $withCompanion);
    }
}
