<?php

namespace App\Modules\ImportOrchestration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FIN\Actions\ContraOpeningFact;
use App\Modules\ImportOrchestration\Actions\AbortImport;
use App\Modules\ImportOrchestration\Actions\CommitImport;
use App\Modules\ImportOrchestration\Actions\DryRunImport;
use App\Modules\ImportOrchestration\Actions\PrepareImport;
use App\Modules\ImportOrchestration\Actions\SignOffImport;
use App\Modules\ImportOrchestration\Kinds\Kinds;
use App\Modules\ImportOrchestration\Kinds\OpeningCreditKind;
use App\Modules\ImportOrchestration\Queries\OpeningImport;
use App\Modules\INV\Actions\ReverseOpeningMovement;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-058 *Impor Data Awal* (ROUTE-058; ROUTE-272–ROUTE-276) and "Koreksi Data Awal" (ROUTE-213; ROUTE-214): each
 * command answering in place on PG-058 with the batch's state (NV-04), a batch outside the viewer's reach unknown
 * (IP-21); MSG-18 alone for a collision outside scope (DP-18).
 */
final class OpeningImportController extends Controller
{
    public function __construct(private readonly OpeningImport $page) {}

    public function show(Request $request): Response
    {
        $chosen = $request->query('batch');

        return Inertia::render('IMPORT/OpeningImport', $this->page->page(self::actor($request), is_string($chosen) ? $chosen : null));
    }

    public function prepare(Request $request, PrepareImport $prepare): RedirectResponse
    {
        $input = self::validated($request, [
            'command_id' => ['required', 'uuid'], 'jenis' => ['required', Rule::in(Kinds::codes())], 'berkas' => ['required', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BusinessDate::today()], 'perusahaan' => ['required', 'array', 'min:1', 'max:20'], 'perusahaan.*' => ['uuid'],
        ]);
        $companies = array_values(array_map(fn (mixed $id): string => self::text($id), is_array($input['perusahaan']) ? $input['perusahaan'] : []));
        $outcome = $prepare->run(self::text($input['command_id']), self::actor($request), self::text($input['jenis']), self::text($input['berkas']), self::text($input['tanggal']), $companies);

        return $this->answer($outcome, 'prepared', $outcome->kind === Outcome::COMMITTED ? $this->page->publicIdOf($outcome->resultId) : null);
    }

    public function dryRun(Request $request, DryRunImport $dryRun, string $id): RedirectResponse
    {
        $batch = $this->page->lookup(self::actor($request), $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['command_id' => ['required', 'uuid']]);

        return $this->answer($dryRun->run(self::text($input['command_id']), self::actor($request), $batch), 'dry_run', $batch->public_id);
    }

    public function signOff(Request $request, SignOffImport $signOff, string $id): RedirectResponse
    {
        $batch = $this->page->lookup(self::actor($request), $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'bukti' => ['required', 'uuid']]);

        return $this->answer($signOff->run(self::text($input['command_id']), self::actor($request), $batch, self::text($input['bukti'])), 'signed', $batch->public_id);
    }

    public function commit(Request $request, CommitImport $commit, string $id): RedirectResponse
    {
        $batch = $this->page->lookup(self::actor($request), $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['command_id' => ['required', 'uuid']]);

        return $this->answer($commit->run(self::text($input['command_id']), self::actor($request), $batch), 'committing', $batch->public_id);
    }

    public function abort(Request $request, AbortImport $abort, string $id): RedirectResponse
    {
        $batch = $this->page->lookup(self::actor($request), $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'alasan' => ['required', 'string', 'min:3', 'max:1000']]);

        return $this->answer($abort->run(self::text($input['command_id']), self::actor($request), $batch, trim(self::text($input['alasan']))), 'aborted', $batch->public_id);
    }

    /** ROUTE-213: an opening stock movement reversed by its import identity, within what is unconsumed (CM-33). */
    public function reverseStock(Request $request, ReverseOpeningMovement $reverse, string $id): RedirectResponse
    {
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'alasan' => ['required', 'string', 'min:3', 'max:1000'], 'batch' => ['nullable', 'uuid']]);
        $movement = $this->page->openingMovement($id) ?? throw new NotFoundHttpException;
        $outcome = $reverse->run(self::text($input['command_id']), self::actor($request), $movement['company'], $movement['id'], [], trim(self::text($input['alasan'])), BusinessDate::today(), false);

        return $this->answer($outcome, 'corrected', is_string($input['batch'] ?? null) ? $input['batch'] : null);
    }

    /** ROUTE-214: an opening receivable or credit of the batch, named by its row, corrected once its applications are disposed (CM-33). */
    public function correctFinance(Request $request, ContraOpeningFact $contra, string $id): RedirectResponse
    {
        $input = self::validated($request, [
            'command_id' => ['required', 'uuid'], 'baris' => ['nullable', 'integer', 'min:1'], 'alasan' => ['required', 'string', 'min:3', 'max:1000'],
            'nilai' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,2})?$/'], 'ppn' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,2})?$/'], 'jumlah' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,2})?$/'],
        ]);
        $fact = $this->page->openingFact($id, (int) self::text($input['baris'] ?? 0)) ?? throw new NotFoundHttpException;
        $kind = $fact['kind'] === OpeningCreditKind::CODE ? ContraOpeningFact::CREDIT : ContraOpeningFact::RECEIVABLE;
        $outcome = $contra->run(self::text($input['command_id']), self::actor($request), $fact['company'], $kind, $fact['entity'],
            ['sales' => self::nullable($input['nilai'] ?? null), 'tax' => self::nullable($input['ppn'] ?? null), 'amount' => self::nullable($input['jumlah'] ?? null)],
            trim(self::text($input['alasan'])), BusinessDate::today(), false);

        return $this->answer($outcome, 'corrected', $fact['batch']);
    }

    /** The command's answer on PG-058, the batch chosen; a refusal in business words, MSG-18 alone for a collision outside scope. */
    private function answer(Outcome $outcome, string $done, ?string $batch): RedirectResponse
    {
        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("import.answer.{$done}")],
            Outcome::REJECTED => [null, match ($outcome->code) {
                'FL-06' => self::say('import.source.'.self::text($state['reason'] ?? 'unreadable')),
                'C-47' => self::say(($state['reason'] ?? null) === 'file' ? 'import.refusal.same_file' : 'import.refusal.same_source'),
                'NM-03' => self::say('import.refusal.seed', ['jenis' => self::text($state['jenis'] ?? ''), 'periode' => self::text($state['periode'] ?? '')]),
                'L-33' => self::say(($state['reason'] ?? null) === 'signed' ? 'import.refusal.signed' : 'import.refusal.unsigned'),
                'ST-02' => self::say('import.refusal.state'),
                'IMP-06' => self::say('import.refusal.committed'),
                'CM-33' => self::say('import.refusal.correction_'.self::text($state['reason'] ?? 'state')),
                'C-03' => self::say('import.refusal.moved_on', ['jumlah' => self::text($state['available'] ?? '0')]),
                'C-45' => self::say('import.refusal.above_net', ['nilai' => self::text($state['net'] ?? '0')]),
                'C-55' => self::say('import.refusal.not_opening'),
                default => self::say('messages.msg_04'),
            }],
            Outcome::CONFLICT => match ($outcome->code) {
                PrepareImport::OUTSIDE_SCOPE => [PrepareImport::OUTSIDE_SCOPE, self::say('messages.msg_18')],
                'ST-01' => ['stale', self::say('messages.msg_03')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->kind === Outcome::CONFLICT && $cause === PrepareImport::OUTSIDE_SCOPE ? null : $outcome->code, 'cause' => $cause, 'text' => $text]);

        return redirect()->to('/data-master/impor'.(is_string($batch) && $batch !== '' ? '?batch='.$batch : ''), 303);
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only(array_keys(array_filter($rules, fn (string $key): bool => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY))), $rules, [
            'required' => self::say('import.validation.required'), 'uuid' => self::say('import.validation.unknown'), 'in' => self::say('import.validation.unknown'),
            'min' => self::say('import.validation.too_short'), 'max' => self::say('import.validation.too_long'), 'date_format' => self::say('import.validation.date'),
            'before_or_equal' => self::say('import.validation.future'), 'regex' => self::say('import.validation.number'), 'integer' => self::say('import.validation.unknown'),
            'array' => self::say('import.validation.required'), 'string' => self::say('import.validation.required'),
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

    private static function nullable(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
