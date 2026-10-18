# P8 quality gate

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

*Written by run PC-4 under [DIR-082](../../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) SP-88 and §8 item 10, in English, in its last commit. It records gate P8 — AICWDF §19's items with §§4B, 25–28, its coverage and the combined review — on P8's REVIEW drafts. A PASS says that the drafts state the item, with its evidence; it is not the Owner's approval of P8, a separate decision at OG-3 ([DIR-081](../../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) OB-09), and no test, installation or deployment has run: the drafts are the plan that P11's Tasks and P10's releases carry out.*

## The drafts

| Document | Holds | Identifiers |
| --- | --- | --- |
| [TEST_STRATEGY](../TEST_STRATEGY.md) | The principles, the sixteen test layers, the three regression layers, environments and data, the domain proofs with the acceptance contracts, the concurrency and failure proofs, the critical journeys, route and interaction integrity, the UI/UX consistency gate, localization, accessibility, devices and UAT, migration, backup and restore tests, evidence, tooling, the Owner questions and the coverage | TS-01–TS-12, TL-01–TL-16, CJ-01–CJ-22, FX-01–FX-10; P8Q-01, P8Q-02 |
| [SECURITY_TEST_MATRIX](../SECURITY_TEST_MATRIX.md) | How a case is written and judged, the fixtures, the matrix, the data paths, the capability rows, the Owner-only denials, the threats and the accepted risks | SM-01–SM-25 |
| [PERFORMANCE_TARGETS](../PERFORMANCE_TARGETS.md) | How a target is measured, the query budgets, the measured targets, the release-critical set and what P9 receives | PFM-01–PFM-20 |
| [DEFINITION_OF_DONE](../DEFINITION_OF_DONE.md) | The statuses and the DONE rule, the per-Task checklist, the brief's categories, the completion examples and module and release completion | DOD-01–DOD-22 |

Drafted in Part 3 (`a5e2b9694ffa87b781bbf57d553eea80c21a54d6`), revised in Part 4 (`0e30dc99e660bb9cc1420d2948ad9a7876b14ef4`) for P9's completion and in Part 5 with the combined review's fixes; every revision's SHA-256 in [TECH-039](../../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed).

## Gate P8

| Item | Check | Result | Evidence |
| --- | --- | --- | --- |
| P8-01 | AICWDF §19 — the sixteen applicable test layers, each with what it holds, where it runs and when a Task must run it | PASS | TEST_STRATEGY §2, TL-01–TL-16 — lint, typecheck, unit, feature, integration, authorization, route integrity, interaction, E2E/browser, localization and language switch, responsive, accessibility critical checks, production build, security, performance and migration |
| P8-02 | §19 — the seven Task statuses; VERIFYING is not DONE | PASS | DEFINITION_OF_DONE §1; TEST_STRATEGY TS-12: a Task whose check fails or has not run stays VERIFYING, or BLOCKED when the check cannot run, never DONE |
| P8-03 | §19 — testing inside each Task; a definition of done P11 can use | PASS | DEFINITION_OF_DONE §§1–5, DOD-01–DOD-22: every item answered for each Task, PASS with its evidence or N/A with its reason (DOD-03), none omitted; the brief's categories and the completion examples; module and release completion with idempotency |
| P8-04 | §28 — the three regression layers | PASS | TEST_STRATEGY §3: Task verification, critical-journey regression, full release regression |
| P8-05 | §25 — route and interaction integrity | PASS | TEST_STRATEGY §8 over the approved contracts — 210 ROUTE-, 165 INT- and 65 NAV- —; TL-07, TL-08; SECURITY_TEST_MATRIX SM-02's route audit |
| P8-06 | §26 — browser E2E | PASS | TEST_STRATEGY §7: CJ-01–CJ-22, the 44 UX scenarios UXS-01–UXS-44 and ADMIN_FLOW's 39 journeys, each run in a critical journey; the E2E capability and TestSprite's role are P8Q-01, an Owner question with its recommendation (§18) |
| P8-07 | §27 — the UI/UX consistency gate | PASS | TEST_STRATEGY §9, the document composer's tolerance included |
| P8-08 | §4B — localization and the language switch | PASS | TEST_STRATEGY §10; TL-10; GAP-037's P8 part |
| P8-09 | Accessibility, devices and UAT | PASS | TEST_STRATEGY §§11–12; the supported browsers and devices are P8Q-02, an Owner question, its option (b) flagged as a P1 change |
| P8-10 | Security tests | PASS | SECURITY_TEST_MATRIX §§1–9: SM-01–SM-25 over the fixtures, the data paths, the capability rows, the Owner-only denials, the threats and the accepted risks — SM-25 field projection within a company in scope —; TEST_STRATEGY §13 |
| P8-11 | Performance | PASS | PERFORMANCE_TARGETS §§1–5: the query budgets, PFM-01–PFM-20, the release-critical set and what P9 receives, a miss split between CI and staging; TEST_STRATEGY §14 |
| P8-12 | Domain, concurrency, migration, backup and restore proofs | PASS | TEST_STRATEGY §5 — 65 C- constraints, 23 of them races, and AC-01–AC-23 each with its proof —, §6 — M-01–M-46 on real PostgreSQL —, §15 — migration, backup and restore tests |
| P8-13 | Coverage — every requirement P0–P7 and P9's drafts hand to P8 answered | PASS | [TEST_STRATEGY §19](../TEST_STRATEGY.md#19-coverage): 335 rows from the scripted "P8" search's 198 units; by script, every unit mapped, every cited section present and holding its token; 0 missing |
| P8-14 | The combined review — every finding fixed at Level 1 or an Owner question | PASS | [PC-4 combined review](../../10-release/evidence/P10_QUALITY_GATE.md#pc-4-combined-review): 74 PC4F rows — CRITICAL 0, HIGH 16, MEDIUM 38, LOW 20 —, B1's 33 on P8 and P10 among them; none open without an Owner question |
| P8-15 | Links, anchors and identifiers; no placeholder, secret, host or production detail | PASS | by script, 5,210 relative links and anchors in 144 Markdown files resolve; TS-, TL-, CJ-, FX-, SM-, PFM- and DOD- defined once and mapped in CONTEXT_INDEX; the drafts scanned: no placeholder, secret, host, address or production detail |
| P8-16 | Nothing claimed as tested; no V1 capability, acceptance criterion or mandatory release check lost | PASS | the drafts state tests to run, never a result; AC-01–AC-23 each with its proof (TEST_STRATEGY §5); AICWDF §21's release checks carried by RELEASE_PLAN §4 |

**Result.** Gate P8: 16 of 16 PASS. P8Q-01 and P8Q-02 go to the Owner at OG-3 with their recommendations ([TEST_STRATEGY §18](../TEST_STRATEGY.md#18-owner-questions)); P8 stays IN_PROGRESS until the Owner approves it.
