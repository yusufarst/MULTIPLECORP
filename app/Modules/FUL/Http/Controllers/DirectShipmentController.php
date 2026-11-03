<?php

namespace App\Modules\FUL\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Actions\IssueDocument;
use App\Modules\DOC\Http\Controllers\DocumentsController;
use App\Modules\FUL\Actions\ConfirmDropShip;
use App\Modules\FUL\Actions\ReturnDropShipToSupplier;
use App\Modules\FUL\Actions\ReverseDropShipConfirmation;
use App\Modules\FUL\Queries\DirectShipments;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-032 *Kirim Langsung* (ROUTE-032; ROUTE-205–ROUTE-207; ROUTE-216; NAV-032): a thin edge over the FUL actions and the
 * query class. A confirmation answers on PG-011 with the confirmation recorded (NAV-032), a reversal and a return in place;
 * a refusal keeps the input (NV-04). "Terbitkan Surat Jalan" drafts its paper through ROUTE-221 and lands on PG-036, which
 * issues it, ROUTE-216 naming the document after the confirmation (WF-DOC-01; `DIR-100`).
 */
final class DirectShipmentController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    public function __construct(
        private readonly DirectShipments $shipments,
    ) {}

    /** ROUTE-032: the project's direct-to-client lines and its confirmations with their corrections, each continued by `?baris=` and `?konfirmasi=` (IP-18). */
    public function show(Request $request, string $id): Response
    {
        $lines = $request->query('baris');
        $confirmations = $request->query('konfirmasi');

        return Inertia::render('FUL/DirectShipment', [
            'page' => $this->shipments->page(self::actor($request), $id, is_string($lines) ? $lines : null, is_string($confirmations) ? $confirmations : null) ?? throw new NotFoundHttpException,
            'today' => BusinessDate::today(),
        ]);
    }

    /** ROUTE-205 "Konfirmasi Kirim Langsung": the purchase, the supplier's reference and proof, per line its quantity and serials; with the client's proof, its delivery (AX-08; L-40). */
    public function confirm(Request $request, ConfirmDropShip $confirm, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->shipments->projectOf($actor, $id, ConfirmDropShip::CAPABILITY) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'pembelian' => ['required', 'uuid'],
            'referensi' => ['required', 'string', 'min:1', 'max:100'],
            'bukti' => ['required', 'uuid'],
            'kasus' => ['nullable', 'uuid'],
            'baris' => ['required', 'array', 'min:1', 'max:50'],
            'baris.*.baris' => ['required', 'string', 'size:32'],
            'baris.*.jumlah' => ['required', self::QUANTITY],
            'baris.*.seri' => ['nullable', 'array', 'max:200'],
            'baris.*.seri.*' => ['string', 'max:100'],
            'penerima' => ['nullable', 'string', 'max:200'],
            'bukti_klien' => ['nullable', 'uuid'],
            'kondisi' => ['nullable', 'string', 'max:500'],
        ]);
        $purchase = $this->shipments->purchaseOf($project, self::text($input['pembelian'])) ?? throw ValidationException::withMessages(['pembelian' => self::say('projects.validation.unknown')]);
        $lines = [];

        foreach (is_array($input['baris']) ? array_values($input['baris']) : [] as $index => $line) {
            $line = is_array($line) ? $line : [];
            $lineId = $this->shipments->purchaseLineOf($purchase, self::text($line['baris'] ?? '')) ?? throw ValidationException::withMessages(["baris.{$index}.baris" => self::say('fulfilment.validation.line')]);
            $quantity = self::text($line['jumlah'] ?? '');

            // A line left at zero is no shipment of it: only what the supplier shipped is confirmed.
            if (preg_match('/^0+(\.0+)?$/', $quantity) === 1) {
                continue;
            }

            $lines[] = ['line' => $lineId, 'quantity' => $quantity, 'serials' => self::strings($line['seri'] ?? null), ...$this->shipments->policyOf($lineId)];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['baris' => self::say('dropship.validation.nothing')]);
        }

        $evidence = $this->shipments->evidenceOf($actor, $project->companyId, self::text($input['bukti'])) ?? throw ValidationException::withMessages(['bukti' => self::say('fulfilment.validation.evidence')]);
        $case = isset($input['kasus']) ? ($this->shipments->caseOf($project, self::text($input['kasus'])) ?? throw ValidationException::withMessages(['kasus' => self::say('projects.validation.unknown')])) : null;
        $delivery = null;

        if (isset($input['bukti_klien'])) {
            $recipient = trim(self::text($input['penerima'] ?? ''));

            if (mb_strlen($recipient) < 3) {
                throw ValidationException::withMessages(['penerima' => self::say('projects.validation.required')]);
            }

            $condition = trim(self::text($input['kondisi'] ?? ''));
            $delivery = ['recipient' => $recipient, 'condition' => $condition === '' ? null : $condition,
                'proof' => $this->shipments->evidenceOf($actor, $project->companyId, self::text($input['bukti_klien'])) ?? throw ValidationException::withMessages(['bukti_klien' => self::say('fulfilment.validation.evidence')])];
        }

        $outcome = $confirm->run(self::text($input['command_id']), $actor, $project, $purchase, self::text($input['referensi']), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $lines, $evidence, $case, $delivery,
            array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true]));

        return $this->answer($project, $outcome, 'confirmed', self::text($input['tanggal']));
    }

    /** ROUTE-206: the confirmation's lines reversed as never confirmed, with MSG-40's reason (IP-09; CM-27). */
    public function reverse(Request $request, ReverseDropShipConfirmation $reverse, string $id, string $dsc): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->shipments->projectOf($actor, $id, ReverseDropShipConfirmation::CAPABILITY) ?? throw new NotFoundHttpException;
        $confirmation = $this->shipments->confirmationOf($project, $dsc) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [...self::correction()]);
        $outcome = $reverse->run(self::text($input['command_id']), $actor, $project, $confirmation, $this->items($confirmation, $input), trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'),
            array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true]), self::strings($input['acknowledged'] ?? null), self::reason($input));

        return $this->answer($project, $outcome, 'reversed', self::text($input['tanggal']), $confirmation);
    }

    /** ROUTE-207: the client's return of the confirmation's lines to the warehouse, at a rack, or to the supplier, with its case and reason (CM-14; CM-15). */
    public function returned(Request $request, ReturnDropShipToSupplier $return, string $id, string $dsc): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->shipments->projectOf($actor, $id, ReturnDropShipToSupplier::CAPABILITY) ?? throw new NotFoundHttpException;
        $confirmation = $this->shipments->confirmationOf($project, $dsc) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            ...self::correction(),
            'tujuan' => ['required', Rule::in(['GUDANG', 'PEMASOK'])],
            'rak' => ['nullable', 'string', 'size:32'],
            'kondisi' => ['nullable', Rule::in(['USABLE', 'UNUSABLE'])],
        ]);
        $warehouse = self::text($input['tujuan']) === 'GUDANG';
        $rack = null;

        if ($warehouse) {
            $rack = $this->shipments->rackOf(self::text($input['rak'] ?? '')) ?? throw ValidationException::withMessages(['rak' => self::say('projects.validation.required')]);

            if (! isset($input['kondisi'])) {
                throw ValidationException::withMessages(['kondisi' => self::say('projects.validation.required')]);
            }
        }

        $outcome = $return->run(self::text($input['command_id']), $actor, $project, $confirmation, $warehouse ? ReturnDropShipToSupplier::WAREHOUSE : ReturnDropShipToSupplier::SUPPLIER, $this->items($confirmation, $input), $rack,
            $warehouse ? self::text($input['kondisi']) : null, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'),
            array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true]), self::strings($input['acknowledged'] ?? null), self::reason($input));

        return $this->answer($project, $outcome, $warehouse ? 'returned_warehouse' : 'returned_supplier', self::text($input['tanggal']), $confirmation);
    }

    /** ROUTE-216: the direct shipment's *Surat Jalan* issued on PG-036, the confirmation's segment naming its document (`DIR-100`; AX-15). */
    public function note(Request $request, DocumentsController $documents, IssueDocument $issue, string $id, string $dsc): RedirectResponse
    {
        $project = $this->shipments->projectOf(self::actor($request), $id, 'documents.issue') ?? throw new NotFoundHttpException;

        return $documents->issue($request, $issue, $this->shipments->noteOf($project, $dsc) ?? throw new NotFoundHttpException);
    }

    /** @return array<string, list<mixed>> a reversal's or a return's lines, reason and date */
    private static function correction(): array
    {
        return [
            'baris' => ['required', 'array', 'min:1', 'max:200'],
            'baris.*.baris' => ['required', 'string', 'size:32'],
            'baris.*.jumlah' => ['required', self::QUANTITY],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'acknowledged' => ['nullable', 'array', 'max:50'],
            'acknowledged.*' => ['uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{line: int, quantity: string}>
     */
    private function items(int $confirmation, array $input): array
    {
        $items = [];

        foreach (is_array($input['baris']) ? array_values($input['baris']) : [] as $index => $line) {
            $line = is_array($line) ? $line : [];
            $quantity = self::text($line['jumlah'] ?? '');

            if (preg_match('/^0+(\.0+)?$/', $quantity) === 1) {
                continue;
            }

            $items[] = ['line' => $this->shipments->confirmationLineOf($confirmation, self::text($line['baris'] ?? '')) ?? throw ValidationException::withMessages(["baris.{$index}.baris" => self::say('fulfilment.validation.line')]), 'quantity' => $quantity];
        }

        return $items === [] ? throw ValidationException::withMessages(['baris' => self::say('dropship.validation.nothing')]) : $items;
    }

    /** The command's answer as flash data: a confirmation on PG-011 (NAV-032), every other answer in place on PG-032. */
    private function answer(ProjectRef $project, Outcome $outcome, string $done, string $sent, ?int $confirmation = null): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $matches = $outcome->code === 'L-49' && $confirmation !== null ? $this->shipments->matches($confirmation, self::strings($state['matches'] ?? null)) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("dropship.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome, $matches ?? [])],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                'ST-09' => ['stale', self::say('dropship.refusal.policy')],
                'ST-02' => ['stale', self::say('dropship.refusal.purchase_closed')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, ...($matches === null ? [] : ['state' => ['matches' => $matches]])]);
        $page = "/proyek/{$project->publicId}";

        return redirect()->to($outcome->kind === Outcome::COMMITTED && $done === 'confirmed' ? $page : $page.'/kirim-langsung', 303);
    }

    /** @param  list<array{id: string, label: string, href: null}>  $matches */
    private static function refusal(Outcome $outcome, array $matches): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-29' => self::say('dropship.refusal.cap', ['sisa' => self::text($state['remainder'] ?? '0')]),
            'C-30' => self::say('dropship.refusal.demand', ['sisa' => self::text($state['remainder'] ?? '0')]),
            'C-27' => self::say($reason === 'delivered' ? 'dropship.refusal.delivered' : 'dropship.refusal.open', ['sisa' => self::text($state['open'] ?? '0')]),
            'C-02' => isset($state['serial']) ? self::say('messages.msg_36', ['seri' => self::text($state['serial'])]) : self::say('dropship.refusal.serials'),
            'C-09' => self::say('dropship.refusal.scale'),
            'C-59' => self::say('dropship.refusal.case'),
            'BR-INV-11' => self::say('dropship.refusal.mode'),
            'WF-INV-01' => self::say('dropship.refusal.rack'),
            'L-49' => self::say('messages.msg_15', ['daftar_catatan_yang_cocok' => implode('; ', array_column($matches, 'label'))]),
            'L-11' => self::say('projects.refusal.closed'),
            'AZ-08' => self::say('messages.msg_14'),
            'C-14' => self::say('dropship.refusal.unknown'),
            default => self::say('dropship.refusal.other'),
        };
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
            'uuid' => __('projects.validation.unknown'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'array' => __('projects.validation.choice'),
            'boolean' => __('projects.validation.choice'),
            'in' => __('projects.validation.choice'),
            'string' => __('projects.validation.choice'),
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

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return array_values(array_filter(is_array($values) ? $values : [], is_string(...)));
    }

    /** @param  array<string, mixed>  $input */
    private static function reason(array $input): ?string
    {
        $reason = trim(self::text($input['reason'] ?? ''));

        return $reason === '' ? null : $reason;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
