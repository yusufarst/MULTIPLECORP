<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\INV\Actions\ReturnToSupplier;
use App\Modules\INV\Data\StockPicks;
use App\Modules\INV\Queries\AdjustmentQueries;
use App\Modules\INV\Queries\SupplierReturnQueries;
use App\Modules\PUR\Actions\RecordReturnOutcome;
use App\Support\Command\Outcome;
use App\Support\Localization\Formats;
use App\Support\Money;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * PG-028's routes (ROUTE-028; ROUTE-176; ROUTE-177; NAV-028): a thin edge over the actions and the query class. A return answers on PG-028 with its case, an outcome in place; a refusal keeps the input (NV-04).
 */
final class SupplierReturnController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    private const MONEY = 'regex:/^\d{1,16}(\.\d{1,2})?$/';

    private const PAGE = '/gudang/retur-pemasok';

    public function __construct(
        private readonly SupplierReturnQueries $queries,
        private readonly AdjustmentQueries $adjustments,
    ) {}

    /** ROUTE-028: `?kasus=` one case, `?pembelian=` the form a purchase opens with `?lot=` its chosen lot, `?produk=` with `?lot=` the lot's own purchase; the cases otherwise. */
    public function show(Request $request): Response|RedirectResponse
    {
        $actor = self::actor($request);
        $productId = self::query($request, 'produk');

        if ($productId !== null && self::query($request, 'pembelian') === null) {
            $handle = self::query($request, 'lot') ?? '';
            $from = $this->queries->purchaseOfLot($actor, $productId, $handle) ?? throw new NotFoundHttpException;

            return redirect()->to(self::PAGE.'?'.http_build_query(['pembelian' => $from, 'lot' => $handle]));
        }

        $caseId = self::query($request, 'kasus');
        $case = $caseId === null ? null : ($this->queries->detail($actor, $caseId) ?? throw new NotFoundHttpException);
        $purchaseId = self::query($request, 'pembelian');
        $purchase = $purchaseId === null ? null : ($this->queries->purchase($actor, $purchaseId) ?? throw new NotFoundHttpException);
        $found = $purchaseId === null ? null : $this->queries->purchaseOf($actor, $purchaseId);
        $lotHandle = self::query($request, 'lot');
        $lot = $found === null || $lotHandle === null ? null : $this->queries->lotOf($found['id'], $lotHandle);

        return Inertia::render('INV/SupplierReturns', [
            'list' => fn (): array => $this->queries->list($actor, self::query($request, 'setelah')),
            'case' => $case,
            'purchase' => $purchase,
            'lot' => $lot === null ? null : $lotHandle,
            'units' => $lot === null ? null : $this->queries->lotUnits($actor, $lot),
            'proofs' => $caseId === null ? null : Inertia::defer(self::group(fn (): array => $this->queries->proofs($actor, $caseId, self::query($request, 'cari_bukti'))), 'details'),
            'today' => AdjustmentQueries::today(),
            'can' => $this->queries->can($actor),
        ]);
    }

    /** ROUTE-176: "Retur ke Pemasok" — units of one lot of the purchase, the reason, the cut; never a cost, a bearer or evidence from the payload (CS-04; C-58: a return cites no pool evidence). */
    public function record(Request $request, ReturnToSupplier $return, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $purchase = $this->queries->purchaseOf($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'lot' => ['required', 'string', 'size:32'],
            'unit' => ['required', 'array', 'min:1', 'max:200'],
            'unit.*.rak' => ['required', 'string', 'size:32'],
            'unit.*.kondisi' => ['required', Rule::in(['USABLE', 'UNUSABLE'])],
            'unit.*.jumlah' => ['required', self::QUANTITY],
            'unit.*.seri' => ['nullable', 'string', 'size:32'],
            'potong' => ['nullable', 'array', 'max:50'],
            'potong.*.reservasi' => ['required', 'string', 'size:32'],
            'potong.*.jumlah' => ['required', self::QUANTITY],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'kebijakan_seri' => ['required', 'boolean'],
            'kebijakan_skala' => ['required', 'integer', 'min:0', 'max:3'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $lot = $this->queries->lotOf($purchase['id'], self::text($input['lot'])) ?? throw ValidationException::withMessages(['lot' => $unknown]);
        $product = $this->queries->productOf($lot);
        $picks = [];

        foreach (is_array($input['unit']) ? $input['unit'] : [] as $position => $unit) {
            $unit = is_array($unit) ? $unit : [];
            $rack = $this->adjustments->location(self::text($unit['rak'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$position}.rak" => $unknown]);
            $handle = self::nullable($unit['seri'] ?? null);
            $serial = $handle === null ? null : ($this->adjustments->serial($product, $handle) ?? throw ValidationException::withMessages(["unit.{$position}.seri" => $unknown]));
            $picks[] = ['lot' => $lot, 'location' => $rack, 'condition' => self::text($unit['kondisi'] ?? ''), 'qty' => self::text($unit['jumlah'] ?? ''), 'serial' => $serial];
        }

        $cuts = [];

        foreach (is_array($input['potong'] ?? null) ? $input['potong'] : [] as $position => $cut) {
            $cut = is_array($cut) ? $cut : [];
            $reservation = $this->adjustments->reservation($product, self::text($cut['reservasi'] ?? '')) ?? throw ValidationException::withMessages(["potong.{$position}.reservasi" => $unknown]);
            $cuts[$reservation] = self::text($cut['jumlah'] ?? '');
        }

        $entry = new StockPicks($product, $picks, $cuts, trim(self::text($input['alasan'])), [], false, [], null, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $request->boolean('kebijakan_seri'), (int) self::text($input['kebijakan_skala']));
        $outcome = $return->run(self::text($input['command_id']), $actor, $purchase['company'], $purchase['id'], $entry, array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));
        $case = $outcome->kind === Outcome::COMMITTED ? $this->queries->casePublicId($outcome->resultId ?? 0) : null;

        return $this->answer($outcome, 'returned', $case, self::PAGE.'?'.http_build_query(['pembelian' => $purchase['public_id'], 'lot' => self::text($input['lot'])]));
    }

    /** ROUTE-177: the outcome on the case, or its correction (`koreksi`), in place (WF-PUR-03). */
    public function outcome(Request $request, RecordReturnOutcome $record, string $id, string $ret): RedirectResponse
    {
        $actor = self::actor($request);
        $case = $this->queries->caseOf($actor, $ret);

        if ($case === null || $case['purchase'] !== strtolower($id)) {
            throw new NotFoundHttpException;
        }

        $input = self::validated($request, [
            'hasil' => ['required', Rule::in(RecordReturnOutcome::KINDS)],
            'koreksi' => ['nullable', 'boolean'],
            'jumlah' => ['nullable', self::QUANTITY],
            'nilai' => ['nullable', self::MONEY],
            'bukti' => ['nullable', 'uuid'],
            'alasan_alokasi' => ['nullable', 'string', 'max:500'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $kind = self::text($input['hasil']);
        $qty = self::nullable($input['jumlah'] ?? null);
        $value = self::nullable($input['nilai'] ?? null);
        $correction = $request->boolean('koreksi');

        if ($kind === 'REPLACEMENT' && $qty === null) {
            throw ValidationException::withMessages(['jumlah' => self::say('projects.validation.required')]);
        }

        if (in_array($kind, ['CREDIT', 'UNRECOVERED'], true) && $value === null) {
            throw ValidationException::withMessages(['nilai' => self::say('projects.validation.required')]);
        }

        $proof = self::nullable($input['bukti'] ?? null);
        $evidence = $proof === null ? null : ($this->queries->evidenceOf($case['company'], $proof) ?? throw ValidationException::withMessages(['bukti' => self::say('projects.validation.unknown')]));

        if ($kind === 'CREDIT' && ! $correction && $evidence === null) {
            throw ValidationException::withMessages(['bukti' => self::say('projects.validation.required')]);
        }

        $outcome = $record->run(self::text($input['command_id']), $actor, $case['company'], $case['id'], $kind, $correction, $qty, $value === null ? null : Money::of($value), $evidence, self::nullable($input['alasan_alokasi'] ?? null), trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'outcome', strtolower($ret), self::PAGE.'?'.http_build_query(['kasus' => strtolower($ret)]));
    }

    /** The answer: a committed return or outcome on its case (NAV-028), a refusal in place, the input kept; a future date at its field (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, ?string $case, string $back): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("returns.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-09' => ['stale', self::say('receiving.refusal.policy')],
                'ST-02' => ['stale', self::say('returns.refusal.closed')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'shortfall' => 1, 'available' => 1, 'remainder' => 1, 'open' => 1])]);

        if ($outcome->kind === Outcome::COMMITTED && $case !== null) {
            return redirect()->to(self::PAGE.'?'.http_build_query(['kasus' => $case]), 303);
        }

        return redirect()->to($back, 303);
    }

    private static function refusal(Outcome $outcome): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            // A replay names the refusal, not what stood at the rack then (CI-05).
            'C-03' => isset($state['available']) ? self::say('stock.refusal.moved_on', ['sisa' => self::text($state['available'])]) : self::say('stock.refusal.moved_on_plain'),
            'C-02' => self::say('dispatch.refusal.serial'),
            'C-07' => $reason === 'mixed_cause' ? self::say('returns.refusal.mixed_cause') : self::say('messages.msg_04'),
            'C-09' => self::say('receiving.refusal.quantity'),
            'C-14' => match ($reason) {
                'one_lot' => self::say('returns.refusal.one_lot'),
                'evidence' => self::say('returns.refusal.evidence'),
                default => self::say('projects.validation.unknown'),
            },
            'C-05', 'C-06' => self::say('receiving.refusal.reservations'),
            'C-29' => match ($reason) {
                'nothing_open' => self::say('returns.refusal.nothing_open'),
                'awaited' => self::say('returns.refusal.awaited'),
                'above_awaited' => self::say('returns.refusal.above_awaited', ['sisa' => self::text($state['remainder'] ?? '0')]),
                default => self::say('returns.refusal.replacement_room', ['sisa' => self::text($state['remainder'] ?? '0')]),
            },
            'C-46', 'C-24' => self::say('returns.refusal.above_open', ['sisa' => Formats::money(self::text($state['open'] ?? '0'))]),
            'AZ-08' => $reason === 'bearer' ? self::say('messages.msg_14_allocation') : self::say('messages.msg_14'),
            default => self::say('messages.msg_04'),
        };
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

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $rules = ['command_id' => ['required', 'uuid'], ...$rules];
        $fields = array_values(array_unique(array_map(fn (string $field): string => explode('.', $field)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'boolean' => __('projects.validation.choice'),
            'integer' => __('projects.validation.number'),
            'array' => __('projects.validation.choice'),
        ])->validate();

        return $valid;
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

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
