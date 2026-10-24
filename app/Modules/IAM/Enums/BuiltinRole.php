<?php

namespace App\Modules\IAM\Enums;

/**
 * The two built-in launch roles of PERMISSIONS_MATRIX RG-01. Role identity is read only by the uses AZ-02
 * lists — the effective-capability resolver above all —, never by a policy or gate; the architecture
 * scan allows this enumeration only there (SM-03).
 */
enum BuiltinRole: string
{
    case Owner = 'OWNER';
    case AdminOperasional = 'ADMIN_OPERASIONAL';
}
