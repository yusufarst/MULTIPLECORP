<?php

namespace App\Modules\DOC\Contracts;

use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;

/**
 * The inversion contract of ARCHITECTURE §3 through which the Cash-In facts a bank line's arrival cites are set in its own command (NX-08; DATABASE §11.3;
 * QS-12): DOC owns the line and its guard, the citing module owns the facts, so DOC never reads a higher tier's tables.
 */
interface BankLineCitations
{
    /**
     * What citing these facts holds, joined to the command's set before its clock.
     *
     * @param  list<int>  $facts
     */
    public function locks(array $facts): LockSet;

    /**
     * The facts' citations set to the line just recorded, with the guard moved by what they carry; a fact already cited or of another account refused.
     *
     * @param  list<int>  $facts
     */
    public function arrived(CommandContext $context, int $lineId, int $accountId, string $amount, array $facts): void;
}
