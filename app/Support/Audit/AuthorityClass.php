<?php

namespace App\Support\Audit;

use App\Modules\IAM\Data\CapabilityCatalogue;

/**
 * The authority an audited change exercised (DATABASE §4.13 `audit_events.authority_class`; WORKFLOWS §4;
 * SECURITY LG-01): a capability's class, or SYSTEM for an account's own change, which exercises no
 * capability (AZ-10), and for the operator's commands and the events without an application actor.
 */
enum AuthorityClass: string
{
    case Adm = 'ADM';
    case AdmPlus = 'ADM_PLUS';
    case OwnerOnly = 'OWNER_ONLY';
    case System = 'SYSTEM';

    private const RANK = ['SYSTEM' => 0, 'ADM' => 1, 'ADM_PLUS' => 2, 'OWNER_ONLY' => 3];

    /**
     * AZ-10: an event of a command records at least the class of the capability the command exercised — a project or
     * a purchase recorded by the Owner's import commit (`import.commit`, OWNER_ONLY) records OWNER_ONLY, the same
     * record by an Admin's command its own ADM (TASK-077).
     */
    public static function exercisedBy(self $own, string $capability): self
    {
        $exercised = CapabilityCatalogue::has($capability) ? CapabilityCatalogue::classOf($capability) : $own;

        return self::RANK[$exercised->value] > self::RANK[$own->value] ? $exercised : $own;
    }
}
