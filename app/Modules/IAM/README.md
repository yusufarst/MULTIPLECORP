# Identity (IAM)

Tier 0 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-005); its other Tasks add the rest.

Tables ([DATABASE §4.1](../../../docs/04-architecture/DATABASE/s04-01-iam.md), 8): `users`, `roles`, `capabilities`, `role_capabilities`, `user_roles`, `user_capability_grants`, `user_company_grants`, `user_external_identities`.

Also its infrastructure stores outside the 125 business tables: the framework's `sessions` (SECURITY AU-06) and the credential-token store `credential_tokens` (AU-11), whose behaviour is TASK-017's.
