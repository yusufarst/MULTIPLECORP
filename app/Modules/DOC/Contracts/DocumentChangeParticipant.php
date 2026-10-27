<?php

namespace App\Modules\DOC\Contracts;

use App\Modules\DOC\Data\DocumentChange;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;

/**
 * The inversion contract of ARCHITECTURE §3 through which a document's change reaches what depended on it — an
 * evidence replacement or unlink (L-41; NX-14), and a document void or revision (NX-12; AX-15; TASK-029) —: inside
 * DOC's transaction, under the locks the change took, PRJ revalidates completion (SF-REVAL), ADM re-evaluates the
 * requirements (BR-ADM-03) and FIN the settlements, so DOC never calls a higher tier. A participant names the rows it
 * will lock for the change before DOC takes its locks — the projects at LK-01 before any lower anchor — and then
 * acts on the change; a refusal it raises refuses the change. Participants are tagged in the container under `TAG`.
 */
interface DocumentChangeParticipant
{
    public const TAG = 'doc.change-participants';

    /** The rows the participant locks for the change, joined to DOC's own lock set in canonical order. */
    public function locks(DocumentChange $change): LockSet;

    /** Re-evaluates what depended on the changed document, in DOC's transaction. */
    public function documentChanged(CommandContext $context, DocumentChange $change): void;
}
