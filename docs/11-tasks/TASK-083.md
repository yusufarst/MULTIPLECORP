# TASK-083 — The alternate operator named and the dispute's governance recorded

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** OWNER

**OBJECTIVE:**

The Owner names, before go-live and preferably as the client designates, the alternate operator P9Q-14 decides — who receives the alerts, can put MULTIPLECORP into maintenance mode and follows the written runbooks, with host access only while acting — records the alternate by role for OPI-01 and OPI-24, has the alert channel tested on the alternate's phone, opens GAP-041's review, and has the business's own governance that settles a disagreement between recovered pre-window Owners named in advance with the client and recorded in the post-incident runbook (GLC-21).

**WHY THIS EXISTS:**

While the Owner is the operator, no one else can take the operator's duty — the alerts, backups, restores and operator-only commands — when the Owner is unavailable (GAP-013), and the tabletop found no second, independent person for AU-14 (TT-F15) and no rule for a disagreement between recovered pre-window Owners (TT-F17). The Owner decided an alternate named before go-live and the business's own governance for a dispute, AU-25 unchanged ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-03, OE-04); GLC-21 makes both go-live items, passed only by their recorded evidence (OE-14), and INF-02 keeps production with the named human operator.

**DEPENDS ON:**

- TASK-076 — Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation
- TASK-080 — Alert channel adopted and monitoring live

**BLOCKS:**

- TASK-087 — Cutover and go-live

**PARALLEL SAFE:** YES — the Owner's and the client's decisions touch no code and no host step, their records are rows of their own and the runbook's governance slot, and only TASK-087 waits on them

**IN SCOPE:**

- **The input it waits on.** OPI-01 — the named operator and an alternate — records today that the Owner is the operator and that no alternate is named; its alternate is designated, preferably by the client, and recorded in INFRASTRUCTURE §9's row by role, never by account; this Task records that designation first — if it cannot be obtained, the Task becomes BLOCKED (DEFINITION_OF_DONE §1)
- **The alternate's role** (DIR-083 OE-03; P9Q-14 (a)): receives the alerts through OPI-05's channel, can put MULTIPLECORP into maintenance mode when a signal needs it, and follows TASK-076's written runbooks; host access given by the client only while acting, never standing and never to an agent (INF-01; OPI-26); the operator-only commands stay with whoever holds host access under INF-02
- **OPI-24's alternate** for backup and restore duty recorded by role with it ([BACKUP_RECOVERY §7](../09-operations/BACKUP_RECOVERY.md#7-the-operators-inputs); P9Q-13's note)
- **The channel on the alternate's phone** (GLC-21): the operator adds the alternate's phone to TASK-080's channel and a test alert reaches it, recorded by role without the number
- **GAP-041's review opened** (RISK-005): an alternate given host access while acting widens the trust AU-14 places in whoever holds host access (P9Q-14 (a)); the Owner opens the review and it is recorded with the gap, the accepted risk unchanged until it is held
- **The dispute's governance** (P9Q-15 (a); DIR-083 OE-04; [INFRASTRUCTURE §7.3](../09-operations/INFRASTRUCTURE.md#73-the-post-incident-recovery-runbook) step 4): the client, as the business and the VPS's owner, names in advance who decides between its Owners in a recovery review; the decision recorded before go-live in the slot TASK-076 left in the post-incident runbook, by role, the person's identity and contact kept in the private contact record outside the repository and the VPS (TT-F12); it acts outside the application, and AU-25 and every application rule stay unchanged
- **INF-02 and GAP-013**: production stays with the named human operator — and the alternate while acting —, and a second person now receives the alerts
- **The record**: OPI-01's and OPI-24's rows by role, the runbook's governance slot by role, the test alert to the alternate, GAP-041's opened review — no person's name, contact detail or account in the repository

**OUT OF SCOPE:**

- a second Owner account — the business's own decision, no go-live item (TT-F27; RELEASE_PLAN §7)
- any OWNER authority, standing host access or operator-only command for the alternate beyond acting (TT-F16; TT-F25)
- any change to AU-25, PERMISSIONS_MATRIX or the application (DIR-083 OE-04)
- the channel's adoption (TASK-080) and the runbooks themselves (TASK-076)

**REFERENCES:**

- [INFRASTRUCTURE §1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-01, INF-02; [§6](../09-operations/INFRASTRUCTURE.md#6-observability) — the alert channel and GAP-041's review; [§9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-01; [§10](../09-operations/INFRASTRUCTURE.md#p9q-14--an-alternate-operator-while-the-owner-is-the-operator) P9Q-14 and [P9Q-15](../09-operations/INFRASTRUCTURE.md#p9q-15--who-settles-a-disagreement-between-recovered-pre-window-owners)
- [BACKUP_RECOVERY §7](../09-operations/BACKUP_RECOVERY.md#7-the-operators-inputs) OPI-24; [§8](../09-operations/BACKUP_RECOVERY.md#8-owner-questions) P9Q-13; [the exercise held in PC-4](../09-operations/BACKUP_RECOVERY.md#the-exercise-held-in-pc-4) TT-F15, TT-F17, TT-F27
- [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-21; [§7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills)
- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-14, AU-25
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-013--successful-http-health-checks-can-hide-stalled-business-processing) GAP-013; [GAP-041](../00-governance/GAP_REGISTER.md#gap-041--operator-abuse-of-the-owner-recovery-command)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-03, OE-04, OE-14
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (11): INF-02; OPI-01; OPI-24; TT-F12; TT-F15–TT-F17; TT-F25; TT-F27; GLC-21; GAP-013

**EXPECTED MODULES / FILE AREAS:**

- no code; OPI-01's row of INFRASTRUCTURE §9 and OPI-24's of BACKUP_RECOVERY §7, the governance slot of TASK-076's post-incident runbook and GAP-041's entry, changed under [CHANGE_CONTROL](../00-governance/CHANGE_CONTROL.md#decision-authority) by role; identities and contacts in the private contact record outside the repository

**BUSINESS RULES:**

- N/A — no business rule; P9Q-15's governance is a rule of the business outside the application, AU-25 unchanged

**ARCHITECTURE RULES:**

- N/A — no code; the application and its authorization stay as they are

**SECURITY / PERMISSION REQUIREMENTS:**

- the alternate's host access granted by the client only while acting and recorded by role; never to an agent (INF-01; OPI-26)
- the alternate holds no OWNER authority; OWNER authority is never given to the operator, the client or anyone not authorized (TT-F16; TT-F25)
- TM-57's residual stays accepted while GAP-041's review is open (RISK-005)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no application data path

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command, lock or job

**AUDIT:**

- N/A — no business action in the application; the decisions are recorded by role in the documents above, and the governance's decisions in a later dispute go into the private incident record with the run's audit events (INFRASTRUCTURE §7.3 step 9)

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
- Future extension impact: LOW — a later change of the alternate is a new record by role
- Speculative abstraction avoided: YES — no new role, account type or authority

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: N/A — no code; the profile stays LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: N/A — not touched
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — the alternate holds no application role or capability by being the alternate
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

- Context7: N/A — no library or tool
- Graphify: N/A — no code
- Browser E2E: N/A — no page
- Other: TASK-080's channel for the alternate's test alert; TASK-076's post-incident runbook with its governance slot; the client's designation
- Missing safe tool behavior: N/A — nothing is installed

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: whom the client designates; who decides between the business's Owners; which runbook steps the alternate takes while acting
- Expected affected areas: the operator-input rows, the runbook's governance slot and GAP-041's entry only

**CONTEXT7:**

- Topics to verify: N/A — no library behaviour
- Version-sensitive assumptions: none

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed
- Indonesian default: N/A — no page is built or changed
- English support: N/A — no page is built or changed
- Plain-language Indonesian: N/A — no page is built or changed
- Language switch impact: NONE
- Mobile-first: N/A — no page; the alternate's alerts arrive on the alternate's own phone
- Responsive: N/A — no page is built or changed
- Adaptive: N/A — no page is built or changed
- Visual breathing room: N/A — no page is built or changed
- Comfortable density: N/A — no page is built or changed
- shadcn/ui reuse: N/A — no page is built or changed
- Design reference: N/A — no page is built or changed
- Primary action: N/A — no page is built or changed
- Secondary actions: N/A — no page is built or changed
- Destructive actions: N/A — no page is built or changed
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; an alternate that cannot be designated makes the Task BLOCKED (DEFINITION_OF_DONE §1)

**ROUTE IMPACT:**

- none

**INTERACTION CONTRACTS:**

- N/A — none

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

1. OPI-01's alternate is recorded by role — preferably as the client designates — with OPI-24's backup and restore alternate; until then the Task is not DONE and GLC-21 holds go-live (DIR-083 OE-03; GLC-21).
2. The record states the alternate's role — receiving the alerts, able to put MULTIPLECORP into maintenance mode, following the written runbooks — and host access granted only while acting (P9Q-14 (a)).
3. A test alert through the channel reaches the alternate's phone (GLC-21).
4. GAP-041's review is opened and recorded with the gap, the accepted risk unchanged until it is held (RISK-005).
5. The governance that settles a dispute between recovered pre-window Owners is named in advance with the client and recorded by role in the post-incident runbook before go-live, and AU-25 and the application are unchanged (DIR-083 OE-04; INFRASTRUCTURE §7.3 step 4).
6. No person's name, contact detail or account enters the repository (INF-05).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no code in this Task
- Authorization: N/A — no route; the alternate gains no application role or capability
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — no build in this Task
- Security: N/A — no security control changes; the host-access rule while acting is recorded instead (INF-02)
- Performance: N/A — no target in this Task
- Migration: N/A — no migration or import code in this Task
- Other: the alternate's test alert recorded; the runbook's governance slot read against INFRASTRUCTURE §7.3 step 4

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the Owner's record of the designation, the governance and the alternate's test alert stands in their place
- [ ] Authorization — the alternate's host access only while acting, granted by the client and recorded by role; no application role added
- [ ] Route integrity — N/A: no route
- [ ] Interaction behavior — N/A: no interaction
- [ ] Browser E2E if required — N/A: no page
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: no build
- [ ] Security checks if required — GAP-041's review opened; no OWNER authority given to the alternate
- [ ] Database safety review — no database read or changed
- [ ] Final diff review — only the role records, the runbook's governance slot and GAP-041's entry changed, with no person's name or contact

**COMPLETION EVIDENCE:**

- Test outputs: the Owner's record — the alternate designated by role and by whom, the alternate's role and host-access rule, the test alert to the alternate's phone, GAP-041's opened review, the dispute's governance named with the client — in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without personal data; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey in this Task
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: OPI-01's and OPI-24's rows, the post-incident runbook's governance slot and GAP-041's entry — nothing else
- Commit SHA: the commit that records them, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); while the alternate is not designated the Task is not DONE and GLC-21 holds go-live; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the Owner's record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-087 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- let an agent access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- give the alternate, the client or anyone not authorized OWNER authority, or the alternate standing host access beyond acting (TT-F16; TT-F25; P9Q-14)
- invent a deciding authority inside the application, or change AU-25 or a rule of PERMISSIONS_MATRIX for the dispute (DIR-083 OE-04)
- write a person's name, contact detail, phone number or account into the repository (INF-05)
- let an agent act as the operator or the alternate, or hold host access (INF-01; INF-02)
