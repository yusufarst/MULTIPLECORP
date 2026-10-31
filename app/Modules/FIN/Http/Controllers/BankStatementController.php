<?php

namespace App\Modules\FIN\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Actions\RecordBankStatementLine;
use App\Modules\DOC\Actions\VoidBankStatementLine;
use App\Modules\DOC\Queries\EvidenceList;
use App\Modules\FIN\Actions\CorrectBankCitation;
use App\Modules\FIN\Actions\SupersedeClaimedDeduction;
use App\Modules\FIN\Queries\BankStatementList;
use App\Modules\FIN\Queries\PaymentInputs;
use App\Support\Command\Outcome;
use App\Support\Localization\Formats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-050 *Mutasi Bank* (ROUTE-050; NAV-050; J-FIN-07): `DOC RecordBankStatementLine` (ROUTE-261) and, each with its reason (IP-09), `DOC VoidBankStatementLine · FIN CorrectBankCitation · SupersedeClaimedDeduction` (ROUTE-262). ROUTE-262's `{id}` names what its
 * action corrects: the line's handle for a void, the payment for a citation or a claimed deduction. An unknown record and one the viewer may not see answer alike (AZ-11; SM-01).
 */
final class BankStatementController extends Controller
{
    /** A positive amount: zero and negative money are refused at the field (BR-FIN-06). */
    private const AMOUNT = 'regex:/^(?!0+(\.0+)?$)\d{1,16}(\.\d{1,2})?$/';

    public function __construct(
        private readonly BankStatementList $list,
        private readonly PaymentInputs $inputs,
        private readonly EvidenceList $evidence,
    ) {}

    /** ROUTE-050. */
    public function index(Request $request): Response
    {
        return Inertia::render('FIN/BankStatement', $this->list->screen(self::actor($request), self::query($request, 'perusahaan'), self::query($request, 'tab'), self::query($request, 'setelah')));
    }

    /** ROUTE-261 "Catat Mutasi Bank" with its BANK_STATEMENT evidence; flagged payments it answers take their citation here (QS-12). */
    public function record(Request $request, RecordBankStatementLine $record): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'perusahaan' => ['required', 'uuid'],
            'rekening' => ['required', 'string', 'size:32'],
            'bukti' => ['required', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'jumlah' => ['required', self::AMOUNT],
            'referensi' => ['required', 'string', 'min:1', 'max:120'],
            'pembayaran' => ['nullable', 'array', 'max:50'],
            'pembayaran.*' => ['uuid'],
        ]);
        $company = $this->inputs->companyId($actor, self::text($input['perusahaan']), RecordBankStatementLine::CAPABILITY) ?? throw ValidationException::withMessages(['perusahaan' => self::say('projects.validation.unknown')]);
        $account = $this->inputs->accountId($company, self::text($input['rekening'])) ?? throw ValidationException::withMessages(['rekening' => self::say('projects.validation.unknown')]);
        $evidence = $this->evidence->idOf($actor, self::text($input['bukti']), $company, ['BANK_STATEMENT']) ?? throw ValidationException::withMessages(['bukti' => self::say('payments.validation.statement')]);
        $citing = [];

        foreach (is_array($input['pembayaran'] ?? null) ? $input['pembayaran'] : [] as $payment) {
            $citing[] = $this->inputs->paymentOf($company, self::text($payment)) ?? throw ValidationException::withMessages(['pembayaran' => self::say('projects.validation.unknown')]);
        }

        $outcome = $record->run(self::text($input['command_id']), $actor, $company, $account, $evidence, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), self::text($input['jumlah']), trim(self::text($input['referensi'])), $citing, array_diff_key($input, ['command_id' => 1]));

        return $this->answer($outcome, 'line_recorded', '/keuangan/mutasi-bank?'.http_build_query(['perusahaan' => self::text($input['perusahaan'])]), self::text($input['tanggal']));
    }

    /** ROUTE-262: the line's void, the citation's correction, the claimed deduction's supersession. `cash.correct`. */
    public function correct(Request $request, VoidBankStatementLine $void, CorrectBankCitation $cite, SupersedeClaimedDeduction $supersede, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'aksi' => ['required', Rule::in(['BATAL', 'KUTIPAN', 'POTONGAN'])],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'mutasi' => ['nullable', 'string', 'size:32'],
            'potongan' => ['nullable', 'string', 'size:32'],
            'jumlah' => ['nullable', self::AMOUNT],
            'kembali' => ['nullable', 'string', 'max:300', 'regex:/^\/keuangan\/mutasi-bank(\?[A-Za-z0-9=&_%-]*)?$/'],
        ]);
        $reason = trim(self::text($input['alasan']));
        $command = self::text($input['command_id']);
        $back = is_string($input['kembali'] ?? null) ? $input['kembali'] : '/keuangan/mutasi-bank';

        if ($input['aksi'] === 'BATAL') {
            $line = $this->inputs->correctableLine($actor, $id) ?? throw new NotFoundHttpException;

            return $this->answer($void->run($command, $actor, $line['company'], $line['id'], $reason), 'line_voided', $back, '');
        }

        $payment = $this->inputs->payment($actor, $id) ?? throw new NotFoundHttpException;

        if ($input['aksi'] === 'KUTIPAN') {
            $handle = self::text($input['mutasi'] ?? '');
            $line = $this->inputs->lineId($payment->company_id, $handle) ?? throw ValidationException::withMessages(['mutasi' => self::say('projects.validation.unknown')]);

            return $this->answer($cite->run($command, $actor, $payment, $line, $reason), 'citation_corrected', $back, '');
        }

        $deduction = $this->inputs->deductionId($payment->id, self::text($input['potongan'] ?? '')) ?? throw ValidationException::withMessages(['potongan' => self::say('projects.validation.unknown')]);
        $amount = self::text($input['jumlah'] ?? '');

        if ($amount === '' || preg_match('/^0+(\.0+)?$/', $amount) === 1) {
            throw ValidationException::withMessages(['jumlah' => self::say('projects.validation.required')]);
        }

        return $this->answer($supersede->run($command, $actor, $payment, $deduction, $amount, $reason), 'deduction_superseded', $back, '');
    }

    private function answer(Outcome $outcome, string $done, string $to, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("payments.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome)],
            Outcome::CONFLICT => match ($outcome->code) {
                'C-19' => ['recorded', self::say('payments.refusal.line_exists')],
                'ST-02' => ['stale', self::say('invoices.refusal.state_changed')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'uncited' => 1, 'cited' => 1, 'settled' => 1])]);

        return redirect()->to($to, 303);
    }

    private static function refusal(Outcome $outcome): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-19' => match ($reason) {
                'cited' => self::say('payments.refusal.line_cited', ['jumlah' => self::money($state['cited'] ?? '0')]),
                'same_line' => self::say('payments.refusal.same_line'),
                'above_line' => self::say('payments.refusal.above_line', ['sisa' => self::money($state['uncited'] ?? '0')]),
                default => self::say('payments.refusal.line'),
            },
            'C-22' => $reason === 'unchanged' ? self::say('receivables.refusal.unchanged') : self::say('payments.refusal.below_settled', ['jumlah' => self::money($state['settled'] ?? '0')]),
            'C-32' => self::say('payments.refusal.account'),
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
        $fields = array_values(array_unique(array_map(fn (string $key): string => explode('.', $key)[0], array_keys($rules))));

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

    private static function money(mixed $amount): string
    {
        return Formats::money(self::text($amount));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
