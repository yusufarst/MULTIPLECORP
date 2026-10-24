<?php

namespace App\Modules\OPS\Enums;

/**
 * How an audited change relates to a record it names (BR-XC-01; BR-CR-01): the records it affects, and
 * the original that a correction revises, voids, reverses, returns or supersedes — never deletes.
 */
enum AuditRelation: string
{
    case Affects = 'AFFECTS';
    case Revises = 'REVISES';
    case Voids = 'VOIDS';
    case Reverses = 'REVERSES';
    case Returns = 'RETURNS';
    case Supersedes = 'SUPERSEDES';
}
