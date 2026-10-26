# TASK-022 — Jobs, files and the document renderer

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Run background work and files safely: the queue and its jobs with their scheduler, the private file storage with its metadata and serving routes, uploads under FL-01–FL-11, the image and thumbnail pipeline, the document renderer and its sweeper, and the status route pages poll.

**WHY THIS EXISTS:**

Renditions, exports, imports and thumbnails run in the background (ARCHITECTURE §11–§12; JB-01–JB-09; SF-RENDER); private business files must never leak (FL-01–FL-11; SM-09, SM-10); GAP-007 asks that an issued document and its file stay consistent through a crash after commit.

**DEPENDS ON:**

- TASK-006 — Schema — ORG, PTY, CAT and DOC tables
- TASK-012 — Field projection, read classes and cross-company links
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-015 — Design system — tokens, components, patterns and states
- TASK-017 — Password sign-in, sessions and credential links

**BLOCKS:**

- TASK-025 — Companies, identity assets, bank accounts and tax configuration
- TASK-027 — Catalog — products, services, units, barcodes, prices and images
- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-030 — Evidence and uploaded documents
- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-060 — Reports, the consolidated report and exports
- TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards
- TASK-074 — Backup tooling, the off-server copy and the drill procedures
- TASK-075 — Monitoring — the business heartbeat, alerts and the uptime check

**PARALLEL SAFE:** NO — it owns the queue, the storage and the renderer every document and export Task uses

**IN SCOPE:**

- the queue on Valkey with its workers, retries, payload versions and the scheduler as CONCURRENCY_IDEMPOTENCY §15 states (JB-01–JB-09; M-31–M-33)
- the storage abstraction of ARCHITECTURE §12 with the file metadata of DATABASE §22: private files outside the web root, served only by `DOC DownloadFile` and `DOC ShowThumbnail` (ROUTE-226, ROUTE-227; INT-014) after authorization and projection
- uploads under FL-01–FL-11 — type and size checks, content inspection, duplicate detection (BD-15), the image library's allowlisted decoders (H9-12) —, with the upload field's presentation (INT-013; IP-16)
- the thumbnail and image rules of PERFORMANCE §8 (PF-31–PF-36; PT-22)
- the renderer's pipeline: an issued version rendered in the background, PENDING and FAILED visible, retried by the sweeper, never reissuing (SF-RENDER; C-12; BR-DOC-03)
- ROUTE-285 and INT-285: the status a page polls for a deferred group — renditions, exports, the import batch — background, never extending the session (RB-05)
- the optional malware scanner of FL-11 left off unless TASK-079's capacity decision adopts it (H9-05; INFRASTRUCTURE P9Q-06)
- HO-20's framework behaviours the jobs rely on, each verified by a TL-05 test so that a changed version fails it (TEST_STRATEGY §6): cache locks taken inside a transaction on the configured store, Valkey (INFRASTRUCTURE §2) — no database cache store is configured, so HO-20's database-store case is recorded as not applicable with that reason —; batched jobs — none in V1, JB-07's import commit being a plain queued job and the export request of ARCHITECTURE §11 replacing a job batch, so an architecture test fails if any job uses the framework's batching, and no job-batch table is created —; and the scheduler with its store down

**OUT OF SCOPE:**

- what a document renders — its template and payload (TASK-029 and the issuing Tasks)
- the evidence pages and commands (TASK-030)
- exports' content (TASK-060)

**REFERENCES:**

- [ARCHITECTURE §11](../04-architecture/ARCHITECTURE.md#11-queue-and-background-jobs)
- [ARCHITECTURE §12](../04-architecture/ARCHITECTURE.md#12-file-storage-abstraction)
- [CONCURRENCY_IDEMPOTENCY §15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue)
- [SECURITY §5](../05-security/SECURITY.md#5-file-security) FL-01–FL-11
- [DATABASE §22](../04-architecture/DATABASE/s20-24-index-storage-migration.md#22-file-and-document-storage-metadata)
- [PERFORMANCE §8](../06-api-performance/PERFORMANCE.md#8-images-and-inertia-props)
- [WORKFLOWS §6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-RENDER
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#documents-files-and-evidence-doc-pg-035-pg-036-pg-038-wf-doc-01-wf-doc-02) ROUTE-226, ROUTE-227, ROUTE-285
- [ADMIN_FLOW §8](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#8-background-work) IP-12
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-09, SM-10
- [TEST_STRATEGY §6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs) HO-20
- the architecture challenge's sequences 7a, 15a ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (75): O-06; CAP-08; CAP-13; OS-03; REF-056; OWN-02; SF-RENDER; ROUTE-226–ROUTE-227; ROUTE-285; INT-013–INT-014; INT-226–INT-227; INT-285; FL-01–FL-11; TM-16; TM-20; TM-23; TM-37–TM-42; TM-46; TM-55; H9-05; H9-12; BD-15; JB-01–JB-09; M-31–M-33; HO-06; HO-20; PF-31–PF-36; UXI-09; UXI-12; UXI-29; UXI-33; IP-12; IP-16; UXS-09; UXS-17–UXS-18; UXS-23; UXS-36; PT-22; SM-09; component Upload; GAP-007

**EXPECTED MODULES / FILE AREAS:**

- the platform queue and scheduler configuration, the storage service, the file controllers, the renderer service and its sweeper

**BUSINESS RULES:**

- BR-DOC-03: rendering never reissues; C-12

**ARCHITECTURE RULES:**

- jobs carry payload versions valid for one release (PRQ-04); a job never holds the only copy of a business fact; Valkey is never a system of record (ARCHITECTURE §1)

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-16, Export of Company B data, including a file generated before a partial revocation (DP-07, FL-09); TM-20, Download of an issued PDF, evidence or import file of Company B (DP-05, FL-07, PJ-15, PJ-20); TM-23, Duplicate-evidence oracle across companies or evidence families (DP-13, FL-10); TM-37, Stored XSS rendered in React or a PDF (WS-09, FL-08); TM-38, SVG, polyglot or active office upload (FL-02, FL-03, FL-05, FL-07); TM-39, Path traversal on storage or download (FL-07); TM-40, SSRF through the renderer (FL-08); TM-41, Command injection, SSRF or file reads through the renderer or image decoders (FL-08, FL-03); TM-42, Decompression, zip or size bombs (FL-02, FL-03, FL-06, WS-12); TM-46, CSV or spreadsheet formula injection into the Owner's workbook (FL-09); TM-55, Evidence of an invisible family uploaded, versioned or matched to tamper with it or probe for it (PJ-08, DP-13, FL-10)
- private files never under the web root; every download authorized and projected (PJ-08; PJ-15); the upload checks of FL-; the decoders allowlisted (H9-12); the CSP unchanged

**COMPANY SCOPE AND FIELD PROJECTION:**

- a file is served only within the viewer's scope and family — evidence by `evidence.view` with its family's view capability (PJ-08)

**CONCURRENCY / IDEMPOTENCY:**

- job retries idempotent by their keys; the sweeper never renders twice in parallel (M-31–M-33); a crash after commit leaves a PENDING rendition the sweeper completes (GAP-007)

**AUDIT:**

- file and rendition failures logged; downloads of private evidence recorded where SECURITY §6 asks

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-005 and TASK-006
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: platform infrastructure and DOC's file services
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)
- the renderer and storage behind small interfaces, replaceable

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — the framework's queues, scheduler and filesystem; the chosen PDF renderer and image library at their versions, licences at no cost
- Graphify: N/A — the codebase is young
- Browser E2E: REQUIRED — an upload and a download on a sample page
- Other: the renderer and image library verified and recorded in TOOLCHAIN
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which Tasks will enqueue jobs and serve files
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: queue retries and backoff; signed or controller-served downloads; the PDF renderer's fonts from the application's origin
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES
- Language switch impact: NONE — status texts are keys
- Mobile-first: YES — uploads from the phone
- Responsive: YES
- Adaptive: N/A — one behaviour on every device
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the upload and status components of TASK-015
- Design reference: DESIGN_SYSTEM §10 *Upload* and PT-22
- Primary action: N/A — no business command
- Secondary actions: download, retry where IP-12 allows
- Destructive actions: none
- Redundancy constraints: N/A — no command control
- Loading state: PENDING shown (IP-12)
- Empty state: N/A — a status always has a state
- Error state: FAILED shown with its retry (IP-12)

**ROUTE IMPACT:**

- ROUTE-226 (download), ROUTE-227 (thumbnail), ROUTE-285 (status polling)

**INTERACTION CONTRACTS:**

- INT-013, INT-014, INT-226, INT-227, INT-285

**NAVIGATION CONTRACT:**

- Parent: N/A — no page of its own; downloads and status serve other pages
- Entry: N/A — no page of its own; downloads and status serve other pages
- Back action: N/A — no page of its own; downloads and status serve other pages
- Cancel: N/A — no page of its own; downloads and status serve other pages
- Success destination: N/A — no page of its own; downloads and status serve other pages
- Error behavior: N/A — no page of its own; downloads and status serve other pages
- State preservation: N/A — no page of its own; downloads and status serve other pages
- Mobile behavior: N/A — no page of its own; downloads and status serve other pages

**ACCEPTANCE CRITERIA:**

1. No private file is reachable by its storage path; each download passes authorization and projection.
2. An upload outside FL-'s types or sizes, or with mismatched content, is refused; a duplicate is detected (BD-15).
3. A rendition killed mid-way is completed by the sweeper, once, and the issued version is never reissued; a revision issued while its predecessor's rendition is pending is a new version, and the old rendition stays the old version's (C-11, C-12; SF-RENDER; architecture challenge 7a, with TASK-029).
4. One template per document layout is read by both the composer's preview and the renderer, and a number is assigned only at issue (D-DS-23; DESIGN_SYSTEM §14.3; SF-ISSUE; architecture challenge 15a, with TASK-029).
5. The status route answers without extending the session (RB-05).
6. Thumbnails and images follow PF-31–PF-36.
7. HO-20's job behaviours each pass a TL-05 test that fails on a changed framework version — cache locks inside a transaction on Valkey, no job using the framework's batching (JB-07; ARCHITECTURE §11), the scheduler with its store down —, and the database-store case is recorded as not applicable, no database cache store being configured (TEST_STRATEGY §6; INFRASTRUCTURE §2).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the upload checks and the payload versions (TL-03)
- Feature: ROUTE-226, -227, -285 with denials (TL-04)
- Integration: workers, retries, the sweeper after a simulated crash (TL-05; M-31–M-33); HO-20's cache locks inside a transaction on Valkey, the architecture test refusing a batched job, and the scheduler with its store down
- Authorization: downloads by family and scope (TL-06)
- Route integrity: ROUTE-226, -227, -285 audited (TL-07)
- Interaction: INT-013, INT-014, INT-285 (TL-08)
- Browser E2E: upload and download on a sample page (TL-09)
- Localization/language switch: status texts in both languages (TL-10)
- Visual breathing room/density: N/A — components of TASK-015
- Responsive: the upload field at TL-11's bands
- Accessibility: the upload field's labelling (TL-12)
- Production build: TL-13
- Security: SM-09, SM-10 (TL-14)
- Performance: thumbnail cost known (PFM-09) and renditions within budget (PFM-16) (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-007's crash-after-commit case

**TEST CHECKLIST:**

- [ ] Lint
- [ ] Typecheck
- [ ] Relevant automated tests
- [ ] Authorization
- [ ] Route integrity
- [ ] Interaction behavior
- [ ] Browser E2E if required
- [ ] Responsive/adaptive if required
- [ ] Visual breathing room/density if UI
- [ ] Production build
- [ ] Security checks if required
- [ ] Database safety review
- [ ] Final diff review

**COMPLETION EVIDENCE:**

- Test outputs: each required layer's result in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form, tied to the tested commit (DOD-04)
- Build result: the production build of TL-13
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-025 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- serve a private file from a public path or a guessable URL
- enable a decoder or a scanner the allowlist or the capacity decision does not name
