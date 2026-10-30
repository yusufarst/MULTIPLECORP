<?php

namespace App\Modules\FIN\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Queries\EvidenceList;
use App\Modules\FIN\Actions\IssueInvoice;
use App\Modules\FIN\Actions\IssuePrepaymentKuitansi;
use App\Modules\FIN\Actions\LinkPrepaymentKuitansi;
use App\Modules\FIN\Actions\ReviseInvoiceDown;
use App\Modules\FIN\Actions\SaveInvoiceDraft;
use App\Modules\FIN\Actions\VoidInvoice;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Data\InvoiceDocuments;
use App\Modules\FIN\Queries\InvoiceComposer;
use App\Modules\FIN\Queries\InvoiceDetail;
use App\Modules\FIN\Queries\InvoiceInputs;
use App\Modules\FIN\Queries\InvoiceList;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Queries\ProjectWorkspace;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use App\Support\Localization\Formats;
use App\Support\Money;
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
 * PG-041–PG-043 (ROUTE-041–ROUTE-043; NAV-041–NAV-043; J-FIN-01; J-FIN-08) with `FIN SaveInvoiceDraft` (ROUTE-241), `IssueInvoice` (ROUTE-242), `VoidInvoice ·
 * ReviseInvoiceDown` (ROUTE-245), `IssuePrepaymentKuitansi` (ROUTE-249) and `LinkPrepaymentKuitansi` (ROUTE-250), each with its `command_id` (CI-01): validation at
 * the fields (IP-14), a refusal in words with the input kept (IP-03; NV-04), a stale draft or a changed list a CONFLICT with what is current (ST-01; ST-07). An
 * unknown invoice and one the viewer may not see answer alike (AZ-11).
 */
final class InvoicesController extends Controller
{
    public function __construct(
        private readonly InvoiceList $list,
        private readonly InvoiceComposer $composer,
        private readonly InvoiceDetail $detail,
        private readonly InvoiceInputs $inputs,
        private readonly ProjectWorkspace $projects,
        private readonly EvidenceList $evidence,
    ) {}

    /** ROUTE-041. */
    public function index(Request $request): Response
    {
        return Inertia::render('FIN/Invoices', $this->list->screen(self::actor($request), self::query($request, 'perusahaan'), self::query($request, 'setelah'), self::query($request, 'nomor')));
    }

    /** ROUTE-042: the composer for the project chosen, or for the invoice's open draft; an issued one shows its result in place, then "Buka Invoice" (NAV-042). */
    public function create(Request $request): Response
    {
        $actor = self::actor($request);
        $draft = self::query($request, 'invoice');
        $invoice = $draft === null ? null : ($this->detail->find($actor, $draft) ?? throw new NotFoundHttpException);
        $project = $invoice !== null ? $this->projectOf($actor, $invoice->project_id) : ($this->projects->reference($actor, self::query($request, 'proyek') ?? '') ?? throw new NotFoundHttpException);

        if (! $this->composer->mayOpen($actor, $project->companyId)) {
            throw new AccessDeniedHttpException;
        }

        return Inertia::render('FIN/InvoiceComposer', $this->composer->screen($actor, $project, $invoice));
    }

    /** ROUTE-043; a draft opens in the composer. */
    public function show(Request $request, string $id): Response|RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;

        if ($invoice->state === 'DRAFT') {
            return redirect()->to('/keuangan/invoice/baru?'.http_build_query(['invoice' => $invoice->public_id]));
        }

        return Inertia::render('FIN/Invoice', $this->detail->of($actor, $invoice));
    }

    /** ROUTE-241 "Simpan Draf". */
    public function save(Request $request, SaveInvoiceDraft $save): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'proyek' => ['required_without:invoice', 'nullable', 'uuid'],
            'invoice' => ['nullable', 'uuid'],
            'lock_version' => ['required_with:invoice', 'nullable', 'integer', 'min:0'],
            ...self::contentRules(),
        ]);
        $invoice = isset($input['invoice']) ? ($this->detail->find($actor, self::text($input['invoice'])) ?? throw new NotFoundHttpException) : null;
        $project = $invoice !== null ? $this->projectOf($actor, $invoice->project_id) : ($this->projects->reference($actor, self::text($input['proyek'] ?? '')) ?? throw new NotFoundHttpException);
        $entered = $this->content($project, $input, 'isi.');
        $outcome = $save->run(self::text($input['command_id']), $actor, $project, $invoice, isset($input['lock_version']) ? (int) self::text($input['lock_version']) : null, $entered, array_diff_key($input, ['command_id' => true]));
        $to = $outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null
            ? '/keuangan/invoice/baru?'.http_build_query(['invoice' => $invoice !== null ? $invoice->public_id : $this->inputs->publicId($outcome->resultId)])
            : ($invoice !== null ? '/keuangan/invoice/baru?'.http_build_query(['invoice' => $invoice->public_id]) : '/keuangan/invoice/baru?'.http_build_query(['proyek' => $project->publicId]));

        return $this->answer($outcome, 'saved', $to, $entered['business_date'], $project->companyId);
    }

    /** ROUTE-242 "Terbitkan Invoice", or its layout *Nota*, after its confirmation (IP-08; MSG-39). */
    public function issue(Request $request, IssueInvoice $issue, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'lock_version' => ['required', 'integer', 'min:0'],
            'tata' => ['required', Rule::in(InvoiceDocuments::LAYOUTS)],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'isi' => ['nullable', 'string', 'size:64'],
        ]);
        $project = $this->projectOf($actor, $invoice->project_id);
        $outcome = $issue->run(self::text($input['command_id']), $actor, $project, $invoice, (int) self::text($input['lock_version']), self::text($input['tata']), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), self::nullable($input['isi'] ?? null));

        return $this->answer($outcome, 'issued', '/keuangan/invoice/baru?'.http_build_query(['invoice' => $invoice->public_id]), self::text($input['tanggal']), $invoice->company_id, self::text($input['tata']));
    }

    /** ROUTE-245 "Revisi Turun" and "Batalkan Invoice" with MSG-40's reason (IP-09). */
    public function correct(Request $request, VoidInvoice $void, ReviseInvoiceDown $revise, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'aksi' => ['required', Rule::in(['BATAL', 'REVISI'])],
            'lock_version' => ['required', 'integer', 'min:0'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'dasar' => ['required', Rule::in(['UNBILLED_ENTRY_ERROR', 'ENTRY_ERROR_REPLACED', 'ENTRY_ERROR', 'RETURN', 'RECONFIRMATION', 'CANCELLATION'])],
            'kasus' => ['nullable', 'string', 'size:32'],
            'konfirmasi' => ['nullable', 'string', 'size:32'],
            'pembatalan' => ['nullable', 'string', 'size:32'],
            'transisi' => ['nullable', 'string', 'size:32'],
            'bukti' => ['nullable', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'isi' => ['nullable', 'array'],
            ...self::contentRules(optional: true),
            'disposisi' => ['nullable', 'array'],
            'disposisi.aplikasi' => ['nullable', 'array'],
            'disposisi.potongan' => ['nullable', 'array'],
            'disposisi.hapus' => ['nullable', 'array'],
            'disposisi.aplikasi.*.id' => ['required', 'string', 'size:32'],
            'disposisi.aplikasi.*.tindakan' => ['required', Rule::in([InvoiceCorrection::RELEASE, InvoiceCorrection::RETARGET, InvoiceCorrection::KEEP])],
            'disposisi.potongan.*.id' => ['required', 'string', 'size:32'],
            'disposisi.potongan.*.tindakan' => ['required', Rule::in([InvoiceCorrection::RERECORD, InvoiceCorrection::RESIDUAL, InvoiceCorrection::KEEP])],
            'disposisi.hapus.*.id' => ['required', 'string', 'size:32'],
            'disposisi.hapus.*.tindakan' => ['required', Rule::in([InvoiceCorrection::SUPERSEDE, InvoiceCorrection::RERECORD])],
        ]);
        $evidence = isset($input['bukti']) ? ($this->evidence->idOf($actor, self::text($input['bukti']), $invoice->company_id, InvoiceCorrection::EVIDENCE)
            ?? throw ValidationException::withMessages(['bukti' => self::say('invoices.validation.evidence')])) : null;
        $project = $invoice->project_id === null ? null : $this->projectOf($actor, $invoice->project_id);
        $content = $project !== null && is_array($input['isi'] ?? null) ? $this->content($project, $input, 'isi.') : null;
        $reductions = $this->inputs->reductionIds($invoice->id);
        $correction = new InvoiceCorrection(
            basis: self::text($input['dasar']),
            reason: trim(self::text($input['alasan'])),
            businessDate: self::text($input['tanggal']),
            prefilled: $request->boolean('tanggal_otomatis'),
            caseId: self::rowOf('correction_cases', self::nullable($input['kasus'] ?? null), $this->inputs->caseIds($invoice->company_id, $invoice->project_id)),
            confirmationId: self::rowOf('commercial_confirmations', self::nullable($input['konfirmasi'] ?? null), $this->inputs->confirmationIds($invoice->project_id)),
            cancellationId: self::rowOf('line_scope_cancellations', self::nullable($input['pembatalan'] ?? null), $this->inputs->cancellationIds($invoice->project_id)),
            transitionId: self::rowOf('project_state_transitions', self::nullable($input['transisi'] ?? null), $this->inputs->transitionIds($invoice->project_id)),
            evidenceId: $evidence,
            content: $content,
            applications: self::dispositions($input, 'aplikasi', 'payment_applications', $reductions['applications']),
            settlements: self::dispositions($input, 'potongan', 'deduction_settlements', $reductions['settlements']),
            writeoffs: self::dispositions($input, 'hapus', 'receivable_writeoffs', $reductions['writeoffs']),
        );
        $payload = array_diff_key($input, ['command_id' => true]);
        $lockVersion = (int) self::text($input['lock_version']);
        $outcome = self::text($input['aksi']) === 'BATAL'
            ? $void->run(self::text($input['command_id']), $actor, $invoice, $lockVersion, $correction, $payload)
            : $revise->run(self::text($input['command_id']), $actor, $invoice, $lockVersion, $correction, $payload);

        return $this->answer($outcome, self::text($input['aksi']) === 'BATAL' ? 'voided' : 'revised', "/keuangan/invoice/{$invoice->public_id}", self::text($input['tanggal']), $invoice->company_id);
    }

    /** ROUTE-249: the draft ROUTE-221 prepared, issued after the confirmation with its reason; PG-036 answers (`DIR-081` OB-02). */
    public function kuitansi(Request $request, IssuePrepaymentKuitansi $issue, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $invoice = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'dokumen' => ['required', 'uuid'],
            'draf' => ['required', 'uuid'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'isi' => ['nullable', 'string', 'size:64'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $document = $this->inputs->kuitansi(self::text($input['dokumen']), $invoice->id) ?? throw new NotFoundHttpException;
        $outcome = $issue->run(self::text($input['command_id']), $actor, $invoice, $document['id'], (int) self::text($input['lock_version']), self::text($input['draf']), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), self::nullable($input['isi'] ?? null), trim(self::text($input['alasan'])));

        return $this->answer($outcome, 'kuitansi_issued', "/dokumen/{$document['public_id']}", self::text($input['tanggal']), $invoice->company_id, 'DOC-05');
    }

    /** ROUTE-250 "Tautkan Kuitansi". */
    public function link(Request $request, LinkPrepaymentKuitansi $link, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $document = $this->inputs->kuitansi($id, null) ?? throw new NotFoundHttpException;
        $invoice = $this->detail->find($actor, $this->inputs->publicId($document['invoice_id'])) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['pembayaran' => ['required', 'array', 'min:1', 'max:50'], 'pembayaran.*' => ['required', 'uuid']]);
        $publicIds = array_values(array_map(self::text(...), is_array($input['pembayaran']) ? $input['pembayaran'] : []));
        sort($publicIds);
        $payments = $this->inputs->paymentIds($invoice->company_id, $publicIds);

        if (count($payments) !== count(array_unique($publicIds))) {
            throw ValidationException::withMessages(['pembayaran' => self::say('invoices.validation.payment')]);
        }

        $outcome = $link->run(self::text($input['command_id']), $actor, $invoice, $document['id'], $payments, implode(',', $publicIds));

        return $this->answer($outcome, 'kuitansi_linked', "/keuangan/invoice/{$invoice->public_id}", BusinessDate::today(), $invoice->company_id);
    }

    private function projectOf(int $actor, ?int $projectId): ProjectRef
    {
        return $this->projects->reference($actor, $this->inputs->projectPublicId($projectId)) ?? throw new NotFoundHttpException;
    }

    /** @return array<string, list<mixed>> */
    private static function contentRules(bool $optional = false): array
    {
        $required = $optional ? 'required_with:isi' : 'required';

        return [
            'isi.jenis' => [$required, Rule::in(['ITEMIZED', 'VALUE_ONLY'])],
            'isi.tanggal' => [$required, 'date_format:Y-m-d'],
            'isi.baris' => [$required, 'array', 'min:1', 'max:200'],
            'isi.baris.*.baris' => ['nullable', 'string', 'size:32'],
            'isi.baris.*.jumlah' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
            'isi.baris.*.keterangan' => ['nullable', 'string', 'max:500'],
            'isi.baris.*.nilai' => ['nullable', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'isi.pajak' => ['nullable', 'array'],
            'isi.pajak.*' => ['nullable', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
        ];
    }

    /**
     * The composer's content as the records take it: confirmed lines by the handle shown, or value-only lines, each at its field.
     *
     * @param  array<string, mixed>  $input
     * @return array{kind: string, business_date: string, lines: list<array<string, mixed>>, taxes: array<string, string>}
     */
    private function content(ProjectRef $project, array $input, string $prefix): array
    {
        $content = is_array($input['isi'] ?? null) ? $input['isi'] : [];
        $kind = self::text($content['jenis'] ?? '');
        $handles = $this->inputs->lineHandles($project->id);
        $lines = [];

        foreach (is_array($content['baris'] ?? null) ? array_values($content['baris']) : [] as $index => $line) {
            $line = is_array($line) ? $line : [];

            if ($kind === 'ITEMIZED') {
                $id = $handles[self::text($line['baris'] ?? '')] ?? throw ValidationException::withMessages(["{$prefix}baris.{$index}.baris" => self::say('fulfilment.validation.line')]);
                $quantity = self::text($line['jumlah'] ?? '');

                if (preg_match('/[1-9]/', $quantity) !== 1) {
                    throw ValidationException::withMessages(["{$prefix}baris.{$index}.jumlah" => self::say('invoices.validation.quantity')]);
                }

                $lines[] = ['line' => $id, 'quantity' => $quantity, 'description' => self::nullable($line['keterangan'] ?? null)];
            } else {
                $value = self::text($line['nilai'] ?? '');
                $description = trim(self::text($line['keterangan'] ?? ''));

                if ($description === '') {
                    throw ValidationException::withMessages(["{$prefix}baris.{$index}.keterangan" => self::say('projects.validation.required')]);
                }

                if ($value === '' || ! Money::of($value)->isPositive()) {
                    throw ValidationException::withMessages(["{$prefix}baris.{$index}.nilai" => self::say('invoices.validation.value')]);
                }

                $lines[] = ['description' => $description, 'value' => $value];
            }
        }

        $taxes = [];

        foreach (is_array($content['pajak'] ?? null) ? $content['pajak'] : [] as $code => $amount) {
            if (is_string($amount) && $amount !== '') {
                $taxes[(string) $code] = $amount;
            }
        }

        return ['kind' => $kind, 'business_date' => self::text($content['tanggal'] ?? ''), 'lines' => $lines, 'taxes' => $taxes];
    }

    /**
     * A disposition list by id; a handle of no current reduction names nothing, and the ST-07 compare then answers with the current list.
     *
     * @param  array<string, mixed>  $input
     * @param  list<int>  $current
     * @return array<int, string>
     */
    private static function dispositions(array $input, string $key, string $table, array $current): array
    {
        $given = is_array($input['disposisi'] ?? null) && is_array($input['disposisi'][$key] ?? null) ? $input['disposisi'][$key] : [];
        $byHandle = [];

        foreach ($current as $id) {
            $byHandle[RowHandle::of($table, $id)] = $id;
        }

        $found = [];

        foreach ($given as $index => $item) {
            $item = is_array($item) ? $item : [];
            $id = $byHandle[self::text($item['id'] ?? '')] ?? null;

            if ($id === null) {
                // A reduction that ended since the page read it: never a silent drop.
                $found[-1 - (int) $index] = self::text($item['tindakan'] ?? '');
            } else {
                $found[$id] = self::text($item['tindakan'] ?? '');
            }
        }

        return $found;
    }

    /** @param  array<mixed>  $ids */
    private static function rowOf(string $table, ?string $handle, array $ids): ?int
    {
        if ($handle === null) {
            return null;
        }

        foreach ($ids as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of($table, (int) $id), $handle)) {
                return (int) $id;
            }
        }

        throw ValidationException::withMessages(['dasar' => self::say('invoices.validation.basis')]);
    }

    /** The command's answer as flash data; a future date at its field (C-50). */
    private function answer(Outcome $outcome, string $done, string $to, string $sent, int $companyId, ?string $type = null): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $numbering = $outcome->code === 'C-10' ? $this->numberingOf($companyId, $type ?? InvoiceDocuments::INVOICE) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("invoices.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome, $numbering, $type)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-01' => ['stale', self::say(($state['reason'] ?? null) === 'facts' ? 'invoices.refusal.facts_changed' : 'messages.msg_03')],
                'ST-02' => ['stale', self::say('invoices.refusal.state_changed')],
                'ST-07' => ['stale', self::say('invoices.refusal.reductions_changed')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                'BD-06' => ['recorded', self::say('invoices.refusal.already_issued', ['nomor' => self::text($state['number'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'link' => $numbering['numbering'] ?? null, 'state' => array_intersect_key($state, ['reason' => 1, 'remaining' => 1, 'kuitansi' => 1, 'linked' => 1, 'outstanding' => 1, 'open' => 1, 'amount' => 1])]);

        return redirect()->to($to, 303);
    }

    /** @param  array{company: string, numbering: ?string}|null  $numbering */
    private static function refusal(Outcome $outcome, ?array $numbering, ?string $type): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-10' => self::say(($numbering['numbering'] ?? null) !== null ? 'invoices.refusal.numbering_owner' : 'messages.msg_17', ['jenis' => self::say('documents.'.strtolower(str_replace('-', '_', $type ?? InvoiceDocuments::INVOICE))), 'periode' => self::text($state['periode'] ?? ''), 'perusahaan' => $numbering['company'] ?? '']),
            'C-54' => self::say('projects.refusal.company_inactive'),
            'C-31' => self::say('invoices.refusal.cap', ['sisa' => self::money($state['remaining'] ?? '0')]),
            'L-11' => self::say('projects.refusal.closed'),
            'L-16' => self::say(in_array($reason, ['unconfirmed', 'no_value', 'unconfirmed_line'], true) ? "invoices.refusal.{$reason}" : 'messages.msg_04'),
            'CM-24' => self::say('invoices.refusal.evidence'),
            'CM-23' => self::say(in_array($reason, ['not_downward', 'not_issued', 'no_content'], true) ? "invoices.refusal.{$reason}" : 'messages.msg_04'),
            'C-56' => self::say(in_array($reason, ['billed', 'basis_row', 'basis'], true) ? "invoices.refusal.basis_{$reason}" : 'invoices.refusal.basis_basis'),
            'C-17' => self::say('invoices.refusal.replacement'),
            'C-16', 'C-15' => self::say('invoices.refusal.undisposed'),
            'C-22' => self::say('invoices.refusal.collected', ['jumlah' => self::money($state['amount'] ?? '0')]),
            'OD-07' => self::say('invoices.refusal.writeoff'),
            'C-43' => match ($reason) {
                'amount_differs' => self::say('invoices.refusal.link_differs', ['kuitansi' => self::money($state['kuitansi'] ?? '0'), 'tertaut' => self::money($state['linked'] ?? '0')]),
                'already_linked' => self::say('invoices.refusal.link_taken'),
                'no_application' => self::say('invoices.refusal.link_no_application'),
                default => self::say('invoices.refusal.kuitansi_cap', ['sisa' => self::money($state['outstanding'] ?? '0'), 'terbuka' => self::money($state['open'] ?? '0'), 'jumlah' => self::money($state['amount'] ?? '0')]),
            },
            'WF-DOC-01' => self::say('invoices.refusal.not_issued'),
            default => self::say('messages.msg_04'),
        };
    }

    /** @return array{company: string, numbering: ?string} The C-10 refusal's company and, for the Owner, PG-061. */
    private function numberingOf(int $companyId, string $type): array
    {
        $company = $this->inputs->company($companyId);
        $owner = $this->projects->viewer(self::actor(request()))->ownerRole;

        return [
            'company' => $company['legal_name'] ?? '',
            'numbering' => $owner && $company !== null ? '/pengaturan/penomoran?'.http_build_query(['perusahaan' => $company['public_id'], 'jenis' => strtolower($type)]) : null,
        ];
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $rules = ['command_id' => ['required', 'uuid'], ...$rules];
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('projects.validation.required'),
            'required_with' => __('projects.validation.required'),
            'required_without' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'array' => __('projects.validation.choice'),
            'integer' => __('projects.validation.number'),
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
