# Identity (IAM)

Tier 0 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-005); its other Tasks add the rest.

Tables ([DATABASE §4.1](../../../docs/04-architecture/DATABASE/s04-01-iam.md), 8): `users`, `roles`, `capabilities`, `role_capabilities`, `user_roles`, `user_capability_grants`, `user_company_grants`, `user_external_identities`.

Also its infrastructure stores outside the 125 business tables: the framework's `sessions` (SECURITY AU-06) and the credential-token store `credential_tokens` (AU-11), whose behaviour is TASK-017's.

## Authorization core (TASK-011)

[PERMISSIONS_MATRIX §2–§6, §9](../../../docs/05-security/PERMISSIONS_MATRIX.md) and [SECURITY EN-01–EN-08](../../../docs/05-security/SECURITY.md#3-enforcement-architecture):

- `Data/CapabilityCatalogue` — the 80 capabilities of §4 with their classes and prerequisites, and §5's WORKFLOWS §4 mapping. `Services/CatalogueSync`, run by `php artisan authorization:sync-catalogue` at installation and after each deployment, adds the missing `capabilities`, the built-in roles and the OWNER role's `role_capabilities`; it never changes or removes a row.
- `Services/EffectiveCapabilities` — the one resolver of EN-02, scoped to a request or job: the role's capabilities plus the active grants, each kept while its prerequisites and, for a command, a company in scope hold (RG-06; CS-05), an OWNER_ONLY capability only through the built-in OWNER role (RG-02), anything else security-logged as an anomaly (OD-16); `Data/Authority` and `Data/CompanyScope` carry the result. `Enums/BuiltinRole` is read only here and by the catalogue sync (AZ-02).
- `Policies/CapabilityGates` — one gate per capability, optionally with a company id. `Services/CommandAuthorization` — RV-01's re-authorization under the actor's row lock, called by the command envelope (TASK-013). `Queries/ScopedRecords` — a public identifier resolved through the scope, out of scope answering as missing (CS-04; AZ-11).
- Routes declare what admits them: `capability:<code>` (`App\Http\Middleware\RequireCapability`) or `access:s0`, `access:self`, `access:enrolment` or `access:none,<EN-03 exception>` (`App\Http\Middleware\DeclareAccess`); the route audit (`tests/Routes/RouteAuditTest.php`; SM-02) fails any other.

## Field projection (TASK-012)

[PERMISSIONS_MATRIX §7, §8, §11](../../../docs/05-security/PERMISSIONS_MATRIX.md#7-resource-and-field-projection):

- `Data/TableClassification` — §7.2's 125 tables with their scope and classes, held to the document by a test; `Data/Sensitivity` — §7.1's entitlement of each class.
- `Data/Audience` — §7.2's audiences (ALL, SV, PV(c), FV(c), CV(c), EV(c), `(any)`, OWN, SELF, none) for each field; `Data/ReadClass` — §7.4's RD-01–RD-10; `Data/CrossCompanyLink` — §8's five link families with their columns and rules; `Data/Projection` — the relation marker (PJ-04), the two sides of an allocation (PJ-05, PJ-06) and a derived figure's entitlement (PJ-22).
- `App\Http\Resources\ProjectedResource` — the base of every prop resource: each field with its audience, sent and computed only for a viewer it admits; `ProjectedResource::props` gates page props before evaluation, so a partial reload cannot name an unauthorized one (WS-07; DP-15). `INV\Http\Resources\InventoryLotResource` is the first, a lot's pooled view (PJ-01, PJ-02, PJ-04). Each page's Task writes the resources of its tables, with their field exceptions of §7.2, on this base.
- `tests/Support/DataPaths.php` — §11's twenty data paths, each with the tests that prove it or the Tasks that build it.
