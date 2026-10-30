<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Audit\AuthorityClass;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * `FIN SaveInvoiceDraft` (ROUTE-241; INT-241; NX-09; WF-FIN-01; J-FIN-01) — "Simpan Draf" in the composer: an invoice of the project from its
 * confirmed lines, or a value-only DP or termin (L-16), its tax components apart from the sales value under the treatment of the business
 * date (BR-FIN-17; C-52). A draft's edits are by the root's `lock_version`, covering its lines, so two editors of different lines still
 * conflict (ST-01; M-38; UXS-11). `invoice.issue` with `finance.view` of the project's company.
 */
final class SaveInvoiceDraft
{
    public const TYPE = 'FIN.SaveInvoiceDraft';

    public const CAPABILITY = 'invoice.issue';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InvoiceRecords $records,
    ) {}

    /**
     * @param  array{kind: string, business_date: string, lines: list<array<string, mixed>>, taxes: array<string, string>}  $entered
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, ?Invoice $invoice, ?int $lockVersion, array $entered, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId, 'invoice' => $invoice?->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $invoice, $lockVersion, $entered): CommandResult {
            $set = (new LockSet)->add('projects', ['id' => $project->id]);
            $context->lock($invoice === null ? $set : $set->add('invoices', ['id' => $invoice->id]));

            if ($context->authority()?->allows('finance.view', $project->companyId) !== true) {
                throw new AuthorizationException;
            }

            if ($entered['business_date'] > BusinessDate::today()) {
                throw Refused::rejected('C-50', ['field' => 'business_date']);
            }

            if ($invoice === null) {
                if (! in_array(DB::table('projects')->where('id', $project->id)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
                    throw Refused::rejected('L-11', ['reason' => 'closed']);
                }

                if (! DB::table('project_commercial_balances')->where('project_id', $project->id)->exists()) {
                    throw Refused::rejected('L-16', ['reason' => 'unconfirmed']);
                }

                $client = DB::table('projects')->where('id', $project->id)->value('client_organization_id');
                $id = $this->records->create($context, $project, is_numeric($client) ? (int) $client : 0, $entered);
                $this->records->record($context, 'FIN.InvoiceDraftSaved', AuthorityClass::Adm, $id, null, $entered['business_date'], ['kind' => $entered['kind'], 'state' => 'DRAFT']);

                return new CommandResult('invoice', $id);
            }

            $fresh = DB::table('invoices')->where('id', $invoice->id)->first(['state', 'lock_version', 'current_version_id']);

            if ($fresh === null || $fresh->state !== 'DRAFT') {
                throw Refused::conflict('ST-02', ['state' => is_object($fresh) && is_string($fresh->state ?? null) ? $fresh->state : 'DELETED']);
            }

            if ((int) Row::text($fresh, 'lock_version') !== $lockVersion) {
                throw Refused::conflict('ST-01');
            }

            $this->records->saveDraft($context, $project, $invoice->id, (int) Row::text($fresh, 'current_version_id'), (int) $lockVersion, $entered);
            $this->records->record($context, 'FIN.InvoiceDraftSaved', AuthorityClass::Adm, $invoice->id, null, $entered['business_date'], ['kind' => $entered['kind'], 'lock_version' => (int) $lockVersion + 1], ['lock_version' => (int) $lockVersion]);

            return new CommandResult('invoice', $invoice->id);
        });
    }
}
