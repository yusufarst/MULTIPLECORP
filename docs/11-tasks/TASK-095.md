# TASK-095 — Defects D8, D9 and D10 — a refused address's PG-005 with the shell's props; PG-065's password input across a 401; PG-013's unit and PIC after MSG-17 (IP-21; IP-11; UXS-28)

Status: APPROVED | Updated: 2026-11-13 | Owner: Planning

Approval: the one combined defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 20, which the Owner approved as Option A ([APPR-030](../00-governance/DECISION_LOG.md#dir-112-and-appr-030--the-owners-final-directive-and-option-a); DIR-112) from TASK-064's findings D8 and D9 and extended to D10 ([APPR-031](../00-governance/DECISION_LOG.md#dir-113-and-appr-031--d10-in-task-095-and-the-acceleration-of-2026-11-13); DIR-113) within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan, under DIR-111's release freeze; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

D8: an address a route's own `auth` or `capability` refuses answers PG-005 as an unknown address does — its status, the shell's hints and the interface language —, rendered without a page error, the refusal itself and every route's admission unchanged (AZ-11; IP-21; WS-07). D9: PG-065's password change interrupted by a 401 reproduced deterministically first; if the typed values are cleared while the in-place dialog covers the page, they are kept in the page's memory until the outcome is known (IP-11) — never stored anywhere else —, and the change is sent once after re-authentication; if not, the evidence recorded and no product change made. D10: after MSG-17 refuses PG-013's "Buat Proyek", the chosen client's units and PICs are read again, so the unit and PIC the form still holds stay shown and choosable — the client's own options only —, the refused command's values and the next attempt's intent unchanged (UXS-28; C-10; IP-02).

**WHY THIS EXISTS:**

TASK-064's journeys in fresh CI on `c1f8732` (run 43226273874), on Chromium and WebKit: CJ-09 found an Admin's address of the Owner's review queue answered 404 with a blank page — `Cannot read properties of undefined (reading 'name')` in PG-005's component —, the priority list running the route's `auth` and `capability` (TASK-011) before `SetLocale` and `HandleInertiaRequests`, so PG-005 (TASK-016) renders without the shared props; CJ-10 found PG-065's current-password field empty while the in-place dialog covers the page (expected the typed value, received ""), its form resetting on every finished request (TASK-023). The Owner approved one combined defect Task for both (APPR-030, Option A), no separate Tasks. CJ-19 on Chromium in the same run found PG-013's PIC empty and disabled after MSG-17 (expected a value, received ""); reproduced deterministically on Chromium against `main`: the refused request carried the chosen unit and PIC, the client's name stayed in its field, but the answer reloads the page without the chosen client's units and PICs, so the unit showed its placeholder and the PIC was empty and disabled (TASK-032's PG-013, against UXS-28's "input kept"); the Owner added it to this Task (APPR-031), no further Task.

**DEPENDS ON:**

- TASK-011 — Authorization core — capabilities, roles, grants, company scope and the route audit
- TASK-016 — Application shell, navigation and the company context
- TASK-023 — My account — password, two-step verification, Google link and language
- TASK-032 — Projects — list, form, journey page and amendment

**BLOCKS:**

- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status

**PARALLEL SAFE:** NO — it changes the order of the middleware every page passes

**IN SCOPE:**

- D8: `SetLocale`, `HandleInertiaRequests` and `SessionDeadline` placed in the middleware priority list before the authentication middleware, after the session's limits, so a refusal by a route's own `auth` or `capability` answers with the shell's shared props and headers; the refusal, its status and every route's admission unchanged
- D8: an interaction test — a refused address and an unknown one answered alike, PG-005 with the account's hints and language; a guest's protected address still sent to sign-in; an authorized account's page unchanged — and a browser test of the refused address on Chromium and WebKit, PG-005 rendered, no page error
- D9: a deterministic browser reproduction of APPR-030's eight points; if confirmed, PG-065's password form reset only after a known outcome, never by a request a 401 ends; the browser test
- D10: PG-013 reading the chosen client's units and PICs again when the page's answer comes back without them — once per client, the values the form holds untouched; the browser test of APPR-031's eight points on Chromium and WebKit
- TASK-064's CJ-09, CJ-10 and CJ-19, run in TASK-064's verification on the merged code

**OUT OF SCOPE:**

- capability decisions, routes, the in-place dialog and its gateway; WS-07's shared props (unchanged in content); other forms; PG-013's command, its payload, its numbering refusal and every other behaviour; TASK-064's journeys (their assertions unchanged)

**REFERENCES:**

- IP-11, IP-21 and UXH-02 in [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/s05-command-model.md); AZ-11 and WS-07 in [SECURITY](../05-security/SECURITY.md)
- [TASK-011](TASK-011.md), [TASK-016](TASK-016.md), [TASK-023](TASK-023.md); [TASK-064](TASK-064.md) — CJ-09, CJ-10
- [APPR-030](../00-governance/DECISION_LOG.md#dir-112-and-appr-030--the-owners-final-directive-and-option-a)

**EXPECTED MODULES / FILE AREAS:**

- `bootstrap/app.php` (the middleware priority list)
- `resources/js/Pages/IAM/OwnAccount.tsx` (the password form)
- `resources/js/Pages/PRJ/ProjectForm.tsx` (the client's units and PICs after a refusal); `tests/Browser/E2E/projects.spec.ts`
- `tests/Interaction/ShellTest.php`; `tests/Browser/E2E/access-denied.spec.ts`; `tests/Browser/E2E/own-account.spec.ts`

**BUSINESS RULES:**

- unchanged

**ARCHITECTURE RULES:**

- the framework's own middleware priority list; no new middleware, no change to what a middleware decides

**SECURITY / PERMISSION REQUIREMENTS:**

- every route's `auth` and `capability` decisions unchanged — only the order puts the language, the shared props and the session headers before them, so a refusal is answered as AZ-11 and IP-21 state; nothing of a refused record rendered (WS-07)
- PG-065: no password kept outside the page's memory — not in storage, history or a log (AU-12)

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
- Auth profile: N/A — the in-place re-authentication unchanged; PG-065 keeps its typed values while it covers the page (IP-11)
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — the middleware order only; every refusal and admission as before
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

- Module boundary affected: the HTTP kernel's middleware order; IAM's PG-065
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — the refused address's PG-005 and PG-065 across a 401, on Chromium and WebKit; TASK-064's CJ-09 and CJ-10
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: the order the priority list gives the web group's and the route's middleware
- Expected affected areas: every page's middleware order (unchanged decisions); PG-005; PG-065

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

1. An address refused by a route's `auth` for an Inertia or JSON request answers 401 as before, a guest's document request is sent to sign-in as before, and an address refused by a route's `capability` answers PG-005 with its status, the shell's hints and the interface language, as an unknown address does — rendered on Chromium and WebKit without a page error; nothing of the refused record exposed; no route newly reachable; the existing security tests unchanged (AZ-11; IP-21).
2. D9 reproduced or refuted with deterministic evidence. If reproduced: while the in-place dialog covers PG-065, the typed password fields keep their values in the page's memory only; after re-authentication the change is sent once; the values are cleared after a known outcome; no value is written to storage, history or a log (IP-11; AU-12).
3. D10: after MSG-17 on PG-013 the chosen client's context remains; the unit and PIC the form holds are shown and enabled, with the client's own options only; the refused command carried those values; a retry is a new intent with the same values (IP-02); no other PG-013 behaviour changes — on Chromium and WebKit (UXS-28; C-10).
4. TASK-064's CJ-09, CJ-10 and CJ-19 pass on Chromium and WebKit, their assertions unchanged.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A
- Feature: N/A
- Integration: N/A — no database path changes
- Authorization: the existing route audit and denial suites unchanged and green (TL-07)
- Route integrity: N/A — no route added or changed
- Interaction: `tests/Interaction/ShellTest.php` — a refused address and an unknown one answered alike with the hints and the language
- Browser E2E: TL-09 — `access-denied.spec.ts`, `own-account.spec.ts`'s and `projects.spec.ts`'s TASK-095 tests on Chromium and WebKit
- Localization/language switch: N/A — no new key; the refused address's PG-005 in the account's language
- Visual breathing room/density: N/A — no visual change
- Responsive: N/A — compositions unchanged
- Accessibility: N/A — unchanged
- Production build: TL-13
- Security: the refusal's status and every route's admission unchanged; nothing of a refused record rendered; no password kept outside the page's memory (AU-12)
- Performance: N/A — no request added
- Migration: N/A
- Other: none

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint in this head's CI (TL-01)
- [x] Typecheck: PHPStan and TypeScript in this head's CI (TL-02)
- [x] Relevant automated tests: `tests/Interaction/ShellTest.php` — a capability-refused address and an unknown one answered alike, PG-005 with the account's hints and language, the root view carrying them on a first load —; every layer in this head's CI
- [x] Authorization: the route audit (TL-07) and every denial suite unchanged and green; `access-denied.spec.ts` — the refusal's 404 kept, a guest still sent to sign-in, the Owner still admitted
- [x] Route integrity: N/A — no route added or changed
- [x] Interaction behavior: `access-denied.spec.ts` (D8), `own-account.spec.ts`'s TASK-095 test (D9) and `projects.spec.ts`'s TASK-095 test (D10)
- [x] Browser E2E if required: those tests on Chromium and WebKit (TL-09); CJ-09, CJ-10 and CJ-19 in TASK-064's verification on the merged code
- [x] Responsive/adaptive if required: N/A — compositions unchanged
- [x] Visual breathing room/density if UI: N/A — no visual change
- [x] Production build: TL-13 in this head's CI
- [x] Security checks if required: every route's `auth` and `capability` decisions unchanged, only their order after the language, the shared props and the session headers; nothing of a refused record rendered; PG-065's values kept in the page's memory only — `nothingKept` over history and storage while covered and after —; PG-013's options the chosen client's own, from the same partial reload as before
- [x] Database safety review: no schema change, no migration, no write path; production not touched
- [x] Final diff review: Codex's review of this pull request before the merge (DIR-096's bounded loop)

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16): this record's head, its fresh CI on GitHub's `ubuntu-24.04` runner — the push run and the pull request's run —, every job required to PASS before the merge, as the pull request's checks show (DIR-103: the record in the final code commit)
- Build result: TL-13 in the same runs
- Route evidence: N/A — no route added or changed
- Interaction evidence: `tests/Interaction/ShellTest.php`'s TASK-095 test
- Browser evidence: `E2E: PASS — Evidence: this head, CI ubuntu-24.04, tests/Browser/E2E/access-denied.spec.ts, own-account.spec.ts and projects.spec.ts on Chromium and WebKit`, as the pull request's checks show before the merge
- Localization evidence: no new key; the refused address's PG-005 in the account's language
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: `bootstrap/app.php`; `resources/js/Pages/IAM/OwnAccount.tsx`; `resources/js/Pages/PRJ/ProjectForm.tsx`; `tests/Interaction/ShellTest.php`; `tests/Browser/E2E/access-denied.spec.ts`, `own-account.spec.ts`, `projects.spec.ts`; `docs/11-tasks/TASK_PLAN.md`, this file, `TASK-011.md`, `TASK-016.md`, `TASK-023.md`, `TASK-032.md`, `TASK-064.md` (Change 20's mirrors); `docs/handoff/CURRENT_HANDOFF.md`; on the same branch, the Owner's record DIR-113 with APPR-031 (`docs/00-governance/DECISION_LOG.md`, its source)
- Commit SHA: this branch's commits on `task/TASK-095-release-stabilization` from `main`, the Owner's identity, no AI attribution
- Notes: **D8** — confirmed first by a server probe against `main` (`e07cc1f`): the Admin's `/tinjauan` answered PG-005 with `props.auth` null; with `SetLocale`, `HandleInertiaRequests` and `SessionDeadline` placed before the authentication middleware in the priority list the same probe passed — the fix; the framework's priority list had moved the route's `auth` and `capability` ahead of them. **D9** — reproduced deterministically on Chromium against `main`: the 401'd request carried the typed values, the fields shown were cleared, the replay after the sign-in sent the same values once (303) and changed the password, nothing was kept in storage or history; fixed by clearing the fields on a known outcome only (`onSuccess`, `onError`), never when a 401 ends the request. **D10** — reproduced deterministically on Chromium against `main`: the refused request carried the chosen unit and PIC, the client's name stayed, the unit showed its placeholder and the PIC was empty and disabled; fixed by reading the chosen client's units and PICs again — once per client — when the page's answer comes back without them, the values the form holds untouched. The local stack's answers took 8–11 s each on this workstation, so the probes ran locally and the regression evidence is CI's (DIR-105)
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-064 — its stabilization once TASK-093, TASK-094 and TASK-095 are DONE (DIR-112)

**DO NOT DO:**

- weaken the capability middleware, turn a refusal into a success, or add an authentication bypass
- keep a password anywhere but the page's memory to retain the form's input
- add to V1 — a capability, page, route, field, key, command or paid service — or change a requirement, an acceptance criterion or an expected value
- weaken, skip or delete a test, or change TASK-064's journey assertions or any existing security test, to obtain a green run (DOD-05; TS-06)
- change the database schema, touch the VPS, production or its data
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- mark DONE without the required tests passing in fresh CI and the completion evidence ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule))
