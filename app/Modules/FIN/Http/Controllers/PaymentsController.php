<?php

namespace App\Modules\FIN\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Queries\EvidenceList;
use App\Modules\FIN\Actions\ApplyPayment;
use App\Modules\FIN\Actions\ContraPayment;
use App\Modules\FIN\Actions\ReallocateApplication;
use App\Modules\FIN\Actions\RecordPayment;
use App\Modules\FIN\Data\ContraEntry;
use App\Modules\FIN\Data\PaymentEntry;
use App\Modules\FIN\Queries\PaymentForm;
use App\Modules\FIN\Queries\PaymentInputs;
use App\Modules\FIN\Queries\PaymentList;
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
 * PG-045 *Pembayaran* and PG-046 *Catat Pembayaran* (ROUTE-045; ROUTE-046; J-FIN-03): `FIN RecordPayment` (ROUTE-251), `ApplyPayment` (ROUTE-252), `ReallocateApplication` (ROUTE-253) and `ContraPayment` (ROUTE-254), each with its `command_id` (CI-01): validation
 * at the fields (IP-14), a refusal in words with the input kept (IP-03; NV-04), the likely duplicate's matches for the second submission (IP-05; UXS-16). An unknown record and one the viewer may not see answer alike (AZ-11; SM-01).
 */
final class PaymentsController extends Controller
{
    /** A positive amount: zero and negative money are refused at the field (BR-FIN-06). */
    private const AMOUNT = 'regex:/^(?!0+(\.0+)?$)\d{1,16}(\.\d{1,2})?$/';

    public function __construct(
        private readonly PaymentList $list,
        private readonly PaymentForm $form,
        private readonly PaymentInputs $inputs,
        private readonly EvidenceList $evidence,
    ) {}

    /** ROUTE-045. */
    public function index(Request $request): Response
    {
        $actor = self::actor($request);

        return Inertia::render('FIN/Payments', [
            ...$this->list->screen($actor, self::query($request, 'perusahaan'), self::query($request, 'tab'), self::query($request, 'klien'), self::query($request, 'setelah'), self::query($request, 'tercatat')),
            'targets' => Inertia::optional(fn (): array => $this->list->targets($actor, self::query($request, 'sasaran'))),
        ]);
    }

    /** ROUTE-046: `finance.view` is the page's too (DIR-094). */
    public function create(Request $request): Response
    {
        return Inertia::render('FIN/RecordPayment', $this->form->screen(self::actor($request), self::query($request, 'perusahaan'), self::query($request, 'klien'), self::query($request, 'invoice'), self::query($request, 'proyek')));
    }

    /** ROUTE-251 "Catat Pembayaran" after MSG-39 (IP-08), with its same-command applications (AX-18). */
    public function record(Request $request, RecordPayment $record): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'perusahaan' => ['required', 'uuid'],
            'klien' => ['required', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'jumlah' => ['required', self::AMOUNT],
            'metode' => ['required', Rule::in(PaymentEntry::METHODS)],
            'rekening' => ['nullable', 'string', 'size:32'],
            'mutasi' => ['nullable', 'string', 'size:32'],
            'referensi' => ['nullable', 'string', 'max:120'],
            'perantara' => ['nullable', 'string', 'max:200'],
            'bukti' => ['nullable', 'array', 'max:5'],
            'bukti.*' => ['uuid'],
            'alasan_tanpa_bukti' => ['nullable', 'string', 'min:3', 'max:500'],
            'potongan' => ['nullable', 'array', 'max:20'],
            'potongan.*.kelas' => ['required', Rule::in(['FEE', 'TAX'])],
            'potongan.*.pajak' => ['nullable', 'string', 'max:30'],
            'potongan.*.biaya' => ['nullable', 'string', 'max:60'],
            'potongan.*.jumlah' => ['required', self::AMOUNT],
            'penerapan' => ['nullable', 'array', 'max:50'],
            'penerapan.*.invoice' => ['required', 'uuid'],
            'penerapan.*.jumlah' => ['required', self::AMOUNT],
            'kekurangan' => ['nullable', 'array', 'max:20'],
            'kekurangan.*.baris' => ['required', 'string', 'size:32'],
            'kekurangan.*.jumlah' => ['required', self::AMOUNT],
            'pemulihan' => ['nullable', 'uuid'],
            'mengganti' => ['nullable', 'uuid'],
            'transfer' => ['nullable', 'uuid'],
            'acknowledged' => ['nullable', 'array', 'max:50'],
            'acknowledged.*' => ['uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
            'kembali' => ['nullable', 'string', 'max:300', 'regex:/^\/keuangan\/pembayaran\/baru(\?[A-Za-z0-9=&_%-]*)?$/'],
        ]);
        $company = $this->inputs->companyId($actor, self::text($input['perusahaan']), RecordPayment::CAPABILITY) ?? throw ValidationException::withMessages(['perusahaan' => self::say('projects.validation.unknown')]);
        $client = $this->inputs->clientId(self::text($input['klien'])) ?? throw ValidationException::withMessages(['klien' => self::say('projects.validation.unknown')]);
        $method = self::text($input['metode']);
        $account = self::nullable($input['rekening'] ?? null);
        $accountId = null;

        if ($method !== 'CASH') {
            if ($account === null) {
                throw ValidationException::withMessages(['rekening' => self::say('projects.validation.required')]);
            }

            $accountId = $this->inputs->accountId($company, $account) ?? throw ValidationException::withMessages(['rekening' => self::say('projects.validation.unknown')]);
        }

        $line = self::nullable($input['mutasi'] ?? null);
        $lineId = $line === null ? null : ($this->inputs->lineId($company, $line) ?? throw ValidationException::withMessages(['mutasi' => self::say('projects.validation.unknown')]));
        $evidence = [];

        foreach (self::list($input['bukti'] ?? []) as $proof) {
            $evidence[] = $this->evidence->idOf($actor, self::text($proof), $company, PaymentEntry::PROOF) ?? throw ValidationException::withMessages(['bukti' => self::say('payments.validation.proof')]);
        }

        $reason = self::nullable($input['alasan_tanpa_bukti'] ?? null);

        if ($evidence === [] && $lineId === null && $reason === null) {
            throw ValidationException::withMessages(['alasan_tanpa_bukti' => self::say('payments.validation.no_proof_reason')]);
        }

        $applications = [];
        $named = self::list($input['penerapan'] ?? []);
        $ids = $this->inputs->invoiceIds($actor, array_values(array_map(fn (mixed $row): string => strtolower(self::text(is_array($row) ? ($row['invoice'] ?? '') : '')), $named)));

        foreach ($named as $index => $row) {
            $key = strtolower(self::text(is_array($row) ? ($row['invoice'] ?? '') : ''));
            $invoice = $ids[$key] ?? throw ValidationException::withMessages(["penerapan.{$index}.invoice" => self::say('projects.validation.unknown')]);

            if (isset($applications[$invoice])) {
                throw ValidationException::withMessages(["penerapan.{$index}.invoice" => self::say('payments.validation.invoice_twice')]);
            }

            $applications[$invoice] = self::text(is_array($row) ? ($row['jumlah'] ?? '') : '');
        }

        $claimed = [];

        foreach (self::list($input['potongan'] ?? []) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $class = self::text($row['kelas'] ?? '');
            $tax = $class === 'TAX' ? ($this->inputs->taxTypeId(self::text($row['pajak'] ?? '')) ?? throw ValidationException::withMessages(["potongan.{$index}.pajak" => self::say('projects.validation.required')])) : null;
            $fee = $class === 'FEE' ? (self::nullable($row['biaya'] ?? null) ?? throw ValidationException::withMessages(["potongan.{$index}.biaya" => self::say('projects.validation.required')])) : null;
            $claimed[] = ['class' => $class, 'tax_type' => $tax, 'fee_type' => $fee, 'amount' => self::text($row['jumlah'] ?? '')];
        }

        $resolves = [];

        foreach (self::list($input['kekurangan'] ?? []) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $ended = $this->inputs->endedApplicationId($company, $client, self::text($row['baris'] ?? '')) ?? throw ValidationException::withMessages(["kekurangan.{$index}.baris" => self::say('projects.validation.unknown')]);
            $resolves[$ended] = self::text($row['jumlah'] ?? '');
        }

        $recovery = self::nullable($input['pemulihan'] ?? null);
        $recoveryId = $recovery === null ? null : ($this->inputs->invoiceIds($actor, [strtolower($recovery)])[strtolower($recovery)] ?? throw ValidationException::withMessages(['pemulihan' => self::say('projects.validation.unknown')]));
        $replacing = self::nullable($input['mengganti'] ?? null);
        $replacingId = $replacing === null ? null : ($this->inputs->paymentOf($company, $replacing) ?? throw ValidationException::withMessages(['mengganti' => self::say('projects.validation.unknown')]));
        $transfer = self::nullable($input['transfer'] ?? null);
        $transferId = $transfer === null ? null : ($this->inputs->transferId($transfer) ?? throw ValidationException::withMessages(['transfer' => self::say('projects.validation.unknown')]));
        $acknowledged = array_values(array_map(fn (mixed $id): string => strtolower(self::text($id)), self::list($input['acknowledged'] ?? [])));
        $entry = new PaymentEntry($company, $client, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), self::text($input['jumlah']), $method, $accountId, $lineId, self::nullable($input['referensi'] ?? null), self::nullable($input['perantara'] ?? null),
            $evidence, $reason, $claimed, $applications, $resolves, $recoveryId, $transferId, $replacingId, $acknowledged, self::nullable($input['reason'] ?? null));
        $outcome = $record->run(self::text($input['command_id']), $actor, $entry, array_diff_key($input, ['command_id' => 1, 'kembali' => 1]));
        $payment = $outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null ? $this->inputs->paymentPublicId($outcome->resultId) : null;

        if ($payment !== null) {
            Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => null, 'text' => self::say('payments.answer.recorded', ['jumlah' => self::money($input['jumlah'])])]);

            return redirect()->to('/keuangan/pembayaran?'.http_build_query(['perusahaan' => self::text($input['perusahaan']), 'tercatat' => $payment]), 303);
        }

        return $this->answer($outcome, 'recorded', self::nullable($input['kembali'] ?? null) ?? '/keuangan/pembayaran/baru?'.http_build_query(['perusahaan' => self::text($input['perusahaan']), 'klien' => self::text($input['klien'])]), self::text($input['tanggal']), self::text($input['perusahaan']));
    }

    /** ROUTE-252 "Terapkan": on PG-045, and PG-048 where TASK-053 places it. */
    public function apply(Request $request, ApplyPayment $apply, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $source = $this->inputs->source($actor, strtolower($id)) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'penerapan' => ['nullable', 'array', 'max:50'],
            'penerapan.*.invoice' => ['required', 'uuid'],
            'penerapan.*.jumlah' => ['required', self::AMOUNT],
            'kekurangan' => ['nullable', 'array', 'max:20'],
            'kekurangan.*.baris' => ['required', 'string', 'size:32'],
            'kekurangan.*.jumlah' => ['required', self::AMOUNT],
            'kembali' => ['nullable', 'string', 'max:300', 'regex:/^\/keuangan\/(pembayaran|kredit-pelanggan)(\?[A-Za-z0-9=&_%-]*)?$/'],
        ]);
        $applications = [];
        $named = self::list($input['penerapan'] ?? []);
        $ids = $this->inputs->invoiceIds($actor, array_values(array_map(fn (mixed $row): string => strtolower(self::text(is_array($row) ? ($row['invoice'] ?? '') : '')), $named)));

        foreach ($named as $index => $row) {
            $key = strtolower(self::text(is_array($row) ? ($row['invoice'] ?? '') : ''));
            $invoice = $ids[$key] ?? throw ValidationException::withMessages(["penerapan.{$index}.invoice" => self::say('projects.validation.unknown')]);

            if (isset($applications[$invoice])) {
                throw ValidationException::withMessages(["penerapan.{$index}.invoice" => self::say('payments.validation.invoice_twice')]);
            }

            $applications[$invoice] = self::text(is_array($row) ? ($row['jumlah'] ?? '') : '');
        }

        $resolves = [];

        foreach (self::list($input['kekurangan'] ?? []) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $ended = $this->inputs->endedApplicationId($source['company'], $source['client'], self::text($row['baris'] ?? '')) ?? throw ValidationException::withMessages(["kekurangan.{$index}.baris" => self::say('projects.validation.unknown')]);
            $resolves[$ended] = self::text($row['jumlah'] ?? '');
        }

        if ($applications === [] && $resolves === []) {
            throw ValidationException::withMessages(['penerapan' => self::say('projects.validation.required')]);
        }

        $outcome = $apply->run(self::text($input['command_id']), $actor, $source, $applications, $resolves, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'kembali' => 1]));

        return $this->answer($outcome, 'applied', self::nullable($input['kembali'] ?? null) ?? '/keuangan/pembayaran', self::text($input['tanggal']), null);
    }

    /** ROUTE-253 "Pindahkan Penerapan" with its reason (IP-09; CM-21). */
    public function reallocate(Request $request, ReallocateApplication $reallocate, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $payment = $this->inputs->payment($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'penerapan' => ['required', 'string', 'size:32'],
            'invoice' => ['required', 'uuid'],
            'jumlah' => ['required', self::AMOUNT],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'potongan' => ['nullable', 'array', 'max:50'],
            'potongan.*.baris' => ['required', 'string', 'size:32'],
            'potongan.*.aksi' => ['required', Rule::in(['PINDAH', 'TETAP'])],
            'kembali' => ['nullable', 'string', 'max:300', 'regex:/^\/keuangan\/pembayaran(\?[A-Za-z0-9=&_%-]*)?$/'],
        ]);
        $application = $this->inputs->applicationId($payment->id, self::text($input['penerapan'])) ?? throw ValidationException::withMessages(['penerapan' => self::say('projects.validation.unknown')]);
        $invoiceKey = strtolower(self::text($input['invoice']));
        $target = $this->inputs->invoiceIds($actor, [$invoiceKey])[$invoiceKey] ?? throw ValidationException::withMessages(['invoice' => self::say('projects.validation.unknown')]);
        $settlements = [];

        foreach (self::list($input['potongan'] ?? []) as $row) {
            $row = is_array($row) ? $row : [];
            $settlement = $this->inputs->settlementId($payment->id, self::text($row['baris'] ?? ''));

            // One the list no longer holds is the command's stale answer (ST-07), never a silent drop.
            if ($settlement !== null) {
                $settlements[$settlement] = self::text($row['aksi'] ?? '') === 'PINDAH';
            }
        }

        $outcome = $reallocate->run(self::text($input['command_id']), $actor, $payment, $application, $target, self::text($input['jumlah']), $settlements, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), trim(self::text($input['alasan'])), array_diff_key($input, ['command_id' => 1, 'kembali' => 1]));

        return $this->answer($outcome, 'reallocated', self::nullable($input['kembali'] ?? null) ?? '/keuangan/pembayaran', self::text($input['tanggal']), null);
    }

    /** ROUTE-254 "Batalkan Catatan Pembayaran (Kontra)" with its reason (IP-09; CM-19; ST-07). */
    public function contra(Request $request, ContraPayment $contra, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $payment = $this->inputs->payment($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'jumlah' => ['required', self::AMOUNT],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'pengganti' => ['nullable', 'uuid'],
            'penerapan' => ['nullable', 'array', 'max:200'],
            'penerapan.*.baris' => ['required', 'string', 'size:32'],
            'penerapan.*.aksi' => ['required', Rule::in([ContraEntry::KEEP, ContraEntry::RELEASE, ContraEntry::MOVE, ContraEntry::SHORTFALL])],
            'penerapan.*.jumlah' => ['nullable', self::AMOUNT],
            'potongan' => ['nullable', 'array', 'max:200'],
            'potongan.*.baris' => ['required', 'string', 'size:32'],
            'potongan.*.aksi' => ['required', Rule::in([ContraEntry::KEEP, ContraEntry::MOVE, ContraEntry::RESIDUAL])],
            'kembali' => ['nullable', 'string', 'max:300', 'regex:/^\/keuangan\/pembayaran(\?[A-Za-z0-9=&_%-]*)?$/'],
        ]);
        $applications = [];

        foreach (self::list($input['penerapan'] ?? []) as $row) {
            $row = is_array($row) ? $row : [];
            $application = $this->inputs->applicationId($payment->id, self::text($row['baris'] ?? ''));

            if ($application !== null) {
                $applications[$application] = ['action' => self::text($row['aksi'] ?? ''), 'amount' => self::nullable($row['jumlah'] ?? null)];
            }
        }

        $settlements = [];

        foreach (self::list($input['potongan'] ?? []) as $row) {
            $row = is_array($row) ? $row : [];
            $settlement = $this->inputs->settlementId($payment->id, self::text($row['baris'] ?? ''));

            if ($settlement !== null) {
                $settlements[$settlement] = self::text($row['aksi'] ?? '');
            }
        }

        $replacing = self::nullable($input['pengganti'] ?? null);
        $replacingId = $replacing === null ? null : ($this->inputs->paymentOf($payment->company_id, $replacing) ?? throw ValidationException::withMessages(['pengganti' => self::say('projects.validation.unknown')]));
        $entry = new ContraEntry(self::text($input['jumlah']), trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $replacingId, $applications, $settlements);
        $outcome = $contra->run(self::text($input['command_id']), $actor, $payment, $entry, array_diff_key($input, ['command_id' => 1, 'kembali' => 1]));

        return $this->answer($outcome, 'contraed', self::nullable($input['kembali'] ?? null) ?? '/keuangan/pembayaran', self::text($input['tanggal']), null);
    }

    /** The command's answer as flash data, the input kept in place; a future date and an unknown proof at their fields (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, string $to, string $sent, ?string $company): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'L-29' && ($outcome->state['reason'] ?? null) === 'no_proof') {
            throw ValidationException::withMessages(['alasan_tanpa_bukti' => self::say('payments.validation.no_proof_reason')]);
        }

        $state = $outcome->state;
        $matches = $outcome->code === 'L-22' ? self::matches($state['matches'] ?? []) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("payments.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome, $matches ?? [])],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-07' => ['stale', self::say('payments.refusal.stale_list')],
                'ST-02' => ['stale', self::say('invoices.refusal.state_changed')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                'C-59' => ['recorded', self::say('payments.refusal.already_rerecorded')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => [
            ...array_intersect_key($state, ['reason' => 1, 'outstanding' => 1, 'capacity' => 1, 'uncited' => 1, 'applications' => 1, 'settlements' => 1, 'override' => 1]),
            ...($matches === null ? [] : ['matches' => $matches]),
        ]]);

        return redirect()->to($to, 303);
    }

    /**
     * @param  mixed  $matches  BD-02's rows
     * @return list<array{id: string, label: string, href: string}>
     */
    private static function matches(mixed $matches): array
    {
        $rows = [];

        foreach (is_array($matches) ? $matches : [] as $match) {
            if (is_array($match)) {
                $rows[] = ['id' => self::text($match['id'] ?? ''), 'label' => Formats::dateLong(self::text($match['date'] ?? '')).' · '.self::money($match['amount'] ?? '0').' · '.self::text($match['client'] ?? '').(is_string($match['reference'] ?? null) ? ' · '.$match['reference'] : ''), 'href' => '/keuangan/pembayaran?tercatat='.self::text($match['id'] ?? '')];
            }
        }

        return $rows;
    }

    /** @param  list<array{id: string, label: string, href: string}>  $matches */
    private static function refusal(Outcome $outcome, array $matches): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'L-22' => self::say(($state['override'] ?? false) === true ? 'messages.msg_15' : 'payments.refusal.duplicate_no_authority', ['daftar_catatan_yang_cocok' => implode('; ', array_column($matches, 'label'))]),
            'C-13' => match ($reason) {
                'undisposed' => self::say('payments.refusal.undisposed', ['jumlah' => self::money($state['short'] ?? '0')]),
                'above_application' => self::say('payments.refusal.above_application', ['jumlah' => self::money($state['application'] ?? '0')]),
                default => self::say('payments.refusal.above_capacity', ['sisa' => self::money($state['capacity'] ?? '0')]),
            },
            'C-14' => self::say('payments.refusal.other_party'),
            'C-15' => $reason === 'voided' ? self::say('payments.refusal.voided') : self::say('payments.refusal.above_outstanding', ['invoice' => self::text($state['invoice'] ?? ''), 'sisa' => self::money($state['outstanding'] ?? '0')]),
            'C-19' => match ($reason) {
                'cited' => self::say('payments.refusal.line_cited', ['jumlah' => self::money($state['cited'] ?? '0')]),
                'same_line' => self::say('payments.refusal.same_line'),
                'above_line' => self::say('payments.refusal.above_line', ['sisa' => self::money($state['uncited'] ?? '0')]),
                default => self::say('payments.refusal.line'),
            },
            'C-22' => $reason === 'unchanged' ? self::say('receivables.refusal.unchanged') : ($reason === 'above_claimed' ? self::say('payments.refusal.above_claimed') : self::say('payments.refusal.below_settled', ['jumlah' => self::money($state['settled'] ?? '0')])),
            'C-32' => self::say('payments.refusal.account'),
            'C-44' => $reason === 'above_shortfall' ? self::say('payments.refusal.above_shortfall', ['jumlah' => self::money($state['shortfall'] ?? '0')]) : self::say('payments.refusal.shortfall_with_money', ['jumlah' => self::money($state['applied'] ?? '0')]),
            'C-45' => $reason === 'settlements' ? self::say('payments.refusal.settlements', ['jumlah' => self::money($state['settled'] ?? '0')]) : self::say('payments.refusal.above_net', ['jumlah' => self::money($state['net'] ?? '0')]),
            'L-17' => self::say('payments.refusal.not_written_off'),
            'L-29' => self::say($reason === 'company_issued' ? 'payments.refusal.company_issued' : 'payments.validation.proof'),
            'L-46' => self::say($reason === 'not_rerecorded' ? 'payments.refusal.not_rerecorded' : 'payments.refusal.refund_consumed'),
            'BR-FIN-03' => self::say('payments.refusal.not_issued'),
            'BR-FIN-07' => self::say('payments.refusal.transfer_capacity', ['sisa' => self::money($state['left'] ?? '0')]),
            'CM-21' => self::say('payments.refusal.same_invoice'),
            'XL-5' => self::say('payments.refusal.transfer_scope'),
            'C-59' => self::say('payments.refusal.rerecords'),
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

    /** @return array<int|string, mixed> */
    private static function list(mixed $value): array
    {
        return is_array($value) ? $value : [];
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

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
