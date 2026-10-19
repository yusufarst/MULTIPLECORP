# TASK-086 — UAT with the Owner and the validators

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** OWNER

**OBJECTIVE:**

The Owner and the Admin Operasional validators run the UAT of RELEASE_PLAN §8 on staging with synthetic data, as TASK-070 prepared it: TEST_STRATEGY §12's real-device checks and UAT items, the per-page lists of new copy, and UAT-01–UAT-04 — numbering and go-live as PG-061 presents them, the opening import with the validators, representative daily work with trustworthy stock and money, and separate Owner and Admin evidence —, the validators' review time held first and the representative document templates validated, each result with its disposition, ending with the Owner's sign-off.

**WHY THIS EXISTS:**

The validators are confirmed but their review capacity is not (DEP-06; [GAP-014](../00-governance/GAP_REGISTER.md#gap-014--eighteen-days-is-a-target-not-a-measured-delivery-capacity)); phones, scanners, printers and the formats users hold are unverified (DEP-03; GAP-020); the documents' content and the representative templates are unvalidated (DEP-04; GAP-019); scanner and session behaviour can create false success or duplicate intent until it is exercised with the real devices (GAP-015). The operational outcomes OS-01–OS-06 need their evidence and the Owner's business decision, LOCALIZATION §5 lists new copy for the Owner with its page's review, and GLC-15 and GLC-17 make the signed UAT and the held review time go-live items.

**DEPENDS ON:**

- TASK-070 — Full release regression and the device UAT's preparation
- TASK-078 — Staging stack, the two applications' isolation and the provider features

**BLOCKS:**

- TASK-087 — Cutover and go-live

**PARALLEL SAFE:** NO — the validators' review time is the plan's scarce capacity, and the UAT runs on the staging stack the operator starts

**IN SCOPE:**

- **The capacity first** (GLC-17; GAP-014; DEP-06; [RELEASE_PLAN §8](../10-release/RELEASE_PLAN.md#8-uat)): the validators' review time secured before the UAT is scheduled; an unavailable validator delays the release and is reported to the Owner as a blocker, and the UAT is never shortened to keep a date (RL-02)
- **The real-device checks** ([TEST_STRATEGY §12](../08-testing/TEST_STRATEGY.md#12-devices-and-uat); DEP-03; GAP-020): on the phone, S2-J01's sign-in, S2-J03's work home and project journey at 390 CSS px and S2-J05's receiving at 390 and 320 CSS px, with the journeys of the C2 phone tasks UT-06, UT-12 and UT-18–UT-24 at 390 CSS px, each record naming the device, the browser and the CSS width; the desktop composer, form beside live preview; the actual USB scanners on a desktop and a phone — wedge speed, the Enter suffix, the scan field's focus, Bluetooth pairing —, the phone's camera application through the file picker, and label and document printing by the method the office already has, no new printer assumed; the formats the users really hold against FL-02's allowlist (DEP-04; SM-09); the time on the device beside the server budgets (PFM-17) and the office connectivity and workload as observed (DEP-09); the supported browsers corrected with this evidence (DIR-083 OE-02)
- **The UAT items** of TEST_STRATEGY §12: the copy of ADMIN_FLOW's message catalogue and of the glossary, and the payment duplicate window's presentation (UXH-08); the seven-day duplicate window against the Owner's finance practice (HO-35); each generated document's content and layout, DOC-01–DOC-14, with FX-10 — the representative document templates validated (GLC-17; GAP-019; DEP-04); each high-risk account holder's phone with an authenticator app (DEP-11); representative daily work with data entered once and reused through the flow, with correct outputs (OS-01) and AC-14's phone and desktop tasks
- **The per-page lists of new copy** (TEST_STRATEGY §10; [LOCALIZATION §5](../07-ux-design/LOCALIZATION.md#5-copy-validation-ca-04-items-710) and [§5.1](../07-ux-design/LOCALIZATION.md#51-the-review-list-cb-15-cd-07)): copy written after APPR-011 accepted when its Indonesian is a GL term or an approved label with its validated English, any other new copy listed for the Owner with its page's review and accepted except the keys the Owner marks (`DIR-081` OB-04); Indonesian stays the canonical text
- **UAT-01–UAT-04** (RELEASE_PLAN §8): numbering and go-live at the cutover as PG-061 presents them — the seeds from the import, the Owner's starts, go-live recorded once — with ADMIN_FLOW §12's numbering journeys (UAT-01; UXH-10); the opening import with the validators — preparation, the dry-run report, the reconciliation to the signed totals and the sign-off — rehearsed on staging, the real data's run being TASK-087's (UAT-02); representative daily work and stock and money trustworthy at opening and after repeated and concurrent work (UAT-03; OS-01; OS-02); separate Owner and Admin Operasional evidence, each validator's record kept on its own (UAT-04)
- **GAP-015 exercised**: refresh and back, an expired login, a slow network and a retry with the actual scanners, the outcome shown as committed, pending or failed
- **The operational outcomes** ([ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence) OS-01–OS-06): each row's evidence named — daily work (OS-01), trustworthy stock and money (OS-02), scoped sensitive information from TASK-069's results (OS-03), recovery from the drills' records (OS-04), cost and capacity from COST_POLICY's inventory and TASK-079's figures (OS-05), deterministic evidence in fresh CI (OS-06) —, the Owner giving the final business decision (RELEASE_PLAN §4)
- **The record** (TEST_STRATEGY §12): validator, date, build, tasks, each result and its disposition, with an issue log; nothing recorded that did not happen; a defect sent to a defect Task through the executor feedback loop, a scope or date tradeoff to the Owner with its evidence (RL-02; DIR-083 OE-20); ending with the Owner's sign-off (GLC-15)

**OUT OF SCOPE:**

- the real data's import, dry run and reconciliation on production (TASK-087)
- fixing a defect the UAT finds — its own defect Task — and any change of page, copy or scope during UAT outside its owning Task
- the automated suites and the release regression (TASK-062–TASK-070); the accessibility and responsive suites (TASK-065, TASK-066)

**REFERENCES:**

- [RELEASE_PLAN §8](../10-release/RELEASE_PLAN.md#8-uat) UAT-01–UAT-04; [§4](../10-release/RELEASE_PLAN.md#4-release-requirements) — the release completion; [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-15, GLC-17; [§1](../10-release/RELEASE_PLAN.md#1-principles) RL-02, RL-05
- [TEST_STRATEGY §12](../08-testing/TEST_STRATEGY.md#12-devices-and-uat); [§10](../08-testing/TEST_STRATEGY.md#10-localization); [§4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-10
- [LOCALIZATION §5](../07-ux-design/LOCALIZATION.md#5-copy-validation-ca-04-items-710) — the copy validation and the rule for copy written after APPR-011; [LOCALIZATION §5.1](../07-ux-design/LOCALIZATION.md#51-the-review-list-cb-15-cd-07) — the review list
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-03, DEP-04, DEP-06, DEP-09, DEP-11
- [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence) OS-01–OS-06
- [ADMIN_FLOW §12](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#12-numbering-and-company-onboarding); [§16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-08, UXH-10
- [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-17; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-09
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-35
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-014--eighteen-days-is-a-target-not-a-measured-delivery-capacity) GAP-014; [GAP-015](../00-governance/GAP_REGISTER.md#gap-015--scannersession-behavior-can-create-false-success-or-duplicate-intent); [GAP-019](../00-governance/GAP_REGISTER.md#gap-019--complete-selectable-documents-need-bounded-coverage-and-validated-content); [GAP-020](../00-governance/GAP_REGISTER.md#gap-020--mobile-first-and-existing-usb-scanners-do-not-prove-device-compatibility)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-02, OE-14, OE-20
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (22): DEP-03–DEP-04; DEP-06; DEP-09; OS-01–OS-06; UAT-01–UAT-04; GLC-15; GLC-17; l10n-rule LOCALIZATION §5; l10n-rule LOCALIZATION §5.1; GAP-014–GAP-015; GAP-019–GAP-020

**EXPECTED MODULES / FILE AREAS:**

- no code; the UAT record — each validator's own record and the issue log, on synthetic data only — kept by the Owner, its summary in COMPLETION EVIDENCE; the Owner's marks on the copy lists returned to TASK-067's lists; a supported-browser correction recorded where TEST_STRATEGY §12 asks for it

**BUSINESS RULES:**

- N/A — no rule is built; the UAT judges the built behaviour against BUSINESS_RULES and WORKFLOWS, and the documents' content against the validated examples DEP-04 asks for

**ARCHITECTURE RULES:**

- N/A — no code; the release is judged as built on staging

**SECURITY / PERMISSION REQUIREMENTS:**

- synthetic data only on staging; a real client document, template sample or legacy row stays private business data outside the repository, staging and every agent's reach — only sanitized examples are validated (RL-05; DEP-04; PRODUCTION_DATA_SAFETY)
- the validators sign in with staging's synthetic accounts, each high-risk one with TOTP (DEP-11)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the validators work as the synthetic accounts of the grant shapes SECURITY_TEST_MATRIX §2 fixes — the Owner and Admins of the synthetic group (FX-02) —, each seeing only what its company scope and projection allow (CS-01–CS-08; PJ-01–PJ-23); OS-03's proof is TASK-069's results, not this UAT

**CONCURRENCY / IDEMPOTENCY:**

- UAT-03's repeated and concurrent work by the validators and GAP-015's retry and expired-session cases exercise the built command identity — one `command_id` per intent, a retry replaying it (CI-01–CI-09) —; no command or lock is added

**AUDIT:**

- N/A — the UAT's actions are synthetic and go with the staging stack; the UAT record is the evidence, each validator's record on its own (UAT-04)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: N/A — no code
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: LOW — later releases that change what a user does or sees repeat the UAT (REL-05)
- Speculative abstraction avoided: YES — TEST_STRATEGY §12's record, no UAT tool

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: N/A — no code; the profile stays LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: N/A — not touched; the high-risk holders' authenticator phones confirmed (DEP-11)
- Google sign-in: N/A — not touched in the UAT
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

- Context7: N/A — no library or tool is configured
- Graphify: N/A — no code
- Browser E2E: N/A — the validators' journeys are manual, on real devices, beside the automated suites of TASK-062–TASK-064
- Other: the staging stack the operator starts from TASK-078's definition; TASK-070's device-UAT preparation and FX-10's document samples; the office's own phones, PCs, scanners and printers
- Missing safe tool behavior: N/A — nothing is installed; no new device or printer is bought (DEP-03)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: the validators' available hours; the devices, browsers and printers the office holds; which copy keys the per-page lists carry
- Expected affected areas: the UAT record only

**CONTEXT7:**

- Topics to verify: N/A — no library behaviour
- Version-sensitive assumptions: none — the browsers and devices are recorded as the users hold them

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES — judged on the pages as built; no page is changed in this Task
- Indonesian default: YES — the message catalogue, the glossary and the per-page copy judged in Indonesian (UXH-08; TEST_STRATEGY §10)
- English support: YES — the English renderings on the per-page lists judged (LOCALIZATION §5)
- Plain-language Indonesian: YES — judged by the validators in the catalogue and the glossary
- Language switch impact: NONE — the switch is used as built
- Mobile-first: YES — the phone journeys at 390 and 320 CSS px on real devices (TEST_STRATEGY §12)
- Responsive: YES — the real devices and the desktop composer
- Adaptive: N/A — no layout is built here; the phone and desktop compositions are judged as built
- Visual breathing room: N/A — no page is built; TEST_STRATEGY §9's screens are TASK-065's
- Comfortable density: N/A — no page is built; TASK-065's gate
- shadcn/ui reuse: N/A — no component is built
- Design reference: N/A — no page is built
- Primary action: N/A — no page is built
- Secondary actions: N/A — no page is built
- Destructive actions: N/A — no page is built; the UAT acts on synthetic data only
- Redundancy constraints: N/A — no page is built
- Loading state: N/A — no page is built; the states are judged in daily work
- Empty state: N/A — no page is built
- Error state: N/A — no page is built; the scanner and session outcomes are judged under GAP-015

**ROUTE IMPACT:**

- none — the release's routes are used as built

**INTERACTION CONTRACTS:**

- N/A — no interaction is built or changed; the INT- contracts are exercised as built

**NAVIGATION CONTRACT:**

- Parent: N/A — no page is built or changed
- Entry: N/A — no page is built or changed
- Back action: N/A — no page is built or changed; refresh and back are exercised under GAP-015
- Cancel: N/A — no page is built or changed
- Success destination: N/A — no page is built or changed
- Error behavior: N/A — no page is built or changed
- State preservation: N/A — no page is built or changed
- Mobile behavior: N/A — no page is built or changed; judged on the phones

**ACCEPTANCE CRITERIA:**

1. The validators' review time is secured before the UAT is scheduled, no item is skipped or shortened for a date, and an unavailable validator is reported to the Owner as a blocker (GLC-17; GAP-014; RL-02).
2. Each real-device check of TEST_STRATEGY §12 is recorded with its device, browser and CSS width — the phone journeys at 390 and 320 CSS px, the desktop composer, the scanners, printing, the users' files and the time on the device (DEP-03; GAP-020; PFM-17).
3. Each UAT item of TEST_STRATEGY §12 and each of UAT-01–UAT-04 has a result and a disposition, with separate Owner and Admin Operasional records (UAT-04).
4. Each document type DOC-01–DOC-14 is judged with FX-10's samples and the representative document templates are validated (GLC-17; GAP-019; DEP-04).
5. The per-page lists of new copy are reviewed and the Owner's marks recorded, unmarked keys accepted as LOCALIZATION §5 states.
6. GAP-015's cases — refresh and back, an expired login, a slow network and a retry with the actual scanners — are exercised and recorded.
7. OS-01–OS-06 each have their evidence named, and the UAT ends with the Owner's sign-off (GLC-15).
8. Only synthetic data is used, and nothing is recorded that did not happen (TEST_STRATEGY §12; RL-05).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no code in this Task
- Authorization: N/A — no route; OS-03's evidence is TASK-069's SECURITY_TEST_MATRIX results
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract is built; the contracts are exercised as built
- Browser E2E: N/A — the manual journeys on real devices complement the automated CJ- suites
- Localization/language switch: the per-page copy lists and the catalogue reviewed in both languages — the human half of TL-10 (TEST_STRATEGY §10)
- Visual breathing room/density: N/A — TASK-065's gate
- Responsive: the real devices of TEST_STRATEGY §12 — the human half of TL-11
- Accessibility: N/A — TL-12's manual checks are TASK-066's
- Production build: N/A — staging runs the release's tagged commit
- Security: SM-09's device half — the formats the users really hold against FL-02's allowlist (TEST_STRATEGY §12)
- Performance: PFM-17's time on the device, recorded beside TASK-079's server times and judged with the Owner
- Migration: N/A — the import is rehearsed with the validators (UAT-02); its migration tests are TASK-057's and TASK-077's
- Other: the UAT record's fields — validator, date, build, tasks, results, dispositions, issue log, sign-off

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the UAT record — each validator's results, dispositions and issue log — stands in their place
- [ ] Authorization — the validators used staging's synthetic accounts within their grants; OS-03 read from TASK-069's results
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — the GAP-015 cases recorded with their outcomes
- [ ] Browser E2E if required — N/A: manual journeys on real devices, each with device, browser and CSS width
- [ ] Responsive/adaptive if required — the phone journeys at 390 and 320 CSS px and the desktop composer recorded
- [ ] Visual breathing room/density if UI — N/A: no page built; TASK-065's gate
- [ ] Production build — N/A: staging runs the release's tagged commit
- [ ] Security checks if required — the users' file formats against FL-02 recorded (SM-09); synthetic data only
- [ ] Database safety review — no real data on staging or in the record
- [ ] Final diff review — only the UAT summary and the returned copy marks; no personal or business data

**COMPLETION EVIDENCE:**

- Test outputs: the UAT record — validator, date, build, tasks, each result and its disposition, the issue log, the device checks with their devices, browsers and CSS widths, the copy-list marks, OS-01–OS-06's evidence named — and the Owner's sign-off, in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task; the build under UAT is named in the record
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract is built; GAP-015's cases are part of the record
- Browser evidence: N/A — no automated journey; the manual device checks are part of the record
- Localization evidence: the per-page copy lists reviewed in both languages, with the Owner's marks
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20) — no device or printer bought
- Files changed: the UAT summary and the copy marks returned to TASK-067's lists — nothing else
- Commit SHA: the commit that records them, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); each issue's disposition, a defect named with its defect Task; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the Owner's and the validators' record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-087 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- use real client data, a real document or a real legacy row on staging or in the record (RL-05; PRODUCTION_DATA_SAFETY)
- shorten or skip an item for a date, or record a result that did not happen (RL-02; TEST_STRATEGY §12)
- change a page, copy or scope during the UAT outside its owning Task — a defect goes to the feedback loop, a scope change to the Owner (DIR-083 OE-20)
- merge the Owner's and the Admin Operasional's records into one (UAT-04)
