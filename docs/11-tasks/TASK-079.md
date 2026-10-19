# TASK-079 — Staging measurements — capacity, Argon2id, durations and the disk budget

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-05

**RISK CLASS:** HIGH

**EXECUTOR:** HUMAN OPERATOR

**OBJECTIVE:**

The human operator measures on the VPS — on the staging stack, outside both applications' business hours — the figures P6, P8 and P9 leave to the real host, and records them: PHP worker memory, Argon2id timing and PostgreSQL behaviour (OPI-07) with the sizing they set and the scanner decision they settle, the limiter and cap values under concurrency, AS-03's peak at 10x with the release-critical server times, the durations of a full backup and of a restore drill (OPI-25), and the shared-disk budget with its thresholds and the retention check — both production applications' combined use counted.

**WHY THIS EXISTS:**

No measurement exists: PERFORMANCE's workload, sizing and growth figures are assumptions until the VPS is measured (AS-07; SZ-01–SZ-09; GAP-017), server times are measured on staging because only staging runs on the VPS's own hardware ([PERFORMANCE_TARGETS §1](../08-testing/PERFORMANCE_TARGETS.md#1-how-a-target-is-measured)), and nominal capacity is not free space (DEP-01). The disk, the CPU and the memory are shared with the client's second production application, so every figure counts both (INF-07) — the architecture challenge's sequence 16b —, and GLC-05, OPI-07 and OPI-25 must hold with their recorded figures before go-live ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-14); GAP-014 keeps capacity open until it is measured.

**DEPENDS ON:**

- TASK-068 — Performance targets and query budgets in CI
- TASK-074 — Backup tooling, the off-server copy and the drill procedures
- TASK-078 — Staging stack, the two applications' isolation and the provider features
- the Owner's answer to P11Q-05 recorded, with what it calls for done — a step run or a narrow amendment approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-084 — Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy

**PARALLEL SAFE:** NO — load on the shared VPS runs alone, outside both applications' business hours, bounded and watched (PERFORMANCE_TARGETS §1)

**IN SCOPE:**

- **Sizing from measured figures** (OPI-07; SZ-01–SZ-04; AS-01; INFRASTRUCTURE §4.1, §8): PHP worker memory measured, setting the web-worker ceiling — up to 16 as the start — within the memory left after the second application's measured use (OPI-27); the two queue workers and the scheduler (SZ-02); the PostgreSQL connections, about 25 inside the default limit (SZ-03); the memory split — about a quarter of the remaining RAM for shared buffers, the hash slots counted (SZ-04); the container limits set in the VPS's own configuration from these figures; the queue store's small budget (SZ-09) and the timeouts and retry budgets (SZ-05) confirmed or corrected through review
- **Argon2id** (H9-11; HO-26; AU-02; AU-04): Argon2id confirmed in the production runtime and its parameters measured on the VPS within AU-02's bounds — about 0.25–0.5 s per hash within the memory budget —, otherwise AU-02's bcrypt fallback; the login dummy hash generated from the configured driver and parameters; then the slots and waits of H6-12 tuned; the parameters recorded as OPI-07's figures, a later change a planned event (RV-10)
- **The scanner decision** (H9-05; DIR-079 CB-09; FL-11): a self-hosted scanner only if the memory headroom left after sizing both production applications holds it, otherwise none; FL-01–FL-06 apply either way
- **The limiter and cap values** (PFM-12; SZ-07; WS-12; AU-05; H6-12): each exercised at its value under concurrency on staging, the verification cap's three slots with the measured Argon2id timing; each kept or corrected through review, the fail-closed limiters unchanged (H6-07)
- **The workload and server times** (PFM-13; PFM-01; PFM-02; AS-02–AS-06; DEP-09): AS-03's peak on staging at 10x — every p95 within its budget, no error, no lock-wait timeout, renditions within QB-17 —, the server times of PERFORMANCE_TARGETS §1 for the release-critical pages, PFM-02 on every page, and the rest of [PERFORMANCE_TARGETS §4](../08-testing/PERFORMANCE_TARGETS.md#4-the-release-critical-set)'s release-critical set — PFM-08, PFM-16 and PFM-20 — in the same run; load only outside both applications' business hours, bounded in duration, with the host and the other application's health watched; a time that misses opens a defect Task and AS-07's review, and the release waits; office connectivity and workload as the operator observes them (DEP-09); PFM-17's time on the device recorded beside these server times at TASK-086's device checks, judged with the Owner at UAT
- **Durations** (OPI-25): a full backup and a restore drill measured on the VPS with TASK-074's tooling, against the 24-hour RPO and the 4-hour RTO — the inputs of OPI-23's backup time (TASK-084), BK-04's freshness margin and the freeze's sizing (IMP-13)
- **The shared-disk budget** ([BACKUP_RECOVERY §3](../09-operations/BACKUP_RECOVERY.md#3-the-shared-disk-budget); OPI-21; GLC-05; BK-11; SZ-08; GT-04–GT-06): U₀, K and the free space as TASK-071 recorded them; DB, F, L and D projected from GT-04–GT-06 and the data steward's counts — counts only —; B from the 7 daily, 4 weekly and 3 monthly generations (DIR-079 CB-11); S measured on the first rehearsal; P computed with the off-server copy's transient file; Kₚ from OPI-27; H set; the budget held with one year's growth, the thresholds `<DISK_WARN_PCT>` and `<DISK_CRIT_PCT>` derived so that a backup and a restore drill still fit at the warning level; a retention the budget cannot hold brought back to the Owner before go-live (`DIR-081` OB-10); DB, F, L and D measured again after the opening import, before go-live, completing GLC-05 in the cutover (TASK-087)
- **Growth** (GT-01–GT-07): the thresholds read against the 100x sizes TASK-068 produced, the first baseline of the monthly report (INFRASTRUCTURE §6; GT-07)
- **The assumptions replaced** (AS-01–AS-08): each figure P9 measures replaces its assumption in the record; a budget the VPS cannot meet is corrected through review, never a reason to cut approved scope (AS-07); the existing VPS stays the near-zero-cost baseline (REF-062)
- **The record**: each measurement with its commit, scale, environment, command and output, the plans beside the query counts (PERFORMANCE_TARGETS §1); OPI-07's figures recorded in INFRASTRUCTURE §9 and OPI-21's and OPI-25's in BACKUP_RECOVERY §7, under CHANGE_CONTROL; the measured values set in the VPS's own configuration, never in the repository's template (INF-05)

**OUT OF SCOPE:**

- the CI half of PERFORMANCE_TARGETS — query counts, N+1 and plans on the scale fixtures (TASK-068)
- the device measurements themselves — PFM-17's device time is taken at TASK-086's device checks
- the post-import measurement of DB, F, L and D and GLC-05's final check (TASK-087)
- production measurements (TASK-084, TASK-087); changing a budget, a limit or the retention without its review; a paid scanner or service (INF-04)

**REFERENCES:**

- [PERFORMANCE §1](../06-api-performance/PERFORMANCE.md#1-assumptions-and-workload) AS-01–AS-08; [§10](../06-api-performance/PERFORMANCE.md#10-session-and-authentication-load); [§11](../06-api-performance/PERFORMANCE.md#11-growth-thresholds) GT-01–GT-07; [§13](../06-api-performance/PERFORMANCE.md#13-resource-inputs-for-p9) SZ-01–SZ-09
- [PERFORMANCE_TARGETS §1](../08-testing/PERFORMANCE_TARGETS.md#1-how-a-target-is-measured); [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-12, PFM-13, PFM-17; [§4](../08-testing/PERFORMANCE_TARGETS.md#4-the-release-critical-set); [§5](../08-testing/PERFORMANCE_TARGETS.md#5-what-p9-receives)
- [INFRASTRUCTURE §4.1](../09-operations/INFRASTRUCTURE.md#41-processes), [§4.2](../09-operations/INFRASTRUCTURE.md#42-configuration), [§8](../09-operations/INFRASTRUCTURE.md#8-capacity-and-cost), [§9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-07; [§10](../09-operations/INFRASTRUCTURE.md#10-owner-questions) P9Q-06 (DIR-079 CB-09)
- [BACKUP_RECOVERY §3](../09-operations/BACKUP_RECOVERY.md#3-the-shared-disk-budget); [§7](../09-operations/BACKUP_RECOVERY.md#7-the-operators-inputs) OPI-21, OPI-25; [§8](../09-operations/BACKUP_RECOVERY.md#8-owner-questions) P9Q-10 (DIR-079 CB-11)
- [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-05; [§5](../10-release/RELEASE_PLAN.md#5-the-opening-import) IMP-13
- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-02, AU-04, AU-05; [§4](../05-security/SECURITY.md#4-web-and-input-security) WS-12; [§5](../05-security/SECURITY.md#5-file-security) FL-11; [§10](../05-security/SECURITY.md#p9--operations) H9-05, H9-11
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-26
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-01, DEP-09; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-062
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-014--eighteen-days-is-a-target-not-a-measured-delivery-capacity) GAP-014; [GAP-017](../00-governance/GAP_REGISTER.md#gap-017--green-checks-may-miss-the-actual-concurrency-and-workload-risks)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-13, OE-14
- Architecture challenge — the sequence 16b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (43): DEP-01; DEP-09; BK-04; BK-11; REF-062; H9-05; H9-11; HO-25–HO-26; AS-01–AS-08; GT-01–GT-07; SZ-01–SZ-09; PFM-01–PFM-02; PFM-12–PFM-13; PFM-17; OPI-07; OPI-21; OPI-25; GLC-05; GAP-014

**EXPECTED MODULES / FILE AREAS:**

- no code; the figures in INFRASTRUCTURE §9 (OPI-07) and BACKUP_RECOVERY §3 and §7 (OPI-21, OPI-25), changed under [CHANGE_CONTROL](../00-governance/CHANGE_CONTROL.md#decision-authority); a budget or limit the figures show wrong goes to its owning document through review; the measured values themselves in the VPS's configuration only

**BUSINESS RULES:**

- N/A — no business rule; the targets and budgets are PERFORMANCE's and PERFORMANCE_TARGETS', referenced, never changed here

**ARCHITECTURE RULES:**

- counts in CI, times on staging; a miss is corrected through review — the query, the index or the budget —, never by cutting scope (PERFORMANCE_TARGETS §1; AS-07)
- every capacity figure counts both production applications' combined use (INF-07; architecture challenge 16b)

**SECURITY / PERMISSION REQUIREMENTS:**

- synthetic data only; the authentication limiters fail closed and the cap's slots keep the verification bounded (H6-07; H6-12)
- load bounded in duration, outside both applications' business hours, the other application's health watched (PERFORMANCE_TARGETS §1); no measurement record holds a secret, a host name or an address (INF-05)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the synthetic fixtures FX-06 at 1x and 10x; the measurements read timings and sizes, never company data

**CONCURRENCY / IDEMPOTENCY:**

- AS-03's peak drives concurrent commands against the release as built: no lock-wait timeout and no deadlock at the peak (PFM-13; HO-25's every deadlock a defect); the limiters exercised under concurrency at their values (PFM-12); no lock class or AX- action is added

**AUDIT:**

- N/A — synthetic data; each measurement's record — commit, scale, environment, command, output — is the evidence (PERFORMANCE_TARGETS §1)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: YES — the measurements read the host as found
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: LOW — the monthly report re-checks the figures (GT-07)
- Speculative abstraction avoided: YES — the tools TASK-004 verified, nothing added

**AUTH IMPACT:**

- Authentication change: NONE — the hashing parameters are measured within AU-02's bounds; the design is unchanged
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), measured, never redesigned
- Auth profile: AICWDF-COMPAT-8 — unchanged; only Argon2id's parameters, or AU-02's bcrypt fallback, are set from the measurement
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered; the load tool and the statement statistics are free (TEST_STRATEGY §17), and the scanner, if the headroom holds one, is self-hosted without licence cost (FL-11)
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: N/A — the operator runs the tools TASK-004 verified and recorded in TOOLCHAIN
- Graphify: N/A — no code
- Browser E2E: N/A — the device time is TASK-086's
- Other: k6 or the equivalent load tool of TEST_STRATEGY §17; `pg_stat_statements` and `auto_explain` on staging; the scale fixtures FX-06; TASK-074's backup and restore tooling; the operator's access under OPI-26
- Missing safe tool behavior: N/A — no agent installs anything; the operator installs only the verified tools on the staging stack, and a paid tier STOPS for the Owner (COST_POLICY §4C.2)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: the second application's measured use and windows (OPI-27); which pages and operations form the release-critical set (PERFORMANCE_TARGETS §4); the data steward's counts for the projections
- Expected affected areas: the staging stack's measurements and the recorded figures only

**CONTEXT7:**

- Topics to verify: N/A — no library is configured; the measured values are entered as TASK-072's configuration expects
- Version-sensitive assumptions: none — the runtime's Argon2id support is confirmed by measurement, not assumed (H9-11)

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed; the pages are measured as built
- Indonesian default: N/A — no page is built or changed
- English support: N/A — no page is built or changed
- Plain-language Indonesian: N/A — no page is built or changed
- Language switch impact: NONE
- Mobile-first: N/A — no page is built or changed
- Responsive: N/A — no page is built or changed
- Adaptive: N/A — no page is built or changed
- Visual breathing room: N/A — no page is built or changed
- Comfortable density: N/A — no page is built or changed
- shadcn/ui reuse: N/A — no page is built or changed
- Design reference: N/A — no page is built or changed
- Primary action: N/A — no page is built or changed
- Secondary actions: N/A — no page is built or changed
- Destructive actions: N/A — no page; disk pressure is simulated by a lowered threshold, never by filling the shared disk (BACKUP_RECOVERY §5)
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; a missed time opens a defect Task and its review (PERFORMANCE_TARGETS §1)

**ROUTE IMPACT:**

- none — the routes are measured as built

**INTERACTION CONTRACTS:**

- N/A — no interaction is built or changed

**NAVIGATION CONTRACT:**

- Parent: N/A — no page
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page

**ACCEPTANCE CRITERIA:**

1. OPI-07's figures — worker memory, Argon2id timing within AU-02's bounds or the bcrypt fallback, PostgreSQL behaviour — are recorded, and the worker ceiling, connections, memory split and container limits are set from what remains after the second application's measured use (SZ-01–SZ-04; INF-07; architecture challenge 16b).
2. The scanner decision of DIR-079 CB-09 is recorded from the measured headroom (H9-05).
3. Each limit of WS-12, AU-05 and H6-12 has been exercised at its value under concurrency on staging and kept or corrected through review (PFM-12).
4. AS-03's peak at 10x meets every p95 budget with no error and no lock-wait timeout, and the release-critical set's server times are recorded; a miss has opened its defect Task and review (PFM-13; PFM-01; PFM-02; PERFORMANCE_TARGETS §4).
5. OPI-25's durations of a full backup and of a restore drill are recorded against the 24-hour RPO and the 4-hour RTO.
6. The disk budget of BACKUP_RECOVERY §3 is computed with both applications' terms and one year's growth, its thresholds let a backup and a restore drill fit at the warning level, and the 7/4/3 retention is confirmed or brought back to the Owner (GLC-05; BK-11; DIR-079 CB-11).
7. Load ran only outside both applications' business hours, bounded, with the other application's health watched and unharmed (PERFORMANCE_TARGETS §1; INF-07).
8. Each measurement's record names its commit, scale, environment, command and output (PERFORMANCE_TARGETS §1).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no code; the measurements on the staging stack stand in its place
- Authorization: N/A — no route, query class or command in this Task
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no journey; device time at TASK-086
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — staging runs the release's tagged commit as CI built it
- Security: the limiters' values under concurrency — the half of SM-14 left to measurement — with the fail-closed limiters unchanged (TL-14; PFM-12)
- Performance: TL-15's staging half — PFM-01, PFM-02, PFM-12, PFM-13 and PERFORMANCE_TARGETS §4's release-critical set, PFM-17 beside them at TASK-086
- Migration: N/A — no migration or import code in this Task
- Other: the disk budget's arithmetic redone by the reviewer from the recorded terms; GT-01–GT-07 read against the 100x sizes

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the operator's measurement records — OPI-07, PFM-12, PFM-13 with the release-critical set, OPI-25, the disk budget — stand in their place
- [ ] Authorization — N/A: no route; the limiters' values recorded under PFM-12
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — N/A: no interaction built or changed
- [ ] Browser E2E if required — N/A: no journey; device time recorded at TASK-086
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: staging runs the release's tagged commit
- [ ] Security checks if required — the authentication limiters stay fail-closed at their measured values
- [ ] Database safety review — synthetic fixtures only; the second application's disk, memory and load watched and unharmed
- [ ] Final diff review — only the recorded figures of INFRASTRUCTURE §9 and BACKUP_RECOVERY §3 and §7 changed, without a host detail

**COMPLETION EVIDENCE:**

- Test outputs: the operator's record of each measurement — its commit, scale, environment, command and output, the plans beside — in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without secrets (PERFORMANCE_TARGETS §1); only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task; the measured commit is named in each record
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey in this Task
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the recorded figures of OPI-07 in INFRASTRUCTURE §9 and of OPI-21 and OPI-25 with the budget's terms in BACKUP_RECOVERY §3 and §7 — nothing else
- Commit SHA: the commit that records them, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); a missed target named with its defect Task; GLC-05 completed after the import in TASK-087; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the operator's record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-084 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- let an agent access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- run load inside either application's business hours, unbounded, or without watching the other application's health (PERFORMANCE_TARGETS §1; INF-07)
- measure with production or real data, or fill the shared disk to test pressure — a lowered threshold only (BACKUP_RECOVERY §5)
- change a budget, a limit or the retention without its review, or answer a missed budget by cutting scope (AS-07; DIR-083 OE-20)
- take a figure measured for MULTIPLECORP alone as the host's capacity (INF-07; GLC-05)
