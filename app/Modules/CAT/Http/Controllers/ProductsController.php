<?php

namespace App\Modules\CAT\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CAT\Actions\AddAlternateUnit;
use App\Modules\CAT\Actions\AttachProductImage;
use App\Modules\CAT\Actions\ChangeDefaultPrice;
use App\Modules\CAT\Actions\CreateProduct;
use App\Modules\CAT\Actions\RegisterBarcode;
use App\Modules\CAT\Actions\UpdateProduct;
use App\Modules\CAT\Queries\ProductCatalog;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-056 *Produk & Jasa* (ROUTE-056, ROUTE-145–ROUTE-147; INT-145–INT-147; NAV-056; SCR-52; J-MD-04): every active
 * account's catalogue — the cursor `setelah` and the product `produk` as whitelisted address keys (NV-05), the lookup
 * ROUTE-281's —, each part under its own read class (PJ-14), and the `products.edit` commands: create, the record's
 * edits — details, serial policy and scale, an alternate unit, a price default, an image, the archive — and the
 * barcode registration. Each NX-01 command carries its `command_id`, its answer in place as flash data (IP-01) and its
 * field errors at their fields (IP-14). A registration sent `inline` from a scan goes back to it, which continues.
 */
final class ProductsController extends Controller
{
    public const ACTIONS = ['details', 'policy', 'unit', 'price', 'image', 'archive'];

    private const LIST = '/data-master/produk';

    public function __construct(private readonly ProductCatalog $catalog) {}

    /** `CAT ShowProducts` (ROUTE-056). */
    public function show(Request $request): HttpResponse
    {
        $after = $request->query('setelah');
        $selected = $request->query('produk');

        return Inertia::render('CAT/Products', $this->catalog->screen(self::actor($request), is_string($after) ? $after : null, is_string($selected) ? $selected : null))
            ->toResponse($request);
    }

    /** `CAT CreateProduct` (ROUTE-145). */
    public function store(Request $request, CreateProduct $create): RedirectResponse
    {
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'kind' => ['required', Rule::in(['GOODS', 'SERVICE'])], 'sku' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:200'], 'unit_code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,12}$/'], 'unit_name' => ['nullable', 'string', 'max:60'],
            'quantity_scale' => ['required', 'integer', 'between:0,3']]);
        $outcome = $create->run(self::text($input['command_id']), self::actor($request), [
            'kind' => self::text($input['kind']), 'sku' => self::optional($input['sku'] ?? null), 'name' => trim(self::text($input['name'])),
            'unit_code' => self::text($input['unit_code']), 'unit_name' => self::optional($input['unit_name'] ?? null) ?? self::text($input['unit_code']), 'quantity_scale' => (int) self::text($input['quantity_scale']),
        ]);
        self::fieldRefusal($outcome);
        $id = $outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null ? $this->catalog->publicIdOf($outcome->resultId) : null;

        return self::answer($outcome, 'products.answer.created', $id === null ? self::back($request) : self::LIST.'?produk='.$id);
    }

    /** `CAT UpdateProduct · AddAlternateUnit · ChangeDefaultPrice · AttachProductImage` (ROUTE-146). */
    public function update(Request $request, string $id, UpdateProduct $change, AddAlternateUnit $unit, ChangeDefaultPrice $price, AttachProductImage $image): RedirectResponse
    {
        $product = $this->catalog->find($id) ?? throw new NotFoundHttpException;
        $action = $request->input('action');
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'action' => ['required', Rule::in(self::ACTIONS)], ...match ($action) {
            'details' => ['lock_version' => ['required', 'integer', 'min:0'], 'name' => ['required', 'string', 'max:200'], 'sku' => ['nullable', 'string', 'max:60']],
            'policy' => ['lock_version' => ['required', 'integer', 'min:0'], 'serialized' => ['required', 'boolean'], 'quantity_scale' => ['required', 'integer', 'between:0,3']],
            'unit' => ['unit_code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,12}$/'], 'unit_name' => ['nullable', 'string', 'max:60'], 'ratio' => ['required', 'integer', 'min:2', 'max:1000000']],
            'price' => ['price_kind' => ['required', Rule::in(array_keys(ChangeDefaultPrice::FAMILIES))], 'amount' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'], 'reason' => ['nullable', 'string', 'max:500']],
            'image' => ['file' => ['required', 'uuid']],
            default => ['lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['nullable', 'string', 'max:500']],
        }]);
        $commandId = self::text($input['command_id']);
        $actor = self::actor($request);
        $version = (int) self::text($input['lock_version'] ?? 0);

        $outcome = match ($input['action']) {
            'details' => $change->details($commandId, $actor, $product, $version, trim(self::text($input['name'])), self::optional($input['sku'] ?? null)),
            'policy' => $change->policy($commandId, $actor, $product, $version, $request->boolean('serialized'), (int) self::text($input['quantity_scale'])),
            'unit' => $unit->run($commandId, $actor, $product, self::text($input['unit_code']), self::optional($input['unit_name'] ?? null) ?? self::text($input['unit_code']), (int) self::text($input['ratio'])),
            'price' => $price->run($commandId, $actor, $product, self::text($input['price_kind']), self::text($input['amount']), self::optional($input['reason'] ?? null)),
            'image' => $image->run($commandId, $actor, $product, self::text($input['file'])),
            default => $change->archive($commandId, $actor, $product, $version, self::optional($input['reason'] ?? null)),
        };
        self::fieldRefusal($outcome);

        return self::answer($outcome, 'products.answer.'.self::text($input['action']), self::LIST.'?produk='.$product->public_id);
    }

    /** `CAT RegisterBarcode` (ROUTE-147). */
    public function barcode(Request $request, string $id, RegisterBarcode $register): RedirectResponse
    {
        $product = $this->catalog->find($id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'kind' => ['required', Rule::in(['MANUFACTURER', 'INTERNAL'])],
            'code' => ['required_if:kind,MANUFACTURER', 'nullable', 'string', 'regex:/^[A-Za-z0-9 \-]{3,64}$/'], 'inline' => ['nullable', 'boolean']]);
        /** @var 'MANUFACTURER'|'INTERNAL' $kind */
        $kind = self::text($input['kind']);
        $outcome = $register->run(self::text($input['command_id']), self::actor($request), $product, $kind, self::optional($input['code'] ?? null));
        self::fieldRefusal($outcome);

        return self::answer($outcome, 'products.answer.barcode', $request->boolean('inline') ? self::back($request) : self::LIST.'?produk='.$product->public_id);
    }

    /** A taken SKU, unit or code at its field (BD-16; BR-INV-09; IP-14). */
    private static function fieldRefusal(Outcome $outcome): void
    {
        $field = $outcome->state['field'] ?? null;
        $reason = $outcome->state['reason'] ?? null;

        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'BR-INV-09') {
            throw ValidationException::withMessages(['code' => __($reason === 'own' ? 'products.validation.code_own' : 'products.validation.code_other')]);
        }

        if ($outcome->kind === Outcome::CONFLICT && in_array($outcome->code, ['SQ-08', 'C-01'], true)) {
            $at = is_string($field) ? $field : ($outcome->code === 'C-01' ? 'code' : 'sku');
            throw ValidationException::withMessages([$at => __('products.validation.taken')]);
        }
    }

    /** The answer in place (IP-01; IG-02), then the page it goes back to. */
    private static function answer(Outcome $outcome, string $done, string $to): RedirectResponse
    {
        $reason = $outcome->state['reason'] ?? null;
        $key = 'products.refusal.'.strtolower(str_replace('-', '_', (string) $outcome->code)).(is_string($reason) ? '_'.$reason : '');
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __($done)],
            Outcome::REJECTED => [null, __($key) !== $key ? __($key) : __('messages.msg_04')],
            Outcome::CONFLICT => $outcome->code === 'ST-01' ? ['stale', __('messages.msg_03')] : ['recorded', __('messages.msg_04')],
            default => ['not_processed', __('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text]);

        return redirect()->to($to, 303);
    }

    /** The page the request came from, on this host only — otherwise the list. */
    private static function back(Request $request): string
    {
        $previous = url()->previous();

        return parse_url($previous, PHP_URL_HOST) === $request->getHost() ? $previous : self::LIST;
    }

    /**
     * Validates the named fields only (WS-01), each message from the catalogue.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('products.validation.required'),
            'required_if' => __('products.validation.required'),
            'max' => __('products.validation.too_long'),
            'in' => __('products.validation.choice'),
            'regex' => __('products.validation.format'),
            'integer' => __('products.validation.whole'),
            'between' => __('products.validation.choice'),
            'min' => __('products.validation.ratio'),
            'boolean' => __('products.validation.choice'),
            'uuid' => __('products.validation.format'),
        ])->validate();

        return $valid;
    }

    private static function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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
