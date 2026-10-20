# Organization (ORG)

Tier 1 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-006) but `number_sequences`', a counter being drawn by one statement, never through a model (CONCURRENCY_IDEMPOTENCY NM-02); its other Tasks add the rest.

Tables ([DATABASE §4.2](../../../docs/04-architecture/DATABASE/s04-02-org.md), 9): `companies`, `company_bank_accounts`, `numbering_schemes`, `number_sequences`, `retired_numbers`, `tax_types`, `tax_rate_versions`, `company_tax_profiles`, `tax_treatment_rules`.
