<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\EvidenceIdentity;
use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Models\EvidenceBalance;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Services\EvidenceRegistry;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * `DOC RegisterEvidence` (ROUTE-229; INT-229; NX-07; SF-EVIDENCE; IP-16 steps 3–5): the evidence an upload ends with —
 * type, issuer, the issuer's reference, document date and, for an amount-bearing type, the proven amount —, of a
 * company or of the pool for WAREHOUSE_RECORD and OTHER with the uploader's tick of MSG-32 (H7-11; PJ-08). Before its
 * transaction it holds FU-10's keys and runs the FL-10 lookups (BD-15; H6-09); in it, the actor's row at LK-03 and the
 * identity matching of BD-14: an identity that exists in the company is REJECTED under L-45 naming it, for "Tambahkan
 * sebagai Versi Baru" — never a second record —; the matches the actor may view are IP-05's warning, the others change
 * nothing and reach the Owner (DP-13). It writes the evidence, its first version current, its guard (C-18) and its
 * business audit — the reproduction of a rendition recorded as one (FL-05) —, and an override's ADM_PLUS event.
 */
final class RegisterEvidence
{
    public const TYPE = 'DOC.RegisterEvidence';

    public function __construct(
        private readonly EvidenceRegistry $registry,
        private readonly EffectiveCapabilities $capabilities,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{file: string, type: string, issuer: string, reference: ?string, date: string, amount: ?string, deliverer: ?string, received: ?string, acknowledged: list<string>, reason: ?string}  $input
     */
    public function run(string $commandId, int $actorUserId, ?int $companyId, ?string $companyPublicId, array $input): Outcome
    {
        $actor = $this->capabilities->for($actorUserId);
        $type = $input['type'];
        $amount = EvidenceTypes::isAmountBearing($type) ? $input['amount'] : null;
        $request = $this->registry->request($commandId, self::TYPE, $actorUserId, $companyId, $companyPublicId ?? 'POOL', [...$input, 'amount' => $amount]);
        $replayed = $this->registry->replay($request);

        if ($replayed !== null) {
            return $replayed;
        }

        if (! EvidenceTypes::mayHandle($actor, $type, $companyId)) {
            throw new AuthorizationException;
        }

        $file = $this->registry->ownUpload($actorUserId, $input['file'], $companyId);
        $keys = EvidenceIdentity::registrationKeys($file->sha256, $input['issuer'], $input['reference'], $input['date'], $companyId);

        return $this->registry->withKeys($keys, function () use ($request, $actor, $file, $input, $type, $amount, $companyId): Outcome {
            $found = $this->registry->matches($actor, $file, $input['issuer'], $type, $input['reference'], $input['date'], $amount, $companyId);

            return $this->registry->run($request, function (CommandContext $context) use ($found, $file, $input, $type, $amount, $companyId): CommandResult {
                $context->lock(new LockSet);
                $this->registry->confirmSession();
                $authority = $this->registry->ensureHandles($context, $type, $companyId);
                $key = EvidenceIdentity::key($input['issuer'], $type, $input['reference'], $input['date']);

                if (EvidenceIdentity::reference($input['reference']) !== '') {
                    $existing = DB::table('evidence_documents')->whereRaw('company_id is not distinct from ?', [$companyId])
                        ->where('evidence_type', $type)->where('identity_key', $key)->whereNotNull('issuer_reference')->value('public_id');

                    if (is_string($existing)) {
                        throw Refused::rejected('L-45', ['evidence' => $existing]);
                    }
                }

                $override = $this->registry->warn($authority, $companyId, $found['visible'], $input['acknowledged'], $input['reason']);
                $now = $context->now();
                $evidence = new EvidenceDocument;
                $evidence->forceFill([
                    'evidence_scope' => $companyId === null ? 'POOL' : 'COMPANY',
                    'company_id' => $companyId,
                    'evidence_type' => $type,
                    'declared_issuer' => trim($input['issuer']),
                    'issuer_reference' => EvidenceIdentity::reference($input['reference']) === '' ? null : trim((string) $input['reference']),
                    'document_date' => $input['date'],
                    'identity_key' => $key,
                    'proven_amount' => $amount,
                    'deliverer' => $input['deliverer'] === null || trim($input['deliverer']) === '' ? null : trim($input['deliverer']),
                    'received_date' => $input['received'],
                    'command_id' => $context->request->commandId,
                    'command_line_no' => 1,
                    'recorded_at' => $now,
                    'recorded_by' => $context->request->actorUserId,
                ])->save();
                $version = $this->registry->addVersion($context, $evidence, $file, null, $now);
                $evidence->forceFill(['current_version_id' => $version->id])->save();
                // The evidence guard is born with its evidence (FU; C-18): the proven amount caps every citation.
                (new EvidenceBalance)->forceFill(['evidence_document_id' => $evidence->id, 'proven_amount' => $amount ?? '0', 'recorded_at' => $now])->save();

                $this->audit->record(
                    action: 'DOC.EvidenceRegistered',
                    authorityClass: AuthorityClass::Adm,
                    actorUserId: $context->request->actorUserId,
                    companyId: $companyId,
                    entityType: 'evidence_document',
                    entityId: $evidence->id,
                    entityPublicId: $evidence->public_id,
                    commandId: $context->request->commandId,
                    change: AuditChange::created(['evidence_type' => $type, 'evidence_scope' => $evidence->evidence_scope, 'document_date' => $input['date'], 'proven_amount' => $amount, 'version_no' => 1]),
                    metadata: ['file' => $file->public_id, 'reproduction' => $found['reproduction'], 'pool' => $companyId === null],
                );

                if ($override) {
                    $this->registry->recordOverride($context, $evidence, $found['visible'], (string) $input['reason']);
                }

                $this->registry->announceHidden($context, $evidence, $found['hidden']);

                return new CommandResult('evidence_document', $evidence->id);
            });
        });
    }
}
