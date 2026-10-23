# Projects (PRJ)

Tier 2 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-007) — the two guards', `project_line_balances` and `project_commercial_balances`, without a factory, a balance belonging to its posting service (ARCHITECTURE §7) —; its other Tasks add the rest.

Tables ([DATABASE §4.5](../../../docs/04-architecture/DATABASE/s04-05-prj.md), 11): `projects`, `project_siplah_details`, `project_lines`, `commercial_confirmations`, `confirmation_line_pins`, `quotation_revisions`, `quotation_revision_lines`, `line_scope_cancellations`, `project_line_balances`, `project_commercial_balances`, `project_state_transitions`.
