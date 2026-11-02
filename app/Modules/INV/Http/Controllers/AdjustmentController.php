<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CST\Queries\CostFigures;
use App\Modules\INV\Actions\AdjustStock;
use App\Modules\INV\Actions\ChangeCondition;
use App\Modules\INV\Actions\MoveLocation;
use App\Modules\INV\Actions\RecognizeLoss;
use App\Modules\INV\Actions\Reinspect;
use App\Modules\INV\Actions\ReverseLoss;
use App\Modules\INV\Data\StockPicks;
use App\Modules\INV\Queries\AdjustmentQueries;
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
 * PG-027's routes and "Pindah Lokasi" (ROUTE-027; ROUTE-190; ROUTE-194–ROUTE-196): a thin edge over the actions and the query classes. A refusal answers in place with the input kept (NV-04); success is PG-019 of the product with the answer (NAV-027), a move in place. The causal project, bearer, cost and allocation are derived, never taken from the payload (CS-04; DP-03).
 */
final class AdjustmentController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    private const ACTIONS = ['kondisi', 'periksa', 'penyesuaian'];

    public function __construct(
        private readonly AdjustmentQueries $queries,
        private readonly MovementHistory $history,
        private readonly StockViews $stock,
        private readonly CostFigures $costs,
    ) {}

    /** ROUTE-027: `?produk=` the form's product, otherwise the search that chooses it; `?aksi=` its command. */
    public function show(Request $request): Response
    {
        $actor = self::actor($request);
        $productId = self::query($request, 'produk');
        $product = $productId === null ? null : ($this->queries->product($productId) ?? throw new NotFoundHttpException);
        $search = self::query($request, 'cari');
        $find = self::query($request, 'temukan');
        $action = self::query($request, 'aksi');
        $internal = $product['id'] ?? null;

        return Inertia::render('INV/Adjustments', [
            'product' => $product === null ? null : array_diff_key($product, ['id' => 1]),
            'action' => in_array($action, self::ACTIONS, true) ? $action : null,
            'lot' => self::query($request, 'lot'),
            'search' => $search,
            'found' => $product !== null || $search === null ? [] : $this->stock->products($actor, $search, false, null)['rows'],
            'units' => fn (): ?array => $internal === null ? null : $this->queries->screen($actor, $internal, $find),
            'cases' => Inertia::defer(self::group(fn (): array => $internal === null ? ['find' => null, 'rows' => [], 'more' => false] : $this->queries->cases($actor, $internal, self::query($request, 'cari_kasus'))), 'details'),
            'proofs' => Inertia::defer(self::group(fn (): array => $this->queries->proofs($actor, self::query($request, 'cari_bukti'))), 'details'),
            'losses' => Inertia::defer(self::group(fn (): array => $internal === null ? [] : $this->lossesOf($actor, $internal)), 'details'),
            'today' => AdjustmentQueries::today(),
            'can' => $this->queries->can($actor),
        ]);
    }

    /** ROUTE-195: "Ubah Kondisi" and "Periksa Ulang". */
    public function condition(Request $request, ChangeCondition $change, Reinspect $reinspect): RedirectResponse
    {
        $actor = self::actor($request);
        $back = $request->input('aksi') === 'periksa';
        $input = self::validated($request, [...self::picks(), 'aksi' => ['required', Rule::in(['kondisi', 'periksa'])], 'bukti' => [$back ? 'nullable' : 'required', 'array', 'max:10']]);
        $product = $this->queries->product(self::text($input['produk'])) ?? throw ValidationException::withMessages(['produk' => self::say('projects.validation.unknown')]);
        $entry = $this->entry($actor, $product, $input, $request);
        $payload = array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]);
        $outcome = $back ? $reinspect->run(self::text($input['command_id']), $actor, $entry, $payload) : $change->run(self::text($input['command_id']), $actor, $entry, $payload);

        return $this->answer($outcome, $back ? 'reinspected' : 'changed', $product['public_id'], self::text($input['tanggal']), $back ? 'periksa' : 'kondisi');
    }

    /** ROUTE-194: "Catat Penyesuaian Stok", a decrease or a loss; never an increase (L-15). */
    public function adjust(Request $request, AdjustStock $adjust, RecognizeLoss $loss): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [...self::picks(), 'jenis' => ['required', Rule::in(['DISPOSAL', 'SHRINKAGE', 'OTHER'])], 'kasus' => ['nullable', 'string', 'size:32'], 'alasan_alokasi' => ['nullable', 'string', 'max:500'], 'bukti' => ['required', 'array', 'max:10']]);
        $product = $this->queries->product(self::text($input['produk'])) ?? throw ValidationException::withMessages(['produk' => self::say('projects.validation.unknown')]);
        $entry = $this->entry($actor, $product, $input, $request);
        $caseHandle = self::nullable($input['kasus'] ?? null);
        $case = $caseHandle === null ? null : ($this->queries->case($actor, $product['id'], $caseHandle) ?? throw ValidationException::withMessages(['kasus' => self::say('projects.validation.unknown')]));
        $kind = self::text($input['jenis']);
        $reason = self::nullable($input['alasan_alokasi'] ?? null);
        $payload = array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]);
        $outcome = $kind === 'OTHER' ? $loss->run(self::text($input['command_id']), $actor, $entry, $kind, $case, $reason, $payload) : $adjust->run(self::text($input['command_id']), $actor, $entry, $kind, $case, $reason, $payload);

        return $this->answer($outcome, 'adjusted', $product['public_id'], self::text($input['tanggal']), 'penyesuaian');
    }

    /** ROUTE-196: `{id}` the movement that recorded the loss. */
    public function reverse(Request $request, ReverseLoss $reverse, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $movement = $this->history->movement($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['alasan' => ['required', 'string', 'min:3', 'max:500'], 'tanggal' => ['required', 'date_format:Y-m-d'], 'tanggal_otomatis' => ['nullable', 'boolean']]);
        $outcome = $reverse->run(self::text($input['command_id']), $actor, $movement['company'], $movement['id'], trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($outcome, 'loss_reversed', null, self::text($input['tanggal']), null);
    }

    /** ROUTE-190: `{id}` the product; the move in place. */
    public function move(Request $request, MoveLocation $move, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $product = $this->queries->product($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'lot' => ['required', 'string', 'size:32'],
            'dari' => ['required', 'string', 'size:32'],
            'ke' => ['required', 'string', 'size:32'],
            'kondisi' => ['required', Rule::in(['USABLE', 'UNUSABLE'])],
            'jumlah' => ['required', self::QUANTITY],
            'seri' => ['nullable', 'array', 'max:500'],
            'seri.*' => ['required', 'string', 'size:32'],
            'kebijakan_seri' => ['required', 'boolean'],
            'kebijakan_skala' => ['required', 'integer', 'min:0', 'max:3'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $lot = $this->queries->lot($product['id'], self::text($input['lot'])) ?? throw ValidationException::withMessages(['lot' => $unknown]);
        $from = $this->queries->location(self::text($input['dari'])) ?? throw ValidationException::withMessages(['dari' => $unknown]);
        $to = $this->queries->location(self::text($input['ke'])) ?? throw ValidationException::withMessages(['ke' => $unknown]);
        $serials = [];

        foreach (is_array($input['seri'] ?? null) ? $input['seri'] : [] as $position => $handle) {
            $serials[] = $this->queries->serial($product['id'], self::text($handle)) ?? throw ValidationException::withMessages(["seri.{$position}" => $unknown]);
        }

        $outcome = $move->run(self::text($input['command_id']), $actor, $product['id'], $lot, $from, $to, self::text($input['kondisi']), self::text($input['jumlah']), $serials, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $request->boolean('kebijakan_seri'), (int) self::text($input['kebijakan_skala']), array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'moved', null, self::text($input['tanggal']), null);
    }

    /** @return list<array<string, mixed>> the recent losses, each bearer's amount only with `cost.view` of that bearer (J-INV-06; PJ-06; CS-08) */
    private function lossesOf(int $actor, int $productId): array
    {
        $rows = $this->queries->losses($actor, $productId);
        $amounts = $this->costs->lossesOf($actor, array_map(fn (array $row): int => is_int($row['internal']) ? $row['internal'] : 0, $rows));

        return array_map(fn (array $row): array => [...array_diff_key($row, ['internal' => 1]), 'amounts' => $amounts[is_int($row['internal']) ? $row['internal'] : 0] ?? []], $rows);
    }

    /** @return array<string, list<mixed>> what every command naming units sends */
    private static function picks(): array
    {
        return [
            'produk' => ['required', 'uuid'],
            'unit' => ['required', 'array', 'min:1', 'max:200'],
            'unit.*.lot' => ['required', 'string', 'size:32'],
            'unit.*.rak' => ['required', 'string', 'size:32'],
            'unit.*.kondisi' => ['required', Rule::in(['USABLE', 'UNUSABLE'])],
            'unit.*.jumlah' => ['required', self::QUANTITY],
            'unit.*.seri' => ['nullable', 'string', 'size:32'],
            'unit.*.dikonfirmasi' => ['nullable', 'boolean'],
            'tanpa_identitas' => ['nullable', 'boolean'],
            'potong' => ['nullable', 'array', 'max:50'],
            'potong.*.reservasi' => ['required', 'string', 'size:32'],
            'potong.*.jumlah' => ['required', self::QUANTITY],
            'bukti.*' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'duplikat' => ['nullable', 'array', 'max:20'],
            'duplikat.*' => ['required', 'uuid'],
            'alasan_duplikat' => ['nullable', 'string', 'max:500'],
            'kebijakan_seri' => ['required', 'boolean'],
            'kebijakan_skala' => ['required', 'integer', 'min:0', 'max:3'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The units, the cut and the evidence resolved through the scope resolver (CS-04; CS-05); an unidentified lot confirmed one by one (L-42).
     *
     * @param  array{id: int, public_id: string, name: string, sku: ?string, serialized: bool, scale: int, unit: string}  $product
     * @param  array<string, mixed>  $input
     */
    private function entry(int $actor, array $product, array $input, Request $request): StockPicks
    {
        $unknown = self::say('projects.validation.unknown');
        $unidentified = $request->boolean('tanpa_identitas');
        $picks = [];

        foreach (is_array($input['unit']) ? $input['unit'] : [] as $position => $unit) {
            $unit = is_array($unit) ? $unit : [];
            $lot = $this->queries->lot($product['id'], self::text($unit['lot'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$position}.lot" => $unknown]);
            $rack = $this->queries->location(self::text($unit['rak'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$position}.rak" => $unknown]);
            $handle = self::nullable($unit['seri'] ?? null);
            $serial = $handle === null ? null : ($this->queries->serial($product['id'], $handle) ?? throw ValidationException::withMessages(["unit.{$position}.seri" => $unknown]));

            if ($unidentified && filter_var($unit['dikonfirmasi'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
                throw ValidationException::withMessages(["unit.{$position}.dikonfirmasi" => self::say('adjustments.validation.confirm_lot')]);
            }

            $picks[] = ['lot' => $lot, 'location' => $rack, 'condition' => self::text($unit['kondisi'] ?? ''), 'qty' => self::text($unit['jumlah'] ?? ''), 'serial' => $serial];
        }

        $cuts = [];

        foreach (is_array($input['potong'] ?? null) ? $input['potong'] : [] as $position => $cut) {
            $cut = is_array($cut) ? $cut : [];
            $reservation = $this->queries->reservation($product['id'], self::text($cut['reservasi'] ?? '')) ?? throw ValidationException::withMessages(["potong.{$position}.reservasi" => $unknown]);
            $cuts[$reservation] = self::text($cut['jumlah'] ?? '');
        }

        $evidence = [];

        foreach (self::strings($input['bukti'] ?? []) as $position => $publicId) {
            $evidence[] = $this->queries->evidence($actor, $publicId) ?? throw ValidationException::withMessages(["bukti.{$position}" => $unknown]);
        }

        return new StockPicks($product['id'], $picks, $cuts, trim(self::text($input['alasan'])), $evidence, $unidentified, self::strings($input['duplikat'] ?? []), self::nullable($input['alasan_duplikat'] ?? null), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $request->boolean('kebijakan_seri'), (int) self::text($input['kebijakan_skala']));
    }

    /** The answer: a committed command on PG-019 of the product (NAV-027), a refusal in place, the input kept; a future date at its field (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, ?string $product, string $sent, ?string $action): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $matches = $outcome->code === 'L-49' ? $this->history->labels(self::strings($state['matches'] ?? [])) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("adjustments.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome, $matches)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-09' => ['stale', self::say('receiving.refusal.policy')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => [...array_intersect_key($state, ['reason' => 1, 'shortfall' => 1, 'available' => 1]), ...($matches === null ? [] : ['matches' => $matches])]]);

        if ($outcome->kind === Outcome::COMMITTED && $product !== null) {
            return redirect()->to("/gudang/stok/{$product}", 303);
        }

        return redirect()->back(303, [], $product === null ? '/gudang/penyesuaian' : "/gudang/penyesuaian?produk={$product}".($action === null ? '' : "&aksi={$action}"));
    }

    /** @param  list<array{id: string, label: string, href: null}>|null  $matches */
    private static function refusal(Outcome $outcome, ?array $matches): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            // A replay names the refusal, not what stood at the rack then (CI-05).
            'C-03' => $reason === 'disposal_usable' ? self::say('adjustments.refusal.disposal_usable') : (isset($state['available']) ? self::say('stock.refusal.moved_on', ['sisa' => self::text($state['available'])]) : self::say('stock.refusal.moved_on_plain')),
            'C-02' => self::say('dispatch.refusal.serial'),
            'C-24' => self::say('adjustments.refusal.lost_once'),
            'C-07' => self::say('adjustments.refusal.same_rack'),
            'C-09' => self::say('receiving.refusal.quantity'),
            'C-14' => $reason === 'case_required' ? self::say('adjustments.refusal.case_required') : self::say('projects.validation.unknown'),
            'C-05', 'C-06' => self::say('receiving.refusal.reservations'),
            'C-55' => self::say('stock.refusal.reversed'),
            'L-42' => self::say('adjustments.refusal.several_companies'),
            'L-49' => self::say('messages.msg_15', ['daftar_catatan_yang_cocok' => implode('; ', array_column($matches ?? [], 'label'))]),
            'AZ-08' => $reason === 'bearer' ? self::say('messages.msg_14') : self::say('receiving.refusal.reservations'),
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
