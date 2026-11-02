<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\INV\Actions\ApplyCountVariance;
use App\Modules\INV\Actions\CancelCount;
use App\Modules\INV\Actions\OpenCount;
use App\Modules\INV\Actions\RecordCount;
use App\Modules\INV\Data\CountEntry;
use App\Modules\INV\Queries\AdjustmentQueries;
use App\Modules\INV\Queries\CountQueries;
use App\Modules\INV\Queries\MovementHistory;
use App\Modules\INV\Queries\StockViews;
use App\Support\Command\Outcome;
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
 * PG-026's routes (ROUTE-026; ROUTE-191–ROUTE-193; ROUTE-215): a thin edge over the actions and the query classes. Every answer is in place (NAV-026), a stale count's with "Hitung Ulang" (MSG-30), a refusal with the input kept (NV-04).
 */
final class CountController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    public function __construct(
        private readonly CountQueries $queries,
        private readonly AdjustmentQueries $adjustments,
        private readonly MovementHistory $history,
        private readonly StockViews $stock,
    ) {}

    /** ROUTE-026: `?hitungan=` one count, `?produk=` the opening's product; the list otherwise, by state. */
    public function show(Request $request): Response
    {
        $actor = self::actor($request);
        $publicId = self::query($request, 'hitungan');
        $count = $publicId === null ? null : ($this->queries->count($actor, $publicId, self::query($request, 'cari_lot')) ?? throw new NotFoundHttpException);
        $productId = self::query($request, 'produk');
        $product = $productId === null ? null : $this->adjustments->product($productId);
        $can = $this->queries->can($actor);
        $search = self::query($request, 'cari');

        return Inertia::render('INV/StockCounts', [
            'list' => fn (): array => $this->queries->list($actor, self::query($request, 'status'), self::query($request, 'setelah')),
            'count' => $count,
            'product' => $product === null ? null : array_diff_key($product, ['id' => 1]),
            'search' => $search,
            'found' => $product !== null || $search === null ? [] : $this->stock->products($actor, $search, false, null)['rows'],
            'racks' => fn (): array => $this->queries->racks(),
            'proofs' => Inertia::defer(self::group(fn (): array => $this->adjustments->proofs($actor, self::query($request, 'cari_bukti'))), 'details'),
            'reservations' => fn (): array => $count === null || ! $can['reserve'] || ! is_array($count['product'] ?? null) ? [] : $this->reservationsOf($actor, $count),
            'available' => fn (): string => $count === null ? '0' : $this->availableOf($count),
            'today' => AdjustmentQueries::today(),
            'can' => $can,
        ]);
    }

    /** ROUTE-191: "Mulai Hitungan", "Hitung Ulang"; a registration count names no rack (`DIR-101`). */
    public function open(Request $request, OpenCount $open): RedirectResponse
    {
        $actor = self::actor($request);
        $registering = $request->boolean('registrasi');
        $input = self::validated($request, [
            'produk' => ['required', 'uuid'],
            'rak' => [$registering ? 'nullable' : 'required', 'string', 'size:32'],
            'kondisi' => [$registering ? 'nullable' : 'required', Rule::in(['USABLE', 'UNUSABLE'])],
            'registrasi' => ['nullable', 'boolean'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $product = $this->adjustments->product(self::text($input['produk'])) ?? throw ValidationException::withMessages(['produk' => $unknown]);
        $rack = $registering ? null : ($this->adjustments->location(self::text($input['rak'] ?? '')) ?? throw ValidationException::withMessages(['rak' => $unknown]));
        $outcome = $open->run(self::text($input['command_id']), $actor, $product['id'], $rack, $registering ? null : self::text($input['kondisi'] ?? ''), array_diff_key($input, ['command_id' => 1]));
        $opened = $outcome->kind === Outcome::COMMITTED ? $this->queries->publicId($outcome->resultId ?? 0) : null;

        return $this->answer($outcome, 'opened', null, $opened);
    }

    /** ROUTE-192: one line, its answer in place (IP-19). */
    public function line(Request $request, RecordCount $record, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $count = $this->queries->internal($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'rak' => ['required', 'string', 'size:32'],
            'kondisi' => ['required', Rule::in(['USABLE', 'UNUSABLE'])],
            'jumlah' => ['nullable', self::QUANTITY],
            'seri' => ['nullable', 'string', 'max:120'],
            'lot' => ['nullable', 'string', 'size:32'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $rack = $this->adjustments->location(self::text($input['rak'])) ?? throw ValidationException::withMessages(['rak' => $unknown]);
        $serial = self::nullable($input['seri'] ?? null);
        $handle = self::nullable($input['lot'] ?? null);
        $lot = $handle === null ? null : ($this->adjustments->lot($count['product'], $handle) ?? throw ValidationException::withMessages(['lot' => $unknown]));
        $qty = self::text($input['jumlah'] ?? '');

        if ($serial === null && $qty === '') {
            throw ValidationException::withMessages(['jumlah' => self::say('projects.validation.required')]);
        }

        $outcome = $record->run(self::text($input['command_id']), $actor, $count['id'], $count['product'], $rack, self::text($input['kondisi']), $qty === '' ? '1' : $qty, $serial, $lot, array_diff_key($input, ['command_id' => 1]));

        return $this->answer($outcome, 'recorded', $count['public'], null);
    }

    /** ROUTE-193: "Terapkan Selisih". The bearer and the cost are derived, never taken from the payload (CS-04). */
    public function apply(Request $request, ApplyCountVariance $apply, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $count = $this->queries->internal($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'unit' => ['nullable', 'array', 'max:200'],
            'unit.*.lot' => ['required', 'string', 'size:32'],
            'unit.*.jumlah' => ['required', self::QUANTITY],
            'unit.*.dikonfirmasi' => ['nullable', 'boolean'],
            'tanpa_identitas' => ['nullable', 'boolean'],
            'tertunda' => ['nullable', self::QUANTITY],
            'konversi' => ['nullable', 'array', 'max:20'],
            'konversi.*.kasus' => ['required', 'string', 'size:32'],
            'konversi.*.jumlah' => ['required', self::QUANTITY],
            'pulihkan' => ['nullable', 'array', 'max:50'],
            'pulihkan.*.konsumsi' => ['required', 'string', 'size:32'],
            'pulihkan.*.jumlah' => ['required', self::QUANTITY],
            'potong' => ['nullable', 'array', 'max:50'],
            'potong.*.reservasi' => ['required', 'string', 'size:32'],
            'potong.*.jumlah' => ['required', self::QUANTITY],
            'bukti' => ['nullable', 'array', 'max:10'],
            'bukti.*' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'alasan_alokasi' => ['nullable', 'string', 'max:500'],
            'duplikat' => ['nullable', 'array', 'max:20'],
            'duplikat.*' => ['required', 'uuid'],
            'alasan_duplikat' => ['nullable', 'string', 'max:500'],
            'kebijakan_seri' => ['required', 'boolean'],
            'kebijakan_skala' => ['required', 'integer', 'min:0', 'max:3'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $entry = $this->entry($actor, $count, $input, $request);
        $outcome = $apply->run(self::text($input['command_id']), $actor, $entry, array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'applied', $count['public'], null);
    }

    /** ROUTE-215: "Batalkan Hitungan". */
    public function cancel(Request $request, CancelCount $cancel, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $count = $this->queries->internal($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['alasan' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->answer($cancel->run(self::text($input['command_id']), $actor, $count['id'], $count['product'], trim(self::text($input['alasan'])), array_diff_key($input, ['command_id' => 1])), 'cancelled', $count['public'], null);
    }

    /**
     * @param  array{id: int, public: string, product: int, location: ?int, condition: ?string, state: string}  $count
     * @param  array<string, mixed>  $input
     */
    private function entry(int $actor, array $count, array $input, Request $request): CountEntry
    {
        $unknown = self::say('projects.validation.unknown');
        $unidentified = $request->boolean('tanpa_identitas');
        $picks = [];

        foreach (is_array($input['unit'] ?? null) ? $input['unit'] : [] as $position => $unit) {
            $unit = is_array($unit) ? $unit : [];

            if ($unidentified && filter_var($unit['dikonfirmasi'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
                throw ValidationException::withMessages(["unit.{$position}.dikonfirmasi" => self::say('adjustments.validation.confirm_lot')]);
            }

            $picks[] = ['lot' => $this->adjustments->lot($count['product'], self::text($unit['lot'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$position}.lot" => $unknown]), 'location' => $count['location'] ?? 0, 'condition' => $count['condition'] ?? '', 'qty' => self::text($unit['jumlah'] ?? ''), 'serial' => null];
        }

        $conversions = [];

        foreach (is_array($input['konversi'] ?? null) ? $input['konversi'] : [] as $position => $row) {
            $row = is_array($row) ? $row : [];
            $conversions[] = ['case' => $this->adjustments->pendingChange($count['product'], self::text($row['kasus'] ?? '')) ?? throw ValidationException::withMessages(["konversi.{$position}.kasus" => $unknown]), 'qty' => self::text($row['jumlah'] ?? '')];
        }

        $restored = [];

        foreach (is_array($input['pulihkan'] ?? null) ? $input['pulihkan'] : [] as $position => $row) {
            $row = is_array($row) ? $row : [];
            $restored[] = ['consumption' => $this->queries->consumption($actor, $count['product'], self::text($row['konsumsi'] ?? '')) ?? throw ValidationException::withMessages(["pulihkan.{$position}.konsumsi" => $unknown]), 'qty' => self::text($row['jumlah'] ?? '')];
        }

        $cuts = [];

        foreach (is_array($input['potong'] ?? null) ? $input['potong'] : [] as $position => $cut) {
            $cut = is_array($cut) ? $cut : [];
            $cuts[$this->adjustments->reservation($count['product'], self::text($cut['reservasi'] ?? '')) ?? throw ValidationException::withMessages(["potong.{$position}.reservasi" => $unknown])] = self::text($cut['jumlah'] ?? '');
        }

        $evidence = [];

        foreach (self::strings($input['bukti'] ?? []) as $position => $publicId) {
            $evidence[] = $this->adjustments->evidence($actor, $publicId) ?? throw ValidationException::withMessages(["bukti.{$position}" => $unknown]);
        }

        return new CountEntry($count['id'], $picks, $unidentified, self::text($input['tertunda'] ?? ''), $conversions, $restored, $cuts, trim(self::text($input['alasan'])), $evidence, self::nullable($input['alasan_alokasi'] ?? null), self::strings($input['duplikat'] ?? []), self::nullable($input['alasan_duplikat'] ?? null), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $request->boolean('kebijakan_seri'), (int) self::text($input['kebijakan_skala']));
    }

    /** @param  array<string, mixed>  $count
     * @return list<array<string, mixed>>
     */
    private function reservationsOf(int $actor, array $count): array
    {
        $internal = $this->queries->internal(self::text($count['id']));

        return $internal === null ? [] : $this->adjustments->screen($actor, $internal['product'])['reservations'];
    }

    /** @param  array<string, mixed>  $count */
    private function availableOf(array $count): string
    {
        $internal = $this->queries->internal(self::text($count['id']));

        return $internal === null ? '0' : $this->queries->available($internal['product']);
    }

    /** The answer in place (NAV-026): a stale count's MSG-30, a refusal with the input kept; a future date at its field (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, ?string $count, ?string $opened): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $matches = $outcome->code === 'L-49' ? $this->history->labels(self::strings($state['matches'] ?? [])) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("counts.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome, $matches)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-03' => ['stale', self::say('messages.msg_30')],
                'ST-09' => ['stale', self::say('receiving.refusal.policy')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => [...array_intersect_key($state, ['reason' => 1, 'shortfall' => 1, 'available' => 1, 'needed' => 1]), ...($matches === null ? [] : ['matches' => $matches])]]);

        return redirect()->to('/gudang/stok-opname'.(($opened ?? $count) === null ? '' : '?hitungan='.($opened ?? $count)), 303);
    }

    /** @param  list<array{id: string, label: string, href: null}>|null  $matches */
    private static function refusal(Outcome $outcome, ?array $matches): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-33' => $reason === 'no_lines' ? self::say('counts.refusal.no_lines') : self::say('counts.refusal.not_open'),
            'C-02' => match ($reason) {
                'counted' => self::say('messages.msg_36', ['seri' => self::text($state['serial'] ?? '')]),
                'in_stock' => self::say('counts.refusal.serial_in_stock', ['seri' => self::text($state['serial'] ?? '')]),
                default => self::say('dispatch.refusal.serial'),
            },
            'C-03' => $reason === 'registration_difference' ? self::say('counts.refusal.registration_difference') : (isset($state['available']) ? self::say('stock.refusal.moved_on', ['sisa' => self::text($state['available'])]) : self::say('stock.refusal.moved_on_plain')),
            'C-07' => $reason === 'split' ? self::say('counts.refusal.split', ['jumlah' => self::text($state['needed'] ?? '')]) : ($reason === 'registration_pending' ? self::say('counts.refusal.registration_pending') : self::say('adjustments.refusal.same_rack')),
            'C-09' => self::say('receiving.refusal.quantity'),
            'C-14' => self::say('projects.validation.unknown'),
            'C-24' => self::say('adjustments.refusal.lost_once'),
            'C-27' => self::say('counts.refusal.above_variance'),
            'C-34' => self::say('counts.refusal.one_finding'),
            'C-35', 'C-36' => self::say('adjustments.refusal.pending_limit'),
            'C-05', 'C-06' => self::say('receiving.refusal.reservations'),
            'L-15' => self::say('counts.refusal.not_identified'),
            'L-35' => self::say('counts.refusal.already_serialized'),
            'L-42' => match ($reason) {
                'one_company' => self::say('adjustments.refusal.one_company'),
                'named_evidence' => self::say('counts.refusal.named_evidence'),
                default => self::say('adjustments.refusal.several_companies'),
            },
            'L-49' => self::say('messages.msg_15', ['daftar_catatan_yang_cocok' => implode('; ', array_column($matches ?? [], 'label'))]),
            'AZ-08' => $reason === 'bearer' ? self::say('messages.msg_14') : ($reason === 'products_edit' ? self::say('counts.refusal.products_edit') : self::say('receiving.refusal.reservations')),
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

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return array_values(array_filter(is_array($values) ? $values : [], is_string(...)));
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
