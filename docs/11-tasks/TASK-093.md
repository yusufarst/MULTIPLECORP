# TASK-093 — Defect D6 — the in-place re-authentication resumes only its own account and only on a checked password (UXH-02; IP-11)

Status: APPROVED | Updated: 2026-11-12 | Owner: Planning

Approval: a Level 1 defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 19, created through the executor feedback loop from TASK-064's defect D6 within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** IN_PROGRESS

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Make the in-place re-authentication resume the covered page — uncover it and resend its pending command — only for the very account it covers, and only after the endpoint has checked the password: any other account, even one whose display name, capabilities and companies match, gets the full document load before anything is uncovered or resent; an answer that is not the endpoint's own success — a redirect followed to a page — is a refusal (UXH-02; IP-11; ADMIN_FLOW §6).

**WHY THIS EXISTS:**

TASK-064's CJ-21 test 4, in fresh CI on `f976270` (runs 43219763584 and 43219798310), found on Chromium and WebKit that a second account with matching hints signs in at the dialog and the guard resends the first account's pending command under its own session — ADMIN_FLOW §6's residual, whose P8 proof has failed —; and `postAnswer()` reads a signed-in session's sign-in request, which the guest guard redirects without checking the password, as a success, so after a 419 on a live session any password resumes the page. Both are TASK-016's guard, a security defect (Change 19; DIR-110).

**DEPENDS ON:**

- TASK-016 — Application shell, navigation and the company context

**BLOCKS:**

- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status

**PARALLEL SAFE:** NO — it changes the shell's session guard every page mounts

**IN SCOPE:**

- the session's account as an opaque key — a keyed digest of the account, no business data — on every authenticated response's header and the root view's meta tag, beside the deadline and outside WS-07's page data (ADMIN_FLOW §6)
- the guard keeping the covered page's key in memory and resuming only when the new session's key equals it and its hints match; any other answer the full document load, never a resend (UXH-02)
- the dialog's answer read as a success only from the endpoint's own JSON — `signed_in`, `confirmed`, `two_factor` or the enrolment answer —, a redirect followed to a page or any other answer a refusal; the shell fixture's gateway answering as the real endpoints do
- unit tests of the comparison and of the answer's reading; interaction tests of the key — distinct for two accounts whose hints match, none for a guest — and of the guest guard's redirect of a signed-in session's sign-in request; TASK-064's CJ-21 test 4

**OUT OF SCOPE:**

- the dialog's copy and layout; the session limits; WS-07's shared props (unchanged); TASK-033's PG-014 (TASK-094)

**REFERENCES:**

- [ADMIN_FLOW §6](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md) — the in-place dialog, UXH-02 and its residual; IP-11
- [SECURITY](../05-security/SECURITY.md) WS-07, AU-07, AU-08
- [TASK-016](TASK-016.md); [TASK-064](TASK-064.md) — CJ-21

**EXPECTED MODULES / FILE AREAS:**

- `app/Http/Middleware/SessionDeadline.php`; `resources/views/app.blade.php`; `resources/js/lib/session-core.ts`, `session-guard.tsx`, `session-account.ts`; `tests/Support/Fixtures/ShellFixture.php`; `tests/Frontend/session-reauth.test.ts`; `tests/Interaction/ShellTest.php`

**BUSINESS RULES:**

- unchanged

**ARCHITECTURE RULES:**

- the account key outside the page data like the deadline; no shared prop added; no new dependency

**SECURITY / PERMISSION REQUIREMENTS:**

- unchanged — each page's own capabilities

**COMPANY SCOPE AND FIELD PROJECTION:**

- unchanged

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command changes

**AUDIT:**

- N/A — no audit event changes

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — nothing changes
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

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: the shell's session guard and its middleware
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL — Inertia's http hooks at the installed version
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — TASK-064's CJ-21
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: what reaches the client outside WS-07's page data
- Expected affected areas: the session guard

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES — the existing keys
- English support: YES — the existing keys in both languages
- Plain-language Indonesian: YES — the existing copy
- Language switch impact: NONE
- Mobile-first: YES — unchanged
- Responsive: YES — the existing compositions
- Adaptive: YES — the existing compositions
- Visual breathing room: YES — the existing tokens
- Comfortable density: YES — unchanged
- shadcn/ui reuse: YES — the existing components only
- Design reference: DESIGN_SYSTEM's own rows named in REFERENCES
- Primary action: unchanged
- Secondary actions: unchanged
- Destructive actions: unchanged
- Redundancy constraints: none added
- Loading state: unchanged
- Empty state: unchanged
- Error state: unchanged

**ROUTE IMPACT:**

- none

**INTERACTION CONTRACTS:**

- N/A — no INT- row changes

**NAVIGATION CONTRACT:**

- Parent: unchanged
- Entry: unchanged
- Back action: unchanged
- Cancel: unchanged
- Success destination: unchanged
- Error behavior: unchanged
- State preservation: unchanged
- Mobile behavior: unchanged

**ACCEPTANCE CRITERIA:**

1. After a re-authentication at the in-place dialog, the covered page is uncovered and its pending command resent only when the new session belongs to the same account — its opaque key equal and its hints matching —; any other account, even one whose display name, capabilities and companies match, gets the full document load and nothing of the page or its command (UXH-02; IP-11).
2. The dialog accepts a sign-in or a confirmation only on the endpoint's own JSON success; a redirect followed to a page — a signed-in session's sign-in request — or any other answer is a refusal, the dialog staying open and nothing resent.
3. TASK-064's CJ-21 passes on Chromium and WebKit, its assertions unchanged.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the comparison by key and hints; the answer's reading
- Feature: the account key on authenticated responses and the root view, distinct per account, none for a guest; the guest guard's redirect of a signed-in sign-in request
- Integration: N/A — no database path changes
- Authorization: N/A — unchanged
- Route integrity: N/A — no route
- Interaction: TASK-064's CJ-21 test 4 (browser)
- Browser E2E: TL-09 — CJ-21 and the shell spec on Chromium and WebKit
- Localization/language switch: N/A — no new key
- Visual breathing room/density: N/A — existing tokens
- Responsive: N/A — compositions unchanged
- Accessibility: unchanged
- Production build: TL-13
- Security: N/A
- Performance: N/A — no request added
- Migration: N/A
- Other: none

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
- Route evidence: N/A — no route is added or changed
- Interaction evidence: the browser test named in TEST REQUIREMENTS
- Browser evidence: `E2E: PASS — Evidence: …` on Chromium and WebKit
- Localization evidence: no new key unless named in IN SCOPE; existing keys in both languages
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-064 — its verification resumed once TASK-093 and TASK-094 are DONE

**DO NOT DO:**

- add to V1 — a capability, page, route, field, key, command or paid service — or change a requirement, an acceptance criterion or an expected value
- weaken, skip or delete a test, or change TASK-064's journey assertions, to obtain a green run (DOD-05; TS-06)
- change the database schema, touch the VPS, production or its data
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- mark DONE without the required tests passing in fresh CI and the completion evidence ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule))
