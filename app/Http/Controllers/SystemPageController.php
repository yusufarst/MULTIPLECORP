<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ROUTE-005, PG-005 *Halaman Sistem* for an address no route resolves (INFORMATION_ARCHITECTURE §5.4; IP-21;
 * AZ-11): the exception handler's entry, reached through the web group so that the page sits in the shell of a
 * signed-in account. It answers not found and nothing else, exactly as an out-of-scope or Owner-only address
 * answers; the page itself is the exception handler's (`SystemAnswer`).
 */
final class SystemPageController
{
    public function __invoke(): never
    {
        throw new NotFoundHttpException;
    }
}
