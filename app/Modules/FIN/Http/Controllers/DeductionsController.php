<?php

namespace App\Modules\FIN\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FIN\Actions\RecordContra;
use App\Modules\FIN\Actions\RecordDeductionSettlement;
use App\Modules\FIN\Actions\ResolveResidualSettlement;
use App\Modules\FIN\Data\SettlementEntry;
use App\Modules\FIN\Queries\DeductionInputs;
use App\Modules\FIN\Queries\DeductionList;
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
 * PG-047's route and commands (ROUTE-047; ROUTE-255; ROUTE-260; ROUTE-263; `DIR-100`): a thin edge over the actions and the query class. A refusal answers in place, the input kept (NV-04); an unknown or out-of-scope record answers alike (AZ-11).
 */
final class DeductionsController extends Controller
{
    private const AMOUNT = 'regex:/^(?!0+(\.0+)?$)\d{1,16}(\.\d{1,2})?$/';

    private const BACK = 'regex:/^\/keuangan\/(potongan|kas-lain)(\?[A-Za-z0-9=&_%-]*)?$/';

    public function __construct(
        private readonly DeductionList $list,
        private readonly DeductionInputs $inputs,
    ) {}

    /** ROUTE-047. */
    public function index(Request $request): Response
    {
        $actor = self::actor($request);
        $for = self::query($request, 'untuk');

        return Inertia::render('FIN/Deductions', [
            ...$this->list->screen($actor, self::query($request, 'perusahaan'), self::query($request, 'tab'), self::query($request, 'setelah'), self::query($request, 'tercatat'), self::query($request, 'pembayaran')),
            'opening' => self::query($request, 'pembayaran'),
            'invoice' => self::query($request, 'invoice'),
            'form' => Inertia::optional(fn () => $for === null ? null : $this->list->form($actor, $for)),
        ]);
    }

    /** ROUTE-255. */
    public function record(Request $request, RecordDeductionSettlement $record): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'pembayaran' => ['required', 'uuid'],
            'klaim' => ['required', 'string', 'size:32'],
            'arah' => ['nullable', Rule::in([SettlementEntry::WITHHELD, SettlementEntry::COLLECTED])],
            'invoice' => ['required', 'uuid'],
            'jumlah' => ['required', self::AMOUNT],
            'bukti' => ['required', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'kembali' => ['nullable', 'string', 'max:300', self::BACK],
        ]);
        $payment = $this->inputs->payment($actor, self::text($input['pembayaran']), RecordDeductionSettlement::CAPABILITY) ?? throw new NotFoundHttpException;
        $claim = $this->inputs->claim($payment['id'], self::text($input['klaim'])) ?? throw ValidationException::withMessages(['klaim' => self::say('projects.validation.unknown')]);
        $direction = self::nullable($input['arah'] ?? null);

        if ($claim['class'] === SettlementEntry::TAX && $direction === null) {
            throw ValidationException::withMessages(['arah' => self::say('deductions.validation.direction')]);
        }

        $invoice = $this->inputs->invoice($payment['company'], self::text($input['invoice'])) ?? throw ValidationException::withMessages(['invoice' => self::say('projects.validation.unknown')]);
        $evidence = $this->inputs->evidence($actor, $payment['company'], self::text($input['bukti'])) ?? throw ValidationException::withMessages(['bukti' => self::say('deductions.validation.evidence')]);
        $entry = new SettlementEntry($invoice, $payment['id'], $claim['class'], $claim['tax_type'], $claim['fee_type'], $claim['class'] === SettlementEntry::TAX ? $direction : null, self::text($input['jumlah']), $evidence, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), trim(self::text($input['alasan'])));
        $outcome = $record->run(self::text($input['command_id']), $actor, $payment['company'], $payment['client'], $entry, array_diff_key($input, ['command_id' => 1, 'kembali' => 1]));

        return $this->answer($outcome, 'recorded', self::nullable($input['kembali'] ?? null) ?? '/keuangan/potongan', self::text($input['tanggal']));
    }

    /** ROUTE-260. */
    public function contra(Request $request, RecordContra $contra): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'jenis' => ['required', Rule::in(RecordContra::KINDS)],
            'catatan' => ['required', 'string', 'max:64'],
            'jumlah' => [Rule::requiredIf(fn (): bool => $request->input('jenis') !== RecordContra::SETTLEMENT), 'nullable', self::AMOUNT],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'kembali' => ['nullable', 'string', 'max:300', self::BACK],
        ]);
        $kind = self::text($input['jenis']);
        $table = ['potongan' => 'deduction_settlements', 'pengeluaran' => 'disbursements', 'beban' => 'expenses'][$kind] ?? '';
        $fact = $this->inputs->fact($actor, $table, self::text($input['catatan']), RecordContra::CAPABILITY) ?? throw new NotFoundHttpException;
        $outcome = $contra->run(self::text($input['command_id']), $actor, $fact['company'], $kind, $fact['id'], self::nullable($input['jumlah'] ?? null), trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'kembali' => 1]));

        return $this->answer($outcome, 'contraed', self::nullable($input['kembali'] ?? null) ?? '/keuangan/potongan', self::text($input['tanggal']));
    }

    /** ROUTE-263. */
    public function resolve(Request $request, ResolveResidualSettlement $resolve, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'pilihan' => ['required', Rule::in(['catat_ulang', 'kontra'])],
            'invoice' => ['required_if:pilihan,catat_ulang', 'nullable', 'uuid'],
            'pembayaran' => ['required_if:pilihan,catat_ulang', 'nullable', 'uuid'],
            'bukti' => ['required_if:pilihan,kontra', 'nullable', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'kembali' => ['nullable', 'string', 'max:300', self::BACK],
        ]);
        $settlement = $this->inputs->fact($actor, 'deduction_settlements', $id, ResolveResidualSettlement::CAPABILITY) ?? throw new NotFoundHttpException;
        $company = $settlement['company'];
        $invoice = null;
        $payment = null;
        $evidence = null;

        if (self::text($input['pilihan']) === 'catat_ulang') {
            $invoice = $this->inputs->invoice($company, self::text($input['invoice'])) ?? throw ValidationException::withMessages(['invoice' => self::say('projects.validation.unknown')]);
            $named = $this->inputs->payment($actor, self::text($input['pembayaran']), ResolveResidualSettlement::CAPABILITY);

            if ($named === null || $named['company'] !== $company) {
                throw ValidationException::withMessages(['pembayaran' => self::say('projects.validation.unknown')]);
            }

            $payment = $named['id'];
        } else {
            $evidence = $this->inputs->evidence($actor, $company, self::text($input['bukti'])) ?? throw ValidationException::withMessages(['bukti' => self::say('deductions.validation.evidence')]);
        }

        $outcome = $resolve->run(self::text($input['command_id']), $actor, $company, $settlement['id'], $invoice, $payment, $evidence, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), [...array_diff_key($input, ['command_id' => 1, 'kembali' => 1]), 'settlement' => $id]);

        return $this->answer($outcome, 'resolved', self::nullable($input['kembali'] ?? null) ?? '/keuangan/potongan', self::text($input['tanggal']));
    }

    /** The command's answer as flash data, the input kept in place; a future date at its field (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, string $to, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("deductions.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome)],
            Outcome::CONFLICT => match ($outcome->code) {
                'BD-08' => ['recorded', self::say('deductions.refusal.duplicate')],
                'ST-07', 'ST-02' => ['stale', self::say('deductions.refusal.stale')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'outstanding' => 1, 'uncited' => 1, 'claimed' => 1, 'room' => 1, 'net' => 1])]);

        return redirect()->to($to, 303);
    }

    private static function refusal(Outcome $outcome): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-14' => self::say('payments.refusal.other_party'),
            'C-15' => $reason === 'voided' ? self::say('payments.refusal.voided') : self::say('deductions.refusal.above_outstanding', ['sisa' => self::money($state['outstanding'] ?? '0')]),
            'C-18' => self::say('deductions.refusal.above_evidence', ['sisa' => self::money($state['uncited'] ?? '0')]),
            'C-22' => match ($reason) {
                'collected_above_tax' => self::say('deductions.refusal.collected_above_tax', ['sisa' => self::money($state['tax'] ?? '0')]),
                'amount' => self::say('deductions.refusal.amount'),
                default => self::say('deductions.refusal.above_claimed', ['sisa' => self::money($state['claimed'] ?? '0')]),
            },
            'C-23' => $reason === 'not_manual' ? self::say('deductions.refusal.expense_not_manual') : self::say('deductions.refusal.above_expensed', ['sisa' => self::money($state['room'] ?? '0')]),
            'C-32', 'C-58' => self::say('deductions.validation.evidence'),
            'C-44' => self::say('deductions.refusal.unbacked'),
            'C-45' => match ($reason) {
                'contraed' => self::say('deductions.refusal.payment_contraed'),
                'transfer_cited' => self::say('deductions.refusal.transfer_cited', ['jumlah' => self::money($state['net'] ?? '0')]),
                'expense_paid' => self::say('deductions.refusal.expense_paid'),
                default => self::say('payments.refusal.above_net', ['jumlah' => self::money($state['net'] ?? '0')]),
            },
            'C-60' => self::say($reason === 'residual' ? 'deductions.refusal.residual' : ($reason === 'no_issuer_evidence' ? 'deductions.validation.evidence' : 'deductions.refusal.not_residual')),
            'BR-FIN-03' => self::say('payments.refusal.not_issued'),
            'BR-FIN-16' => self::say('deductions.refusal.no_rule'),
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
            'required_if' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
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
