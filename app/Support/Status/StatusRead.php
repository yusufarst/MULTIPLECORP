<?php

namespace App\Support\Status;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * One kind of state a page polls through ROUTE-285 (INT-285; ADMIN_FLOW IP-12): the rendition of a document
 * version here, an export request and an import batch with their Tasks. Its read is the page's query class,
 * authorized by the page's view capability at request time; a denied and an unknown record answer alike.
 */
interface StatusRead
{
    public const TAG = 'status-reads';

    /** The `{kind}` of `/status/{kind}/{id}`. */
    public function kind(): string;

    /**
     * @return array<string, scalar|null>
     *
     * @throws ModelNotFoundException
     */
    public function read(Authenticatable $viewer, string $id): array;
}
