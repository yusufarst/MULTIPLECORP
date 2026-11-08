# CR-001 — A fallback review while the external reviewer is unavailable

Status: ACCEPTED — the Owner's explicit approval of 2026-11-08, [DIR-107 and APPR-029](../DECISION_LOG.md#dir-107-and-appr-029--the-owners-approval-of-cr-001) | Date: 2026-11-07, accepted 2026-11-08 | Raised by: the executor, under CHANGE_CONTROL ([Lightweight records](../CHANGE_CONTROL.md#lightweight-records)) | Decision owner: the Owner

In force from 2026-11-08: the Owner approved it in their own words ([transcript](../sources/EXECUTION_OWNER_APPROVAL_CR-001_2026-11-08.txt)), amending [DIR-096](../DECISION_LOG.md#dir-096--standing-execution-directives)'s review requirement only as specified here; the Owner may revoke it at any time. The Owner's approval adds that the reviewer examines the complete pull request diff against the Task's contract, authorization, security, data integrity, concurrency and regression, and that a fallback review never counts as a successful Codex review. [DIR-106](../DECISION_LOG.md#dir-106--the-owners-codex-exception-for-pull-request-72)'s exception stays pull request #72's alone.

## Problem

DIR-096 makes a pull request eligible only when Codex's review is COMPLETED with its valid P1/P2 findings fixed, or past its 10-minute limit from its actual start (CODEX_TIMEOUT). Codex has answered at its usage limit three times — pull request #72 (comments 6703406133 and 6703851491) and pull request #73, TASK-060's (comments 6708226490 and 6708240278, on its opening and on the request for a review). A usage-limit answer is neither a review, a pass nor a CODEX_TIMEOUT, so an otherwise eligible pull request cannot merge, and every Task depending on it waits: TASK-062–TASK-069 wait on TASK-060. Buying credits is a paid service the Owner has not authorized (COST_POLICY).

## Why it matters

The external review is one gate among several — CI's fourteen layers, the authorization and integration suites, DEFINITION_OF_DONE — but it is the only one that depends on a third party's quota. Without a standing rule, each outage needs a one-off exception like DIR-106, which stops the pipeline until the Owner answers.

## Recommended option — a bounded fallback, recorded per pull request

When, and only when, Codex answers a pull request with a usage limit, an outage or no answer at all past its 10-minute limit, the executor may substitute an independent review under all of these conditions:

1. **Evidence of unavailability** — the reviewer's own answer (comment ids, or its absence past 10 minutes from the request) recorded on the pull request and in the Task's DONE record. A request made and answered "limit reached" counts once; the executor does not repeat requests or buy credits.
2. **Independence** — a reviewer that did not write the change, given the code and the Task's contract, never the author's conclusions; read-only, so it cannot edit what it reviews.
3. **Scope** — the whole diff of the pull request against its Task's acceptance criteria and security, company-scope, concurrency and data rules, reporting each finding with file, line, failure scenario and severity.
4. **Bounded loop** — one review, its material findings fixed in one batch, one targeted verification of those fixes; a further round only for a material defect the verification finds. No review is requested merely because a commit exists.
5. **Every other gate unchanged** — CI green on the final head, mergeability, no known material defect carried forward, DEFINITION_OF_DONE and the DONE record; a HIGH-risk Task keeps its adversarial preflight. A finding left open is a blocker, never waived by the executor.
6. **Disclosure** — the pull request and the DONE record say "Codex unavailable — fallback review under CR-001", with the review chain; never "Codex passed".
7. **Return to Codex** — when Codex is available again, its review is requested on the next pull request as usual; the fallback never replaces an available Codex review, and the Owner may revoke CR-001 at any time.

## Alternatives

- **(b) An exception per pull request, as DIR-106** — no standing rule; each outage stops the pipeline until the Owner answers. Safe, slowest.
- **(c) Buy Codex credits** — keeps DIR-096 as written; a recurring paid service needing the Owner's COST_POLICY decision.
- **(d) Wait for the quota to reset** — no cost, unknown delay; the dependent Tasks wait.

## Impact

- Scope: none — a review-process rule; no product, data or security rule changes.
- Time: removes the stop at each outage; one fallback review costs about as much as a Codex round.
- Risk: an internal reviewer may miss what Codex would catch; bounded by conditions 2–5 and by CI, and disclosed under condition 6.

## On approval

The Owner's words transcribed as a source record, a DIR- entry in the [decision log](../DECISION_LOG.md) amending DIR-096's merge rule by reference to this record, and its status here set to ACCEPTED with that entry's link; CURRENT_HANDOFF's rows updated.
