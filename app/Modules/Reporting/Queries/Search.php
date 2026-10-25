<?php

namespace App\Modules\Reporting\Queries;

use App\Modules\CAT\Queries\ProductLookup;
use App\Modules\DOC\Queries\DocumentLookup;
use App\Modules\FIN\Queries\InvoiceLookup;
use App\Modules\IAM\Data\Authority;
use App\Modules\INV\Queries\SerialLookup;
use App\Modules\PRJ\Queries\ProjectLookup;
use App\Modules\PTY\Queries\PartyLookup;
use App\Support\Search\Term;

/**
 * `Reporting Search` (ROUTE-281; INT-005, INT-281): the request of the shell's type-ahead, of PG-009 and of every
 * lookup inside a form, in IP-17's order. A code is looked up exactly first in every keyed column the viewer
 * may read — SKU and barcode, serial, project number, a document's formatted number, an invoice's (PF-19) —, at
 * most a type's rows, one more saying the list is cut; then names by PF-20–PF-21, a term without a trigram by
 * equality and prefix only (PF-23). An inactive account finds nothing (ROUTE-281: every active account). Each module's query class carries its own
 * capability and the company scope in its statement (PF-22; PJ-10), so a record outside them changes no row,
 * cut or notice (DP-06). The answer names what the screen says: a short term (MSG-24), a cut list (MSG-23) or
 * an unknown code (MSG-25).
 */
final readonly class Search
{
    /** A type-ahead's rows per type (IP-17; PF-21). */
    public const ROWS = 20;

    /** The result types, in the order the groups are shown; a form's lookup names the ones it offers. */
    public const TYPES = ['product', 'serial', 'project', 'document', 'invoice', 'client', 'unit', 'pic', 'supplier'];

    public function __construct(
        private ProductLookup $products,
        private PartyLookup $parties,
        private ProjectLookup $projects,
        private InvoiceLookup $invoices,
        private SerialLookup $serials,
        private DocumentLookup $documents,
    ) {}

    /**
     * @param  list<string>  $types  the result types asked for; all of them when empty
     * @return array{exact: list<array<string, string|null>>, groups: list<array{type: string, rows: list<array<string, string|null>>}>, notice: ?string}
     */
    public function run(Authority $viewer, Term $term, array $types = []): array
    {
        if (! $viewer->active) {
            return ['exact' => [], 'groups' => [], 'notice' => null];
        }

        $wanted = $types === [] ? self::TYPES : array_values(array_intersect(self::TYPES, $types));
        $asks = fn (string $type): bool => in_array($type, $wanted, true);
        // An invoice's documents are shown once, as the invoice, to the viewer who sees it so.
        $asInvoices = $asks('invoice') && $viewer->allows(InvoiceLookup::CAPABILITY) ? InvoiceLookup::DOCUMENT_TYPES : [];

        $coded = [
            'product' => fn () => $this->products->exact($term, self::ROWS),
            'serial' => fn () => $this->serials->exact($viewer, $term, self::ROWS),
            'project' => fn () => $this->projects->exact($viewer, $term, self::ROWS),
            'document' => fn () => $this->documents->exact($viewer, $term, self::ROWS, $asInvoices),
            'invoice' => fn () => $this->invoices->exact($viewer, $term, self::ROWS),
        ];

        $exact = [];
        $cut = false;

        foreach ($coded as $type => $find) {
            if (! $asks($type)) {
                continue;
            }

            $found = $find();
            $cut = $cut || count($found) > self::ROWS;
            $exact = [...$exact, ...array_slice($found, 0, self::ROWS)];
        }

        $named = [
            'product' => fn () => $this->products->names($term, self::ROWS),
            'project' => fn () => $this->projects->titles($viewer, $term, self::ROWS),
            'client' => fn () => $this->parties->clients($term, self::ROWS),
            'unit' => fn () => $this->parties->units($viewer, $term, self::ROWS),
            'pic' => fn () => $this->parties->contacts($viewer, $term, self::ROWS),
            'supplier' => fn () => $this->parties->suppliers($term, self::ROWS),
        ];

        $seen = array_flip(array_map(fn (array $row): string => $row['type'].':'.$row['id'], $exact));
        $groups = [];

        foreach ($named as $type => $find) {
            if (! $asks($type)) {
                continue;
            }

            $found = $find();
            $cut = $cut || $found['cut'];
            $rows = array_values(array_filter($found['rows'], fn (array $row): bool => ! isset($seen[$row['type'].':'.$row['id']])));

            if ($rows !== []) {
                $groups[] = ['type' => $type, 'rows' => $rows];
            }
        }

        return ['exact' => $exact, 'groups' => $groups, 'notice' => self::notice($term, $exact, $groups, $cut)];
    }

    /**
     * @param  list<array<string, string|null>>  $exact
     * @param  list<array{type: string, rows: list<array<string, string|null>>}>  $groups
     */
    private static function notice(Term $term, array $exact, array $groups, bool $cut): ?string
    {
        if ($cut) {
            return 'cut';
        }

        if ($exact === [] && $groups === [] && $term->looksLikeCode()) {
            return 'unknown_code';
        }

        return $term->hasTrigram() ? null : 'short';
    }
}
