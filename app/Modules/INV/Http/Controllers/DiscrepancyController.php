<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\INV\Actions\AttributeSurplus;
use App\Modules\INV\Actions\ResolveUnattributedLoss;
use App\Modules\INV\Actions\ResolveUnattributedLossWithEvidence;
use App\Modules\INV\Actions\ReverseUnattributedResolution;
use App\Modules\INV\Queries\AdjustmentQueries;
use App\Modules\INV\Queries\DiscrepancyQueries;
use App\Support\Command\Outcome;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-029's routes (ROUTE-029; ROUTE-197–ROUTE-200; NAV-029; RD-09): a thin edge over the actions and the query classes. A resolved row leaves the list in place; a refusal keeps the input (NV-04). An evidence resolution names no company: the lots do (OD-10).
 */
final class DiscrepancyController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    public function __construct(
        private readonly DiscrepancyQueries $queries,
        private readonly AdjustmentQueries $adjustments,
    ) {}

    /** ROUTE-029. */
    public function show(Request $request): Response
    {
        $actor = self::actor($request);

        return Inertia::render('INV/Discrepancies', [
            'findings' => fn (): array => $this->queries->findings($actor, self::query($request, 'setelah_temuan')),
            'cases' => fn (): array => $this->queries->cases($actor, self::query($request, 'setelah_kasus')),
            'resolutions' => fn (): array => $this->queries->resolutions($actor, self::query($request, 'setelah_penetapan')),
            'companies' => fn (): array => $this->queries->companies($actor),
            'proofs' => fn (): array => $this->adjustments->proofs($actor, self::query($request, 'cari_bukti')),
            'today' => AdjustmentQueries::today(),
            'can' => $this->queries->can($actor),
        ]);
    }

    /** ROUTE-197: `{id}` the finding. */
    public function surplus(Request $request, AttributeSurplus $attribute, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $finding = $this->queries->finding($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'perusahaan' => ['required', 'uuid'],
            'dasar_biaya' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'kebijakan_seri' => ['required', 'boolean'],
            'kebijakan_skala' => ['required', 'integer', 'min:0', 'max:3'],
            'pulihkan' => ['nullable', 'array', 'max:50'],
            'pulihkan.*.konsumsi' => ['required', 'string', 'size:32'],
            'pulihkan.*.jumlah' => ['required', self::QUANTITY],
            ...self::common(),
        ]);
        $company = $this->queries->company(self::text($input['perusahaan'])) ?? throw ValidationException::withMessages(['perusahaan' => self::say('projects.validation.unknown')]);
        $restored = [];

        foreach (is_array($input['pulihkan'] ?? null) ? $input['pulihkan'] : [] as $position => $row) {
            $row = is_array($row) ? $row : [];
            $restored[] = ['consumption' => $this->queries->consumption(self::text($row['konsumsi'] ?? '')) ?? throw ValidationException::withMessages(["pulihkan.{$position}.konsumsi" => self::say('projects.validation.unknown')]), 'qty' => self::text($row['jumlah'] ?? '')];
        }

        $outcome = $attribute->run(self::text($input['command_id']), $actor, $finding, $company, Money::of(self::text($input['dasar_biaya'])), $restored, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $request->boolean('kebijakan_seri'), (int) self::text($input['kebijakan_skala']), array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'attributed');
    }

    /** ROUTE-198: `{id}` the case; each line a company and the lots it closes, the Owner's choice. */
    public function resolve(Request $request, ResolveUnattributedLoss $resolve, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $case = $this->queries->case($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'baris' => ['required', 'array', 'min:1', 'max:20'],
            'baris.*.perusahaan' => ['required', 'uuid'],
            'baris.*.tutup' => ['required', 'array', 'min:1', 'max:50'],
            'baris.*.tutup.*.lot' => ['required', 'string', 'size:32'],
            'baris.*.tutup.*.jumlah' => ['required', self::QUANTITY],
            ...self::common(),
        ]);
        $unknown = self::say('projects.validation.unknown');
        $lines = [];

        foreach (is_array($input['baris']) ? $input['baris'] : [] as $position => $line) {
            $line = is_array($line) ? $line : [];
            $lines[] = ['company' => $this->queries->company(self::text($line['perusahaan'] ?? '')) ?? throw ValidationException::withMessages(["baris.{$position}.perusahaan" => $unknown]), 'closes' => $this->closes($line['tutup'] ?? [], "baris.{$position}.tutup")];
        }

        return $this->answer($resolve->run(self::text($input['command_id']), $actor, $case['id'], $lines, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1])), 'resolved');
    }

    /** ROUTE-199: `{id}` the case; the lots and the proof, the company derived from them (OD-10). */
    public function resolveWithEvidence(Request $request, ResolveUnattributedLossWithEvidence $resolve, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $case = $this->queries->case($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'tutup' => ['required', 'array', 'min:1', 'max:50'],
            'tutup.*.lot' => ['required', 'string', 'size:32'],
            'tutup.*.jumlah' => ['required', self::QUANTITY],
            'bukti' => ['nullable', 'array', 'max:10'],
            'bukti.*' => ['required', 'uuid'],
            ...self::common(),
        ]);
        $evidence = [];

        foreach (is_array($input['bukti'] ?? null) ? $input['bukti'] : [] as $position => $publicId) {
            $evidence[] = $this->queries->evidence($actor, self::text($publicId)) ?? throw ValidationException::withMessages(["bukti.{$position}" => self::say('projects.validation.unknown')]);
        }

        return $this->answer($resolve->run(self::text($input['command_id']), $actor, $case['id'], $this->closes($input['tutup'], 'tutup'), $evidence, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1])), 'resolved');
    }

    /** ROUTE-200: `{id}` the resolution; an evidence-identified one by its capability or the Owner (OD-10; OD-15). */
    public function reverse(Request $request, ReverseUnattributedResolution $reverse, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $resolution = $this->queries->resolution($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, self::common());

        return $this->answer($reverse->run(self::text($input['command_id']), $actor, $resolution, $this->queries->reversalCapability($actor, $resolution), trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1])), 'reversed');
    }

    /** @return list<array{lot: int, qty: string}> */
    private function closes(mixed $rows, string $field): array
    {
        $closes = [];

        foreach (is_array($rows) ? $rows : [] as $position => $row) {
            $row = is_array($row) ? $row : [];
            $closes[] = ['lot' => $this->queries->lot(self::text($row['lot'] ?? '')) ?? throw ValidationException::withMessages(["{$field}.{$position}.lot" => self::say('projects.validation.unknown')]), 'qty' => self::text($row['jumlah'] ?? '')];
        }

        return $closes;
    }

    /** @return array<string, list<string>> */
    private static function common(): array
    {
        return ['alasan' => ['required', 'string', 'min:3', 'max:500'], 'tanggal' => ['required', 'date_format:Y-m-d'], 'tanggal_otomatis' => ['nullable', 'boolean']];
    }

    private function answer(Outcome $outcome, string $done): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("discrepancies.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-09' => ['stale', self::say('receiving.refusal.policy')],
                default => ['recorded', self::say('discrepancies.refusal.changed')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'condition' => 1, 'available' => 1, 'pending' => 1])]);

        return redirect()->to('/gudang/selisih-temuan', 303);
    }

    private static function refusal(Outcome $outcome): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'OD-10' => self::say('discrepancies.refusal.evidence_'.self::text($state['condition'] ?? 'a')),
            'C-35' => $reason === 'above_pending' ? self::say('discrepancies.refusal.above_pending', ['jumlah' => self::text($state['pending'] ?? '')]) : self::say('discrepancies.refusal.finding'),
            'C-37' => match ($reason) {
                'above_bound' => self::say('discrepancies.refusal.above_bound'),
                'not_candidate' => self::say('discrepancies.refusal.not_candidate'),
                'own_first' => self::say('discrepancies.refusal.own_first'),
                'lot_short' => self::say('discrepancies.refusal.lot_short'),
                'condition_own_only' => self::say('discrepancies.refusal.condition_own_only'),
                'reversed' => self::say('discrepancies.refusal.reversed'),
                default => self::say('discrepancies.refusal.changed'),
            },
            'C-02' => self::say('dispatch.refusal.serial'),
            'AZ-08' => self::say('messages.msg_14'),
            'C-14' => self::say('projects.validation.unknown'),
            'C-27' => self::say('counts.refusal.above_variance'),
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
        $fields = array_values(array_unique(array_map(fn (string $field): string => explode('.', $field)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
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
}
