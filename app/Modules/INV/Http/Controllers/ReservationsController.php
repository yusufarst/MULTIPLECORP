<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\INV\Actions\ReleaseReservation;
use App\Modules\INV\Actions\Reserve;
use App\Modules\INV\Queries\Reservations;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-025 *Reservasi* (ROUTE-025) and its commands (WF-INV-02; J-INV-02): "Reservasi" (ROUTE-184; AX-01) from PG-025 or
 * PG-011's *Item dan pemenuhan*, "Kurangi Reservasi" and "Lepas" with their reason (ROUTE-185; AX-02). Each command answers
 * where it was sent from (NV-03; NAV-025): a refusal names the current availability or remainder (ST-06), a quantity outside
 * the product's scale or not above zero is answered at its field (MSG-12; IP-14).
 */
final class ReservationsController extends Controller
{
    public function __construct(private readonly Reservations $reservations) {}

    /** ROUTE-025. */
    public function index(Request $request): Response
    {
        return Inertia::render('INV/Reservations', $this->reservations->screen(self::actor($request), [
            'cabang' => self::optional($request->query('cabang')),
            'tab' => self::optional($request->query('tab')),
            'produk' => self::optional($request->query('produk')),
            'urut' => self::optional($request->query('urut')),
            'setelah' => self::optional($request->query('setelah')),
        ]));
    }

    /** ROUTE-184 "Reservasi". */
    public function store(Request $request, Reserve $reserve): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'proyek' => ['required', 'uuid'],
            'baris' => ['required', 'string', 'size:32'],
            'jumlah' => ['required', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
        ]);
        $line = $this->reservations->line($actor, self::text($input['proyek']), self::text($input['baris'])) ?? throw new NotFoundHttpException;
        $quantity = self::quantity(self::text($input['jumlah']), $line['scale']);
        $outcome = $reserve->run(self::text($input['command_id']), $actor, $line['company'], $line['project'], $line['line'], self::text($input['baris']), $quantity);

        return $this->answer($outcome, 'reserved');
    }

    /** ROUTE-185 "Kurangi Reservasi" (`jumlah`) or "Lepas" (`lepas`), with the reason. */
    public function reduce(Request $request, ReleaseReservation $release, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $reservation = $this->reservations->reservation($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'lepas' => ['nullable', 'boolean'],
            'jumlah' => ['required_unless:lepas,1,true', 'nullable', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $quantity = $request->boolean('lepas') ? null : self::quantity(self::text($input['jumlah']), $reservation['scale']);
        $outcome = $release->run(self::text($input['command_id']), $actor, $reservation['company'], $reservation['reservation'], $id, $quantity, trim(self::text($input['alasan'])));

        return $this->answer($outcome, $quantity === null ? 'released' : 'reduced');
    }

    /** A positive quantity at the product's scale, normalized; MSG-12 at the field otherwise (C-09). */
    private static function quantity(string $quantity, int $scale): string
    {
        $decimals = str_contains($quantity, '.') ? strlen(rtrim(explode('.', $quantity)[1], '0')) : 0;

        if (preg_match('/^0+(\.0+)?$/', $quantity) === 1) {
            throw ValidationException::withMessages(['jumlah' => self::say('reservations.validation.positive')]);
        }

        if ($decimals > $scale) {
            throw ValidationException::withMessages(['jumlah' => self::say('reservations.validation.scale')]);
        }

        return $quantity;
    }

    private function answer(Outcome $outcome, string $done): RedirectResponse
    {
        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("reservations.answer.{$done}")],
            Outcome::REJECTED => [null, match ($outcome->code) {
                'C-04' => self::say('reservations.refusal.available', ['tersedia' => self::figure($state['available'] ?? '0')]),
                'C-05' => isset($state['remainder'])
                    ? self::say('reservations.refusal.remainder', ['sisa' => self::figure($state['remainder'])])
                    : self::say('reservations.refusal.remaining', ['sisa' => self::figure($state['remaining'] ?? '0')]),
                'BR-RSV-01' => self::say(($state['reason'] ?? '') === 'mode' ? 'reservations.refusal.mode' : 'reservations.refusal.unconfirmed'),
                'AZ-08' => self::say('messages.msg_14'),
                default => self::say('messages.msg_04'),
            }],
            Outcome::CONFLICT => ['recorded', self::say('messages.msg_04')],
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text]);

        return redirect()->back(303, [], '/gudang/reservasi');
    }

    /**
     * @param  array<string, list<string>>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $rules = ['command_id' => ['required', 'uuid'], ...$rules];

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only(array_keys($rules)), $rules, [
            'required' => __('projects.validation.required'),
            'required_unless' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'size' => __('projects.validation.unknown'),
            'regex' => __('reservations.validation.quantity'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'boolean' => __('projects.validation.choice'),
        ])->validate();

        return $valid;
    }

    /** @param  array<string, string>  $replace */
    private static function say(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }

    private static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }

    /** A refusal's current figure without its trailing zeros (ST-06): "5.000" reads 5, "2.500" reads 2.5. */
    private static function figure(mixed $value): string
    {
        $text = self::text($value);

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function optional(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
