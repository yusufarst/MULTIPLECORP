# TASK-022 — Jobs, files and the document renderer

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-27 the Owner's decision [DIR-099](../00-governance/DECISION_LOG.md#dir-099--task-022s-choices) changes two lines of the DATABASE IMPACT ASSESSMENT: the runtime role's `UPDATE` of `documents.mode`, `document_types.is_numbered` and `opening_customer_credits.import_row_id`, for the row locks of LK-30, LK-08 and LK-26 alone, and a trigger on each refusing every update of its rows, by the migration `2026_10_27_000143`; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

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

- Schema change: a privilege only — the runtime role's `UPDATE` of `documents.mode`, `document_types.is_numbered` and `opening_customer_credits.import_row_id`, so that it can take the row locks of LK-30, LK-08 and LK-26, and a trigger on each refusing every update of its rows (DIR-099); no table or column — otherwise uses TASK-005 and TASK-006
- Migration required: YES — one expand-only, privilege-only migration, `2026_10_27_000143`, touching no row (DIR-099)
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

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Feature/Files/FileRoutesTest.php` — a rendition downloaded by its family's viewer with FL-07's attachment profile; a denied, an unknown, a malformed and a storage-path address answered alike, the denial security-logged; no export or unowned upload served —; `tests/Authorization/FileAccessTest.php` — every purpose by family and scope (PJ-08, PJ-15, PJ-19, PJ-20, rows 27 and 124) —; `tests/Interaction/FilesTest.php` — authorization at request time. Criterion 2: `tests/Unit/DOC/UploadInspectionTest.php` — FL-01–FL-06's refusals, SM-09's elements of H8-09 —; `tests/Interaction/FilesTest.php` — a duplicate of evidence content and a reproduction of a rendition found by their checksum (BD-15; FL-10; FL-05), their disclosure TASK-030's (DP-13). Criterion 3: `tests/Integration/Jobs/RenditionPipelineTest.php` — a rendition rendered once, the version untouched; a worker's abandoned attempt taken over and one READY in the end; an abandoned RENDERING attempt re-dispatched by the sweeper after 10 minutes and a crash before dispatch after 2 (M-31, M-32, GAP-007); five attempts with their backoff; a revision issued while the predecessor's rendition is pending leaving it the predecessor's; LK-01's projects locked before LK-30 —, with TASK-029. Criterion 4: with TASK-029 — the renderer reads the one template TASK-029 writes (`RenditionTemplate`), and TASK-022 issues no number. Criterion 5: `tests/Feature/Files/FileRoutesTest.php` — the status and the thumbnail write no session, a download does (RB-05; RV-06). Criterion 6: `tests/Integration/Jobs/ScheduledWorkTest.php` and `FileRoutesTest.php` — JB-04's 80 px thumbnail and preview written once, a missing one owed and answered absent (PF-31, PF-32; PT-22) —; `tests/Performance/FileRequestsTest.php`. Criterion 7: `tests/Integration/Jobs/FrameworkBehavioursTest.php` — a cache lock inside a transaction on Valkey, the scheduler with its store down —; `tests/Architecture/JobsTest.php` and the scan rule `job-batching` — no batched job, no job-batch table —; the database-store case not applicable, no database cache store being configured

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS locally and in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS locally and in CI (TL-02)
- [x] Relevant automated tests: Unit — the upload checks, the families and the payload versions (TL-03); Feature — ROUTE-226, ROUTE-227 and ROUTE-285 with their denials (TL-04); Integration — the rendition attempts on real PostgreSQL with the real renderer, HO-20 on Valkey, the upload pipeline, JB-04, JB-08 and JB-09 (TL-05); PASS locally and in CI
- [x] Authorization: downloads by family and scope for every purpose (TL-06)
- [x] Route integrity: ROUTE-226, ROUTE-227 and ROUTE-285 in the route audit and the route list (TL-07)
- [x] Interaction behavior: INT-013, INT-014, INT-226, INT-227 and INT-285 (TL-08)
- [x] Browser E2E if required: an upload and a download on the sample page, MSG-31's refusal and the rendition's states, on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: the upload field at 320, 390, 768, 1,280 and 1,680 px without a sideways scroll (TL-11)
- [x] Visual breathing room/density if UI: N/A — TASK-015's upload, status and preview components, nothing hand-styled
- [x] Production build: TL-13 PASS locally and in CI; the sample page local-only
- [x] Security checks if required: SM-09's upload elements and SM-10's renderer and decoder elements — network and file reads refused, a missing asset stopping the render, crafted decoder inputs (TL-14; TL-05)
- [x] Database safety review: one expand-only, privilege-only migration under DIR-099 (L) with APPR-026 — the runtime role's `UPDATE` of one column of `documents`, `document_types` and `opening_customer_credits` for their row locks alone, a trigger on each refusing every update —, touching no row; local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources; ER-06's one independent review, its points fixed and tested

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `70f3ba6`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40411469632 (push) and 40411475362 (pull request #27), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration". On the Owner's workstation, 2026-10-27: the suites Unit 314, Architecture 54, Feature 200, Integration 948, Authorization 120, Routes 11, Interaction 24 and Performance 7; TypeScript, ESLint, the frontend tests, Pint, Larastan/PHPStan, the production build, and the sample page's E2E, Localization, Responsive and Accessibility specs on Chromium and WebKit PASS
- Build result: `TL-13 Frontend build: PASS — Evidence: 70f3ba6, CI ubuntu-24.04 run 40411469632`
- Route evidence: TL-07's route audit PASS — ROUTE-226 `GET /berkas/{id}/unduh`, ROUTE-227 `GET /berkas/{id}/gambar-kecil` and ROUTE-285 `GET /status/{kind}/{id}`, each `access:s0` with the owning record or the page's query class deciding —; `tests/Routes/RouteAuditTest.php`, `tests/Feature/BootTest.php`
- Interaction evidence: `tests/Interaction/FilesTest.php` (INT-013, INT-014, INT-226, INT-227, INT-285)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 70f3ba6, CI ubuntu-24.04 run 40411469632`; TL-10, TL-11 and TL-12 for the sample page in the same run
- Localization evidence: the upload field and the rendition's states in both languages (`tests/Browser/Localization/files.spec.ts`) (DOD-16); no key added — the catalogue's own
- Performance evidence: PFM-09 — an uncached thumbnail answered with 2 statements in about 19 ms, measured, no write —; PFM-16 — an issued version to READY in about 3.4 s on the real renderer, within 30 s (TL-15)
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — WeasyPrint, GD, zip, DejaVu and Inter at no cost (DIR-099)
- Files changed: `app/Modules/DOC/` — `Contracts/` (the renderer and the template), `Data/`, `Enums/`, `Http/` (FileController, FileResponses), `Jobs/` (`RenderDocument`, `RenditionSweeper`, `ProcessImage`), `Queries/` (`FileAccess`, `FileDownloads`, `RenditionStatus`, `ContentMatches`), `Services/` (the upload pipeline, the image codec, the office inspection, the store, the renditions, the WeasyPrint renderer) —; `app/Support/Jobs/`, `app/Support/Status/`; `app/Console/Commands/` (`SweepRenditions`, `PurgeCommandLog`, `SweepOwedWork`); `app/Http/Controllers/StatusController.php`, `app/Http/Middleware/SecurityHeaders.php`; `bootstrap/app.php` (the schedule); `config/`; `routes/`; `database/migrations/2026_10_27_000143_doc_make_lock_order_rows_lockable.php`; `docker/php/Dockerfile`; `resources/renderer/`; `resources/js/` (the upload client, the status poll, the rendition status, the thumbnail, the sample page); `tests/`; DIR-099's records, APPR-026's amendments and TOOLCHAIN's row; this file, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `4cd802b`, `2df9194`, `dc1ccf6`, `adb34ef`, `30eeeaf`, `c5b761c`, `c68cad7`, `fbf90f4`, `04aa984`, `d41aa6c`, `79550b7` and `70f3ba6`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-022-jobs-files-renderer`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-27; Context7 consulted for WeasyPrint's URL fetcher and Laravel's queues and scheduler (TOOLCHAIN's TASK-022 row); CI the final proof. The Owner decided at one checkpoint DIR-099 — C1 WeasyPrint, C2 GD, reading (1) the jobs' ownership — and, on the discovery that the runtime role could not lock `documents`, `document_types` and `opening_customer_credits`, DIR-099 (L) with APPR-026's text. Readings within the approved text: the export, import-validation and import-commit jobs and their branches of M-32, and M-33, are TASK-060's and TASK-057's, registering their sweeps at JB-09's point and their states at ROUTE-285's; the upload route ROUTE-228, its limiter of WS-12 and the duplicate warnings' disclosure (DP-13) are TASK-030's, which calls the pipeline and owes an image's derivatives to JB-04; the renderer's inline profile and ROUTE-225 are TASK-029's, with the templates; an evidence photo's re-encoded preview is ROUTE-227's `varian=pratinjau`; no download of private evidence is a security event, SECURITY §6 naming none beside the exports'; a pool OPENING_SIGNOFF opens to the Owner and to `import.prepare` over every company; the failed jobs are kept 14 days, within CI-07's horizon; the optional scanner stays off (H9-05). ER-06's one review found no HIGH point; its MEDIUM points — a DDE field hidden by joining field codes, a relocated part unscanned, a decode's memory — and its LOW points — a failed finish's bytes, the renderer's own CPU bound, a DTD in UTF-16, a newer payload's attempts, an unused inline profile, the sweep's spent attempts, a file's own disk — fixed and tested, a pool sign-off's stricter rule kept. Codex's one P2 on pull request #27 — the DDE field boundaries — fixed and tested. A TASK-017 test whose fixed instant passed AU-07's 12 hours on 2026-10-26 now reads an hour ago. The READY gates of the Tasks TASK-022 blocks were re-checked at this DONE: TASK-025, TASK-027 and TASK-030 READY; TASK-029 waits on TASK-025, TASK-060 on TASK-037, TASK-051 and TASK-054, TASK-074 on TASK-029 and TASK-072; TASK-057, TASK-072 and TASK-075 stay BLOCKED on their inputs. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

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
