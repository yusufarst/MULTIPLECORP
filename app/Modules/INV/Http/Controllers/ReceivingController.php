<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Actions\ReceiveGoods;
use App\Modules\INV\Actions\ReverseReceipt;
use App\Modules\INV\Data\ReceiptEntry;
use App\Modules\INV\Queries\ReceivingQueries;
use App\Modules\INV\Queries\ScanLookup;
use App\Support\Command\Outcome;
use Closure;
use Illuminate\Http\JsonResponse;
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
 * PG-021 and PG-022's routes and commands (ROUTE-021; ROUTE-022; ROUTE-181; ROUTE-183; ROUTE-282): a thin edge over the actions and the query classes. A refusal answers at its line, the input kept (NV-04; IP-19); an unknown or out-of-scope record answers 404, alike (CS-04; AZ-11).
 */
final class ReceivingController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    public function __construct(
        private readonly ReceivingQueries $queries,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** ROUTE-021: each company its own deferred group, with its own error and retry (NAV-021). */
    public function worklist(Request $request): Response
    {
        $actor = self::actor($request);
        $groups = $this->queries->groups($actor);
        $props = ['groups' => array_map(fn (array $group): array => array_diff_key($group, ['internal' => 1]), $groups)];
        $after = self::query($request, 'setelah');
        $only = self::query($request, 'kelompok');

        foreach ($groups as $group) {
            $props['group_'.$group['id']] = Inertia::defer(self::group(fn (): array => $this->queries->worklist($actor, $group['internal'], $only === $group['id'] ? $after : null)), 'group_'.$group['id']);
        }

        return Inertia::render('INV/ReceivingWorklist', $props);
    }

    /** ROUTE-022. */
    public function show(Request $request, string $id): Response
    {
        $actor = self::actor($request);
        $purchase = $this->queries->purchase($actor, $id) ?? throw new NotFoundHttpException;

        return Inertia::render('INV/ReceiveGoods', $this->queries->screen($actor, $purchase['id'], $purchase['company']));
    }

    /** ROUTE-181. */
    public function receive(Request $request, ReceiveGoods $receive, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $purchase = $this->queries->purchase($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'baris' => ['required', 'string', 'size:32'],
            'jenis' => ['required', Rule::in(ReceiptEntry::KINDS)],
            'satuan' => ['required', 'string', 'max:32'],
            'layak' => ['required', self::QUANTITY],
            'rusak' => ['required', self::QUANTITY],
            'tolak' => ['required', self::QUANTITY],
            'rak' => ['nullable', 'string', 'size:32'],
            'referensi' => ['nullable', 'string', 'max:120'],
            'mengoreksi' => ['nullable', 'string', 'size:32'],
            'kasus' => ['nullable', 'uuid'],
            'reservasi' => ['nullable', self::QUANTITY],
            'bukti' => ['nullable', 'array', 'max:10'],
            'bukti.*' => ['required', 'uuid'],
            'seri' => ['nullable', 'array', 'max:2000'],
            'seri.*.nomor' => ['required', 'string', 'max:120'],
            'seri.*.rusak' => ['nullable', 'boolean'],
            'kebijakan_seri' => ['required', 'boolean'],
            'kebijakan_skala' => ['required', 'integer', 'min:0', 'max:3'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $line = $this->queries->line($purchase['id'], self::text($input['baris'])) ?? throw ValidationException::withMessages(['baris' => $unknown]);
        $unit = $this->queries->unit($line, self::text($input['satuan'])) ?? throw ValidationException::withMessages(['satuan' => $unknown]);
        $rackHandle = self::nullable($input['rak'] ?? null);
        $rack = $rackHandle === null ? null : ($this->queries->location($rackHandle) ?? throw ValidationException::withMessages(['rak' => $unknown]));
        $citedHandle = self::nullable($input['mengoreksi'] ?? null);
        $cited = $citedHandle === null ? null : ($this->queries->receipt($line, $citedHandle) ?? throw ValidationException::withMessages(['mengoreksi' => $unknown]));
        $case = null;
        $caseKey = self::nullable($input['kasus'] ?? null);

        if ($caseKey !== null) {
            $case = $this->queries->openCase($purchase['company'], $caseKey) ?? throw ValidationException::withMessages(['kasus' => $unknown]);
        }

        $evidence = [];

        foreach (is_array($input['bukti'] ?? null) ? $input['bukti'] : [] as $position => $publicId) {
            $evidence[] = $this->queries->evidence($actor, $purchase['company'], self::text($publicId)) ?? throw ValidationException::withMessages(["bukti.{$position}" => $unknown]);
        }

        $serials = [];

        foreach (is_array($input['seri'] ?? null) ? $input['seri'] : [] as $serial) {
            if (is_array($serial)) {
                $serials[] = ['serial' => trim(self::text($serial['nomor'] ?? '')), 'damaged' => filter_var($serial['rusak'] ?? false, FILTER_VALIDATE_BOOLEAN)];
            }
        }

        $entry = new ReceiptEntry($purchase['id'], $line, self::text($input['jenis']), $unit, self::text($input['layak']), self::text($input['rusak']), self::text($input['tolak']), $rack, $serials, self::nullable($input['referensi'] ?? null), $cited, $case, self::nullable($input['reservasi'] ?? null), $evidence, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $request->boolean('kebijakan_seri'), (int) self::text($input['kebijakan_skala']));
        $outcome = $receive->run(self::text($input['command_id']), $actor, $purchase['company'], $entry, array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'received', '/gudang/barang-masuk/'.$purchase['public_id'], self::text($input['tanggal']));
    }

    /** ROUTE-183: `{id}` the receipt. */
    public function reverse(Request $request, ReverseReceipt $reverse, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $lot = $this->queries->lot($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'tempat' => ['required', 'array', 'min:1', 'max:200'],
            'tempat.*.rak' => ['required', 'string', 'size:32'],
            'tempat.*.kondisi' => ['required', Rule::in(['USABLE', 'UNUSABLE'])],
            'tempat.*.jumlah' => ['required', self::QUANTITY],
            'tempat.*.seri' => ['nullable', 'string', 'size:32'],
            'potong' => ['nullable', 'array', 'max:50'],
            'potong.*.reservasi' => ['required', 'string', 'size:32'],
            'potong.*.jumlah' => ['required', self::QUANTITY],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $removals = [];

        foreach (is_array($input['tempat']) ? $input['tempat'] : [] as $position => $place) {
            $place = is_array($place) ? $place : [];
            $rack = $this->queries->location(self::text($place['rak'] ?? '')) ?? throw ValidationException::withMessages(["tempat.{$position}.rak" => $unknown]);
            $serialHandle = self::nullable($place['seri'] ?? null);
            $serial = $serialHandle === null ? null : ($this->queries->serialOfLot($lot['id'], $serialHandle) ?? throw ValidationException::withMessages(["tempat.{$position}.seri" => $unknown]));
            $removals[] = ['location' => $rack, 'condition' => self::text($place['kondisi'] ?? ''), 'qty' => self::text($place['jumlah'] ?? ''), 'serial' => $serial === null ? null : $serial['id']];
        }

        $cuts = [];

        foreach (is_array($input['potong'] ?? null) ? $input['potong'] : [] as $position => $cut) {
            $cut = is_array($cut) ? $cut : [];
            $reservation = $this->queries->reservation(self::text($cut['reservasi'] ?? '')) ?? throw ValidationException::withMessages(["potong.{$position}.reservasi" => $unknown]);
            $cuts[$reservation] = self::text($cut['jumlah'] ?? '');
        }

        $outcome = $reverse->run(self::text($input['command_id']), $actor, $lot['company'], $lot['id'], $removals, $cuts, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($outcome, 'reversed', self::back($request, '/pembelian/'.$lot['purchase']), self::text($input['tanggal']));
    }

    /** ROUTE-282: read-only, no `command_id`; the scanner's own limiter (WS-12). */
    public function scan(Request $request, ScanLookup $lookup): JsonResponse
    {
        $code = $request->input('kode');

        if (! is_string($code) || trim($code) === '' || mb_strlen($code) > 200) {
            return response()->json(['message' => self::say('projects.validation.required')], 422);
        }

        return response()->json($lookup->find($this->capabilities->for(self::actor($request)), $code));
    }

    /** The command's answer as flash data, the input kept in place; a future date at its field (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, string $to, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("receiving.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome)],
            Outcome::CONFLICT => match ($outcome->code) {
                'BD-01' => ['recorded', self::say('receiving.refusal.duplicate')],
                'ST-09' => ['stale', self::say('receiving.refusal.policy')],
                'ST-02' => ['stale', self::say('receiving.refusal.purchase_state')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'remainder' => 1, 'serial' => 1, 'shortfall' => 1, 'available' => 1])]);

        return redirect()->to($to, 303);
    }

    private static function refusal(Outcome $outcome): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-29' => self::say('receiving.refusal.above_remainder', ['sisa' => self::text($state['remainder'] ?? '0')]),
            'C-02' => $reason === 'in_stock' ? self::say('receiving.refusal.serial_in_stock', ['seri' => self::text($state['serial'] ?? '')]) : self::say('receiving.refusal.serial_count'),
            'C-03' => self::say('receiving.refusal.above_unconsumed', ['sisa' => self::text($state['available'] ?? '0')]),
            'C-06', 'AZ-08' => self::say('receiving.refusal.reservations'),
            'C-09' => self::say('receiving.refusal.quantity'),
            'C-14' => self::say('projects.validation.unknown'),
            'C-21' => self::say('receiving.refusal.charge'),
            'C-59' => self::say('receiving.refusal.cited_receipt'),
            'BR-INV-11' => self::say('receiving.refusal.not_warehouse'),
            'BR-RSV-03', 'BR-RSV-01', 'C-04', 'C-05' => self::say('receiving.refusal.reservation'),
            'WF-INV-01' => self::say('receiving.refusal.no_rack'),
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

    private static function back(Request $request, string $fallback): string
    {
        $back = $request->input('kembali');

        return is_string($back) && preg_match('/^\/(pembelian|gudang)\/[A-Za-z0-9\/_-]*$/', $back) === 1 ? $back : $fallback;
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
