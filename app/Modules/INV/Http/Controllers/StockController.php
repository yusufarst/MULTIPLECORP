<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CST\Queries\CostFigures;
use App\Modules\INV\Actions\ReverseMovement;
use App\Modules\INV\Queries\AdjustmentQueries;
use App\Modules\INV\Queries\MovementHistory;
use App\Modules\INV\Queries\ReceivingQueries;
use App\Modules\INV\Queries\StockViews;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * ROUTE-018, ROUTE-019, ROUTE-020 and ROUTE-212 (TASK-040): the pooled stock views, each read in its query class — physical in INV's, cost in CST's —, and "Balikkan" on PG-020.
 */
final class StockController extends Controller
{
    public function __construct(
        private readonly StockViews $stock,
        private readonly MovementHistory $history,
        private readonly CostFigures $costs,
        private readonly ReceivingQueries $receiving,
        private readonly AdjustmentQueries $adjustments,
    ) {}

    /** ROUTE-018: per product or per rack and condition, the stock value of a holder's own companies deferred (PJ-22). */
    public function index(Request $request): Response
    {
        $actor = self::actor($request);
        $view = self::query($request, 'tampil') === 'rak' ? 'rak' : 'produk';
        $after = self::query($request, 'setelah');
        $search = self::query($request, 'cari');
        $below = self::query($request, 'saring') === StockViews::BELOW_MINIMUM;
        $condition = in_array(self::query($request, 'kondisi'), StockViews::CONDITIONS, true) ? self::query($request, 'kondisi') : null;

        return Inertia::render('INV/Stock', [
            'view' => $view,
            'filters' => ['saring' => $below ? StockViews::BELOW_MINIMUM : null, 'kondisi' => $condition],
            'list' => fn (): array => $view === 'rak' ? $this->stock->places($actor, $search, $condition, $after) : $this->stock->products($actor, $search, $below, $after),
            'values' => Inertia::defer(self::group(fn (): array => $this->costs->stockValues($actor))),
            'can' => fn (): array => ['purchase' => $this->history->mayPurchase($actor), 'move' => $this->adjustments->can($actor)['move']],
        ]);
    }

    /** ROUTE-019: the lots and serials deferred (QB-05), cost and basis only per source company with `cost.view` (PJ-02). */
    public function show(Request $request, string $id): Response
    {
        $actor = self::actor($request);
        $screen = $this->stock->product($id) ?? throw new NotFoundHttpException;
        $internal = $screen['internal'];

        return Inertia::render('INV/ProductStock', [
            'product' => $screen['product'],
            'places' => fn (): array => $this->stock->placesOf($internal),
            'reservations' => fn (): array => $this->stock->reservationPage($actor, $internal, self::query($request, 'reservasi_setelah')),
            'group' => Inertia::defer(self::group(fn (): array => $this->stock->group($actor, $internal, self::query($request, 'lot_setelah'), self::query($request, 'seri_setelah')))),
            'movements' => Inertia::defer(self::group(fn (): array => $this->history->recent($actor, $internal)), 'movements'),
            'costs' => Inertia::defer(self::group(fn (): array => $this->costs->lotsOfProduct($actor, $internal)), 'costs'),
            'can' => fn (): array => ['purchase' => $this->history->mayPurchase($actor), ...$this->adjustments->can($actor)],
            'moving' => Inertia::optional(fn (): array => ['units' => $this->adjustments->screen($actor, $internal), 'racks' => $this->adjustments->racks()]),
            'today' => BusinessDate::today(),
            'scale' => $screen['scale'],
        ]);
    }

    /** ROUTE-020; the reversal's figures on request, for its dialog (IP-20). */
    public function historyPage(Request $request): Response
    {
        $actor = self::actor($request);
        $filters = MovementHistory::filters(['produk' => self::query($request, 'produk'), 'jenis' => self::query($request, 'jenis'), 'dari' => self::query($request, 'dari'), 'sampai' => self::query($request, 'sampai'), 'urut' => self::query($request, 'urut'), 'gerakan' => self::query($request, 'gerakan')]);
        $movement = self::query($request, 'gerak');
        $receipt = self::query($request, 'terima');

        return Inertia::render('INV/StockHistory', [
            'filters' => $filters,
            'list' => fn (): array => $this->history->page($actor, $filters, self::query($request, 'setelah')),
            'types' => MovementHistory::TYPES,
            'today' => BusinessDate::today(),
            'cut' => Inertia::optional(fn (): ?array => $movement === null ? null : $this->history->cutOf($actor, $movement)),
            'reversal' => Inertia::optional(fn (): ?array => $receipt === null ? null : $this->receiving->reversalOf($actor, $receipt)),
        ]);
    }

    /** ROUTE-212. */
    public function reverse(Request $request, ReverseMovement $reverse, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $movement = $this->history->movement($actor, $id) ?? throw new NotFoundHttpException;

        /** @var array<string, mixed> $input */
        $input = Validator::make($request->only(['command_id', 'alasan', 'tanggal', 'tanggal_otomatis', 'potong']), [
            'command_id' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'potong' => ['nullable', 'array', 'max:50'],
            'potong.*.reservasi' => ['required', 'string', 'size:32'],
            'potong.*.jumlah' => ['required', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
        ], [
            'required' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'boolean' => __('projects.validation.choice'),
            'array' => __('projects.validation.choice'),
        ])->validate();
        $unknown = self::say('projects.validation.unknown');
        $cuts = [];

        foreach (is_array($input['potong'] ?? null) ? $input['potong'] : [] as $position => $cut) {
            $cut = is_array($cut) ? $cut : [];
            $reservation = $this->history->reservation($movement['id'], self::text($cut['reservasi'] ?? '')) ?? throw ValidationException::withMessages(["potong.{$position}.reservasi" => $unknown]);
            $cuts[$reservation] = self::text($cut['jumlah'] ?? '');
        }

        $outcome = $reverse->run(self::text($input['command_id']), $actor, $movement['company'], $movement['id'], $cuts, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($outcome, self::text($input['tanggal']));
    }

    private function answer(Outcome $outcome, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say('stock.answer.reversed')],
            Outcome::REJECTED => [null, match ($outcome->code) {
                'C-55' => self::say('stock.refusal.reversed'),
                // A replay names the refusal, not what stood at the rack then (CI-05).
                'C-03' => isset($state['available']) ? self::say('stock.refusal.moved_on', ['sisa' => self::text($state['available'])]) : self::say('stock.refusal.moved_on_plain'),
                'C-02' => self::say('receiving.refusal.serial_reversal'),
                'OD-15' => self::say('stock.refusal.owner_only'),
                'AZ-08' => ($state['reason'] ?? null) === 'bearer' ? self::say('stock.refusal.other_company') : self::say('receiving.refusal.reservations'),
                'C-05', 'C-06' => self::say('receiving.refusal.reservations'),
                default => self::say('messages.msg_04'),
            }],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'shortfall' => 1, 'available' => 1])]);

        // In place, the list's filters kept (IP-18; NV-03).
        return redirect()->back(303, [], '/gudang/riwayat-stok');
    }

    private static function group(Closure $read): Closure
    {
        return function () use ($read): mixed {
            try {
                return $read();
            } catch (Throwable $error) {
                Log::error('A deferred group failed.', ['exception' => $error::class]);

                return ['failed' => true];
            }
        };
    }

    /** @param  array<string, string>  $replace */
    private static function say(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new AccessDeniedHttpException;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
