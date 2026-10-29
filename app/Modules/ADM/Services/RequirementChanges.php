<?php

namespace App\Modules\ADM\Services;

use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * ADM's part of `DocumentChangeParticipant` (ARCHITECTURE §3; L-41; BR-ADM-03; architecture challenge 7b, 20a): inside
 * DOC's transaction, the projects of the affected items held at LK-01, each requirement a changed document or evidence
 * satisfied re-evaluated — a voided version's links unlinked, a revised version's links moved to the revision that
 * superseded it, an evidence's links unlinked with the evidence's unlink from the item; a replaced evidence is judged on
 * its new current version by {@see Readiness}, whose status is derived each time, so nothing is written for it.
 */
final class RequirementChanges implements DocumentChangeParticipant
{
    public function __construct(private readonly Requirements $requirements) {}

    public function locks(DocumentChange $change): LockSet
    {
        $set = new LockSet;

        foreach (DB::table('admin_requirements')->whereIn('id', $this->requirementIds($change))->pluck('project_id') as $project) {
            $set = $set->add('projects', ['id' => is_numeric($project) ? (int) $project : 0]);
        }

        return $set;
    }

    public function documentChanged(CommandContext $context, DocumentChange $change): void
    {
        if ($change->kind === DocumentChange::DOCUMENT_VOIDED || $change->kind === DocumentChange::DOCUMENT_REVISED) {
            $this->version($context, $change);
        } elseif ($change->kind === DocumentChange::EVIDENCE_UNLINKED) {
            $this->evidence($context, $change);
        }
    }

    /** A voided or revised version satisfies nothing more; a revision's issued version takes its place (AX-15). */
    private function version(CommandContext $context, DocumentChange $change): void
    {
        $revised = $change->kind === DocumentChange::DOCUMENT_REVISED && $change->currentVersionId !== null;
        $links = DB::table('admin_requirement_links as l')->join('admin_requirements as r', 'r.id', '=', 'l.admin_requirement_id')
            ->where('l.document_version_id', $change->previousVersionId)->whereNull('l.unlinked_at')->orderBy('l.id')
            ->get(['l.id', 'r.id as requirement', 'r.project_id', 'r.company_id', 'r.lock_version', 'r.state']);

        foreach ($links as $link) {
            $this->requirements->unlink($context, (int) Row::text($link, 'id'), $revised ? 'The linked version was revised (L-41).' : 'The linked version was voided (L-41).');

            if ($revised && Row::text($link, 'state') === 'ACTIVE') {
                $current = Requirements::find((int) Row::text($link, 'project_id'), (int) Row::text($link, 'requirement'));

                if ($current !== null) {
                    $this->requirements->link($context, $current, $change->currentVersionId, null);
                }
            }
        }
    }

    /** The evidence's unlink from an item opens what that evidence satisfied (architecture challenge 20a). */
    private function evidence(CommandContext $context, DocumentChange $change): void
    {
        $requirements = array_values(array_map(fn (array $link): int => $link['id'], array_filter($change->links, fn (array $link): bool => $link['target'] === 'admin_requirement_id')));

        if ($requirements === []) {
            return;
        }

        $links = DB::table('admin_requirement_links')->where('evidence_document_id', $change->evidenceId)->whereIn('admin_requirement_id', $requirements)
            ->whereNull('unlinked_at')->orderBy('id')->pluck('id');
        $reason = DB::table('evidence_links')->whereIn('id', array_column($change->links, 'link'))->whereNotNull('unlink_reason')->value('unlink_reason');

        foreach ($links as $link) {
            $this->requirements->unlink($context, is_numeric($link) ? (int) $link : 0, is_string($reason) ? $reason : 'The evidence was unlinked (L-41).');
        }
    }

    /** @return list<int> the requirements the change may touch, read before DOC takes its locks */
    private function requirementIds(DocumentChange $change): array
    {
        $ids = array_map(fn (array $link): int => $link['id'], array_filter($change->links, fn (array $link): bool => $link['target'] === 'admin_requirement_id'));

        if (! $change->isDocument()) {
            foreach (DB::table('admin_requirement_links')->where('evidence_document_id', $change->evidenceId)->whereNull('unlinked_at')->pluck('admin_requirement_id') as $id) {
                $ids[] = is_numeric($id) ? (int) $id : 0;
            }
        }

        return array_values(array_unique($ids));
    }
}
