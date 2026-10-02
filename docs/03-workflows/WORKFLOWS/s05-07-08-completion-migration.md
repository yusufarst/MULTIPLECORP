### 5.7 Completion and cancellation

#### WF-PRJ-03 — Project completion (*Penyelesaian*) `[desk]`

Predicates are derived on demand and re-read inside the completion command (BR-PRJ-02; AC completion table):

| # | Predicate | Evaluated over |
| --- | --- | --- |
| 1 | Fulfillment | every line delivered or handed over (net of returns) = pinned demand, or its remainder cancelled |
| 2 | Delivery | every shipment delivered or formally closed; no open discrepancy case |
| 3 | Returns/corrections | no open correction case for the project (returns, discrepancies, damaged-unit outcomes, payment cases), including unbacked-refund and unresolved-deduction residuals |
| 4 | Documents | every required generated document issued, not voided and rendered/available |
| 5 | Administration | every required item satisfied, validly waived (N/A on an eligible item) or removed by the Owner |
| 6 | Billing | every issued non-void invoice billed or voided |
| 7 | Receivable | each invoice's outstanding = 0 through applications and fee/tax settlements, or Owner-disposed; no dispute hold |
| 8 | Stock | no active reservation; no open project-linked purchase remainder without closure; no open count finding or restoration touching the project; no unresolved unusable units and no pending unexplained-loss case whose causal project is this one (L-38; DIR-026) |
| 9 | Payment | no open payment/disbursement correction case; project-originated credit dispositioned; no pre-payment Kuitansi left unlinked and unvoided |
| 10 | Audit | every committed command audited; required evidence present (payment, delivery, closure, settlement); no flagged unevidenced payment |

- **Normal completion (AX-25):** an explicit decision by OWN or ADM when all ten predicates are true at commit (L-12); never automatic; delivery or full payment alone never completes.
- **Force Complete (AX-26, OWN!):** review the blocker snapshot → explicit confirmation bound to that snapshot (changed blockers → CONFLICT) → mandatory reason → record actor, timestamp, before/after predicate snapshot and residual obligations (open receivables stay active and aging; release-required reservations; open cases; missing documents) → nothing is fabricated (BR-PRJ-04). Admin and API attempts are denied.
- **Residual commands** accepted on COMPLETED_* and CANCELLED projects without reopening (L-11) — they may only reduce residual obligations: payments and applications to the project's residual receivables, fee/tax settlements, refunds of project-originated credit, Owner write-off and the Owner's supersession of a write-off to apply recovered money; billing of an issued-unbilled invoice; issuing, revising or voiding collection documents for residual receivables (DOC-05 in either mode, DOC-07, DOC-13); release of residual reservations; receipts on still-open project purchases (goods go to the free pool with no reservation offer); drop-ship confirmations and delivery records for shipments made before completion; CM-07 for shipments in transit; resolution and closure of listed open cases and of unusable units whose causal project is this one, including loss recognition and the resolution of a pending unexplained-loss case (Owner, or `DIR-027` ADM+ from definitive evidence); uploads of late client originals; linking or voiding an outstanding pre-payment Kuitansi. Anything that would raise the project's outstanding or otherwise change a completed result is a correction (SF-REVAL); anything else is REJECTED.
- **Revalidation (SF-REVAL):** a correction is *material* when it changes a predicate input or a project CALC value; a material correction on a completed project returns it to ACTIVE with a link to the correction; the historical completion or Force Complete record stays (BR-CR-05).
- **Corrections on a CANCELLED project:** the section 8 rows apply without any state change — P2 defines no reactivation from CANCELLED — so the project stays CANCELLED while the correction changes only the facts it corrects, and the correction and its residual effects stay visible in QS-16 (L-11).
- **Traceability:** CAP-09/18; AC-21; REF-G05; IMG-08/09; OWN-03; GAP-022.

#### WF-PRJ-04 — Project cancellation `[desk]`

- **Decision (ADM+, reason):** allowed at any stage (BR-PRJ-01); in the same action SYS releases every active reservation; companion corrections are performed first for whatever is actually being undone (returns, invoice voids/revisions with disposition, purchase cancellation or remainder closure, remaining-scope cancellation). Facts that stay true — goods kept by the client, value legitimately owed such as a retained DP — remain recorded; open receivables stay active and collectable (L-10); afterwards only residual commands and section 8 corrections, without reactivation, apply (L-11).
- **Guidance:** a substantially fulfilled deal normally ends by remaining-scope cancellation and normal completion instead.
- **Traceability:** CAP-04/18; AC-12/21; GAP-005.

### 5.8 Migration-adjacent workflow

#### WF-MIG-01 — Opening facts `[desk]`

- **Main path:** ADM prepares an import batch with an import identity per row → isolated dry run → malformed/duplicate rows surfaced → reconciliation to signed control totals per company (stock quantity and cost, receivables, credits) → joint Owner/Admin sign-off → Owner commits (AX-28; L-33).
- **Opening facts:** opening lots per company/product/location/condition with cost, conversion snapshot and serials (BR-INV-12); opening receivables per open invoice with remaining value and due date or `migrasi` aging bucket (BR-FIN-14); opening customer credit for advances held — per client and company, an application source carrying its import identity and the signed opening evidence, never a live-period Cash-In (BR-XD-06; BR-FIN-05; L-50); open projects with remaining demand lines (confirmation evidence = migration record) and their opening receivables, prior deliveries and invoices kept as archive; open purchases with their remaining quantities; reservations re-created afterwards through normal reservation commands; document number sequences seeded per company/type/period after the last legacy number used, so no number collides (BR-DOC-02).
- **Rules:** reruns are idempotent by import identity; archived history is never replayed as current effects (BR-XD-06); wrong opening facts → CM-33.
- **Deferred:** cutover freeze versus delta reconciliation is an Owner agreement at P10 (GAP-010).
- **Traceability:** CAP-15; AC-17; REF-051; GAP-010.

