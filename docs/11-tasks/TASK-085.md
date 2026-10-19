# TASK-085 — Installation, the first Owner, two-step enrolment and the APP_KEY rotation

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-05, P11Q-06

**RISK CLASS:** HIGH

**EXECUTOR:** HUMAN OPERATOR

**OBJECTIVE:**

On prepared production and before any business data, the human operator — the Owner today (OPI-09) — runs TASK-020's installation step, which creates the first Owner and writes its audit event with the operator's identity; starts the dated key register with the installation's key; has the Owner enrol two-step verification on the Owner's own phone; then makes the one `APP_KEY` rotation DIR-083 OE-05 decides, by the rotation runbook, with its re-encryption count verified and the register's entries made; and confirms AU-25's halt, as OE-10 amends it, in the working runbook — each recorded.

**WHY THIS EXISTS:**

The audit trail can establish every pre-window Owner only if the installation step's OWNER assignment is audited with the operator's identity ([GAP-049](../00-governance/GAP_REGISTER.md#gap-049--the-first-owner-installation-event-has-no-actor-rule); AU-25); the Owner's account is high-risk and needs TOTP (AU-16; GAP-033); and the installation's key is created before the first OWNER assignment, so an undatable leak of it would leave no pre-window Owner (TT-F05) — which the one rotation after the first Owner's set-up, before any business data, prevents ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-05; TT-F26). CUT-02 orders these before the masters, and GLC-10, GLC-13 and GLC-23 pass only by their recorded evidence (OE-14).

**DEPENDS ON:**

- TASK-020 — Installation step, the first Owner and the Owner-recovery path
- TASK-084 — Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy
- the Owner's answers to P11Q-05 and P11Q-06 recorded, with what they call for done — a step run or a narrow amendment approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-087 — Cutover and go-live

**PARALLEL SAFE:** NO — it creates production's first account and rotates its key, on which the cutover and every later production step build

**IN SCOPE:**

- **The installation step** (PRQ-09; [INFRASTRUCTURE §5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path); GLC-13; GAP-049; SM-24): TASK-020's step run by the operator on production, under the runtime role, operator-only and audited, never through a web route; it creates exactly one first Owner and writes the audit event of the OWNER assignment with the operator's identity — the host account and the stated name, never a user row —, OPI-09's identity being recorded in that event and not in the repository; refused when run again on the installed workspace, so every later OWNER account comes only through `permissions.manage` (RG-05; OD-01)
- **The first Owner's credentials**, set by the Owner through the path the Owner's answer to P11Q-06 decides and TASK-020 builds — under option (a), recommended, the operator issues the first Owner's OWNER_RECOVERY link through AU-14's command right after the installation step, with the Owner present, who opens it on the Owner's own device ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) —, never set, read or revealed by the operator (AU-14's rule for the operator's commands)
- **The key register started** (TT-F04; GLC-16; INFRASTRUCTURE §7.2 step 2, §7.4 step 1): the installation's `APP_KEY` entered with its creation time as the register's first entry, in the operator's secret store, never in the repository
- **Two-step enrolment** (GLC-10; DEP-11; AU-16; AU-18; GAP-033): the Owner enrols TOTP at once with an authenticator app on the Owner's own phone, before any business data; every other high-risk account holder's phone with an authenticator app is confirmed in UAT (DEP-11; TASK-086) and enrolled at that account's first sign-in (AU-18), those accounts being created at the cutover (CUT-02; TASK-087), where GLC-10 is read in full
- **The one `APP_KEY` rotation** ([INFRASTRUCTURE §7.4](../09-operations/INFRASTRUCTURE.md#74-the-key-rotation-and-re-encryption-runbook); GLC-23; DIR-083 OE-05; SX-02; H9-14; TT-F26; CUT-02): after the first Owner's set-up and before any business data, by TASK-076's rotation runbook — the new key current and the old one in `APP_PREVIOUS_KEYS`; AU-21's re-encryption command run, touching only the first Owner's TOTP data; the old key retired only when the re-encrypted count equals the enrolled accounts; the register's entries for the new key and the old key's retirement; the HMAC keys left unchanged unless the rotation's reason requires them (SX-02)
- **The backups' pairing** (BKR-08; TM-65): any generation taken before the rotation stays paired with the key valid at its time, kept while the generation is retained; the TOTP data stays ciphertext under the current key in every generation
- **AU-25's halt in the working runbook** (GLC-23; DIR-083 OE-10; TT-F29; INFRASTRUCTURE §7.3 step 6; PRQ-15): the operator confirms that the working copy of TASK-076's post-incident runbook sends a halt on a trusted state first to the Owner's incident decision outside the application, with the client
- **INF-01 and SX-04**: the operator alone runs the step and the commands, on production; no agent holds any part of it
- **The record**: the installation's event recorded with the operator's identity, the register's entries made, the Owner's enrolment done, the re-encryption count equal to the enrolled accounts, the runbook's halt confirmed — by role, never a key, a password, a secret or a token

**OUT OF SCOPE:**

- the company masters, the other accounts, roles and grants, the tax configuration and the numbering schemes — after the rotation, at the cutover (CUT-02; TASK-087)
- the yearly rotation after go-live (DIR-083 OE-05) — an operations routine by TASK-076's runbook, no go-live item
- the step and the commands themselves (TASK-020) and the runbooks (TASK-076)
- any business data and the opening import (TASK-087)

**REFERENCES:**

- [INFRASTRUCTURE §5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path) — the first-Owner installation step; [§4.3](../09-operations/INFRASTRUCTURE.md#43-secrets-and-keys); [§7.2](../09-operations/INFRASTRUCTURE.md#72-the-key-compromise-incident-runbook) step 2; [§7.3](../09-operations/INFRASTRUCTURE.md#73-the-post-incident-recovery-runbook) step 6; [§7.4](../09-operations/INFRASTRUCTURE.md#74-the-key-rotation-and-re-encryption-runbook); [§9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-09; [P9Q-16](../09-operations/INFRASTRUCTURE.md#p9q-16--rotating-the-application-key-once-after-the-first-owner-and-periodically)
- [RELEASE_PLAN §6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) CUT-02; [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-10, GLC-13, GLC-23; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-09
- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-14, AU-16, AU-18, AU-21, AU-25; [§7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-02, SX-04; [§8](../05-security/SECURITY.md#8-threat-model) TM-65; [§10](../05-security/SECURITY.md#p9--operations) H9-14
- [PERMISSIONS_MATRIX §3](../05-security/PERMISSIONS_MATRIX.md#3-role-and-grant-model) RG-05, RG-11; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-01
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-24; [§9](../08-testing/SECURITY_TEST_MATRIX.md#9-where-each-case-runs)
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-11
- [BACKUP_RECOVERY §1](../09-operations/BACKUP_RECOVERY.md#1-the-decision-and-its-limits) BKR-08; [the exercise held in PC-4](../09-operations/BACKUP_RECOVERY.md#the-exercise-held-in-pc-4) TT-F04, TT-F05, TT-F26, TT-F29
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-049--the-first-owner-installation-event-has-no-actor-rule) GAP-049; [GAP-033](../00-governance/GAP_REGISTER.md#gap-033--the-owner-account-has-no-second-authentication-factor-in-v1)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-05, OE-10, OE-14
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (18): DEP-11; AU-14; SX-02; TM-65; H9-14; INF-01; OPI-09; TT-F04–TT-F05; TT-F26; TT-F29; GLC-10; GLC-13; GLC-16; GLC-23; PRQ-09; GAP-033; GAP-049

**EXPECTED MODULES / FILE AREAS:**

- no code; the operator's record outside the repository with its summary in COMPLETION EVIDENCE; the key register in the operator's secret store; production's keys in its environment only

**BUSINESS RULES:**

- N/A — no business rule; SECURITY §2 and the decisions DIR-083 OE-05 and OE-10 are the rule set

**ARCHITECTURE RULES:**

- the installation step and the key commands are operator-only console commands under the runtime role, never a web route (INFRASTRUCTURE §5, §7)
- the order of CUT-02: the installation, then the rotation, then the masters — before any business data

**SECURITY / PERMISSION REQUIREMENTS:**

- no step sets, reads or reveals a password, a TOTP secret or a key; none enters a record, a log or the repository (AU-14; SX-01; TM-65)
- TOTP mandatory for the Owner, on the Owner's own phone (AU-16; GAP-033); later OWNER accounts only through `permissions.manage` (RG-05; OD-01)
- the old key retired only after the verified count, and kept while a generation paired with it is retained (SX-02; BKR-08)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no company exists yet; the first Owner's scope will be every company (CS-01), and the installation step exercises no capability (RG-11's operator rule)

**CONCURRENCY / IDEMPOTENCY:**

- the installation step runs once and is refused on an installed workspace (INFRASTRUCTURE §5); the old key is retired only once the re-encrypted count equals the enrolled accounts (INFRASTRUCTURE §7.4 step 4); no business command runs in this Task

**AUDIT:**

- the installation's audit event of the OWNER assignment records the operator's identity — the host account and the stated name —, never a user row, RG-11's operator rule extended to it (INFRASTRUCTURE §5; LG-01; GAP-049); TOTP_ENROLLED and TOTP_SECRETS_REENCRYPTED written as security events and alerted (LG-02; LG-07); the register's dated entries in the operator's secret store

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN — the rotation runs before any business data, its re-encryption touching only the first Owner's TOTP data (INFRASTRUCTURE §7.4 step 6)

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: LOW — the yearly rotation reuses the same runbook (DIR-083 OE-05)
- Speculative abstraction avoided: YES — the built step and commands only

**AUTH IMPACT:**

- Authentication change: NONE — the designed installation, enrolment and rotation are exercised as built
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), never redesigned
- Auth profile: AICWDF-COMPAT-8 — the first Owner's password set by the Owner under it; TOTP mandatory for the Owner (AU-16)
- Google sign-in: N/A — off in production until TASK-082's production client exists; a link needs active TOTP (AU-22)
- Google registration behavior: EXISTING_ACCOUNT_ONLY — no account is created by Google
- Google credentials owner action required: NO
- Authorization change: NONE — the first Owner holds the OWNER role by the installation step alone; later Owners come through `permissions.manage` (RG-05)
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

- Context7: N/A — the operator runs TASK-020's step and commands as built
- Graphify: N/A — no code
- Browser E2E: N/A — no journey is added; the Owner enrols TOTP on the application's own pages
- Other: TASK-020's installation step and AU-21's re-encryption command; TASK-076's rotation and post-incident runbooks; the operator's secret store for the register; the Owner's own phone with an authenticator app
- Missing safe tool behavior: N/A — nothing is installed in this Task

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: the generations already taken on production and their paired key; the stated name the operator gives the installation step
- Expected affected areas: production's first account, its keys and the register only

**CONTEXT7:**

- Topics to verify: N/A — no library is configured
- Version-sensitive assumptions: none — the previous-keys behaviour TASK-020 verified is used as built

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed; the Owner enrols on the pages TASK-018 built
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
- Destructive actions: N/A — no page; the old key's retirement waits for the verified count
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; a refused second installation or a count mismatch stops the step for the operator

**ROUTE IMPACT:**

- none — console only; the Owner's enrolment uses TASK-018's routes as built

**INTERACTION CONTRACTS:**

- N/A — no interaction is built or changed

**NAVIGATION CONTRACT:**

- Parent: N/A — console only
- Entry: N/A — console only
- Back action: N/A — console only
- Cancel: N/A — console only
- Success destination: N/A — console only
- Error behavior: N/A — console only
- State preservation: N/A — console only
- Mobile behavior: N/A — console only

**ACCEPTANCE CRITERIA:**

1. Run once by the operator on production, the installation step creates exactly one first Owner and writes its audit event with the operator's identity and no user row as actor; a second run is refused (GLC-13; GAP-049; PRQ-09).
2. The key register's first entry records the installation's key with its creation time, outside the repository (TT-F04).
3. The Owner's TOTP is enrolled on the Owner's own phone before any business data exists (GLC-10; AU-16; GAP-033).
4. The one `APP_KEY` rotation runs after the first Owner's set-up and before any business data: the re-encrypted count equals the enrolled accounts before the old key is retired, and the register records the new key and the retirement (GLC-23; DIR-083 OE-05; TT-F26).
5. Every generation taken before the rotation stays paired with the old key, kept while the generation is retained (BKR-08).
6. The working post-incident runbook carries AU-25's halt as DIR-083 OE-10 amends it (GLC-23; TT-F29).
7. No password, TOTP secret or key was set, read or revealed by the operator, and none entered a record or the repository (AU-14; SX-01; TM-65).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command is built; TASK-020's tests cover the step
- Integration: N/A — no code in this Task
- Authorization: N/A — no route; the one OWNER assignment checked by the operator through the step's output
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no journey is added
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — production runs the release's tagged commit as CI built it
- Security: no SECURITY_TEST_MATRIX case runs on production (§9) — SM-24 is proven in CI by TASK-020; the operator checks the installation event, TOTP_ENROLLED and TOTP_SECRETS_REENCRYPTED read-only in the security log
- Performance: N/A — no target in this Task
- Migration: N/A — no migration or import code in this Task
- Other: the re-encryption count compared with the enrolled accounts before the retirement; the register's entries checked against INFRASTRUCTURE §7.4 step 1

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the operator's record of the installation, the register's start, the Owner's enrolment and the rotation stands in their place
- [ ] Authorization — exactly one OWNER assignment, made by the installation step; a second run refused
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — N/A: no interaction built or changed
- [ ] Browser E2E if required — N/A: no journey is added
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: production runs the release's tagged commit
- [ ] Security checks if required — the installation event with the operator's identity and no user row; the re-encryption count verified before the retirement; no secret handled in clear
- [ ] Database safety review — no business data; the old key kept while its paired generations are retained
- [ ] Final diff review — no repository change beyond this record's summary; no key, password or token anywhere in it

**COMPLETION EVIDENCE:**

- Test outputs: the operator's record — the installation step's run and its refused second run, the audit event recorded with the operator's identity, the register's first entry, the Owner's TOTP enrolment, the rotation with its re-encryption count and the register's entries, the runbook's halt confirmed — in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without secrets; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey in this Task
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: none in the application; this file's evidence summary only
- Commit SHA: the commit that records the summary, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); GLC-10 read in full at the cutover, with the other high-risk holders' phones confirmed in UAT; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the operator's record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-087 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- let an agent access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- run the installation step a second time, or make an OWNER account other than through `permissions.manage` (INFRASTRUCTURE §5; RG-05)
- put business data on production before the rotation is done and its count verified (CUT-02; INFRASTRUCTURE §7.4 step 6)
- set, read or reveal a password, a TOTP secret or a key, or write one into a record, a log or the repository (AU-14; SX-01)
- retire the old key before the re-encrypted count equals the enrolled accounts, or drop a generation's paired key while that generation is retained (INFRASTRUCTURE §7.4 step 4; BKR-08)
