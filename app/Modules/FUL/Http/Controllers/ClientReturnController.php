<?php

namespace App\Modules\FUL\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FUL\Queries\ClientReturns;
use App\Modules\INV\Actions\RestoreStock;
use App\Modules\INV\Actions\ReverseRestorationOrReturn;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-034 *Retur dari Klien* (ROUTE-034; ROUTE-210; ROUTE-211; NAV-034; WF-FUL-05): a thin edge over INV's actions and FUL's query
 * class. A return answers on PG-034 with its case opened (NAV-034), a reversal in place on the page that sent it — PG-034 or
 * PG-028; a refusal keeps the input (NV-04).
 */
final class ClientReturnController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    public function __construct(
        private readonly ClientReturns $returns,
    ) {}

    /** ROUTE-034: the shipments the client received, the cases with their steps, the restored units. */
    public function show(Request $request, string $id): Response
    {
        return Inertia::render('FUL/ClientReturn', [
            'page' => $this->returns->page(self::actor($request), $id) ?? throw new NotFoundHttpException,
            'today' => BusinessDate::today(),
        ]);
    }

    /** ROUTE-210 "Retur dari Klien": the shipment line, per unit its consumption, quantity, condition and rack, the case's reason and evidence (AX-10). */
    public function record(Request $request, RestoreStock $restore, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->returns->projectOf($actor, $id, RestoreStock::CAPABILITY) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'baris' => ['required', 'string', 'size:32'],
            'unit' => ['required', 'array', 'min:1', 'max:200'],
            'unit.*.konsumsi' => ['required', 'string', 'size:32'],
            'unit.*.jumlah' => ['required', self::QUANTITY],
            'unit.*.satuan_dasar' => ['nullable', 'boolean'],
            'unit.*.kondisi' => ['required', Rule::in(['USABLE', 'UNUSABLE'])],
            'unit.*.rak' => ['required', 'string', 'size:32'],
            'unit.*.seri' => ['nullable', 'string', 'size:32'],
            'reservasi' => ['nullable', self::QUANTITY],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'bukti' => ['required', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'acknowledged' => ['nullable', 'array', 'max:50'],
            'acknowledged.*' => ['uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $line = $this->returns->dispatchLineOf($project, self::text($input['baris'])) ?? throw ValidationException::withMessages(['baris' => self::say('fulfilment.validation.line')]);
        $items = [];

        foreach (is_array($input['unit']) ? array_values($input['unit']) : [] as $index => $unit) {
            $unit = is_array($unit) ? $unit : [];
            $consumption = $this->returns->consumptionOf($line, self::text($unit['konsumsi'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$index}.konsumsi" => self::say('projects.validation.unknown')]);
            $serial = isset($unit['seri']) ? ($this->returns->serialOf($consumption, self::text($unit['seri'])) ?? throw ValidationException::withMessages(["unit.{$index}.seri" => self::say('projects.validation.unknown')])) : null;
            $items[] = ['consumption' => $consumption, 'qty' => self::text($unit['jumlah'] ?? ''), 'base_unit' => (bool) ($unit['satuan_dasar'] ?? false), 'condition' => self::text($unit['kondisi'] ?? ''),
                'location' => $this->returns->rackOf(self::text($unit['rak'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$index}.rak" => self::say('projects.validation.required')]), 'serial' => $serial];
        }

        $evidence = $this->returns->evidenceOf($actor, $project->companyId, self::text($input['bukti'])) ?? throw ValidationException::withMessages(['bukti' => self::say('fulfilment.validation.evidence')]);
        $policy = $this->returns->policyOf($line);
        $reserve = self::text($input['reservasi'] ?? '');
        $outcome = $restore->run(self::text($input['command_id']), $actor, $project->companyId, $line, $items, $reserve === '' ? null : $reserve, trim(self::text($input['alasan'])), $evidence, self::strings($input['acknowledged'] ?? null), self::reason($input),
            self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $policy['serialized'], $policy['scale'], array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true]));

        return $this->answer($outcome, 'recorded', self::text($input['tanggal']), "/proyek/{$project->publicId}/retur");
    }

    /** ROUTE-211 "Balikkan (Tercatat Keliru)": the original's restorations or returned consumptions with their quantities, a cut where needed, MSG-40's reason (AX-36; IP-20). */
    public function reverse(Request $request, ReverseRestorationOrReturn $reverse, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $original = $this->returns->originalOf($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'unit' => ['required', 'array', 'min:1', 'max:200'],
            'unit.*.pemulihan' => ['nullable', 'string', 'size:32'],
            'unit.*.konsumsi' => ['nullable', 'string', 'size:32'],
            'unit.*.jumlah' => ['nullable', self::QUANTITY],
            'unit.*.kondisi' => ['nullable', Rule::in(['USABLE', 'UNUSABLE'])],
            'unit.*.rak' => ['nullable', 'string', 'size:32'],
            'potong' => ['nullable', 'array', 'max:50'],
            'potong.*.reservasi' => ['required', 'string', 'size:32'],
            'potong.*.jumlah' => ['required', self::QUANTITY],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'asal' => ['nullable', 'string', 'max:300'],
        ]);
        $back = self::back(self::text($input['asal'] ?? ''));
        $payload = array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true, 'asal' => true]);
        $units = is_array($input['unit']) ? array_values($input['unit']) : [];

        if ($original['confirmation'] !== null) {
            $first = is_array($units[0] ?? null) ? $units[0] : [];
            $restoration = $this->returns->restorationOf(null, $original['confirmation'], self::text($first['pemulihan'] ?? '')) ?? throw ValidationException::withMessages(['unit.0.pemulihan' => self::say('projects.validation.unknown')]);
            $outcome = $reverse->runDropship(self::text($input['command_id']), $actor, $original['company'], $original['confirmation'], $restoration, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $payload);

            return $this->answer($outcome, 'reversed', self::text($input['tanggal']), $back);
        }

        $movement = (int) $original['movement'];
        $items = [];

        foreach ($units as $index => $unit) {
            $unit = is_array($unit) ? $unit : [];

            if ($original['type'] === 'PURCHASE_RETURN') {
                $items[] = ['restoration' => null, 'consumption' => $this->returns->returnedConsumptionOf($movement, self::text($unit['konsumsi'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$index}.konsumsi" => self::say('projects.validation.unknown')]),
                    'qty' => self::text($unit['jumlah'] ?? ''), 'location' => $this->returns->rackOf(self::text($unit['rak'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$index}.rak" => self::say('projects.validation.required')]),
                    'condition' => isset($unit['kondisi']) ? self::text($unit['kondisi']) : throw ValidationException::withMessages(["unit.{$index}.kondisi" => self::say('projects.validation.required')])];

                continue;
            }

            $items[] = ['restoration' => $this->returns->restorationOf($movement, null, self::text($unit['pemulihan'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$index}.pemulihan" => self::say('projects.validation.unknown')]),
                'consumption' => null, 'qty' => self::text($unit['jumlah'] ?? ''), 'location' => null, 'condition' => null];
        }

        $cuts = [];

        foreach (is_array($input['potong'] ?? null) ? array_values($input['potong']) : [] as $cut) {
            $cut = is_array($cut) ? $cut : [];
            $reservation = RowHandle::valid(self::text($cut['reservasi'] ?? '')) ? $this->returns->reservationOf(self::text($cut['reservasi'] ?? '')) : null;

            if ($reservation !== null) {
                $cuts[$reservation] = self::text($cut['jumlah'] ?? '');
            }
        }

        $outcome = $reverse->run(self::text($input['command_id']), $actor, $original['company'], $movement, $items, $cuts, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $payload);

        return $this->answer($outcome, 'reversed', self::text($input['tanggal']), $back, $outcome->code === 'C-06' ? $this->returns->cutChoices($actor, $movement, $original['company']) : []);
    }

    /** The page a reversal answers on: PG-034 or PG-028 that sent it, never another address. */
    private static function back(string $from): string
    {
        return preg_match('#^/(proyek/[0-9a-f-]{36}/retur|gudang/retur-pemasok(\?kasus=[0-9a-f-]{36})?)$#', $from) === 1 ? $from : '/gudang/retur-pemasok';
    }

    /** @param  list<array<string, mixed>>  $choices */
    private function answer(Outcome $outcome, string $done, string $sent, string $page, array $choices = []): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $matches = $outcome->code === 'L-49' ? $this->returns->matches(self::strings($state['matches'] ?? null)) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("returns_client.answer.{$done}").self::reactivated($this->returns->reactivatedBy(self::text(request()->input('command_id'))))],
            Outcome::REJECTED => [null, self::refusal($outcome, $matches ?? [])],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                'ST-09' => ['stale', self::say('returns_client.refusal.policy')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, ...($matches === null ? [] : ['state' => ['matches' => $matches]]),
            ...($outcome->code === 'C-06' ? ['state' => [...$state, 'reservations' => $choices]] : [])]);

        return redirect()->to($page, 303);
    }

    /** @param  list<array{id: string, label: string, href: null}>  $matches */
    private static function refusal(Outcome $outcome, array $matches): string
    {
        $state = $outcome->state;

        return match ($outcome->code) {
            'C-30' => self::say('returns_client.refusal.delivered', ['sisa' => self::text($state['remainder'] ?? '0')]),
            'C-27' => self::say('returns_client.refusal.open', ['sisa' => self::text($state['open'] ?? '0')]),
            'C-55' => self::say('returns_client.refusal.reversal', ['sisa' => self::text($state['open'] ?? '0')]),
            'C-03' => self::say('returns_client.refusal.not_present'),
            'C-02' => self::say('returns_client.refusal.serial'),
            'C-46' => self::say('returns_client.refusal.settled'),
            'C-29' => self::say('returns_client.refusal.replaced', ['sisa' => self::text($state['remainder'] ?? '0')]),
            'C-06' => self::say('returns_client.refusal.reserved'),
            'C-09' => self::say('returns_client.refusal.scale'),
            'L-49' => self::say('messages.msg_15', ['daftar_catatan_yang_cocok' => implode('; ', array_column($matches, 'label'))]),
            'AZ-01' => self::say('messages.msg_14'),
            'AZ-08' => self::say('messages.msg_14'),
            'BR-RSV-03' => self::say('returns_client.refusal.reserve'),
            default => self::say('returns_client.refusal.other'),
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
            'string' => __('projects.validation.choice'),
        ])->validate();

        return $valid;
    }

    /** @param  array<string, string>  $replace */
    private static function say(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }

    /**
     * SF-REVAL's sentence: each completed project the command returned to *Aktif* (CM-32).
     *
     * @param  list<string>  $numbers
     */
    private static function reactivated(array $numbers): string
    {
        return $numbers === [] ? '' : ' '.self::say('returns_client.answer.reactivated', ['nomor' => implode(', ', $numbers)]);
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
