<?php

namespace App\Modules\FUL\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FUL\Actions\CloseDelivery;
use App\Modules\FUL\Actions\CorrectDelivery;
use App\Modules\FUL\Actions\RecordDelivery;
use App\Modules\FUL\Queries\Deliveries;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-030 *Pengiriman* and PG-031 *Catat Pengiriman* (ROUTE-030; ROUTE-031; ROUTE-201; ROUTE-203; ROUTE-204; NAV-030;
 * NAV-031): a thin edge over the FUL actions and the query class. A recorded delivery answers on PG-030 (NAV-031's success
 * destination), a closure and a correction in place on PG-031; a refusal keeps the input (NV-04). "Terbitkan" drafts its
 * paper through ROUTE-221 and lands on PG-036 (`DIR-081` OB-02), ROUTE-202 issuing it there.
 */
final class DeliveryController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    private const PAGE = '/gudang/pengiriman';

    public function __construct(
        private readonly Deliveries $deliveries,
    ) {}

    /** ROUTE-030: the shipments awaiting delivery, or `?tab=tercatat` the deliveries recorded (IP-18). */
    public function index(Request $request): Response
    {
        $actor = self::actor($request);
        $tab = $request->query('tab') === 'tercatat' ? 'tercatat' : 'menunggu';

        return Inertia::render('FUL/Deliveries', ['list' => $this->deliveries->list($actor, $tab), 'can' => $this->deliveries->can($actor)]);
    }

    /** ROUTE-031: one shipment with its lines, deliveries and closures. */
    public function show(Request $request, string $id): Response
    {
        $actor = self::actor($request);

        return Inertia::render('FUL/RecordDelivery', [
            'shipment' => $this->deliveries->shipment($actor, $id) ?? throw new NotFoundHttpException,
            'today' => BusinessDate::today(),
            'can' => $this->deliveries->can($actor),
        ]);
    }

    /** ROUTE-201 "Catat Pengiriman": the recipient, the date, the condition, the proof, per line its quantity (AX-09). */
    public function record(Request $request, RecordDelivery $record, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $shipment = $this->deliveries->shipmentOf($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'penerima' => ['required', 'string', 'min:3', 'max:200'],
            'kondisi' => ['nullable', 'string', 'max:500'],
            'selisih' => ['nullable', 'boolean'],
            'pemeriksa' => ['nullable', 'string', 'max:200'],
            'hasil_pemeriksaan' => ['nullable', 'string', 'max:500'],
            'bukti' => ['required', 'uuid'],
            'baris' => ['required', 'array', 'min:1', 'max:200'],
            'baris.*.baris' => ['required', 'string', 'size:32'],
            'baris.*.jumlah' => ['required', self::QUANTITY],
            'acknowledged' => ['nullable', 'array', 'max:50'],
            'acknowledged.*' => ['uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $condition = trim(self::text($input['kondisi'] ?? ''));

        // A difference or a condition problem opens its case with the condition as its reason (SF-CASE).
        if ($request->boolean('selisih') && mb_strlen($condition) < 3) {
            throw ValidationException::withMessages(['kondisi' => self::say('deliveries.validation.discrepancy')]);
        }

        $inspector = trim(self::text($input['pemeriksa'] ?? ''));
        $result = trim(self::text($input['hasil_pemeriksaan'] ?? ''));

        if (($inspector === '') !== ($result === '')) {
            throw ValidationException::withMessages([$inspector === '' ? 'pemeriksa' : 'hasil_pemeriksaan' => self::say('projects.validation.required')]);
        }

        $lines = [];
        $seen = [];

        foreach (is_array($input['baris']) ? array_values($input['baris']) : [] as $index => $line) {
            $line = is_array($line) ? $line : [];
            $shipmentLine = $this->deliveries->lineOf($shipment['movement'], self::text($line['baris'] ?? '')) ?? throw ValidationException::withMessages(["baris.{$index}.baris" => self::say('fulfilment.validation.line')]);
            $quantity = self::text($line['jumlah'] ?? '');

            if (isset($seen[$shipmentLine])) {
                throw ValidationException::withMessages(["baris.{$index}.baris" => self::say('fulfilment.validation.duplicate')]);
            }

            $seen[$shipmentLine] = true;

            // A line left at zero is no delivery of it (PX-02): only what reached the client is recorded.
            if (BigDecimal::of($quantity)->isPositive()) {
                $lines[] = ['shipment' => $shipmentLine, 'line' => $this->deliveries->projectLineOf($shipmentLine), 'quantity' => $quantity];
            }
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['baris' => self::say('deliveries.validation.nothing')]);
        }

        $evidence = $this->deliveries->evidenceOf($actor, $shipment['project']->companyId, self::text($input['bukti'])) ?? throw ValidationException::withMessages(['bukti' => self::say('fulfilment.validation.evidence')]);
        $reason = self::reason($input);
        $acknowledged = self::strings($input['acknowledged'] ?? null);
        $outcome = $record->run(self::text($input['command_id']), $actor, $shipment['project'], self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), trim(self::text($input['penerima'])),
            $condition === '' ? null : $condition, $request->boolean('selisih') ? $condition : null, $inspector === '' ? null : ['inspector' => $inspector, 'result' => $result],
            $lines, $evidence, $acknowledged, $reason, array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true]));

        return $this->answer($shipment['project'], strtolower($id), $outcome, 'recorded', self::text($input['tanggal']));
    }

    /** ROUTE-203 "Tutup Pengiriman" or "Catat Hilang di Perjalanan" with MSG-40's reason (IP-09; NX-15; AX-30). */
    public function close(Request $request, CloseDelivery $close, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $shipment = $this->deliveries->shipmentOf($actor, $id, 'delivery.close') ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'baris' => ['required', 'string', 'size:32'],
            'jenis' => ['required', Rule::in(['REFUSED', 'RETURNED_UNDELIVERED', 'LOST_IN_TRANSIT'])],
            'jumlah' => ['required', self::QUANTITY],
            'seri' => ['nullable', 'array', 'max:200'],
            'seri.*' => ['string', 'size:32'],
            'menggantikan' => ['nullable', 'string', 'size:32'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'acknowledged' => ['nullable', 'array', 'max:50'],
            'acknowledged.*' => ['string', 'size:32'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $line = $this->deliveries->lineOf($shipment['movement'], self::text($input['baris'])) ?? throw ValidationException::withMessages(['baris' => self::say('fulfilment.validation.line')]);
        /** @var list<string> $handles */
        $handles = array_values(array_filter(is_array($input['seri'] ?? null) ? $input['seri'] : [], is_string(...)));
        $serials = $this->deliveries->serialsOf($line, $handles) ?? throw ValidationException::withMessages(['seri' => self::say('projects.validation.unknown')]);
        $supersedes = isset($input['menggantikan']) ? ($this->deliveries->closureOf($line, self::text($input['menggantikan'])) ?? throw ValidationException::withMessages(['menggantikan' => self::say('projects.validation.unknown')])) : null;
        $outcome = $close->run(self::text($input['command_id']), $actor, $shipment['project'], $line, self::text($input['jenis']), self::text($input['jumlah']), $serials, $supersedes,
            trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true]), self::strings($input['acknowledged'] ?? null), self::reason($input));

        return $this->answer($shipment['project'], strtolower($id), $outcome, self::text($input['jenis']) === 'LOST_IN_TRANSIT' ? 'lost' : 'closed', self::text($input['tanggal']), $line);
    }

    /** ROUTE-204 "Koreksi Catatan Pengiriman": the wrong line reversed exactly, then the correct quantity (CM-08). */
    public function correct(Request $request, CorrectDelivery $correct, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $shipment = $this->deliveries->shipmentOf($actor, $id, 'fulfillment.correct') ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'pengiriman' => ['required', 'uuid'],
            'baris' => ['required', 'string', 'size:32'],
            'jumlah' => ['nullable', self::QUANTITY],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'acknowledged' => ['nullable', 'array', 'max:50'],
            'acknowledged.*' => ['uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $delivery = $this->deliveries->deliveryOf($shipment['movement'], self::text($input['pengiriman'])) ?? throw ValidationException::withMessages(['pengiriman' => self::say('projects.validation.unknown')]);
        $line = $this->deliveries->deliveryLineOf($delivery, self::text($input['baris'])) ?? throw ValidationException::withMessages(['baris' => self::say('fulfilment.validation.line')]);
        $quantity = self::text($input['jumlah'] ?? '');
        $outcome = $correct->run(self::text($input['command_id']), $actor, $shipment['project'], $delivery, $line, $quantity === '' || ! BigDecimal::of($quantity)->isPositive() ? null : $quantity,
            trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true]), self::strings($input['acknowledged'] ?? null), self::reason($input));

        return $this->answer($shipment['project'], strtolower($id), $outcome, 'corrected', self::text($input['tanggal']));
    }

    /**
     * The command's answer as flash data: a recorded delivery on PG-030 (NAV-031), every other answer in place on PG-031.
     *
     * @param  ?int  $closed  the shipment line a closure's likely duplicates stand on, as MSG-15 lists them
     */
    private function answer(ProjectRef $project, string $shipment, Outcome $outcome, string $done, string $sent, ?int $closed = null): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $named = self::strings($state['matches'] ?? null);
        $matches = $outcome->code === 'L-49' ? ($closed === null ? $this->deliveries->matches($project->id, $named) : $this->deliveries->closureMatches($closed, $named)) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("deliveries.answer.{$done}")],
            Outcome::REJECTED => [null, $this->refusal($outcome, $matches ?? [])],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, ...($matches === null ? [] : ['state' => ['matches' => $matches]])]);

        return redirect()->to($outcome->kind === Outcome::COMMITTED && $done === 'recorded' ? self::PAGE : self::PAGE.'/'.$shipment, 303);
    }

    /** @param  list<array{id: string, label: string, href: null}>  $matches */
    private function refusal(Outcome $outcome, array $matches): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-30' => match ($reason) {
                'remainder' => self::say('deliveries.refusal.remainder', ['baris' => self::text($state['line'] ?? ''), 'sisa' => self::text($state['remainder'] ?? '0')]),
                'reversed' => self::say('deliveries.refusal.reversed'),
                default => self::say('deliveries.refusal.not_reversible'),
            },
            'C-09' => self::say('deliveries.refusal.scale', ['baris' => self::text($state['line'] ?? '')]),
            'C-02' => self::say('deliveries.refusal.serials', ['baris' => self::text($state['line'] ?? '')]),
            'C-07' => $reason === 'supersedes' ? self::say('deliveries.refusal.supersedes') : self::say('messages.msg_04'),
            'C-27' => self::say('deliveries.refusal.lost', ['baris' => self::text($state['line'] ?? '')]),
            'L-49' => self::say('messages.msg_15', ['daftar_catatan_yang_cocok' => implode('; ', array_column($matches, 'label'))]),
            'L-11' => self::say('projects.refusal.closed'),
            'AZ-08' => self::say('messages.msg_14'),
            default => self::say('messages.msg_04'),
        };
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $rules = ['command_id' => ['required', 'uuid'], ...$rules];
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'array' => __('projects.validation.choice'),
            'boolean' => __('projects.validation.choice'),
            'in' => __('projects.validation.choice'),
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

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return array_values(array_filter(is_array($values) ? $values : [], is_string(...)));
    }

    /** @param  array<string, mixed>  $input */
    private static function reason(array $input): ?string
    {
        $reason = trim(self::text($input['reason'] ?? ''));

        return $reason === '' ? null : $reason;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
