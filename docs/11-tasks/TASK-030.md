# TASK-030 — Evidence and uploaded documents

Status: APPROVED | Updated: 2026-10-23 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-23 the Owner's decision [DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) (2) gives this Task the type-free evidence identity index of DATABASE §4.10 — a scope line, the database impact for that index alone, criterion 11 and its performance test; and DIR-089 (5) criterion 12 and its authorization test; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build external and uploaded evidence: the upload route every upload field uses, the evidence's registration with its identity per company, its versions and the current version, its links to records and requirements with their replacement, the pool evidence of shared stock, the duplicate warnings disclosed only within the actor's view, and PG-038 *Bukti* (ROUTE-038, ROUTE-228–ROUTE-231; WF-DOC-02; SF-EVIDENCE; J-DOC-02).

**WHY THIS EXISTS:**

Authentic external originals — client SPK and orders, SIPLAH orders, third-party HPS, tax and bank documents, delivery and payment proofs — are evidence, never generated, and their identity per company decides what they may prove: re-attaching one versions it, never duplicates it, and its proven amount caps every settlement, expense and charge citing it (WF-DOC-02; SF-EVIDENCE; L-45; FS-12; architecture challenge 6d). Replacing evidence must re-evaluate what depended on it (L-41; architecture challenge 20a), and an evidence match must never become an oracle across companies or families (DP-13; TM-23; GAP-034). Requirements, payments, expenses and the import (TASK-035, TASK-051, TASK-054, TASK-057) attach their proofs through it.

**DEPENDS ON:**

- TASK-016 — Application shell, navigation and the company context
- TASK-022 — Jobs, files and the document renderer

**BLOCKS:**

- TASK-034 — Commercial confirmation and fulfillment planning
- TASK-035 — Administrative checklist and document readiness
- TASK-036 — Costing foundations — lot cost basis, charge allocation and HPP attribution
- TASK-051 — Payments, applications and bank statement lines
- TASK-054 — Expenses, disbursements and other cash
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — it owns the evidence rows (LK-29) and the registration keys (LK-28) that every proof-bearing command cites

**IN SCOPE:**

- ROUTE-228 with INT-228 and INT-013 (`DOC StoreUpload`): one upload route for every upload field — PG-038's sheet, "Buat" › *Unggah Dokumen*, readiness "Unggah", the proof fields of PG-022, PG-031 and PG-046, the company assets, product images and import sources — each purpose under its capability (PJ-08; PJ-20) and its allowlist, running TASK-022's pipeline outside every business transaction (FL-01–FL-06; FL-11), carrying no `command_id` (RB-03), within the upload limit and the daily byte budget (WS-12), and answering the file's id for the command that follows (component Upload; IP-16)
- ROUTE-229 with INT-229 (NX-07): the registration — evidence type, issuer, the issuer's reference, document date and, for an amount-bearing type, the proven amount; the company, or *Gudang bersama* for the pool types WAREHOUSE_RECORD and OTHER with the uploader's tick of MSG-32 (H7-11; PJ-08) —; an identity that already exists in the company offered as "Tambahkan sebagai Versi Baru", never a second record (L-45; BD-14); a reference-less match and a content or cross-type match the actor may view raising IP-05's warning with `warnings.override`, any other match showing nothing (FL-10; DP-13; BD-15; UXS-17; UXS-18)
- ROUTE-230 with INT-230 (NX-07): a new version of an evidence; a version of evidence already linked or cited becomes current only through "Jadikan Versi Berlaku" (NX-14)
- ROUTE-231 with INT-231 (NX-14): link, unlink with a reason and replace an evidence on its record or requirement; a replacement re-evaluating the requirements, settlements and completion that depended on it inside DOC's transaction through `DocumentChangeParticipant` (L-41; BR-ADM-03; architecture challenge 20a)
- the evidence guard: an amount-bearing evidence's proven amount capping every settlement, expense and charge citing it, and its type bound to the roles that may cite it, so re-registering a document under another type gains no capacity (C-18; C-58; DATABASE §4.10; architecture challenge 6d)
- a reproduction of a company document — an upload whose checksum equals a stored rendition — always recorded as one and never satisfying a client original, its flag shown only to a viewer who may see that rendition (FL-05; FS-12; IP-16 step 5; GAP-034)
- PG-038 with NAV-038 and its view ROUTE-038 (`evidence.view` with the family's view capability; SCR-36): evidence by branch and pool evidence, its versions and links; an uploaded file opened only as a download, an image through its re-encoded preview (FL-03; FL-04; FL-07; DESIGN_SYSTEM §14.3; UXI-33)
- the type-free evidence identity index of DATABASE §4.10 ([DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) (2)): the issuer's normalization defined once (L-45) and used alike by `identity_key` and by the index; the index designed for the queries that read it — FL-10's cross-type warning among them, whose matching runs across the workspace, across companies —, any column order other than §4.10's recorded there as a Level 1 amendment; added expand-only

**OUT OF SCOPE:**

- the upload pipeline's checks, the image decoders, the storage, the downloads and thumbnails (TASK-022) and the optional malware scanner, off unless TASK-079 adopts it (FL-11)
- what each record does with its evidence — settlements, payments, bank lines, expenses, requirements and the import (TASK-035, TASK-051, TASK-052, TASK-054, TASK-057); exports and their formula neutralization (FL-09; TASK-060)
- the generated documents and their renditions (TASK-029)

**REFERENCES:**

- [WORKFLOWS §5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-02--external-and-uploaded-documents-both) WF-DOC-02; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-EVIDENCE, SF-SETTLE; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-41, L-45
- [BUSINESS_RULES §7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-12; [BR-ADM-03](../02-domain/BUSINESS_RULES.md#administration-adm)
- [DATABASE §4.10](../04-architecture/DATABASE/s04-10-doc.md#410-doc--documents--evidence); [§10](../04-architecture/DATABASE/s07-10-representations.md#10-document-architecture) external evidence and authenticity; [§22](../04-architecture/DATABASE/s20-24-index-storage-migration.md#22-file-and-document-storage-metadata); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-18, C-58
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `DocumentChangeParticipant`; [§12](../04-architecture/ARCHITECTURE.md#12-file-storage-abstraction)
- [SECURITY §5](../05-security/SECURITY.md#5-file-security) FL-01–FL-11; [§8](../05-security/SECURITY.md#8-threat-model) TM-16, TM-20, TM-23, TM-37–TM-42, TM-46, TM-55; [§10 — P7](../05-security/SECURITY.md#p7--presentation-only) H7-07, H7-11; [§4](../05-security/SECURITY.md#4-web-and-input-security) WS-12
- [PERMISSIONS_MATRIX §7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 90–95; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-20; [§10](../05-security/PERMISSIONS_MATRIX.md#10-owner-review-queue-access); [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-05, DP-13
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#documents-files-and-evidence-doc-pg-035-pg-036-pg-038-wf-doc-01-wf-doc-02) ROUTE-228–ROUTE-231; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-038
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#documents-files-and-evidence-doc-pg-035-pg-036-pg-038-wf-doc-01-wf-doc-02) INT-228–INT-231; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-013, INT-015
- [NAVIGATION_CONTRACTS §2.4](../07-ux-design/NAVIGATION_CONTRACTS.md#24-fulfilment-cases-and-documents) NAV-038; [INFORMATION_ARCHITECTURE §8.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#84-fulfilment-cases-and-documents) PG-038
- [ADMIN_FLOW IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload); [IP-05](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-05--duplicate-warnings); [J-DOC-02](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#105-documents-and-administration); [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#duplicates) UXS-17, UXS-18; [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-33; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-15, MSG-31, MSG-32
- [DESIGN_SYSTEM §10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition) the component Upload; [§14.3](../07-ux-design/DESIGN_SYSTEM.md#143-document-preview)
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-07, NX-14; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-14, BD-15; [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-10
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-09, SM-12, SM-23, SM-25; [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-12, QB-16
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-034--two-owner-review-items-have-no-record-to-be-listed-from) GAP-034
- Architecture challenge — the sequences 6d, 20a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (52): CAP-09; REF-034; IMG-06; WF-DOC-02; SF-EVIDENCE; ROUTE-038; ROUTE-228–ROUTE-231; INT-013; INT-228–INT-231; FL-01–FL-11; TM-16; TM-20; TM-23; TM-37–TM-42; TM-46; TM-55; NX-07; NX-14; BD-14–BD-15; PG-038; SCR-36; UXI-33; IP-16; J-DOC-02; UXS-17–UXS-18; NAV-038; SM-09; component Upload; GAP-034

**EXPECTED MODULES / FILE AREAS:**

- the DOC module's StoreUpload, RegisterEvidence, AddEvidenceVersion, LinkEvidence, UnlinkEvidence and ReplaceEvidence actions with their policies and the evidence query classes (RC-02); `resources/js/Pages/DOC` for PG-038 and the upload field of the component Upload; `tests/Feature/DOC` and `tests/Concurrency/`

**BUSINESS RULES:**

- FS-12 and L-41: an upload identical to a system rendition never satisfies a client-original requirement, official external documents are never generated, and replacing an external document re-evaluates what depended on it (WF-DOC-02; BR-ADM-03)
- L-45: the evidence identity is issuer, document type, the issuer's reference and document date per company — a bank statement line by account, date, amount and reference —; the same identity versions the evidence, and a reference-less document raises a duplicate warning that needs an ADM+ override with its reason
- no company-issued document proves that money was received (SF-EVIDENCE); pool evidence holds only physical content of shared stock (PJ-08)

**ARCHITECTURE RULES:**

- the upload runs outside every business transaction and the registration that follows is a command (FL-01; CI-09; NX-07); the FL-10 lookups run at upload on their indexes, after the registration keys are taken and before the registration's transaction opens, and no linking, replacing or citing command runs them (H6-09; BD-15)
- bytes are immutable `file_objects`, private, under generated keys; a version never overwrites a file (DATABASE §4.10, §22; ARCHITECTURE §12)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-228 by purpose: evidence `evidence.upload` with the family's view capability (PJ-08); a company asset `companies.manage`; a product image `products.edit`; an import source or legacy archive item `import.prepare` with every batch company in scope (PJ-20); ROUTE-229–ROUTE-231 `evidence.upload`; a duplicate's second submission `warnings.override` (IP-05)
- an account sees and chooses only the evidence types of the families it may view (PJ-08; TM-55); a download is authorized at request time through the owning record, never by a signed link alone, and served as an attachment under FL-07's profile (DP-05; TM-20); a match the actor may not view changes nothing in the answer — wording, steps or timing class (DP-13; UXS-18; TM-23)

**COMPANY SCOPE AND FIELD PROJECTION:**

- company evidence belongs to its company and links only to that company's records by composite keys; pool evidence belongs to no company and links only to pool records (C-58; DATABASE §4.10); the command's company is the evidence's (CS-03)
- PJ-08: evidence visible with `evidence.view`, its company in scope and its family's view capability — finance, cost, TAX_INVOICE, project, company WAREHOUSE_RECORD and OPENING_SIGNOFF families as PJ-08 lists them —; pool WAREHOUSE_RECORD and OTHER with `stock.view` and `evidence.view`; a link never widens visibility (row 95); `file_objects` only through the owning record (row 90); evidence balances by the family of their evidence (row 94)
- the list one company at a time plus the pool branch, the scope inside the query (PF-13; CS-07)

**CONCURRENCY / IDEMPOTENCY:**

- ROUTE-228 is a bookkeeping request without `command_id` (RB-03; CI-09); ROUTE-229 and ROUTE-230 are NX-07 with `command_id` (CI-01): lock set LK-00, LK-03, LK-29 after the registration keys of FU-10 at LK-28 — held from before the transaction until it ends, so two registrations of one identity or content run one after the other and cannot both miss the warning (BD-14; BD-15)
- ROUTE-231 is NX-14 (LK-00, LK-01, LK-03, LK-09, LK-10, LK-11, LK-21, LK-26, LK-27, LK-29): the evidence and the anchor of the record it is linked to locked, data-dependent through the records and requirements the evidence is linked to; a replacement makes a registered version current (CONCURRENCY_IDEMPOTENCY §7.3)
- a lost race on an identity is CONFLICT, answered as DP-13 prescribes when the other record lies outside scope (SQ-08); replay re-authorized (CI-05); an override names the matches it acknowledges and a new match is REJECTED again (CONCURRENCY_IDEMPOTENCY §11)

**AUDIT:**

- every registration, version, link, unlink and replacement is a business audit event in its transaction with the individual actor, the reason where required and the linkage (BR-XC-01; LG-01); an override is ADM_PLUS under its acknowledged action code, listed in QS-21 and the Owner's review (AZ-10; QS-14); every pool upload reaches the Owner (PJ-08); an undisclosed match writes EVIDENCE_DUPLICATE_UNDISCLOSED once the registration has committed (FL-10; LG-02; GAP-034); UPLOAD_REJECTED for a refused file (LG-02); no file contents in any record (LG-03)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: PROPOSED — the type-free evidence identity index of DATABASE §4.10 alone (DIR-089 (2)), expand-only; otherwise the tables of TASK-005–TASK-009, a missing column an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: YES — expand-only, that index alone
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: DOC, tier 1, cited by the records of every higher module through evidence links (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the storage and the decoders behind TASK-022's interfaces (ARCHITECTURE §12)
- Replaceable adapter/contract required: NO — `DocumentChangeParticipant` is TASK-029's contract, used here
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — office files stay attachments, never previewed or rendered (FL-02)

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

- Context7: REQUIRED — the framework's streamed uploads, `mimes` and `mimetypes` rules, session-level advisory locks in PostgreSQL, at the installed versions
- Graphify: OPTIONAL — once the proof-bearing commands exist
- Browser E2E: REQUIRED — J-DOC-02 and CJ-20's evidence uploads
- Other: crafted fixtures for each refused kind — SVG, polyglot, active office file, decompression bomb — never committed as real data (DOD-21)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of DOC and TASK-022's pipeline
- Pre-change questions: which commands cite evidence and in which role; which upload fields send files to ROUTE-228
- Expected affected areas: DOC's evidence actions; every upload field's command referencing a file id

**CONTEXT7:**

- Topics to verify: `pg_advisory_lock` in its two-integer form and its release on session end; the framework's file validation rules reading content
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — MSG-15, MSG-31, MSG-32 and IP-16's labels as written
- Language switch impact: keeps unsent input; a file being uploaded continues
- Mobile-first: YES — `[both]`; on the phone the picker may open the camera for a photo at the place of the event (IP-16; J-DOC-02)
- Responsive: YES
- Adaptive: YES — drag and drop on the desktop only as an addition to the picker, never the only way (J-DOC-02)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the component Upload — idle, dragging, uploading, success and error with retry and announcements —, the sheet (PT-16), the data table and dense rows (PT-05, PT-06), the inline warning (PT-14)
- Design reference: PG-038 as INFORMATION_ARCHITECTURE §8.4 states it, the upload as IP-16 and DESIGN_SYSTEM §10; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Unggah" — the upload sheet's choose, upload and register
- Secondary actions: "Tambahkan sebagai Versi Baru", "Jadikan Versi Berlaku", link and unlink, "Unduh"
- Destructive actions: an unlink with its reason; no control deletes evidence or a version
- Redundancy constraints: one upload field component and one upload route for every purpose (ROUTE-228)
- Loading state: the upload's progress bar with "Batalkan" (IP-16 step 2)
- Empty state: no evidence yet — one sentence with the upload action (PT-19)
- Error state: MSG-31 at the field for a refused file; IP-05's warning with the visible matches; MSG-20 when the upload limit is reached

**ROUTE IMPACT:**

- ROUTE-038 (PG-038's view); ROUTE-228 (an upload, every purpose), ROUTE-229 (register), ROUTE-230 (a new version), ROUTE-231 (link, unlink, replace); downloads through ROUTE-226 (TASK-022)

**INTERACTION CONTRACTS:**

- INT-013 (with TASK-022), INT-228 as INT-013; INT-229–INT-231; INT-015 for the warning's second submission (with TASK-013)

**NAVIGATION CONTRACT:**

- Parent: none — *Dokumen* › *Bukti* (NAV-038)
- Entry: *Dokumen* › *Bukti*; "Buat" › *Unggah Dokumen*; the upload fields of the record pages
- Back action: N/A — a top-level list (NAV-038 names none); an upload field returns to its own form
- Cancel: the upload sheet's "Batal"; removing the file before sending (INT-013)
- Success destination: PG-038 with the registered evidence (NAV-038), or the form the upload field belongs to
- Error behavior: NV-04 — MSG-31 at the field, the input kept
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[both]` — photograph and upload at the place of the event (J-DOC-02)

**ACCEPTANCE CRITERIA:**

1. A file outside its purpose's types or size is refused at the field with MSG-31 and nothing else is sent; an SVG, a polyglot, an active office file and a decompression bomb are refused (FL-02–FL-05; TM-38; TM-42).
2. Registering evidence whose identity already exists in the company offers "Tambahkan sebagai Versi Baru" and never creates a second evidence, and two concurrent registrations of one identity or one content run one after the other, the second seeing the first (L-45; BD-14; BD-15).
3. A reference-less match, or a content or cross-type match the actor may view, is a warning: without `warnings.override` nothing is recorded; with it the second submission names the matches and its reason and is listed in QS-21 and the Owner's review (IP-05; FL-10; UXS-17).
4. A match in another company or an invisible family shows the uploader nothing — the same answer, steps and timing class as without it — and the Owner finds it in the review queue (DP-13; UXS-18; SM-12; TM-23).
5. An upload equal to a stored rendition is recorded as a reproduction and never satisfies a client-original requirement, its flag shown only to a viewer who may see that rendition (FL-05; FS-12; SM-23).
6. An amount-bearing evidence's proven amount caps every settlement, expense and charge citing it, and a document registered again under another type gains no capacity in any role (C-18; C-58; architecture challenge 6d).
7. Replacing or unlinking evidence that satisfied a requirement or backed a settlement re-evaluates them in the same transaction (L-41; BR-ADM-03; architecture challenge 20a).
8. Pool evidence needs MSG-32's tick, links only to pool records, is visible with `stock.view` and `evidence.view`, and every pool upload reaches the Owner (PJ-08; H7-11).
9. An uploaded file is served only as an attachment under FL-07's headers, authorized through its record at request time; an image shows its re-encoded preview; another company's file answers as not found (FL-07; DP-05; TM-20; TM-39).
10. Uploads stop at 60 per hour and the daily byte budget, and an account can neither upload, version nor link evidence of a family it may not view (WS-12; PJ-08; TM-55).
11. The type-free evidence identity index exists, built on the one issuer normalization `identity_key` also uses, and each query that reads it — FL-10's cross-type warning across the workspace among them — uses it in `EXPLAIN` (DIR-089 (2); TL-15).
12. A registration, a new version or a link references only an upload its own actor made for that purpose; another account's upload answers as not found, proven by an authorization test — the database holds a file to one owner and one company, not to its actor (DIR-089 (5); FL-01; IDOR).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the identity key's normalization (TL-03)
- Feature: ROUTE-038 and ROUTE-228–ROUTE-231, each purpose's allowlist and limit (TL-04)
- Integration: concurrent registrations of one identity and of one content (BD-14, BD-15); a replacement re-evaluating a requirement and a settlement in one transaction (TL-05)
- Authorization: each upload purpose's capability; PJ-08's families for upload, version, link and view; pool evidence's audience (TL-06); criterion 12's upload of another account (DIR-089 (5))
- Route integrity: ROUTE-038 and ROUTE-228–ROUTE-231 audited (TL-07)
- Interaction: INT-013 and INT-228–INT-231, each command with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-DOC-02 — a photo from the phone's camera and the desktop's picker with drag and drop — and CJ-20's in-scope duplicate warning (TL-09)
- Localization/language switch: both languages on PG-038 and the upload field (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12 — the upload's states announced (component Upload)
- Production build: TL-13
- Security: SM-09 for every purpose (TM-38, TM-39, TM-42); SM-10 for the decoders (TM-37, TM-40, TM-41); SM-12 and SM-23 for the hidden matches (TM-23, TM-55; GAP-034); SM-25 and SM-01 for foreign and invisible files (TM-20); TM-16 and TM-46 stay with the exports of TASK-060 under FL-09 (TL-14)
- Performance: QB-12 for PG-038; QB-16 for downloads; the cross-company identity probe once per company and once for pool evidence (BD-15; AS-08); the type-free identity index used in `EXPLAIN` by each query that reads it, FL-10's across companies (DIR-089 (2)) (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: FL-11 — a file marked infected by the scanner, where TASK-079 adopted one, is never served

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Feature/DOC/EvidenceTest.php` — an SVG, a disguised type, an active office file and a pixel bomb refused at ROUTE-228 with MSG-31 and the purpose's types and size, nothing stored, UPLOAD_REJECTED written —; each purpose under its own capability, a company outside the scope answered as unknown; the pipeline's checks themselves TASK-022's (`tests/Unit/DOC/UploadInspectionTest.php`)
- Criterion 2: `tests/Feature/DOC/EvidenceTest.php` — an identity of the company REJECTED under L-45 naming it, versioned by ROUTE-230, never a second record —; two concurrent registrations of one identity and of one content under real PostgreSQL sessions, run one after the other on FU-10's keys, the second seeing the first (`tests/Integration/Evidence/EvidenceRacesTest.php`)
- Criterion 3: `tests/Feature/DOC/EvidenceTest.php` — a reference-less match REJECTED with its matches and their labels, refused again without `warnings.override`, without naming the matches or without a reason, then recorded with an ADM_PLUS `DOC.EvidenceDuplicateOverridden` event for QS-21 and the Owner's review —; a replayed warning answering its current matches (`tests/Interaction/EvidenceTest.php`); the browser's CJ-20 (`tests/Browser/E2E/evidence.spec.ts`)
- Criterion 4: `tests/Feature/DOC/EvidenceTest.php` — a match in another company COMMITTED with the ordinary answer and written as EVIDENCE_DUPLICATE_UNDISCLOSED after the commit, its basis, both public identifiers and both companies —; the split made by the authority re-read at LK-03
- Criterion 5: `tests/Feature/DOC/EvidenceTest.php` — an upload equal to another company's rendition recorded as a reproduction in its audit, written for the Owner, its flag shown to the Owner and not to the uploader —; the requirement's satisfaction is TASK-035's, the flag derived from the stored checksums (DATABASE §10's application check)
- Criterion 6: `tests/Integration/Evidence/EvidenceGuardTest.php` — a fee advice's citations and expenses capped by its proven amount through the guard's CHECK and `GuardUpdate`, a document registered again as OTHER refused under C-58 in every amount-bearing role, nothing moved
- Criterion 7: `tests/Integration/Evidence/EvidenceChangeTest.php` — a replacement reaching two `DocumentChangeParticipant`s, standing for ADM's requirement and FIN's settlement re-evaluation, inside DOC's transaction with their project locked at LK-01, committed together; a participant's refusal refusing the replacement and the unlink, nothing written
- Criterion 8: `tests/Feature/DOC/EvidenceTest.php` and `tests/Authorization/EvidenceAccessTest.php` — pool evidence only of WAREHOUSE_RECORD and OTHER with MSG-32's tick, linked only to pool records, seen with `stock.view` and `evidence.view`, every pool registration and version flagged `pool` in its audit for the Owner's QS-14
- Criterion 9: `tests/Authorization/EvidenceAccessTest.php` — an evidence file served through its evidence as an attachment under FL-07's headers to its family's viewers, answered as not found to another family and another company —; the download route TASK-022's (`tests/Feature/Files/FileRoutesTest.php`)
- Criterion 10: `tests/Feature/DOC/EvidenceTest.php` — 60 uploads an hour and the daily byte budget counted from `file_objects` under the uploader's row lock, MSG-20 with the minutes left —; `tests/Authorization/EvidenceAccessTest.php` — another family's type refused at the upload and the registration, its evidence unknown to a version and a link, a link to a record the viewer may not see neither shown, counted nor unlinked (PJ-08 row 95)
- Criterion 11: `tests/Performance/EvidenceQueriesTest.php` — FL-10's cross-type lookup across every company and the pool, and BD-14's reference-less lookup, served by `evidence_documents_type_free_identity` in `EXPLAIN` —; the issuer's normalization defined once in `EvidenceIdentity`, the index reading `identity_key`'s parts (`tests/Unit/DOC/EvidenceIdentityTest.php`); §4.10's column order kept, so no amendment
- Criterion 12: `tests/Feature/DOC/EvidenceTest.php` — a registration and a version referencing another account's upload answered as not found, nothing written

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS in CI (TL-02)
- [x] Relevant automated tests: Unit — the identity's normalization and FU-10's keys (TL-03); Feature — ROUTE-038 and ROUTE-228–ROUTE-231, each purpose's allowlist and limit (TL-04); Integration — BD-14 and BD-15 under real sessions, the participants in one transaction, the guard's caps (TL-05); PASS in CI
- [x] Authorization: each upload purpose's capability; PJ-08's families for upload, version, link and view; pool evidence's audience; a link's record (row 95); criterion 12 (TL-06)
- [x] Route integrity: ROUTE-038 and ROUTE-228–ROUTE-231 in the route audit and the route list (TL-07)
- [x] Interaction behavior: INT-228–INT-231, each command's `command_id` replayed, an unlink's and a warning's replay among them (TL-08)
- [x] Browser E2E if required: J-DOC-02 — a client order dropped on the desktop's field and a photo from the phone's picker — and CJ-20's warning, on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: PG-038 and its upload sheet at 320, 390, 768, 1,280 and 1,680 px without a sideways scroll (TL-11)
- [x] Visual breathing room/density if UI: TASK-015's components — the data list, the sheet, the upload field, the duplicate warning —, nothing hand-styled
- [x] Production build: TL-13 PASS in CI
- [x] Security checks if required: SM-09 for every purpose; SM-12 and SM-23 for the hidden matches and the reproduction; SM-25 and SM-01 for foreign and invisible files (TL-14); TM-16 and TM-46 stay with TASK-060
- [x] Database safety review: one expand-only index, `2026_10_27_000144`; local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources; ER-06's one independent review — one P1, two P2, four P3 — and Codex's two P2, each fixed and tested; the accessibility check measured at rest, once the sheet's reveal has finished

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `67e732f`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40534569610 (push) and 40534576518 (pull request #30), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration". The workstation's Docker engine stopped answering twice during the Task; the Owner restarted it, and the local runs were the targeted PHP suites, ESLint and TypeScript, the browser suites left to CI while BantuKasir's ran
- Build result: `TL-13 Frontend build: PASS — Evidence: 67e732f, CI ubuntu-24.04 run 40534569610`
- Route evidence: TL-07's route audit PASS — ROUTE-038 `GET /dokumen/bukti` (`evidence.view`), ROUTE-228 `POST /berkas` (`access:s0`, each purpose's capability in the action, WS-12's limiter), ROUTE-229 `POST /bukti`, ROUTE-230 `POST /bukti/{id}/versi` and ROUTE-231 `POST /bukti/{id}/tautan` (`evidence.upload`)
- Interaction evidence: `tests/Interaction/EvidenceTest.php` (INT-228–INT-231)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 67e732f, CI ubuntu-24.04 run 40534569610`; TL-10, TL-11 and TL-12 for PG-038 in the same run
- Localization evidence: PG-038 and its upload sheet in both languages (`tests/Browser/Localization/evidence.spec.ts`) (DOD-16); 87 catalogue keys added, "Tambahkan sebagai Versi Baru" canonical, the rest proposed
- Performance evidence: QB-12 — PG-038 in the same statements for one evidence and for 25 —; the type-free identity index in `EXPLAIN` (`tests/Performance/EvidenceQueriesTest.php`) (TL-15)
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: `app/Modules/DOC/` — `Actions/` (StoreUpload, RegisterEvidence, AddEvidenceVersion, LinkEvidence, UnlinkEvidence, ReplaceEvidence), `Contracts/DocumentChangeParticipant`, `Data/` (DocumentChange, EvidenceIdentity, EvidenceTypes, LinkHandle, StoredUpload), `Http/Controllers/EvidenceController`, `Queries/` (EvidenceList, EvidenceMatches; ContentMatches' record identifiers; FileAccess on EvidenceTypes), `Services/` (EvidenceRegistry, EvidenceUse, EvidenceGuard, UploadBudget, UploadLimited; Uploads claiming the budget) —, the type-free index's migration, WS-12's upload limiter and `config/files.php`'s bounds, the routes, PG-038 `resources/js/Pages/DOC/Evidence.tsx` with the upload client's fields, the duplicate warning's link-less match and PG-038 in the navigation, 87 catalogue keys, the tests and browser fixtures, TOOLCHAIN's row; this record
- Commit SHA: `01e95dd`, `de69dce`, `acb482c`, `d0641a2`, `67e732f`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-030-evidence`
- Notes: criteria as CRITERIA DISPOSITIONS states. Discoveries for Planning: `DocumentChangeParticipant`, named TASK-029's contract, is defined here since TASK-029 is not built and NX-14 needs it now — TASK-029 uses it as it stands —; the daily byte budget has no number in the sources, 500 MB per account an assumption (`UPLOAD_DAILY_BYTES`) until P8 measures (H6-07). Readings within the approved text: OPENING_SIGNOFF is registered by the import (TASK-057), never chosen on PG-038; PG-038 links evidence to projects, each record page linking its own records through the same action later; an unlink names its link by a keyed hash of its identifier, the page never receiving it; a link's own visibility follows its record's family (PJ-08 row 95); the reproduction flag is derived from the stored checksums, recorded in the registration's audit. Codex reviewed `de69dce`, its two P2 fixed and tested in `d0641a2`; no review of the later commits arrived within ten minutes of their push — CODEX_TIMEOUT recorded for them, never a pass (DIR-096). Not applicable: Migration tests (TL-16) — an index alone, its migration run by every suite —; FL-11's scanner — off until TASK-079

**SAFE NEXT TASK:**

- TASK-035 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- run the FL-10 lookups inside a business transaction or in a linking, replacing or citing command (H6-09)
- reveal a match outside the actor's view in any wording, count, flag or timing, or let a reproduction satisfy a client original (DP-13; FS-12)
- render an uploaded PDF inline from the application's origin, preview an office file, or put a request value into a storage path (FL-04; FL-07)
