<?php

namespace App\Modules\OPS\Enums;

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
}
