# Localization architecture

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.6 (BP-3). Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft — the normative localization contract GAP-037 plans is Stage 3's —, and nothing in it is normative.

**Sources.** [V1_SCOPE CAP-14](../../01-product/V1_SCOPE.md#must-ship) and AC-14; the Owner's clarification C-1 in its own words ([source record 50](../../00-governance/sources/P7_REBASELINE_OWNER_DECISIONS_2026-10-04.txt), "LANGUAGE-PREFERENCE STORAGE"; DIR-053); R1 [k)](../evidence/P7_REBASELINE_GATE.md#k-language-and-authentication); [GAP-037](../../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested); the gate record's GR-14, GR-17 and GP-5; DESIGN_SYSTEM [§16](../DESIGN_SYSTEM.md#16-copy-and-formatting) and [§12.1](../DESIGN_SYSTEM.md#121-status-system); INFORMATION_ARCHITECTURE [§3](../INFORMATION_ARCHITECTURE.md#3-glossary); ADMIN_FLOW [§14](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) and [IP-13](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry); SECURITY [§2](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-06–AU-08 and [§4](../../05-security/SECURITY.md#4-web-and-input-security) WS-07; [DATABASE §4.1](../../04-architecture/DATABASE/s04-01-iam.md#41-iam--identity--access); BUSINESS_RULES [BR-DT-06](../../02-domain/BUSINESS_RULES.md#dates-and-backdating--dir-018); DIR-061 SS-07; DIR-064 SP-13 and SP-18. **Official documentation, accessed 2026-10-13 (SP-18):** Laravel 13.x, *Localization*, `https://laravel.com/docs/localization` — the `lang` directory, which `lang:publish` creates; strings as PHP arrays or as JSON, one file set per language; the `locale` and fallback-locale options of `config/app.php`, and `App::setLocale`, `App::currentLocale` and `App::isLocale`; the `__` helper returning the key itself when a string is missing; `:name` placeholders; pluralization with `|`, ranges and `trans_choice`. Inertia.js, *Shared data*, `https://inertiajs.com/shared-data` — the page states no version and links the v3 documentation —: the `share` method of the `HandleInertiaRequests` middleware, shared data "included with every response", `usePage().props` in React. What these pages do not establish is marked "needs verification at implementation", as GAP-043 does for framework behaviour.

## 1. The requirement

- **Indonesian by default, English secondary.** The interface is in natural Bahasa Indonesia by default, with English as the secondary language through a language switch and "no uncontrolled hardcoded user-facing copy" ([V1_SCOPE CAP-14](../../01-product/V1_SCOPE.md#must-ship)); C-1 restates it: "id-ID is the default" and "English is secondary" ([source record 50](../../00-governance/sources/P7_REBASELINE_OWNER_DECISIONS_2026-10-04.txt)).
- **The switch.** "ID | EN" at the top right of sign-in, and "Bahasa tampilan: Indonesia | English" in the account menu and in the phone's *Lainnya* ([R1 k)](../evidence/P7_REBASELINE_GATE.md#k-language-and-authentication)); the flow is [WFL-04](WRITTEN_FLOWS.md#wfl-04--the-language-switch-both).
- **Before and after sign-in.** "pre-login preference may be device/browser local"; "authenticated preference is per account" (C-1).
- **Documents and data.** "generated or issued business documents and business data stay in Bahasa Indonesia for V1" (CAP-14); "issued documents and business data remain Indonesian as already decided" (C-1).
- **Storage.** C-1's rules: inspect the approved data model first; "prefer an existing suitable mechanism when it preserves the approved security and shared-device rules"; no "inappropriate generic JSON/preferences store"; a genuinely new field goes through the database change-control process "before it becomes normative" (§3).
- **Checks.** Missing-translation, language-switch and layout checks, which AICWDF §4B.5 and §27 call for ([GAP-037](../../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)) — §§2.7 and 2.8.

## 2. The design

### 2.1 Locales

Two locales: `id` (id-ID, Bahasa Indonesia), the default, and `en`, the secondary ([CAP-14](../../01-product/V1_SCOPE.md#must-ship); C-1). The fallback locale is `id`. Laravel sets the locale per request with `App::setLocale` and reads it with `App::currentLocale` (Laravel 13.x). A string missing in English would fall back to Indonesian, and `__` returns the key itself when a string is missing (Laravel 13.x); §2.7 treats both as defects.

### 2.2 Keys and their naming (proposed, Level 1)

One key per string, in dot syntax, grouped by area, with English identifiers that trace each string to its source:

| Group | Key, for example | Holds | Indonesian text from |
| --- | --- | --- | --- |
| `messages` | `messages.msg_19` | the message catalogue, one key per MSG | [ADMIN_FLOW §14](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) |
| `glossary` | `glossary.gl_041` | the glossary terms used as labels, one key per GL | [INFORMATION_ARCHITECTURE §3](../INFORMATION_ARCHITECTURE.md#3-glossary) |
| `status` | `status.project.draft` | the state labels, one key per family and state | [DESIGN_SYSTEM §12.1](../DESIGN_SYSTEM.md#121-status-system) |
| `auth` | `auth.code_mismatch` | AU-04's generic answers and H7-16's advice | [SECURITY §2](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials); the gate record |
| `documents` | `documents.doc_06` | the document type names | V1_SCOPE's [document catalog](../../01-product/V1_SCOPE.md#selectable-document-catalog) |
| `pages` | `pages.pg_001.heading` | each page's own copy, one group per page of [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) | the journey specifications' copy sections (§5) |

A string with values carries named placeholders — Laravel's `:name` form —, never concatenated pieces, so each language orders its own words. Laravel keeps strings as PHP arrays or as JSON, one file set per language (Laravel 13.x); which form, and how the strings reach React (§2.9), is P11's.

### 2.3 Who owns the copy

| Copy | Indonesian | English |
| --- | --- | --- |
| Defined by a source — MSG-01–MSG-41, the GL terms, AU-04's answers, H7-16's advice, the state labels of [DESIGN_SYSTEM §12.1](../DESIGN_SYSTEM.md#121-status-system), the document type names | canonical, quoted exactly | proposed by Planning, validated by the Owner ([GAP-037](../../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)) |
| Recorded in the gate record — R1's and VC1's labels ([R1 k)](../evidence/P7_REBASELINE_GATE.md#k-language-and-authentication) for sign-in) | quoted from there | proposed, validated by the Owner |
| Not defined by any source — a heading, a label, a helper line, the prototype's panel | proposed copy, drafted by Planning with the GL terms and within [OC-06](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework), citing the requirement it serves; validated by the Owner at the preview | proposed, validated by the Owner |

The Indonesian string is the source and the English one renders it: no English string exists without its Indonesian source, and no copy states a rule, figure or business meaning that its source does not give (DIR-064 §11.3).

### 2.4 Formats

[DESIGN_SYSTEM §16](../DESIGN_SYSTEM.md#16-copy-and-formatting) formats each kind of value one way (UXV-12): Rupiah with "Rp" and no space, the dot between thousands and the comma before sen ("Rp1.250.000", "Rp1.250.000,50"); negative money with the minus sign before "Rp" ("−Rp250.000"); quantities exact, never rounded, followed by their unit ("3,5 kg"; "120 rim (24 dus)"); percentages ("0,25%"); dates ("02 Okt 2026", and "2 Oktober 2026" in a sentence); times on the 24-hour clock with a dot and "WIB" ("14.05 WIB"); periods; business numbers exactly as issued; and counts bounded where [PF-15](../../06-api-performance/PERFORMANCE.md#4-pagination) bounds them. All dates and times are WIB: business dates, numbering periods and aging use Asia/Jakarta day boundaries ([BR-DT-06](../../02-domain/BUSINESS_RULES.md#dates-and-backdating--dir-018)), and the application stores timestamps in UTC ([ARCHITECTURE §16](../../04-architecture/ARCHITECTURE.md#16-configuration-environments-and-database-roles)). Entry follows the same convention: [IP-13](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry) normalizes the Indonesian separators only, and any other dot or character is a validation error (IP-14), never a silent reinterpretation.

These sources fix the formats for an interface they assumed Indonesian; for the English interface they are silent. A value is the same figure on the screen, in an export and on an issued document, and entry accepts one convention, so a figure must not change meaning with the language: "1.250" is one thousand two hundred and fifty under §16 and one and a quarter in English usage. [OQ-04](OWNER_QUESTIONS.md#oq-04--value-formats-in-the-english-interface) asks the Owner and recommends §16's formats unchanged in both languages — the English interface translates labels, never values. Until the Owner answers, the prototype shows §16's formats in both languages.

**Plurals.** An Indonesian noun does not change with its number — "1 baris", "20 baris" —, so the Indonesian strings need no plural forms. An English string with a count uses `trans_choice`, its forms separated by `|`, with ranges where needed and `:count` for the number (Laravel 13.x); each counted string in a journey specification's copy section gives its English forms.

### 2.5 What never translates

- **Business data.** Client and legal names, codes, SKUs and user-entered text "are data and are never translated" ([DESIGN_SYSTEM §16](../DESIGN_SYSTEM.md#16-copy-and-formatting)); so are the names of companies, people, products and units, a note a user writes and the values a history entry records.
- **Business numbers.** Document, project and purchase numbers and lot codes appear exactly as issued (§16).
- **Issued and generated documents.** Every rendition stays Indonesian whatever the display language ([CAP-14](../../01-product/V1_SCOPE.md#must-ship); C-1): a document issued while the issuer's interface is in English is the same Indonesian document. An export is a generated file of business data and stays Indonesian, its column headers included (Level-1 reading of CAP-14).
- **Document type names.** The approved names (§16) name Indonesian documents, and the proposed English rendering of each is the approved name itself, so that the screen matches the paper — proposed copy, for the Owner's validation at the preview.

### 2.6 The reference's micro-copy (GR-17; GP-5)

The reference's components carry their own micro-copy — Indonesian defaults, partial English through `L(id, en)` —, and it is never normative wording ([GP-5](../evidence/P7_REBASELINE_GATE.md#checkpoint-c1--closure-on-the-golden-frontend-reference-dir-059)); [GR-17](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01) carries its localization to Stage 2.

- **In the application** no string comes from the reference: each becomes a key of §2.2, its Indonesian text canonical, recorded or proposed under §2.3, its English rendering proposed.
- **In the prototype** the vendored scripts stay byte-identical (SD-04; SP-1), so a string they render cannot be edited. On a journey screen each such string is MultipleCorp copy of §2.3, or kept off the screen, or listed in [DEVIATION_REGISTER](DEVIATION_REGISTER.md) with its screen and the language it shows in — never shown unlisted. A string with no English rendering, shown on an English page, is a missing string (§2.7). Which vendored strings the journey screens show, and how those scripts choose their language, is read from the vendored files in Part 5 and recorded in DEVIATION_REGISTER and [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md).

### 2.7 Both languages, no missing string

**The rule.** Every interface string exists in both languages before its screen is accepted, and no component holds a hardcoded user-facing string ([CAP-14](../../01-product/V1_SCOPE.md#must-ship)). Each of these is a defect: a key shown as itself; an empty string; on an English page, an Indonesian interface string — other than §2.5's values, names and document type names —; a sentence built from concatenated pieces.

**In the application** P8 adds these checks to the definition of done and P11's Tasks fill the localization fields of the AICWDF §23 template ([GAP-037](../../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)); the tooling that finds a missing key is P11's choice — needs verification at implementation.

**How S2-2 checks it**, by script, the result recorded with SV-02 in S2-2's verification records:

1. Each interface string of a prototype screen sits in an element marked with its key of §2.2 — an attribute such as `data-copy` —, and each value or name of §2.5 in an element marked as data. Both pages of a screen, under `/id/` and `/en/` (§4), come from one copy table per screen, the journey specification's (§5).
2. The script loads every page of both sets in a headless browser on 127.0.0.1, with the tooling [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md) plans within SS-04–SS-06, and reads the marked elements.
3. A screen passes when its two pages hold the same keys; no keyed element is empty or shows its key; on the English page no keyed text equals its Indonesian text unless the copy table marks it the same in both — a name, "PDF", "WIB", a document type name —; and no visible text stands outside a keyed or data element, except a vendored string listed under §2.6.
4. The check covers the scripted states S2-2 renders — messages, dialogs, the out-of-scope page and the panel "Tentang prototipe ini" (SS-08) included —, not only each page's first view.

### 2.8 The layout after a language change

A string's length differs between the two languages, so every screen is checked in both. In the application, [WFL-04](WRITTEN_FLOWS.md#wfl-04--the-language-switch-both) leaves the user on the same page with its layout re-checked; the switch changes the interface strings only, and the page, its data and its unsent input stay — the input kept in memory, as [IP-11](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-11--session-limits-expiry-and-re-authentication) keeps it across a re-login (Level 1; needs verification at implementation). P8's layout checks follow AICWDF §27 ([GAP-037](../../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)).

S2-2 renders each journey screen in both languages at SV-02's viewports — 1440×900, 1366×768, 390×844 and 320 px wide — and a script checks, for each:

1. no horizontal scroll except in data tables (SV-02);
2. no clipped or truncated label — no button, tab, badge, chip, menu item or column header whose text overflows its box; a long label wraps where its pattern allows, or its copy is shortened in that language;
3. targets at their sizes (SV-05);
4. the same regions and actions in the same order in both languages, with the same primary action.

In the prototype the switch opens the same screen in the other set, a new page (§4): an input typed before the switch is not carried, which [SIMULATION_REGISTER](SIMULATION_REGISTER.md) lists as a difference from the application.

### 2.9 How strings reach the interface

[SECURITY WS-07](../../05-security/SECURITY.md#4-web-and-input-security) lets the shared props hold only the account's display name, its own effective capabilities and granted companies, the locale and generic flash text, and gives the two-factor challenge page and an enrolment-only session no shared prop beyond the display name and the locale; Inertia includes shared data "with every response" (Inertia.js, *Shared data*). So every response carries the locale, and the string tables never travel as shared props. The strings reach React either with each page's props — the page's own group of §2.2 — or as a static bundle per language served from the application's own origin, which WS-08's policy admits; that choice, and any client library it would bring, is P11's under DEP-07 — needs verification at implementation. Messages, validation errors and flash text come from the server in the request's locale — needs verification at implementation.

## 3. Persistence — stated, not decided

### 3.1 What the sources fix

- **C-1** ([source record 50](../../00-governance/sources/P7_REBASELINE_OWNER_DECISIONS_2026-10-04.txt)): "pre-login preference may be device/browser local"; "authenticated preference is per account"; the approved data model is inspected first; an existing suitable mechanism is preferred when it preserves the approved security and shared-device rules; no generic JSON or preferences store is invented; a genuinely new field goes "through the repository's normal database/change-control process before it becomes normative".
- **GR-14** — whether a UI preference, the language before sign-in among them, persists and how is Stage 2–3 work under C-1; on a shared device a browser-stored choice follows the device, not the account ([gate record g)](../evidence/P7_REBASELINE_GATE.md#g-client-side-storage-and-shared-devices)).
- **SECURITY** ([§2](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials); [§4](../../05-security/SECURITY.md#4-web-and-input-security)) — sessions live on the server, under a cookie that expires when the browser closes (AU-06); they end after 60 idle minutes or 12 hours after login (AU-07); V1 has no "remember me" — no persistent login cookie —, so that the absolute timeout is real on shared office PCs and floor devices (AU-08); WS-07 lists the locale among the shared props.
- **The approved data model** — the `users` table has no locale or preference column, and no logical table holds a per-account preference ([DATABASE §4.1](../../04-architecture/DATABASE/s04-01-iam.md#41-iam--identity--access); [GAP-037](../../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)'s Stage 1 continuation).
- **The prototype** — no storage of any kind, browser storage included (SS-07); [SP-13](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents)'s guard makes every storage access throw.

### 3.2 Before sign-in

C-1 allows a device-local choice. Three ways keep it, none decided here — Stage 3 chooses under GR-14 and SECURITY:

| Way | How | Within C-1 and SECURITY | Cost |
| --- | --- | --- | --- |
| (P1) The address | the language a parameter of the sign-in pages' address, kept by each link between them | nothing is stored; a new visit starts in Indonesian | the choice is made again at each visit |
| (P2) A device cookie | a cookie holding only `id` or `en`, set by the switch | device-local, holding no account data; on a shared device it follows the device, harmless before sign-in (GR-14) | a second cookie beside AU-06's session cookie, its attributes set under SECURITY in P6 and P11 |
| (P3) Browser storage | the client keeps the choice in local storage | browser-local, as C-1 names it; not among the data the GA1 reconciliation keeps out of browser storage (gate record g)) | the server does not see the choice, so its own strings — AU-04's generic answers — need the choice sent with each request |

Level-1 choices, proposed, whichever way Stage 3 takes:

- A choice made before sign-in never writes the account's preference: a device's choice must not follow a person into their account (GR-14).
- The pages before the full session — PG-001, PG-002, PG-003 and the enrolment-only session's PG-004 — show the pre-login choice, which WS-07's locale carries there; the account's preference applies from the first page after sign-in, so that the language does not change in the middle of the sign-in sequence ([AUTH_JOURNEYS](AUTH_JOURNEYS.md#2-the-journeys)).
- An account that has never chosen gets the default, Indonesian (C-1).

### 3.3 After sign-in

| Way | Per account (C-1) | Security and shared devices | Schema |
| --- | --- | --- | --- |
| (a) A nullable column `users.locale` | yes — kept with the account, across sessions and devices | read into the session at sign-in and carried as WS-07's locale; nothing kept on the device | one new column, through database change control |
| (b) The session only | no — lost when the session ends, at the browser's close, after 60 idle minutes or 12 hours (AU-06; AU-07): the account chooses again at each sign-in, a choice per session | nothing kept beyond the session | none |
| (c) A device cookie | no — it follows the device: on a shared office PC or floor device (AU-08) one person's choice applies to the next person's account (GR-14) | a persistent cookie beside the session's | none |
| (d) An existing place | none exists — no table holds a per-account preference, and C-1 rules out a generic JSON or preferences store | — | — |

Only (a) gives a preference per account while keeping the approved security and shared-device rules, so the analysis shows that a new field is genuinely needed. Under C-1 and [CHANGE_CONTROL](../../00-governance/CHANGE_CONTROL.md#decision-authority) it is not enacted: [OQ-05](OWNER_QUESTIONS.md#oq-05--where-the-accounts-language-preference-is-kept) asks the Owner, and [GAP-056](../../00-governance/GAP_REGISTER.md#gap-056--the-accounts-language-preference-has-no-approved-storage-place) records it, OWNER_DECISION_REQUIRED. The exact change it would put to change control: in `users`, one nullable column `locale`, limited by a CHECK to `id` and `en`, NULL meaning the default, Indonesian — a column, so the 125 logical tables stay 125. Its reason: C-1's per-account preference. Its impact: DATABASE §4.1; the account's own change from the account menu and *Akun Saya* (PG-065), whose route, request-forgery protection and any audit P6 and P11 define under SECURITY; the session's locale set from it at sign-in, WS-07 unchanged; P8's tests.

Until the Owner decides, S2-1's documents assume no column: the journey specifications show the switch and its effect on the current session, and the prototype carries the language in the address (§4). The re-login dialog keeps the session's language ([AUTH_JOURNEYS §2.5](AUTH_JOURNEYS.md#25-the-re-login-after-an-expiry--d-ux-03s-redesign-proposed)); at logout the session ends with its locale (AU-06), and the sign-in page follows §3.2 ([AUTH_JOURNEYS §2.7](AUTH_JOURNEYS.md#27-logout)).

## 4. The prototype's mechanism (prototype-only)

Chosen for the prototype only, within [SS-07](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061) and [SP-13](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents); it decides nothing for the application (§3):

- The language is the first segment of every page address, `/id/` or `/en/`; a new visit starts under `/id/`, the default.
- Every journey screen, the out-of-scope page and the panel exist in both sets, built from one copy table per screen (§2.7), so that no string exists in one language only.
- Every link keeps its segment. The switch — "ID | EN" on PG-001, and "Bahasa tampilan: Indonesia | English" in the account menu and, on the phone, in *Lainnya* › *Akun* — links to the same screen in the other set.
- Nothing is stored: no cookie, no browser storage — SP-13's guard makes any attempt throw —, and no history state beyond what the Owner's statement under SP-13 allows: an address, a scroll position or a non-sensitive key.
- [SIMULATION_REGISTER](SIMULATION_REGISTER.md) lists the mechanism as a simulation of §3's persistence, with §2.8's difference; [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md) plans how S2-2 builds the two sets, with Node.js built-ins only.

## 5. Copy rules for the journey specifications

Each journey specification's copy section ([DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.13) holds, for each screen, one table with the columns Key, Indonesian, English, Kind and Source:

1. **Mandated copy** — each canonical Indonesian text quoted exactly, with its source: a MSG text of [ADMIN_FLOW §14](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue), a GL term of [INFORMATION_ARCHITECTURE §3](../INFORMATION_ARCHITECTURE.md#3-glossary), an [AU-04](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials) answer, H7-16's advice, a state label of [DESIGN_SYSTEM §12.1](../DESIGN_SYSTEM.md#121-status-system), a document type name, or a label the gate record holds for R1 or VC1. Mandated copy stays on the screen, visible and concise ([R1 g)](../evidence/P7_REBASELINE_GATE.md#g-copy-oc-06)); its English is marked "proposed".
2. **Proposed copy** — a heading, label, helper line or panel text the sources do not define, drafted in Indonesian with the GL terms within OC-06's budget — a heading, a state or number, an action; a helper sentence only when the user needs it to decide or act now —, citing the requirement it serves and marked "proposed copy", with a proposed English rendering.
3. **Data** — the names, numbers, amounts and dates the screen shows, from the synthetic set (SP-20), marked as data and never translated (§2.5), formatted under §2.4.
4. **Counted strings and values** — a counted string gives its English forms (§2.4); a string with values gives its placeholders (§2.2).
5. **Limits** — no copy states a rule, figure or business meaning beyond its source, and none is marketing language (DIR-064 §11.3).

The Owner validates the proposed copy and every English rendering at the preview ([GAP-037](../../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)).

## 6. Trace

| Requirement | Source | Here | In the prototype | P8 and P11 |
| --- | --- | --- | --- | --- |
| Indonesian by default, English secondary | [CAP-14](../../01-product/V1_SCOPE.md#must-ship); C-1 | §2.1 | both sets, `/id/` the default (§4) | P8's localization checks ([GAP-037](../../00-governance/GAP_REGISTER.md#triage)) |
| The switch and its placements | [R1 k)](../evidence/P7_REBASELINE_GATE.md#k-language-and-authentication); [WFL-04](WRITTEN_FLOWS.md#wfl-04--the-language-switch-both) | §§1, 4 | "ID \| EN" on PG-001 (S2-J01); the account menu and *Lainnya* › *Akun* in the shell | P8 language-switch check |
| No hardcoded copy, no missing string | CAP-14; GAP-037 | §§2.2, 2.7 | §2.7's check, by script | P8's definition of done; P11's AICWDF §23 fields |
| Copy ownership and the Owner's validation | GAP-037; [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §11.3 | §§2.3, 5 | the copy sections of S2-J01–S2-J06 | the Owner's validated copy carried into Stage 3 |
| Formats | [DESIGN_SYSTEM §16](../DESIGN_SYSTEM.md#16-copy-and-formatting); [BR-DT-06](../../02-domain/BUSINESS_RULES.md#dates-and-backdating--dir-018); [IP-13](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry) | §2.4; OQ-04 | §16's formats in both languages | P8 format checks in both languages |
| Documents and business data in Indonesian | CAP-14; C-1; DIR-037 D7 | §2.5 | data marked and untranslated | P8: a document issued under the English interface is the Indonesian document |
| The reference's micro-copy | GP-5; GR-17 | §2.6 | DEVIATION_REGISTER | — |
| The layout after a change | GAP-037; SV-02; SV-05 | §2.8 | §2.8's check at SV-02's viewports | P8's layout checks (AICWDF §27) |
| How strings reach React | [WS-07](../../05-security/SECURITY.md#4-web-and-input-security); Inertia | §2.9 | — | P11, DEP-07 |
| The choice before sign-in | C-1; GR-14 | §3.2 | the address (§4) | Stage 3 chooses |
| The account's preference | C-1; [DATABASE §4.1](../../04-architecture/DATABASE/s04-01-iam.md#41-iam--identity--access); AU-06–AU-08; WS-07 | §3.3; OQ-05; GAP-056 | the address (§4) | after the Owner's decision: DATABASE change control, then P8 |

## 7. Questions, Level-1 choices and what stays open

- **Questions** ([OWNER_QUESTIONS](OWNER_QUESTIONS.md#how-this-register-works)): [OQ-04](OWNER_QUESTIONS.md#oq-04--value-formats-in-the-english-interface) — the value formats of the English interface, needed at the Owner's preview and before Stage 3, prototype and Stage 3 only, no gap; [OQ-05](OWNER_QUESTIONS.md#oq-05--where-the-accounts-language-preference-is-kept) — where the account's preference is kept, before Stage 3, a P4 change — [DATABASE](../../04-architecture/DATABASE/README.md#database-architecture) — if (a), [GAP-056](../../00-governance/GAP_REGISTER.md#triage).
- **Level-1 choices**, recorded with TECH-032: the key naming (§2.2); exports Indonesian with their headers (§2.5); a pre-login choice never writing the account's preference, and the pages before the full session showing the pre-login choice (§3.2); a language change keeping the page and its unsent input (§2.8); the prototype's mechanism (§4).
- **Stated, not decided:** the way the choice is kept before sign-in (§3.2) — Stage 3 under [GR-14](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01); the string delivery to React (§2.9) — P11 under [DEP-07](../../01-product/V1_SCOPE.md#dependencies-and-evidence-needed).
- **For later change control, an observation and not a proposal:** [ARCHITECTURE §16](../../04-architecture/ARCHITECTURE.md#16-configuration-environments-and-database-roles) says "user-facing locale is Indonesian (P7)", which reads with CAP-14's bilingual interface; its alignment belongs to the stage that makes the localization contract normative.
