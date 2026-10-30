<?php

namespace App\Modules\OPS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OPS\Queries\ActivityHistory;
use App\Modules\OPS\Queries\SecurityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Owner's two log pages (OD-14; LG-05): PG-063 *Riwayat Aktivitas* (ROUTE-063; `OPS ShowActivityHistory`; NAV-063)
 * and PG-064 *Log Keamanan* (ROUTE-064; `OPS ShowSecurityLog`; NAV-064), read-only views outside SF-CMD — no command,
 * no lock, no audit (PF-12). Their routes require `audit.view` and `security_log.view`, OWNER_ONLY, so an Admin's request
 * answers as an unknown page (AZ-11). The filters and the cursor are whitelisted address keys (WS-03; IA §11); an event's
 * before and after are read on demand, a partial reload of `detail` (PF-06).
 */
final class LogsController extends Controller
{
    public function __construct(
        private readonly ActivityHistory $history,
        private readonly SecurityLog $security,
    ) {}

    /** ROUTE-063. */
    public function history(Request $request): HttpResponse
    {
        $actor = self::actor($request);
        $event = self::query($request, 'peristiwa');

        return Inertia::render('OPS/ActivityHistory', [
            ...$this->history->screen($actor, [
                'dari' => self::query($request, 'dari'),
                'sampai' => self::query($request, 'sampai'),
                'perusahaan' => self::query($request, 'perusahaan'),
                'pelaku' => self::query($request, 'pelaku'),
                'catatan' => self::query($request, 'catatan'),
                'setelah' => self::query($request, 'setelah'),
            ]),
            'detail' => Inertia::optional(fn (): ?array => $event === null ? null : $this->history->detail($event)),
        ])->toResponse($request);
    }

    /** ROUTE-064. */
    public function security(Request $request): HttpResponse
    {
        return Inertia::render('OPS/SecurityLog', [
            ...$this->security->screen(self::actor($request), [
                'jenis' => self::query($request, 'jenis'),
                'dari' => self::query($request, 'dari'),
                'sampai' => self::query($request, 'sampai'),
                'perusahaan' => self::query($request, 'perusahaan'),
                'setelah' => self::query($request, 'setelah'),
                'peristiwa' => self::query($request, 'peristiwa'),
            ]),
            // Opened from a security notice: "Kembali" returns to the origin (INT-020; NAV-064).
            'from_notice' => self::query($request, 'asal') === 'beranda',
        ])->toResponse($request);
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' && strlen($value) <= 200 ? $value : null;
    }

    private static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }
}
