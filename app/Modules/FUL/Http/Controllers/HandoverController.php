<?php

namespace App\Modules\FUL\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Queries\EvidenceList;
use App\Modules\FUL\Actions\RecordHandover;
use App\Modules\FUL\Actions\ReverseHandover;
use App\Modules\FUL\Queries\ServiceHandovers;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Queries\ProjectWorkspace;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-033 *Serah Terima Jasa* (ROUTE-033; NAV-033; WF-FUL-04; J-FUL-04) with "Catat Serah Terima" (ROUTE-208) and
 * "Balikkan" (ROUTE-209), each with its `command_id` (CI-01): the handover's answer on PG-011 (NAV-033), the reversal's
 * in place; validation at the fields (IP-14), a refusal in words with the input kept (IP-03; NV-04), a likely duplicate
 * with its matches (IP-05; INT-015). An unknown project, handover or line answers as unknown (AZ-11).
 */
final class HandoverController extends Controller
{
    public function __construct(
        private readonly ProjectWorkspace $projects,
        private readonly ServiceHandovers $handovers,
        private readonly EvidenceList $evidence,
    ) {}

    /** ROUTE-033. */
    public function show(Request $request, string $id): Response
    {
        $actor = self::actor($request);

        $after = $request->query('setelah');

        return Inertia::render('FUL/ServiceHandover', $this->handovers->screen($actor, $this->project($actor, $id), is_string($after) ? $after : null));
    }

    /** ROUTE-208 "Catat Serah Terima". */
    public function store(Request $request, RecordHandover $record, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->project($actor, $id);
        $input = self::validated($request, [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'penerima' => ['required', 'string', 'min:3', 'max:200'],
            'bukti' => ['nullable', 'uuid'],
            'baris' => ['required', 'array', 'min:1', 'max:200'],
            'baris.*.baris' => ['required', 'string', 'size:32'],
            'baris.*.jumlah' => ['required', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
            'baris.*.lingkup' => ['required', 'string', 'min:3', 'max:500'],
            'acknowledged' => ['nullable', 'array', 'max:50'],
            'acknowledged.*' => ['uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $handles = $this->handovers->lineHandles($project);
        $lines = [];
        $seen = [];

        foreach (is_array($input['baris']) ? array_values($input['baris']) : [] as $index => $line) {
            $line = is_array($line) ? $line : [];
            $handle = self::text($line['baris'] ?? '');
            $lineId = $handles[$handle] ?? throw ValidationException::withMessages(["baris.{$index}.baris" => self::say('fulfilment.validation.line')]);

            if (isset($seen[$lineId])) {
                throw ValidationException::withMessages(["baris.{$index}.baris" => self::say('fulfilment.validation.duplicate')]);
            }

            $seen[$lineId] = true;
            $quantity = self::text($line['jumlah'] ?? '');

            if (! BigDecimal::of($quantity)->isPositive()) {
                throw ValidationException::withMessages(["baris.{$index}.jumlah" => self::say('handover.validation.quantity')]);
            }

            $lines[] = ['line' => $lineId, 'quantity' => $quantity, 'scope' => trim(self::text($line['lingkup'] ?? ''))];
        }

        $evidence = isset($input['bukti']) ? ($this->evidence->idOf($actor, self::text($input['bukti']), $project->companyId, ServiceHandovers::EVIDENCE)
            ?? throw ValidationException::withMessages(['bukti' => self::say('fulfilment.validation.evidence')])) : null;
        $reason = trim(self::text($input['reason'] ?? ''));
        /** @var list<string> $acknowledged */
        $acknowledged = array_values(array_filter(is_array($input['acknowledged'] ?? null) ? $input['acknowledged'] : [], is_string(...)));
        $outcome = $record->run(self::text($input['command_id']), $actor, $project, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), trim(self::text($input['penerima'])),
            $lines, $evidence, $acknowledged, $reason === '' ? null : $reason, array_diff_key($input, ['command_id' => true]));

        return $this->answer($project, $outcome, 'recorded', self::text($input['tanggal']));
    }

    /** ROUTE-209 "Balikkan" with MSG-40's reason (IP-09). */
    public function reverse(Request $request, ReverseHandover $reverse, string $id, string $ho): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->project($actor, $id);
        $handover = $this->handovers->handoverOf($project, $ho) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'baris' => ['required', 'string', 'size:32'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $line = $this->handovers->handoverLine($handover, self::text($input['baris'])) ?? throw ValidationException::withMessages(['baris' => self::say('fulfilment.validation.line')]);
        $outcome = $reverse->run(self::text($input['command_id']), $actor, $project, $handover, strtolower($ho), $line['line'], $line['project_line'], self::text($input['baris']), trim(self::text($input['alasan'])));

        return $this->answer($project, $outcome, 'reversed', BusinessDate::today());
    }

    /** A project the viewer may see, else unknown (AZ-11). */
    private function project(int $actor, string $id): ProjectRef
    {
        return $this->projects->reference($actor, $id) ?? throw new NotFoundHttpException;
    }

    /** The command's answer as flash data: a recorded handover on PG-011 (NAV-033), every other answer in place on PG-033. */
    private function answer(ProjectRef $project, Outcome $outcome, string $done, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $matches = $outcome->code === 'L-49' ? $this->handovers->matches($project, array_values(array_filter(is_array($state['matches'] ?? null) ? $state['matches'] : [], is_string(...)))) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("handover.answer.{$done}")],
            Outcome::REJECTED => [null, $this->refusal($outcome, $matches ?? [])],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                'C-30' => ['recorded', self::say('handover.refusal.reversed')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, ...($matches === null ? [] : ['state' => ['matches' => $matches]])]);

        return redirect()->to($outcome->kind === Outcome::COMMITTED && $done === 'recorded' ? "/proyek/{$project->publicId}" : "/proyek/{$project->publicId}/serah-terima", 303);
    }

    /** @param  list<array{id: string, label: string, href: null}>  $matches */
    private function refusal(Outcome $outcome, array $matches): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-30' => match ($reason) {
                'remainder' => self::say('handover.refusal.remainder', ['baris' => self::text($state['line'] ?? ''), 'sisa' => self::text($state['remainder'] ?? '0')]),
                'reversed' => self::say('handover.refusal.reversed'),
                default => self::say('handover.refusal.not_reversible'),
            },
            'C-09' => self::say('handover.refusal.scale', ['baris' => self::text($state['line'] ?? ''), 'skala' => self::text($state['scale'] ?? '0')]),
            'L-49' => self::say('messages.msg_15', ['daftar_catatan_yang_cocok' => implode('; ', array_column($matches, 'label'))]),
            'L-39' => self::say('handover.refusal.not_service', ['baris' => self::text($state['line'] ?? '')]),
            'L-20' => self::say('fulfilment.refusal.unconfirmed'),
            'L-11' => self::say('projects.refusal.closed'),
            default => self::say('messages.msg_04'),
        };
    }

    /**
     * @param  array<string, list<string>>  $rules
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

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
