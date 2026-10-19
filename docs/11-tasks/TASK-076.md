# TASK-076 — Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Write the operator's working runbooks PRQ-15 asks for — INFRASTRUCTURE §7.1's Owner-account recovery, §7.2's key-compromise incident, §7.3's post-incident recovery with Scenario B and §7.4's key rotation and re-encryption — with BACKUP_RECOVERY §4's restore procedure and its step 7 and RELEASE_PLAN §6's rollback rules, every step tracing to its source step, the tabletop's findings and the OG-3 decisions applied, and blank forms for the private records (GLC-16) — the incident record, the private contact record of the Owners and the dated key register — whose filled copies live outside the repository and the VPS.

**WHY THIS EXISTS:**

AU-14, AU-21 and AU-25 leave their human procedures to P9 (H9-07, H9-14), which wrote them in [INFRASTRUCTURE §7](../09-operations/INFRASTRUCTURE.md#7-security-operations) and walked §7.1–§7.3 in the tabletop exercise, whose thirty findings TT-F01–TT-F30 BACKUP_RECOVERY §6 assigns to their owners; the Owner then decided the alternate, the dispute's governance, the two rotations, AU-25's halt and the raise after a restore ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-03–OE-05, OE-10, OE-11). A recovery first run during a real incident can stall at a halt, run out of order or reinstate an Owner longer than needed, so PRQ-15 makes these the operator's working documents before go-live — the architecture challenge's sequences 12a, 13a and 16a, with GAP-050's carried cases and GAP-064's raise.

**DEPENDS ON:**

- TASK-020 — Installation step, the first Owner and the Owner-recovery path
- TASK-074 — Backup tooling, the off-server copy and the drill procedures

**BLOCKS:**

- TASK-081 — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal
- TASK-083 — The alternate operator named and the dispute's governance recorded

**PARALLEL SAFE:** NO — it writes the working runbooks that TASK-081's drills rehearse and that TASK-083 completes with the alternate and the dispute's governance

**IN SCOPE:**

- **The Owner-account recovery runbook** ([§7.1](../09-operations/INFRASTRUCTURE.md#71-the-owner-account-recovery-runbook); AU-14; H9-07; TM-57; TM-68): the last resort — the Owner's recovery codes, a code a sustained cooldown blocks counting as not usable, then another active Owner, the operator recording why neither can serve —; identity in person through the private contact record of the Owners, the verification written into the private incident record before the command, and when the Owner is the operator the record saying so with no independent verification claimed (TT-F12, TT-F13, TT-F15; OE-03); the audit trail checked for the account's removals during an open window first; TASK-020's command, with the confirmer step during a key-compromise run; the hand-over on the Owner's own device, the link never in a log, a ticket or the repository; the use's effects of AU-20 e; the alert and the record, never the token
- **The key-compromise incident runbook** ([§7.2](../09-operations/INFRASTRUCTURE.md#72-the-key-compromise-incident-runbook); AU-21; H9-14; TM-65): the alarm and the private record's first entries — the detection time in WIB, the type, the source, the environments possibly affected, the status suspected, the evidence masked (TT-F03) —, the client told once the incident is confirmed or may affect the service or its data (TT-F02); the window's start recorded once, from the dated key register when the leak cannot be dated, an unregistered or ambiguous creation time being the first halt (TT-F04, TT-F05); the inventory, a private-interface binding proving nothing, Scenario A only when write access is excluded and otherwise Scenario B, which stops the second application too with the client told and involved (TT-F06, TT-F07); every key and affected secret rotated before the command (TT-F09); the maintenance mode on every process, each confirmed by a refused forbidden request (TT-F10); AU-21's protocol (a)–(d); the mode lifted only after the closing sweep and the checks; MULTIPLECORP's environment only (TT-F11)
- **The post-incident recovery runbook** ([§7.3](../09-operations/INFRASTRUCTURE.md#73-the-post-incident-recovery-runbook); AU-25; H8-21's protocol; GAP-050): restoration, then recovery through path (e), then the review; each restored Owner shown with the removal it reversed, the restoration temporary until the review decides (TT-F14); the review's dispute rule — neither Owner presumed right, the protections kept, the review and the end of recovery incomplete until the governance P9Q-15 decides settles it — with the slot where that governance, named in advance, is recorded before go-live by TASK-083 (DIR-083 OE-04; GLC-21; TT-F17); **Scenario B** using only a backup or snapshot independently verified — integrity, date and source — as predating the compromise, a backup on the compromised host never presumed clean and an off-server copy proving nothing by existing (TT-F21, TT-F24; BKR-09; architecture challenge 13a) — the copy of DIR-083 OE-09 being the answer to TT-F19, which found no backup able to serve Scenario B —, the stop for the Owner's incident decision outside the application when none exists, and the restored state's opening checklist — maintenance mode kept, new keys with the leaked key never listed, every SX-01 secret rotated again, the incident command in full, every precondition verified (TT-F23) —, MULTIPLECORP's recovery isolated from the second application, which is never presumed safe (TT-F08); **the halts as OE-10 amends AU-25** — on a trusted state first to the Owner's incident decision outside the application, with the client, which may choose Scenario B's restore or keep the trusted state; on a restored state the Owner's decision —, that decision covering isolation, evidence, recovery from verifiable sources, a clean rebuild and every data-loss risk, each recorded and approved by its authority (TT-F22), no Owner created or guessed and no OWNER authority given to the operator, the client or anyone not authorized (TT-F16, TT-F25); a corrected window start as a new run, its history kept outside the application (TT-F28); the end of recovery only when its conditions hold, every open security decision closed first (TT-F18), recorded by TASK-020's atomic step under the OWNER role row (TT-F30); with one Owner, halt (2) waits on that person, a second Owner account being the business's own decision (TT-F27); the record
- **The key rotation and re-encryption runbook** ([§7.4](../09-operations/INFRASTRUCTURE.md#74-the-key-rotation-and-re-encryption-runbook); SX-02; H9-14): the dated register in the operator's secret store; the new `APP_KEY` current and the old one in `APP_PREVIOUS_KEYS`; the HMAC keys rotated only when the reason requires it, the Owner then reissuing outstanding credential links; AU-21's re-encryption command and TOTP_SECRETS_REENCRYPTED; the old key retired only when the re-encrypted count equals the enrolled accounts; each generation's paired key kept while it is retained (BKR-08); when — on SX-02's triggers, once after the first Owner's set-up and before any business data (OE-05; CUT-02; run in TASK-085), then yearly; and, beside it, AU-02's planned change of hashing parameters or driver — announced, bounded, recorded, never a silent switch, since it ends each account's other sessions at its next login (INFRASTRUCTURE §4.2; HO-26; RV-10)
- **The restore runbook** ([BACKUP_RECOVERY §4](../09-operations/BACKUP_RECOVERY.md#4-the-restore-procedure); BK-08; BKR-05; BKR-08): steps 1–7 with TASK-074's tooling — `ReconcileGuards` before reopening (HO-28; GAP-031) — and **step 7**: no numbered type issues again before the Owner records, per company, type and period, the highest number on the papers issued after the recovery point and TASK-021's command raises each affected counter once in this recovery, never lowering one, audited with the incident; the lost facts recorded again through the ordinary commands under new numbers, the papers' numbers beside them in the private incident record (DIR-083 OE-11; RBK-02; GAP-064; architecture challenge 12a); a restore after a key compromise being Scenario B, not this procedure alone
- **The rollback runbook** (RBK-01–RBK-06): the code rollback to the rollback target in a maintenance window, the queue drained by the newer workers and the newer release's pages reloaded or their commands refused (RBK-01); data recovery only by the restore runbook, on the Owner's decision (RBK-02); a wrong opening fact corrected by contras and reversals by import identity, never imported again (RBK-03); a failed step stopping the release, with maintenance mode when writes are at risk and the operator's and the Owner's choice recorded (RBK-04); an interrupted migration recovered by its rehearsed procedure (RBK-05); the second application never touched, a Scenario B stopping the whole host with the client told and involved (RBK-06; TT-F06; architecture challenge 16a)
- **The private record forms**, blank in the repository and filled only outside it and the VPS: the private incident record (TT-F03; TT-F28), the private contact record of the Owners (TT-F12) and the dated key register (TT-F04) — fields only, never a value
- **The operator-only commands' use** (H9-03; SX-04; H9-09): `GuardMaintenance` under the administration role, and the Owner-recovery, re-encryption, key-compromise incident and OWNER-restoration commands under the runtime role, each run only by the operator, audited, never through a web route; their use rehearsed on staging only (SM-19; SM-21; TASK-081)
- **The P9 handoffs as runbook steps**: HO-28's `ReconcileGuards` after every restore; HO-23's workers and scheduler restarted and no step flushing Valkey; HO-25's alerts as the alarm of §7.2 step 1; HO-26's planned hashing change; HO-21's timeouts, HO-29's purges and HO-30's recorded versions referenced where a step depends on them (TASK-072)
- **UXH-09's aggregate alerts**: delivered through the channel by TASK-075; the runbooks add no in-app form — the Owner's in-app notices carry the single security events INFORMATION_ARCHITECTURE §9 names

**OUT OF SCOPE:**

- running a runbook or a command on staging or production — the operator's: rehearsed in TASK-081, the first rotation in TASK-085, every incident by the operator alone
- the commands themselves — TASK-020's recovery and incident commands, TASK-021's raise, TASK-013's `GuardMaintenance` and `ReconcileGuards` — and TASK-074's tooling
- naming the alternate operator and the dispute's governance — the Owner's and the client's, in TASK-083
- the filled private records and the key register's entries — the operator's, outside the repository
- any new recovery path, deciding authority, role or bypass (AU-25 unchanged; DIR-083 OE-04)

**REFERENCES:**

- [INFRASTRUCTURE §7](../09-operations/INFRASTRUCTURE.md#7-security-operations) and §7.1–§7.4; [§4.2](../09-operations/INFRASTRUCTURE.md#42-configuration) — hashing; [§4.3](../09-operations/INFRASTRUCTURE.md#43-secrets-and-keys); [§10](../09-operations/INFRASTRUCTURE.md#10-owner-questions) P9Q-14, P9Q-15, P9Q-16
- [BACKUP_RECOVERY §4](../09-operations/BACKUP_RECOVERY.md#4-the-restore-procedure); [§6](../09-operations/BACKUP_RECOVERY.md#6-the-tabletop-incident-recovery-exercise) and [the exercise held in PC-4](../09-operations/BACKUP_RECOVERY.md#the-exercise-held-in-pc-4) — TT-F02–TT-F30 and their owners; [§1](../09-operations/BACKUP_RECOVERY.md#1-the-decision-and-its-limits) BKR-05, BKR-08, BKR-09
- [RELEASE_PLAN §6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) RBK-01–RBK-06; [§7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills); [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-21, GLC-23, GLC-25, GLC-26; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-15
- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-14, AU-20, AU-21, AU-25; [§7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-02, SX-04; [§8](../05-security/SECURITY.md#8-threat-model) TM-57, TM-65, TM-68
- [SECURITY §10](../05-security/SECURITY.md#p9--operations) H9-03, H9-07, H9-09, H9-14; [P8 handoff](../05-security/SECURITY.md#p8--required-denial-and-security-tests-p8-creates-its-own-files) H8-19, H8-21
- [PERMISSIONS_MATRIX §3](../05-security/PERMISSIONS_MATRIX.md#3-role-and-grant-model) RG-11 — the operator's identity
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-21, HO-23, HO-25, HO-26, HO-28–HO-30; [§14](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency) NM-03, NM-04; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-01; [§16](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#16-authorization-and-revocation-races) RV-18
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-19, SM-21; [§9](../08-testing/SECURITY_TEST_MATRIX.md#9-where-each-case-runs)
- [ADMIN_FLOW §16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-09; [INFORMATION_ARCHITECTURE §9](../07-ux-design/INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues)
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-050--post-incident-recovery-cases-carried-to-p8-and-p9) GAP-050; [GAP-064](../00-governance/GAP_REGISTER.md#gap-064--numbers-already-used-on-paper-after-a-restores-recovery-point); [GAP-031](../00-governance/GAP_REGISTER.md#gap-031--guard-balances-can-drift-from-the-facts-they-summarize); [GAP-041](../00-governance/GAP_REGISTER.md#gap-041--operator-abuse-of-the-owner-recovery-command)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-03–OE-05, OE-10–OE-13
- Architecture challenge — the sequences 12a, 13a, 16a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (60): BK-08; AU-14; SX-02; SX-04; TM-57; TM-65; TM-68; H8-19; H8-21; H9-03; H9-07; H9-09; H9-14; HO-21; HO-23; HO-25–HO-26; HO-28–HO-30; UXH-09; SM-19; BKR-08; TT-F02–TT-F19; TT-F21–TT-F25; TT-F27–TT-F28; TT-F30; RBK-01–RBK-06; GLC-16; PRQ-15; GAP-031; GAP-050; GAP-064

**EXPECTED MODULES / FILE AREAS:**

- the runbooks, one per procedure, and the blank private-record forms in the repository's operations documentation; no code

**BUSINESS RULES:**

- N/A — the runbooks state no rule: each step cites its source step of INFRASTRUCTURE §7, BACKUP_RECOVERY §4 or RELEASE_PLAN §6, and where a working copy and its source differ the source wins and the copy is corrected; the raise's rules stay NM-03, NM-04 and NX-01 (TASK-021)

**ARCHITECTURE RULES:**

- the runbooks call only the operator-only console commands that exist (H9-03), on the production host and by the operator, never through a web route
- each runbook acts on MULTIPLECORP's environment alone (INF-07; TT-F11); a code rollback is never data recovery (RL-04)

**SECURITY / PERMISSION REQUIREMENTS:**

- no token, key, password, credential link or secret in a runbook, a form or anything kept in the repository; a link travels outside the application and is never logged (INFRASTRUCTURE §7.1 step 5; SX-01)
- the procedures are the human operator's alone, never an agent's (SX-04); identity is verified in person through the private contact record, never through contact details the application holds or a session or e-mail that may be compromised (TT-F12)
- TM-57's controls kept — the Owner present, the operator's identity audited, K3's order recorded, the alert through a channel the operator does not solely control once one exists, the review able to remove a restored Owner again —, the residual accepted by the Owner (GAP-041; RISK-005)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — accounts, roles, keys and backups only; the review's ordinary Owner commands keep their own capability and scope (PERMISSIONS_MATRIX RG-05; OD-01), and the runbooks add none

**CONCURRENCY / IDEMPOTENCY:**

- the OWNER-restoration command refused a second time for one account in one run (M-46); the window's start read from the run's audit and any other value refused (AU-25); the end of recovery recorded atomically under the OWNER role row (LK-02; TT-F30; GAP-050); the raise once per recovery and never lowering a counter (NX-01; LK-31) — each the command's own guard, which the runbook relies on and never works around

**AUDIT:**

- the runbooks rely on the commands' audit and security events — the operator's identity recorded without a user row (RG-11; LG-01), OWNER_RECOVERY_LINK_ISSUED, CREDENTIALS_VOIDED, OWNER_RESTORED and TOTP_SECRETS_REENCRYPTED (LG-02), the raise's event naming the incident (NM-03) —; the private incident record keeps every decision, reversal and open matter beside them, without a secret (INFRASTRUCTURE §7.3 step 9)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — operator documents only
- Stable core kept provider-neutral: YES — the runbooks rely on no provider feature (INF-08)
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no incident-management tool; one document per procedure

**AUTH IMPACT:**

- Authentication change: NONE — the runbooks use the designed recovery and incident commands of TASK-020 and change none
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: AICWDF-COMPAT-8 — unchanged; passwords are set only by their holders through their links
- Google sign-in: N/A — not touched; a recovered Owner's Google link is unlinked by AU-20 e and suspect links by the OWNER unlink (AU-24)
- Google registration behavior: N/A — no path registers an account
- Google credentials owner action required: NO
- Authorization change: NONE — OWNER_ONLY rules unchanged
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

- Context7: OPTIONAL — the framework's maintenance-mode and previous-keys commands the steps name, checked at the installed version
- Graphify: N/A — documentation only
- Browser E2E: N/A — no page
- Other: the console commands of TASK-020, TASK-021 and TASK-013 and the tooling of TASK-074, read as built, so that each step names the command it runs
- Missing safe tool behavior: N/A — nothing is installed; the runbooks only use what the earlier Tasks built

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — documentation only; the commands are read from TASK-020's, TASK-021's and TASK-074's code
- Pre-change questions: which command each step runs and what it prints; which audit and security events each writes; which steps the tabletop corrected (TT-F09) or added
- Expected affected areas: the operations documentation only

**CONTEXT7:**

- Topics to verify: the framework's maintenance mode without a bypass and `APP_PREVIOUS_KEYS`, as the steps use them
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — operator documents only; the Owner's link opens PG-003 of TASK-017
- Indonesian default: N/A — operator documents only
- English support: N/A — operator documents only
- Plain-language Indonesian: N/A — operator documents only
- Language switch impact: NONE
- Mobile-first: N/A — operator documents only
- Responsive: N/A — operator documents only
- Adaptive: N/A — operator documents only
- Visual breathing room: N/A — operator documents only
- Comfortable density: N/A — operator documents only
- shadcn/ui reuse: N/A — operator documents only
- Design reference: N/A — operator documents only
- Primary action: N/A — operator documents only
- Secondary actions: N/A — operator documents only
- Destructive actions: N/A — no page; every irreversible step — Scenario B's stop, a restore, a key's retirement — waits for the decision its source names
- Redundancy constraints: N/A — operator documents only
- Loading state: N/A — operator documents only
- Empty state: N/A — operator documents only
- Error state: N/A — no page; a failed step stops the run as its source states

**ROUTE IMPACT:**

- none — console commands and documents only

**INTERACTION CONTRACTS:**

- N/A — no page interaction

**NAVIGATION CONTRACT:**

- Parent: N/A — operator documents only
- Entry: N/A — operator documents only
- Back action: N/A — operator documents only
- Cancel: N/A — operator documents only
- Success destination: N/A — operator documents only
- Error behavior: N/A — operator documents only
- State preservation: N/A — operator documents only
- Mobile behavior: N/A — operator documents only

**ACCEPTANCE CRITERIA:**

1. Every runbook step names its source step — INFRASTRUCTURE §7.1–§7.4, BACKUP_RECOVERY §4 or an RBK- rule — and no step adds a recovery path, a deciding authority, a role, a bypass or a rule the sources do not state.
2. The key-compromise runbook records the alarm first, the window's start once, the inventory, every key and affected secret rotated before the command, the maintenance mode confirmed on every process, the command's protocol, and the mode lifted only after the closing sweep (TT-F03, TT-F04, TT-F07, TT-F09, TT-F10).
3. The post-incident runbook keeps restoration, recovery and review in that order, shows each restored Owner with the removal it reversed, keeps a disputed change unvalidated until the governance named in advance settles it, and holds the slot TASK-083 fills (TT-F14; TT-F17; DIR-083 OE-04).
4. Scenario B uses only a backup or snapshot independently verified as predating the compromise — an off-server copy proving nothing by existing —, stops for the Owner's decision outside the application when none exists, and takes the host out of service with the client told (TT-F21; TT-F24; TT-F06; architecture challenge 13a and 16a).
5. On a trusted state a halt goes first to the Owner's incident decision outside the application, with the client, and no step creates or guesses an Owner or gives OWNER authority to the operator or the client (DIR-083 OE-10; TT-F16; TT-F25).
6. The restore runbook keeps every numbered issue stopped until the Owner's raise is recorded and run once for the recovery, and records the lost facts again under new numbers with the papers' numbers in the private record (DIR-083 OE-11; RBK-02; GAP-064; architecture challenge 12a).
7. The rotation runbook retires an old key only after the re-encrypted count equals the enrolled accounts, and names the rotation after the first Owner's set-up and the yearly one (SX-02; DIR-083 OE-05).
8. Every runbook acts on MULTIPLECORP alone, and no runbook or form holds a token, key, password, link, real name or contact detail (INF-07; TT-F11; SX-01).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no domain logic in this Task
- Feature: N/A — no route or command in this Task; the commands' tests are TASK-020's and TASK-021's
- Integration: N/A — no database, queue or job path in this Task
- Authorization: N/A — no route, query class or command in this Task
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no user-facing copy; operator documents are written in English
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — no code in this Task
- Security: SM-15's secret-like scan over the runbooks and forms (TL-14); the paths of H8-19 and H8-21 that SM-19 and SM-21 test match the runbooks' steps, and their operator-run cases on staging are TASK-081's
- Performance: N/A — operator procedures off the request path
- Migration: N/A — no migration or import code in this Task
- Other: a step-by-step trace from each runbook step to its source step, reviewed; each of TT-F02–TT-F19, TT-F21–TT-F25, TT-F27, TT-F28 and TT-F30 found in the step BACKUP_RECOVERY §6 lands it in

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
- Build result: N/A — no code in this Task
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-081 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- run a runbook, a command or any step on staging or production, or hold a key, a link or a private record of them (SX-04; INF-01)
- add a recovery path, a deciding authority, a role or a bypass the sources do not state, or write the dispute's governance — the Owner's and the client's (DIR-083 OE-04; AU-25)
- write a token, a key, a password, a link, a real name or a contact detail into a runbook or a form (SX-01; INF-05)
- let a step touch the second application, except Scenario B's stop of the whole host with the client (INF-07; RBK-06)
