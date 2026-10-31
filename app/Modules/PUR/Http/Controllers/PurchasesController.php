<?php

namespace App\Modules\PUR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PUR\Actions\AmendPurchase;
use App\Modules\PUR\Actions\CancelPurchase;
use App\Modules\PUR\Actions\ClosePurchaseRemainder;
use App\Modules\PUR\Actions\CorrectPurchaseCost;
use App\Modules\PUR\Actions\MarkLinePerformed;
use App\Modules\PUR\Actions\RecordPurchase;
use App\Modules\PUR\Actions\RecordPurchaseCharge;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Queries\PurchaseDetail;
use App\Modules\PUR\Queries\PurchaseForm;
use App\Modules\PUR\Queries\PurchaseInputs;
use App\Modules\PUR\Queries\PurchaseList;
use App\Support\Command\Outcome;
use App\Support\Localization\Formats;
use App\Support\Search\Row;
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
 * PG-015, PG-016 and PG-017's routes and commands (ROUTE-015–ROUTE-017; ROUTE-171–ROUTE-175; ROUTE-178–ROUTE-180): a thin edge over the actions and the query classes. A refusal answers in place, the input kept (NV-04); an unknown or out-of-scope record answers 404, alike (CS-04; AZ-11).
 */
final class PurchasesController extends Controller
{
    private const AMOUNT = 'regex:/^(?!0+(\.0+)?$)\d{1,16}(\.\d{1,2})?$/';

    private const MONEY = 'regex:/^\d{1,16}(\.\d{1,2})?$/';

    private const QUANTITY = 'regex:/^(?!0+(\.0+)?$)\d{1,15}(\.\d{1,3})?$/';

    private const BASIS = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    public function __construct(
        private readonly PurchaseList $list,
        private readonly PurchaseDetail $detail,
        private readonly PurchaseForm $form,
        private readonly PurchaseInputs $inputs,
    ) {}

    /** ROUTE-015. */
    public function index(Request $request): Response
    {
        return Inertia::render('PUR/Purchases', $this->list->screen(self::actor($request), self::query($request, 'perusahaan'), self::query($request, 'status'), self::query($request, 'setelah')));
    }

    /** ROUTE-016. */
    public function show(Request $request, string $id): Response
    {
        $actor = self::actor($request);
        $purchase = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;

        $product = self::query($request, 'produk');

        return Inertia::render('PUR/Purchase', [...$this->detail->screen($actor, $purchase), 'product' => $product === null ? null : ($this->form->products([$product])[strtolower($product)] ?? null)]);
    }

    /** ROUTE-017: `proyek` a project's shortage, `produk` a product chosen on a line, `dari` a supplementary purchase; `harga` the products whose previous price the chosen supplier's line shows (SH-01). */
    public function create(Request $request): Response
    {
        $actor = self::actor($request);
        $company = self::query($request, 'perusahaan');
        $screen = $this->form->screen($actor, self::query($request, 'proyek'), $company, self::query($request, 'produk'), self::query($request, 'dari'));
        $products = array_values(array_filter(explode(',', self::query($request, 'harga') ?? '')));

        return Inertia::render('PUR/RecordPurchase', [...$screen, 'previous' => $this->form->previous($actor, is_string($screen['company'] ?? null) ? $screen['company'] : null, self::query($request, 'pemasok'), $products)]);
    }

    /** ROUTE-171. */
    public function store(Request $request, RecordPurchase $record): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'proyek' => ['nullable', 'uuid'],
            'perusahaan' => ['required_without:proyek', 'nullable', 'uuid'],
            'pemasok' => ['required', 'uuid'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'referensi' => ['nullable', 'string', 'max:120'],
            ...self::lineRules(),
            ...self::chargeRules(),
        ]);
        $company = null;
        $project = null;
        $projectKey = self::nullable($input['proyek'] ?? null);

        if ($projectKey !== null) {
            $found = $this->inputs->project($actor, $projectKey, RecordPurchase::CAPABILITY) ?? throw ValidationException::withMessages(['proyek' => self::say('projects.validation.unknown')]);
            $project = $found['id'];
            $company = $found['company'];
        } else {
            $company = $this->inputs->company($actor, self::text($input['perusahaan'] ?? ''), RecordPurchase::CAPABILITY) ?? throw ValidationException::withMessages(['perusahaan' => self::say('projects.validation.unknown')]);
        }

        $supplier = $this->inputs->supplier(self::text($input['pemasok'])) ?? throw ValidationException::withMessages(['pemasok' => self::say('projects.validation.unknown')]);
        $entry = new PurchaseEntry($company, $project, $supplier, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), self::nullable($input['referensi'] ?? null), $this->lines($input, $project, false), $this->charges($actor, $company, $input));
        $outcome = $record->run(self::text($input['command_id']), $actor, $entry, array_diff_key($input, ['command_id' => 1]));

        if ($outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null) {
            Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => null, 'text' => self::say('purchases.answer.recorded')]);

            return redirect()->to('/pembelian/'.$this->publicId($outcome->resultId), 303);
        }

        return $this->answer($outcome, 'recorded', redirect()->back(303)->getTargetUrl(), self::text($input['tanggal']), $this->inputs->companyName($company));
    }

    /** ROUTE-172. */
    public function amend(Request $request, AmendPurchase $amend, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $purchase = $this->inputs->purchase($actor, $id, AmendPurchase::CAPABILITY) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['lock_version' => ['required', 'integer'], ...self::lineRules()]);
        $entry = new PurchaseEntry($purchase->company_id, $purchase->project_id, $purchase->supplier_id, $purchase->business_date->format('Y-m-d'), false, $purchase->supplier_reference, $this->lines($input, $purchase->project_id, true), []);
        $outcome = $amend->run(self::text($input['command_id']), $actor, $purchase, (int) self::text($input['lock_version']), $entry, array_diff_key($input, ['command_id' => 1, 'lock_version' => 1]));

        return $this->answer($outcome, 'amended', self::back($purchase));
    }

    /** ROUTE-173. */
    public function cancel(Request $request, CancelPurchase $cancel, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $purchase = $this->inputs->purchase($actor, $id, CancelPurchase::CAPABILITY) ?? throw new NotFoundHttpException;
        $input = self::validated($request, self::reasoned());
        $outcome = $cancel->run(self::text($input['command_id']), $actor, $purchase, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($outcome, 'cancelled', self::back($purchase), self::text($input['tanggal']));
    }

    /** ROUTE-174. */
    public function close(Request $request, ClosePurchaseRemainder $close, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $purchase = $this->inputs->purchase($actor, $id, ClosePurchaseRemainder::CAPABILITY) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            ...self::reasoned(),
            'baris' => ['required', 'array', 'min:1', 'max:500'],
            'baris.*.baris' => ['required', 'string', 'size:32', 'distinct'],
            'baris.*.jumlah' => ['required', self::QUANTITY],
        ]);
        $closures = [];

        foreach (self::rows($input['baris']) as $position => $row) {
            $line = $this->inputs->line($purchase->id, self::text($row['baris'] ?? '')) ?? throw ValidationException::withMessages(["baris.{$position}.baris" => self::say('projects.validation.unknown')]);
            $closures[$line] = self::text($row['jumlah'] ?? '');
        }

        $outcome = $close->run(self::text($input['command_id']), $actor, $purchase, $closures, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($outcome, 'closed', self::back($purchase), self::text($input['tanggal']));
    }

    /** ROUTE-175. */
    public function correct(Request $request, CorrectPurchaseCost $correct, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $purchase = $this->inputs->purchase($actor, $id, CorrectPurchaseCost::CAPABILITY) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            ...self::reasoned(),
            'jenis' => ['required', Rule::in(['PRICE_CORRECTION', 'TAX_CORRECTION', 'CHARGE_CORRECTION'])],
            'catatan' => ['required', 'string', 'size:32'],
            'nilai' => ['required', self::MONEY],
        ]);
        $kind = self::text($input['jenis']);

        if ($kind === 'CHARGE_CORRECTION') {
            $charge = $this->inputs->charge($purchase->id, self::text($input['catatan'])) ?? throw ValidationException::withMessages(['catatan' => self::say('projects.validation.unknown')]);
            $outcome = $correct->charge(self::text($input['command_id']), $actor, $purchase, $charge, self::text($input['nilai']), null, [], trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));
        } else {
            $line = $this->inputs->line($purchase->id, self::text($input['catatan'])) ?? throw ValidationException::withMessages(['catatan' => self::say('projects.validation.unknown')]);
            $outcome = $correct->line(self::text($input['command_id']), $actor, $purchase, $line, $kind, self::text($input['nilai']), trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));
        }

        return $this->answer($outcome, 'corrected', self::back($purchase), self::text($input['tanggal']));
    }

    /** ROUTE-179. */
    public function correctBasis(Request $request, CorrectPurchaseCost $correct, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $purchase = $this->inputs->purchase($actor, $id, CorrectPurchaseCost::CAPABILITY) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            ...self::reasoned(),
            'catatan' => ['required', 'string', 'size:32'],
            'dasar' => ['required', Rule::in(PurchaseEntry::BASES)],
            'nilai_dasar' => ['nullable', 'array', 'max:500'],
            'nilai_dasar.*' => ['nullable', self::BASIS],
        ]);
        $charge = $this->inputs->charge($purchase->id, self::text($input['catatan'])) ?? throw ValidationException::withMessages(['catatan' => self::say('projects.validation.unknown')]);
        $values = self::basisValues($input['nilai_dasar'] ?? []);
        $outcome = $correct->charge(self::text($input['command_id']), $actor, $purchase, $charge, null, self::text($input['dasar']), $values, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($outcome, 'corrected', self::back($purchase), self::text($input['tanggal']));
    }

    /** ROUTE-178. */
    public function charge(Request $request, RecordPurchaseCharge $record, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $purchase = $this->inputs->purchase($actor, $id, RecordPurchaseCharge::CAPABILITY) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'tagihan' => ['required', 'uuid'],
            'klasifikasi' => ['required', Rule::in([PurchaseEntry::ATTRIBUTABLE, PurchaseEntry::NON_ATTRIBUTABLE])],
            'jumlah' => ['required', self::AMOUNT],
            'dasar' => ['required_if:klasifikasi,'.PurchaseEntry::ATTRIBUTABLE, 'nullable', Rule::in(PurchaseEntry::BASES)],
            'nilai_dasar' => ['nullable', 'array', 'max:500'],
            'nilai_dasar.*' => ['nullable', self::BASIS],
        ]);
        $bill = $this->inputs->bill($actor, $purchase->company_id, self::text($input['tagihan'])) ?? throw ValidationException::withMessages(['tagihan' => self::say('projects.validation.unknown')]);
        $attributable = self::text($input['klasifikasi']) === PurchaseEntry::ATTRIBUTABLE;
        $charge = ['bill' => $bill, 'classification' => self::text($input['klasifikasi']), 'amount' => self::text($input['jumlah']), 'basis' => $attributable ? self::nullable($input['dasar'] ?? null) : null, 'values' => self::basisValues($input['nilai_dasar'] ?? [])];
        $outcome = $record->run(self::text($input['command_id']), $actor, $purchase, $charge, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1]));

        return $this->answer($outcome, 'charged', self::back($purchase), self::text($input['tanggal']));
    }

    /** ROUTE-180. */
    public function mark(Request $request, MarkLinePerformed $mark, string $id, string $line): RedirectResponse
    {
        $actor = self::actor($request);
        $performed = ! $request->boolean('batalkan');
        $purchase = $this->detail->find($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'batalkan' => ['nullable', 'boolean'],
            'alasan' => [$performed ? 'nullable' : 'required', 'string', 'min:3', 'max:500'],
        ]);
        $lineId = $this->inputs->line($purchase->id, $line) ?? throw new NotFoundHttpException;
        $outcome = $mark->run(self::text($input['command_id']), $actor, $purchase, $lineId, $performed, $performed ? null : trim(self::text($input['alasan'] ?? '')));

        return $this->answer($outcome, $performed ? 'marked' : 'unmarked', self::back($purchase));
    }

    /** @return array<string, list<mixed>> */
    private static function lineRules(): array
    {
        return [
            'baris' => ['required', 'array', 'min:1', 'max:500'],
            'baris.*.handle' => ['nullable', 'string', 'size:32'],
            'baris.*.jenis' => ['required', Rule::in([PurchaseEntry::GOODS, PurchaseEntry::NONSTOCK])],
            'baris.*.produk' => ['required', 'uuid'],
            'baris.*.satuan' => ['required', 'string', 'max:32'],
            'baris.*.jumlah' => ['required', self::QUANTITY],
            'baris.*.harga' => ['required', self::MONEY],
            'baris.*.langsung' => ['nullable', 'boolean'],
            'baris.*.item_proyek' => ['nullable', 'string', 'size:32'],
            'baris.*.uraian' => ['nullable', 'string', 'max:500'],
            'baris.*.pajak' => ['nullable', 'array', 'max:10'],
            'baris.*.pajak.*.kode' => ['required', 'string', 'max:32'],
            'baris.*.pajak.*.tarif' => ['required', 'regex:/^\d{1,3}(\.\d{1,6})?$/'],
            'baris.*.pajak.*.nilai' => ['required', self::MONEY],
        ];
    }

    /** @return array<string, list<mixed>> */
    private static function chargeRules(): array
    {
        return [
            'biaya' => ['nullable', 'array', 'max:20'],
            'biaya.*.tagihan' => ['required', 'uuid'],
            'biaya.*.klasifikasi' => ['required', Rule::in([PurchaseEntry::ATTRIBUTABLE, PurchaseEntry::NON_ATTRIBUTABLE])],
            'biaya.*.jumlah' => ['required', self::AMOUNT],
            'biaya.*.dasar' => ['nullable', Rule::in(PurchaseEntry::BASES)],
            'biaya.*.nilai_dasar' => ['nullable', 'array', 'max:500'],
            'biaya.*.nilai_dasar.*' => ['nullable', self::BASIS],
        ];
    }

    /** @return array<string, list<mixed>> */
    private static function reasoned(): array
    {
        return [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * The form's lines, each reference resolved inside the scope; a wrong one at its field (IP-14).
     *
     * @param  array<string, mixed>  $input
     * @return list<array{handle: ?string, kind: string, product: int, line: ?int, mode: string, quantity: string, unit: int, price: string, description: string, taxes: list<array{type: int, rate: string, amount: string}>}>
     */
    private function lines(array $input, ?int $project, bool $amending): array
    {
        $unknown = self::say('projects.validation.unknown');
        $errors = [];
        $lines = [];

        foreach (self::rows($input['baris'] ?? []) as $position => $row) {
            $product = $this->inputs->product(self::text($row['produk'] ?? ''));

            if ($product === null) {
                $errors["baris.{$position}.produk"] = $unknown;

                continue;
            }

            $unit = $this->inputs->unit($product, self::text($row['satuan'] ?? ''));

            if ($unit === null) {
                $errors["baris.{$position}.satuan"] = $unknown;
            }

            $demandHandle = self::nullable($row['item_proyek'] ?? null);
            $demand = $demandHandle === null ? null : $this->inputs->demandLine($project, $demandHandle);

            if ($demandHandle !== null && $demand === null) {
                $errors["baris.{$position}.item_proyek"] = $unknown;
            }

            $taxes = [];

            foreach (self::rows($row['pajak'] ?? []) as $index => $tax) {
                $type = $this->inputs->taxType(self::text($tax['kode'] ?? ''));

                if ($type === null) {
                    $errors["baris.{$position}.pajak.{$index}.kode"] = $unknown;

                    continue;
                }

                $taxes[] = ['type' => $type, 'rate' => self::text($tax['tarif'] ?? ''), 'amount' => self::text($tax['nilai'] ?? '')];
            }

            $lines[] = ['handle' => $amending ? self::nullable($row['handle'] ?? null) : null, 'kind' => self::text($row['jenis'] ?? ''), 'product' => $product, 'line' => $demand, 'mode' => filter_var($row['langsung'] ?? false, FILTER_VALIDATE_BOOLEAN) ? PurchaseEntry::DROPSHIP : PurchaseEntry::WAREHOUSE,
                'quantity' => self::text($row['jumlah'] ?? ''), 'unit' => $unit ?? 0, 'price' => self::text($row['harga'] ?? ''), 'description' => trim(self::text($row['uraian'] ?? '')), 'taxes' => $taxes];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{bill: int, classification: string, amount: string, basis: ?string, values: list<?string>}>
     */
    private function charges(int $actor, int $company, array $input): array
    {
        $charges = [];

        foreach (self::rows($input['biaya'] ?? []) as $position => $row) {
            $bill = $this->inputs->bill($actor, $company, self::text($row['tagihan'] ?? '')) ?? throw ValidationException::withMessages(["biaya.{$position}.tagihan" => self::say('projects.validation.unknown')]);
            $attributable = self::text($row['klasifikasi'] ?? '') === PurchaseEntry::ATTRIBUTABLE;
            $basis = self::nullable($row['dasar'] ?? null);

            if ($attributable && $basis === null) {
                throw ValidationException::withMessages(["biaya.{$position}.dasar" => self::say('projects.validation.required')]);
            }

            $charges[] = ['bill' => $bill, 'classification' => self::text($row['klasifikasi'] ?? ''), 'amount' => self::text($row['jumlah'] ?? ''), 'basis' => $attributable ? $basis : null, 'values' => self::basisValues($row['nilai_dasar'] ?? [])];
        }

        return $charges;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rows(mixed $rows): array
    {
        $list = [];

        foreach (is_array($rows) ? array_values($rows) : [] as $position => $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $list[$position] = $row;
            }
        }

        return $list;
    }

    /** @return list<?string> */
    private static function basisValues(mixed $values): array
    {
        return array_values(array_map(fn (mixed $value): ?string => is_scalar($value) && (string) $value !== '' ? (string) $value : null, is_array($values) ? $values : []));
    }

    private static function back(Purchase $purchase): string
    {
        return '/pembelian/'.$purchase->public_id;
    }

    private function publicId(int $id): string
    {
        return Row::text(Purchase::query()->toBase()->where('id', $id)->first(['public_id']) ?? (object) ['public_id' => ''], 'public_id');
    }

    /** The command's answer as flash data, the input kept in place; a future date at its field (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, string $to, ?string $sent = null, string $company = ''): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("purchases.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome, $company)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-01', 'ST-02', 'ST-07' => ['stale', self::say('purchases.refusal.stale')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent ?? '', 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'uncited' => 1])]);

        return redirect()->to($to, 303);
    }

    private static function refusal(Outcome $outcome, string $company): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-10' => self::say('messages.msg_17', ['jenis' => self::say('glossary.gl_006'), 'periode' => self::text($state['periode'] ?? ''), 'perusahaan' => $company]),
            'C-14' => self::say('purchases.refusal.other_record'),
            'C-20' => self::say('purchases.refusal.above_bill', ['sisa' => Formats::money(self::text($state['uncited'] ?? '0'))]),
            'C-21' => self::say('purchases.refusal.charge_conserved'),
            'C-29' => match ($reason) {
                'received' => self::say('purchases.refusal.cancel_received'),
                'performed' => self::say('purchases.refusal.cancel_performed'),
                'paid' => self::say('purchases.refusal.cancel_paid'),
                'above_open' => self::say('purchases.refusal.above_open', ['sisa' => self::text($state['open'] ?? '0')]),
                default => self::say('purchases.refusal.quantity'),
            },
            'C-54' => self::say('purchases.refusal.company_inactive'),
            'C-09' => self::say('purchases.refusal.conversion'),
            'J-MD-03' => self::say('purchases.refusal.supplier_inactive'),
            'L-05' => self::say('projects.validation.unknown'),
            'L-23' => self::say(match ($reason) {
                'edit_instead' => 'purchases.refusal.edit_instead',
                'lines' => 'purchases.refusal.lines_kept',
                'nonstock' => 'purchases.refusal.amend_nonstock',
                default => 'purchases.refusal.first_effect',
            }),
            'L-43' => self::say('purchases.refusal.source_bill'),
            'SF-CHARGE' => self::say('purchases.refusal.basis'),
            'AX-34' => self::say('purchases.refusal.unchanged'),
            'NX-04' => self::say($reason === 'not_nonstock' ? 'purchases.refusal.not_nonstock' : 'purchases.refusal.not_open'),
            'CM-35' => self::say('purchases.refusal.not_attributable'),
            'BR-FIN-17' => self::say('purchases.refusal.no_tax_rule'),
            'WF-PUR-01' => self::say($reason === 'project_state' ? 'purchases.refusal.project_state' : ($reason === 'service_as_goods' ? 'purchases.refusal.service_as_goods' : 'purchases.refusal.no_lines')),
            'WF-PUR-02' => self::say('purchases.refusal.nonstock_replenishment'),
            'WF-FUL-03' => self::say('purchases.refusal.dropship'),
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
        $valid = Validator::make($request->all(), $rules, [
            'required' => __('projects.validation.required'),
            'required_if' => __('projects.validation.required'),
            'required_without' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'distinct' => __('projects.validation.choice'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'boolean' => __('projects.validation.choice'),
            'integer' => __('projects.validation.number'),
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

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
