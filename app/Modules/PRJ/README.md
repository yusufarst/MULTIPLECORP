# Projects (PRJ)

Tier 2 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-007) — the two guards', `project_line_balances` and `project_commercial_balances`, without a factory, a balance belonging to its posting service (ARCHITECTURE §7) —; its other Tasks add the rest.

Tables ([DATABASE §4.5](../../../docs/04-architecture/DATABASE/s04-05-prj.md), 11): `projects`, `project_siplah_details`, `project_lines`, `commercial_confirmations`, `confirmation_line_pins`, `quotation_revisions`, `quotation_revision_lines`, `line_scope_cancellations`, `project_line_balances`, `project_commercial_balances`, `project_state_transitions`.

TASK-032 adds the project record and its hub:

- `Contracts/` — the two inversion contracts ARCHITECTURE §3 names: `CompletionPredicateProvider` (tag `prj.completion-predicates`), which PUR, INV, FUL, FIN and ADM implement for the completion predicates they own, each a set query over the projects asked; and `ProjectLifecycleParticipant` (tag `prj.lifecycle-participants`) for re-confirmation, cancellation and revalidation, run inside PRJ's transaction.
- `Actions/` — `CreateProject`, `UpdateProject` and `DiscardDraftProject` (ROUTE-151–ROUTE-153; NX-03).
- `Services/` — `ProjectRecords`, the record's writes and its audit; `Journey`, the stage, blocker and next-step derivation, never stored (L-01).
- `Queries/` — `ProjectList` (PG-010 with its journey summary group), `ProjectWorkspace` (PG-011 and its three deferred groups), `ProjectForm` (PG-013 and the resolution of its references through the scope resolver), `ProjectJourneys` (the facts and predicates every journey reads).
