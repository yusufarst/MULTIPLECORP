<?php

namespace App\Modules\PRJ\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PRJ\Actions\CompleteProject;
use App\Modules\PRJ\Actions\ForceCompleteProject;
use App\Modules\PRJ\Queries\ProjectCompletion;
use App\Modules\PRJ\Queries\ProjectWorkspace;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-012's route and commands (ROUTE-012; ROUTE-161; ROUTE-162; NAV-012; J-PRJ-03): a thin edge over the actions and the query class. Success leads to PG-011
 * with the completed state; a refusal answers in place — the unmet conditions named (C-39), a changed snapshot shown again to confirm (ST-04) —; an unknown
 * or out-of-scope project answers alike (AZ-11; CS-04). No payload names a state, a transition kind or a snapshot (WS-02).
 */
final class ProjectCompletionController extends Controller
{
    public function __construct(
        private readonly ProjectWorkspace $workspace,
        private readonly ProjectCompletion $completion,
    ) {}

    /** ROUTE-012. */
    public function show(Request $request, string $id): Response
    {
        $actor = self::actor($request);
        $project = $this->workspace->lookup($actor, $id) ?? throw new NotFoundHttpException;

        return Inertia::render('PRJ/Completion', $this->completion->screen($actor, $project));
    }

    /** ROUTE-161 "Selesaikan Proyek". */
    public function complete(Request $request, CompleteProject $complete, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->lookup($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, []);
        $outcome = $complete->run(self::text($input['command_id']), $actor, $project->id, $project->company_id, $project->public_id);

        return $this->answer($outcome, $project->public_id, 'completed');
    }

    /** ROUTE-162 "Selesaikan Paksa", the Owner's only (OD-12). */
    public function force(Request $request, ForceCompleteProject $force, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->lookup($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'alasan' => ['required', 'string', 'min:3', 'max:1000'],
            'snapshot' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
        ]);
        $outcome = $force->run(self::text($input['command_id']), $actor, $project->id, $project->company_id, $project->public_id, trim(self::text($input['alasan'])), self::text($input['snapshot']));

        return $this->answer($outcome, $project->public_id, 'force_completed');
    }

    /** The command's answer as flash data: PG-011 on success, PG-012 in place otherwise, the unmet conditions or the changed snapshot named (J-PRJ-03). */
    private function answer(Outcome $outcome, string $publicId, string $done): RedirectResponse
    {
        $state = $outcome->state;
        $unmet = array_values(array_filter(is_array($state['unmet'] ?? null) ? $state['unmet'] : [], 'is_string'));
        $conditions = implode(', ', array_map(fn (string $predicate): string => self::say("journey.pred.{$predicate}"), $unmet));
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("completion.answer.{$done}")],
            Outcome::REJECTED => [null, match ($outcome->code) {
                'C-39' => self::say('completion.refusal.unmet', ['syarat' => $conditions]),
                'AZ-01', 'AZ-08', 'OD-12' => self::say('messages.msg_14'),
                default => self::say('messages.msg_04'),
            }],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-04' => ['stale', self::say('completion.refusal.snapshot_changed')],
                'ST-02' => ['stale', self::say('completion.refusal.not_active')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => ['unmet' => $unmet]]);

        return redirect()->to($outcome->kind === Outcome::COMMITTED ? "/proyek/{$publicId}" : "/proyek/{$publicId}/penyelesaian", 303);
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $rules = ['command_id' => ['required', 'uuid'], ...$rules];

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only(array_keys($rules)), $rules, [
            'required' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'string' => __('projects.validation.choice'),
            'regex' => __('projects.validation.unknown'),
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

        return is_int($id) ? $id : throw new AccessDeniedHttpException;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
