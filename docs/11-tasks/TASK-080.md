# TASK-080 — Alert channel adopted and monitoring live

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-05

**RISK CLASS:** HIGH

**EXECUTOR:** HUMAN OPERATOR

**OBJECTIVE:**

The human operator adopts the zero-cost alert channel OPI-05 records — its COST_POLICY §4C.4 record written first, its token kept as a secret — and makes TASK-075's monitoring live on the staging stack: the business heartbeat and its alerts, the backup, copy and disk alerts, the security alerts to the operator and the Owner, the log viewer and the external uptime check, each tested end to end on the operator's and the Owner's own phones, with the in-app notice and the daily look kept as the floor — ready for TASK-081's failure drill and for production.

**WHY THIS EXISTS:**

GAP-013 stays open until the channel is set up and the failure drill passes ([GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-013--successful-http-health-checks-can-hide-stalled-business-processing)); the tabletop found the alarm handled manually because the planned channel was not set up (TT-F01); DEP-08 asks for a viable channel identified and tested, never assumed; BK-10, H9-10 and H9-15 need failures and security signals to reach the named operator and the Owner; and GLC-03 and GLC-11 require the channel tested end to end, its free-tier record before adoption and monitoring live — each passed only by its recorded evidence ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-14). A failed off-server copy must reach a human too — the architecture challenge's sequence 13c.

**DEPENDS ON:**

- TASK-075 — Monitoring — the business heartbeat, alerts and the uptime check
- TASK-078 — Staging stack, the two applications' isolation and the provider features
- the Owner's answer to P11Q-05 recorded, with what it calls for done — a step run or a narrow amendment approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-081 — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal
- TASK-083 — The alternate operator named and the dispute's governance recorded

**PARALLEL SAFE:** NO — it adopts the one channel that every later alert, drill and incident uses, on the shared VPS

**IN SCOPE:**

- **The channel adopted** (OPI-05; DIR-079 CB-06; DEP-08): the zero-cost push channel TASK-071 recorded, its COST_POLICY §4C.4 record — limits, quota behaviour, lock-in, exit strategy and data portability — written before it is adopted (GLC-03) and entered in COST_POLICY's recurring-cost inventory (INFRASTRUCTURE §8); its token set as a secret in the environment only (SX-01); each message a neutral signal name with no S3 or S4 value (LG-03); the in-app notice at the Owner's next login and the operator's daily look kept as the floor; the optional self-service e-mail reset left off unless the channel is e-mail and P9 confirms it (INFRASTRUCTURE §6; AU-12)
- **Monitoring live on staging** (GLC-11's staging half): TASK-075's heartbeat and its alerts, HO-25's signals, the backup, copy and disk alerts (BK-10; BK-11), the statement statistics, the log viewer behind the operator's access, and the free external uptime check, its §4C.4 record written before adoption (DIR-079 CB-07, CB-10; INF-04)
- **The end-to-end tests** (GLC-03; GLC-16; GAP-013; TT-F01): a test alert from staging reaching the operator's and the Owner's own phones — one phone while the Owner is the operator (OPI-05) —; a failed backup and a failed off-server copy simulated on staging — never on the real destination's data — each reaching the named operator (BK-10; BKR-09; architecture challenge 13c); TT-F01's manual alarm retired only when these pass; the failure drill's full signal list is TASK-081's
- **The security alerts** (H9-10; H9-15; LG-07): a seeded security signal on staging reaching the operator and the Owner; the record stating whether the channel is one the operator does not solely control — if it is not, the Owner's floor stays the in-app notice (H9-06; AU-14; GAP-041), and a confirmed independent channel triggers GAP-041's review (RISK-005); the alternate's phone joins in TASK-083 (GLC-21)
- **Ready for production**: the same configuration, made live on production before any production data in TASK-084 (CUT-02)
- **The record**: each test's time, signal, recipient by role and result — never the token or a phone number

**OUT OF SCOPE:**

- the failure drill (TASK-081) and the alternate's phone (TASK-083)
- production's monitoring live, which needs production running — TASK-084, before any data (CUT-02)
- the alert rules and the delivery adapter themselves (TASK-075)
- a paid channel, SMS or mail service, tracker or uptime service (P9Q-03 (d); INF-04)

**REFERENCES:**

- [INFRASTRUCTURE §6](../09-operations/INFRASTRUCTURE.md#6-observability); [§8](../09-operations/INFRASTRUCTURE.md#8-capacity-and-cost); [§9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-05; [§10](../09-operations/INFRASTRUCTURE.md#10-owner-questions) P9Q-03, P9Q-04, P9Q-07 (DIR-079 CB-06, CB-07, CB-10)
- [BACKUP_RECOVERY §2](../09-operations/BACKUP_RECOVERY.md#2-the-twelve-obligations) BK-10, BK-11; [§1](../09-operations/BACKUP_RECOVERY.md#1-the-decision-and-its-limits) BKR-09; [§6](../09-operations/BACKUP_RECOVERY.md#the-exercise-held-in-pc-4) TT-F01
- [RELEASE_PLAN §7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills); [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-03, GLC-11, GLC-21
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-08
- [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-03, LG-07; [§10](../05-security/SECURITY.md#p9--operations) H9-06, H9-10, H9-15
- [COST_POLICY](../00-governance/COST_POLICY.md#recurring-cost-inventory) — the inventory and §4C.4
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-013--successful-http-health-checks-can-hide-stalled-business-processing) GAP-013; [GAP-041](../00-governance/GAP_REGISTER.md#gap-041--operator-abuse-of-the-owner-recovery-command)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-03, OE-14
- Architecture challenge — the sequence 13c of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (13): CAP-16; DEP-08; BK-10–BK-11; H9-10; H9-15; HO-25; OPI-05; TT-F01; GLC-03; GLC-11; GLC-16; GAP-013

**EXPECTED MODULES / FILE AREAS:**

- no code; the free-tier records of the channel and the uptime check in [COST_POLICY](../00-governance/COST_POLICY.md#recurring-cost-inventory)'s recurring-cost inventory, changed under CHANGE_CONTROL; the token and the phones held by the operator outside the repository

**BUSINESS RULES:**

- N/A — no business rule; the alerts are operational and security signals

**ARCHITECTURE RULES:**

- the channel and the uptime check at zero cost, the log viewer self-hosted; no error data or log leaves the VPS — only neutral alert messages (P9Q-04; INF-04)
- the floor is kept whatever the channel: the in-app notice for the Owner and the operator's daily look at the heartbeat (DIR-079 CB-06)

**SECURITY / PERMISSION REQUIREMENTS:**

- the channel's token a secret of SX-01, in the environment only; no message holds a secret, a credential token or an S3 or S4 value (LG-03)
- the Owner's alerts go through a channel the operator does not solely control once one is confirmed, otherwise by the in-app floor (LG-07; H9-06; GAP-041)
- the log viewer reached only through the operator's administrative access (LG-05)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the alerts name a neutral signal and its time, never a company record or another company's data

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command, lock or job is added; a test alert changes no business state

**AUDIT:**

- N/A — no business action; the security events behind the security alerts are TASK-010's (LG-02); each test alert's time, signal, recipient by role and result is in the operator's record

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: YES — the channel sits behind TASK-075's adapter
- External/vendor-specific code isolated: N/A — no code; the channel's configuration only
- Replaceable adapter/contract required: NO — TASK-075's adapter already isolates the channel
- Provider lock-in risk: NONE — each free tier's exit is in its §4C.4 record
- Future extension impact: LOW
- Speculative abstraction avoided: YES — one channel and one uptime check

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: N/A — no code; the profile stays LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: N/A — not touched; the e-mail reset stays off unless the channel is e-mail and P9 confirms it (AU-12)
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered; the channel and the uptime check are free tiers, each recorded under COST_POLICY §4C.4 before it is adopted (GLC-03; GLC-11)
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: N/A — the operator configures what TASK-075 built and verified
- Graphify: N/A — no code
- Browser E2E: N/A — no page
- Other: the channel's own set-up on the operator's and the Owner's phones; the uptime service's official terms page, read and dated for the §4C.4 record; the staging stack of TASK-078
- Missing safe tool behavior: N/A — no agent installs anything; the operator sets up only the free channel and check OPI-05 and DIR-079 CB-10 name, and a paid tier STOPS for the Owner (COST_POLICY §4C.2)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: which signals TASK-075 alerts on and to whom; whether the channel is one the operator does not solely control
- Expected affected areas: the staging stack's monitoring and COST_POLICY's inventory only

**CONTEXT7:**

- Topics to verify: N/A — no library is configured beyond TASK-075's adapter
- Version-sensitive assumptions: none — each free tier's terms read and dated at adoption

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed; the Owner's in-app floor is TASK-058's *Beranda* row
- Indonesian default: N/A — no page is built or changed
- English support: N/A — no page is built or changed
- Plain-language Indonesian: N/A — no page is built or changed
- Language switch impact: NONE
- Mobile-first: N/A — no page; the alerts reach the operator's and the Owner's own phones
- Responsive: N/A — no page is built or changed
- Adaptive: N/A — no page is built or changed
- Visual breathing room: N/A — no page is built or changed
- Comfortable density: N/A — no page is built or changed
- shadcn/ui reuse: N/A — no page is built or changed
- Design reference: N/A — no page is built or changed
- Primary action: N/A — no page is built or changed
- Secondary actions: N/A — no page is built or changed
- Destructive actions: N/A — no page; a simulated failure touches synthetic data only
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; an alert that does not arrive fails the test and keeps the manual alarm (TT-F01)

**ROUTE IMPACT:**

- none — the uptime check reads TASK-072's minimal health check; no route is added

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

1. The channel's COST_POLICY §4C.4 record exists before it is adopted, and its token lives in the environment only (GLC-03; SX-01).
2. A test alert from staging reaches the operator's and the Owner's own phones as a neutral signal name with no S3 or S4 value (OPI-05; LG-03; GAP-013).
3. A simulated failed backup and a simulated failed off-server copy on staging each reach the named operator (BK-10; BKR-09; architecture challenge 13c).
4. A seeded security signal reaches the operator and the Owner, and the record states whether the channel is one the operator does not solely control — the Owner's in-app floor staying where it is not (H9-10; H9-15; H9-06).
5. The heartbeat with its alerts, the log viewer and the external uptime check run on staging, the uptime check's §4C.4 record written before its adoption (GLC-11; INF-04).
6. TT-F01's manual alarm is retired only after these tests pass, and no paid channel or service is adopted (TT-F01; INF-04).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no code; the end-to-end alert tests on the staging stack stand in its place
- Authorization: N/A — no route; the log viewer reached through the operator's access only
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no user-facing copy; alerts are neutral signal names
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — no build in this Task
- Security: no token, credential token or S3 or S4 value in a test message or a log line (TL-14; LG-03)
- Performance: N/A — no target is measured here
- Migration: N/A — no migration or import code in this Task
- Other: each test alert recorded with its time, signal, recipient by role and result

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the operator's record of each end-to-end alert test stands in their place
- [ ] Authorization — the log viewer reached through the operator's access only; the token held by the operator
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — N/A: no interaction built or changed
- [ ] Browser E2E if required — N/A: no page
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: no build
- [ ] Security checks if required — no token, secret or S3 or S4 value in any test message; the channel's independence from the operator recorded
- [ ] Database safety review — simulated failures on staging's synthetic data only
- [ ] Final diff review — only the free-tier records of COST_POLICY's inventory changed, without a token or a phone number

**COMPLETION EVIDENCE:**

- Test outputs: the operator's record — the channel's adoption after its §4C.4 record, each test alert's time, signal, recipient by role and result, the simulated backup and copy failures, the seeded security signal, the uptime check and the log viewer live on staging — in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without secrets; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey in this Task
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20); the channel's and the uptime check's COST_POLICY §4C.4 records
- Files changed: the free-tier records in COST_POLICY's recurring-cost inventory — nothing else
- Commit SHA: the commit that records them, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); production's monitoring made live before any data in TASK-084, the alternate's phone in TASK-083; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the operator's record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-081 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- let an agent access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- adopt a paid channel, SMS, mail or uptime service, or a free tier before its §4C.4 record (INF-04; COST_POLICY §4C.2, §4C.4)
- put the channel's token, a phone number or a secret in the repository, a message or a log (SX-01; INF-05)
- turn on the self-service e-mail reset unless the channel is e-mail and P9 confirms it (AU-12; INFRASTRUCTURE §6)
- record the channel as tested on a planned or partial test, or retire the manual alarm before the tests pass (DIR-083 OE-14; TT-F01)
