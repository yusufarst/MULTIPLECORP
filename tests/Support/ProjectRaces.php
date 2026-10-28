<?php

namespace Tests\Support;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Actions\UpdateProject;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Queries\ProjectForm;
use App\Modules\PRJ\Services\ProjectRecords;
use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/**
 * The project commands as the integration races run them in their own sessions (TASK-032), each named by identifiers
 * and answering its outcome as a word: a creation through ROUTE-151's action, an amendment through ROUTE-152's, and a
 * channel-dependent commit — the commercial confirmation TASK-034 writes — held open until the other session waits on
 * the project it locks.
 */
final class ProjectRaces
{
    /** @return array{organization: ClientOrganization, unit: ClientUnit, address: ClientAddress, contact: ClientContact} */
    public static function client(int $organizationId): array
    {
        $unit = ClientUnit::query()->where('client_organization_id', $organizationId)->firstOrFail();

        return [
            'organization' => ClientOrganization::query()->findOrFail($organizationId),
            'unit' => $unit,
            'address' => ClientAddress::query()->where('client_unit_id', $unit->id)->firstOrFail(),
            'contact' => ClientContact::query()->where('client_unit_id', $unit->id)->firstOrFail(),
        ];
    }

    /** @return array{product: Product, base: Unit, box: ?Unit} */
    public static function product(int $productId): array
    {
        $product = Product::query()->findOrFail($productId);

        return ['product' => $product, 'base' => Unit::query()->findOrFail($product->base_unit_id), 'box' => null];
    }

    public static function create(int $actorId, int $companyId, int $organizationId, int $productId, string $goLive): string
    {
        return Projects::create($actorId, Company::query()->findOrFail($companyId), self::client($organizationId), [self::product($productId)], $goLive);
    }

    /** ROUTE-152 at the version the caller read, the channel and the title as given. */
    public static function amend(int $actorId, int $projectId, int $lockVersion, string $channel, string $title): string
    {
        $project = Project::query()->findOrFail($projectId);
        $company = Company::query()->findOrFail($project->company_id);
        $payload = Projects::payload($company, self::client($project->client_organization_id), [], ['kanal' => $channel, 'judul' => $title, 'baris' => self::lines($projectId)]);
        $form = app(ProjectForm::class);
        $input = $form->resolve($form->viewer($actorId), $payload, $project);

        return self::word(app(UpdateProject::class)->run((string) $payload['command_id'], $actorId, $project, $lockVersion, $input, BusinessDate::today(), true));
    }

    /** A commercial confirmation committed under the project's lock only once the other session waits on that lock. */
    public static function holdThenConfirm(int $actorId, int $projectId): string
    {
        $company = (int) DB::table('projects')->where('id', $projectId)->value('company_id');
        $request = new CommandRequest((string) Str::uuid(), 'test.confirm', $actorId, $company, 'projects.view', Fingerprint::of('test.confirm', null, ['project' => $projectId]));

        return self::word(app(CommandEnvelope::class)->run($request, function (CommandContext $context) use ($actorId, $projectId, $company): CommandResult {
            $context->lock(ProjectRecords::locks([$projectId]));
            DB::table('commercial_confirmations')->insert(['project_id' => $projectId, 'company_id' => $company, 'kind' => 'NO_QUOTATION', 'business_date' => BusinessDate::today(),
                'decided_by' => $actorId, 'reason' => 'SPK klien', 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $actorId]);
            Barrier::await('the confirmation holds the project');
            Sessions::awaitBlocked();

            return new CommandResult('project', $projectId);
        }));
    }

    /** @return list<array<string, string>> */
    private static function lines(int $projectId): array
    {
        return DB::table('project_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')->join('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->where('l.project_id', $projectId)->orderBy('l.line_no')->get(['l.id', 'p.public_id', 'u.code', 'l.entered_qty', 'l.unit_price', 'l.fulfillment_mode'])
            ->map(fn (object $line): array => ['baris' => RowHandle::of('project_lines', (int) $line->id), 'produk' => (string) $line->public_id, 'satuan' => (string) $line->code,
                'jumlah' => rtrim(rtrim((string) $line->entered_qty, '0'), '.'), 'harga' => (string) $line->unit_price, 'mode' => (string) $line->fulfillment_mode])->values()->all();
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : (string) $outcome->code));
    }
}
