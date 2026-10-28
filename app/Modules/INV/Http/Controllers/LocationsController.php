<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\INV\Actions\SaveLocation;
use App\Modules\INV\Queries\LocationDirectory;
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
 * PG-057 *Lokasi Rak* (ROUTE-057, ROUTE-148; INT-148; NAV-057; SCR-53; J-MD-05): `stock.view`'s page of the shared
 * warehouse's racks and the products' minimum stock, and `warehouse.manage`'s one command that creates or edits a rack
 * and sets a product's minimum. Each NX-01 command carries its `command_id`, its answer in place as flash data (IP-01)
 * and its field errors at their fields (IP-14).
 */
final class LocationsController extends Controller
{
    private const PAGE = '/data-master/lokasi-rak';

    public function __construct(private readonly LocationDirectory $locations) {}

    /** `INV ShowLocations` (ROUTE-057). */
    public function show(Request $request): HttpResponse
    {
        return Inertia::render('INV/Locations', $this->locations->screen(self::actor($request)))->toResponse($request);
    }

    /** `INV SaveLocation · SetMinimumStock` (ROUTE-148). */
    public function save(Request $request, SaveLocation $save): RedirectResponse
    {
        $kind = $request->input('kind');
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'kind' => ['required', Rule::in(['create', 'edit', 'minimum'])], ...match ($kind) {
            'create' => ['code' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-]{0,23}$/'], 'name' => ['required', 'string', 'max:120']],
            'edit' => ['code' => ['required', 'string'], 'name' => ['required', 'string', 'max:120'], 'active' => ['required', 'boolean'], 'lock_version' => ['required', 'integer', 'min:0']],
            default => ['product' => ['required', 'string'], 'minimum' => ['nullable', 'string', 'regex:/^\d{1,15}(\.\d{1,3})?$/'], 'lock_version' => ['nullable', 'integer', 'min:0']],
        }]);
        $commandId = self::text($input['command_id']);
        $actor = self::actor($request);
        $name = trim(self::text($input['name'] ?? ''));
        $version = (int) self::text($input['lock_version'] ?? 0);

        $outcome = match ($input['kind']) {
            'create' => $save->create($commandId, $actor, mb_strtoupper(trim(self::text($input['code']))), $name),
            'edit' => $save->edit($commandId, $actor, $this->locations->find(self::text($input['code'])) ?? throw new NotFoundHttpException, $version, $name, $request->boolean('active')),
            default => (function () use ($save, $commandId, $actor, $input): Outcome {
                $product = $this->locations->productOf(self::text($input['product'])) ?? throw new NotFoundHttpException;
                $minimum = is_string($input['minimum'] ?? null) && trim($input['minimum']) !== '' ? trim($input['minimum']) : null;

                $read = is_numeric($input['lock_version'] ?? null) ? (int) $input['lock_version'] : null;

                return $save->setMinimum($commandId, $actor, $product['id'], self::text($input['product']), $read, $minimum);
            })(),
        };

        $field = $outcome->state['field'] ?? null;

        if (in_array($outcome->code, ['SQ-08', 'BR-INV-03'], true) && in_array($outcome->kind, [Outcome::CONFLICT, Outcome::REJECTED], true)) {
            throw ValidationException::withMessages([is_string($field) ? $field : 'code' => __($outcome->code === 'SQ-08' ? 'locations.validation.taken' : 'locations.validation.scale')]);
        }

        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __('locations.answer.'.self::text($input['kind']))],
            Outcome::CONFLICT => $outcome->code === 'ST-01' ? ['stale', __('messages.msg_03')] : ['recorded', __('messages.msg_04')],
            Outcome::REJECTED => [null, __('messages.msg_04')],
            default => ['not_processed', __('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text]);

        return redirect()->to(self::PAGE, 303);
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
            'required' => __('locations.validation.required'),
            'max' => __('locations.validation.too_long'),
            'in' => __('locations.validation.choice'),
            'regex' => __('locations.validation.format'),
            'boolean' => __('locations.validation.choice'),
        ])->validate();

        return $valid;
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
