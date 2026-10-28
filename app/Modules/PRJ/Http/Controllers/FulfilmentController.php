<?php

namespace App\Modules\PRJ\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Queries\EvidenceList;
use App\Modules\PRJ\Actions\CancelRemainingScope;
use App\Modules\PRJ\Actions\ConfirmWithoutQuotation;
use App\Modules\PRJ\Actions\SplitReplanLine;
use App\Modules\PRJ\Queries\ProjectWorkspace;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-011's confirmation and *Item dan pemenuhan* commands (WF-PRJ-02; WF-FUL-01; J-PRJ-02; J-FUL-01): "Konfirmasi
 * Proyek" (ROUTE-158) on the client's order evidence, "Batalkan Sisa" (ROUTE-159) and "Ubah Cara Pemenuhan"
 * (ROUTE-160), each with its `command_id` (CI-01) and its answer in place on PG-011 as flash data (IP-01; NV-03) — a
 * refusal in words with the input kept (IP-03; IP-14). A line names itself by the handle PG-011 showed; an unknown
 * project, line or evidence answers as unknown (AZ-11).
 */
final class FulfilmentController extends Controller
{
    public function __construct(
        private readonly ProjectWorkspace $projects,
        private readonly EvidenceList $evidence,
    ) {}

    /** ROUTE-158 "Konfirmasi Proyek" after its confirmation (IP-08; MSG-39). */
    public function confirm(Request $request, ConfirmWithoutQuotation $confirm, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->projects->lookup($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'bukti' => ['required', 'uuid'],
            'alasan' => ['nullable', 'string', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $evidence = $this->evidence->idOf($actor, self::text($input['bukti']), $project->company_id, ConfirmWithoutQuotation::EVIDENCE)
            ?? throw ValidationException::withMessages(['bukti' => self::say('fulfilment.validation.evidence')]);
        $reason = trim(self::text($input['alasan'] ?? ''));
        $outcome = $confirm->run(self::text($input['command_id']), $actor, $project, $evidence, self::text($input['bukti']), $reason === '' ? null : $reason,
            self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($project->public_id, $outcome, 'confirmed', self::text($input['tanggal']));
    }

    /** ROUTE-159 "Batalkan Sisa" with MSG-40's reason (IP-09) and the reservation cut (IP-20). */
    public function cancel(Request $request, CancelRemainingScope $cancel, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->projects->lookup($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'baris' => ['required', 'array', 'min:1', 'max:200'],
            'baris.*.baris' => ['required', 'string', 'size:32'],
            'baris.*.jumlah' => ['required', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'bukti' => ['nullable', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $quantities = [];

        foreach (is_array($input['baris']) ? array_values($input['baris']) : [] as $index => $line) {
            $line = is_array($line) ? $line : [];
            $lineId = $this->projects->lineOf($project, self::text($line['baris'] ?? '')) ?? throw ValidationException::withMessages(["baris.{$index}.baris" => self::say('fulfilment.validation.line')]);
            $quantities[$lineId] = self::text($line['jumlah'] ?? '');
        }

        $evidence = isset($input['bukti']) ? ($this->evidence->idOf($actor, self::text($input['bukti']), $project->company_id, ['CLIENT_SPK', 'CLIENT_ORDER', 'SIPLAH_ORDER', 'OTHER'])
            ?? throw ValidationException::withMessages(['bukti' => self::say('fulfilment.validation.evidence')])) : null;
        $outcome = $cancel->run(self::text($input['command_id']), $actor, $project, $quantities, trim(self::text($input['alasan'])), self::text($input['tanggal']),
            $request->boolean('tanggal_otomatis'), $evidence, array_diff_key($input, ['command_id' => true]));

        return $this->answer($project->public_id, $outcome, 'cancelled', self::text($input['tanggal']));
    }

    /** ROUTE-160 "Ubah Cara Pemenuhan" with its reason (IP-09; J-FUL-01). */
    public function replan(Request $request, SplitReplanLine $split, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->projects->lookup($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'baris' => ['required', 'string', 'size:32'],
            'cara' => ['required', 'in:WAREHOUSE,DROPSHIP'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $lineId = $this->projects->lineOf($project, self::text($input['baris'])) ?? throw ValidationException::withMessages(['baris' => self::say('fulfilment.validation.line')]);
        $outcome = $split->run(self::text($input['command_id']), $actor, $project, $lineId, self::text($input['baris']), self::text($input['cara']), trim(self::text($input['alasan'])));

        return $this->answer($project->public_id, $outcome, 'replanned', BusinessDate::today());
    }

    /** The command's answer as flash data on PG-011; a future date at its field (C-50). */
    private function answer(string $project, Outcome $outcome, string $done, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("fulfilment.answer.{$done}")],
            Outcome::REJECTED => [null, match ($outcome->code) {
                'C-05' => self::say('fulfilment.refusal.executed', ['baris' => self::text($state['line'] ?? '')]),
                'C-31' => self::say('fulfilment.refusal.invoiced'),
                'ST-06' => self::say('fulfilment.refusal.remainder', ['baris' => self::text($state['line'] ?? ''), 'sisa' => self::text($state['remainder'] ?? '0')]),
                'L-11' => self::say('projects.refusal.closed'),
                'L-20' => self::say('fulfilment.refusal.unconfirmed'),
                'L-21' => self::say($reason === 'mode' ? 'fulfilment.refusal.mode' : 'fulfilment.refusal.started'),
                'WF-PRJ-02' => self::say(in_array($reason, ['quotation', 'no_lines', 'unpriced', 'details'], true) ? "fulfilment.refusal.{$reason}" : 'messages.msg_04'),
                default => self::say('messages.msg_04'),
            }],
            Outcome::CONFLICT => match ($outcome->code) {
                'BD-05' => ['recorded', self::say('fulfilment.refusal.confirmed')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text]);

        return redirect()->to("/proyek/{$project}", 303);
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
            'in' => __('projects.validation.choice'),
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
