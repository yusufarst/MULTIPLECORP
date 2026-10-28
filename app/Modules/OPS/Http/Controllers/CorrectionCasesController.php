<?php

namespace App\Modules\OPS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OPS\Actions\CloseCorrectionCase;
use App\Modules\OPS\Queries\CorrectionCaseList;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-040 *Kasus Koreksi* (ROUTE-040; NAV-040; SCR-33) and `OPS CloseCorrectionCase` (ROUTE-271; INT-271; NX-06):
 * the cases of a branch with their residuals, and "Tutup Kasus" with its outcome as the reason (IP-09), its answer in
 * place as flash data (IP-01) — REJECTED naming each residual in words while one stands (C-46), a stale case a
 * CONFLICT with the input kept (IP-03). An unknown case and one the viewer may not see answer alike (AZ-11).
 */
final class CorrectionCasesController extends Controller
{
    public const PAGE = '/dokumen/kasus';

    public function __construct(private readonly CorrectionCaseList $cases) {}

    public function index(Request $request): HttpResponse
    {
        return Inertia::render('OPS/CorrectionCases', $this->cases->screen(self::actor($request), self::query($request, 'perusahaan'), self::query($request, 'setelah'), self::query($request, 'kasus')))->toResponse($request);
    }

    public function close(Request $request, CloseCorrectionCase $close, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $case = $this->cases->find($actor, $id);

        /** @var array<string, mixed> $input */
        $input = Validator::make($request->only(['command_id', 'lock_version', 'alasan']), [
            'command_id' => ['required', 'uuid'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'required' => __('cases.validation.required'),
            'min' => __('cases.validation.reason'),
            'max' => __('cases.validation.too_long'),
        ])->validate();

        $outcome = $close->run(self::text($input['command_id']), $actor, $case, is_numeric($input['lock_version']) ? (int) $input['lock_version'] : 0, self::text($input['alasan']));
        $residuals = is_array($outcome->state['residuals'] ?? null) ? array_keys($outcome->state['residuals']) : [];

        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say('cases.answer.closed')],
            Outcome::REJECTED => [null, $outcome->code === 'C-46'
                ? self::say('cases.refusal.residual', ['daftar' => implode(', ', array_map(fn (mixed $name): string => self::say('cases.residual.'.self::text($name)), $residuals))])
                : self::say('messages.msg_04')],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-01' => ['stale', self::say('messages.msg_03')],
                'ST-02' => ['stale', self::say('cases.answer.state_changed')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text]);

        return redirect()->to(self::PAGE.'?'.http_build_query(['perusahaan' => self::query($request, 'perusahaan'), 'kasus' => $case->public_id]), 303);
    }

    /** @param  array<string, string>  $replace */
    private static function say(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key) ?? $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
