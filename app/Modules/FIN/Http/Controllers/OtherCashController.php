<?php

namespace App\Modules\FIN\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CST\Actions\RecordExpense;
use App\Modules\FIN\Actions\RecordDisbursement;
use App\Modules\FIN\Actions\RecordOtherCashIn;
use App\Modules\FIN\Data\OtherCash;
use App\Modules\FIN\Queries\OtherCashList;
use App\Support\Command\Outcome;
use App\Support\Localization\Formats;
use App\Support\RowHandle;
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
 * PG-049's route and commands (ROUTE-049; ROUTE-257–ROUTE-259; NAV-049): a thin edge over the actions and the query class. A refusal answers in place, the input kept (NV-04); a
 * reference of another company or out of scope answers like an unknown one (CS-04; AZ-11; MSG-09).
 */
final class OtherCashController extends Controller
{
    private const AMOUNT = 'regex:/^(?!0+(\.0+)?$)\d{1,16}(\.\d{1,2})?$/';

    private const PAGE = '/keuangan/kas-lain';

    private const BACK = 'regex:/^\/keuangan\/kas-lain(\?[A-Za-z0-9=&_%-]*)?$/';

    public function __construct(private readonly OtherCashList $list) {}

    /** ROUTE-049. */
    public function index(Request $request): Response
    {
        $actor = self::actor($request);
        $for = self::query($request, 'untuk');

        return Inertia::render('FIN/OtherCash', [
            ...$this->list->screen($actor, self::query($request, 'perusahaan'), self::query($request, 'tab'), self::query($request, 'setelah'), self::query($request, 'tercatat')),
            'form' => Inertia::optional(fn () => $for === null ? null : $this->list->form($actor, $for)),
        ]);
    }

    /** ROUTE-257 "Catat Beban". */
    public function expense(Request $request, RecordExpense $record): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'perusahaan' => ['required', 'uuid'],
            'kategori' => ['required', 'string', 'min:2', 'max:100'],
            'jumlah' => ['required', self::AMOUNT],
            'proyek' => ['nullable', 'uuid'],
            'tagihan' => ['nullable', 'uuid'],
            'bukti' => ['nullable', 'array', 'max:10'],
            'bukti.*' => ['uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'kembali' => ['nullable', 'string', 'max:300', self::BACK],
        ]);
        $company = $this->list->companyOf($actor, self::text($input['perusahaan']), RecordExpense::CAPABILITY);

        // RG-06: the expense is recorded with the cost side in view.
        if ($company === null || ! $this->list->allows($actor, 'cost.view', $company)) {
            throw new NotFoundHttpException;
        }

        $project = self::optional($input, 'proyek', fn (string $id): ?int => $this->list->publicOf('projects', $company, $id));
        $bill = self::optional($input, 'tagihan', fn (string $id): ?int => $this->list->publicOf('evidence_documents', $company, $id));
        $evidence = $this->evidence($input, $company);
        $outcome = $record->run(self::text($input['command_id']), $actor, $company, $project, trim(self::text($input['kategori'])), self::text($input['jumlah']), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $bill, $evidence,
            array_diff_key($input, ['command_id' => 1, 'kembali' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'expense', self::back($input, 'beban'), self::text($input['tanggal']));
    }

    /** ROUTE-258 "Catat Pengeluaran Kas". */
    public function disbursement(Request $request, RecordDisbursement $record): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'perusahaan' => ['required', 'uuid'],
            'jenis' => ['required', Rule::in(OtherCash::DISBURSEMENT_KINDS)],
            'jumlah' => ['required', self::AMOUNT],
            'rekening' => ['required', 'string', 'size:32'],
            'pembelian' => ['required_if:jenis,PURCHASE_PAYMENT', 'nullable', 'uuid'],
            'beban' => ['required_if:jenis,EXPENSE_PAYMENT', 'nullable', 'string', 'size:32'],
            'bukti_pajak' => ['required_if:jenis,TAX_REMITTANCE', 'nullable', 'uuid'],
            'jenis_pajak' => ['required_if:jenis,TAX_REMITTANCE', 'nullable', 'string', 'max:40'],
            'bukti' => ['nullable', 'array', 'max:10'],
            'bukti.*' => ['uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'kembali' => ['nullable', 'string', 'max:300', self::BACK],
        ]);
        $company = $this->list->companyOf($actor, self::text($input['perusahaan']), RecordDisbursement::CAPABILITY) ?? throw new NotFoundHttpException;
        $kind = self::text($input['jenis']);
        $account = $this->list->accountOf($company, self::text($input['rekening'])) ?? throw ValidationException::withMessages(['rekening' => self::say('projects.validation.unknown')]);
        $reference = [
            'purchase' => $kind === 'PURCHASE_PAYMENT' ? self::optional($input, 'pembelian', fn (string $id): ?int => $this->list->publicOf('purchases', $company, $id)) : null,
            'expense' => $kind === 'EXPENSE_PAYMENT' ? self::optional($input, 'beban', fn (string $handle): ?int => $this->list->allows($actor, 'cost.view', $company) ? $this->list->handleOf('expenses', $company, $handle) : null) : null,
            'tax_evidence' => $kind === 'TAX_REMITTANCE' ? self::optional($input, 'bukti_pajak', fn (string $id): ?int => $this->list->publicOf('evidence_documents', $company, $id)) : null,
            'tax_type' => $kind === 'TAX_REMITTANCE' ? self::optional($input, 'jenis_pajak', fn (string $code): ?int => $this->list->taxTypeOf($code)) : null,
        ];
        $evidence = $this->evidence($input, $company);
        $outcome = $record->run(self::text($input['command_id']), $actor, $company, $kind, self::text($input['jumlah']), $account, $reference, $evidence, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'),
            array_diff_key($input, ['command_id' => 1, 'kembali' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'disbursement', self::back($input, 'pengeluaran'), self::text($input['tanggal']));
    }

    /** ROUTE-259 "Catat Kas Masuk Lain". */
    public function cashIn(Request $request, RecordOtherCashIn $record): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'perusahaan' => ['required', 'uuid'],
            'kategori' => ['required', Rule::in(OtherCash::CATEGORIES)],
            'jumlah' => ['required', self::AMOUNT],
            'rekening' => ['required', 'string', 'size:32'],
            'pembelian' => ['nullable', 'uuid'],
            'retur' => ['nullable', 'uuid'],
            'transfer' => ['required_if:kategori,INTERCOMPANY_TRANSFER_IN', 'nullable', 'uuid'],
            'mutasi' => ['nullable', 'string', 'size:32'],
            'bukti' => ['nullable', 'array', 'max:10'],
            'bukti.*' => ['uuid'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'kembali' => ['nullable', 'string', 'max:300', self::BACK],
        ]);
        $company = $this->list->companyOf($actor, self::text($input['perusahaan']), RecordOtherCashIn::CAPABILITY) ?? throw new NotFoundHttpException;
        $category = self::text($input['kategori']);
        $account = $this->list->accountOf($company, self::text($input['rekening'])) ?? throw ValidationException::withMessages(['rekening' => self::say('projects.validation.unknown')]);
        $refund = $category === 'SUPPLIER_REFUND';
        $reference = [
            'purchase' => $refund ? self::optional($input, 'pembelian', fn (string $id): ?int => $this->list->publicOf('purchases', $company, $id)) : null,
            'case' => $refund ? self::optional($input, 'retur', fn (string $id): ?int => $this->list->publicOf('correction_cases', $company, $id)) : null,
            'transfer' => $category === 'INTERCOMPANY_TRANSFER_IN' ? self::optional($input, 'transfer', fn (string $id): ?int => $this->list->transferOf($id)) : null,
            'line' => self::optional($input, 'mutasi', fn (string $handle): ?int => $this->list->handleOf('bank_statement_lines', $company, $handle)),
        ];
        $evidence = $this->evidence($input, $company);
        $note = $input['catatan'] ?? null;
        $outcome = $record->run(self::text($input['command_id']), $actor, $company, $category, self::text($input['jumlah']), $account, $reference, $evidence, is_string($note) ? $note : null, self::text($input['tanggal']),
            $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'kembali' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'cashin', self::back($input, 'kas-masuk'), self::text($input['tanggal']));
    }

    /**
     * The proofs named, each the company's: an unknown one answers at the field.
     *
     * @param  array<string, mixed>  $input
     * @return list<int>
     */
    private function evidence(array $input, int $company): array
    {
        $ids = [];

        foreach (is_array($input['bukti'] ?? null) ? $input['bukti'] : [] as $publicId) {
            $ids[] = $this->list->publicOf('evidence_documents', $company, self::text($publicId)) ?? throw ValidationException::withMessages(['bukti' => self::say('projects.validation.unknown')]);
        }

        return array_values(array_unique($ids));
    }

    /**
     * A named reference resolved, or the field's error; none named stays none.
     *
     * @param  array<string, mixed>  $input
     * @param  callable(string): ?int  $resolve
     */
    private static function optional(array $input, string $field, callable $resolve): ?int
    {
        $value = $input[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $resolve(trim($value)) ?? throw ValidationException::withMessages([$field => self::say('projects.validation.unknown')]);
    }

    /** @param  array<string, mixed>  $input */
    private static function back(array $input, string $tab): string
    {
        $back = $input['kembali'] ?? null;

        return is_string($back) && $back !== '' ? $back : self::PAGE.'?tab='.$tab;
    }

    /** The command's answer as flash data, the input kept in place; a future date at its field (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, string $to, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("other_cash.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-02', 'ST-07' => ['stale', self::say('other_cash.refusal.cancelled')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'uncited' => 1, 'left' => 1, 'open' => 1])]);

        // PG-049 with the record (NAV-049): its row marked.
        $recorded = $outcome->kind === Outcome::COMMITTED && $outcome->resultType !== null && $outcome->resultId !== null
            ? RowHandle::of(['expense' => 'expenses', 'disbursement' => 'disbursements'][$outcome->resultType] ?? 'other_cash_receipts', $outcome->resultId) : null;

        return redirect()->to($recorded === null ? $to : $to.(str_contains($to, '?') ? '&' : '?').'tercatat='.$recorded, 303);
    }

    private static function refusal(Outcome $outcome): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-20' => self::say('other_cash.refusal.bill', ['sisa' => self::money($state['uncited'] ?? '0')]),
            'C-18' => self::say('other_cash.refusal.tax_proof_room', ['sisa' => self::money($state['uncited'] ?? '0')]),
            'C-19' => $reason === 'above_line' ? self::say('other_cash.refusal.line_room', ['sisa' => self::money($state['uncited'] ?? '0')]) : self::say('other_cash.refusal.line'),
            'C-23' => self::say('other_cash.refusal.reserved_category'),
            'C-32' => self::say('other_cash.refusal.account'),
            'C-46' => self::say('other_cash.refusal.case_open', ['sisa' => self::money($state['open'] ?? '0')]),
            'C-58' => self::say('other_cash.refusal.tax_proof'),
            'L-29' => self::say($reason === 'company_issued' ? 'other_cash.refusal.company_issued' : 'other_cash.refusal.proof'),
            'L-45' => self::say('other_cash.refusal.evidence'),
            'XL-5' => self::say('other_cash.refusal.transfer_scope'),
            'BR-FIN-07' => self::say('other_cash.refusal.transfer_capacity', ['sisa' => self::money($state['left'] ?? '0')]),
            'BR-FIN-17' => self::say($reason === 'treatment' ? 'other_cash.refusal.tax_treatment' : 'other_cash.refusal.tax_rule'),
            'C-14' => self::say('payments.refusal.other_party'),
            'AZ-01', 'AZ-08' => self::say('messages.msg_14'),
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
        $valid = Validator::make($request->only(array_keys(array_filter($rules, fn (string $key): bool => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY))), $rules, [
            'required' => __('projects.validation.required'),
            'required_if' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'array' => __('projects.validation.choice'),
            'string' => __('projects.validation.choice'),
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
}
