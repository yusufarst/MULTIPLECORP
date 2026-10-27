<?php

namespace App\Modules\ORG\Data;

/**
 * The company master's legal fields (DATABASE §4.2) and which of them are identity assets — NPWP, the address, the
 * contact and the director, whose change asks the step-up (AU-15; ROUTE-132; PJ-19).
 */
final class CompanyFields
{
    public const LEGAL = ['legal_name', 'legal_form', 'npwp', 'address_lines', 'city', 'phone', 'email', 'director_name'];

    public const IDENTITY = ['npwp', 'address_lines', 'city', 'phone', 'email', 'director_name'];

    /** An NPWP by its digits, the form it is compared in; none when it holds none. */
    public static function npwpDigits(?string $npwp): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $npwp);

        return $digits === '' ? null : $digits;
    }
}
