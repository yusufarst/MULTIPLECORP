# Fulfillment (FUL)

Tier 6 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables, each with a synthetic factory (TASK-009); its other Tasks add the rest.

- TASK-047, the service handover (WF-FUL-04; AX-09): `Actions/` `RecordHandover`, `ReverseHandover`; `Services/` `HandoverPosting` (the writes, through PRJ's `DemandPosting`), `ServiceHandoverSubjects` and `HandoverFacts` (DOC-08's `DocumentSubjectProvider`), `HandoverPredicate` (the fulfilment predicate's service lines); `Queries/ServiceHandovers` and `Http/Controllers/HandoverController` for PG-033.

Tables ([DATABASE §4.9](../../../docs/04-architecture/DATABASE/s04-09-ful.md), 7): `dropship_confirmations`, `dropship_confirmation_lines`, `deliveries`, `delivery_lines`, `delivery_closures`, `service_handovers`, `service_handover_lines`.
