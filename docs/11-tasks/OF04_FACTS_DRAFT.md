# OF-04 facts draft

Status: REVIEW | Updated: 2026-10-31 | Owner: Planning

**A draft, not in force.** Under [DIR-085](../00-governance/DECISION_LOG.md#dir-085-dir-086-obs-034-and-tech-041--og-4-decisions-appr-016-the-planning-freeze-and-the-execution-authorization) OF-04 and record 84 ER-09, the executor drafted with Change 4 ([DIR-100](../00-governance/DECISION_LOG.md#dir-100--change-4-the-contract-rows-and-the-consistency-edit)) one narrow amendment defining the three facts of [P11Q-04](TASK_PLAN.md#p11q-04--facts-the-approved-documents-use-but-do-not-define) and [GAP-068](../00-governance/GAP_REGISTER.md#gap-068--facts-a-rule-depends-on-that-no-approved-document-defines), each the minimum its cited rules need. It takes effect only on the Owner's separate approval of this text. TASK-037, TASK-043 and TASK-077 stay BLOCKED until then, and nothing else waits on it. On approval each definition is copied into the document named under it, marked with its decision.

## F1 — The performed fact of a non-stock purchase line

**Rules that use it.** WF-PUR-01 and AX-12: a purchase is cancelled only with "no performed or paid non-stock line". No document says what makes a line performed.

**Definition.** A `NONSTOCK` purchase line is performed when the Admin records that the supplier has done the work or delivered the service. Being paid is a separate fact: `disbursements` cite the purchase and no line (DATABASE §4.12), so a non-stock line counts as paid when a live PURCHASE_PAYMENT disbursement cites its purchase.

- **Column (DATABASE §4.6).** `purchase_lines.performed_at timestamptz NULL`, with CHECK (`performed_at IS NULL OR kind = 'NONSTOCK'`). The runtime role may update this one column alone, under a trigger, and DATABASE §28's trigger against changing a line with an effect excepts it.
- **Control.** PG-016 "Tandai Terlaksana" on an open purchase's non-stock line, under `purchase.manage`; taking it back when it was recorded in error, with its reason (IP-09), under `purchase.correct_cost`. Both go through one route and interaction, ROUTE-180 and INT-180, written when this draft is approved, in family NX-04, whose name and first-effect condition this draft amends for it. Each is audited with its actor.
- **Effect.** It is read by AX-12's guard under the purchase header and by nothing else. It posts no cost, because the line's cost was attributed at the purchase (L-18), and it is not a first effect under L-23.

**Alternative for the Owner.** Derive the fact with no column, from a service handover (AX-09) on the line's `project_line_id`. It needs no control, but a non-stock line with no project or no handover, such as a freight line, could never count as performed, so AX-12 would let that purchase be cancelled after the work was done.

## F2 — The registration count

**Rules that use it.** L-35 and WF-MD-04: switching a product to serialized needs zero stock or "a registration count that assigns a serial to every on-hand unit". DATABASE §8 reads L-35 as a precondition on `stock_product_balances`. No document defines the count's movement, its serial transition or its posting.

**Definition.** A registration count is a stock count whose every unit on hand of one product gets a serial, applied in one command that changes no quantity and no cost and turns the policy on in the same transaction.

- **Marking (DATABASE §4.7).** `stock_counts.purpose` (`ORDINARY`, `SERIAL_REGISTRATION`), default `ORDINARY`. A registration count's product set is one non-serialized goods product. Its `location_id` and `condition` are NULL, which this draft allows on a registration count alone, with DIR-091's scope trigger amended to match, and its `stock_count_scopes` rows cover every location and condition holding that product.
- **Lines.** Each line records one serial and its lot: `stock_count_lines.lot_id` NULL except on a registration count, because a serial's lot is never chosen later (DATABASE §5.4; L-13).
- **Movement (DATABASE §5.3).** A new type, `SERIAL_REGISTRATION`, in the net-zero groups, per (lot, location, condition): one − entry without a serial and one + entry citing `serial_unit_id`, for each unit. It writes no `lot_cost_entries`, no consumption and no reservation event.
- **Serial transition (DATABASE §8).** A new `serial_units` row, or an existing one that is not IN_STOCK, as a receipt's buy-back does, becomes IN_STOCK at the line's lot, location and condition. A serial already IN_STOCK is refused.
- **Command.** "Terapkan Selisih" under `stock.count_apply` with `products.edit`, which holds the serial policy (PERMISSIONS_MATRIX §4), within AX-06, whose lock set gains LK-08 for the product's catalogue row in §7.1's order, re-read under the product and scope guards: the count is OPEN with its basis versions current; on every (lot, location, condition) the lines equal the balance, a difference being refused so that an ordinary count settles it first; base quantities are integral (BR-INV-12). In the same transaction the product's serial policy is set, which is L-35's precondition being met by the command itself, and the count becomes APPLIED.

**Alternative for the Owner.** Keep L-35's zero-stock path alone in V1, which takes the registration count out (OE-20). The goods would have to be adjusted out and received again, which distorts lots and cost, so it is not recommended.

## F3 — The go-live setting and its event

**Rules that use it.** INFRASTRUCTURE §4.2, HO-32 and NM-03: go-live is recorded once, "as a setting P9 keeps with the deployment and nobody changes afterwards". RG-11 names the operator rule for AU-14's, AU-21's and AU-25's commands and not this one. TASK-021 reads go-live through one interface, `NumberingGoLive`, until a store exists (DIR-098).

**Definition.**

- **Store (INFRASTRUCTURE §4.2).** One file on the deployment's persistent private storage, which every web and worker process mounts: the day as `Y-m-d` in WIB. It is created with exclusive creation, so a second recording finds it and is refused. It is outside the application's write paths, so no web request or job can change it. The `NumberingGoLive` binding reads it. It is listed among the deployment's files to keep in BACKUP_RECOVERY's restore procedure.
- **Why not a database row.** INFRASTRUCTURE §4.2 and HO-32 say the deployment keeps it, and a database restore must not change what go-live was (NM-03; GAP-064).
- **Command.** The operator's console command of PRQ-07, never a web route. It records go-live once.
- **Audit event.** `ORG.NumberingGoLiveRecorded`, a business audit event written once, with `actor_user_id` NULL, `authority_class` SYSTEM, and in place of an application actor the operator's identity as AU-14 records it — the host account and the stated name (RG-11, extended to this command by this definition). The file is created first and the event then committed. A run that finds the file and no event writes the event from the file and changes nothing else, so a failed commit is repaired by running again; a run that finds both is refused and writes nothing.

**Alternative for the Owner.** A one-row database table, insert-only by trigger, making the setting and its event one atomic write. It is simpler to make atomic, but it departs from "with the deployment" and a restore could lose or change the setting, so INFRASTRUCTURE §4.2 and HO-32 would have to be amended as well.

## What approval does

Approving this text, as written or with the Owner's changes, closes GAP-068. It removes P11Q-04 from TASK-037, TASK-043 and TASK-077, adds ROUTE-180 and INT-180 with their LOCALIZATION keys, and amends DATABASE §§4.6, 4.7, 5.3, 8 and 28, CONCURRENCY_IDEMPOTENCY §7.1's AX-06 and §7.3's NX-04, ROUTE-193 and INT-193, INFRASTRUCTURE §4.2, BACKUP_RECOVERY's restore procedure and PERMISSIONS_MATRIX RG-11, each marked with its decision.
