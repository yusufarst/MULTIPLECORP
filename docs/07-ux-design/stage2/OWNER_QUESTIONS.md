# Owner questions — S2-1

Status: DRAFT | Updated: 2026-10-17 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §14. Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** [CHANGE_CONTROL](../../00-governance/CHANGE_CONTROL.md#decision-authority) — Levels 2 and 3 and the form of a flagged decision; [GAP_REGISTER](../../00-governance/GAP_REGISTER.md); DIR-064 §14 and SP-21; the S2-1 documents each question comes from, linked in its entry. No technology behaviour is stated here beyond what the linked documents verify.

**S2-2 decisions.** On 2026-10-14 the Owner decided OQ-01 (b), OQ-06 (c), OQ-07 (a) and OQ-09 (a) under [DIR-065](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes); OQ-02, OQ-03, OQ-04, OQ-05 and OQ-08 stay open and deferred, OQ-04's option (a) shown until the preview.

**GF-2, GF-2A and PC-1 decisions** (note of 2026-10-17, written by run PC-1 under [DIR-075](../../00-governance/DECISION_LOG.md#dir-073dir-075-obs-029-and-tech-036--p7-ux-re-baseline-cp-1r-verdict-p7p11-completion-plan-run-pc-1-and-stage-2-completion) §10 item 2). Run GF-2's checkpoint CP-0 asked OQ-10–OQ-16, which the Owner decided on 2026-10-15 under [DIR-069](../../00-governance/DECISION_LOG.md#dir-069-dir-070-obs-027-and-tech-035--p7-ux-re-baseline-gf-2-decisions-authorization-baseline-and-corrected-pilot), OQ-15 deferred; GF-2's proposed visuals raised OQ-17 and OQ-18 (SP-39 (b)). At CP-1 the Owner chose OQ-13's concept A and kept OQ-15 and OQ-17–OQ-18 open under [DIR-071](../../00-governance/DECISION_LOG.md#dir-071-dir-072-and-obs-028--p7-ux-re-baseline-gf-2-cp-1-decisions-amendment-gf-2a-and-its-baseline) (CQ-2–CQ-4). DIR-075 replaces the preview by the Owner's checkpoint CP-A: OQ-04, OQ-15, OQ-17, OQ-18 and the phone test (OQ-07) are on CP-A's decision sheet, with OQ-02, OQ-03, OQ-05, OQ-08 and GAP-052 marked as needed before Stage 3 (PC-2). Planning's options and recommendations for OQ-10–OQ-16 were given in the planning session and are not transcribed (the sixty-eighth source record's note); their entries state the decisions.

## How this register works

Every question here is the Owner's to decide; S2-1 enacts none (CHANGE_CONTROL Levels 2 and 3; [AK-2](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup); SP-21). Each entry states the problem, why it matters, its canonical sources, the options, the recommendation, the impact on scope, time and risk, when the answer is needed — before S2-2, at the Owner's preview, before Stage 3, or at P11 —, whether it is prototype-only or would change P0–P6, with its gap number then, and its status. A question whose answer would change P0–P6 semantics is also a gap in [GAP_REGISTER](../../00-governance/GAP_REGISTER.md), OWNER_DECISION_REQUIRED; a prototype-only question stays here and in [the gate record](../evidence/P7_REBASELINE_GATE.md#p7-ux-re-baseline--evidence-and-gates). OQ-01 is reserved for the runtime question (SP-15); the others are numbered from OQ-02 as they arise.

| OQ | Subject | Needed | Kind | Gap | Status |
| --- | --- | --- | --- | --- | --- |
| [OQ-01](#oq-01--the-runtime-for-the-vendored-scripts) | The runtime for the vendored scripts | before S2-2 | prototype-only | — | DECIDED (b) — DIR-065 |
| [OQ-02](#oq-02--the-project-lists-journey-summary-per-row) | The project list's journey summary per row | before Stage 3 | would change P6 ([QB-01](../../06-api-performance/PERFORMANCE.md#6-query-budgets)) | GAP-054 | OPEN — deferred (DIR-065) |
| [OQ-03](#oq-03--dispatch-ready-rows-in-pekerjaan) | Dispatch-ready rows in *Pekerjaan* | before Stage 3 | would change P3 and P6 (a new signal) | GAP-055 | OPEN — deferred (DIR-065) |
| [OQ-04](#oq-04--value-formats-in-the-english-interface) | Value formats in the English interface | at CP-A | prototype and Stage 3 | — | OPEN — deferred (DIR-065), at CP-A (DIR-075); (a) shown until then |
| [OQ-05](#oq-05--where-the-accounts-language-preference-is-kept) | Where the account's language preference is kept | before Stage 3 | would change P4 ([DATABASE](../../04-architecture/DATABASE/README.md#database-architecture)) | GAP-056 | OPEN — deferred (DIR-065) |
| [OQ-06](#oq-06--the-simulated-google-button-of-s2-j01) | The simulated Google button of S2-J01 | before S2-2 | prototype-only | — | DECIDED (c) — DIR-065 |
| [OQ-07](#oq-07--the-phone-tests-arrangement) | The phone test's arrangement | before S2-2 | prototype-only | — | DECIDED (a) — DIR-065 |
| [OQ-08](#oq-08--the-purchases-own-identifier) | The purchase's own identifier | before Stage 3 | would change P4 and P6, or P5's wording | GAP-057 | OPEN — deferred (DIR-065) |
| [OQ-09](#oq-09--the-screen-list-for-s2-2) | The screen list for S2-2 | before S2-2 | prototype-only | — | DECIDED (a) — DIR-065 |
| [OQ-10](#oq-10--the-navigation-shells-anatomy) | The navigation shell's anatomy | at CP-0 | prototype and Stage 3 | — | DECIDED — DIR-069 |
| [OQ-11](#oq-11--the-prototype-marker) | The prototype marker | at CP-0 | prototype-only | — | DECIDED — DIR-069 |
| [OQ-12](#oq-12--where-48-px-targets-apply) | Where 48 px targets apply | at CP-0 | prototype and Stage 3 | — | DECIDED — DIR-069; CQ-5 (DIR-071) |
| [OQ-13](#oq-13--goldens-hero-logo-and-visual-richness-and-the-sign-in-illustration) | Golden's hero, logo and visual richness, and the sign-in illustration | at CP-0 | prototype-only | — | DECIDED — DIR-069; concept A (DIR-071) |
| [OQ-14](#oq-14--kembali-on-the-code-step) | "Kembali" on the code step | at CP-0 | prototype and Stage 3 | — | DECIDED — DIR-069; the server side open for P11 |
| [OQ-15](#oq-15--gr-16s-variant) | GR-16's variant | at CP-A | prototype and Stage 3 | — | OPEN — deferred to CP-1 (DIR-069), CP-1R (DIR-071) and CP-A (DIR-075) |
| [OQ-16](#oq-16--gf-2s-execution-configuration) | GF-2's execution configuration | at CP-0 | process | — | DECIDED — DIR-069 |
| [OQ-17](#oq-17--a-sparkline-on-the-kpi-cards) | A sparkline on the KPI cards | at CP-A | prototype-only; would change P2 and P6 as a metric | — | OPEN — a prototype-only proposal, off by default (DIR-071 CQ-2) |
| [OQ-18](#oq-18--a-delta-on-the-kpi-cards) | A delta on the KPI cards | at CP-A | prototype-only; would change P2 and P6 as a metric | — | OPEN — a prototype-only proposal, off by default (DIR-071 CQ-2) |

## OQ-01 — The runtime for the vendored scripts

- **Problem.** The reference's interactions.js and patterns.js — its shell, menus, dialogs, sheets, select, tabs and lists — call a runtime API, `MCProject`, `MC_PROJECT`, `MCRoutes`, `MCTime` and `MCBrand`, that its `project-config.js` provides with its demonstration configuration; that file is not vendored (SP-1), and [SD-04](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) covers byte-identical copies only. Without a runtime, interactions.js's navigation, phone-navigation and account sections return at once, and SP-14 lets the two scripts load only if the answer provides their API. composer.js cannot load whatever the answer, since it calls guide.js, which does not load ([PROTOTYPE_PLAN §2.1](PROTOTYPE_PLAN.md#21-what-the-vendored-scripts-need)).
- **Why it matters.** It decides whether the prototype's interactions run on the reference's own code — GD-02 makes the reference primary for how an interaction is presented — and how close the reference's demonstration material comes to the prototype (AK-5).
- **Canonical sources.** DIR-061 SD-03, SD-04 and AK-5; DIR-062 SP-1 and SP-2; DIR-064 SP-13–SP-15; DIR-059 GD-02, GD-04 and GD-08; the prototype's `VENDOR.md`; [PROTOTYPE_PLAN §2.2](PROTOTYPE_PLAN.md#22-the-runtime--the-analysis-for-oq-01-sp-15).
- **Options.**
  - **(a) A modified copy of `project-config.js`** — with the Owner's explicit permission beyond SD-04, every route, module, fixture, persona, navigation tree and storage hook of its demonstration configuration removed.
  - **(b) A runtime provider written new** — one prototype script with only the 31 members the vendored scripts call, returning MultipleCorp's prototype configuration: R1's navigation per persona, the account menu, the brand, the fixed business day, the language of the address; the reference's behaviours that differ from MultipleCorp's switched off by configuration — the logout question, for one, asked only when input is unsent ([MSG-35](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); [GR-09](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)).
  - **(c) No runtime** — interactions.js and patterns.js do not load; the prototype's own script provides the dialogs, menus, disclosures, tabs, sheets and drawer its screens use, on the reference's classes and motion tokens.
- **Recommendation.** (b): the reference's interaction code runs byte-identical, no line of its demonstration configuration is copied, and every difference from MultipleCorp's behaviour is a configuration that S2-2's checks can read. (c) is the fallback if the Owner prefers the smallest import surface; (a) is not recommended.
- **Impact.** (a): scope — the fastest wiring, then a full AK-5 audit of a modified reference file; risk — demonstration material left behind; a new permission. (b): scope — one script written new, with its checks; time — moderate in S2-2; risk — a member's expected shape misread, which S2-2's checks find before its commit. (c): scope — the prototype's own interaction script; time — moderate; risk — keyboard and motion details that differ from the reference's, measured by SV-01, SV-04 and SV-05.
- **Needed.** Before S2-2. **Kind.** Prototype-only; no gap. **Status.** DECIDED — option (b), the Owner's decision under [DIR-065](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes) (2026-10-14): the runtime provider `site/assets/mc/runtime.js` written new, no line of `project-config.js` copied. **On the pilot pages** of run GF-1 the Owner's K3 applies instead ([DIR-067](../../00-governance/DECISION_LOG.md#dir-067-dir-068-obs-026-and-tech-034--p7-ux-re-baseline-golden-first-decisions-pilot-authorization-baseline-and-pilot); SP-30): the reference's own `project-config.js` runs byte-identical with MultipleCorp's configuration patch, `window.MC_PROJECT_PATCH`, and `runtime.js` does not load there; S2-2's pages keep option (b) ([GOLDEN_FIRST_PILOT](GOLDEN_FIRST_PILOT.md) §3).

## OQ-02 — The project list's journey summary per row

- **Problem.** R1 draws the project list as a list of journeys: each row with the current stage, a blocker marker and the next action, and a filter by stage ([R1 d)](../evidence/P7_REBASELINE_GATE.md#d-project-list-as-a-list-of-journeys)). [QUERY_BUDGET_CHECKS §2](QUERY_BUDGET_CHECKS.md#2-qb-01--stage-blocker-marker-and-next-action-on-the-project-list) finds that they do not fit [QB-01](../../06-api-performance/PERFORMANCE.md#6-query-budgets): from *Pengiriman* on, the stage needs the completion predicates and the documents group, which a list row cannot read within four queries and one filter-lookup group; the stage is stored nowhere, so no index serves a stage filter.
- **Why it matters.** The project list is a primary entry for the Owner and the Admin (R1 a)). A row's stage and next action let them scan many projects without opening each ([OC-01](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework); OC-03). Without them a row shows the project's state and deadline, and the journey's stage, blockers and next action are one step away, on the project.
- **Canonical sources.** PERFORMANCE QB-01, PF-05, PF-09, PF-34, PF-41, PF-42 and the set-query form of [QS-15](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue); WF-PRJ-03; the R1 [project journey model](../evidence/P7_REBASELINE_GATE.md#c1-rework--project-journey-model); [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md#information-architecture) SCR-09.
- **Options.**
  - **(a) Keep SCR-09's list within QB-01** — the design S2-1 uses: state and deadline per row; the stage, blockers and next action on the journey workspace (QB-02), the waiting work in *Pekerjaan* and *Beranda* (QB-10). No change.
  - **(b) Extend QB-01 through P6 change control** — a second deferred group on the list running the ten predicate set queries and the documents readiness over the page's project ids, at most twelve statements, the form QS-15 registers for a page of projects; each row derives its stage, blocker marker and next action when the group arrives. The stage filter stays out unless P8 shows a bounded filter that meets PF-42.
  - **(c) Show only what guards give** — the fulfilment stages and the shortage blocker — and nothing beyond them: a stage present on some rows and absent on others, which would mislead.
- **Recommendation.** (b) for the application, decided before Stage 3: it realizes the list of journeys of the R1 structure the Owner approved for Stage 2 (GD-01), at a bounded cost — page size times twelve set queries, deferred so the rows render first. S2-2 prototypes (a), as DIR-064 §12.8 requires, so the Owner sees the fitting design at the preview.
- **Impact.** (a): none. (b): scope — one P6 change to QB-01 and the list's query class, and P8's fixtures and plans at 10x and 100x (HO-16); time — small in Stage 3, with one more registered query class in P8; risk — list latency at 100 rows per page (PF-17), bounded by the deferral and PF-41's branches. (c): misleading rows, not recommended.
- **Needed.** Before Stage 3. **Kind.** Would change P6 — [GAP-054](../../00-governance/GAP_REGISTER.md#gap-054--the-project-list-cannot-show-each-projects-journey-stage-blocker-and-next-action-within-its-query-budget). **Status.** OPEN — deferred by DIR-065 (2026-10-14).

## OQ-03 — Dispatch-ready rows in *Pekerjaan*

- **Problem.** R1's *Pekerjaan* lists the dispatch-ready work — the remaining reservations that wait to leave the warehouse — among the goods work ([R1 b)](../evidence/P7_REBASELINE_GATE.md#b-admin-work-home-oc-01-oc-05-pl-4)). [PF-24](../../06-api-performance/PERFORMANCE.md#7-dashboards-reports-and-exports) admits to the action queue only the register's statements, and none of [QS-01](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)–QS-22 returns a line that is reserved and not yet dispatched ([QUERY_BUDGET_CHECKS §3](QUERY_BUDGET_CHECKS.md#3-pf-24--dispatch-ready-rows-in-pekerjaan)).
- **Why it matters.** Dispatch is daily warehouse work for the warehouse/field Admin on the phone (the [persona task analysis](../evidence/P7_REBASELINE_GATE.md#persona-task-analysis); R1 f)). With the fitting design that Admin opens *Barang Keluar*'s worklist from *Pekerjaan* — one more step — instead of seeing the rows there.
- **Canonical sources.** PERFORMANCE PF-24, QB-10 and the signal register; WORKFLOWS §10; [BUSINESS_RULES](../../02-domain/BUSINESS_RULES.md#business-rules) CALC-02; L-04; [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md#information-architecture) §9 (the Admin's G1, eleven statements) and SCR-22.
- **Options.**
  - **(a) Keep the fitting design** — *Keluarkan dan kirim* shows QS-06's rows and one link, with no count, to the dispatch worklist of *Barang Keluar* (SCR-22). No change.
  - **(b) A new signal for dispatch-ready lines** — the remaining reservations of ACTIVE projects' WAREHOUSE lines (CALC-02), one bounded statement registered in WORKFLOWS §10 and PERFORMANCE's register, placed in the Admin's G1, which then holds twelve statements: inside QB-10, no budget change; P3 and P6 change control.
- **Recommendation.** (b), decided before Stage 3: the rows sit with the other goods work where the warehouse/field Admin starts, at the cost of one statement within an existing group's bound. S2-2 prototypes (a).
- **Impact.** (a): none. (b): scope — one signal in WORKFLOWS §10, one register row in PERFORMANCE, the Admin's G1 at twelve; time — small in Stage 3, one more signal for P8 to verify; risk — G1 then at its bound, so a later signal there needs a budget change.
- **Needed.** Before Stage 3. **Kind.** Would change P3 and P6 — [GAP-055](../../00-governance/GAP_REGISTER.md#gap-055--dispatch-ready-work-has-no-registered-signal-for-the-admins-work-home). **Status.** OPEN — deferred by DIR-065 (2026-10-14).

## OQ-04 — Value formats in the English interface

- **Problem.** [DESIGN_SYSTEM §16](../DESIGN_SYSTEM.md#16-copy-and-formatting) formats every value one way — Rupiah, quantities with their units, percentages, dates, times in WIB — for an interface it assumed Indonesian, and [IP-13](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry) accepts entry with the Indonesian separators only. For the English interface the sources are silent ([LOCALIZATION_ARCHITECTURE §2.4](LOCALIZATION_ARCHITECTURE.md#24-formats)).
- **Why it matters.** A value is the same figure on the screen, in an export and on an issued document. In English usage "1.250" reads as one and a quarter; separators that changed with the language could make a user misread an amount or a quantity, or type one that IP-13 rejects.
- **Canonical sources.** DESIGN_SYSTEM §16 and UXV-12; ADMIN_FLOW IP-13 and IP-14; [BUSINESS_RULES](../../02-domain/BUSINESS_RULES.md#business-rules) BR-DT-06; [V1_SCOPE CAP-14](../../01-product/V1_SCOPE.md#must-ship); C-1.
- **Options.**
  - **(a) §16's formats unchanged in both languages** — the English interface translates labels, never values: "Rp1.250.000,50", "3,5 kg", "02 Okt 2026", "14.05 WIB". IP-13 unchanged.
  - **(b) §16's numbers and money in both languages, with month names in the interface's language** — "02 Oct 2026" in English, the separators unchanged. IP-13 unchanged; an English date reads differently from the issued document's.
  - **(c) English conventions in English** — "Rp1,250,000.50", "3.5 kg", "Oct 2, 2026": entry would need a second convention — a change to ADMIN_FLOW's IP-13 and IP-14 —, and the same figure would read differently on the screen and on paper.
- **Recommendation.** (a): every figure reads one way whatever the language, entry stays as it is, and the screen matches the paper, with English the secondary language (C-1). (b) is acceptable if the Owner prefers English month names.
- **Impact.** (a): none beyond Stage 3's localization contract stating it. (b): the date format takes the language for month names only, and P8 checks each language. (c): scope — IP-13 and IP-14 changed, with a second parser; risk — misread or mistyped amounts; time — more P8 tests.
- **Needed.** At the Owner's preview, before Stage 3; the prototype shows (a) until then. **Kind.** Prototype and Stage 3; no P0–P6 change, so no gap. **Status.** OPEN — deferred by DIR-065 (2026-10-14). *Note of 2026-10-17:* the preview is the Owner's checkpoint CP-A (DIR-075), where this question is asked.

## OQ-05 — Where the account's language preference is kept

- **Problem.** C-1 asks for an authenticated preference "per account". The approved `users` table has no locale or preference column and no table holds a per-account preference; a session ends when the browser closes, after 60 idle minutes or 12 hours, and V1 has no "remember me"; a device cookie follows the device, not the account ([LOCALIZATION_ARCHITECTURE §3.3](LOCALIZATION_ARCHITECTURE.md#33-after-sign-in)).
- **Why it matters.** Without a place, the preference lasts one session: an account that works in English chooses again at every sign-in, which the 12-hour limit and the idle expiry make several times a day. On a shared office PC or floor device, a choice kept on the device would apply to the next person's account ([GR-14](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)).
- **Canonical sources.** C-1 ([source record 50](../../00-governance/sources/P7_REBASELINE_OWNER_DECISIONS_2026-10-04.txt); [DIR-053](../../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction)); [V1_SCOPE](../../01-product/V1_SCOPE.md#v1-scope-and-product-constraints) CAP-14; [SECURITY](../../05-security/SECURITY.md#security-architecture) AU-06–AU-08 and WS-07; [DATABASE §4.1](../../04-architecture/DATABASE/s04-01-iam.md#41-iam--identity--access); [GAP-037](../../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested); GR-14.
- **Options.**
  - **(a) A nullable column `users.locale`** — limited by a CHECK to `id` and `en`, NULL meaning the default, Indonesian; read into the session at sign-in. Through database change control.
  - **(b) The session only** — no schema change; the choice lasts one session, so the preference is per session, not per account.
  - **(c) A device cookie** — no schema change; it follows the device, and on a shared device carries one person's choice into another's account.
- **Recommendation.** (a): the only option that gives the per-account preference C-1 asks for while keeping the approved security and shared-device rules, with one column and no generic store. As C-1 requires, it goes through the database change-control process with its exact change, reason and impact before it becomes normative.
- **Impact.** (a): scope — one column in DATABASE §4.1, the 125 logical tables unchanged; the account's own change from the account menu and *Akun Saya* (PG-065), its route defined in P6 under SECURITY; P8's tests. Time — small, with Stage 3's localization contract. Risk — low: the value is not sensitive, and WS-07 already shares the locale. (b): no change, against C-1's per-account preference. (c): no schema change, against GR-14 on shared devices.
- **Needed.** Before Stage 3. **Kind.** Would change P4 — DATABASE — under (a): [GAP-056](../../00-governance/GAP_REGISTER.md#gap-056--the-accounts-language-preference-has-no-approved-storage-place). **Status.** OPEN — deferred by DIR-065 (2026-10-14).

## OQ-06 — The simulated Google button of S2-J01

- **Problem.** S2-J01's sign-in shows "Lanjutkan dengan Google" (R1 k); [AU-22](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials)). Google's [sign-in branding guidelines](https://developers.google.com/identity/branding-guidelines), last updated 2026-07-07, say the button "must always include" the standard-colour "G" and set its theme, font and padding; the prototype may not use the reference's drawn "G" ([GD-08](../../00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation); SD-04), loads nothing from Google ([SS-03](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061); SS-05) and has no Google Sans file ([PROTOTYPE_PLAN §6](PROTOTYPE_PLAN.md#6-the-simulated-google-button-of-s2-j01)).
- **Why it matters.** Participants recognize the button by its look; how close the prototype comes to Google's artwork is a choice about brand use, not about design.
- **Canonical sources.** SECURITY AU-22; SS-03; SS-05; DIR-059 GD-08; DIR-061 SD-04; GR-13.
- **Options.**
  - **(a) A neutral placeholder** — the light theme's fill, stroke, padding and label, a plain grey disc in the logo's place, the text in Inter.
  - **(b) A text-only button** — the label in an outline button, without the "G".
  - **(c) Google's own "G"** — the official logo file, obtained under Google's terms and kept in the prototype only.
- **Recommendation.** (a): recognizable by its label and place, with no Google artwork and no download; the marker and the panel already say that Google sign-in is simulated. The application's button follows the guidelines in full, in P11 with [GAP-042](../../00-governance/GAP_REGISTER.md#triage) (GR-13).
- **Impact.** (a): none beyond S2-2's drawing. (b): none; the furthest from the guidelines and the least recognizable. (c): a download of Google's asset, which needs the Owner's permission; still no Google Sans.
- **Needed.** Before S2-2. **Kind.** Prototype-only; no gap. **Status.** DECIDED — option (c), the Owner's explicit decision under [DIR-065](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes) (2026-10-14), replacing the recommendation (a) and the Owner's own earlier answer "a": Google's authentic full-colour "G", unaltered, in a button keeping the reference's style, on S2-J01's screens only; Google sign-in stays simulated. The "G" came from Google's official asset download under SP-24 as the Owner clarified it — made by script from the light, icon-only button file, only the button's background and stroke removed —, its provenance in the prototype's `google-g.SOURCE.txt` and in TECH-033; the differences from Google's button rules, the "G"'s displayed size included, are [GR-32](DEVIATION_REGISTER.md).

## OQ-07 — The phone test's arrangement

- **Problem.** S2-0 did not run the phone access test: the network's profile was Public, by the Owner's choice; S2-2 or the Owner's preview carries it (SP-6), under [SS-06](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061) — the Owner present, a network with the Private profile, a firewall allowance for that profile only, no forwarding, tunnel or hosting, the server stopped after use ([PROTOTYPE_PLAN §5](PROTOTYPE_PLAN.md#5-the-phone-test-sp-6-ss-06)).
- **Why it matters.** The phone journeys — S2-J01 and S2-J03 at 390 px, S2-J05 at 390 and 320 px — are judged on a real phone's touch, keyboard and browser only if the test runs; emulation gives the layout, not the device.
- **Canonical sources.** [DIR-062](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SP-6; SS-06; SV-02; SD-08.
- **Options.**
  - **(a) At the Owner's preview** — the Owner present, a trusted network set to Private for the session.
  - **(b) In S2-2** — the same conditions during the build.
  - **(c) Emulation only** — SV-02's renders by emulation, the real phone first in C2's sessions.
- **Recommendation.** (a): S2-2 relies on emulation, and the real phone joins the preview the Owner attends anyway.
- **Impact.** (a): the Owner changes the network profile for the preview and the server runs with `--host` for its length; (b): the same, during S2-2; (c): no setting changes, and no real-phone evidence before C2.
- **Needed.** Before S2-2, to plan it. **Kind.** Prototype-only; no gap. **Status.** DECIDED — option (a), the Owner's decision under [DIR-065](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes) (2026-10-14): S2-2 used emulation only; the real phone joins the Owner's preview (SS-06). *Note of 2026-10-17:* the preview is the Owner's checkpoint CP-A (DIR-075), where the phone journeys S2-J01, S2-J03 and S2-J05 are tested on a real phone.

## OQ-08 — The purchase's own identifier

- **Problem.** A purchase record has no number of its own — DATABASE §4.6's `purchases` holds its company, project, supplier, business date, the supplier's reference and its state, the PO being a document of the record ([BR-DOC-04](../../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization)) —, while PERMISSIONS_MATRIX PJ-18's receiving projection lists a "purchase number", and R1 and VC1 named purchases ARJ/PB/2026/0216 ([SP-20](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents)). The PO is optional and never a precondition (BR-PUR-02), so many purchases will have no order number to be known by ([S2-J04](journeys/S2-J04.md#3-synthetic-data); [S2-J05](journeys/S2-J05.md#3-synthetic-data)).
- **Why it matters.** The warehouse/field Admin finds the purchase to receive by its name on *Pekerjaan* and in *Barang Masuk*; the purchase list, the journey's *Pengadaan* and every conversation about a purchase need one stable name. Supplier and date alone repeat when a supplier delivers twice in a day.
- **Canonical sources.** [DATABASE §4.6](../../04-architecture/DATABASE/s04-06-pur.md#46-pur--procurement); [PERMISSIONS_MATRIX PJ-18](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections); BR-PUR-02; BR-DOC-04; [WF-PUR-01](../../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-pur-01--project-purchase-desk); SP-20.
- **Options.**
  - **(a) A business number for every purchase**, drawn at its save from a company's numbering sequence under the numbering rules ([NM-01](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency)–NM-10), through DATABASE and numbering change control.
  - **(b) The order's number where an order is issued**, otherwise the supplier, the business date and the supplier's reference — no change; PJ-18's "purchase number" read as the order's number.
  - **(c) The supplier's reference alone** — no change; it is the supplier's, may be empty and is not unique.
- **Recommendation.** (a): every purchase gets one stable name from the moment it exists, as PJ-18 already assumes and the Owner's approved data set uses, at the cost of one more numbered sequence per company. S2-1's journeys use (b), the design that fits the current rules (DIR-064 §14.5), so S2-J04 issues the order and S2-J05 receives "ARJ/PB/2026/0216" as the order's number.
- **Impact.** (a): scope — a number column and its sequence in DATABASE and CONCURRENCY_IDEMPOTENCY's numbering, the numbering start for the type (NX-01), P8's tests; time — small in P4 and P6 change control; risk — low. (b): none in P0–P6; the screens name a purchase without an order by supplier and date, and PJ-18's wording is aligned. (c): ambiguity.
- **Needed.** Before Stage 3. **Kind.** Would change P4 and P6 under (a), and P5's wording under (b): [GAP-057](../../00-governance/GAP_REGISTER.md#gap-057--a-purchase-has-no-identifier-of-its-own-while-the-receiving-projection-lists-one). **Status.** OPEN — deferred by DIR-065 (2026-10-14).

## OQ-09 — The screen list for S2-2

- **Problem.** S2-2 builds exactly the screens of the six journeys, and every addition needs the Owner's approval ([SD-01](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup); SD-05). [PROTOTYPE_PLAN §8](PROTOTYPE_PLAN.md#8-the-screen-list-for-the-owners-approval) lists them: 53 PS screens of S2-J01–S2-J06 and the out-of-scope page, with each one's page, journey, persona, viewports and languages.
- **Why it matters.** The list bounds S2-2's work and the Owner's preview; a screen missing from it cannot be built, and one added later waits for another approval.
- **Canonical sources.** DIR-061 SD-01, SD-05 and SD-06; DIR-064 §12.12 item 8; the six journey specifications.
- **Options.** (a) approve the list as it stands; (b) approve it with named changes; (c) reject it, with the reason, for S2-1's list to be revised.
- **Recommendation.** (a): the list is exactly SD-05's six journeys, each screen traced to its canonical source in its specification, with the out-of-scope page for everything else.
- **Impact.** (a): S2-2 can be authorized on it. (b): the named changes before S2-2. (c): a revision of the journeys' screen lists before S2-2.
- **Needed.** Before S2-2. **Kind.** Prototype-only; no gap. **Status.** DECIDED — option (a), the Owner's decision under [DIR-065](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes) (2026-10-14): S2-2 built exactly PS-01–PS-53 and the out-of-scope page.

## OQ-10 — The navigation shell's anatomy

- **Problem.** GF-1 kept R1's placements in the shell — the account menu in the top bar (GR-26) among them — where Golden puts its account at the sidebar's foot and its search and primary action in its own slots ([GOLDEN_FIRST_PILOT §10](GOLDEN_FIRST_PILOT.md#10-open-items-and-questions-for-the-owner) item 3); GD-03 had made R1 authoritative for the shell.
- **Decision.** "APPROVED as recommended": Golden governs the navigation shell's visual anatomy, placement and interaction quality; MULTIPLECORP's approved role-based information architecture, labels, actions and security boundaries remain authoritative — GD-03 narrowed for presentation ([DIR-069](../../00-governance/DECISION_LOG.md#dir-069-dir-070-obs-027-and-tech-035--p7-ux-re-baseline-gf-2-decisions-authorization-baseline-and-corrected-pilot)). GF-2 applied it to the shell (SP-38; GOLDEN_FIRST_PILOT §13).
- **Kind.** Prototype and Stage 3's DESIGN_SYSTEM; no P0–P6 change, so no gap. **Status.** DECIDED — DIR-069 (2026-10-15).

## OQ-11 — The prototype marker

- **Problem.** GF-1's marker (GR-33) took room in the phone's app bar and pushed the two longest titles onto two lines at 320 px (GOLDEN_FIRST_PILOT §10 item 8).
- **Decision.** "APPROVED as recommended": the marker stays clearly identifiable without disrupting headings, proportions or visual balance, SS-08's meaning unchanged (DIR-069). GF-2 made it one line in the phone app bar's trailing slot and the desktop top bar's actions slot (SP-38).
- **Kind.** Prototype-only; no gap. **Status.** DECIDED — DIR-069 (2026-10-15).

## OQ-12 — Where 48 px targets apply

- **Problem.** GR-07's 48 px floor controls made GF-1's scan bar 91 px where Golden's is 69 px (GOLDEN_FIRST_PILOT §10 item 4).
- **Decision.** "APPROVED in principle": 48 px only where canonical field-operation and accessibility requirements actually demand it, accessible touch targets throughout (DIR-069); CQ-5 then set 48 px for the warehouse operational controls where required and the applicable canonical rules (UXV-10) for the other controls ([DIR-071](../../00-governance/DECISION_LOG.md#dir-071-dir-072-and-obs-028--p7-ux-re-baseline-gf-2-cp-1-decisions-amendment-gf-2a-and-its-baseline)), applied by GF-2A's SP-47 and PC-1's SP-53 (b).
- **Kind.** Prototype and Stage 3; no gap. **Status.** DECIDED — DIR-069 (2026-10-15); CQ-5, DIR-071.

## OQ-13 — Golden's hero, logo and visual richness, and the sign-in illustration

- **Problem.** GF-1 left out Golden's hero and greeting, its logo and the visual elements whose data MULTIPLECORP does not define, and drew no sign-in imagery (GD-08; VP-3; VP-5).
- **Decision.** "PARTIALLY APPROVED, WITH REVISION REQUIRED": Golden's hero and greeting restored and its logo `i-logo-mark` used as a clearly identified prototype placeholder, in the private prototype only; Golden's visual richness kept without invented data — data-backed visuals and plainly marked proposals (SP-39); the sign-in illustration original and drawn in code, never unlicensed photography, two concepts at CP-1 (SP-40) (DIR-069). At CP-1 the Owner chose concept A "Meja Kerja" (DIR-071 CQ-3), built in GF-2A (SP-48).
- **Kind.** Prototype-only — the final logo stays open (VP-5); no gap. **Status.** DECIDED — DIR-069 (2026-10-15); concept A, DIR-071.

## OQ-14 — "Kembali" on the code step

- **Problem.** Golden's code step offers "Kembali" to the credentials; S2-J01 gave none (GOLDEN_FIRST_PILOT §10 item 7).
- **Decision.** "APPROVED as recommended, provided the back action safely cancels the pending authentication attempt and preserves the canonical security rules" (DIR-069): back to PS-01, the pending attempt discarded and nothing kept but the e-mail (SP-38), as [S2-J01](journeys/S2-J01.md) and [AUTH_JOURNEYS](AUTH_JOURNEYS.md) §2.1 now state; how the server voids a challenge the person cancels — AU-16 names no such case — is open for P11.
- **Kind.** Prototype and Stage 3, the server side for P11; no gap now. **Status.** DECIDED — DIR-069 (2026-10-15).

## OQ-15 — GR-16's variant

- **Problem.** GR-16 offers two desktop text scales: variant A, Golden's sizes, and variant B (`?varian=b`), with the smallest desktop text at 14 px.
- **Options.** (a) variant A; (b) variant B.
- **History.** Deferred to CP-1 (DIR-069); at CP-1 the Owner found variant B promising but did not approve it (the seventieth source record) and deferred the choice to CP-1R (DIR-071 CQ-4); after CP-1R, DIR-075 puts it on CP-A's decision sheet. Both variants stay switchable on the desktop pages.
- **Needed.** At CP-A. **Kind.** Prototype and Stage 3's DESIGN_SYSTEM; no gap. **Status.** OPEN.

## OQ-16 — GF-2's execution configuration

- **Problem.** The Owner preferred Claude Opus 5.5 with Ultracode for the corrective pilot; unbounded multi-agent workflows could cross the Owner's approval boundaries.
- **Decision.** "REVISE THE EXECUTION STRATEGY": a staged Ultracode approach keeping the Owner's approval at CP-1 and CP-2, no workflow crossing an approval boundary, narrow agent ownership, commits and pushes under the main executor and the lab and baselines protected (DIR-069), enacted as DIR-070 §3 — Claude Opus 5.5 at Max effort with Ultracode on, WF-1–WF-4 bounded — and continued by GF-2A's WF-2R and PC-1's WF-PC1.
- **Kind.** Process; no gap. **Status.** DECIDED — DIR-069 (2026-10-15).

## OQ-17 — A sparkline on the KPI cards

- **Problem.** Golden's KPI tiles carry a sparkline; MULTIPLECORP defines no series for it (VP-3: needs definition, not proposed). GF-2 drew one from a plainly illustrative series, marked "Usulan" and listed in the marker's panel, behind a switch (SP-39 (b)).
- **Options.** (a) keep it as a prototype-only proposal, off by default; (b) drop it; (c) define it through change control — the metric's definition (P2) and its query budget (P6 QB-10, TX-10) —, which the Owner has not authorized (DIR-071 CQ-2).
- **Recommendation.** Planning's answer at OG-0 keeps OQ-17 and OQ-18 in the prototype only, not part of V1 (the seventy-third source record).
- **Impact.** (a) and (b): none beyond the prototype; (c): a new metric and query in P2 and P6, then a gap.
- **Needed.** At CP-A. **Kind.** Prototype-only now; would change P2 and P6 as a production metric. **Status.** OPEN — behind `?usulan=1`, off by default (DIR-071 CQ-2).

## OQ-18 — A delta on the KPI cards

- **Problem.** Golden's KPI tiles carry a delta against an earlier period; MULTIPLECORP defines no comparison for them (VP-3). GF-2 drew one from the same illustrative series, marked "Usulan" (SP-39 (b)); WF-2R found that its wording "dari Sep 2026" reads as a real comparison and lacks Golden's screen-reader direction word (BR2R-14, a D, not implemented).
- **Options, recommendation and impact.** As OQ-17's.
- **Needed.** At CP-A. **Kind.** Prototype-only now; would change P2 and P6 as a production metric. **Status.** OPEN — behind `?usulan=1`, off by default (DIR-071 CQ-2).
