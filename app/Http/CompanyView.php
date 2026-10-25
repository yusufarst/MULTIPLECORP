<?php

namespace App\Http;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CompanyScope;
use App\Modules\ORG\Queries\CompanyDirectory;
use Illuminate\Http\Request;

/**
 * The working filter "Tampilkan" of a list, a queue or *Beranda* (INFORMATION_ARCHITECTURE §7; WF-ACC-01; INT-007;
 * TASK-016): the whitelisted address key `perusahaan`, the public identifiers of the companies to show, so that
 * reload and Back keep it and nothing else stores it (DP-02; D-IA-02). It is a view, never an authorization: the
 * server intersects it with the viewer's scope (CS-03), and the page's query classes apply the result.
 */
final class CompanyView
{
    public const KEY = 'perusahaan';

    public static function scope(Request $request, Authority $viewer): CompanyScope
    {
        $asked = $request->query(self::KEY);
        $publicIds = is_array($asked) ? array_values(array_filter($asked, 'is_string')) : (is_string($asked) ? explode(',', $asked) : []);

        return $viewer->scope->narrowedTo(app(CompanyDirectory::class)->idsOf($viewer->scope, array_slice($publicIds, 0, 50)));
    }
}
