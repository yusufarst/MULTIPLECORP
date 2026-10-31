# Localization

Status: APPROVED | Updated: 2026-10-31 | Owner: Planning

Approval: [APPR-011](../00-governance/DECISION_LOG.md#appr-011--p7-ux-re-baseline-approved), explicit Owner approval on 2026-10-18 (DIR-081 OB-07 (a) and (c)) of this document as committed in run PC-4's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-31, under [DIR-100](../00-governance/DECISION_LOG.md#dir-100--change-4-the-contract-rows-and-the-consistency-edit), Change 4 of [TASK_PLAN §7](../11-tasks/TASK_PLAN.md#7-controlled-task-plan-changes), the controlled change DIR-085 OF-02 and OF-03 call for (record 84 ER-09), adds six keys to §6.7 for the controls ROUTE_CONTRACTS adds, the four proposed among them for the Owner's single pass (§5). Each is marked `DIR-100`. It takes effect at the Owner's merge.

Amendment: on 2026-10-31, under [DIR-101](../00-governance/DECISION_LOG.md#dir-101--the-owners-decisions-on-p11q-07-of-04-and-the-labels), the Owner's decisions on P11Q-07 and OF-04 (APPR-027) and Change 15 of [TASK_PLAN §7](../11-tasks/TASK_PLAN.md#7-controlled-task-plan-changes), adds three keys and records the Owner's validation of four Indonesian labels. Each changed statement is marked `DIR-101`.

Run PC-2 wrote this new document on 2026-10-18 under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-63, in Stage 3 of the P7 UX re-baseline, from Stage 2's [LOCALIZATION_ARCHITECTURE](stage2/LOCALIZATION_ARCHITECTURE.md) with the Owner's CP-A decisions — OQ-04 (a) and OQ-05 (a), the account's language kept in `users.locale` ([DIR-077](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-04 item 14; CA-08) — and its key inventory (SP-68). It answers the design half of [GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested); P8 tests both languages. Run PC-3 completed it on 2026-10-18 under [DIR-080](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) SP-73: a proposed English rendering for each of the 306 keys owed at PC-2's end ([DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-15) — 0 owed —, and the validation rule of §5 with its review list (CB-15; CD-07). Run PC-4 recorded the Owner's OG-2 decisions in it on 2026-10-18 under [DIR-082](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) SP-80 and SP-82: the single pass of §5.1, its 881 keys validated and none marked ([DIR-081](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) OB-06), and OQ-24 (a)'s rule for the copy P11 writes later (OB-04), each marked `DIR-081`.

**Sources.** [V1_SCOPE](../01-product/V1_SCOPE.md)'s language and experience contract and CAP-14; the Owner's C-1 ([source record 50](../00-governance/sources/P7_REBASELINE_OWNER_DECISIONS_2026-10-04.txt)); LOCALIZATION_ARCHITECTURE, carried here where §§3–5 say so; [SECURITY](../05-security/SECURITY.md) AU-06–AU-08, AU-13 and WS-07; [API_AND_INTEGRATIONS](../06-api-performance/API_AND_INTEGRATIONS.md) RB-10 and [DATABASE §4.1](../04-architecture/DATABASE/s04-01-iam.md#41-iam--identity--access) as drafted for CA-08; [ADMIN_FLOW §14](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md) §§3 and 8; [DESIGN_SYSTEM](DESIGN_SYSTEM.md) §§12.1 and 16; the journeys' copy sections ([S2-J01–S2-J06](stage2/journeys/) §8) and the session copy the prototype carries — its keyed copy and pages at commit D, read, never changed.

Authority: P7 — the localization contract: the locales, how the language is chosen and kept, the keys and who owns their text, the formats, what never translates, and the inventory of every key. It adds no behaviour; a string's meaning is its source's — ADMIN_FLOW's messages, INFORMATION_ARCHITECTURE's glossary and pages, DESIGN_SYSTEM's statuses.

## 1. The localization contract

AICWDF §18.11, for every user-facing page:

| Element | Contract | Grounds |
| --- | --- | --- |
| Default locale | `id` — Indonesian (id-ID) | C-1; V1_SCOPE |
| Supported locales | `id` and `en` | C-1; CAP-14 |
| Plain-language Indonesian | required — natural, professional Bahasa Indonesia from one glossary (INFORMATION_ARCHITECTURE §3) | V1_SCOPE; UXN-01 |
| Language switch | required — before sign-in on the sign-in pages, after sign-in in the account menu and *Akun Saya* (§2) | C-1; R1 k); `DIR-077` CA-08 |
| Hard-coded localizable copy | forbidden — every string is a key with both texts (§3.6) | AICWDF §18.11 |
| Layout | holds under the longer of the two texts and under WCAG 2.2 1.4.12's spacing; mandated copy is never cut (§3.7; GR-35) | AICWDF §18.11; GR-35 |
| Values | formatted one way in both languages — DESIGN_SYSTEM §16's formats; the English interface translates labels, never values (§3.3) | OQ-04 (a), `DIR-077` CA-04 item 14 |

## 2. Locales, the choice and where it is kept

- **Before sign-in — the address (LOCALIZATION_ARCHITECTURE §3.2, way P1; Level 1, D-IA-23).** The sign-in pages PG-001–PG-004 carry the chosen language as one whitelisted address key, `bahasa=id` or `bahasa=en`, kept by each link between them (INFORMATION_ARCHITECTURE §11). Nothing is stored — no cookie and no browser storage —, so a shared office PC or floor device keeps no one's choice (GR-14; AU-08), and a new visit starts in Indonesian. Ways P2 and P3 are not taken: each would keep something on the device that SECURITY does not otherwise need.
- **After sign-in — the account's own row (`DIR-077` CA-08).** The account's language is `users.locale` — nullable, `id` or `en`, NULL meaning the default, Indonesian (DATABASE §4.1, as drafted) —, read into the session at sign-in and carried as WS-07's locale. Only the account changes it, through *Bahasa tampilan* in the account menu or on *Akun Saya* (SECURITY AU-13; API_AND_INTEGRATIONS RB-10; ROUTE-118); the change applies to the current session at once, keeps the page and its unsent input (§3.7), and is audited (RG-11).
- **Carried Level-1 rules** (LOCALIZATION_ARCHITECTURE §3.2; TECH-032): a choice made before sign-in never writes the account's language, so a device's choice never follows a person into their account; the pages before the full session — PG-001–PG-004 — show the pre-login choice, and the account's language applies from the first page after sign-in, so the language does not change in the middle of the sign-in sequence; an account that never chose gets Indonesian; the re-login dialog keeps the session's language (IP-11); at logout the session ends with its locale and the sign-in page follows the address (AU-06).
- **Documents and exports** stay Indonesian whatever the interface language (§3.4).

## 3. Keys, ownership, formats and rules

Carried from LOCALIZATION_ARCHITECTURE §2, each subsection as Stage 2 wrote it, with its links moved and its decided points stated.

### 3.1 Keys and their naming (proposed, Level 1)

One key per string, in dot syntax, grouped by area, with English identifiers that trace each string to its source:

| Group | Key, for example | Holds | Indonesian text from |
| --- | --- | --- | --- |
| `messages` | `messages.msg_19` | the message catalogue, one key per MSG | [ADMIN_FLOW §14](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) |
| `glossary` | `glossary.gl_041` | the glossary terms used as labels, one key per GL | [INFORMATION_ARCHITECTURE §3](INFORMATION_ARCHITECTURE.md#3-glossary) |
| `status` | `status.project.draft` | the state labels, one key per family and state | [DESIGN_SYSTEM §12.1](DESIGN_SYSTEM.md#121-status-system) |
| `auth` | `auth.code_mismatch` | AU-04's generic answers and H7-16's advice | [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials); the gate record |
| `documents` | `documents.doc_06` | the document type names | V1_SCOPE's [document catalog](../01-product/V1_SCOPE.md#selectable-document-catalog) |
| `pages` | `pages.pg_001.heading` | each page's own copy, one group per page of [IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md) | the journey specifications' copy sections (LOCALIZATION_ARCHITECTURE §4, prototype-only) |

A string with values carries named placeholders — Laravel's `:name` form —, never concatenated pieces, so each language orders its own words. Laravel keeps strings as PHP arrays or as JSON, one file set per language (Laravel 13.x); which form, and how the strings reach React (§3.8), is P11's.

### 3.2 Who owns the copy

| Copy | Indonesian | English |
| --- | --- | --- |
| Defined by a source and held by this inventory — MSG-01–MSG-41, the GL terms, AU-04's answers, H7-16's advice, the state labels of [DESIGN_SYSTEM §12.1](DESIGN_SYSTEM.md#121-status-system), the document type names, the signals' empty states and the control labels the contracts quote | canonical, quoted exactly | proposed by Planning, validated by the Owner in one pass at APPR-011 (§5; [GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)) |
| Defined by a source and keyed when P11 writes it — the copy §7 lists outside this inventory | canonical, quoted exactly | proposed with P11's Task, validated as [OQ-24](stage2/OWNER_QUESTIONS.md#oq-24--copy-written-after-appr-011) decides (GAP-037) |
| Recorded in the gate record — R1's and VC1's labels ([R1 k)](evidence/P7_REBASELINE_GATE.md#k-language-and-authentication) for sign-in) | quoted from there | proposed, validated by the Owner in the same pass (§5) |
| Not defined by any source — a heading, a label, a helper line, the prototype's panel —, held by this inventory for the six journeys; beyond them keyed when P11 writes it, validated as [OQ-24](stage2/OWNER_QUESTIONS.md#oq-24--copy-written-after-appr-011) decides (§7) | proposed copy, drafted by Planning with the GL terms and within [OC-06](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework), citing the requirement it serves; validated by the Owner in the same pass at APPR-011 (§5) | proposed, validated by the Owner in the same pass (§5) |

The Indonesian string is the source and the English one renders it: no English string exists without its Indonesian source, and no copy states a rule, figure or business meaning that its source does not give (DIR-064 §11.3).

### 3.3 Formats

[DESIGN_SYSTEM §16](DESIGN_SYSTEM.md#16-copy-and-formatting) formats each kind of value one way (UXV-12): Rupiah with "Rp" and no space, the dot between thousands and the comma before sen ("Rp1.250.000", "Rp1.250.000,50"); negative money with the minus sign before "Rp" ("−Rp250.000"); quantities exact, never rounded, followed by their unit ("3,5 kg"; "120 rim (24 dus)"); percentages ("0,25%"); dates ("02 Okt 2026", and "2 Oktober 2026" in a sentence); times on the 24-hour clock with a dot and "WIB" ("14.05 WIB"); periods; business numbers exactly as issued; and counts bounded where [PF-15](../06-api-performance/PERFORMANCE.md#4-pagination) bounds them. All dates and times are WIB: business dates, numbering periods and aging use Asia/Jakarta day boundaries ([BR-DT-06](../02-domain/BUSINESS_RULES.md#dates-and-backdating--dir-018)), and the application stores timestamps in UTC ([ARCHITECTURE §16](../04-architecture/ARCHITECTURE.md#16-configuration-environments-and-database-roles)). Entry follows the same convention: [IP-13](ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry) normalizes the Indonesian separators only, and any other dot or character is a validation error (IP-14), never a silent reinterpretation.

These sources fixed the formats for an interface they assumed Indonesian. The Owner decided at CP-A that they stay unchanged in both languages — the English interface translates labels, never values ([OQ-04](stage2/OWNER_QUESTIONS.md#oq-04--value-formats-in-the-english-interface) (a); `DIR-077` CA-04 item 14): a figure reads one way whatever the language, entry accepts one convention (IP-13), and the screen matches the issued document.

**Plurals.** An Indonesian noun does not change with its number — "1 baris", "20 baris" —, so the Indonesian strings need no plural forms. An English string with a count uses `trans_choice`, its forms separated by `|`, with ranges where needed and `:count` for the number (Laravel 13.x); each counted string in a journey specification's copy section gives its English forms.

### 3.4 What never translates

- **Business data.** Client and legal names, codes, SKUs and user-entered text "are data and are never translated" ([DESIGN_SYSTEM §16](DESIGN_SYSTEM.md#16-copy-and-formatting)); so are the names of companies, people, products and units, a note a user writes and the values a history entry records.
- **Business numbers.** Document, project and purchase numbers and lot codes appear exactly as issued (§16).
- **Issued and generated documents.** Every rendition stays Indonesian whatever the display language ([CAP-14](../01-product/V1_SCOPE.md#must-ship); C-1): a document issued while the issuer's interface is in English is the same Indonesian document. An export is a generated file of business data and stays Indonesian, its column headers included (Level-1 reading of CAP-14).
- **Document type names.** The approved names (§16) name Indonesian documents, and the proposed English rendering of each is the approved name itself, so that the screen matches the paper — proposed copy, for the Owner's validation in the single pass at APPR-011 (§5).

### 3.5 The reference's micro-copy (GR-17; GP-5)

The reference's components carry their own micro-copy — Indonesian defaults, partial English through `L(id, en)` —, and it is never normative wording ([GP-5](evidence/P7_REBASELINE_GATE.md#checkpoint-c1--closure-on-the-golden-frontend-reference-dir-059)); [GR-17](evidence/P7_REBASELINE_GATE.md#carried-items-gr-01) carries its localization to Stage 2.

- **In the application** no string comes from the reference: each becomes a key of §3.1, its Indonesian text canonical, recorded or proposed under §2, its English rendering proposed.
- **In the prototype** the vendored scripts stay byte-identical (SD-04; SP-1), so a string they render cannot be edited. On a journey screen each such string is MultipleCorp copy of §2, or kept off the screen, or listed in [DEVIATION_REGISTER](stage2/DEVIATION_REGISTER.md) with its screen and the language it shows in — never shown unlisted. A string with no English rendering, shown on an English page, is a missing string (§3.6). Which vendored strings the journey screens show, and how those scripts choose their language, is read from the vendored files in Part 5 and recorded in DEVIATION_REGISTER and [PROTOTYPE_PLAN](stage2/PROTOTYPE_PLAN.md).

### 3.6 Both languages, no missing string

**The rule.** Every interface string exists in both languages before its screen is accepted, and no component holds a hardcoded user-facing string ([CAP-14](../01-product/V1_SCOPE.md#must-ship)). Each of these is a defect: a key shown as itself; an empty string; on an English page, an Indonesian interface string — other than §3.4's values, names and document type names —; a sentence built from concatenated pieces.

**In the application** P8 adds these checks to the definition of done and P11's Tasks fill the localization fields of the AICWDF §23 template ([GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)); the tooling that finds a missing key is P11's choice — needs verification at implementation.

**How S2-2 checks it**, by script, the result recorded with SV-02 in S2-2's verification records:

1. Each interface string of a prototype screen sits in an element marked with its key of §3.1 — an attribute such as `data-copy` —, and each value or name of §3.4 in an element marked as data. Both pages of a screen, under `/id/` and `/en/` (LOCALIZATION_ARCHITECTURE §4, prototype-only), come from one copy table per screen, the journey specification's (LOCALIZATION_ARCHITECTURE §4, prototype-only).
2. The script loads every page of both sets in a headless browser on 127.0.0.1, with the tooling [PROTOTYPE_PLAN](stage2/PROTOTYPE_PLAN.md) plans within SS-04–SS-06, and reads the marked elements.
3. A screen passes when its two pages hold the same keys; no keyed element is empty or shows its key; on the English page no keyed text equals its Indonesian text unless the copy table marks it the same in both — a name, "PDF", "WIB", a document type name —; and no visible text stands outside a keyed or data element, except a vendored string listed under §3.5.
4. The check covers the scripted states S2-2 renders — messages, dialogs, the out-of-scope page and the panel "Tentang prototipe ini" (SS-08) included —, not only each page's first view.

### 3.7 The layout after a language change

A string's length differs between the two languages, so every screen is checked in both. In the application, [WFL-04](stage2/WRITTEN_FLOWS.md#wfl-04--the-language-switch-both) leaves the user on the same page with its layout re-checked; the switch changes the interface strings only, and the page, its data and its unsent input stay — the input kept in memory, as [IP-11](ADMIN_FLOW/s06-09-session-forms-lists.md#ip-11--session-limits-expiry-and-re-authentication) keeps it across a re-login (Level 1; needs verification at implementation). P8's layout checks follow AICWDF §27 ([GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)).

S2-2 renders each journey screen in both languages at SV-02's viewports — 1440×900, 1366×768, 390×844 and 320 px wide — and a script checks, for each:

1. no horizontal scroll except in data tables (SV-02);
2. no clipped or truncated label — no button, tab, badge, chip, menu item or column header whose text overflows its box; a long label wraps where its pattern allows, or its copy is shortened in that language;
3. targets at their sizes (SV-05);
4. the same regions and actions in the same order in both languages, with the same primary action.

In the prototype the switch opens the same screen in the other set, a new page (LOCALIZATION_ARCHITECTURE §4, prototype-only): an input typed before the switch is not carried, which [SIMULATION_REGISTER](stage2/SIMULATION_REGISTER.md) lists as a difference from the application.

### 3.8 How strings reach the interface

[SECURITY WS-07](../05-security/SECURITY.md#4-web-and-input-security) lets the shared props hold only the account's display name, its own effective capabilities and granted companies, the locale and generic flash text, and gives the two-factor challenge page and an enrolment-only session no shared prop beyond the display name and the locale; Inertia includes shared data "with every response" (Inertia.js, *Shared data*). So every response carries the locale, and the string tables never travel as shared props. The strings reach React either with each page's props — the page's own group of §3.1 — or as a static bundle per language served from the application's own origin, which WS-08's policy admits; that choice, and any client library it would bring, is P11's under DEP-07 — needs verification at implementation. Messages, validation errors and flash text come from the server in the request's locale — needs verification at implementation.

## 4. Copy rules

Each journey specification's copy section ([DIR-064](../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.13) holds, for each screen, one table with the columns Key, Indonesian, English, Kind and Source:

1. **Mandated copy** — each canonical Indonesian text quoted exactly, with its source: a MSG text of [ADMIN_FLOW §14](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue), a GL term of [INFORMATION_ARCHITECTURE §3](INFORMATION_ARCHITECTURE.md#3-glossary), an [AU-04](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) answer, H7-16's advice, a state label of [DESIGN_SYSTEM §12.1](DESIGN_SYSTEM.md#121-status-system), a document type name, or a label the gate record holds for R1 or VC1. Mandated copy stays on the screen, visible and concise ([R1 g)](evidence/P7_REBASELINE_GATE.md#g-copy-oc-06)); its English is marked "proposed".
2. **Proposed copy** — a heading, label, helper line or panel text the sources do not define, drafted in Indonesian with the GL terms within OC-06's budget — a heading, a state or number, an action; a helper sentence only when the user needs it to decide or act now —, citing the requirement it serves and marked "proposed copy", with a proposed English rendering.
3. **Data** — the names, numbers, amounts and dates the screen shows, from the synthetic set (SP-20), marked as data and never translated (§3.4), formatted under §3.3.
4. **Counted strings and values** — a counted string gives its English forms (§3.3); a string with values gives its placeholders (§3.1).
5. **Limits** — no copy states a rule, figure or business meaning beyond its source, and none is marketing language (DIR-064 §11.3).

The Owner validates the proposed copy and every English rendering in one pass at APPR-011 (§5; `DIR-079` CB-15, CD-07; [GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)).

## 5. Copy validation (CA-04 items 7–10)

**The validation rule** ([DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-15 and CD-07; [DIR-080](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) SP-73). Indonesian stays the canonical text. The Owner validates the copy in one pass at APPR-011: every key on the review list of §5.1 — the proposed Indonesian copy, D6's punctuation and CA-04 items 8–10 included (CD-07), and every proposed English rendering, the 306 run PC-3 wrote for the keys owed at PC-2's end (CB-15) and those Stage 4 added among them. The table is accepted as proposed except the keys the Owner marks, and each marked key is corrected before Stage 5. This changes how the Owner validates, not that the Owner validates (CB-15).

**The pass** (`DIR-081` OB-06, 2026-10-18). The review list of §5.1 at `4b3ee9b` — 881 keys, Indonesian and English — is accepted as proposed; the Owner marked no key, so none is corrected before Stage 5. Each key on the list is marked validated in §6 (§5.1), its copy in force with APPR-011.

**Copy written after APPR-011** (`DIR-081` OB-04; [OQ-24](stage2/OWNER_QUESTIONS.md#oq-24--copy-written-after-appr-011) (a)) — the Owner's rule, word for word: «A key P11 adds is accepted when its Indonesian is a GL term or a label of an approved source and its English is that term's or label's validated rendering. Any other new copy is marked proposed and listed for me with its page's P8 review, accepted except the keys I mark.» P11's Task template carries the list (OB-04), and each page's review is P8's ([GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)).

The Owner accepted CA-04 items 7–10 for the sessions and left their final wording to Stage 3 (`DIR-077` CA-04 items 7–10). The table below records Stage 3's check of each against its canonical name and §4's rules — none changes a meaning, a business term or a glossary label —; each stays on the review list for the Owner's pass. The Owner's C2 decision names no change of copy (DIR-079).

| Item | Key | Indonesian | English | Stage 3's check (PC-2) |
| --- | --- | --- | --- | --- |
| 7 — D6 | MSG-01's lead as the notice's title (TB-D04) | the title is the message's lead, with no colon and no final full stop; the detail follows in the body as whole sentences | the same rule | settled as this rule (Level 1, TECH-037) |
| 8 | `pages.pg_002.back` | Kembali | Back | checked — the canonical "Kembali" of IP-08's dialogs (OQ-14) |
| 8 | `pages.pg_002.code_advice` | Jangan bagikan kode ini kepada siapa pun. | Never share this code with anyone. | checked — H7-16's advice (SP-48) |
| 8 | `pages.pg_006.ageing_table` | Tabel | Table | checked — the chart's table alternative (S2-J02 §§8–9) |
| 8 | `pages.pg_006.col_bucket` | Umur | Age | checked |
| 8 | `pages.pg_006.col_amount` | Jumlah | Amount | checked |
| 8 | `pages.pg_006.bucket_31_60` | Lewat 31–60 hari | 31–60 days overdue | checked — BR-FIN-15's buckets |
| 8 | `pages.pg_006.bucket_61_90` | Lewat 61–90 hari | 61–90 days overdue | checked — BR-FIN-15 |
| 8 | `pages.pg_006.bucket_90` | Lewat lebih dari 90 hari | More than 90 days overdue | checked — BR-FIN-15 |
| 9 | `pages.pg_017.cancel` | Batal | Cancel | checked — "Batal" canonical (PT-09) |
| 9 | `result.copied` | Nomor tersalin | Number copied | checked — Golden's result text (GR-17) |
| 9 | the palette's answer when nothing matches | Tidak ada hasil untuk “:q”. | No results for “:q”. | checked — Golden's own text, its value a named placeholder (§3.1); its key `shell.palette.no_results` |
| 10 | S2-1's proposed copy and English renderings — the journeys' copy sections | as §6 lists them, kind *proposed* or *session copy* | as §6 lists them | checked as written for every key §6 holds; a value in them becomes a named placeholder (§3.1) |

### 5.1 The review list (CB-15, CD-07)

Counted by script over §6 when run PC-3 completed this draft, and again after Stage 4's fixes. A key is on the list when its Indonesian text is proposed — its kind *proposed* or *session copy*, alone or beside another — or its English text is proposed, which every English text is except one that is never translated (§3.4) or a value (*data*). **881 keys** are on the list; 8 are not: `documents.draft_marking`, `documents.number_line`, `pages.pg_006.period`, `pages.pg_006.view.arj`, `pages.pg_006.view.btn`, `pages.pg_006.view.ckp`, `pages.pg_017.line_link.code`, `pages.pg_017.line_link.qty`. The English written by PC-3, 410 texts, is CB-15's renderings of the 306 keys owed at PC-2's end — five of them split by Stage 4 into one key per state or merged into the per-state keys that already held their states, so that eleven keys now carry those texts — and the renderings of the 98 keys Stage 4 added or corrected: the second forms of six messages (eight keys), the signals' empty states (24), a region's states (3), the composer's toggle (1), the control labels that ROUTE_CONTRACTS and INFORMATION_ARCHITECTURE §8 quote (59), the PO's approved name (1), PG-001's password message (1) and the reservation cut's block title (1) — the S4F rows of the gate record. The keys themselves, with both texts and their kind, are §6's rows. **The pass** (`DIR-081` OB-06): the 881 keys accepted as proposed, none marked; each key on the list changed, by script, from proposed to validated under APPR-011 — its kind in §6 reads *validated* where it read *proposed*, *session copy · validated* for session copy, and gains *en validated* where its English alone was on the list —; the 8 keys off the list unchanged.

| Group | Keys | On the list | Indonesian proposed | English proposed | English written by PC-3 |
| --- | --- | --- | --- | --- | --- |
| `messages` | 85 | 85 | 25 | 85 | 48 |
| `glossary` | 213 | 213 | 0 | 213 | 208 |
| `status` | 81 | 81 | 4 | 81 | 57 |
| `auth` | 2 | 2 | 0 | 2 | 0 |
| `documents` | 16 | 14 | 0 | 14 | 8 |
| `pages` | 234 | 228 | 175 | 228 | 1 |
| the shared groups of §6.7 | 267 (`DIR-100`; `DIR-101`) | 258 | 97 | 258 | 88 |
| **All** | **898** | **881** | **301** | **881** | **410** |

## 6. Key inventory

Every key, built by script from its source when this draft was written and checked again for gate PC-2 (SP-68): the group of LOCALIZATION_ARCHITECTURE §2.2 (§3.1 here), the key, its Indonesian and English texts, its kind — *canonical* from its source, *recorded* from a measured reference, *proposed* awaiting the Owner's single pass (§5) — since that pass (`DIR-081` OB-06) *validated*, and *en validated* where only the English was proposed —, *session copy* the text the usability sessions read (CA-04 item 10), *data* a value shown as it is — a company code, an SKU, a quantity — and *never translated* a text §3.4 keeps in Indonesian, both off the review list — and its source. Each `{name}` in a text is the key's named placeholder, written `:name` in P11's string files and `:count` for the number of a counted string, whose two English forms are separated by `\|` (§§3.1, 3.3). A catalogue key whose slots describe prose to be written per case — `messages.msg_01`, `msg_02`, `msg_05`, `msg_35` and `msg_39`, MSG-39's `{Kata kerja} {objek}?` and its consequence among them — is the message's pattern, not a runtime string: its instances carry the copy (§6.1), and P11 keys each instance. A runtime slot named in several words is written with underscores in P11's files — `{tanggal lama}` as `:tanggal_lama`, `{daftar catatan yang cocok}` as `:daftar_catatan_yang_cocok`. Every key holds both texts: the 306 English texts marked **owed** at PC-2's end were written by run PC-3 as proposed renderings — from the key's canonical or proposed Indonesian, with the GL terms and the key's placeholders and formats kept, a counted string with its two forms (§3.3) — and were marked *en proposed (PC-3)* in the Kind column, *en validated (PC-3)* since the Owner's pass ([DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-15); P8 fails a page with a missing string (§3.6). A *session copy* text that holds a synthetic value — an amount, a number, a name — becomes a named placeholder in P11 (§3.1). The prototype-only keys — the marker, its panel, the proposals and the out-of-scope page, 20 keys — are not part of V1 and are left out.

### 6.1 `messages` — the message catalogue and its instances

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `messages.msg_01` | {Objek} {kata kerja pasif}. | {Objek} {kata kerja pasif}. | canonical — COMMITTED · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_02` | Tidak dapat diproses: {aturan dalam bahasa bisnis}. {fakta saat ini}. {langkah berikutnya} | Cannot be processed: {aturan dalam bahasa bisnis}. {fakta saat ini}. {langkah berikutnya} | canonical — REJECTED · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_03` | Data ini sudah berubah sejak Anda membukanya. Isian Anda tetap ada di formulir. Tinjau versi terbaru, lalu terapkan ulang. | This data has changed since you opened it. Your input is still in the form. Review the latest version, then re-apply. | canonical — CONFLICT — stale · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_04` | Sudah tercatat — periksa sebelum mengulang. | Already recorded — check before repeating. | canonical — CONFLICT — already recorded · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_05` | {Hasil turunan} sedang dibuat. {Tindakan utama} sudah tercatat. | {Hasil turunan} is being created. {Tindakan utama} is recorded. | canonical — PENDING · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_06` | Sistem sedang sibuk sehingga tindakan ini belum diproses. Aman diulang. | The system is busy, so this action has not been processed. It is safe to repeat. | canonical — FAILED — not processed · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_07` | Belum dapat dipastikan apakah tindakan ini tersimpan. Tekan Coba Lagi — sistem tidak akan mencatat dua kali. | It is not yet certain whether this action was saved. Press Try again — the system will not record it twice. | canonical — FAILED — outcome unknown · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_08` | {Jenis} sudah terbit dengan nomor {nomor}. PDF gagal dibuat dan akan dicoba lagi otomatis (percobaan {n} dari 5). | {Jenis} is issued with number {nomor}. The PDF could not be created and will be retried automatically (attempt {n} of 5). | canonical — A failed rendition · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_09` | Halaman tidak ditemukan. | Page not found. | canonical — Not found — nonexistent or outside scope · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_10` | Anda tidak memiliki kewenangan untuk tindakan ini. | You do not have the capability for this action. | canonical — Forbidden · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_11` | Sesi Anda telah berakhir. Masukkan e-mail dan kata sandi untuk melanjutkan. Isian Anda di halaman ini tidak hilang. | Your session has ended. Enter your email and password to continue. Your input on this page is not lost. | canonical — Session ended, in-place · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_12` | Periksa {n} isian yang ditandai. | Check the {n} marked field.\|Check the {n} marked fields. | canonical — Validation summary · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_13` | Koneksi terputus. Belum ada yang dikirim. Coba lagi setelah tersambung. | Connection lost. Nothing has been sent. Try again once you are connected. | canonical — Network loss before sending · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_14` | Tindakan ini menyangkut catatan perusahaan lain di luar akses Anda. Tidak diproses, dan diteruskan: Owner atau admin yang berwenang akan melihatnya. | This action concerns another company's records outside your access. It was not processed and has been passed on: the Owner or an authorized admin will see it. | canonical — Routed refusal · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_15` | Kemungkinan catatan ganda. {daftar catatan yang cocok}. Jika ini memang catatan berbeda, beri alasan dan lanjutkan. | Possible duplicate record. {daftar catatan yang cocok}. If this really is a different record, give a reason and continue. | canonical — Duplicate warning · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_16` | Tanggal transaksi terisi otomatis {tanggal lama}, sedangkan hari ini {tanggal}. Pilih tanggal yang benar. | The transaction date was filled in automatically as {tanggal lama}, but today is {tanggal}. Choose the correct date. | canonical — A prefilled date that went stale · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_17` | Nomor {jenis} untuk periode {periode} di {perusahaan} belum dimulai. Hanya Owner yang dapat memulai penomoran atau menetapkan nomor awal. Hubungi Owner, lalu ulangi tindakan ini. | Numbering of {jenis} for the period {periode} at {perusahaan} has not started. Only the Owner can start numbering or set a starting number. Contact the Owner, then repeat this action. | canonical — C-10 for an Admin · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_18` | Tidak dapat diimpor — perlu tinjauan Owner. | Cannot be imported — needs the Owner's review. | canonical — An import collision outside scope · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_19` | E-mail atau kata sandi salah. | Incorrect email or password. | canonical — Sign-in or re-authentication failed · en validated | ADMIN_FLOW §14 |
| `messages.msg_20` | Terlalu banyak percobaan. Coba lagi dalam {n} menit. | Too many attempts. Try again in {n} minute.\|Too many attempts. Try again in {n} minutes. | canonical — A limit was reached · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_21` | Data melebihi batas satu ekspor. Persempit periode, lalu minta lagi. | The data exceeds the limit of one export. Narrow the period, then request it again. | canonical — Export above the row bound · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_22` | Dua ekspor Anda masih diproses. Tunggu salah satunya selesai. | Two of your exports are still being processed. Wait for one of them to finish. | canonical — A third export · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_23` | Daftar dipotong. Persempit kata kunci. | The list was cut. Narrow the search term. | canonical — A search list was cut · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_24` | Ketik minimal 3 huruf atau angka untuk mencari nama. Kode dicari persis. | Type at least 3 letters or digits to search by name. Codes are matched exactly. | canonical — A term too short for name search · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_25` | Kode {kode} tidak dikenal. | Code {kode} is not recognized. | canonical — An unknown code · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_26` | Daftar tidak dapat dilanjutkan. Muat ulang daftar. | The list cannot be continued. Reload the list. | canonical — A list cannot continue · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_27` | Konfirmasi kata sandi untuk melanjutkan. Diminta kembali setelah 15 menit. | Confirm your password to continue. It is asked again after 15 minutes. | canonical — Step-up · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_28` | Tautan tidak berlaku. Minta tautan baru kepada Owner. | This link is not valid. Ask the Owner for a new link. | canonical — A credential link that cannot be used · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_29` | Tautan atur kata sandi untuk akun ini dibuat pada {waktu} oleh {penerbit}. | A password-setting link for this account was created at {waktu} by {penerbit}. | canonical — Notice after a credential event · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_30` | Stok di lingkup ini berubah setelah hitungan dimulai. Hitungan ditandai kedaluwarsa — lakukan hitung ulang. | Stock in this scope changed after the count started. The count is marked stale — count again. | canonical — A stale count · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_31` | Berkas tidak dapat diunggah. Jenis yang diterima: {jenis}; ukuran maksimal {ukuran}. | The file cannot be uploaded. Accepted types: {jenis}; maximum size {ukuran}. | canonical — An upload refused · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_32` | Berkas ini hanya menampilkan kondisi fisik stok bersama, bukan dokumen milik suatu perusahaan. | This file only shows the physical condition of the shared stock, not a document belonging to a company. | canonical — The pool-evidence confirmation · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_33` | Sesi akan berakhir dalam {n} menit karena tidak ada aktivitas. | The session will end in {n} minute because of inactivity.\|The session will end in {n} minutes because of inactivity. | canonical — Idle warning · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_34` | Sesi berakhir pukul {jam} dan tidak dapat diperpanjang. Kirim isian Anda sebelum itu; isian yang belum dikirim akan hilang. | The session ends at {jam} and cannot be extended. Send your input before then; input not yet sent will be lost. | canonical — The absolute limit ahead · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_35` | Isian yang belum dikirim akan hilang. Tetap {keluar/tinggalkan halaman}? | Unsent input will be lost. {keluar/tinggalkan halaman} anyway? | canonical — Leaving with unsent input · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_36` | Nomor seri {seri} sudah dipindai. | Serial number {seri} has already been scanned. | canonical — A scan that does not fit · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_37` | Kode {kode} cocok dengan lebih dari satu barang. Pilih yang dimaksud. | Code {kode} matches more than one item. Choose the one you mean. | canonical — An ambiguous code · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_38` | Akun Anda belum memiliki akses perusahaan. Hubungi Owner. | Your account has no company access yet. Contact the Owner. | canonical — An account without any company grant · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_39` | {Kata kerja} {objek}? {Akibat yang tidak dapat diubah}. {Cara mengoreksi} | {Kata kerja} {objek}? {Akibat yang tidak dapat diubah}. {Cara mengoreksi} | canonical — Confirming an irreversible command · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_40` | Alasan (wajib) — tercatat di riwayat dan terlihat oleh Owner. | Reason (required) — recorded in the history and visible to the Owner. | canonical — The reason field · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_41` | Selisih {jumlah} {satuan} menunggu penetapan Owner. Stok fisik sudah disesuaikan; pekerjaan gudang tidak terhambat. | A discrepancy of {jumlah} {satuan} awaits the Owner's attribution. Stock on hand is already adjusted; warehouse work is not held up. | canonical — A pending unattributed quantity · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_01_draft` | Draf invoice tersimpan. | Invoice draft saved. | validated | S2-J03 §8 |
| `messages.msg_01_invoice` | Invoice diterbitkan dengan nomor ARJ/INV/2026/0141. | Invoice issued with number ARJ/INV/2026/0141. | canonical · en validated | S2-J03 §8 |
| `messages.msg_01_ntb` | Nota Terima Barang diterbitkan dengan nomor ARJ/NTB/2026/0057. | Nota Terima Barang issued with number ARJ/NTB/2026/0057. | canonical · en validated | S2-J05 §8 |
| `messages.msg_01_payment` | Pembayaran Rp300.000 tercatat. Rp300.000 diterapkan ke ARJ/INV/2026/0128; sisa piutang Rp700.000. | Payment of Rp300.000 recorded. Rp300.000 applied to ARJ/INV/2026/0128; Rp700.000 outstanding. | canonical · validated | S2-J06 §8 |
| `messages.msg_01_payment_flagged` | Pembayaran Rp300.000 tercatat tanpa bukti dan ditandai sampai buktinya diunggah. | Payment of Rp300.000 recorded without proof and flagged until its proof is uploaded. | validated | S2-J06 §8 |
| `messages.msg_01_po` | PO diterbitkan dengan nomor ARJ/PB/2026/0216. | PO issued with number ARJ/PB/2026/0216. | canonical · en validated | S2-J04 §8 |
| `messages.msg_01_purchase` | Pembelian dari PT Contoh Komputindo tercatat. Belum ada uang keluar dan belum ada barang masuk. | The purchase from PT Contoh Komputindo is recorded. No money has gone out and no goods have arrived. | validated | S2-J04 §8 |
| `messages.msg_01_receipt` | Barang masuk tercatat: 18 unit layak pakai di A-02-03, 1 unit rusak diterima, 1 unit ditolak. 15 unit direservasi untuk ARJ/PRJ/2026/0147. | Goods received recorded: 18 unit usable at A-02-03, 1 unit damaged and accepted, 1 unit rejected. 15 unit reserved for ARJ/PRJ/2026/0147. | validated | S2-J05 §8 |
| `messages.msg_01_receipt_0213` | Barang masuk tercatat: 2 unit layak pakai di B-02-01. | Goods received recorded: 2 unit usable at B-02-01. | validated | S2-J05 §8 |
| `messages.msg_01_receipt_l2` | Barang masuk tercatat: 20 unit layak pakai di A-03-01. | Goods received recorded: 20 unit usable at A-03-01. | validated | S2-J05 §8 |
| `messages.msg_01_receipt_l3` | Barang masuk tercatat: 20 unit layak pakai di A-03-02. | Goods received recorded: 20 unit usable at A-03-02. | validated | S2-J05 §8 |
| `messages.msg_01_receipt_l4` | Barang masuk tercatat: 2 unit layak pakai di B-01-01. | Goods received recorded: 2 unit usable at B-01-01. | validated | S2-J05 §8 |
| `messages.msg_01_receipt_noreserve` | Barang masuk tercatat: 18 unit layak pakai di A-02-03, 1 unit rusak diterima, 1 unit ditolak. | Goods received recorded: 18 unit usable at A-02-03, 1 unit damaged and accepted, 1 unit rejected. | validated | S2-J05 §8 |
| `messages.msg_01_receipt_noreserve_tpl` | Barang masuk tercatat: {usable} unit layak pakai di {rack}, {damaged} unit rusak diterima, {rejected} unit ditolak. | Goods received recorded: {usable} unit usable at {rack}, {damaged} unit damaged and accepted, {rejected} unit rejected. | session copy · validated | the pilot's copy |
| `messages.msg_01_receipt_tpl` | Barang masuk tercatat: {usable} unit layak pakai di {rack}, {damaged} unit rusak diterima, {rejected} unit ditolak. {reserve} unit direservasi untuk {project}. | Goods received recorded: {usable} unit usable at {rack}, {damaged} unit damaged and accepted, {rejected} unit rejected. {reserve} unit reserved for {project}. | session copy · validated | the pilot's copy |
| `messages.msg_02_above_outstanding` | Tidak dapat diproses: penerapan melebihi sisa piutang. Sisa ARJ/INV/2026/0133 Rp250.000, diterapkan Rp300.000. Kurangi jumlah penerapan. | Cannot be processed: the application exceeds the outstanding amount. ARJ/INV/2026/0133 has Rp250.000 outstanding, Rp300.000 applied. Reduce the amount applied. | validated | S2-J06 §8 |
| `messages.msg_02_above_payment` | Tidak dapat diproses: jumlah penerapan melebihi pembayaran. Pembayaran Rp300.000, diterapkan Rp550.000. Kurangi penerapan atau biarkan sisanya sebagai kredit pelanggan. | Cannot be processed: the applications exceed the payment. Payment Rp300.000, applied Rp550.000. Reduce the applications or leave the rest as customer credit. | validated | S2-J06 §8 |
| `messages.msg_05_pdf` | PDF sedang dibuat. Invoice sudah terbit. | The PDF is being created. The invoice is issued. | canonical · recorded · en validated | S2-J03 §8 |
| `messages.msg_05_po` | PDF sedang dibuat. PO sudah terbit. | The PDF is being created. The PO is issued. | canonical · en validated | S2-J04 §8 |
| `messages.msg_29_owner` | Tautan atur kata sandi yang Anda buat untuk Bayu dipakai pada 04 Okt 2026 16.20 WIB. Jika bukan orang tersebut yang memakainya, akhiri sesinya di Pengguna & Akses. | The password-setting link you created for Bayu was used on 04 Okt 2026 16.20 WIB. If it was not that person, end the session in Users and access. | canonical · en validated | S2-J02 §8 |
| `messages.msg_29_owner_tpl` | Tautan atur kata sandi yang Anda buat untuk {v} dipakai pada {w} WIB. Jika bukan orang tersebut yang memakainya, akhiri sesinya di Pengguna & Akses. | The password-setting link you created for {v} was used on {w} WIB. If it was not that person, end the session in Users and access. | session copy · validated | the pilot's copy |
| `messages.msg_35_leave` | Isian yang belum dikirim akan hilang. Tetap tinggalkan halaman? | Unsent input will be lost. Leave the page anyway? | canonical · en validated | S2-J04 §8 |
| `messages.msg_35_logout` | Isian yang belum dikirim akan hilang. Tetap keluar? | Unsent input will be lost. Sign out anyway? | canonical · en validated | S2-J01 §8 |
| `messages.msg_39_invoice` | Terbitkan Invoice? Nomor akan ditetapkan dan tidak dapat diubah. Kekeliruan hanya dapat diperbaiki dengan revisi atau pembatalan dokumen. | Issue the invoice? The number will be assigned and cannot change. A mistake can only be corrected by revising or voiding the document. | canonical · en validated | S2-J03 §8 |
| `messages.msg_39_ntb` | Terbitkan Nota Terima Barang? Nomor akan ditetapkan dan tidak dapat diubah. Kekeliruan hanya dapat diperbaiki dengan revisi atau pembatalan dokumen. | Issue the Nota Terima Barang? The number will be assigned and cannot change. A mistake can only be corrected by revising or voiding the document. | canonical · en validated | S2-J05 §8 |
| `messages.msg_39_payment` | Catat pembayaran Rp300.000? Kas masuk akan tercatat dan diterapkan ke invoice yang dipilih. Kekeliruan hanya dapat diperbaiki dengan koreksi pembayaran. | Record the payment of Rp300.000? Cash in will be recorded and applied to the selected invoice. A mistake can only be corrected by a payment correction. | validated | S2-J06 §8 |
| `messages.msg_39_po` | Terbitkan PO? Nomor akan ditetapkan dan tidak dapat diubah. Kekeliruan hanya dapat diperbaiki dengan revisi atau pembatalan dokumen. | Issue the PO? The number will be assigned and cannot change. A mistake can only be corrected by revising or voiding the document. | canonical · en validated | S2-J04 §8 |
| `messages.msg_39_receipt` | Catat barang masuk? 18 unit layak pakai di A-02-03, 1 unit rusak diterima, 1 unit ditolak; 15 unit direservasi untuk ARJ/PRJ/2026/0147. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 18 unit usable at A-02-03, 1 unit damaged and accepted, 1 unit rejected; 15 unit reserved for ARJ/PRJ/2026/0147. A mistake can only be corrected by a receiving correction. | validated | S2-J05 §8 |
| `messages.msg_39_receipt_0213` | Catat barang masuk? 2 unit layak pakai di B-02-01. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 2 unit usable at B-02-01. A mistake can only be corrected by a receiving correction. | validated | S2-J05 §8 |
| `messages.msg_39_receipt_l2` | Catat barang masuk? 20 unit layak pakai di A-03-01. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 20 unit usable at A-03-01. A mistake can only be corrected by a receiving correction. | validated | S2-J05 §8 |
| `messages.msg_39_receipt_l3` | Catat barang masuk? 20 unit layak pakai di A-03-02. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 20 unit usable at A-03-02. A mistake can only be corrected by a receiving correction. | validated | S2-J05 §8 |
| `messages.msg_39_receipt_l4` | Catat barang masuk? 2 unit layak pakai di B-01-01. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 2 unit usable at B-01-01. A mistake can only be corrected by a receiving correction. | validated | S2-J05 §8 |
| `messages.msg_39_receipt_noreserve` | Catat barang masuk? 18 unit layak pakai di A-02-03, 1 unit rusak diterima, 1 unit ditolak. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 18 unit usable at A-02-03, 1 unit damaged and accepted, 1 unit rejected. A mistake can only be corrected by a receiving correction. | validated | S2-J05 §8 |
| `messages.msg_39_receipt_noreserve_tpl` | Catat barang masuk? {usable} unit layak pakai di {rack}, {damaged} unit rusak diterima, {rejected} unit ditolak. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? {usable} unit usable at {rack}, {damaged} unit damaged and accepted, {rejected} unit rejected. A mistake can only be corrected by a receiving correction. | session copy · validated | the pilot's copy |
| `messages.msg_39_receipt_tpl` | Catat barang masuk? {usable} unit layak pakai di {rack}, {damaged} unit rusak diterima, {rejected} unit ditolak; {reserve} unit direservasi untuk {project}. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? {usable} unit usable at {rack}, {damaged} unit damaged and accepted, {rejected} unit rejected; {reserve} unit reserved for {project}. A mistake can only be corrected by a receiving correction. | session copy · validated | the pilot's copy |
| `messages.no_receivable_yet` | Belum ada piutang aktif dan belum ada kas yang tercatat. | No receivable is active and no cash is recorded yet. | validated | S2-J03 §8 |
| `messages.msg_06_defect` | Terjadi kesalahan sistem; tindakan ini belum diproses. Kode rujukan {kode} — laporkan bila terulang. | A system error occurred; this action has not been processed. Reference code {kode} — report it if it happens again. | canonical — FAILED — a defect · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_08_exhausted` | {Jenis} sudah terbit dengan nomor {nomor}. PDF gagal dibuat setelah 5 percobaan; operator sudah diberi tahu. | {Jenis} is issued with number {nomor}. The PDF could not be created after 5 attempts; the operator has been notified. | canonical — A failed rendition, after the fifth attempt · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_14_allocation` | Tindakan ini memerlukan kewenangan alokasi antar-perusahaan. Tidak diproses, dan diteruskan: Owner atau admin yang berwenang akan melihatnya. | This action needs the inter-company allocation authority. It was not processed and has been passed on: the Owner or an authorized admin will see it. | canonical — Routed refusal, without the allocation authority · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_15_no_authority` | Kemungkinan catatan ganda. {daftar catatan yang cocok}. Periksa catatan di atas. Jika ini memang {catatan} yang berbeda, minta admin berwenang atau Owner untuk mencatatnya. | Possible duplicate record. {daftar catatan yang cocok}. Check the records above. If this really is a different {catatan}, ask an authorized admin or the Owner to record it. | canonical — Duplicate warning, without the authority · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_29_used` | Kata sandi akun ini diatur pada {waktu} melalui tautan yang dibuat {penerbit}. | This account's password was set at {waktu} through a link created by {penerbit}. | canonical — Notice after a credential event, link used · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_29_changed` | Kata sandi akun ini diubah pada {waktu}. | This account's password was changed at {waktu}. | canonical — Notice after a credential event, password changed · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_29_not_you` | Jika bukan Anda, segera beri tahu Owner. | If this was not you, tell the Owner at once. | canonical — Notice after a credential event, its closing sentence · en validated (PC-3) | ADMIN_FLOW §14 |
| `messages.msg_41_company` | Paparan perusahaan ini pada selisih yang menunggu penetapan Owner: {jumlah} {satuan}. Belum menjadi biaya atau kerugian. | This company's exposure to the discrepancy awaiting the Owner's attribution: {jumlah} {satuan}. Not yet a cost or a loss. | canonical — A pending unattributed quantity, in a company's views · en validated (PC-3) | ADMIN_FLOW §14 |

### 6.2 `glossary` — the terms used as labels

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `glossary.gl_001` | Beranda | Home | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Dashboard (home) |
| `glossary.gl_002` | Antrean Tindakan | Action queue | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Admin action queue |
| `glossary.gl_003` | Tinjauan Owner | Owner review | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Owner review queue (QS-14) |
| `glossary.gl_004` | Proyek | Projects | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Project |
| `glossary.gl_005` | Penawaran | Quotation | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Quotation |
| `glossary.gl_006` | Pembelian | Purchases | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Purchase |
| `glossary.gl_007` | Gudang | Warehouse | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Warehouse |
| `glossary.gl_008` | Stok | Stock | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Stock (availability view) |
| `glossary.gl_009` | Barang Masuk | Goods receiving | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Receiving |
| `glossary.gl_010` | Barang Keluar | Goods dispatch | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Dispatch |
| `glossary.gl_011` | Riwayat Stok | Stock history | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Stock movement history |
| `glossary.gl_012` | Stok Opname | Stock count | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Stock opname (count) |
| `glossary.gl_013` | Pengiriman | Deliveries | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Delivery |
| `glossary.gl_014` | Dokumen | Documents | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Documents |
| `glossary.gl_015` | Dokumen Administrasi | Administrative documents | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Administrative requirements |
| `glossary.gl_016` | Keuangan | Finance | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Finance area |
| `glossary.gl_017` | Piutang | Receivables | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Receivables |
| `glossary.gl_018` | Pembayaran | Payments | canonical · en validated | INFORMATION_ARCHITECTURE §3 — Payment (received) |
| `glossary.gl_019` | Pengeluaran & Kas Lain | Disbursements and other cash | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Disbursements, expenses and other cash |
| `glossary.gl_020` | Laporan | Reports | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Reports |
| `glossary.gl_021` | Data Master | Master data | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Master data |
| `glossary.gl_022` | Pengaturan | Settings | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Settings |
| `glossary.gl_023` | Cari | Search | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Search |
| `glossary.gl_024` | Pindai | Scan | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Scan |
| `glossary.gl_025` | Riwayat Aktivitas | Activity history | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Activity history (audit search) |
| `glossary.gl_026` | Riwayat | History | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Record timeline |
| `glossary.gl_027` | Log Keamanan | Security log | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Security log |
| `glossary.gl_028` | Ekspor Saya | My exports | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Own exports |
| `glossary.gl_029` | Akun Saya | My account | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Account page |
| `glossary.gl_030` | Lainnya | More | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — More (phone navigation) |
| `glossary.gl_031` | Owner | Owner | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Owner role |
| `glossary.gl_032` | Admin Operasional | Admin Operasional | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Admin Operasional role |
| `glossary.gl_033` | Perusahaan | Company | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Legal company |
| `glossary.gl_034` | Akses Perusahaan | Company access | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Company grant |
| `glossary.gl_035` | Kewenangan | Capability | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Capability |
| `glossary.gl_036` | Kewenangan khusus | Special capability | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — ADM+ capability |
| `glossary.gl_037` | Khusus Owner | Owner only | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Owner-only |
| `glossary.gl_038` | perusahaan lain | another company | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Relation marker |
| `glossary.gl_039` | Tampilkan perusahaan | Show companies | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Working company filter |
| `glossary.gl_040` | Semua perusahaan | All companies | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — All companies (filter value) |
| `glossary.gl_041` | Gabungan | Consolidated | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Consolidated (Owner figures) |
| `glossary.gl_042` | Sesi | Session | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Session |
| `glossary.gl_043` | Masuk / Keluar | Sign in / sign out | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Sign in / sign out |
| `glossary.gl_044` | Kata sandi | Password | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Password |
| `glossary.gl_045` | Tautan atur kata sandi | Password-setting link | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Credential link |
| `glossary.gl_046` | Konfirmasi kata sandi | Password confirmation | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Step-up confirmation |
| `glossary.gl_047` | Nonaktifkan | Deactivate | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Deactivate (account, company) |
| `glossary.gl_048` | Klien | Client | canonical · en validated | INFORMATION_ARCHITECTURE §3 — Client organization |
| `glossary.gl_049` | Unit | Unit | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Client unit |
| `glossary.gl_050` | PIC | PIC | canonical · en validated | INFORMATION_ARCHITECTURE §3 — Person in charge |
| `glossary.gl_051` | Pemasok | Supplier | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Supplier |
| `glossary.gl_052` | Produk / Jasa | Product / service | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Product / service |
| `glossary.gl_053` | SKU | SKU | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — SKU |
| `glossary.gl_054` | Barcode | Barcode | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Barcode |
| `glossary.gl_055` | Nomor seri | Serial number | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Serial number |
| `glossary.gl_056` | Satuan dasar / Satuan lain | Base unit / alternate unit | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Base unit / alternate unit |
| `glossary.gl_057` | Lokasi rak | Rack location | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Rack / location |
| `glossary.gl_058` | Arsipkan | Archive | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Archive (master) |
| `glossary.gl_059` | Item proyek | Project item | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Project item (demand line) |
| `glossary.gl_060` | Kanal: Reguler / SIPLAH | Channel: Regular / SIPLAH | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Channel REGULAR / SIPLAH |
| `glossary.gl_061` | Konfirmasi Proyek | Project confirmation | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Commercial confirmation |
| `glossary.gl_062` | Disetujui klien / Ditolak klien | Approved by client / rejected by client | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Client approval / rejection of a quotation |
| `glossary.gl_063` | Cara pemenuhan: Gudang / Kirim langsung / Jasa | Fulfilment: warehouse / direct shipment / service | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Fulfilment mode: WAREHOUSE / DROP-SHIP / SERVICE |
| `glossary.gl_064` | Kekurangan stok | Stock shortage | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Shortage |
| `glossary.gl_065` | Biaya pembelian | Purchase charge | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Purchase charge |
| `glossary.gl_066` | Tagihan sumber | Source bill | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Source bill of a charge |
| `glossary.gl_067` | Tutup Sisa Pembelian | Close purchase remainder | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Purchase-remainder closure |
| `glossary.gl_068` | Pembelian susulan | Supplementary purchase | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Supplementary purchase |
| `glossary.gl_069` | Batalkan sisa | Cancel the remainder | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Remaining-scope cancellation |
| `glossary.gl_070` | Penyelesaian Proyek | Project completion | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Project completion |
| `glossary.gl_071` | Syarat penyelesaian | Completion conditions | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Completion conditions (gate) |
| `glossary.gl_072` | Selesaikan Paksa | Force complete | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Force Complete |
| `glossary.gl_073` | Kewajiban tersisa | Remaining obligations | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Residual obligations |
| `glossary.gl_074` | Batalkan Proyek | Cancel project | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Project cancellation |
| `glossary.gl_075` | Tenggat | Deadline | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Deadline |
| `glossary.gl_076` | Stok fisik | On hand | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — ON HAND |
| `glossary.gl_077` | Direservasi | Reserved | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — RESERVED |
| `glossary.gl_078` | Tidak layak pakai | Unusable | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — UNUSABLE |
| `glossary.gl_079` | Tersedia | Available | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — AVAILABLE |
| `glossary.gl_080` | Reservasi | Reservation | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Reservation |
| `glossary.gl_081` | Lot | Lot | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Receiving lot |
| `glossary.gl_082` | Ambil lebih dulu | Pick first | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Oldest-first picking aid |
| `glossary.gl_083` | Layak pakai / Rusak diterima / Ditolak | Usable / damaged and accepted / rejected | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Receiving outcomes |
| `glossary.gl_084` | Ubah kondisi | Change condition | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Condition change |
| `glossary.gl_085` | Karantina | Quarantine | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Quarantine location |
| `glossary.gl_086` | Penyesuaian Stok | Stock adjustment | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Adjustment (decrease) |
| `glossary.gl_087` | Pemusnahan | Disposal | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Disposal |
| `glossary.gl_088` | Kerugian persediaan | Inventory loss | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Inventory loss |
| `glossary.gl_089` | Selisih menunggu penetapan | Discrepancy awaiting attribution | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Pending unattributed loss case |
| `glossary.gl_090` | Temuan lebih | Surplus finding | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Count surplus finding |
| `glossary.gl_091` | Tetapkan perusahaan | Attribute to a company | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Owner attribution |
| `glossary.gl_092` | Hitungan kedaluwarsa | Stale count | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Stale count |
| `glossary.gl_093` | Alokasi antar-perusahaan | Inter-company allocation | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Inter-company allocation |
| `glossary.gl_094` | Pindah lokasi | Move location | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Location move |
| `glossary.gl_095` | Stok minimum / Saran isi ulang | Minimum stock / restock advice | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Minimum stock / restock advice |
| `glossary.gl_096` | Retur ke pemasok / Retur dari klien | Supplier return / client return | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Purchase return / sales return |
| `glossary.gl_097` | Konfirmasi kirim langsung | Direct shipment confirmation | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Drop-ship confirmation |
| `glossary.gl_098` | Serah Terima Jasa | Service handover | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Service handover |
| `glossary.gl_099` | Penerima / Bukti kirim | Recipient / delivery proof | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Recipient / delivery proof |
| `glossary.gl_100` | Selisih pengiriman | Delivery discrepancy | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Delivery discrepancy |
| `glossary.gl_101` | Tutup Pengiriman | Close delivery | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Delivery closure |
| `glossary.gl_102` | Kasus koreksi | Correction case | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Correction case |
| `glossary.gl_103` | Penawaran | Penawaran | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-01 |
| `glossary.gl_104` | Purchase Order / PO | Purchase Order / PO | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-02 |
| `glossary.gl_105` | Surat Jalan / Faktur Pengiriman | Surat Jalan / Faktur Pengiriman | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-03 |
| `glossary.gl_106` | Nota | Nota | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-04 |
| `glossary.gl_107` | Kuitansi | Kuitansi | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-05 |
| `glossary.gl_108` | Invoice | Invoice | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-06 |
| `glossary.gl_109` | Lampiran Kuitansi | Lampiran Kuitansi | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-07 |
| `glossary.gl_110` | BAST | BAST | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-08 |
| `glossary.gl_111` | SPK | SPK | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-09 |
| `glossary.gl_112` | Berita Acara Pemeriksaan Barang | Berita Acara Pemeriksaan Barang | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-10 |
| `glossary.gl_113` | HPS | HPS | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-11 |
| `glossary.gl_114` | Nota Terima Barang | Nota Terima Barang | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-12 |
| `glossary.gl_115` | Surat Permintaan Pembayaran | Surat Permintaan Pembayaran | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-13 |
| `glossary.gl_116` | Surat Pesanan | Surat Pesanan | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — DOC-14 |
| `glossary.gl_117` | Draf | Draft | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Draft |
| `glossary.gl_118` | Terbitkan | Issue | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Issue (a document) |
| `glossary.gl_119` | Revisi | Revision | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Revision |
| `glossary.gl_120` | Batalkan Dokumen | Void document | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Void |
| `glossary.gl_121` | PDF sedang dibuat / PDF siap / PDF gagal dibuat | PDF being created / PDF ready / PDF failed | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Rendition states |
| `glossary.gl_122` | Bukti | Evidence | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Evidence |
| `glossary.gl_123` | Unggah | Upload | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Upload |
| `glossary.gl_124` | Asli dari klien | Client original | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Client original |
| `glossary.gl_125` | Bukti gudang bersama | Shared warehouse evidence | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Pool evidence |
| `glossary.gl_126` | Wajib / Opsional | Required / optional | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Requirement: required / optional |
| `glossary.gl_127` | Belum ada / Disiapkan / Terpenuhi / Tidak berlaku | Missing / prepared / satisfied / not applicable | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Requirement status |
| `glossary.gl_128` | Tidak Berlaku | Not applicable | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — N/A waiver |
| `glossary.gl_129` | Boleh ditandai tidak berlaku | May be marked not applicable | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Waiver-eligible |
| `glossary.gl_130` | Nilai penjualan | Sales value | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Sales / transaction value |
| `glossary.gl_131` | Belum Ditagihkan | Not yet billed | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Issued, not billed |
| `glossary.gl_132` | Tagihkan | Bill | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Billing act |
| `glossary.gl_133` | Tanggal tagih / Jatuh tempo | Billing date / due date | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Billing date / due date |
| `glossary.gl_134` | Piutang aktif | Active receivables | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Active receivable |
| `glossary.gl_135` | Sisa tagihan | Outstanding bill | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Outstanding of an invoice |
| `glossary.gl_136` | Umur piutang | Receivable ageing | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Aging |
| `glossary.gl_137` | Lewat jatuh tempo | Overdue | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Overdue |
| `glossary.gl_138` | Lunas | Paid in full | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Paid in full |
| `glossary.gl_139` | Kas masuk | Cash in | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Cash-In |
| `glossary.gl_140` | Penerapan pembayaran | Payment application | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Payment application |
| `glossary.gl_141` | Kredit Pelanggan | Customer credit | canonical · en validated | INFORMATION_ARCHITECTURE §3 — Customer credit |
| `glossary.gl_142` | Pengembalian dana | Refund | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Refund |
| `glossary.gl_143` | Potongan fee / Pajak dipotong atau dipungut | Fee deduction / tax withheld or collected | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Fee settlement / tax settlement |
| `glossary.gl_144` | Potongan diklaim | Claimed deduction | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Claimed deduction |
| `glossary.gl_145` | Sengketa | Disputed | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Dispute hold |
| `glossary.gl_146` | Hapuskan Piutang | Write off receivable | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Write-off |
| `glossary.gl_147` | Pengeluaran kas / Kas keluar | Disbursement / cash out | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Disbursement / Cash-Out |
| `glossary.gl_148` | Beban | Expense | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Expense |
| `glossary.gl_149` | Kas masuk lain | Other cash in | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Other Cash-In |
| `glossary.gl_150` | Mutasi bank | Bank statement | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Bank statement line |
| `glossary.gl_151` | Biaya / HPP | Cost / HPP | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Cost / HPP |
| `glossary.gl_152` | Laba / Margin | Profit / margin | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Profit / margin |
| `glossary.gl_153` | Arus kas | Cash flow | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Managerial cashflow |
| `glossary.gl_154` | Menunggu pembayaran | Awaiting payment | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Pre-payment Kuitansi state |
| `glossary.gl_155` | Kontra | Contra | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Contra-fact |
| `glossary.gl_156` | Pindahkan penerapan | Move application | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Reallocation |
| `glossary.gl_157` | Simpan | Save | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Save (draft, master) |
| `glossary.gl_158` | Catat | Record | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Record (a fact) |
| `glossary.gl_159` | Tersimpan / Tercatat / Terbit | Saved / recorded / issued | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — COMMITTED |
| `glossary.gl_160` | Tidak dapat diproses | Cannot be processed | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — REJECTED |
| `glossary.gl_161` | Data sudah berubah · Sudah tercatat | Data has changed · Already recorded | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — CONFLICT |
| `glossary.gl_162` | Sedang diproses | Processing | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — PENDING |
| `glossary.gl_163` | Belum dapat dipastikan · Belum diproses | Not yet confirmed · Not yet processed | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — FAILED |
| `glossary.gl_164` | Coba Lagi | Try again | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Retry |
| `glossary.gl_165` | Terapkan ulang | Re-apply | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Re-apply |
| `glossary.gl_166` | Alasan | Reason | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Reason |
| `glossary.gl_167` | Tanggal transaksi | Transaction date | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Business date |
| `glossary.gl_168` | Dicatat | Recorded | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Recorded at |
| `glossary.gl_169` | Tanggal mundur | Backdated | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Backdated |
| `glossary.gl_170` | Koreksi / Pembalikan | Correction / reversal | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Correction / reversal |
| `glossary.gl_171` | Muat lagi | Load more | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Load more |
| `glossary.gl_172` | Filter / Urutkan | Filter / sort | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Filter / sort |
| `glossary.gl_173` | Ekspor | Export | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Export |
| `glossary.gl_174` | Unduh / Cetak | Download / print | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Download / print |
| `glossary.gl_175` | Halaman tidak ditemukan | Page not found | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Not found |
| `glossary.gl_176` | Format nomor | Number format | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Numbering scheme |
| `glossary.gl_177` | Mulai Penomoran | Start numbering | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Start of numbering |
| `glossary.gl_178` | Nomor awal | Starting number | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Seed |
| `glossary.gl_179` | Batas awal penomoran | Numbering start boundary | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Seed boundary |
| `glossary.gl_180` | Go-live | Go-live | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Go-live |
| `glossary.gl_181` | Impor Data Awal | Opening import | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Opening import |
| `glossary.gl_182` | Disiapkan / Uji coba lolos / Disetujui bersama / Selesai diimpor / Dibatalkan | Prepared / dry run passed / signed off jointly / imported / cancelled | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Import batch states |
| `glossary.gl_183` | Uji coba impor | Import dry run | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Dry run |
| `glossary.gl_184` | Tanggal batas | Cutoff date | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Cutoff date |
| `glossary.gl_185` | Pintasan | Shortcuts | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Shortcut help |
| `glossary.gl_186` | Kode rujukan | Reference code | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Reference code (correlation id) |
| `glossary.gl_187` | Tindakan Lain | More actions | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Overflow menu of page actions |
| `glossary.gl_188` | Bagian Lain | More sections | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Overflow menu of a record's parts |
| `glossary.gl_189` | Pengadaan | Procurement | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — A project's purchasing part |
| `glossary.gl_190` | Urungkan | Undo | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Undo a line removal |
| `glossary.gl_191` | Pratinjau Akses | Access preview | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Access preview |
| `glossary.gl_192` | Pekerjaan | Work | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Work home — work grouped by what has to happen next |
| `glossary.gl_193` | Tinjauan | Review | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — The Owner's review entry |
| `glossary.gl_194` | Menu lengkap | Full menu | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Full menu |
| `glossary.gl_195` | Kerja | Work areas | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Work section of the phone's *Lainnya* |
| `glossary.gl_196` | Buat | Create | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Create menu |
| `glossary.gl_197` | Cari atau pindai kode | Search or scan a code | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Search-and-scan field |
| `glossary.gl_198` | Bahasa tampilan | Display language | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Display language |
| `glossary.gl_199` | Perjalanan Proyek | Project journey | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Project journey |
| `glossary.gl_200` | Tahap proyek | Project stages | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Project stages |
| `glossary.gl_201` | Sekarang · Berikutnya · Hambatan | Now · Next · Blockers | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Now, next and blockers |
| `glossary.gl_202` | Kesiapan dokumen | Document readiness | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Document readiness |
| `glossary.gl_203` | Rincian proyek | Project details | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Project details |
| `glossary.gl_204` | Yang tersisa sebelum selesai | What remains before completion | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Remaining before completion |
| `glossary.gl_205` | Posisi keuangan | Financial position | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Financial position |
| `glossary.gl_206` | Proyek yang perlu perhatian | Projects needing attention | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Projects needing attention |
| `glossary.gl_207` | Perlu keputusan Anda | Needs your decision | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Needs your decision |
| `glossary.gl_208` | Antrean operasional | Operational queue | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Operational queue |
| `glossary.gl_209` | Tampilkan | Show | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Show — the company filter |
| `glossary.gl_210` | Lihat Semua | See all | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — See all |
| `glossary.gl_211` | Proyek melewati tenggat | Project past its deadline | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Overdue project |
| `glossary.gl_212` | Siap dikeluarkan | Ready to dispatch | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §3 — Dispatch-ready |
| `glossary.receivables` | Piutang | Receivables | canonical · en validated | S2-J02 §8 |

### 6.3 `status` — the state labels

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `status.project.draft` | Draf | Draft | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — DRAFT |
| `status.project.active` | Aktif | Active | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — ACTIVE |
| `status.project.completed_normal` | Selesai | Completed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — COMPLETED_NORMAL |
| `status.project.completed_forced` | Selesai (Paksa) | Completed (forced) | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — COMPLETED_FORCED |
| `status.project.cancelled` | Dibatalkan | Cancelled | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — CANCELLED |
| `status.quotation_revision.draft` | Draf | Draft | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — DRAFT |
| `status.quotation_revision.sent` | Terkirim | Sent | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — SENT |
| `status.quotation_revision.approved` | Disetujui Klien | Approved by client | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — APPROVED |
| `status.quotation_revision.rejected` | Ditolak Klien | Rejected by client | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — REJECTED |
| `status.quotation_revision.sent_past_validity` | Kedaluwarsa | Expired | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — SENT past validity (L-25) |
| `status.document_version.draft` | Draf | Draft | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — DRAFT |
| `status.document_version.issued` | Terbit | Issued | canonical · en validated | DESIGN_SYSTEM §12.1 — ISSUED |
| `status.document_version.superseded` | Direvisi | Revised | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — SUPERSEDED |
| `status.document_version.voided` | Dibatalkan | Voided | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — VOIDED |
| `status.rendition.pending_rendering` | PDF Sedang Dibuat | PDF being created | canonical · en validated | DESIGN_SYSTEM §12.1 — PENDING, RENDERING |
| `status.rendition.ready` | PDF Siap | PDF ready | canonical · en validated | DESIGN_SYSTEM §12.1 — READY |
| `status.rendition.failed` | PDF Gagal Dibuat | PDF failed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — FAILED |
| `status.pre_payment_kuitansi.open_not_linked` | Menunggu Pembayaran | Awaiting payment | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — open, not linked |
| `status.pre_payment_kuitansi.linked` | Terhubung ke Pembayaran | Linked to payment | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — linked |
| `status.invoice_and_receivable.issued_not_billed` | Belum Ditagihkan | Not yet billed | canonical · en validated | DESIGN_SYSTEM §12.1 — issued, not billed |
| `status.invoice_and_receivable.billed_outstanding` | Ditagihkan | Billed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — billed, outstanding |
| `status.invoice_and_receivable.past_due_date` | Lewat Jatuh Tempo | Overdue | canonical · en validated | DESIGN_SYSTEM §12.1 — past due date |
| `status.invoice_and_receivable.outstanding_zero` | Lunas | Paid in full | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — outstanding zero |
| `status.invoice_and_receivable.dispute_hold` | Sengketa | Disputed | canonical · en validated | DESIGN_SYSTEM §12.1 — dispute hold |
| `status.invoice_and_receivable.written_off` | Dihapuskan | Written off | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — written off |
| `status.payment.without_proof` | Tanpa Bukti | No proof | canonical · en validated | DESIGN_SYSTEM §12.1 — without proof (L-29) |
| `status.payment.contra_d` | Dikontra | Contra'd | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — contra'd |
| `status.reservation.active` | Aktif | Active | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — ACTIVE |
| `status.reservation.released` | Dilepas | Released | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — RELEASED |
| `status.reservation.consumed` | Terpakai | Consumed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — CONSUMED |
| `status.purchase.open` | Terbuka | Open | canonical · en validated | DESIGN_SYSTEM §12.1 — OPEN |
| `status.purchase.closed` | Sisa Ditutup | Remainder closed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — CLOSED (remainder closed) |
| `status.purchase.cancelled` | Dibatalkan | Cancelled | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — CANCELLED |
| `status.unit_condition.usable` | Layak Pakai | Usable | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — usable |
| `status.unit_condition.unusable` | Tidak Layak Pakai | Unusable | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — UNUSABLE |
| `status.count.open` | Terbuka | Open | canonical · en validated | DESIGN_SYSTEM §12.1 — OPEN |
| `status.count.stale` | Kedaluwarsa | Stale | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — STALE (ST-03) |
| `status.count.applied` | Diterapkan | Applied | canonical · en validated | DESIGN_SYSTEM §12.1 — APPLIED |
| `status.count_finding.open_owner_to_attribute` | Menunggu Owner | Awaiting the Owner | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — open, Owner to attribute |
| `status.pending_unexplained_loss_case.pending` | Menunggu Penetapan | Awaiting attribution | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — pending |
| `status.pending_unexplained_loss_case.resolved` | Ditetapkan | Attributed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — resolved |
| `status.correction_case.open` | Terbuka | Open | canonical · en validated | DESIGN_SYSTEM §12.1 — OPEN |
| `status.correction_case.closed` | Ditutup | Closed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — CLOSED |
| `status.delivery.partly_delivered` | Terkirim Sebagian | Partly delivered | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — partly delivered |
| `status.delivery.fully_delivered` | Terkirim | Delivered | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — fully delivered |
| `status.delivery.with_a_discrepancy` | Ada Selisih | With a discrepancy | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — with a discrepancy |
| `status.delivery.closed` | Ditutup | Closed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — closed |
| `status.administrative_requirement.missing` | Belum Ada | Missing | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — missing |
| `status.administrative_requirement.draft_only` | Disiapkan | Prepared | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — draft only |
| `status.administrative_requirement.satisfied` | Terpenuhi | Satisfied | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — satisfied |
| `status.administrative_requirement.waived` | Tidak Berlaku | Not applicable | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — waived |
| `status.export.requested` | Diminta | Requested | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — requested |
| `status.export.running` | Diproses | Processing | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — running |
| `status.export.ready` | Siap Diunduh | Ready to download | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — READY |
| `status.export.failed` | Gagal | Failed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — FAILED |
| `status.export.withheld` | Tidak Tersedia | Not available | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — WITHHELD |
| `status.import_batch.prepared` | Disiapkan | Prepared | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — PREPARED |
| `status.import_batch.dry_run_passed` | Uji Coba Lolos | Dry run passed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — DRY_RUN_PASSED |
| `status.import_batch.signed_off` | Disetujui Bersama | Signed off jointly | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — SIGNED_OFF |
| `status.import_batch.committing` | Sedang Diimpor | Importing | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — committing |
| `status.import_batch.committed` | Selesai Diimpor | Imported | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — COMMITTED |
| `status.import_batch.stopped` | Terhenti | Stopped | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — stopped |
| `status.import_batch.aborted` | Dibatalkan | Cancelled | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — ABORTED |
| `status.account_and_company.active` | Aktif | Active | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — active |
| `status.account_and_company.inactive` | Nonaktif | Inactive | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — inactive |
| `status.product_stock.below_its_minimum` | Stok Menipis | Low stock | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — below its minimum |
| `status.readiness_item.complete` | Lengkap | Complete | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — complete |
| `status.readiness_item.not_complete` | Belum | Not yet | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 — not complete |
| `status.document.issued` | Terbit | Issued | canonical · en validated | S2-J03 §8 |
| `status.invoice.issued_unbilled` | Terbit — Belum Ditagihkan | Issued — not yet billed | recorded · en validated | S2-J03 §8 |
| `status.invoice.unbilled` | Belum Ditagihkan | Not yet billed | canonical · en validated | S2-J02 §8 |
| `status.part.done` | Tuntas | Done | session copy · validated | the pilot's pages; DESIGN_SYSTEM §12.1 — the part of a project |
| `status.part.in_progress` | Berjalan | In progress | session copy · validated | the pilot's pages; DESIGN_SYSTEM §12.1 — the part of a project |
| `status.part.not_needed` | Tidak Diperlukan | Not needed | session copy · validated | the pilot's pages; DESIGN_SYSTEM §12.1 — the part of a project |
| `status.part.not_started` | Belum Dimulai | Not started | session copy · validated | the pilot's pages; DESIGN_SYSTEM §12.1 — the part of a project |
| `status.payment.no_proof` | Tanpa Bukti | No proof | canonical · en validated | S2-J06 §8 |
| `status.receivable.disputed` | Sengketa | Disputed | canonical · en validated | S2-J02 §8 |
| `status.receivable.overdue` | Lewat Jatuh Tempo | Overdue | canonical · en validated | S2-J02 §8 |
| `status.rendition.pending` | PDF Sedang Dibuat | PDF being created | canonical · en validated | S2-J03 §8 |
| `status.task_line.not_recorded` | Belum Dicatat | Not yet recorded | canonical · en validated | S2-J05 §8 |
| `status.task_line.recorded` | Tercatat | Recorded | canonical · en validated | S2-J05 §8 |

### 6.4 `auth` — the sign-in answers and advice

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `auth.code_mismatch` | Kode tidak cocok atau sudah tidak berlaku. Masukkan kode terbaru dari aplikasi autentikator. | The code does not match or has expired. Enter the latest code from the authenticator app. | recorded · en validated | S2-J01 §8 |
| `auth.shared_device_advice` | Di perangkat bersama, keluar dari Google setelah selesai; kunci ponsel saat tidak dipakai. | On a shared device, sign out of Google when you finish; lock your phone when it is not in use. | recorded · en validated | S2-J01 §8 |

### 6.5 `documents` — the document names and the draft marking

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `documents.doc_01` | Penawaran | Penawaran | canonical — the approved name · en validated (PC-3) | V1_SCOPE's document catalog |
| `documents.doc_02` | Purchase Order / PO | Purchase Order / PO | canonical — the approved name · en validated (PC-3) | V1_SCOPE's document catalog |
| `documents.doc_03` | Surat Jalan / Faktur Pengiriman | Surat Jalan / Faktur Pengiriman | canonical — the approved name · en validated (PC-3) | V1_SCOPE's document catalog |
| `documents.doc_04` | Nota | Nota | canonical — the approved name · en validated (PC-3) | V1_SCOPE's document catalog |
| `documents.doc_05` | Kuitansi | Kuitansi | canonical — the approved name · en validated (PC-3) | V1_SCOPE's document catalog |
| `documents.doc_06` | Invoice | Invoice | canonical — the approved name · en validated | V1_SCOPE's document catalog |
| `documents.doc_07` | Lampiran Kuitansi | Lampiran Kuitansi | canonical — the approved name · en validated (PC-3) | V1_SCOPE's document catalog |
| `documents.doc_08` | BAST | BAST | canonical — the approved name · en validated | V1_SCOPE's document catalog |
| `documents.doc_09` | SPK | SPK | canonical — the approved name · en validated | V1_SCOPE's document catalog |
| `documents.doc_10` | Berita Acara Pemeriksaan Barang | Berita Acara Pemeriksaan Barang | canonical — the approved name · en validated | V1_SCOPE's document catalog |
| `documents.doc_11` | HPS | HPS | canonical — the approved name · en validated (PC-3) | V1_SCOPE's document catalog |
| `documents.doc_12` | Nota Terima Barang | Nota Terima Barang | canonical — the approved name · en validated (PC-3) | V1_SCOPE's document catalog |
| `documents.doc_13` | Surat Permintaan Pembayaran | Surat Permintaan Pembayaran | canonical — the approved name · en validated | V1_SCOPE's document catalog |
| `documents.doc_14` | Surat Pesanan | Surat Pesanan | canonical — the approved name · en validated | V1_SCOPE's document catalog |
| `documents.draft_marking` | DRAF — belum terbit | DRAF — belum terbit | never translated (§3.4) | S2-J03 §8 |
| `documents.number_line` | Nomor diberikan saat terbit | Nomor diberikan saat terbit | never translated (§3.4) | S2-J03 §8 |

### 6.6 `pages` — every page of INFORMATION_ARCHITECTURE §8

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `pages.pg_001.title` | Masuk | Sign in | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_001.email` | E-mail | Email | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.forgot` | Lupa kata sandi? | Forgot your password? | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.forgot_answer` | Minta tautan atur ulang kepada Owner. | Ask the Owner for a reset link. | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.google` | Lanjutkan dengan Google | Continue with Google | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.heading` | Masuk ke MultipleCorp | Sign in to MultipleCorp | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.language` | ID \\| EN | ID \\| EN | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.language.en` | EN | EN | session copy · validated | the pilot's pages |
| `pages.pg_001.language.id` | ID | ID | session copy · validated | the pilot's pages |
| `pages.pg_001.or` | atau | or | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.password` | Kata sandi | Password | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.show` | Tampilkan | Show | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.submit` | Masuk | Sign in | recorded · en validated | S2-J01 §8 |
| `pages.pg_001.password_saved` | Kata sandi tersimpan. Masuk untuk melanjutkan. | Password saved. Sign in to continue. | validated · en validated (PC-3) | ROUTE_CONTRACTS ROUTE-106; NAVIGATION_CONTRACTS NAV-003 |
| `pages.pg_002.title` | Verifikasi Dua Langkah | Two-step verification | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_002.back` | Kembali | Back | validated | S2-J01 §8 |
| `pages.pg_002.code_advice` | Jangan bagikan kode ini kepada siapa pun. | Never share this code with anyone. | session copy · validated | the pilot's pages |
| `pages.pg_002.code_label` | Kode 6 digit dari aplikasi autentikator | 6-digit code from the authenticator app | recorded · en validated | S2-J01 §8 |
| `pages.pg_002.heading` | Masukkan kode verifikasi | Enter the verification code | recorded · en validated | S2-J01 §8 |
| `pages.pg_002.no_app` | Tidak bisa memakai aplikasi? | Can't use the app? | recorded · en validated | S2-J01 §8 |
| `pages.pg_002.no_app_answer` | Masuk dengan kata sandi untuk memakai kode pemulihan. | Sign in with your password to use a recovery code. | recorded · en validated | S2-J01 §8 |
| `pages.pg_002.recovery_label` | Kode pemulihan | Recovery code | validated | S2-J01 §8 |
| `pages.pg_002.submit` | Verifikasi | Verify | recorded · en validated | S2-J01 §8 |
| `pages.pg_002.use_recovery` | Gunakan kode pemulihan | Use a recovery code | recorded · en validated | S2-J01 §8 |
| `pages.pg_003.title` | Atur Kata Sandi | Set password | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_004.title` | Aktifkan Verifikasi Dua Langkah | Turn on two-step verification | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_004.heading` | Aktifkan Verifikasi Dua Langkah | Turn on two-step verification | validated | S2-J01 §8 |
| `pages.pg_004.reset_line` | Atur ulang aplikasi autentikator untuk melanjutkan. | Set up your authenticator app again to continue. | validated | S2-J01 §8 |
| `pages.pg_004.start` | Atur aplikasi autentikator | Set up the authenticator app | session copy · validated | the pilot's pages |
| `pages.pg_005.title` | Halaman Sistem | System page | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_006.title` | Beranda | Home | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_006.ageing` | Umur piutang | Receivable ageing | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.ageing_table` | Tabel | Table | validated | S2-J02 §8 |
| `pages.pg_006.all_decisions` | Lihat semua keputusan (7) | See all decisions (7) | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.all_decisions_tpl` | Lihat semua keputusan ({v}) | See all decisions ({v}) | session copy · validated | the pilot's copy |
| `pages.pg_006.as_of` | Angka per 08.15 WIB | Figures as of 08.15 WIB | canonical · en validated | S2-J02 §8 |
| `pages.pg_006.as_of_tpl` | Angka per {v} WIB | Figures as of {v} WIB | session copy · validated | the pilot's copy |
| `pages.pg_006.attention` | Proyek yang perlu perhatian | Projects needing attention | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.bucket_1_30` | Lewat 1–30 hari | 1–30 days overdue | recorded · validated | S2-J02 §8 |
| `pages.pg_006.bucket_31_60` | Lewat 31–60 hari | 31–60 days overdue | recorded · validated | S2-J02 §8 |
| `pages.pg_006.bucket_61_90` | Lewat 61–90 hari | 61–90 days overdue | recorded · validated | S2-J02 §8 |
| `pages.pg_006.bucket_90` | Lewat lebih dari 90 hari | More than 90 days overdue | recorded · validated | S2-J02 §8 |
| `pages.pg_006.bucket_current` | Belum jatuh tempo | Not yet due | recorded · validated | S2-J02 §8 |
| `pages.pg_006.col_amount` | Jumlah | Amount | validated | S2-J02 §8 |
| `pages.pg_006.col_bucket` | Umur | Age | validated | S2-J02 §8 |
| `pages.pg_006.decisions` | Perlu keputusan Anda | Needs your decision | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.details` | Rincian angka | Figure details | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.kpi_cash_in` | Kas masuk (uang diterima) | Cash in (money received) | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.kpi_profit` | Laba manajerial | Managerial profit | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.kpi_receivable` | Piutang aktif | Active receivables | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.kpi_sales` | Nilai penjualan (invoice terbit) | Sales value (issued invoices) | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.name` | Beranda | Home | session copy · validated | the pilot's pages |
| `pages.pg_006.open_work` | Buka Pekerjaan | Open Work | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.ops` | Antrean operasional | Operational queue | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.overdue_part` | lewat jatuh tempo Rp41.250.000 | Rp41.250.000 overdue | validated | S2-J02 §8 |
| `pages.pg_006.overdue_part_tpl` | lewat jatuh tempo {v} | {v} overdue | session copy · validated | the pilot's copy |
| `pages.pg_006.period` | Okt 2026 | Okt 2026 | data | S2-J02 §8 |
| `pages.pg_006.period_name` | Periode | Period | validated | S2-J02 §8 |
| `pages.pg_006.row_qs10` | Piutang lewat jatuh tempo | Overdue receivables | validated | S2-J02 §8 |
| `pages.pg_006.row_qs11` | Piutang dalam sengketa | Disputed receivables | validated | S2-J02 §8 |
| `pages.pg_006.row_qs14` | Aktivitas baru sejak Anda masuk sebelumnya | New activity since your previous sign-in | validated | S2-J02 §8 |
| `pages.pg_006.row_qs16` | Kewajiban tersisa | Remaining obligations | validated | S2-J02 §8 |
| `pages.pg_006.row_qs16_meta` | Selesai paksa · 1 reservasi tersisa untuk dilepas | Completed (forced) · 1 remaining reservation to release | validated | S2-J02 §8 |
| `pages.pg_006.row_qs16_meta_tpl` | Selesai paksa · {v} reservasi tersisa untuk dilepas | Completed (forced) · {v} remaining reservation to release\|Completed (forced) · {v} remaining reservations to release | session copy · validated | the pilot's copy |
| `pages.pg_006.row_qs20` | Penolakan yang diteruskan | Forwarded refusals | validated | S2-J02 §8 |
| `pages.pg_006.row_qs20_meta` | ARJ · Barang keluar menyentuh lot perusahaan lain · 2 hari lalu | ARJ · A dispatch touched another company's lot · 2 days ago | validated | S2-J02 §8 |
| `pages.pg_006.row_qs20_meta_tpl` | Barang keluar menyentuh lot perusahaan lain · {v} hari lalu | A dispatch touched another company's lot · {v} day ago\|A dispatch touched another company's lot · {v} days ago | session copy · validated | the pilot's copy |
| `pages.pg_006.row_qs22` | Selisih Tinta printer hitam menunggu penetapan Owner. | A discrepancy in Tinta printer hitam awaits the Owner's attribution. | validated | S2-J02 §8 |
| `pages.pg_006.row_qs22_tpl` | Selisih {v} menunggu penetapan Owner. | A discrepancy in {v} awaits the Owner's attribution. | session copy · validated | the pilot's copy |
| `pages.pg_006.shortage_0088` | Laptop 14 inci kurang 20 unit | Laptop 14 inci short by 20 unit | recorded · en validated | S2-J02 §8 |
| `pages.pg_006.shortage_tpl` | {v} kurang {w} unit | {v} short by {w} unit | session copy · validated | the pilot's copy |
| `pages.pg_006.view` | Gabungan · ARJ · BTN · CKP | Consolidated · ARJ · BTN · CKP | canonical · recorded — the company codes are data · en validated | S2-J02 §8 |
| `pages.pg_006.view.arj` | ARJ | ARJ | data | the pilot's pages |
| `pages.pg_006.view.btn` | BTN | BTN | data | the pilot's pages |
| `pages.pg_006.view.ckp` | CKP | CKP | data | the pilot's pages |
| `pages.pg_006.view.gabungan` | Gabungan | Consolidated | session copy · validated | the pilot's pages |
| `pages.pg_006.view_label` | Tampilan | View | validated | S2-J02 §8 |
| `pages.pg_007.title` | Pekerjaan | Work | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_007.awaiting_receipt` | Menunggu tanda terima klien | Awaiting the client's receipt | recorded · validated | S2-J03 §8 |
| `pages.pg_007.count_label` | Jumlah per perusahaan | Count per company | validated | S2-J03 §8 |
| `pages.pg_007.docs_empty` | Tidak ada dokumen atau kasus yang perlu ditindaklanjuti. | No documents or cases need follow-up. | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.group_billing` | Tagihan dan pembayaran | Billing and payments | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.group_completion` | Penyelesaian proyek | Project completion | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.group_docs` | Dokumen dan kasus | Documents and cases | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.group_goods` | Barang: terima, keluarkan, kirim | Goods: receive, dispatch, deliver | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.line_not_received` | 1 baris belum diterima | 1 line not yet received | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.name` | Pekerjaan | Work | session copy · validated | the pilot's pages |
| `pages.pg_007.next_card` | Berikutnya: Terima ARJ/PB/2026/0216 — 4 baris belum dicatat | Next: Receive ARJ/PB/2026/0216 — 4 lines not yet recorded | recorded · en validated | S2-J05 §8 |
| `pages.pg_007.next_card_3` | Berikutnya: Terima ARJ/PB/2026/0216 — 3 baris belum dicatat | Next: Receive ARJ/PB/2026/0216 — 3 lines not yet recorded | validated | S2-J05 §8 |
| `pages.pg_007.not_invoiced` | Nilai terkonfirmasi belum diinvoice | Confirmed value not yet invoiced | validated | S2-J03 §8 |
| `pages.pg_007.not_received_3` | 3 baris belum diterima | 3 lines not yet received | validated | S2-J05 §8 |
| `pages.pg_007.not_received_4` | 4 baris belum diterima | 4 lines not yet received | validated | S2-J05 §8 |
| `pages.pg_007.qs06_open` | Buka pengiriman | Open delivery | validated | S2-J03 §8 |
| `pages.pg_007.receipt_for_project_tpl` | {v} untuk {w} | {v} for {w} | validated | S2-J03 §3 — the receipt row, "ARJ/PB/2026/0213 untuk ARJ/PRJ/2026/0152" |
| `pages.pg_007.row_qs03` | 15 unit diterima menunggu reservasi | 15 unit received, awaiting reservation | validated | S2-J05 §8 |
| `pages.pg_007.shortage` | Laptop 14 inci kurang 15 unit | Laptop 14 inci short by 15 unit | recorded · validated | S2-J03 §8 |
| `pages.pg_007.sub_delivery` | Catat pengiriman | Record delivery | recorded · en validated | S2-J05 §8 |
| `pages.pg_007.sub_dispatch` | Keluarkan dan kirim | Dispatch and deliver | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.sub_dispatch_only` | Keluarkan barang | Dispatch goods | recorded · en validated | S2-J05 §8 |
| `pages.pg_007.sub_prepare` | Siapkan barang | Prepare goods | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.sub_receive` | Terima barang | Receive goods | recorded · en validated | S2-J03 §8 |
| `pages.pg_007.unmet` | 2 syarat belum terpenuhi | 2 conditions not met | validated | S2-J03 §8 |
| `pages.pg_008.title` | Tinjauan | Review | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_009.title` | Cari | Search | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_065.title` | Akun Saya | My account | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_010.title` | Proyek | Projects | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_011.title` | Perjalanan Proyek | Project journey | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_012.title` | Penyelesaian Proyek | Project completion | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_013.title` | Formulir Proyek | Project form | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_014.title` | Penawaran | Quotation | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_037.title` | Daftar Penawaran | Quotations | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_015.title` | Pembelian | Purchases | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_016.title` | Detail Pembelian | Purchase | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_016.layout` | Tata letak | Layout | validated | S2-J04 §8 |
| `pages.pg_016.remaining` | Sisa diterima | Remaining to receive | canonical · validated | S2-J04 §8 |
| `pages.pg_017.title` | Buat Pembelian | New purchase | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_017.basis_line_value` | Dasar alokasi: Nilai baris | Allocation basis: line value | validated | S2-J04 §8 |
| `pages.pg_017.cancel` | Batal | Cancel | session copy · validated | the pilot's pages |
| `pages.pg_017.charge` | Biaya pembelian | Purchase charge | validated | S2-J04 §8 |
| `pages.pg_017.charge_amount` | Nilai biaya | Charge amount | validated | S2-J04 §8 |
| `pages.pg_017.class_attributable` | Masuk biaya perolehan | Part of acquisition cost | canonical · en validated | S2-J04 §8 |
| `pages.pg_017.class_expense` | Beban | Expense | canonical · en validated | S2-J04 §8 |
| `pages.pg_017.classification` | Klasifikasi | Classification | validated | S2-J04 §8 |
| `pages.pg_017.line_link` | LPT-14-I5 · untuk item proyek: Laptop 14 inci · 20 unit · Ubah | LPT-14-I5 · for project item: Laptop 14 inci · 20 unit · Change | recorded · en validated | S2-J04 §8 |
| `pages.pg_017.line_link.change` | Ubah | Change | session copy · validated | the pilot's pages |
| `pages.pg_017.line_link.code` | LPT-14-I5 | LPT-14-I5 | data | the pilot's pages |
| `pages.pg_017.line_link.for_item` | untuk item proyek: Laptop 14 inci | for project item: Laptop 14 inci | session copy · validated | the pilot's pages |
| `pages.pg_017.line_link.qty` | 20 unit | 20 unit | data | the pilot's pages |
| `pages.pg_017.line_link_tpl` | untuk item proyek: {v} · {w} unit | for project item: {v} · {w} unit | session copy · validated | the pilot's copy |
| `pages.pg_017.new_supplier` | Pemasok Baru | New supplier | validated | S2-J04 §8 |
| `pages.pg_017.save` | Simpan Pembelian | Save purchase | validated | S2-J04 §8 |
| `pages.pg_017.save_reason` | Pilih pemasok dan isi jumlah serta harga setiap baris. | Choose the supplier and enter each line's quantity and price. | validated | S2-J04 §8 |
| `pages.pg_017.source_bill` | Tagihan sumber | Source bill | validated | S2-J04 §8 |
| `pages.pg_017.supplier_quote` | Nomor penawaran pemasok | Supplier's quotation number | validated | S2-J04 §8 |
| `pages.pg_017.use_data_from` | Gunakan data dari | Use data from | recorded · en validated | S2-J04 §8 |
| `pages.pg_018.title` | Stok | Stock | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_019.title` | Detail Stok Produk | Product stock | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_020.title` | Riwayat Stok | Stock history | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_021.title` | Barang Masuk | Goods receiving | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_022.title` | Terima Barang | Receive goods | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_022.damaged` | Rusak diterima | Damaged and accepted | canonical · en validated | S2-J05 §8 |
| `pages.pg_022.delivery_ref` | Nomor surat jalan pemasok | Supplier's delivery reference | validated | S2-J05 §8 |
| `pages.pg_022.name` | Terima Barang | Receive goods | session copy · validated | the pilot's pages |
| `pages.pg_022.not_received_1` | 1 unit belum diterima | 1 unit not yet received | validated | S2-J05 §8 |
| `pages.pg_022.progress` | 0 baris tercatat · 4 baris tersisa | 0 lines recorded · 4 lines remaining | recorded · en validated | S2-J05 §8 |
| `pages.pg_022.progress.recorded` | 0 baris tercatat | 0 lines recorded | session copy · validated | the pilot's pages |
| `pages.pg_022.progress.recorded_1` | 1 baris tercatat | 1 line recorded | validated | S2-J05 §8 |
| `pages.pg_022.progress.recorded_2` | 2 baris tercatat | 2 lines recorded | recorded · en validated | S2-J05 §8 |
| `pages.pg_022.progress.recorded_3` | 3 baris tercatat | 3 lines recorded | validated | S2-J05 §8 |
| `pages.pg_022.progress.recorded_4` | 4 baris tercatat | 4 lines recorded | validated | S2-J05 §8 |
| `pages.pg_022.progress.remaining` | 4 baris tersisa | 4 lines remaining | session copy · validated | the pilot's pages |
| `pages.pg_022.progress.remaining_0` | 0 baris tersisa | 0 lines remaining | validated | S2-J05 §8 |
| `pages.pg_022.progress.remaining_1` | 1 baris tersisa | 1 line remaining | validated | S2-J05 §8 |
| `pages.pg_022.progress.remaining_2` | 2 baris tersisa | 2 lines remaining | recorded · en validated | S2-J05 §8 |
| `pages.pg_022.progress.remaining_3` | 3 baris tersisa | 3 lines remaining | validated | S2-J05 §8 |
| `pages.pg_022.rack` | Rak | Rack | validated | S2-J05 §8 |
| `pages.pg_022.rack_choose` | Pilih rak | Choose a rack | validated | S2-J05 §8 |
| `pages.pg_022.record` | Catat Barang Masuk | Record goods received | canonical · en validated | S2-J05 §8 |
| `pages.pg_022.record_reason` | Isi jumlah dan rak. | Enter the quantities and the rack. | validated | S2-J05 §8 |
| `pages.pg_022.rejected` | Ditolak | Rejected | canonical · en validated | S2-J05 §8 |
| `pages.pg_022.reserve` | Reservasi untuk Proyek Ini | Reserve for this project | canonical · validated | S2-J05 §8 |
| `pages.pg_022.reserve_limit` | Maksimal 15 unit | At most 15 unit | canonical · validated | S2-J05 §8 |
| `pages.pg_022.reserve_limit_tpl` | Maksimal {v} unit | At most {v} unit | session copy · validated | the pilot's copy |
| `pages.pg_022.summary` | Ringkasan | Summary | validated | S2-J05 §8 |
| `pages.pg_022.usable` | Layak pakai | Usable | canonical · en validated | S2-J05 §8 |
| `pages.pg_023.title` | Barang Keluar | Goods dispatch | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_024.title` | Keluarkan Barang | Dispatch goods | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_025.title` | Reservasi | Reservations | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_026.title` | Stok Opname | Stock count | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_027.title` | Penyesuaian & Kondisi | Adjustments and condition | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_028.title` | Retur ke Pemasok | Supplier return | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_029.title` | Selisih & Temuan | Discrepancies and findings | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_030.title` | Pengiriman | Deliveries | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_031.title` | Catat Pengiriman | Record delivery | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_032.title` | Kirim Langsung | Direct shipment | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_033.title` | Serah Terima Jasa | Service handover | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_034.title` | Retur dari Klien | Client return | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_035.title` | Dokumen | Documents | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_036.title` | Detail Dokumen | Document | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_038.title` | Bukti | Evidence | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_039.title` | Dokumen Administrasi | Administrative documents | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_040.title` | Kasus Koreksi | Correction cases | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_041.title` | Invoice | Invoices | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_042.title` | Buat Invoice | New invoice | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_042.name` | Buat Invoice | New invoice | session copy · validated | the pilot's pages |
| `pages.pg_043.title` | Detail Invoice | Invoice | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_043.col_subtotal` | Subtotal | Subtotal | validated | S2-J03 §8 |
| `pages.pg_043.documents` | Dokumen | Documents | validated | S2-J03 §8 |
| `pages.pg_043.lines` | Baris invoice | Invoice lines | validated | S2-J03 §8 |
| `pages.pg_043.value` | Nilai invoice | Invoice value | validated | S2-J03 §8 |
| `pages.pg_044.title` | Piutang | Receivables | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_044.col_due` | Jatuh tempo | Due date | validated | S2-J02 §8 |
| `pages.pg_044.col_invoice` | Invoice | Invoice | validated | S2-J02 §8 |
| `pages.pg_044.col_no` | No. | No. | validated | S2-J02 §8 |
| `pages.pg_044.col_overdue` | Lewat | Overdue by | validated | S2-J02 §8 |
| `pages.pg_044.days_38` | 38 hari | 38 days | validated | S2-J02 §8 |
| `pages.pg_044.days_4` | 4 hari | 4 days | validated | S2-J02 §8 |
| `pages.pg_044.name` | Piutang | Receivables | session copy · validated | the pilot's pages |
| `pages.pg_045.title` | Pembayaran | Payments | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_045.col_date` | Tanggal | Date | validated | S2-J06 §8 |
| `pages.pg_045.name` | Pembayaran | Payments | session copy · validated | the pilot's pages |
| `pages.pg_046.title` | Catat Pembayaran | Record payment | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_046.account` | Rekening perusahaan | Company account | validated | S2-J06 §8 |
| `pages.pg_046.amount` | Jumlah | Amount | validated | S2-J06 §8 |
| `pages.pg_046.applications` | Penerapan ke invoice | Applied to invoices | validated · canonical | S2-J06 §8 |
| `pages.pg_046.col_applied` | Diterapkan | Applied | validated | S2-J06 §8 |
| `pages.pg_046.method` | Metode | Method | validated | S2-J06 §8 |
| `pages.pg_046.method_transfer` | Transfer bank | Bank transfer | validated | S2-J06 §8 |
| `pages.pg_046.name` | Catat Pembayaran | Record payment | session copy · validated | the pilot's pages |
| `pages.pg_046.net_paid` | Klien membayar setelah potongan | The client paid net of deductions | validated | S2-J06 §8 |
| `pages.pg_046.no_proof_reason` | Alasan tanpa bukti (wajib) — tercatat di riwayat dan terlihat oleh Owner. | Reason for no proof (required) — recorded in the history and visible to the Owner. | validated | S2-J06 §8 |
| `pages.pg_046.outstanding` | Sisa piutang | Outstanding | validated | S2-J06 §8 |
| `pages.pg_046.proof` | Bukti | Proof | validated | S2-J06 §8 |
| `pages.pg_046.record_reason` | Lengkapi data pembayaran. | Complete the payment details. | validated | S2-J06 §8 |
| `pages.pg_046.reference` | Referensi | Reference | validated | S2-J06 §8 |
| `pages.pg_046.statement_line` | Baris mutasi bank | Bank statement line | validated | S2-J06 §8 |
| `pages.pg_047.title` | Potongan | Deductions | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_048.title` | Kredit Pelanggan | Customer credit | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_049.title` | Pengeluaran & Kas Lain | Disbursements and other cash | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_050.title` | Mutasi Bank | Bank statement | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_051.title` | Laporan | Reports | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_052.title` | Laporan Gabungan | Consolidated report | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_053.title` | Ekspor Saya | My exports | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_054.title` | Klien | Clients | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_055.title` | Pemasok | Suppliers | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_056.title` | Produk & Jasa | Products and services | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_057.title` | Lokasi Rak | Rack locations | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_058.title` | Impor Data Awal | Opening import | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_059.title` | Perusahaan | Companies | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_060.title` | Pengguna & Akses | Users and access | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_061.title` | Penomoran Dokumen | Document numbering | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_062.title` | Pajak | Tax | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_063.title` | Riwayat Aktivitas | Activity history | validated | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_064.title` | Log Keamanan | Security log | validated | INFORMATION_ARCHITECTURE §8 |

### 6.7 Shared groups — forms, totals, files, dialogs, the shell, actions, the composer, the signals' empty states and a region's states

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `actions.add_follow_up_purchase` | Tambahkan Pembelian Susulan | Add follow-up purchase | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-171 |
| `actions.apply` | Terapkan | Apply | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-252 |
| `actions.apply_and_lift_write_off` | Terapkan dan Cabut Penghapusan | Apply and lift write-off | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-248 |
| `actions.apply_to_invoice` | Terapkan ke Invoice | Apply to invoice | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-252 |
| `actions.apply_variance` | Terapkan Selisih | Apply variance | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-193 |
| `actions.attribute_company` | Tetapkan Perusahaan | Attribute to a company | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-198 |
| `actions.attribute_surplus` | Tetapkan Surplus | Attribute surplus | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-197 |
| `actions.attribute_with_evidence` | Tetapkan dengan Bukti | Attribute with evidence | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-199 |
| `actions.back_to_work` | Kembali ke Pekerjaan | Back to Work | recorded · en validated | S2-J05 §8 |
| `actions.bill` | Tagihkan | Bill | validated · canonical | S2-J03 §8 |
| `actions.cancel_purchase` | Batalkan Pembelian | Cancel purchase | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-173 |
| `actions.cancel_remainder` | Batalkan Sisa | Cancel remainder | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-159 |
| `actions.change_condition` | Ubah Kondisi | Change condition | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-195 |
| `actions.change_fulfilment` | Ubah Cara Pemenuhan | Change fulfilment mode | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-160 |
| `actions.change_password` | Ubah Kata Sandi | Change password | validated · en validated (PC-3) | ROUTE_CONTRACTS ROUTE-111 |
| `actions.close_case` | Tutup Kasus | Close case | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-271 |
| `actions.complete_project` | Selesaikan Proyek | Complete project | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-161 |
| `actions.confirm_direct_shipment` | Konfirmasi Kirim Langsung | Confirm direct shipment | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-205 |
| `actions.copy_number` | Salin nomor | Copy number | canonical · recorded · en validated | S2-J03 §8 |
| `actions.correct_delivery_record` | Koreksi Catatan Pengiriman | Correct delivery record | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-204 |
| `actions.correct_opening_data` | Koreksi Data Awal | Correct opening data | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-213 |
| `actions.correct_price_tax_charge` | Koreksi Harga, Pajak atau Biaya | Correct price, tax or charge | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-175 |
| `actions.count_again` | Hitung Ulang | Count again | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-191 |
| `actions.create_credential_link` | Buat Tautan Atur Kata Sandi | Create password-setting link | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-125 |
| `actions.create_internal_barcode` | Buat Barcode Internal | Create internal barcode | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-147 |
| `actions.create_invoice` | Buat Invoice | Create invoice | recorded · en validated | S2-J03 §8 |
| `actions.create_project` | Buat Proyek | Create project | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-151 |
| `actions.create_purchase` | Buat Pembelian | Create purchase | recorded · validated | S2-J03 §8 |
| `actions.create_revision` | Buat Revisi | Create revision | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-156 |
| `actions.discard_draft` | Buang Draf | Discard draft | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-153 |
| `actions.download` | Unduh | Download | canonical · en validated | S2-J03 §8 |
| `actions.edit_project` | Ubah proyek | Edit project | validated · en validated (PC-3) | ROUTE_CONTRACTS ROUTE-152 |
| `actions.end_session` | Akhiri Sesi | End session | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-130 |
| `actions.issue_ntb` | Terbitkan Nota Terima Barang | Issue Nota Terima Barang | recorded · en validated | S2-J05 §8 |
| `actions.issue_po` | Terbitkan PO | Issue PO | canonical · en validated | S2-J04 §8 |
| `actions.issue_prepayment_kuitansi` | Terbitkan Kuitansi untuk Proses Pembayaran | Issue Kuitansi for the payment process | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-249 |
| `actions.issue_quotation` | Terbitkan Penawaran | Issue Penawaran | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-155 |
| `actions.issue_surat_jalan` | Terbitkan Surat Jalan | Issue Surat Jalan | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-187 |
| `actions.lift_dispute` | Cabut Sengketa | Lift dispute | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-246 |
| `actions.link_kuitansi` | Tautkan Kuitansi | Link Kuitansi | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-250 |
| `actions.load_more` | Muat Lagi | Load more | canonical · en validated | S2-J06 §8 |
| `actions.make_current_version` | Jadikan Versi Berlaku | Make the current version | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-230 |
| `actions.mark_disputed` | Tandai Sengketa | Mark disputed | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-246 |
| `actions.mark_not_applicable` | Tandai Tidak Berlaku | Mark not applicable | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-166 |
| `actions.more` | Tindakan Lain | More actions | recorded · canonical · en validated | S2-J02 §8 |
| `actions.move_location` | Pindah Lokasi | Move location | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-190 |
| `actions.open_invoice` | Buka Invoice | Open invoice | validated · canonical | S2-J03 §8 |
| `actions.open_project` | Buka Proyek | Open project | recorded · validated | S2-J03 §8 |
| `actions.print` | Cetak | Print | canonical · en validated | S2-J03 §8 |
| `actions.print_label` | Cetak Label | Print label | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE_CONTRACTS §4; J-MD-04 |
| `actions.print_count_sheet` | Cetak Lembar Hitung | Print count sheet | en proposed · the Owner validated the Indonesian (`DIR-101`) | ROUTE_CONTRACTS §4; IA §8.3 |
| `actions.record_late_charge` | Catat Biaya Susulan | Record late charge | en proposed · the Owner validated the Indonesian (`DIR-101`) | ROUTE_CONTRACTS ROUTE-178 |
| `actions.correct_allocation_basis` | Koreksi Dasar Alokasi | Correct allocation basis | canonical · en proposed (`DIR-100`) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-179 |
| `actions.cancel_count` | Batalkan Hitungan | Cancel count | en proposed · the Owner validated the Indonesian (`DIR-101`) | ROUTE_CONTRACTS ROUTE-215 |
| `actions.resolve_residual_settlement` | Selesaikan Sisa Potongan | Resolve remaining deduction | en proposed · the Owner validated the Indonesian (`DIR-101`) | ROUTE_CONTRACTS ROUTE-263 |
| `actions.abort_batch` | Batalkan Batch | Abort batch | canonical · en proposed (`DIR-100`) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-276 |
| `actions.mark_performed` | Tandai Terlaksana | Mark performed | validated with F1 · en proposed (`DIR-101`) | ROUTE_CONTRACTS ROUTE-180 |
| `actions.undo_performed` | Batalkan Tanda Terlaksana | Undo performed mark | proposed (`DIR-101`) | ROUTE_CONTRACTS ROUTE-180 |
| `actions.hold_credit` | Tahan Kredit | Hold credit | validated with P11Q-07 · en proposed (`DIR-101`) | ROUTE_CONTRACTS ROUTE-264 |
| `actions.record_adjustment` | Catat Penyesuaian Stok | Record stock adjustment | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-194 |
| `actions.record_bank_line` | Catat Mutasi Bank | Record bank statement line | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-261 |
| `actions.record_cash_disbursement` | Catat Pengeluaran Kas | Record cash disbursement | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-258 |
| `actions.record_client_answer` | Catat Jawaban Klien | Record the client's answer | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-157 |
| `actions.record_deduction` | Catat Potongan | Record deduction | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-255 |
| `actions.record_dispatch` | Catat Barang Keluar | Record goods dispatch | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-186 |
| `actions.record_expense` | Catat Beban | Record expense | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-257 |
| `actions.record_loss_in_transit` | Catat Hilang di Perjalanan | Record loss in transit | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-203 |
| `actions.record_other_cash_in` | Catat Kas Masuk Lain | Record other cash-in | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-259 |
| `actions.record_payment` | Catat Pembayaran | Record payment | canonical · en validated | S2-J06 §8 |
| `actions.reduce_reservation` | Kurangi Reservasi | Reduce reservation | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-185 |
| `actions.refund` | Kembalikan Dana | Refund | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-256 |
| `actions.register_barcode` | Daftarkan Barcode | Register barcode | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-147 |
| `actions.reinspect` | Periksa Ulang | Re-inspect | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-195 |
| `actions.release` | Lepas | Release | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-185 |
| `actions.request_again` | Minta Lagi | Request again | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-283 |
| `actions.reverse` | Balikkan | Reverse | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-212 |
| `actions.reverse_attribution` | Balikkan Penetapan | Reverse attribution | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-200 |
| `actions.reverse_receipt` | Balikkan Penerimaan | Reverse receipt | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-183 |
| `actions.set_first_number` | Tetapkan Nomor Awal | Set first number | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-137 |
| `actions.ship_direct` | Kirim langsung ke klien | Ship directly to the client | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-171 |
| `actions.start_count` | Mulai Hitungan | Start count | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-191 |
| `actions.start_receiving` | Mulai Terima Barang | Start receiving | recorded · en validated | S2-J05 §8 |
| `actions.view_purchase` | Lihat Pembelian | View purchase | recorded · validated | S2-J03 §8 |
| `composer.all_lines` | Semua baris terkonfirmasi | All confirmed lines | validated | S2-J03 §8 |
| `composer.back_to_journey` | Kembali ke perjalanan proyek | Back to the project journey | recorded · en validated | S2-J03 §8 |
| `composer.billing_address` | Alamat penagihan | Billing address | validated | S2-J03 §8 |
| `composer.carried` | Diambil dari proyek | Taken from the project | recorded · en validated | S2-J03 §8 |
| `composer.check_cap` | Nilai invoice tidak melebihi nilai terkonfirmasi proyek. | The invoice value is within the project's confirmed value. | validated | S2-J03 §8 |
| `composer.check_delivered` | Nilai invoice tidak melebihi nilai yang sudah dikirim. | The invoice value is not above the delivered value. | validated | S2-J03 §8 |
| `composer.check_uninvoiced` | Nilai terkonfirmasi yang belum diinvoice: Rp43.900.000. | Confirmed value not yet invoiced: Rp43.900.000. | validated | S2-J03 §8 |
| `composer.checks` | Pemeriksaan | Checks | recorded · en validated | S2-J03 §8 |
| `composer.checks_ok` | 3 pemeriksaan lolos | 3 checks passed | validated | S2-J03 §8 |
| `composer.client_block` | Klien dan penagihan | Client and billing | validated | S2-J03 §8 |
| `composer.close_full` | Tutup | Close | validated | S2-J03 §8 |
| `composer.details` | Rincian | Details | validated · en validated (PC-3) | DESIGN_SYSTEM D-DS-23 — the tablet's "Rincian \| Pratinjau" and the phone's first step (DC-11) |
| `composer.entered` | Diisi sekarang | Filled in now | recorded · en validated | S2-J03 §8 |
| `composer.fit_width` | Pas lebar | Fit width | recorded · en validated | S2-J03 §8 |
| `composer.fit_width.actual` | 100% | 100% | session copy · validated | the pilot's pages |
| `composer.fit_width.fit` | Pas lebar | Fit width | session copy · validated | the pilot's pages |
| `composer.fix_at_source` | Perbaiki di data proyek | Fix in the project's data | recorded · en validated | S2-J03 §8 |
| `composer.full_screen` | Layar penuh | Full screen | recorded · en validated | S2-J03 §8 |
| `composer.issue` | Terbitkan Invoice | Issue invoice | recorded · en validated | S2-J03 §8 |
| `composer.layout` | Tata letak: Invoice · Nota | Layout: Invoice · Nota | validated | S2-J03 §8 |
| `composer.layout.invoice` | Invoice | Invoice | session copy · validated | the pilot's pages |
| `composer.layout.label` | Tata letak: | Layout: | session copy · validated | the pilot's pages |
| `composer.layout.nota` | Nota | Nota | session copy · validated | the pilot's pages |
| `composer.lines` | Baris terkonfirmasi | Confirmed lines | validated | S2-J03 §8 |
| `composer.page_n` | Halaman 1 dari 1 | Page 1 of 1 | recorded · en validated | S2-J03 §8 |
| `composer.page_next` | Halaman berikutnya | Next page | validated | S2-J03 §8 |
| `composer.page_prev` | Halaman sebelumnya | Previous page | validated | S2-J03 §8 |
| `composer.preview` | Pratinjau | Preview | validated | S2-J03 §8 |
| `composer.preview_label` | Pratinjau {jenis}, draf belum terbit | {jenis} preview, draft not issued | validated | S2-J03 §8; DESIGN_SYSTEM §14.3 — the paper's name for every document type |
| `composer.sales_value` | Nilai penjualan | Sales value | validated | S2-J03 §8 |
| `composer.save_draft` | Simpan Draf | Save draft | recorded · en validated | S2-J03 §8 |
| `composer.scope` | Yang ditagihkan | What is invoiced | validated | S2-J03 §8 |
| `composer.source` | Dari proyek ARJ/PRJ/2026/0139 | From project ARJ/PRJ/2026/0139 | validated | S2-J03 §8 |
| `composer.summary` | Ringkasan | Summary | validated | S2-J03 §8 |
| `composer.summary_estimate` | Ringkasan (perkiraan) | Summary (estimate) | validated | S2-J03 §8 |
| `composer.tax` | PPN 11% | PPN 11% | validated | S2-J03 §8 |
| `composer.total` | Total invoice | Invoice total | validated | S2-J03 §8 |
| `composer.total_estimate` | Total invoice (perkiraan) | Invoice total (estimate) | validated | S2-J03 §8 |
| `composer.unit` | Unit | Unit | validated | S2-J03 §8 |
| `composer.value_only` | Nilai saja (DP atau termin) | Value only (down payment or instalment) | validated | S2-J03 §8 |
| `dialogs.back` | Kembali | Back | canonical · en validated | S2-J03 §8 |
| `dialogs.leave.confirm` | Tinggalkan Halaman | Leave page | validated | S2-J04 §8 |
| `dialogs.logout.back` | Kembali | Back | validated | S2-J01 §8 |
| `dialogs.logout.confirm` | Keluar | Sign out | validated | S2-J01 §8 |
| `files.cancel` | Batalkan | Cancel | canonical · en validated | S2-J04 §8 |
| `files.choose` | Pilih File | Choose file | validated | S2-J04 §8 |
| `files.limits` | PDF, JPG, PNG atau WebP, maksimal 20 MB. | PDF, JPG, PNG or WebP, up to 20 MB. | recorded · en validated | S2-J04 §8 |
| `files.remove` | Hapus file | Remove file | validated | S2-J04 §8 |
| `files.uploaded` | Terunggah | Uploaded | validated | S2-J04 §8 |
| `files.uploading` | Mengunggah | Uploading | validated | S2-J04 §8 |
| `forms.company` | Perusahaan | Company | canonical · en validated | S2-J04 §8 |
| `forms.intercompany_reason` | Alasan alokasi antar-perusahaan | Reason for inter-company allocation | canonical · en validated (PC-3) | ADMIN_FLOW — the control's label; ROUTE_CONTRACTS ROUTE-186 |
| `forms.max_exceeded` | Maksimal 15 unit. | At most 15 unit. | validated | S2-J05 §8 |
| `forms.max_exceeded_tpl` | Maksimal {v} unit. | At most {v} unit. | session copy · validated | the pilot's copy |
| `forms.more_details` | Rincian lain | More details | recorded · en validated | S2-J04 §8 |
| `forms.project` | Proyek | Project | canonical · en validated | S2-J04 §8 |
| `forms.qty_decrease` | Kurangi | Decrease | session copy · validated | the pilot's copy |
| `forms.qty_increase` | Tambah | Increase | session copy · validated | the pilot's copy |
| `forms.remove_line` | Hapus baris | Remove line | validated | S2-J04 §8 |
| `forms.reservation_cut` | Stok Tidak Cukup untuk Semua Reservasi | Not enough stock for all reservations | canonical · en validated (PC-3) | ADMIN_FLOW IP-20 |
| `forms.supplier` | Pemasok | Supplier | canonical · en validated | S2-J04 §8 |
| `journey.all_documents` | Semua dokumen | All documents | recorded · en validated | S2-J03 §8 |
| `journey.blocked` | Terhambat | Blocked | canonical · recorded · en validated | S2-J02 §8 |
| `journey.blocker_overdue` | Piutang BTN/INV/2026/0094 lewat jatuh tempo 4 hari | Receivable BTN/INV/2026/0094 4 days overdue | recorded · en validated | S2-J02 §8 |
| `journey.blockers` | Hambatan | Blockers | recorded · en validated | S2-J02 §8 |
| `journey.cost.direct` | Biaya/HPP | Cost / HPP | recorded · en validated | S2-J02 §8 |
| `journey.cost.margin` | Margin | Margin | validated | S2-J02 §8 |
| `journey.cost.profit` | Laba | Profit | recorded · en validated | S2-J02 §8 |
| `journey.cost_profit` | Biaya dan laba | Cost and profit | recorded · en validated | S2-J02 §8 |
| `journey.deadline` | Tenggat 15 Okt 2026 · 10 hari lagi | Deadline 15 Okt 2026 · 10 days left | recorded · en validated | S2-J03 §8 |
| `journey.deadline.date` | Tenggat 15 Okt 2026 | Deadline 15 Okt 2026 | session copy · validated | the pilot's pages |
| `journey.deadline.left` | 10 hari lagi | 10 days left | session copy · validated | the pilot's pages |
| `journey.desk_only` | Langkah ini dikerjakan di komputer. | This step is done on a computer. | validated | S2-J03 §8 |
| `journey.details` | Rincian proyek | Project details | recorded · en validated | S2-J02 §8 |
| `journey.docs_readiness` | Kesiapan dokumen | Document readiness | recorded · en validated | S2-J03 §8 |
| `journey.financial` | Posisi keuangan | Financial position | recorded · en validated | S2-J02 §8 |
| `journey.next` | Berikutnya | Next | recorded · en validated | S2-J02 §8 |
| `journey.next_bast` | Unggah BAST bertanda tangan klien (asli) | Upload the client-signed BAST (original) | recorded · en validated | S2-J02 §8 |
| `journey.next_invoice` | Buat invoice dari 3 baris terkonfirmasi | Create an invoice from 3 confirmed lines | recorded · en validated | S2-J03 §8 |
| `journey.no_blockers` | Tidak ada | None | recorded · en validated | S2-J03 §8 |
| `journey.not_needed` | Tahap yang tidak diperlukan | Stages not needed | recorded · en validated | S2-J02 §8 |
| `journey.now` | Sekarang | Now | recorded · en validated | S2-J02 §8 |
| `journey.other_met` | 8 syarat lain terpenuhi | 8 other conditions met | recorded · en validated | S2-J02 §8 |
| `journey.pos.billed` | Sudah ditagihkan | Billed | recorded · en validated | S2-J02 §8 |
| `journey.pos.cash_in` | Kas masuk | Cash in | recorded · en validated | S2-J02 §8 |
| `journey.pos.confirmed` | Nilai terkonfirmasi | Confirmed value | recorded · en validated | S2-J02 §8 |
| `journey.pos.invoiced` | Sudah diinvoice | Invoiced | recorded · en validated | S2-J02 §8 |
| `journey.pos.outstanding` | Sisa tagihan | Outstanding bill | recorded · en validated | S2-J02 §8 |
| `journey.pred.administration` | Administrasi | Administration | recorded · en validated | S2-J02 §8 |
| `journey.pred.audit` | Audit | Audit | recorded · en validated | S2-J02 §8 |
| `journey.pred.billing` | Penagihan | Billing | recorded · en validated | S2-J02 §8 |
| `journey.pred.delivery` | Pengiriman | Delivery | recorded · en validated | S2-J02 §8 |
| `journey.pred.documents` | Dokumen | Documents | recorded · en validated | S2-J02 §8 |
| `journey.pred.fulfilment` | Pemenuhan | Fulfilment | recorded · en validated | S2-J02 §8 |
| `journey.pred.payment` | Pembayaran | Payment | recorded · en validated | S2-J02 §8 |
| `journey.pred.receivable` | Piutang | Receivable | recorded · en validated | S2-J02 §8 |
| `journey.pred.returns` | Retur dan koreksi | Returns and corrections | recorded · en validated | S2-J02 §8 |
| `journey.pred.stock` | Stok | Stock | recorded · en validated | S2-J02 §8 |
| `journey.primary_bast` | Unggah BAST Klien | Upload client BAST | recorded · canonical · en validated | S2-J02 §8 |
| `journey.remaining` | Yang tersisa sebelum selesai | What remains before completion | recorded · en validated | S2-J02 §8 |
| `journey.stage.agreement` | Kesepakatan | Agreement | session copy · validated | the pilot's pages |
| `journey.stage.billing` | Penagihan | Billing | session copy · validated | the pilot's pages |
| `journey.stage.completion` | Penyelesaian | Completion | session copy · validated | the pilot's pages |
| `journey.stage.delivery` | Pengiriman | Delivery | session copy · validated | the pilot's pages |
| `journey.stage.direct` | Kirim langsung | Direct shipment | recorded · en validated | S2-J02 §8 |
| `journey.stage.documents` | Dokumen | Documents | session copy · validated | the pilot's pages |
| `journey.stage.invoice` | Invoice | Invoice | session copy · validated | the pilot's pages |
| `journey.stage.payment` | Pembayaran | Payment | session copy · validated | the pilot's pages |
| `journey.stage.service` | Jasa | Service | recorded · en validated | S2-J02 §8 |
| `journey.stage.warehouse` | Gudang | Warehouse | session copy · validated | the pilot's pages |
| `journey.step_of_0079` | (langkah 4 dari 8) | (step 4 of 8) | recorded · en validated | S2-J02 §8 |
| `journey.step_of_0139` | (langkah 6 dari 9) | (step 6 of 9) | recorded · en validated | S2-J02 §8 |
| `journey.track` | Tahap proyek | Project stages | recorded · en validated | S2-J02 §8 |
| `journey.waiting_invoice` | Menunggu: invoice terbit | Waiting: invoice issued | recorded · en validated | S2-J03 §8 |
| `pt10.columns.price` | Harga | Price | session copy · validated | the pilot's pages |
| `pt10.columns.product` | Produk/Jasa | Product/Service | session copy · validated | the pilot's pages |
| `pt10.columns.quantity` | Jumlah | Quantity | session copy · validated | the pilot's pages |
| `pt10.columns.subtotal` | Subtotal Perkiraan | Subtotal Estimate | session copy · validated | the pilot's pages |
| `pt10.columns.tax` | Pajak | Tax | session copy · validated | the pilot's pages |
| `pt10.columns.unit` | Satuan | Unit | session copy · validated | the pilot's pages |
| `pt20.placeholder` | Pindai atau ketik kode | Scan or type a code | canonical · en validated | S2-J05 §8 |
| `pt20.ready` | Siap Memindai | Ready to scan | canonical · en validated | S2-J05 §8 |
| `pt20.search` | Cari Produk | Find product | canonical · en validated | S2-J05 §8 |
| `pt25.label` | Tanggal transaksi | Transaction date | canonical · en validated | S2-J03 §8 |
| `pt25.today` | Tanggal transaksi: Hari ini, 05 Okt 2026 · Ubah | Transaction date: Today, 05 Okt 2026 · Change | canonical · en validated | S2-J03 §8 |
| `pt25.today.change` | Ubah | Change | session copy · validated | the pilot's pages |
| `pt25.today.line_tpl` | Tanggal transaksi: Hari ini, {v} | Transaction date: Today, {v} | session copy · validated | the pilot's copy |
| `purchase.col_actions` | Tindakan baris | Line actions | validated | S2-J04 §8 |
| `purchase.col_no` | No. | No. | validated | S2-J04 §8 |
| `purchase.lines` | Baris pembelian | Purchase lines | validated | S2-J04 §8 |
| `purchase.subtotal` | Subtotal | Subtotal | validated | S2-J04 §8 |
| `purchase.summary` | Ringkasan | Summary | validated | S2-J04 §8 |
| `purchase.tax_ppn` | PPN 11% | PPN 11% | validated | S2-J04 §8 |
| `purchase.total` | Total | Total | validated | S2-J04 §8 |
| `result.copied` | Nomor tersalin | Number copied | session copy · validated | the pilot's copy |
| `shell.account.language` | Bahasa tampilan: Indonesia \\| English | Display language: Indonesia \\| English | recorded · en validated | S2-J01 §8 |
| `shell.account.logout` | Keluar | Sign out | recorded · en validated | S2-J01 §8 |
| `shell.account.menu` | Menu akun | Account menu | session copy · validated | the pilot's copy |
| `shell.account.my_account` | Akun Saya | My account | recorded · en validated | S2-J01 §8 |
| `shell.back` | Kembali | Back | session copy · validated | the pilot's copy |
| `shell.collapse` | Ciutkan navigasi | Collapse navigation | session copy · validated | the pilot's copy |
| `shell.create` | Buat | Create | session copy · validated | the pilot's pages |
| `shell.drawer.account` | Akun | Account | session copy · validated | the pilot's copy |
| `shell.drawer.close` | Tutup menu | Close menu | session copy · validated | the pilot's copy |
| `shell.drawer.label` | Menu aplikasi | Application menu | session copy · validated | the pilot's copy |
| `shell.drawer.nav` | Navigasi lengkap | Full navigation | session copy · validated | the pilot's copy |
| `shell.expand` | Perluas navigasi | Expand navigation | session copy · validated | the pilot's copy |
| `shell.nav.akun` | Akun | Account | session copy · validated | the pilot's copy |
| `shell.nav.beranda` | Beranda | Home | session copy · validated | the pilot's pages |
| `shell.nav.cari` | Cari | Search | session copy · validated | the pilot's copy |
| `shell.nav.lainnya` | Lainnya | More | session copy · validated | the pilot's copy |
| `shell.nav.pekerjaan` | Pekerjaan | Work | session copy · validated | the pilot's pages |
| `shell.palette.no_results` | Tidak ada hasil untuk “{q}”. | No results for “{q}”. | recorded · validated | CA-04 item 9 — Golden's own text (§5) |
| `shell.search` | Cari atau pindai kode | Search or scan a code | session copy · validated | the pilot's copy |
| `shell.search_kbd` | Ctrl K | Ctrl K | session copy · validated | the pilot's pages |
| `shell.skip` | Langsung ke konten utama | Skip to main content | session copy · validated | the pilot's pages |
| `signals.qs_01_empty` | Semua permintaan gudang sudah direservasi. | All warehouse demand is reserved. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-01's empty state |
| `signals.qs_02_empty` | Tidak ada kekurangan stok. | No stock shortages. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-02's empty state |
| `signals.qs_03_empty` | Tidak ada barang diterima yang menunggu reservasi. | No received goods awaiting reservation. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-03's empty state |
| `signals.qs_04_empty` | Tidak ada reservasi yang dikurangi. | No reservations have been cut. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-04's empty state |
| `signals.qs_05_empty` | Tidak ada sisa pembelian yang menunggu barang. | No purchase remainders awaiting goods. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-05's empty state |
| `signals.qs_06_empty` | Semua barang yang keluar sudah tercatat terkirim. | All dispatched goods are recorded as delivered. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-06's empty state |
| `signals.qs_07_empty` | Tidak ada kasus koreksi terbuka. | No open correction cases. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-07's empty state |
| `signals.qs_08_empty` | Semua PDF sudah tersedia. | All PDFs are available. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-08's empty state |
| `signals.qs_09_empty` | Tidak ada invoice yang belum ditagihkan. | No invoices that are not yet billed. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-09's empty state |
| `signals.qs_10_empty` | Tidak ada piutang yang lewat jatuh tempo. | No overdue receivables. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-10's empty state |
| `signals.qs_11_empty` | Tidak ada piutang dalam sengketa. | No disputed receivables. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-11's empty state |
| `signals.qs_12_empty` | Tidak ada kredit atau pembayaran yang perlu ditindaklanjuti. | No customer credit or payments that need follow-up. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-12's empty state |
| `signals.qs_13_empty` | Tidak ada potongan yang menunggu bukti. | No claimed deductions awaiting evidence. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-13's empty state |
| `signals.qs_14_empty` | Tidak ada aktivitas baru sejak Anda masuk sebelumnya. | No new activity since your previous sign-in. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-14's empty state |
| `signals.qs_15_empty` | Tidak ada proyek aktif yang terhambat. | No active projects are blocked. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-15's empty state |
| `signals.qs_16_empty` | Tidak ada kewajiban tersisa. | No remaining obligations. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-16's empty state |
| `signals.qs_17_empty` | Tidak ada stok di bawah minimum. | No stock below minimum. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-17's empty state |
| `signals.qs_18_empty` | Tidak ada temuan hitungan yang terbuka. | No open count findings. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-18's empty state |
| `signals.qs_19_empty` | Tidak ada Kuitansi untuk Proses Pembayaran yang menunggu. | No Kuitansi for the payment process awaiting its payment. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-19's empty state |
| `signals.qs_20_empty` | Tidak ada penolakan yang diteruskan dalam 14 hari terakhir. | No forwarded refusals in the last 14 days. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-20's empty state |
| `signals.qs_21_empty` | Tidak ada peringatan duplikat yang dikonfirmasi. | No confirmed duplicate warnings. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-21's empty state |
| `signals.qs_22_empty` | Tidak ada selisih yang menunggu penetapan. | No discrepancies awaiting attribution. | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-22's empty state |
| `signals.qs_23_empty` | Tidak ada proyek yang melewati tenggat. | No projects past their deadline. | validated · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-23's empty state |
| `signals.qs_24_empty` | Tidak ada barang yang siap dikeluarkan. | No goods ready for dispatch. | validated · en validated (PC-3) | INFORMATION_ARCHITECTURE §9.1 — QS-24's empty state |
| `states.group_reload` | Muat Ulang Bagian Ini | Reload this section | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9; DESIGN_SYSTEM PT-19 |
| `states.refresh` | Muat Ulang | Reload | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9 — Refresh |
| `states.updated_at` | Diperbarui {waktu} WIB | Updated {waktu} WIB | canonical · en validated (PC-3) | INFORMATION_ARCHITECTURE §9 — "Diperbarui 14.05 WIB" |
| `totals.estimate` | Perkiraan | Estimate | canonical · en validated | S2-J04 §8 |

## 7. Counts

Derived by script (SP-68), and again after Stage 4's fixes: **898 keys** (`DIR-100`; `DIR-101`) — 85 messages (MSG-01–MSG-41 each with its key, with its instances and second forms), 213 glossary keys (GL-001–GL-212 each with its key), 81 state labels, 2 sign-in answers, 16 document keys, 234 page keys and 267 shared keys. **Pages:** 65 of 65 with a title in both languages. **Indonesian:** 898 of 898 written. **English:** 898 of 898 written, 410 of them proposed by run PC-3 — CB-15's renderings of the 306 keys owed at PC-2's end (`DIR-079` CB-15), eleven keys after Stage 4 split five of them by state, and those of the 98 keys Stage 4 added or corrected —; 0 owed. **Validated:** 881 of 898 — every key of §5.1's review list, accepted as proposed in the Owner's single pass, none marked (`DIR-081` OB-06); the other 8 data or never translated. **Missing:** 0 — every key of this inventory holds both texts. The inventory holds the copy of §3.2's first row, the copy recorded in the gate record and the six journeys' copy. Outside it, keyed when P11 writes it, under GAP-037 and [OQ-24](stage2/OWNER_QUESTIONS.md#oq-24--copy-written-after-appr-011): the pattern and accessibility copy of DESIGN_SYSTEM §§9–17 — the outcome and state labels of §§12.2 and 12.3, the scanner's notices (§13.2), the print header and footer (§17) —; the labels ADMIN_FLOW defines beyond its message catalogue and the journeys — the correction entries of §11, the numbering statements of §12, the import actions of §13 and labels such as "Buka Catatan" and "Terapkan Ulang pada Versi Terbaru" —; the fields, column headings and helper lines of the pages beyond the six journeys; the per-case instances of the message patterns beyond the six journeys' (§6); and the single labels inside the glossary entries that join several, GL-159 "Tersimpan / Tercatat / Terbit" among them. **The review list (§5.1):** 881 keys — 301 with proposed Indonesian, 881 with proposed English —, 8 keys off it.