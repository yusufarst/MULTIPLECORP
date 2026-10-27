<?php

namespace App\Modules\PTY\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PTY\Actions\ArchiveSupplier;
use App\Modules\PTY\Actions\CreateSupplier;
use App\Modules\PTY\Actions\UpdateSupplier;
use App\Modules\PTY\Data\PartyFields;
use App\Modules\PTY\Queries\SupplierDirectory;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-055 *Pemasok* (ROUTE-055, ROUTE-143, ROUTE-144; INT-143, INT-144; NAV-055; SCR-51; J-MD-03): every active account's
 * page of suppliers — the term `q`, the cursor `setelah` and the supplier `pemasok` as whitelisted address keys
 * (NV-05) —, their details and price history each under its own read class (PJ-09; PJ-10), and the `parties.manage`
 * commands that create, edit and archive a supplier. A creation sent `inline` from PG-017 goes back to the purchase
 * form with the supplier chosen (IP-19).
 */
final class SuppliersController extends Controller
{
    public function __construct(private readonly SupplierDirectory $suppliers) {}

    /** `PTY ShowSuppliers` (ROUTE-055). */
    public function show(Request $request): HttpResponse
    {
        $term = $request->query('q');
        $after = $request->query('setelah');
        $selected = $request->query('pemasok');
        $term = is_string($term) && trim($term) !== '' ? mb_substr(trim($term), 0, 100) : null;

        return Inertia::render('PTY/Suppliers', [
            ...$this->suppliers->screen(PartyForms::actor($request), $term, is_string($after) ? $after : null, is_string($selected) ? $selected : null),
            'term' => $term,
        ])->toResponse($request);
    }

    /** `PTY CreateSupplier` (ROUTE-143). */
    public function store(Request $request, CreateSupplier $create): RedirectResponse
    {
        $input = PartyForms::validated($request, ['command_id' => ['required', 'uuid'], 'inline' => ['nullable', 'boolean'], ...self::rules(),
            'acknowledged' => ['nullable', 'array', 'max:20'], 'acknowledged.*' => ['uuid'], 'reason' => ['nullable', 'string', 'max:500']]);
        $fields = PartyFields::of(PartyFields::SUPPLIER, $input);
        $outcome = $create->run(PartyForms::text($input['command_id']), PartyForms::actor($request), (string) $fields['name'], array_diff_key($fields, ['name' => true]),
            PartyForms::list($input['acknowledged'] ?? []), PartyForms::optional($input['reason'] ?? null));

        $committed = $outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null;
        $supplier = $committed ? $this->suppliers->supplierOf((int) $outcome->resultId) : null;
        $inline = $request->boolean('inline');
        $matches = $outcome->code === 'PF-21' ? $this->suppliers->matches(PartyForms::list($outcome->state['matches'] ?? [])) : [];
        $to = $inline || $supplier === null ? url()->previous() : '/data-master/pemasok?'.http_build_query(['pemasok' => $supplier['id']]);

        return PartyForms::answer($outcome, 'parties.answer.supplier_created', $to, $matches, $inline && $supplier !== null ? ['supplier' => $supplier] : []);
    }

    /** `PTY UpdateSupplier · ArchiveSupplier` (ROUTE-144). */
    public function update(Request $request, string $id, UpdateSupplier $change, ArchiveSupplier $archive): RedirectResponse
    {
        $supplier = $this->suppliers->find($id) ?? throw new NotFoundHttpException;
        $archiving = $request->input('action') === 'archive';
        $input = PartyForms::validated($request, ['command_id' => ['required', 'uuid'], 'action' => ['required', Rule::in(['change', 'archive'])], 'lock_version' => ['required', 'integer', 'min:0'],
            ...($archiving ? ['reason' => ['nullable', 'string', 'max:500']] : self::rules())]);
        $commandId = PartyForms::text($input['command_id']);
        $lockVersion = (int) PartyForms::text($input['lock_version']);

        $outcome = $archiving
            ? $archive->run($commandId, PartyForms::actor($request), $supplier, $lockVersion, PartyForms::optional($input['reason'] ?? null))
            : $change->run($commandId, PartyForms::actor($request), $supplier, $lockVersion, PartyFields::of(PartyFields::SUPPLIER, $input));

        return PartyForms::answer($outcome, 'parties.answer.supplier_'.PartyForms::text($input['action']), '/data-master/pemasok?'.http_build_query(['pemasok' => $supplier->public_id]));
    }

    /** @return array<string, list<mixed>> */
    private static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'npwp' => ['nullable', 'string', 'regex:/^[0-9 .\-]{15,25}$/'],
            'address_lines' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9 +()\-]{6,30}$/'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
        ];
    }
}
