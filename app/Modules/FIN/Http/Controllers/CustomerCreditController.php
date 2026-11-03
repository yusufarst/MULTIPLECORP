<?php

namespace App\Modules\FIN\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FIN\Actions\HoldCredit;
use App\Modules\FIN\Actions\RefundCredit;
use App\Modules\FIN\Queries\CustomerCredit;
use App\Modules\FIN\Queries\PaymentInputs;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-048 *Kredit Pelanggan* (ROUTE-048; NAV-048; SCR-44; J-FIN-06) with "Kembalikan Dana" (ROUTE-256; INT-256) and "Tahan Kredit" (ROUTE-264; INT-264), each with its
 * confirmation and reason (IP-08; IP-09), answering in place (IP-01). "Terapkan ke Invoice" is TASK-051's ROUTE-252, sent back here.
 */
final class CustomerCreditController extends Controller
{
    public const PAGE = '/keuangan/kredit-pelanggan';

    private const AMOUNT = 'regex:/^\d{1,16}(\.\d{1,2})?$/';

    public function __construct(private readonly CustomerCredit $credit, private readonly PaymentInputs $inputs) {}

    /** ROUTE-048: credit per client and company with its sources, refunds and projects. */
    public function index(Request $request): Response
    {
        $branch = $request->query('cabang');
        $after = $request->query('setelah');

        return Inertia::render('FIN/CustomerCredit', ['page' => $this->credit->screen(self::actor($request), is_string($branch) ? $branch : null, is_string($after) ? $after : null)]);
    }

    /** ROUTE-256 "Kembalikan Dana": the amount, the account it leaves from, the transfer evidence and the reason (AX-23). */
    public function refund(Request $request, RefundCredit $refund, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $source = $this->inputs->source($actor, strtolower($id)) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'jumlah' => ['required', self::AMOUNT],
            'rekening' => ['required', 'string', 'size:32'],
            'bukti' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $account = $this->credit->accountOf($source['company'], self::text($input['rekening'])) ?? throw ValidationException::withMessages(['rekening' => self::say('projects.validation.unknown')]);
        $evidence = $this->credit->evidenceOf($source['company'], self::text($input['bukti'])) ?? throw ValidationException::withMessages(['bukti' => self::say('projects.validation.unknown')]);
        $outcome = $refund->run(self::text($input['command_id']), $actor, $source, self::text($input['jumlah']), $account, $evidence, trim(self::text($input['alasan'])), self::text($input['tanggal']),
            $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => true, 'tanggal_otomatis' => true]));

        return $this->answer($outcome, 'refunded', self::text($input['tanggal']));
    }

    /** ROUTE-264 "Tahan Kredit": the project's credit held with its reason (`DIR-104`). */
    public function hold(Request $request, HoldCredit $hold, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->credit->projectOf($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['alasan' => ['required', 'string', 'min:3', 'max:500']]);
        $outcome = $hold->run(self::text($input['command_id']), $actor, $project['id'], $project['company'], trim(self::text($input['alasan'])), array_diff_key($input, ['command_id' => true]));

        return $this->answer($outcome, 'held', null);
    }

    private function answer(Outcome $outcome, string $done, ?string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("credit.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent ?? '', 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, ['reason' => 1, 'capacity' => 1, 'left' => 1, 'held' => 1])]);

        return redirect()->to(self::PAGE, 303);
    }

    private static function refusal(Outcome $outcome): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'C-13' => $reason === 'amount' ? self::say('credit.refusal.amount') : self::say('credit.refusal.capacity', ['sisa' => self::text($state['capacity'] ?? '0')]),
            'L-29' => self::say($reason === 'credit_unevidenced' ? 'credit.refusal.unevidenced' : 'credit.refusal.proof'),
            'C-14' => match ($reason) {
                'not_active' => self::say('credit.refusal.not_active'),
                'nothing_left' => self::say('credit.refusal.nothing_left'),
                'covered' => self::say('credit.refusal.covered'),
                default => self::say('credit.refusal.other'),
            },
            'AZ-01', 'AZ-08' => self::say('messages.msg_14'),
            default => self::say('credit.refusal.other'),
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
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'boolean' => __('projects.validation.choice'),
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

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
