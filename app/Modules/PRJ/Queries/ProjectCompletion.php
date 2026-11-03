<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Queries\ProjectCases;
use App\Modules\PRJ\Contracts\ResidualObligationProvider;
use App\Modules\PRJ\Data\CompletionSnapshot;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Modules\PRJ\Models\Project;
use App\Support\Search\Row;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * PG-012 *Penyelesaian Proyek* (ROUTE-012; NAV-012; SCR-14; WF-PRJ-03; J-PRJ-03; UXI-26): the ten conditions derived from the facts and decisions each time
 * it is shown, never stored (L-01; FS-11) — each with its state and, unmet, what is missing and where it is resolved —, "Selesaikan Proyek" with
 * `projects.complete` and, for the Owner only, the Force Complete section with the unmet conditions, the residual obligations and the hash of the snapshot
 * shown (UXS-37; ST-04; OD-12). The same reading AX-25 and AX-26 make after the project's LK-01 lock ({@see self::read()}). Predicates as set queries (PF-24).
 */
final class ProjectCompletion
{
    /** Where each condition is resolved: the page and the capability that opens it (J-PRJ-03; AZ-01). */
    private const RESOLVED = [
        Predicates::FULFILMENT => ['projects.view', '/proyek/%s'],
        Predicates::DELIVERY => ['projects.view', '/gudang/pengiriman'],
        Predicates::RETURNS => ['projects.view', '/dokumen/kasus'],
        Predicates::DOCUMENTS => ['projects.view', '/dokumen'],
        Predicates::ADMINISTRATION => ['projects.view', '/dokumen/administrasi'],
        Predicates::BILLING => ['finance.view', '/keuangan/piutang'],
        Predicates::RECEIVABLE => ['finance.view', '/keuangan/piutang'],
        Predicates::STOCK => ['stock.reserve', '/gudang/reservasi'],
        Predicates::PAYMENT => ['finance.view', '/keuangan/pembayaran'],
        Predicates::AUDIT => ['finance.view', '/keuangan/pembayaran'],
    ];

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly ProjectJourneys $journeys,
        private readonly ProjectCases $cases,
        private readonly Container $app,
    ) {}

    /**
     * PG-012 for the viewer.
     *
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, Project $project): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $company = $project->company_id;
        $money = $viewer->allows('finance.view', $company);
        $states = $this->journeys->predicates([$project->id], $money)[$project->id] ?? [];
        $conditions = [];

        foreach (Predicates::ALL as $predicate) {
            $state = $states[$predicate] ?? PredicateState::none();
            [$capability, $path] = self::RESOLVED[$predicate];
            $conditions[] = ['key' => $predicate, 'met' => $state->met(), 'applies' => $state->applies, 'items' => array_map(fn (JourneyItem $item): array => $item->toArray(), $state->items),
                'href' => $state->met() || ! $viewer->allows($capability, $company) ? null : sprintf($path, $project->public_id)];
        }

        $active = $project->state === 'ACTIVE';
        $owner = $viewer->allows('projects.force_complete', $company);
        $unmet = CompletionSnapshot::unmet($states);
        // The Owner's section reads every money item: the snapshot the confirmation binds is the full one (UXS-37; SM-25).
        $force = $owner && $active && $unmet !== [] ? $this->read($project->id) : null;

        return [
            'project' => ['id' => $project->public_id, 'number' => $project->project_number, 'title' => $project->title, 'state' => $project->state],
            'conditions' => $conditions,
            'unmet' => $unmet,
            'can' => ['complete' => $active && $viewer->allows('projects.complete', $company), 'force' => $force !== null],
            'force' => $force === null ? null : ['unmet' => $force['unmet'], 'obligations' => $force['snapshot']['obligations'], 'hash' => $force['hash']],
            'record' => self::record($viewer, $project),
        ];
    }

    /**
     * The predicates, the snapshot and its hash for one project, every money item read (AX-25; AX-26): inside a command after LK-01 is held.
     *
     * @return array{states: array<string, PredicateState>, unmet: list<string>, snapshot: array<string, mixed>, hash: string}
     */
    public function read(int $projectId): array
    {
        $states = $this->journeys->predicates([$projectId], true)[$projectId] ?? [];
        $obligations = [];

        foreach ($this->cases->of([$projectId])[$projectId]['open'] ?? [] as $open) {
            $obligations[] = ['kind' => 'case', 'ref' => $open['kind'], 'label' => $open['kind'], 'amount' => null, 'quantity' => (string) $open['count']];
        }

        foreach ($this->app->tagged(ResidualObligationProvider::TAG) as $provider) {
            if ($provider instanceof ResidualObligationProvider) {
                array_push($obligations, ...($provider->obligations([$projectId])[$projectId] ?? []));
            }
        }

        $snapshot = CompletionSnapshot::of($states, $obligations);

        return ['states' => $states, 'unmet' => CompletionSnapshot::unmet($states), 'snapshot' => $snapshot, 'hash' => CompletionSnapshot::hash($snapshot)];
    }

    /**
     * A completed project's record: its last completion — normal or forced — with the unmet conditions and residual obligations a Force Complete kept
     * visible (BR-PRJ-04; AC-21); the obligations' money only to `finance.view` (SM-25).
     *
     * @return array<string, mixed>|null
     */
    private static function record(Authority $viewer, Project $project): ?array
    {
        $row = DB::table('project_state_transitions as t')->leftJoin('users as u', 'u.id', '=', 't.decided_by')->where('t.project_id', $project->id)->whereIn('t.transition_kind', ['COMPLETE_NORMAL', 'FORCE_COMPLETE'])
            ->orderByDesc('t.id')->first(['t.transition_kind', 't.reason', 't.unmet_predicates', 't.residual_obligations', 't.recorded_at', 'u.name']);

        if ($row === null) {
            return null;
        }

        $unmet = json_decode(Row::nullable($row, 'unmet_predicates') ?? '[]', true);
        $obligations = json_decode(Row::nullable($row, 'residual_obligations') ?? '[]', true);
        $money = $viewer->allows('finance.view', $project->company_id);

        return [
            'kind' => Row::text($row, 'transition_kind'), 'reason' => Row::nullable($row, 'reason'), 'by' => Row::nullable($row, 'name'), 'at' => Row::text($row, 'recorded_at'),
            'unmet' => is_array($unmet) ? array_values(array_filter($unmet, 'is_string')) : [],
            'obligations' => is_array($obligations) ? array_values(array_map(fn (mixed $item): array => is_array($item) ? ['kind' => is_string($item['kind'] ?? null) ? $item['kind'] : '', 'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'amount' => $money && is_string($item['amount'] ?? null) ? $item['amount'] : null, 'quantity' => is_string($item['quantity'] ?? null) ? $item['quantity'] : null] : [], $obligations)) : [],
        ];
    }
}
