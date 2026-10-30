<?php

namespace App\Modules\FIN\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Queries\EvidenceList;
use App\Modules\FIN\Actions\BillInvoice;
use App\Modules\FIN\Actions\ClearDisputeHold;
use App\Modules\FIN\Actions\CorrectBilling;
use App\Modules\FIN\Actions\SetDisputeHold;
use App\Modules\FIN\Actions\SupersedeWriteOff;
use App\Modules\FIN\Actions\WriteOffReceivable;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Queries\InvoiceDetail;
use App\Modules\FIN\Queries\InvoiceInputs;
use App\Modules\FIN\Queries\ReceivableList;
use App\Support\Command\Outcome;
use App\Support\Localization\Formats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-044 *Piutang* (ROUTE-044; NAV-044; SCR-40) and PG-043's billing, dispute and write-off controls (J-FIN-02; J-FIN-05): `FIN BillInvoice` (ROUTE-243), `CorrectBilling`
 * (ROUTE-244), `SetDisputeHold · ClearDisputeHold` (ROUTE-246), `WriteOffReceivable` (ROUTE-247) and `SupersedeWriteOff` (ROUTE-248), each with its `command_id` (CI-01):
 * validation at the fields (IP-14), a refusal in words with the input kept (IP-03; NV-04). An unknown invoice and one the viewer may not see answer alike (AZ-11).
 */
final class ReceivablesController extends Controller
{
    private const LIST = '/^\/keuangan\/piutang(\?[A-Za-z0-9=&_%-]*)?$/';

    private const AMOUNT = 'regex:/^\d{1,16}(\.\d{1,2})?$/';

    public function __construct(
        private readonly ReceivableList $list,
        private readonly InvoiceDetail $detail,
        private readonly InvoiceInputs $inputs,
        private readonly EvidenceList $evidence,
    ) {}

    /** ROUTE-044. */
    public function index(Request $request): Response
    {
        return Inertia::render('FIN/Receivables', $this->list->screen(self::actor($request), self::query($request, 'perusahaan'), self::query($request, 'tab'), self::query($request, 'klien'), self::query($request, 'setelah')));
    }

    /** ROUTE-243 "Tagihkan" after its confirmation (IP-08; MSG-39); from PG-044 back to its filters (IP-18). */
    public function bill(Request $request, BillInvoice $bill, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [...self::dates(), 'kembali' => ['nullable', 'string', 'max:300', 'regex:'.self::LIST]]);
        $outcome = $bill->run(self::text($input['command_id']), $actor, $invoice, self::text($input['tanggal']), self::text($input['jatuh_tempo']), $request->boolean('tanggal_otomatis'));
        $back = self::nullable($input['kembali'] ?? null);

        return $this->answer($outcome, 'billed', $back ?? "/keuangan/invoice/{$invoice->public_id}", self::text($input['tanggal']));
    }

    /** ROUTE-244 "Koreksi Penagihan" with its reason (IP-09; CM-25). */
    public function correct(Request $request, CorrectBilling $correct, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [...self::dates(), 'bukti' => ['required', 'uuid'], 'alasan' => ['required', 'string', 'min:3', 'max:500']]);
        $evidence = $this->evidence->idOf($actor, self::text($input['bukti']), $invoice->company_id, InvoiceCorrection::EVIDENCE)
            ?? throw ValidationException::withMessages(['bukti' => self::say('invoices.validation.evidence')]);
        $outcome = $correct->run(self::text($input['command_id']), $actor, $invoice, $evidence, self::text($input['tanggal']), self::text($input['jatuh_tempo']), $request->boolean('tanggal_otomatis'), trim(self::text($input['alasan'])));

        return $this->answer($outcome, 'billing_corrected', "/keuangan/invoice/{$invoice->public_id}", self::text($input['tanggal']));
    }

    /** ROUTE-246 "Tandai Sengketa" and "Cabut Sengketa", each with its reason (IP-09). */
    public function hold(Request $request, SetDisputeHold $set, ClearDisputeHold $clear, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['aksi' => ['required', Rule::in(['TANDAI', 'CABUT'])], 'alasan' => ['required', 'string', 'min:3', 'max:500']]);
        $reason = trim(self::text($input['alasan']));
        $setting = self::text($input['aksi']) === 'TANDAI';
        $outcome = $setting ? $set->run(self::text($input['command_id']), $actor, $invoice, $reason) : $clear->run(self::text($input['command_id']), $actor, $invoice, $reason);

        return $this->answer($outcome, $setting ? 'hold_set' : 'hold_cleared', "/keuangan/invoice/{$invoice->public_id}", '');
    }

    /** ROUTE-247 "Hapuskan Piutang", the Owner's, with its confirmation and reason (IP-08; IP-09; OD-11). */
    public function writeOff(Request $request, WriteOffReceivable $writeOff, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'jumlah' => ['required', self::AMOUNT],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'alasan_kredit' => ['nullable', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $outcome = $writeOff->run(self::text($input['command_id']), $actor, $invoice, self::text($input['jumlah']), trim(self::text($input['alasan'])), self::nullable($input['alasan_kredit'] ?? null), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($outcome, 'written_off', "/keuangan/invoice/{$invoice->public_id}", self::text($input['tanggal']));
    }

    /** ROUTE-248 "Terapkan dan Cabut Penghapusan", the Owner's, with its reason (IP-09; L-17). */
    public function supersede(Request $request, SupersedeWriteOff $supersede, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'penghapusan' => ['required', 'string', 'size:32'],
            'pembayaran' => ['required', 'uuid'],
            'jumlah' => ['required', self::AMOUNT],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $writeoff = $this->inputs->writeoffId($invoice->id, self::text($input['penghapusan'])) ?? throw ValidationException::withMessages(['penghapusan' => self::say('receivables.validation.writeoff')]);
        $payment = $this->inputs->paymentIds($invoice->company_id, [self::text($input['pembayaran'])])[0] ?? throw ValidationException::withMessages(['pembayaran' => self::say('invoices.validation.payment')]);
        $outcome = $supersede->run(self::text($input['command_id']), $actor, $invoice, $writeoff, $payment, self::text($input['jumlah']), trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($outcome, 'superseded', "/keuangan/invoice/{$invoice->public_id}", self::text($input['tanggal']));
    }

    /** @return array<string, list<mixed>> The billing date and the entered due date (no payment-terms engine; WF-FIN-02). */
    private static function dates(): array
    {
        return [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'jatuh_tempo' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal'],
        ];
    }

    /** The command's answer as flash data; a future date and a missing evidence at their fields (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, string $to, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;

        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'CM-25' && ($state['reason'] ?? null) === 'evidence') {
            throw ValidationException::withMessages(['bukti' => self::say('invoices.refusal.evidence')]);
        }

        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("receivables.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome)],
            Outcome::CONFLICT => match ($outcome->code) {
                'BD-07' => ['recorded', self::say('receivables.refusal.already_billed', ['tanggal' => Formats::dateLong(self::text($state['billed_at'] ?? '')), 'jatuh' => is_string($state['due_date'] ?? null) ? Formats::dateLong($state['due_date']) : '—'])],
                'ST-02' => ['stale', self::say('invoices.refusal.state_changed')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'outstanding' => 1, 'credit' => 1, 'billed_at' => 1, 'due_date' => 1, 'standing' => 1, 'capacity' => 1])]);

        return redirect()->to($to, 303);
    }

    private static function refusal(Outcome $outcome): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'BR-FIN-02' => self::say('invoices.refusal.not_issued'),
            'BR-FIN-08' => self::say('receivables.refusal.no_receivable'),
            'CM-25' => self::say($reason === 'unchanged' ? 'receivables.refusal.unchanged' : 'receivables.refusal.not_billed'),
            'C-42' => self::say($reason === 'no_hold' ? 'receivables.refusal.no_hold' : 'receivables.refusal.hold_open'),
            'C-15' => $reason === 'above_recovered'
                ? self::say('receivables.refusal.above_recovered', ['penghapusan' => self::money($state['standing'] ?? '0'), 'kredit' => self::money($state['capacity'] ?? '0')])
                : self::say('receivables.refusal.above_outstanding', ['sisa' => self::money($state['outstanding'] ?? '0')]),
            'L-17' => $reason === 'not_recovery'
                ? self::say('receivables.refusal.not_recovery')
                : self::say('receivables.refusal.credit', ['kredit' => self::money($state['credit'] ?? '0')]),
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

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only(array_keys($rules)), $rules, [
            'required' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
            'after_or_equal' => __('receivables.validation.due_before_billing'),
            'regex' => __('projects.validation.number'),
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

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }

    private static function money(mixed $amount): string
    {
        return Formats::money(self::text($amount));
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
