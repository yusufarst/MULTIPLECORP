# Definition of done

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-012](../00-governance/DECISION_LOG.md#appr-012--p8-testing-quality-and-definition-of-done-approved), explicit Owner approval on 2026-10-19 (DIR-083 OE-15) of this document as committed in run PC-5's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-20 DOD-14 requires the relevant TestSprite flows only where TestSprite is available at no cost — otherwise Playwright's suite and the other relevant tests are the proof —, no required test, security or accessibility coverage reduced; a check on the stack's "not found" page verifies only a tool's availability. It is the narrow amendment the Owner approved ([APPR-017](../00-governance/DECISION_LOG.md#dir-087-and-appr-017--execution-time-owner-decisions-and-the-testsprite-amendment)) for the Owner's decision that TestSprite is optional (DIR-087), and the document keeps the APPROVED status of APPR-012.

Run PC-4 drafted this document on 2026-10-19 under [DIR-082](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) SP-85, P8 being authorized by the Owner's [DIR-081](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) OB-09; the Owner approved P8 at OG-3 (DIR-083 OE-15). No Task exists yet — Tasks are created in P11 (AICWDF §22) —, so nothing here has been applied.

**Sources.** AICWDF §19's Task statuses and §§21, 25–28; [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md)'s Task model, Task contract and terminology; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md)' feature, module and release completion obligations — the eight dimensions, the reasoned N/A rule and the dispatch, payment and invoice examples; the brief's §44, the definition-of-done principle ([source record](../00-governance/sources/OWNER_BRIEF_2026-09-27.txt)); [ENGINEERING_PRINCIPLES](../00-governance/ENGINEERING_PRINCIPLES.md#verification-and-completion); REFERENCE_COVERAGE's REF-G06 and its P8 row; [LOCALIZATION §3.6](../07-ux-design/LOCALIZATION.md#36-both-languages-no-missing-string); GAP-024 and GAP-037 ([GAP_REGISTER](../00-governance/GAP_REGISTER.md)).

Authority: P8 — the definition of done that **every P11 Task cites**, by its DOD identifiers, in its STATUS, TEST CHECKLIST and COMPLETION EVIDENCE. It adds no requirement to a Task beyond what the approved documents give it; it states how each is shown. Module and release aggregation is P10's ([RELEASE_PLAN](../10-release/RELEASE_PLAN.md)); the tests are [TEST_STRATEGY](TEST_STRATEGY.md)'s, [SECURITY_TEST_MATRIX](SECURITY_TEST_MATRIX.md)'s and [PERFORMANCE_TARGETS](PERFORMANCE_TARGETS.md)', and [TEST_STRATEGY §19](TEST_STRATEGY.md#19-coverage) maps every requirement to this document's sections (DOD).

## 1. Statuses and the DONE rule

- **Statuses** — TODO, READY, IN_PROGRESS, BLOCKED, VERIFYING, DONE, SUPERSEDED (AICWDF §19). **VERIFYING is not DONE.**
- **DONE** means: the required implementation is complete, the required tests pass locally and again in fresh CI, and the completion evidence exists (AICWDF §22.2) — every item of §2 answered for the Task, each **PASS** with its evidence or **N/A** with its reason under DOD-03. A working UI alone is not DONE (AGENT_OPERATING_MODEL).
- **On DONE** the agent sets the status, checks the Task in the Task plan, updates its counters and updates CURRENT_HANDOFF (AGENT_OPERATING_MODEL, the automatic progress update).
- **Not DONE while a check fails.** A Task whose required check fails or has not run stays VERIFYING — or is BLOCKED when the check cannot run — and is never DONE (AGENT_OPERATING_MODEL); a regression found after DONE is a new defect Task, and the original Task's identifier and history stay as they are.

## 2. The checklist

Each Task's contract answers every item: PASS with its evidence or N/A with its reason (DOD-03); none is omitted.

**Completion and evidence**

| DOD | Item | Shown by |
| --- | --- | --- |
| DOD-01 | **Scope and acceptance.** Everything IN SCOPE is delivered and nothing OUT OF SCOPE; every acceptance criterion holds; no requirement is silently dropped | the Task's ACCEPTANCE CRITERIA, each with its test |
| DOD-02 | **The tests.** The Task's TEST REQUIREMENTS and TEST CHECKLIST are written and pass — every layer TEST_STRATEGY §2 requires for the kind of change —, locally and again in fresh CI | the run's evidence (TEST_STRATEGY §16) |
| DOD-03 | **N/A only with a reason.** A non-applicable item names its specific reason, which the reviewer accepts; a missing tool, insufficient time or a failed check is never N/A; a required check that cannot run makes the Task BLOCKED; every category keeps its reasoned answer (REF-G06) | the item's answer in the Task |
| DOD-04 | **Evidence of what ran.** COMPLETION EVIDENCE in TEST_STRATEGY §16's form, tied to the tested commit; planned checks never reported as run | the Task's COMPLETION EVIDENCE |
| DOD-05 | **No weakened test.** No valid test was weakened, skipped or deleted to obtain a green run | the diff's tests and the reviewer's check |
| DOD-06 | **Documentation and current state.** The documents the Task changes are updated with it, and CURRENT_HANDOFF on DONE | the diff |

**The eight dimensions** (ACCEPTANCE_CRITERIA, evaluated explicitly for every Task)

| DOD | Dimension | Shown by |
| --- | --- | --- |
| DOD-07 | **1. Business** — the required capabilities, acceptance and material exception paths hold; policy decisions resolved; business correctness | the Task's acceptance tests; the numeric fixtures where its rules have them (FX-01) |
| DOD-08 | **2. UI/UX** — natural Indonesian, shadcn-based consistency, mobile and desktop usability and accessibility, loading, empty, error and conflict states; AICWDF §27's gate answered item by item | TEST_STRATEGY §9; TL-09–TL-12 |
| DOD-09 | **3. Backend** — server validation, correct use-case behaviour, meaningful failures and error handling, maintainable thin entry points | TL-04; the Task's refusal cases |
| DOD-10 | **4. Database** — correct migrations, expand-only within a release; the required foreign-key, unique, check and not-null constraints; indexes; safe money and data representation; the DATABASE IMPACT ASSESSMENT and the release's DB CHANGE declaration | the C-rows the Task touches (TEST_STRATEGY §5); TL-16 |
| DOD-11 | **5. Security and authorization** — authentication; capability, company and resource isolation; IDOR and BOLA; CSRF, session and cookie; XSS, SQL injection and clickjacking; private uploads and downloads; the COMPANY SCOPE AND FIELD PROJECTION field | the Task's cases of SECURITY_TEST_MATRIX, positive and denial |
| DOD-12 | **6. Concurrency, idempotency and integrity** — atomic effects; race, duplicate and retry protection; state preconditions; ledger and balance consistency; corrections and audit; the CONCURRENCY / IDEMPOTENCY field | the Task's M rows and ⚡ C-rows; `ReconcileGuards` clean (TEST_STRATEGY §6) |
| DOD-13 | **7. Performance and N+1** — query evidence, bounded loading, pagination and search, verified indexes, no N+1 regression, measured targets where defined | the CI half of PERFORMANCE_TARGETS PFM-01–PFM-03 for the Task's pages and queries — query counts, N+1 and plans on the scale fixtures —; staging times belong to the release-critical set (PFT §4), a miss there opening a defect Task and AS-07's review |
| DOD-14 | **8. Automated verification** — Pest backend and integration tests; the relevant TestSprite flows in the role P8Q-01 settles where TestSprite is available at no cost, otherwise Playwright's suite and the other relevant tests (APPR-017); fresh CI; static analysis and style; frontend typecheck and build; regression — Layer 1, and Layer 2 when the Task closes a group —; business UAT where applicable | TL-01–TL-05, TL-13; TEST_STRATEGY §§3, 12 |

**The project's further items**

| DOD | Item | Shown by |
| --- | --- | --- |
| DOD-15 | **Route and interaction integrity** — the Task's routes declared and audited, its INT- tests and NAV- paths passing, AICWDF §25's nine zeros holding on its pages | TL-07, TL-08; TEST_STRATEGY §8 |
| DOD-16 | **Localization** — no missing string; both languages checked on every page the Task changes; documents and business data Indonesian; its new copy listed for the page's review (`DIR-081` OB-04); the localization fields of the AICWDF §23 template filled | TL-10; TEST_STRATEGY §10 |
| DOD-17 | **Accessibility critical checks** on every page the Task changes | TL-12; TEST_STRATEGY §11 |
| DOD-18 | **Browser E2E** — the Task's journeys, or the steps it adds to one, pass with evidence: `E2E: PASS — Evidence: …` | TL-09; TEST_STRATEGY §7 |
| DOD-19 | **Audit** — the business audit and security events, correction linkage and actor attribution of the AUDIT field | the Task's audit assertions |
| DOD-20 | **Cost and toolchain** — ADDITIONAL RECURRING COST CHANGE: NONE, or an approved exception (COST_POLICY); every tool the Task introduces verified and recorded in TOOLCHAIN | the Task's cost line; TOOLCHAIN |
| DOD-21 | **Synthetic data and no secret** — no real data, secret, credential, host detail or production configuration in code, tests, fixtures or evidence | the CI's secret-like scan (SM-15); the review |
| DOD-22 | **The production boundary** — no direct production access or mutation by an agent; a migration designed and tested outside production, rehearsed on staging and run by the operator (AICWDF §29) | the Task's DATABASE IMPACT ASSESSMENT; RELEASE_PLAN |

## 3. The brief's categories

Every category the brief's §44 lists is answered by an item, explicitly, with "why it applies or does not apply":

| Brief §44 | DOD |
| --- | --- |
| business correctness | DOD-07 |
| authorization; company isolation; security | DOD-11 |
| validation; error handling | DOD-09 |
| DB constraints | DOD-10 |
| N+1; performance implications | DOD-13 |
| race conditions; idempotency | DOD-12 |
| auditability | DOD-19 |
| automated tests; regression | DOD-02, DOD-14 |

## 4. The completion examples

ACCEPTANCE_CRITERIA's minimum examples, each covered by the items and tests named — minimum examples, not exhaustive tests:

- **Inventory dispatch** — authorization and company scope (DOD-11); a valid barcode, product, project and quantity and sufficient stock (DOD-07, DOD-09; C-01, C-03, C-04); duplicates and concurrent dispatch (DOD-12; M-01, M-05); movement, balance and audit (DOD-12, DOD-19; `ReconcileGuards`); reversal (CM rows; C-55); N+1 (DOD-13); responsive interaction (DOD-08; CJ-07); backend, concurrency and regression checks (DOD-14) — AC-03, AC-06, AC-13, AC-14, AC-16 and AC-22.
- **Payment** — normal, full, partial, DP, termin and overpayment (FX-01 1–5; CJ-04); invalid amounts (DOD-09); isolation (DOD-11); duplicate and idempotent requests (M-08–M-12); correction and reallocation (CM rows); audit (DOD-19); the correct receivable and secured proof (C-13–C-19; SM-09) — AC-10, AC-12, AC-13 and AC-16.
- **Invoice** — project, client, company, quantity and value snapshots (DOD-07); unique company, type and period numbering under concurrency (C-10; M-15–M-19); PDF and print (CJ-15); issue locking, revision and void history (C-11, C-53, C-56); access and tests (DOD-11) — AC-01, AC-08, AC-12, AC-13, GAP-007 and GAP-016.

## 5. Module and release completion

A **module** is complete only when its Tasks are DONE and its cross-module protections work together — inventory with identity and barcodes, receiving, balances, the ledger, dispatch, locations and minimum stock, adjustment, opname, returns and reversal, drop-ship, allocation and attribution, access, concurrency and idempotency, and regression — a stock page alone does not pass —; billing, payment and documents reconciling together rather than passing as separate screens (ACCEPTANCE_CRITERIA). Its proof is Layer 2's regression over the module's critical journeys (TEST_STRATEGY §3). The aggregation of modules into a release, and the V1 release completion, are [RELEASE_PLAN](../10-release/RELEASE_PLAN.md)'s.

## 6. Coverage

Every requirement this document answers is a row of [TEST_STRATEGY §19](TEST_STRATEGY.md#19-coverage), which lists its section here.
