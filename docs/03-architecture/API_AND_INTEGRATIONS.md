# Routes and integration boundaries

Status: APPROVED | Updated: 2026-10-01 | Owner: Planning

Approval: [APPR-007](../00-governance/DECISION_LOG.md#appr-007--p6-concurrency-idempotency-and-performance-approved), explicit conditional Owner approval on 2026-09-30 (DIR-032) of this document as committed in the P6 finalization checkpoint; the approved file hash, the verified conditions and the exclusions are recorded there. Approval changes lifecycle only, not implementation authorization.

Authority: P6 — Concurrency, Idempotency & Performance, authorized by the Owner's fast-track directive [DIR-032](../00-governance/DECISION_LOG.md#dir-032-obs-011-and-tech-021--p6-authorization-entry-baseline-and-concurrency-documentation) (twenty-eighth source record). This document owns **route and integration boundaries**: what kind of routes the application has, when a versioned external API may appear, and the contract any future external event or call must satisfy. The command identity those routes carry is owned by [CONCURRENCY_IDEMPOTENCY](CONCURRENCY_IDEMPOTENCY.md); the place of the integration seam in the code by [ARCHITECTURE §13](ARCHITECTURE.md#13-integration-adapters-and-the-siplah-seam); authentication, sessions and every security control by [SECURITY](SECURITY.md); phase evidence by [P6_QUALITY_GATE](P6_QUALITY_GATE.md).

**Anti-duplication contract:** business meaning stays in V1_SCOPE, DOMAIN_MODEL, BUSINESS_RULES and WORKFLOWS; authorization truth in [PERMISSIONS_MATRIX](../02-domain/PERMISSIONS_MATRIX.md). This document cites their identifiers and never restates, relaxes or extends a rule.

**Boundary:** documentation only, and **it invents no integration**. V1 has no live external integration and its core operation never depends on one (D-03; AC-15; ARCHITECTURE §13; DP-04). Section 2 is a contract for the day a real integration is proposed; nothing in it is built, scheduled or promised for V1.

## Source and version evidence

Read on 2026-09-30 from current official documentation; versions are evidence, not pins (DEP-07).

| Source | Version seen | Facts used |
| --- | --- | --- |
| Inertia — protocol, redirects, responses | v3 (3.7.1) | an Inertia visit expects an Inertia response; any other response — a file, plain JSON, an error page — is handled as a non-Inertia response, so a download must be an ordinary browser request |
| Laravel — HTTP Client | 13.x (13.34.0) | outbound requests have a connect timeout and a total timeout and an explicit bounded retry with a delay between attempts |
| Laravel — Queues | 13.x | after-commit dispatch, attempts and backoff, failed jobs — the job rules of CONCURRENCY_IDEMPOTENCY §15 |

## 1. Route boundaries

| ID | Rule |
| --- | --- |
| RB-01 | Internal pages are Inertia web routes under session and request-forgery protection. They are not converted into a REST API and carry no version prefix (OB §33; ARCHITECTURE §9). |
| RB-02 | Every route is authenticated and declares its capability, with exactly the exceptions EN-03 lists. No route is a second entrance to an effect: web controllers, the import pipeline, jobs and any future API call the same action (EN-05). |
| RB-03 | No GET request changes state (WS-04). Every SF-CMD command is one state-changing request that carries a `command_id` (CI-01) and answers with the outcome vocabulary of WORKFLOWS §1; the authentication requests of CI-09 and the bookkeeping requests — the upload of a file, an export request — have their own mechanisms and carry none. The registration of evidence that follows an upload is a command and carries one (NX-07). |
| RB-04 | File downloads and exports are ordinary browser requests through the authorizing controller (FL-07; RV-08) — never Inertia visits, never signed URLs as the only authorization; a download changes no business state and writes only its session activity and the security event SECURITY requires of it (LG-02). |
| RB-05 | Background requests — polling, deferred and visibility-triggered props, prefetch, thumbnails and previews — are read-only and never extend a session (RV-06; H7-13). Thumbnail and preview routes are background by their route; any other request is background only when the client marks it, and a request that is not a GET is never background. A download the user starts is a user-initiated request. |
| RB-06 | **`/api/v1` does not exist in V1.** It is created only when a real external integration needs it (D-03; DP-04). It is then versioned explicitly; `/api/v2` appears only for a breaking change; nothing is versioned for appearance (OB §33). |
| RB-07 | A future API reuses the actions, policies, scope resolver and projections of the web layer (DP-04); OWNER_ONLY commands are never exposed to it. Its authentication — least privilege, per integration (OB §33) — is designed in SECURITY when it is needed; V1 has no API token, service account or impersonation (AU-01, AU-17, AZ-12). |
| RB-08 | The rate limits of WS-12 apply per route class; a future API receives its own limiter class with the behaviour H6-07 defines. |
| RB-09 | The health endpoint is minimal and reveals no internals (SX-03). |

## 2. Contract for a future external integration

An integration that one day delivers events to, or takes calls from, this system must satisfy every row (OB §28, §34). None is built in V1.

| ID | Requirement |
| --- | --- |
| IC-01 | **Inbox first.** An inbound event is stored in an integration inbox — introduced with the first integration (ARCHITECTURE §13) — by one short transaction, and acknowledged to the sender only after that row commits. |
| IC-02 | **Authentication and signature.** Every inbound event is authenticated before it is parsed: a signature over the raw body with a per-integration secret or key, compared in constant time; credentials are per integration, least-privilege, rotated and stored as secrets (SX-01). |
| IC-03 | **Replay protection.** A signed timestamp inside a tolerance window, together with the uniqueness of IC-04. An event outside the window is refused with an error and stored nowhere, so an honest sender retries or alerts; only an event that is already stored is acknowledged again, without any effect. |
| IC-04 | **Event uniqueness.** UNIQUE (source, event identity) on the inbox: the same event delivered twice is one row. |
| IC-05 | **Idempotency.** A job turns each accepted event into an ordinary action whose `command_id` is the UUID version 5 of its source and event identity (CI-01), so a redelivery or a job retry cannot repeat an effect: a committed outcome is replayed (CI-05, CI-08). |
| IC-06 | **Internal validation.** The action validates allowed fields, company scope, authority and business preconditions exactly as for a user. **External data never mutates stock or money except through the internal actions** (OB §34). An event whose command is refused keeps the refusal on its inbox row for a person to resolve; a deterministic identity records only a committed outcome (CI-08), so the event can be driven again once the cause is removed. |
| IC-07 | **Actor.** V1 allows no service account and no acting on behalf of another account (AU-01; AZ-12). Whose authority an integration's commands carry is therefore an Owner-level decision, to be taken when the first integration is proposed — it is not decided here. |
| IC-08 | **Timeouts.** Every outbound call has a connect timeout and a total timeout — 5 s and 15 s as starting values — and runs in a job: never inside a database transaction (OB §31) and never inside a user's request. |
| IC-09 | **Retry.** Bounded attempts with exponential backoff and jitter, only for transport errors and explicitly retryable answers. A poison event or call ends FAILED on its durable row and is visible to the operator. |
| IC-10 | **Queue.** Inbound processing and outbound calls run on the queue under the job rules of CONCURRENCY_IDEMPOTENCY §15 — after-commit dispatch, identifier-only payloads, a payload version, a sweep that re-dispatches owed work. An outage of the external system delays only its own inbox and never the core application (OB §34). |
| IC-11 | **Logs.** Each event and call is logged with its correlation id, source, event identity and outcome — never a secret, a signature or an S3 value (LG-03, LG-04). |
| IC-12 | **Outbox only when justified.** An outbound effect is recorded as a durable row in the business transaction and sent after commit; a generic transactional outbox is introduced only when a real integration needs one (OB §31; ARCHITECTURE §11). |
| IC-13 | **Identifiers and order.** External identifiers are attributes, never keys (OB §9; DATABASE §16); the order in which events arrive is never assumed. |
| IC-14 | **SIPLAH.** SIPLAH data stays manual project metadata in V1 (CAP-12; BR-FIN-10). A future adapter sits behind the module interface of ARCHITECTURE §13 and satisfies IC-01–IC-13. |

## 3. What V1 does not contain

No external API, API token, webhook endpoint, inbound or outbound integration, integration inbox, or paid messaging or identity service (DEP-08). Adding any of them is a separately authorized change; an integration that would change visibility, authority, cost or scope is an Owner decision under [change control](../00-governance/CHANGE_CONTROL.md#decision-authority).

## 4. Traceability

- **Owner brief:** §9 → IC-13, IC-14; §28 → IC-03–IC-05; §33 → RB-01, RB-06, RB-07; §34 → IC-01–IC-14; §35 → IC-10.
- **ARCHITECTURE:** §9 → RB-01–RB-05; §13 → section 2.
- **SECURITY and PERMISSIONS_MATRIX:** EN-03, EN-05, WS-04, WS-12, FL-07, LG-02 → RB-02–RB-04, RB-08; DP-04 → RB-06, RB-07.
- **Scope and acceptance:** D-03, AC-15, CAP-12 → RB-06, IC-14. **OWNER_DECISION_REQUIRED = 0** — IC-07 names a decision that belongs to a future integration, not to V1.
