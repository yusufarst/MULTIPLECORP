<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Data\FileFamilies;
use App\Modules\IAM\Data\Authority;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Who reaches a generated document (PERMISSIONS_MATRIX §4, §7.2 rows 86–89; PJ-15): its metadata with
 * `projects.view`, its payload, draft fields and rendition with its family's view capability, each within the
 * document's company; issuing needs `documents.issue` with that family's view — DOC-01 `quotation.manage` too —,
 * revising and voiding `documents.correct` with it. The invoice layouts are corrected through the invoice and a
 * quotation is revised through its revision, never voided (L-25; CM-23; CM-24).
 */
final class DocumentAccess
{
    public const ISSUE = 'documents.issue';

    public const CORRECT = 'documents.correct';

    /** The layouts the invoice's own issue and correction govern (`invoice.issue`, `invoice.correct`; L-26). */
    public const INVOICE_LAYOUTS = ['DOC-04', 'DOC-06'];

    /** Whether the type is prepared and issued on PG-036; a quotation is issued only from its revision, at the record (WF-QUO-01; ROUTE-155). */
    public static function issuedHere(string $typeCode): bool
    {
        return $typeCode !== 'DOC-01';
    }

    public static function mayViewMetadata(Authority $viewer, int $companyId): bool
    {
        return $viewer->allows('projects.view', $companyId);
    }

    public static function mayViewContent(Authority $viewer, string $typeCode, int $companyId): bool
    {
        $family = FileFamilies::document($typeCode);

        return $family !== null && $viewer->allows($family, $companyId);
    }

    public static function mayIssue(Authority $viewer, string $typeCode, int $companyId): bool
    {
        return $viewer->allows(self::ISSUE, $companyId) && self::mayViewContent($viewer, $typeCode, $companyId)
            && ($typeCode !== 'DOC-01' || $viewer->allows('quotation.manage', $companyId));
    }

    public static function mayCorrect(Authority $viewer, string $typeCode, int $companyId): bool
    {
        return $viewer->allows(self::CORRECT, $companyId) && self::mayViewContent($viewer, $typeCode, $companyId)
            && self::correctedHere($typeCode);
    }

    /** Whether ROUTE-223 and ROUTE-224 correct the type, or its own record's correction does. */
    public static function correctedHere(string $typeCode): bool
    {
        return $typeCode !== 'DOC-01' && ! in_array($typeCode, self::INVOICE_LAYOUTS, true);
    }

    /** @throws AuthorizationException when the authority re-read under the locks no longer issues the type (RV-01) */
    public static function ensureIssue(?Authority $authority, string $typeCode, int $companyId): void
    {
        if ($authority === null || ! self::mayIssue($authority, $typeCode, $companyId)) {
            throw new AuthorizationException;
        }
    }

    /** @throws AuthorizationException when the authority re-read under the locks no longer corrects the type (RV-01) */
    public static function ensureCorrect(?Authority $authority, string $typeCode, int $companyId): void
    {
        if ($authority === null || ! ($authority->allows(self::CORRECT, $companyId) && self::mayViewContent($authority, $typeCode, $companyId))) {
            throw new AuthorizationException;
        }
    }
}
