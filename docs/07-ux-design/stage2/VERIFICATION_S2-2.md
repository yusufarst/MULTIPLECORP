# S2-2 verification

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is the verification record of run S2-2 of Stage 2 of the P7 UX re-baseline, written under [DIR-066](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes) §§7 and 9 after the Owner's decisions [DIR-065](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes): every check of DIR-066 §7 per screen or journey with its result and evidence, the render list with its SHA-256 (SP-26), GR-16's two variants, and what stays for the Owner's preview and the phone test. The prototype lives in the private prototype repository ([the prototype](README.md#the-prototype)); it is neither a source of truth nor application code, its renders stay there, and this record keeps text only (P-4). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; nothing here is normative.

**Sources.** DIR-066 §7 — the checks — and SP-22–SP-27; [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md) §4 — the verification tooling —, §7 — the AK-5 checklist — and §8 — the screen list; the six journey specifications [S2-J01](journeys/S2-J01.md)–[S2-J06](journeys/S2-J06.md), each with its §6 checks and its §9 build notes; [DEVIATION_REGISTER](DEVIATION_REGISTER.md) and [SIMULATION_REGISTER](SIMULATION_REGISTER.md).

## 1. The build and the run

| Item | Value |
| --- | --- |
| Build checked | The private prototype repository's commit `a273207e3ea7c906e311cecd567c00ff19cf98ad` on `main` — parent `503e83bd3ed720f2af1d7c5eb4bc0f84500b9edb`, message `feat: build P7 Stage 2 journey prototypes (S2-2)`, 410 files —, its push left to the Owner under SP-27, the permission layer having refused it; the checks ran on the working tree that commit records, unchanged between the last run and the commit |
| Date and host | 2026-10-14 (WIB), on this computer only: the prototype's read-only server `tools/serve.mjs` on 127.0.0.1:8780; for SV-01 the reference served read-only on 127.0.0.1:8781 by a script outside both repositories |
| Browser | Google Chrome 155.0.8059.41 headless, a new temporary profile for each run, the DevTools protocol over a pipe; its version read with `Browser.getVersion`. The installed Chrome updated itself from 155.0.8059.39 during the run; every result below is from 155.0.8059.41 |
| Tooling | Node.js v24.18.0 built-ins, no package; `tools/verify/` — `cdp.mjs` (the pipe client), `screens.mjs` (the screen list), `page-checks.mjs` (the in-page checks), `render.mjs`, `static.mjs`, `journeys.mjs` with `journeys-b.mjs`, `focus.mjs`, `motion.mjs` and `sv01.mjs` |
| The reference (SP-25) | A temporary clone of tag v1.0.0 at `14497d997ee7994dfa9153d41b8cea636578e24c`, verified as DIR-064 §13.1 states — `SHA256SUMS-scope.txt` 41 OK and `SHA256SUMS-public.txt` 55 OK, both lists' own SHA-256 as stated there —, never altered, deleted afterwards; its renders not kept, its demonstration copy staying out (AK-5) |
| Afterwards | Every server and browser stopped; the temporary profiles, the clone and Google's archive deleted; `git status` of both working copies showing nothing of them |

**How a screen's state is reached.** Each screen of the list is a page of the prototype or a state of one, `#ps-NN` (PROTOTYPE_PLAN §9); a state reached by an action — PS-10's account menu, PS-11's logout — is opened by the same action before the checks; every job loads its page afresh.

## 2. The checks of DIR-066 §7

A failing check was fixed within scope and run again; §8 lists what the runs found and how each was fixed. No check was worked around and nothing was installed. The results below are the final runs on the build of §1.

| Check | Method | Result | Evidence |
| --- | --- | --- | --- |
| SV-01 — side by side with the reference; [DESIGN_SYSTEM_MAPPING §8](DESIGN_SYSTEM_MAPPING.md#8-the-fidelity-audit)'s fidelity audit | `sv01.mjs`: the computed properties of eleven shared components — the body text, the page title, the section card and its title, the field and its label, the primary and outline buttons, the sidebar, the app bar and the bottom bar — on the reference's public demonstration page and on the prototype's pages — S2-J06's payment form and list on the desktop, *Beranda* on the phone —, at 1440, 1366, 390 and 320 px; a brand-coloured property compared with the reference's token, its demonstration page re-theming the brand. The reference has no public sign-in page, so the sign-in screens are compared through its tokens | PASS — 118 measured properties equal; 10 differences, each registered — GR-01, the field's edge, at 1440 and 1366 px (2); GR-22, the phone bars' translucency, at 390 and 320 px (8) —; 0 outside the register | [DEVIATION_REGISTER](DEVIATION_REGISTER.md) |
| SV-02 — every screen at its viewports, no horizontal scroll except data tables | `render.mjs`: each screen at PROTOTYPE_PLAN §8.2's viewports — 1440×900, 1366×768, 390×844 and 320×640 — in both languages; the page's scroll width against its client width, a scrolling frame excepted | PASS — no page scrolls sideways in any of the 340 render jobs or the 42 reflow jobs | §3 |
| SV-03 — the type scale per viewport; AICWDF §18.2's checklist; GR-16's two variants | `render.mjs`: the computed sizes of body, title, section title, label, row title and button per job; the checklist's quality gate judged from the renders, below the table; variant B rendered at 1366×768 | PASS — the sizes per viewport in §3: body text 12.96 px at 1440 and 12.88 px at 1366 in variant A, 14 px in variant B and on the phone; AICWDF §18.2's quality gate below | §3; §6 |
| SV-04 — durations and easing against the tokens and VP-8; reduced motion | `motion.mjs`: the motion tokens as the page computes them, with and without `prefers-reduced-motion: reduce`; the running animations of a disclosure and of the sign-in's step measured against them; the prototype's stylesheet on the tokens only | PASS — 6 of 6: the override layer's transitions and animations on the motion tokens only; a disclosure opens in 240 ms on the reveal easing; the sign-in step moves in two 300 ms halves of its 600 ms token; under reduced motion nothing moves — the disclosure without animation, the step a 75 ms cross-fade within `--mc-dur-reduced` —; nothing decorative: no interval loop, `requestAnimationFrame` only for the file line's progress bar | — |
| SV-05 — contrast computed from renders: text ≥ 4.5:1, non-text ≥ 3:1 | `page-checks.mjs` in every render job: each shown text's computed colour against its background composited as rendered — the WCAG relative-luminance formula, transparent layers over the canvas —, 4.5:1, or 3:1 for large text; disabled controls and decorative text hidden from assistive technology left out; a field's edge against its surface, 3:1 (1.4.11). No text of the prototype sits on a gradient or an image, checked on every screen's first state at 1440 and 390 px | PASS — no text below its ratio and no field edge below 3:1 in the 340 render jobs | §3 |
| SV-05 — focus visible and not obscured | `focus.mjs`: dispatched Tab keys through every screen — the desktop screens at 1440×900, the phone screens at 390×844, in Indonesian —, each stop showing its own outline or shadow or its field's frame, and the element at its centre the focused one or inside it, so that no sticky bar hides it (2.4.7; 2.4.11) | PASS — 54 screens, 893 Tab stops, each visible and unobscured | — |
| SV-05 — targets 24/44/48 px | `page-checks.mjs` in every render job: every interactive element's box — a field by its frame, a hit area drawn by `::before` or `::after` counted —, 24 px with 2.5.8's spacing exception, 44 px on the phone and with a coarse pointer (GR-07), 48 px on S2-J05's floor screens except the bottom bar, the app bar, a sheet's head and the drawer | PASS — no target below its size in the 340 render jobs | §3 |
| SV-05 — a keyboard path through every journey | `journeys.mjs` types into every field and the scan field and confirms by Enter, step by step; `focus.mjs` walks every screen by Tab; `panel.mjs` reaches the marker and the panel by Tab, Enter and Escape | PASS — every journey driven by typing and Enter (222 checks); every screen walked by Tab; the panel reached by Tab, Enter and Escape (146 jobs) | §4 |
| SV-05 — text spacing and reflow | `render.mjs --spacing --reflow`: in every job but variant B, WCAG 2.2 1.4.12's spacing — line height 1.5, letter spacing 0.12 em, word spacing 0.16 em, paragraph spacing 2 em — injected, then the clipped copy and the horizontal scroll measured again; the desktop-only screens also at 320×640, judged on horizontal scroll and clipped copy (1.4.10) | PASS — no clipped copy and no sideways scroll in the 342 jobs under 1.4.12's spacing — the 300 renders in variant A and the 42 reflow jobs at 320×640, both languages | §3 |
| SV-05 — status messages (4.1.3) and never colour alone (1.4.1) | `page-checks.mjs` in every render job: each notice and saved state inside a status or alert region; every status pill naming its state in words | PASS — every notice and saved state in a status region and every pill named, in the 340 render jobs | §3 |
| SV-06 — the render files and their SHA256SUMS (SP-26) | `render.mjs`: one PNG per render job, full height — the viewport grown to the document, so that fixed bars sit at its foot —, named `<PS id>_<lang>_<viewport>[_b].png`; `SHA256SUMS.txt` written over the folder | PASS — 340 renders; `SHA256SUMS.txt`, SHA-256 `53964bb36493ccb5cadb0ec7a967a35512e2c3ee8b71cdf79e86e9d914ab0830`, `sha256sum -c` 340 OK | §5 |
| SS-01–SS-08 | In every render, journey and panel job: no request beyond 127.0.0.1 (SS-05), no console error or warning and no exception, both storages empty (SS-07; SP-13), `history.state` holding only the vendored keys `mcNavDrawer` and `ptSheet` (PROTOTYPE_PLAN §2.4); the marker shown on every screen and the panel opening from it (SS-08, `panel.mjs`); Google sign-in simulated, with no request to Google (SS-03); `static.mjs`: e-mail addresses under example domains only and no `multiplecorp.id` address (SS-02), storage APIs only in the storage guard; a scan of the files written new: no credential, key, token or password value, and no address requested beyond 127.0.0.1 — the SVG namespace and the source notes' addresses only —; the server on 127.0.0.1 only and stopped after each run | PASS — in the 382 render and reflow jobs, the 222 journey checks and the 146 panel jobs: no request beyond 127.0.0.1, no console error, warning or exception, both storages empty, `history.state` as the vendored keys; the marker on every screen and the panel opening from it; the static scan clean | §3; §4 |
| AK-5 — PROTOTYPE_PLAN §7's checklist, the Google "G" and the Lucide symbols its allowed sources; the vendored files 15 OK | `static.mjs --ref`: the vendored files against `VENDOR_SHA256SUMS.txt`; every file written new against §7's patterns — the example pages' CSS prefixes, the persona Sari, `multiplecorp.id`, `data-page` — and against the reference's demonstration values: the route identifiers of its `project-config.js`, read from the verified clone, and the fixtures of the vendored `interactions.js`, with §7's named exclusions; `icons.js`, `page-skeleton.html` and `project-config.js` absent; the sprite's symbols only Lucide's; the Google "G" only on S2-J01's sign-in page | PASS — 16 of 16: the vendored files 15 of 15 OK; no example page's prefix, persona or address; the reference's 43 route identifiers and 47 fixture values absent, §7's named exclusions applied; `icons.js`, `page-skeleton.html` and `project-config.js` absent; 65 symbols, Lucide's only; the Google "G" on `masuk.html` only, in both languages; storage APIs only in the guard; 23 page pairs with the same keys; 72 text files outside `site/vendor/` — the 69 authored files of TECH-033's table, `SHA256SUMS.txt`, and S2-0's `.gitattributes` and `tools/serve.mjs` — UTF-8, LF, no byte-order mark | — |
| The journeys end to end in both languages, against their specifications | `journeys.mjs` with `journeys-b.mjs`: each step of the six trace matrices — S2-J05's S2-R01–S2-R09 and S2-J06's numeric cases among them —, at 1366×768 and on the phone (390×844; S2-J05 also at 320×640), each journey closed by its hygiene check | PASS — 222 checks in both languages: S2-J01 48, S2-J02 22, S2-J03 26, S2-J04 20, S2-J05 84 — S2-R01–S2-R09 among them —, S2-J06 22 — its numeric cases among them —, each run closed by its hygiene check | §4 |
| 0 missing strings; every link and control resolving | The copy dictionary's check — every keyed string of every page against the dictionary: 3,662 keyed strings, 0 missing, 0 differing —; `render.mjs`'s unkeyed text and attribute checks per job; `static.mjs`: the keys of `/id/` and `/en/` equal, no keyed string empty, every link resolving to a page of the prototype | PASS — 3,662 keyed strings, 0 missing and 0 differing from the dictionary; no unkeyed text or accessible name in any render job; every link and source resolving inside `site/` | §3 |
| The phone test (OQ-07) | Not run in S2-2: the Owner's decision OQ-07 (a) places it at the Owner's preview; S2-2 checks the phone screens by emulation only | Not run, by decision | §7 |

**AICWDF §18.2's quality gate (SV-03)**, judged at design level from the renders, with the measurements behind each item; the Owner's preview judges it finally (SD-08):

| Item | Result | Basis |
| --- | --- | --- |
| VISUAL BREATHING ROOM | PASS | the reference's page margins, card padding and gaps on every screen, sections as cards |
| CONTENT DENSITY APPROPRIATE | PASS | the reference's comfortable density on every screen; lists and tables only where the work is a list |
| AUTH SCREEN SIMPLICITY | PASS | S2-J01: one task per step — the sign-in form with its Google option, the code, the recovery —, the language switch and the marker beside it |
| GOOGLE LOGIN VISUAL CONSISTENCY | PASS | the reference's outline button with Google's authentic "G" on white, beside the form's own button style; its differences from Google's rules recorded as GR-32 |
| SECTION SEPARATION | PASS | cards, section titles and the reference's dividers; the phone's groups as folds |
| SPACING CONSISTENCY | PASS | the override layer's 227 spacing declarations all on the reference's 2 px step but five — four optical offsets of 3 px (the phone marker's two lines, twice; the stage indicator's segments; the ageing chart's keys) and the hidden-text utility's −1 px margin |
| READABILITY | PASS | the type scale of §3; copy that wraps and is never cut (GR-35); every text at its contrast; the tables' figures in tabular figures |

## 3. Per screen

Each render job — a screen in one language at one viewport — passed every in-page check of `page-checks.mjs`: no horizontal scroll, no clipped copy, no unkeyed text or accessible name, no text below its contrast, no field edge below 3:1, no target below its size, every notice in a status region, every pill named, the marker shown; no request beyond 127.0.0.1, no console error or exception, both storages empty and `history.state` as the vendored keys. "Spacing" counts the jobs passing again under 1.4.12's spacing; "Reflow" the desktop-only screens at 320×640 (1.4.10); "Focus" the Tab stops `focus.mjs` walked; "Panel" the jobs of `panel.mjs`.

| Screen | Journey | Page and state, under `site/id/` and `site/en/` | Viewports | Renders | Spacing | Reflow | Focus | Panel |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| PS-01 | S2-J01 | `masuk.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 9 | 4 |
| PS-02 | S2-J01 | `masuk.html` `#ps-02` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 9 | 4 |
| PS-03 | S2-J01 | `masuk.html` `#ps-03` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 6 | 4 |
| PS-04 | S2-J01 | `masuk.html` `#ps-04` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 6 | 4 |
| PS-05 | S2-J01 | `masuk.html` `#ps-05` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 5 | 4 |
| PS-06 | S2-J01 | `verifikasi-dua-langkah.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 4 | 4 |
| PS-07 | S2-J01 | `masuk.html` `#ps-07` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 6 | 4 |
| PS-08 | S2-J02 | `laras/beranda.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 31 | 4 |
| PS-09 | S2-J03 | `dimas/pekerjaan.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 18 | 4 |
| PS-10 | S2-J01 | `dimas/pekerjaan.html` — the account menu opened | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | its page | its page |
| PS-11 | S2-J01 | `dimas/pembelian-baru.html` — the logout asked | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | its page | its page |
| PS-12 | S2-J02 | `laras/beranda.html` `#ps-12` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 28 | 4 |
| PS-13 | S2-J02 | `laras/beranda.html` `#ps-13` | 390×844, 320×640 | 4 | 4 | — | 19 | 2 |
| PS-14 | S2-J02 | `laras/piutang.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 28 | 4 |
| PS-15 | S2-J02 | `laras/proyek.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 20 | 4 |
| PS-16 | S2-J03 | `dimas/pekerjaan.html` `#ps-16` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 20 | 4 |
| PS-17 | S2-J03 | `dimas/proyek.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 19 | 4 |
| PS-18 | S2-J03 | `dimas/proyek.html` `#ps-18` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 19 | 4 |
| PS-19 | S2-J03 | `dimas/invoice-baru.html` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 29 | 2 |
| PS-20 | S2-J03 | `dimas/invoice-baru.html` `#ps-20` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 29 | 2 |
| PS-21 | S2-J03 | `dimas/invoice-baru.html` `#ps-21` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 4 | 2 |
| PS-22 | S2-J03 | `dimas/invoice-baru.html` `#ps-22` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 4 | 2 |
| PS-23 | S2-J03 | `dimas/invoice-baru.html` `#ps-23` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 25 | 2 |
| PS-24 | S2-J03 | `dimas/invoice.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 21 | 4 |
| PS-25 | S2-J04 | `dimas/pembelian-baru.html` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 27 | 2 |
| PS-26 | S2-J04 | `dimas/pembelian-baru.html` `#ps-26` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 40 | 2 |
| PS-27 | S2-J04 | `dimas/pembelian-baru.html` `#ps-27` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 45 | 2 |
| PS-28 | S2-J04 | `dimas/pembelian.html` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 20 | 2 |
| PS-29 | S2-J04 | `dimas/pembelian.html` `#ps-29` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 4 | 2 |
| PS-30 | S2-J04 | `dimas/pembelian.html` `#ps-30` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 22 | 2 |
| PS-31 | S2-J05 | `bayu/pekerjaan.html` | 390×844, 320×640 | 4 | 4 | — | 12 | 2 |
| PS-32 | S2-J05 | `bayu/barang-masuk.html` | 390×844, 320×640 | 4 | 4 | — | 9 | 2 |
| PS-33 | S2-J05 | `bayu/barang-masuk.html` `#ps-33` | 390×844, 320×640 | 4 | 4 | — | 9 | 2 |
| PS-34 | S2-J05 | `bayu/barang-masuk.html` `#ps-34` | 390×844, 320×640 | 4 | 4 | — | 10 | 2 |
| PS-35 | S2-J05 | `bayu/barang-masuk.html` `#ps-35` | 390×844, 320×640 | 4 | 4 | — | 3 | 2 |
| PS-36 | S2-J05 | `bayu/barang-masuk.html` `#ps-36` | 390×844, 320×640 | 4 | 4 | — | 8 | 2 |
| PS-37 | S2-J05 | `bayu/barang-masuk.html` `#ps-37` | 390×844, 320×640 | 4 | 4 | — | 8 | 2 |
| PS-38 | S2-J05 | `bayu/barang-masuk.html` `#ps-38` | 390×844, 320×640 | 4 | 4 | — | 7 | 2 |
| PS-39 | S2-J05 | `bayu/barang-masuk.html` `#ps-39` | 390×844, 320×640 | 4 | 4 | — | 12 | 2 |
| PS-40 | S2-J05 | `bayu/barang-masuk.html` `#ps-40` | 390×844, 320×640 | 4 | 4 | — | 3 | 2 |
| PS-41 | S2-J05 | `bayu/barang-masuk.html` `#ps-41` | 390×844, 320×640 | 4 | 4 | — | 14 | 2 |
| PS-42 | S2-J05 | `bayu/barang-masuk-0213.html` | 390×844, 320×640 | 4 | 4 | — | 8 | 2 |
| PS-43 | S2-J05 | `bayu-r06/barang-masuk.html` | 390×844, 320×640 | 4 | 4 | — | 8 | 2 |
| PS-44 | S2-J05 | `bayu/pekerjaan.html` `#ps-44` | 390×844, 320×640 | 4 | 4 | — | 13 | 2 |
| PS-45 | S2-J06 | `dimas/pembayaran.html` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 22 | 2 |
| PS-46 | S2-J06 | `dimas/pembayaran-baru.html` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 28 | 2 |
| PS-47 | S2-J06 | `dimas/pembayaran-baru.html` `#ps-47` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 31 | 2 |
| PS-48 | S2-J06 | `dimas/pembayaran-baru.html` `#ps-48` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 4 | 2 |
| PS-49 | S2-J06 | `dimas/pembayaran.html` `#ps-49` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 23 | 2 |
| PS-50 | S2-J06 | `dimas/pembayaran-baru.html` `#ps-50` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 31 | 2 |
| PS-51 | S2-J06 | `dimas/pembayaran-baru.html` `#ps-51` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 14 | 2 |
| PS-52 | S2-J06 | `dimas/pembayaran-baru.html` `#ps-52` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 31 | 2 |
| PS-53 | S2-J06 | `dimas/pembayaran.html` `#ps-53` | 1440×900, 1366×768 | 5 (1 in variant B) | 6 | 2 | 23 | 2 |
| Out of scope | — | `dimas/luar-cakupan.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 12 | 4 |
| Out of scope, outside the shell | — | `luar-cakupan.html` | 1440×900, 1366×768, 390×844, 320×640 | 9 (1 in variant B) | 8 | — | 4 | 4 |
| Start page | — | `site/index.html`, one page for both languages | 1440×900, 1366×768, 390×844, 320×640 | 4 | 4 | — | 29 | 4 |

In all 340 renders, 342 spacing passes, 42 reflow jobs, 54 screens walked by Tab and 146 panel jobs, every one passing.

**The type scale (SV-03)**, the computed sizes per viewport across every screen — size, or size/weight:

| Viewport | Body | Page title | Section title | Field label | Row title | Button |
| --- | --- | --- | --- | --- | --- | --- |
| 1440×900 | 12.96px, 14px | 25.92px/700, 27.38px/700, 28px/700, 35px/700 | 15.27px/700 | 12.03px/400, 16px/600 | 12.96px/600 | 12.96px/600, 13px/600, 14px/600, 18px/600 |
| 1366×768 | 12.88px, 14px | 25.54px/700, 25.76px/700, 28px/700, 35px/700 | 15.18px/700 | 11.96px/400, 16px/600 | 12.88px/600 | 12.88px/600, 13px/600, 14px/600, 18px/600 |
| 390×844 | 14px | 25px/700, 28px/700 | 16.5px/700 | 13px/400, 16px/600 | 14px/600 | 13px/600, 14px/600, 18px/600 |
| 320×640 | 14px | 25px/700, 28px/700 | 16.5px/700 | 13px/400, 16px/600 | 14px/600 | 13px/600, 14px/600, 18px/600 |
| 1366×768, variant B | 14px | 25.54px/700, 28px/700, 35px/700 | 16.5px/700 | 13px/400, 16px/600 | 14px/600 | 13px/600, 14px/600, 18px/600 |

## 4. Per journey

`journeys.mjs` walked each journey end to end in both languages, each step checked against its specification's trace matrix and §6 checks; the runs are named by viewport width and language. Every check passed.

**[S2-J01](journeys/S2-J01.md)** — 48 checks:

| Step checked | Runs |
| --- | --- |
| PS-01 the sign-in form | 1366 id, 390 id, 1366 en, 390 en |
| PS-02 MSG-19 for a wrong password, nothing else | 1366 id, 390 id, 1366 en, 390 en |
| PS-03 the code step | 1366 id, 390 id, 1366 en, 390 en |
| PS-04 the generic code answer | 1366 id, 390 id, 1366 en, 390 en |
| PS-08 Laras lands on Beranda | 1366 id, 390 id, 1366 en, 390 en |
| PS-05 the recovery code | 1366 id, 390 id, 1366 en, 390 en |
| PS-06 PG-004 after the recovery code | 1366 id, 390 id, 1366 en, 390 en |
| PS-07 Google, then the code without the recovery link | 1366 id, 390 id, 1366 en, 390 en |
| PS-09 Dimas lands on Pekerjaan | 1366 id, 390 id, 1366 en, 390 en |
| PS-10 the account menu | 1366 id, 1366 en |
| PS-11 MSG-35 when unsent input meets Keluar | 1366 id, 1366 en |
| Keluar -> PS-01 | 1366 id, 1366 en |
| Keluar with nothing unsent asks nothing (GR-09) | 1366 id, 1366 en |
| hygiene: storage 0/0, history keys, no outbound request, no console error | 1366 id, 390 id, 1366 en, 390 en |

**[S2-J02](journeys/S2-J02.md)** — 22 checks:

| Step checked | Runs |
| --- | --- |
| PS-08 Gabungan | 1366 id, 390 id, 1366 en, 390 en |
| PS-12 BTN alone, the ageing chart with its table | 1366 id, 390 id, 1366 en, 390 en |
| PS-14 receivables | 1366 id, 390 id, 1366 en, 390 en |
| PS-15 the journey with its current stage | 1366 id, 390 id, 1366 en, 390 en |
| hygiene: storage 0/0, history keys, no outbound request, no console error | 1366 id, 390 id, 1366 en, 390 en |
| PS-13 every decision on the phone | 390 id, 390 en |

**[S2-J03](journeys/S2-J03.md)** — 26 checks:

| Step checked | Runs |
| --- | --- |
| PS-16 Penyelesaian proyek open | 1366 id, 390 id, 1366 en, 390 en |
| PS-17 now, next, blockers | 1366 id, 390 id, 1366 en, 390 en |
| PS-19 the draft | 1366 id, 1366 en |
| PS-20 the draft saved | 1366 id, 1366 en |
| PS-21 MSG-39 | 1366 id, 1366 en |
| PS-22 the number with "Simulasi" | 1366 id, 1366 en |
| PS-23 PDF ready | 1366 id, 1366 en |
| PS-24 the issued invoice | 1366 id, 1366 en |
| hygiene: storage 0/0, history keys, no outbound request, no console error | 1366 id, 390 id, 1366 en, 390 en |
| PS-17 on the phone: the desk-only line, no control | 390 id, 390 en |

**[S2-J04](journeys/S2-J04.md)** — 20 checks:

| Step checked | Runs |
| --- | --- |
| PS-25 prefilled: line 1 at 15, the save disabled with its reason | 1366 id, 1366 en |
| the supplier by lookup (IP-17) | 1366 id, 1366 en |
| the price shown formatted on leaving the field (IP-13) | 1366 id, 1366 en |
| PS-26 four lines, Perkiraan Rp224.442.000 | 1366 id, 1366 en |
| PS-27 the charge with its source bill: Rp225.692.000 | 1366 id, 1366 en |
| the back action asks MSG-35 "tinggalkan halaman" | 1366 id, 1366 en |
| PS-28 Terbuka, the answer (no cash, no stock), the server figures | 1366 id, 1366 en |
| PS-29 MSG-39 with the layout PO | 1366 id, 1366 en |
| PS-30 ARJ/PB/2026/0216 with "Simulasi", the PDF pending | 1366 id, 1366 en |
| hygiene: storage 0/0, history keys, no outbound request, no console error | 1366 id, 1366 en |

**[S2-J05](journeys/S2-J05.md)** — 84 checks:

| Step checked | Runs |
| --- | --- |
| PS-31 the "Berikutnya" card | 390 id, 320 id, 390 en, 320 en |
| PS-32 the scan field focused; the bottom bar hidden (GR-11) | 390 id, 320 id, 390 en, 320 en |
| PS-33 / S2-R01 the offer empty; the field cleared | 390 id, 320 id, 390 en, 320 en |
| S2-R04 the limit 15 from the 18 usable units | 390 id, 320 id, 390 en, 320 en |
| S2-R05 16 sent and REJECTED at the field; nothing recorded | 390 id, 320 id, 390 en, 320 en |
| PS-35 IP-08 names the outcomes, the rack and the reservation | 390 id, 320 id, 390 en, 320 en |
| S2-R09 no reservation before the answer | 390 id, 320 id, 390 en, 320 en |
| PS-36 / S2-R07 one answer: the receipt and 15 reserved | 390 id, 320 id, 390 en, 320 en |
| PS-37 / S2-R03 no offer on a line without a project line | 390 id, 320 id, 390 en, 320 en |
| PS-38 "Cari Produk" by name | 390 id, 320 id, 390 en, 320 en |
| PS-39 four lines, four answers (IP-19), the summary | 390 id, 320 id, 390 en, 320 en |
| PS-40 MSG-39 for the Nota | 390 id, 320 id, 390 en, 320 en |
| PS-41 ARJ/NTB/2026/0057 with "Simulasi" | 390 id, 320 id, 390 en, 320 en |
| S2-R01 the offer left empty: nothing reserved in the question | 390 id, 320 id, 390 en, 320 en |
| S2-R08 PS-44 with QS-03's row | 390 id, 320 id, 390 en, 320 en |
| PS-42 / S2-R02 no offer: the quotation is SENT | 390 id, 320 id, 390 en, 320 en |
| PS-42 recorded | 390 id, 320 id, 390 en, 320 en |
| PS-43 / S2-R06 no offer without stock.reserve | 390 id, 320 id, 390 en, 320 en |
| S2-R06 recorded normally, then PS-44 | 390 id, 320 id, 390 en, 320 en |
| PJ-18 no price on Bayu's screens | 390 id, 320 id, 390 en, 320 en |
| hygiene: storage 0/0, history keys, no outbound request, no console error | 390 id, 320 id, 390 en, 320 en |

**[S2-J06](journeys/S2-J06.md)** — 22 checks:

| Step checked | Runs |
| --- | --- |
| PS-45 the list, newest first | 1366 id, 1366 en |
| PS-47 / BR-FIN-07 the client's issued invoices of ARJ only, 0141 as Belum Ditagihkan | 1366 id, 1366 en |
| PS-50 REJECTED above the outstanding Rp250.000, the input kept | 1366 id, 1366 en |
| Kredit Pelanggan Perkiraan 300.000 - 550.000 | 1366 id, 1366 en |
| PS-51 REJECTED above the payment Rp300.000 | 1366 id, 1366 en |
| BR-FIN-05 Kredit Pelanggan Rp0 with Rp300.000 applied to 0128 | 1366 id, 1366 en |
| PS-48 IP-08's confirmation | 1366 id, 1366 en |
| PS-49 / numeric annex item 1: Rp700.000 outstanding, one answer | 1366 id, 1366 en |
| PS-52 / L-29 the reason required before sending | 1366 id, 1366 en |
| PS-53 flagged Tanpa Bukti, with its answer | 1366 id, 1366 en |
| hygiene: storage 0/0, history keys, no outbound request, no console error | 1366 id, 1366 en |

## 5. The render list (SP-26; SV-06)

`evidence/s2-2/SHA256SUMS.txt` in the prototype repository, in full — 340 renders, each named `<PS id>_<lang>_<viewport>[_b].png` — the out-of-scope page as `OOS` in the shell and `OOS-bare` outside it, the start page as `START` —; the list's own SHA-256 is `53964bb36493ccb5cadb0ec7a967a35512e2c3ee8b71cdf79e86e9d914ab0830`.

```text
c7126c4059ce9bb2fb43b80141d2441982db1bf33f43110e97fa51d9b6e70fb5  OOS-bare_en_1366x768.png
fe6f7caec9d4de62c5bfa616950c33df931d75da85b81eab40070f062c405fb4  OOS-bare_en_1440x900.png
a6be26982aa0495df49702efe39192c71f54e27eeee1ae01c8a3b3ebc8214db2  OOS-bare_en_320x640.png
a136bb5391f2592409a1243e5b0a642b53481c5c47da3d6e03beaff68c357ade  OOS-bare_en_390x844.png
59e7cf0ac1f7be64058ea35b54c471d12c5bd0ee3d575ab95bf87bbc3ff3d691  OOS-bare_id_1366x768.png
59e7cf0ac1f7be64058ea35b54c471d12c5bd0ee3d575ab95bf87bbc3ff3d691  OOS-bare_id_1366x768_b.png
e55d482dc442aba858b4c51895addb17a75140b3c4cb1ad6b801b384711c9242  OOS-bare_id_1440x900.png
a532d22804f10c06214cf96b8424cafe8e32d735f6f6b38e74c2ed826265d078  OOS-bare_id_320x640.png
91e19170a05fead08b917b9c67f6b0613d824fc4395838c06afe9a50d2070f52  OOS-bare_id_390x844.png
cda31e25ab5d6b03d3810893473096a2d86c7e5742c21c4f374545451f1a3bd7  OOS_en_1366x768.png
5617e1bc2eaf0e1e2aae9b5211e942bcc3c6d427762ac70970223bc3ff1abf48  OOS_en_1440x900.png
0d8f9d34c044e92b5e37d8ba7721d50db4328824012278f2c7207aca53cb726c  OOS_en_320x640.png
4a57599380d2bc77862c44100a1dfa5002edf5c596b63ffdef992e0fdaf7d42a  OOS_en_390x844.png
ae221f20a99e3966a51fba35a3e659399161552ced4d90673855b83da7df1818  OOS_id_1366x768.png
ce5fc518d3a0701b91a2a1e2e0471fee2f35897b61b3981fe48b1f9926a5be14  OOS_id_1366x768_b.png
f2ecbc74a6c3f319b0224f71d2a364f40a8dcf75e897a55bcb6601f8314e0df9  OOS_id_1440x900.png
549ce5b8ed8c30cecb14fab210c0e4bc7494e6199cabdf6b186639cc89d0f84a  OOS_id_320x640.png
4b5bcb716b9ea445f068d220c8966e5b7779afe2685dda6e08b9144d7a47e4db  OOS_id_390x844.png
f1c82522ecef752c602e623cc5a0493d89859db05584ca11f0db33787b67421e  PS-01_en_1366x768.png
099fedd7038926723a1c154afe1fce5b7c9a09e163d02f0785c07b6470de9c5b  PS-01_en_1440x900.png
3a5ed606ede238bfcc5b8692ec91f918547feadce7c33c9d090866fe63ff2a78  PS-01_en_320x640.png
125af02c640f3cca6b3caf709d11f900e108d4bbdb5de248a63a76e148376f5c  PS-01_en_390x844.png
17a2b076d1dc17fb1670432d642b70d16a2d904a9bb86c45b426896713d2cbe3  PS-01_id_1366x768.png
17a2b076d1dc17fb1670432d642b70d16a2d904a9bb86c45b426896713d2cbe3  PS-01_id_1366x768_b.png
b07e8ececfb9ee57c0c8f194b55eb0cbac8bb167ebb1870948bf80cf8f4ddb6d  PS-01_id_1440x900.png
bfb8320d8aab369dbfee512f408c29c83708cda51cad128514b6552ebc71cbb1  PS-01_id_320x640.png
b0876ecc9d76c9d523ec61ed4eaaf0a55956cfc0ba579a11901aaba5b35aeb9e  PS-01_id_390x844.png
765c246290765b4757209824b37ccb2a3481849df293dce9fdc8f6e9390245cb  PS-02_en_1366x768.png
232dafe1590cd2cfbac2fb893684441cfe9de50297f190e20cc15e6baa313abe  PS-02_en_1440x900.png
94819e267354d4a162383e204196f2c73a71ba31c7cbdf97bbe0b19050ed15b6  PS-02_en_320x640.png
513a482052b48dd8bab010c7df40f379ad2c6b3ba3df3698243522f17444ea85  PS-02_en_390x844.png
bc131eaea95061ecc457802d43fcc60278e98fb41308ea41cf68b146308f7801  PS-02_id_1366x768.png
bc131eaea95061ecc457802d43fcc60278e98fb41308ea41cf68b146308f7801  PS-02_id_1366x768_b.png
cf08f0c85ef5c976fb9803e648b6fc80d91e57835284301d449bec4ffed1107a  PS-02_id_1440x900.png
26bbf2672e68c6385bc51b52b30e6c66002a8982c0ec18f830de36de74557816  PS-02_id_320x640.png
a2d800caf7d98161cbe6c0050ba8a2ccebd69751e1029c394ca9fc0ee17c3951  PS-02_id_390x844.png
53422f86d1c9482cb749e93cc17b7453dda976ed6e853c46733833b2c7f0ee2e  PS-03_en_1366x768.png
5aa3a97cd3dfb7e0ad9e76b238c43f86195e02a5ea28f5d89a76b92cc315735c  PS-03_en_1440x900.png
91edcb48a9c336f71f71798ab4f056f68d06de7bd2c8457863764116452af281  PS-03_en_320x640.png
33dc195be956decbe10588a0f4b138b902f335e6066b4167edf51f29895d20ed  PS-03_en_390x844.png
731ca785393a441d9b6015f30fb82bdda961461b7fcb3638675acddfc7d44957  PS-03_id_1366x768.png
731ca785393a441d9b6015f30fb82bdda961461b7fcb3638675acddfc7d44957  PS-03_id_1366x768_b.png
049f71081031bdf0bb15e1e0e0ba6d934dba661e1385e8a0c301c31be2dd83bd  PS-03_id_1440x900.png
6e2407bcdea538ea45774c52ccde66215c860533743ecec57b6a0bd0563649aa  PS-03_id_320x640.png
652e8f78c3d262c2608619db13b0b03063b4b5adea4af6ff548264658cad65e1  PS-03_id_390x844.png
cd6ec10c930cfe31cf7d930a2ff57f2f28bf180d8b89ad46415778a3092cef87  PS-04_en_1366x768.png
b0799c6f18b52b1a516618ead793f5fa772bc7985dd4bd71a7dcaead4aa29bab  PS-04_en_1440x900.png
a832264547ac0466883c231b841897e78bafccda6f21fc6ec1528ad856dd7636  PS-04_en_320x640.png
65edae20004e42d25df2f9c43d0388acf8a2a624568222093c2d795fa0f4d827  PS-04_en_390x844.png
52e0a5466859a845d5ccfe7d3624c2f1bb9e0e206bc09fc5d2cbdef602acf44e  PS-04_id_1366x768.png
52e0a5466859a845d5ccfe7d3624c2f1bb9e0e206bc09fc5d2cbdef602acf44e  PS-04_id_1366x768_b.png
1fbc3745b66e8b7997e8b52ec55baff9857d5c742f67d6f16abc04347a67bac7  PS-04_id_1440x900.png
52ef25a73aa82af126a2eacbadca76ad71d5a99f54f2e7df860b35bd0601465b  PS-04_id_320x640.png
5d2278aac3bd17bb0d079f9a1f3a0c1b9744e1d20f3c4f822638f3dd4787ffe8  PS-04_id_390x844.png
376145deae1d32a7c57c651d529d29537c3f3d742906e286a55a3f0e2dc6622e  PS-05_en_1366x768.png
c181240145227f3af92a13b7e8ab01e962ff5614f385262517bd49f5cfdbd8f0  PS-05_en_1440x900.png
a336bf7ad97da88504dab1730863f560e40be9d98223baa7abd5bfc6d4fd6976  PS-05_en_320x640.png
c62aad09955f2831e935b7e6dcfa74e6d8c486d1da84670fea350dc0af850d66  PS-05_en_390x844.png
1818132ed3f175f4155eccdac61b4a9f1a58cbe34b27b3cac93bbbef4a0ad57f  PS-05_id_1366x768.png
1818132ed3f175f4155eccdac61b4a9f1a58cbe34b27b3cac93bbbef4a0ad57f  PS-05_id_1366x768_b.png
f166091942646bcc6d9d46eb12444328efef8027d00a1d80c4d9af06cce55947  PS-05_id_1440x900.png
6adced0709a18830f91cb5d95108c73e65bb46d1bbaaabced93b2e9244d9389b  PS-05_id_320x640.png
ad0e09b50bab84a991a58f46aa8f2db13d2896643f3dca2afe461a2274168807  PS-05_id_390x844.png
a661b7a13c1a04fa68bbd14cd18dba0a88af88ff5d35362730f5757cb76e9181  PS-06_en_1366x768.png
7916379f2ab9a711de632addd20b23aa9958096dab0bd4a0928a8ae58b3e286e  PS-06_en_1440x900.png
13177efcc1c45425e2cf52d8734a467cf9f39b6c3557c5aae8ce7afd4d33c020  PS-06_en_320x640.png
6f8e6c0f671900e9e140e72845f2b25e89516b2a62b8aa96df17230ec92363bf  PS-06_en_390x844.png
886024ad6b37c2402019a18b1f4472fad314db29173d73f100f35d03020bf0a6  PS-06_id_1366x768.png
886024ad6b37c2402019a18b1f4472fad314db29173d73f100f35d03020bf0a6  PS-06_id_1366x768_b.png
0c3511431f923a07a2eee366804764bec95450d6d345c29a6ebb68bebb32eedb  PS-06_id_1440x900.png
247cea80302338173044a023d54ed248d67df5b0e8e8a7a565ac65953734c9e4  PS-06_id_320x640.png
70287cf8a1224b87b4b078376291275f2c8e60fd1a947c3c769f89e7d42a43eb  PS-06_id_390x844.png
88e2ddee67db5e2e4308b8aa85ec85f2ae3e3bd1a4f691361e40a4e8770a6277  PS-07_en_1366x768.png
677303e739c730517354383799f7c1f0044ca1831f759e97936247ba5dca2722  PS-07_en_1440x900.png
b11a637fd3b4ceec4455b908aa5b7ee7c6983953505661c4cad160547393792d  PS-07_en_320x640.png
4ff212d5412d86f46b31b4a2d04a1f61afd012c4089d1338ec4d9bfcd68cc74e  PS-07_en_390x844.png
9b7aab5e7788b35d7f68375f54ac8a3be7acf48e6e3a3b8f03915a51c58c0681  PS-07_id_1366x768.png
9b7aab5e7788b35d7f68375f54ac8a3be7acf48e6e3a3b8f03915a51c58c0681  PS-07_id_1366x768_b.png
b284c48977f9c0193d3d6a3781a62c57e2a999d9b9ee4c7bd8720077da2645b8  PS-07_id_1440x900.png
920d0b3aedae0a40f2c644bc77abb0eb41c00dea708620e5eb4ee62ad413f66a  PS-07_id_320x640.png
48f2d27093232e30b6a264e63e65efc9a031971790158f257746a37cff8869b4  PS-07_id_390x844.png
a2f12e4aecb353a521d9c9d2c2e42240a11a857c8afce1c88aa40683c7f35e68  PS-08_en_1366x768.png
46287bf8b263e28573adecb56d5c924d35a941b7d88147daf543642521c79311  PS-08_en_1440x900.png
d14fc19a768ad3d0c08c8f3d7a3efb37fc29054f78bb8fb6a2618fda525d8182  PS-08_en_320x640.png
da50181f9503589c2f34c392d341a63f57e1cdf3b53a6252000cb78227cb6c99  PS-08_en_390x844.png
c0bb498147b0d634c37bc88381fc8dcd41d9d0d9fc0f8ec775f2daf5462c9d29  PS-08_id_1366x768.png
4882c54e602a67b35e9ed5bc82d8893fde2097ae17100f72d53055d3e6b1d343  PS-08_id_1366x768_b.png
bc1bd930fba2a3eefe47fd8d6a3ad0409cee3d24250f3a1e83c222e88533d3a1  PS-08_id_1440x900.png
5669482b2d43f4d78517ba828f2aaa38c5ef98d7cbb803c0f5b5c7756967def3  PS-08_id_320x640.png
89fef7e489f42f03477f87f5d3d4da23ad448f1e605ae0f09fd8d664d3f8cd7e  PS-08_id_390x844.png
a6c742ab5556b920ba6d0d970ad0b47d8fea30343974c2e960f2ff6993ef1e1e  PS-09_en_1366x768.png
5ab2b51b72008a6af7e4a7034d55a76e2e313a1f9c8d2f022a4cc5b77b3b35cb  PS-09_en_1440x900.png
11a58d8f783289e71b31b5d28d88e98aa7bfd3d2d5ec6de6b153fe6a334f4023  PS-09_en_320x640.png
1a785a18e1f61fe021cc5a465239aceaae73eb5030675a84271f10cbb2b93d41  PS-09_en_390x844.png
34823eb6fcf80bf1e80c3d236bd5fca7a96da86634d967239f2e8639d009c3a0  PS-09_id_1366x768.png
f434bbe41e7334331b6af3e055b70d969594809541cb79cd43ee1d1b47336a42  PS-09_id_1366x768_b.png
dad3c25ad6bdf7d0d563dd0e09b6f901d04e2bf39b155c8b419f13d91b48db26  PS-09_id_1440x900.png
27014e3ea8dce1c1df4179ca2c34c1d4a26e60aea918a1c2adbcc1feb09775c4  PS-09_id_320x640.png
3046a56265474c337000beeedf2ed0eeda8bd9828df9b88a81e57ce6d7ada15f  PS-09_id_390x844.png
a6c742ab5556b920ba6d0d970ad0b47d8fea30343974c2e960f2ff6993ef1e1e  PS-10_en_1366x768.png
35f4c7fb2dd41d3625b1e14c3200ad42a4d885a23247bf18fde2d146b841dd41  PS-10_en_1440x900.png
854147d40c7ea075e1728cb6c27d32809407773a7deb5c77a69c12973b565501  PS-10_en_320x640.png
4af66c9858518ffbb17d562f29c5e138c29ecbd43ae25d2d4a3c87acf42fcf4f  PS-10_en_390x844.png
34823eb6fcf80bf1e80c3d236bd5fca7a96da86634d967239f2e8639d009c3a0  PS-10_id_1366x768.png
f434bbe41e7334331b6af3e055b70d969594809541cb79cd43ee1d1b47336a42  PS-10_id_1366x768_b.png
919999729872979c58710561d6e316d0c0e1f2a1e876041ceddb6585ccc5bb78  PS-10_id_1440x900.png
5bf182515b64c8f6877243d9f95c5f08500befcc548900c67d755e59d2c4608d  PS-10_id_320x640.png
e6e546e8df25d9f056bdbb80a0d3831b9e289a68bde4482a196545338e174d64  PS-10_id_390x844.png
6dadbfc2124028c72070f69a7c14fbc96b9cc1471590ec86f9038d2e4e0382ee  PS-11_en_1366x768.png
be06c53b946251a58b6b383bf7e6015099149191b4aa627c89a78dc94f3b6bd9  PS-11_en_1440x900.png
c3a5252567c75452274630f4d262e2cb2ac1a4f1adb36a1523050b5107eb081e  PS-11_id_1366x768.png
ca06c2c7e4a14e1c19538b4ce4c5b2b03512955186ebc83f0a23661b2652a3a3  PS-11_id_1366x768_b.png
8b3c8eeaa7f7da408012a183e2ec8d4bf25a217a6bfc3c8347b818f7880200ce  PS-11_id_1440x900.png
da5a71664c36dd599b4df6eb4ee757b7651fbdcb6490ff381f60d38249b73f29  PS-12_en_1366x768.png
4e48e252d169202c4bfb692926beff7e2e574034c3fece3817afb4fb4daf5bf8  PS-12_en_1440x900.png
316d9f873ddcc4c70e78ffd3fa359f62ed188956760fd8b0f36d1c0ac4a21655  PS-12_en_320x640.png
9b9b4b6307012d3f08da174e90d9225c42b7abada99bdf4fe6ff1cc71eb2bfe4  PS-12_en_390x844.png
aafcdc43260652c82e2636c993249b19ceeee7c75692f3d6d859efe106cf3212  PS-12_id_1366x768.png
1d852c4dab5cc7d7c5feb0d07e42b2db7777e933142b46402ef17650d2c3e545  PS-12_id_1366x768_b.png
920689dc0dbc3a70fafa0e5f2784ab908b137dd329ed1bcfe94ac563d88ba529  PS-12_id_1440x900.png
3b0f714be59ed6369ea4500e029e9c33084eebe288bfc5af2388842c6a2f8c18  PS-12_id_320x640.png
ab94f80972b30e4d754bc8ba3d65680933e2b5d65d342e82df5f1ef5838e60e7  PS-12_id_390x844.png
daf52ce86db669d7eb7fec3d357b50913a7a1508f1ae7a2d564454bd9ee3c058  PS-13_en_320x640.png
6b7652fce6d209691346988429a94d6e66b3b33ab15574dab02c19bd3f6a6745  PS-13_en_390x844.png
8168140fa569b16d216c1d452b18b7794082070883a90c7ebbe47659b153250c  PS-13_id_320x640.png
de3fcae4d1a53f19935502b4425652cf32e218ec4d693e7fd5ec3107b1193cc6  PS-13_id_390x844.png
068ccfe97fe149879233d90b871966558c15d2a81ba63a019873b451c4574740  PS-14_en_1366x768.png
dce683f54371a6df9d0274b2bc1e86e6a3b9729f6bf362fc2110cb316fb69fee  PS-14_en_1440x900.png
e1956735756638a6cbe041bb1fe4837c6917f5ae6a394a0846eb2dbc6ebcadfd  PS-14_en_320x640.png
d9f3de1604835046748d8dfb959df2b7009c9f5be91f60390a0c67cc9f0eef28  PS-14_en_390x844.png
dab27f5b224321b71d167b9ae8b17b9997457ed7a8c847a48290a4db090925b4  PS-14_id_1366x768.png
873f82dc34bfe4dd78010c03e246864f040b1546394f3f4401dafc02d9a776e8  PS-14_id_1366x768_b.png
fd7e26376c8b8626e368418c6658d129ac4a14b04cd7c586307b9e4a427c772d  PS-14_id_1440x900.png
cb80b7dc30c1fe84221a198c380ee0883fee5a3bf52000c117b937e3a039b2eb  PS-14_id_320x640.png
edb89ed8753577305aa78b0056ffc907cb97150f1a4d0132b9ff8b34b4549666  PS-14_id_390x844.png
b219e53dc64ed6dcb4eae6e633490e7500dbc8d9dca088bb17af779df157108c  PS-15_en_1366x768.png
2cdaabb01b8a019349d319f839ce6f22485514629a8d485a3edf0237b25167e2  PS-15_en_1440x900.png
d26dbf9f06db69b1850a6b0a078241f3d2220a59e9affe2895f4867c88ab3820  PS-15_en_320x640.png
7491cc30ea16a54fa4576ac69bdb988caa64a9b729d757f3587de4d4d9bc9cd0  PS-15_en_390x844.png
62b18a1daa7487b33dda3b5891b2a25f31412a073ab766c4112ea1efc06842c9  PS-15_id_1366x768.png
08bc5576ef13cbdfd26a9a37e17f62a010bb8ff7422b2d0aa80bace3c4d54faf  PS-15_id_1366x768_b.png
4b468f09ad5edf4a30b52def02e884657aeb34e7861c1bfd9cf0f737b4dfdd9e  PS-15_id_1440x900.png
7f90bbc7dfc0d7686a4caabeb623d69bc3209a65cbc9ab05583e77e7092af662  PS-15_id_320x640.png
222ddbb6ef5b2ec07eee302a6e1af8ff6513db1ab91765a21d509684021508e3  PS-15_id_390x844.png
5154af249a6053ce56503a4997473b3cad14b11d1f23060ad127dbb6529244da  PS-16_en_1366x768.png
f2e34bf74260f1a5f72af016e50c7ec019668752ab4e4dca4a27a7e0525b29f2  PS-16_en_1440x900.png
e91fcc75e57c19ec5bc8f6923e95e413f93484032b47b03f202d3aca7cae0011  PS-16_en_320x640.png
f3a45636c4eadd551c09a2c5b5c75623a789dc1ec4e6a6544c360c4774e468c3  PS-16_en_390x844.png
932bf83ce705be5bafec52948fbaa80ad1d4d9a4539ce35e256935be03d522cf  PS-16_id_1366x768.png
e7e37a5f4848c509c1015f8dc535954f5b50bc0371a5d80b5e0fb6f791b0a0ec  PS-16_id_1366x768_b.png
1ba5ff2af4ad8fe042d498fb4275f74e1ed3b333255e62736d4776a653b578a0  PS-16_id_1440x900.png
5acc17b20741c837910d436d5e81bd2b9dcd7ced7540eea334bf6ddcad5ee6eb  PS-16_id_320x640.png
44fd39901f9ceffa62f2b78987cdb0981c516545f482dc9044da31ead190144b  PS-16_id_390x844.png
4f5d72ad2ad08fd5aad61f18400b81eb6c2977456688316820820f7bbcd4eef1  PS-17_en_1366x768.png
447cceb3057d173e810d8d114beef559ae9c90e217cf717eef771069b3eed4c8  PS-17_en_1440x900.png
d0c4f1202914ba47cfa747a9701a34c83915b062ac792544ad7e496175464cd4  PS-17_en_320x640.png
58a41f7410c780857f8bda0d45ee536e7367269373fab98ca1251ead7c480d3e  PS-17_en_390x844.png
364a56f2d44f32507a717cf71bbd84d1aa4246f645e38f11508973698ee0d3f0  PS-17_id_1366x768.png
375123d3563a0268e821236c07a2e0338c9f91156769b67e5aeeca1ee8dbd42c  PS-17_id_1366x768_b.png
1c930bfc4cf27186947595856822397e122d2257e7f3544d4a4238977ab4af35  PS-17_id_1440x900.png
4c29b6ae1f7d32fe704f18a0b2b1c005299e9df3017e23ea999740a3bccbef83  PS-17_id_320x640.png
5429378195c4150c14897ea4027b12cff681442ec5469f23be870828fda97655  PS-17_id_390x844.png
3babc2221226a7b08e192b455621f0d5654856d542f15dea44c0abdc004f1643  PS-18_en_1366x768.png
eb754a164360f6492b7846a26854a352c0c5d017589ee44f7c32aa5ae8781969  PS-18_en_1440x900.png
1b5b809c4102e9a85b4026becae400b161c583556e92645c909e0204bdde3ae8  PS-18_en_320x640.png
18d7c8cd56aa00f26f32168a074bf67e68fd670f3edbfe55f0ee7c4bc290cbad  PS-18_en_390x844.png
fbbdb17f9cb1b5a198c28635ed96da38eb29b7c240fc2b54b1262696b935ba8e  PS-18_id_1366x768.png
04ba1e9b19b125cf397d9fc58628b632eedd5b35591f9cc147fc239ddb9b013f  PS-18_id_1366x768_b.png
13b3a2958ee4f780ffcf15c10632b7835ceba1d860369446ca12ea5ce6d276b4  PS-18_id_1440x900.png
b1356bd38b5fe98f3c85356a0ec6fc244cb97464240eaf87e3d2e9258eaadc2b  PS-18_id_320x640.png
224be1607947628b4b7545d36a4c6b20f54ac9c2c95c563539842e68d26b0f96  PS-18_id_390x844.png
aac43ed16408982be457dbd6189ad837744ff8d6c8386f2d3d13e9e0d1b9f57c  PS-19_en_1366x768.png
3d7b81b82c7b998c52a816b29f088e3f70801917484ee5a63f7c1531fa5e9d3b  PS-19_en_1440x900.png
ad48b9d4bf2408c4c01fe03e321c0f296d651aa5b98b365e7bf31741fb180208  PS-19_id_1366x768.png
145ce8fceb3bd1597617b1a326c825b28e26cf8ee3820ca0a2fe8ec725c06865  PS-19_id_1366x768_b.png
32d0a59feee6487a340db0d883ae0b8dc3b9d498c3e200dd633f06171ba80cf9  PS-19_id_1440x900.png
fa38436e59c43ab22b73060cad24e73661af84621ce18ad2d3bbc8dec70b7732  PS-20_en_1366x768.png
63986d4af6d1eb0891e29d86cd923144a362437890f8c0d284fcd1d24f1ce1c1  PS-20_en_1440x900.png
36653c2f0cb6b3a71b30e75ac3a201568cf51d9d7b460a011cea320ce6fdea95  PS-20_id_1366x768.png
f62c35a3164acc01087b4409ce2604e2c4a96f1886d02b6c85253d02b482647a  PS-20_id_1366x768_b.png
7fe209ae6d8fae9ae04dd8b7497e6d4db8aee46a9e83c91a671e76502a52321e  PS-20_id_1440x900.png
96af5cc8f1dd65d34ec8981f590ab2c8828d1d099be23afd5ef5cdcba3f81035  PS-21_en_1366x768.png
a2019b1be4ec16e106a2f126c3c923c5fde41163fcc9cc8e9e84e1ffac0101e0  PS-21_en_1440x900.png
731f4e4c7231b4bd8d312d2eca7383e49498b183be73c9c8a45fc480a3c8ac66  PS-21_id_1366x768.png
5abfa80a5b206854a8b29339f510a443465e51f8a58ba8bf2af8543ff9e0c64f  PS-21_id_1366x768_b.png
8f497f71338b4d9787da53cd93550ebb127cb6a5df19ca6d3d49431feb5e1f06  PS-21_id_1440x900.png
deb462bea62e28124686e7d55918a81bc6787181152a4a963bc0b039955aa26d  PS-22_en_1366x768.png
58cf8c685797a9aafb9cc02b2e0969a7e0f6082d41b472d6aa5b7c1a041283be  PS-22_en_1440x900.png
35f327d50be442308815f593e63862db14fd2f8ec883d05ba8277b0bae56ee0c  PS-22_id_1366x768.png
23867008c410c1a674daa4139fb5d62435172872ca755ca8fd4bb018a1487c9d  PS-22_id_1366x768_b.png
1029dc37a4e15f370f51842c8c785c498b47f0a29849bf9e42bbecfbb7616275  PS-22_id_1440x900.png
deb462bea62e28124686e7d55918a81bc6787181152a4a963bc0b039955aa26d  PS-23_en_1366x768.png
58cf8c685797a9aafb9cc02b2e0969a7e0f6082d41b472d6aa5b7c1a041283be  PS-23_en_1440x900.png
35f327d50be442308815f593e63862db14fd2f8ec883d05ba8277b0bae56ee0c  PS-23_id_1366x768.png
23867008c410c1a674daa4139fb5d62435172872ca755ca8fd4bb018a1487c9d  PS-23_id_1366x768_b.png
1029dc37a4e15f370f51842c8c785c498b47f0a29849bf9e42bbecfbb7616275  PS-23_id_1440x900.png
1b28a2933b51b1d5d9f76b9a3fd3646753207274443107eee7f5200209d4efab  PS-24_en_1366x768.png
f562c938040639610564df37c97b658f523d10a824e99ba2014125e9d337a23f  PS-24_en_1440x900.png
0cab1ed0a20ffdec4c18b180afbd0abb81c696925fe70b3dd1c7e276820a1be0  PS-24_en_320x640.png
6487d01d0dbd3209781e0440082a67f4d36e2bff514d9477d2e917b3d097bfc2  PS-24_en_390x844.png
ca9214d28c7f5db496b47247d6ecb130b03c271eb1d717f47d7250a6401cf6f2  PS-24_id_1366x768.png
6ba5e787ae06390dcc7bd908eaff68bb3efc5d3d9e9acac13add8181967e64a7  PS-24_id_1366x768_b.png
a9632b82f7b8344fe99731d0c5e24494a656e33260ded0113266f31dd0b1fd99  PS-24_id_1440x900.png
9bdf7b256b195d2848c827c167e7ad1027ce790e4e9ff6ad26a80b78a628bb95  PS-24_id_320x640.png
18505b9339e87df75e44af8070192e816087ce602ba4fde6cc7bb3de12605cee  PS-24_id_390x844.png
d237421c3c36fe589ca4fad0d40769f541f9628bdf301655d49bcac63a3c7747  PS-25_en_1366x768.png
5358e64fc4e33cf18b7eff18a66228e65ee6111b468676c06fd4d2cd2007e6a0  PS-25_en_1440x900.png
a7ff79243f3ef1ed9dd5d085a48ab9e58d0e284a3922c4a03d21ce14864f59f8  PS-25_id_1366x768.png
2bfd77baa2ef2ac7ff34ccfbcdfe7c9920f723e85bf802901ab81a8ab5b961f6  PS-25_id_1366x768_b.png
b8612fd776ab532bca067eaeaf7f41303b917a79b87f616adf7fd3dc2153d1e5  PS-25_id_1440x900.png
659af44bc89d93a574d58e8bedfc3bc4c3e21b1249c684e7f0e4b448926293dc  PS-26_en_1366x768.png
8eaf7886433b703af874ac3fef524a5d2e1d3910b106d62279d30dda0d48cbde  PS-26_en_1440x900.png
c036f63673bf96617f2705ea69dbfc60a311499140e49faf8c4a766895a6ed57  PS-26_id_1366x768.png
06dadfffc526cc5468e28f8d5c8f1f96b9a62affdd3c7f9e20ba4a33c976d296  PS-26_id_1366x768_b.png
6f7ecd36d4adaf362fbf7da59f1e256f5229fc18e02b9b34275241db91f0ca28  PS-26_id_1440x900.png
62a9bcf889f94302899711bfc90ec477a6186cdc56075efbdb6ce28595730212  PS-27_en_1366x768.png
914c4f1e7a11718328e6995382482afb8ee899c618779d844c5a387d14a58028  PS-27_en_1440x900.png
481294cc8924831a5c60da874c39c3c54e22dc97b3b18bd10b808c36b297000f  PS-27_id_1366x768.png
c33a3f695dc7437123c7fcad52fa9b436abcbd7afb928713558ff61022534f39  PS-27_id_1366x768_b.png
6d7477bc9de64b03d7fc3e7ce077a1ffd161ae0f3581687039c8a8435f945d93  PS-27_id_1440x900.png
12468a50185d222fab9746910866274aacd4c37ec054ff973249f7d60f12ace8  PS-28_en_1366x768.png
4bd628bd614eb708f9d9074cb762f0167e2c3533cb234f1e373a163aef9ae052  PS-28_en_1440x900.png
7de26b229f635bf6eb07482f23a6039d5d775acb84ebab87f19417ce3530cf20  PS-28_id_1366x768.png
3493dac07d3ded587414785cd2448b00c7f8b28d995d4a06996476c56357cb12  PS-28_id_1366x768_b.png
cc54c074102c8b4ae4cc311b976da2770139059c5cf48ba6635a4dd5550fe14e  PS-28_id_1440x900.png
de2fcf51ac2b84384a8c536461126e27b91a7176d60639a89b22320f13efaca2  PS-29_en_1366x768.png
b3c3230eaa81290f8ec85454f0d51da42a281bfaeacf2cab216059705aefc373  PS-29_en_1440x900.png
b3ab81519b6cde8eb581d1f6c9940eb6dab022afb06721d7bf528f638d849fac  PS-29_id_1366x768.png
b9ea1d3955704bf8bd126b4f4fb2bb2f35fd9db1371b9120c09e47f11f961037  PS-29_id_1366x768_b.png
62d6fb5deea434b76ecfaf28f0176db40b53b8ee18dbf8c6ce2d590367145960  PS-29_id_1440x900.png
29b1b9fece11bc66a83e0854bbb22c1108a0f344f479e2edfcf001d890244eba  PS-30_en_1366x768.png
d939a6586c2fe9c114436f0fc882d18b7a97ac1ea24ed2356b41b7efb7d644bb  PS-30_en_1440x900.png
2bcd2154be2b7948b34acd36bb532c53f2fbc34da51fa20603eb555b242a5762  PS-30_id_1366x768.png
a4d2884e28636a05a099f24dff4f76a610b340d854e54b41eca93701a5f56ccb  PS-30_id_1366x768_b.png
7af1f135c655606e4287086adfcabab23b645ffbdb683ac30851619edc25625e  PS-30_id_1440x900.png
c35b0a7c32d3e8b30544edf7e525e47bd589dc5c76ab14dc441224c83ace3ce3  PS-31_en_320x640.png
57217a0be776ec0a07800dc4f98d5db975b7736112d6c5b3430b913d6516f7b2  PS-31_en_390x844.png
3ba64f25a7593f94b067766492196fb9ca14aa3896cc1f79d1c4dfc56a8f8c9a  PS-31_id_320x640.png
8afc7d7b98f0345e7ae961c33b3ac225a548314ab1a6ae6c382b6d02128579f0  PS-31_id_390x844.png
e2505624147e2829e2d1567d35f32ced171dae7a1c05c1ceb22a81d760504f29  PS-32_en_320x640.png
5201fd70ef2446b128acd193e6d84aa522059731ff2d1c9d96976d7c59beb790  PS-32_en_390x844.png
82d02b87a20f1dcb6f9b26e91c9c1261231eeb76234ba382800842c5d216b9fb  PS-32_id_320x640.png
041c9102eadab281bbfa438a8105688efd1d869d3ed20d1320fe36efbbe4349c  PS-32_id_390x844.png
eff252f5d9e8c0a52c5e29eb4ad5f1f6afb84eb0fbae33b19f3ad7e3a4f6d473  PS-33_en_320x640.png
8e41f6522a5dc03a9ab8e5b8f4aadcd1e9ad8a366f7b24398a359ba08ea49014  PS-33_en_390x844.png
1c78b3bb07a5c66d33d7c6f8555c1ac969738d3798cd796e52c66751b17aea40  PS-33_id_320x640.png
d15824330634c219862464f22c16b154e76cbbca11dd4597ea382df7e28045b6  PS-33_id_390x844.png
809bba55f0bfcee7357fe2430962ba4b7f00e9d1fbea30e44fba9c1b3f9635f0  PS-34_en_320x640.png
efd33aa8facc2ea83b68f1a2230219e28db1a6ce6b1fe5c63b40cbd46c0f93ee  PS-34_en_390x844.png
76b0e8675f48df2e65d07b834d297d0552d5b0da4e418211e67b1bfb46f20e77  PS-34_id_320x640.png
febb10cde290fbdcd8563bcd84b9dd53f4b79f2e1529d5f05f1cc585436a743d  PS-34_id_390x844.png
9dbbf27df85828d2c71b5db33bb492d848a25fdeff91dc183f773affdcb20e41  PS-35_en_320x640.png
7570abe1f9a57388d1bac340080c8878cb59ef166ddd301a9011c336c0dcffb2  PS-35_en_390x844.png
000c714498adee5ad87d33640019ac9f61488a54f5c0f38175a938fd220d7255  PS-35_id_320x640.png
38e67ae6c62d589aa62e1599cebb35e8a40802acedbd2573f8fe0845ec62e8c5  PS-35_id_390x844.png
373ee60268ac5b76ab29c432b331c250183ebed9ee4c85a9358ac9852a488f86  PS-36_en_320x640.png
a5a9e08cb5133264f8caf2174dac60550b5d33e22c0d045729a06ee75965d406  PS-36_en_390x844.png
46cd9239c89f55ca969dae8515b1d1bf0e1e9e6309b8d85b1e3e97e1bb1a3ab7  PS-36_id_320x640.png
063bf06ac457d492c7f77d09b4193b25c3e74738ffbe7154e3a82a662a9927da  PS-36_id_390x844.png
12f93a5c4dceaa63627a8e5aeab2f27ae8767663c9f1ff5c52308f10f5326046  PS-37_en_320x640.png
c2c5740427f403a947c4a39584a17f05e2e65f4fdf769ce44a531a343c4ac5c4  PS-37_en_390x844.png
c25b41a472e8540b6a7f6e8a36892effb51d58dafd0c274abcd2a79f3a489e4a  PS-37_id_320x640.png
043b2c17a23af9cdd7a2a2eea6fdfdc54e91e64013e93610d1a3747614883872  PS-37_id_390x844.png
ceb02119f426c3f13d15a62da4b0802524289b3a6e6bf69ffe57285d0e67a9a9  PS-38_en_320x640.png
be561bc989ee4ba17e8d6bd5405f9e0adc7bc1e3a12be94e37352d91a69ba445  PS-38_en_390x844.png
411d890cfcc46f97dfac4beb8af820cffcaa5cc8819ef54b67e7f7dcd1d81597  PS-38_id_320x640.png
62cad3e25757665fcb8e029a9b2df9b4ee3e465cbe64e2728c9ce668c3bcd00a  PS-38_id_390x844.png
96e031211a404158c7f1849e42d6bd917de8442ee4a4e76799654fd8353f77c5  PS-39_en_320x640.png
06fc48c017a7277f2e8ed88ae4ded82b76a98344f07af4d528c534f9d3427591  PS-39_en_390x844.png
a76b9f967dfabed04ac6041995f04dbba7717c9140beca3605ccd89fb94d5b4a  PS-39_id_320x640.png
17fd54e084cf54708fd7061cd36ec79405cc366a958ed71d89889bc92425145a  PS-39_id_390x844.png
dcd7aed62c19a519dc4a36eb84042de2f236e23c38526e67fb7d6f933f26a025  PS-40_en_320x640.png
269397abb895702ad4685217a76b492bc1b70ebb7c796109c7cb8c48effb7349  PS-40_en_390x844.png
be7cbb1372413bb1cc725943662899b2b084e167736a43c99c9bd0e1834aa9ce  PS-40_id_320x640.png
5b0c0e2480e362729e7d2a64dca19d479d6e8cf694a2d5d4633e764c1b3857fa  PS-40_id_390x844.png
6053bad94647567673ce0ebafdfa8d1b197cec1eef76804a5bd7095e0ec80d4b  PS-41_en_320x640.png
c3d0a961a12bfe634c730a470773bfe874afcbe5c639f46c9342b5c1bfd077cc  PS-41_en_390x844.png
5517cfec4b061cfbdb8534d7c06e5b0fd7c2c93b449250b883d6a8b89c09ee5e  PS-41_id_320x640.png
614213faaf6fb5177d1cc430938dbe6a29a587adfa5a55ba5601c31c2a08e37c  PS-41_id_390x844.png
925aa4f95e1366dc89f6a20605a11ed997616c6bb1c16cec7e5d576f01c3fc20  PS-42_en_320x640.png
965b79921e1f37af418be96ecbbd8641140cb0476c7ae372b8fd41b23fae5d8a  PS-42_en_390x844.png
cff0e709df97f8a9daa419254a8a2b606408a434378d358466c654efa27208e0  PS-42_id_320x640.png
0b344affdcffa22b1d81ad060d17857326c152a669d36641f9a24b2f083ad997  PS-42_id_390x844.png
ad16d908e7835429821bae9f5aa0529ecf62e5e33ca31a28b34940c7247e0a3a  PS-43_en_320x640.png
d3ea7293480e93b71ddeda9e411b9485b3522944cda9ad7e51481f9283ece41e  PS-43_en_390x844.png
368e12dbe71b0bf48a55f24d99e2b6727867d4afab3f4c544e1a907951a7d013  PS-43_id_320x640.png
42080be5e9657f16afc8b08550e9d53e9013338963c2c53915beb0bd76894db3  PS-43_id_390x844.png
0f52c2fad0d2b8d8949f69867cfd05bcf4b1b6c10a5b98d9d6dd887c8a4e3f1e  PS-44_en_320x640.png
11d0b4156e99b6815020b53a1148b29e7f6cd6d5f7b6072d476995ae7355b166  PS-44_en_390x844.png
a0142ba1bc60974a151cc1079e782b5db73b220fd48ae0992dec6c3440b348da  PS-44_id_320x640.png
adc8665350d395096e4c17e0a7e3512dc749dd701bb4bba1b41fbdfd916e53fd  PS-44_id_390x844.png
eb78b56c624b580da62237099fe6dfe770246964e531e9fb823647510f4c19eb  PS-45_en_1366x768.png
00bd7a2cbcb317261f94e1be55016b56b54c655bfb8eafdae44fb935797f48ac  PS-45_en_1440x900.png
64fc3af6a80ba222372e15fb4d1ae7b64254af147a6f67104679e0b8eab28af1  PS-45_id_1366x768.png
26da869d6d63ad204a0a704ba14ca2649e3a5c5405e8d1281863b21eaaa15eb7  PS-45_id_1366x768_b.png
0fc0880f37e83679c7f6b627f95943ce183d11dce24e1c886bf6ae21c4a0f709  PS-45_id_1440x900.png
1f48172fa7b409b37a048b3c37e1a48d8d1a3bdac0f962d258edd74d28e90de9  PS-46_en_1366x768.png
9b57c6362e3ca09d31b69a509372882ca193cb52bd69bafc0aa932feb86793b5  PS-46_en_1440x900.png
7ff1e783214c9b2fc5df150fc6a20576c8af4d4950a95e2869692caf2ab5b95d  PS-46_id_1366x768.png
cfb2a6467adb263d4ab9db308a418e71f7bd4b7baf4c50dfc85832ee982a82fb  PS-46_id_1366x768_b.png
2fd3983b298ba1c73a5e3b49304da83199787c4464d03ea00257a67f0ccfb481  PS-46_id_1440x900.png
bb0985191c51f82ad25a2eece3692f6bd01e2edad9d0cccc507f93ff9f8e0839  PS-47_en_1366x768.png
e60ee7a692691aa2adfa9d5c2e3a8e1270dbbaa77fc81448842bb8497f82804d  PS-47_en_1440x900.png
e33d14b664d9203ec1a06a4f317675951ef5aefbfb86e3a27317cbb51effcacf  PS-47_id_1366x768.png
a83c331a4a2ee59153bc3a05b11cb17814ce055d8e2087a9c47ec927b1b56d38  PS-47_id_1366x768_b.png
66e15cdf790c2666b10f26e35dfb3fd356d3244d25ccc43f1209aa9f66d67e15  PS-47_id_1440x900.png
133e532a801d09b3a2ce7f2bc3c18959638c72d80ecebddeba6c95e0c7bfc0a1  PS-48_en_1366x768.png
c8b45d18bc66020e89ef895ec5247756abca6a01b0336eb9d8cb241628305669  PS-48_en_1440x900.png
1f3ba5bde6c6dd7d8e2528d3739a4e4840bfc92b3155dd135bbfb0c96407e696  PS-48_id_1366x768.png
52b6d6ec231b61fcbf11381f42fd49f2acf6458ab34c71277f437f362fd98418  PS-48_id_1366x768_b.png
b4ec37835a81c6a61ca487b3f20726e62b50aa2394c4632d7e8e16b306f9ee45  PS-48_id_1440x900.png
3c58ba57c4c93dcb4f56e320bb03340ca2e8cc56d81f2ddbaa7b327d631bd6f8  PS-49_en_1366x768.png
084a1e8b1a66861f80d5ee90b7999be0416f79f76721eac41ce187cc6520c470  PS-49_en_1440x900.png
5b88ff6f4638bc0d925878fa43fd90859bfaa0cbd88423be0443fb88df42ccdc  PS-49_id_1366x768.png
f913b360ed64c74ad883641e1e6053500d9431eb5334e0abc66fa636c96203f2  PS-49_id_1366x768_b.png
c474582328054d0920774a5b1a6cd302bb22230337c35581718d4d37ab665342  PS-49_id_1440x900.png
092481c559b8213b9333c2f418a0a42998c842726fd1989141ea109fdbdeb122  PS-50_en_1366x768.png
a6f8e3c1207d699ba0ac958a2c94953ad0d11e951eb14ffd277e5fcd3b7b30ab  PS-50_en_1440x900.png
554f7b6ff1c07c5c44028591760d09dd6f1b8ecc804c0462b7919a5608423c1e  PS-50_id_1366x768.png
ccdda3bf6dc59688fd18a19bb56eb378d8a5f8e739224e68a087ffaeaacf6e41  PS-50_id_1366x768_b.png
042b80a6146f8615a67d9d5be69f10bb6b3133c443abee90f6cd5ebbbf9d344a  PS-50_id_1440x900.png
0c4486d218be04292e12af9db419dfa488e391e6e5a208a9ff6ba6968d2aa8e3  PS-51_en_1366x768.png
9ecfb8d8436fea13fa6832cc1b1cdaaffba03fcae7bb4de870d0e6d7e78ab1f3  PS-51_en_1440x900.png
6870a4bbf8f89100b5546b5f2cad615b7f746e2d1b55c5221427d28b3821c3fb  PS-51_id_1366x768.png
b8204a3f54070992cdad220e6fbf211a50c6584723cf9231b4c24636f2af77b9  PS-51_id_1366x768_b.png
e760def1c5a0c978b10caa19f14d567ba3f9d0ec039157b1257da3a600636277  PS-51_id_1440x900.png
79c294ef48419158b2a2e132f7fa272033d7b1f754b16262126279114e0ba352  PS-52_en_1366x768.png
b12d6d3e808892574406f522a7fa001b358dd4e0b44ac5a7732c85be78986d67  PS-52_en_1440x900.png
155dee8f6f74dc2a312d7a35a3566db22dd79aa83802394fc759b41d729808cb  PS-52_id_1366x768.png
7d188713ac7981afd3eab21f47aee24dd2d9d0402d5dce1576d28cc2a1db2441  PS-52_id_1366x768_b.png
68752f72786f1569a3960ffdd1ee0435ed7202528aa8ddd45c20244d935e7427  PS-52_id_1440x900.png
2a9252a0cb340b3b5dc34034f112f79cf4ad1ddcd101c9a8ebaee8068bcfb0cc  PS-53_en_1366x768.png
e191bca394402fff5d730ef6b47d81a6d6f8a31571747b880694c3904127c13b  PS-53_en_1440x900.png
7010dc47bcd696e077679ca3e51d91d4999fd9b0ae1009d350cd0fa5e9637341  PS-53_id_1366x768.png
d82103011b5dbf7bcb1660a21aeed8c981b790974afa5353afc18c509263817f  PS-53_id_1366x768_b.png
5a40ba91e22b3dab83654e051d433d41bdf7503b781efd90a2e31e7c7edec2e7  PS-53_id_1440x900.png
2afd22e9453805d05b8f300e925bce072df2303f19d7e5fe37c95b674a2df7b5  START_id_1366x768.png
9f0c79605ce4b31565e3d34bed498b5707ce2085136c1ba19af7c1368c7861dd  START_id_1440x900.png
fe081a007da2f0d385f0422cdf3decfdade9ae378c756356e2cbac02508d7b60  START_id_320x640.png
5cdc7214f4fe0f913499eacbd982b412265f309967eb5d109f124f4146a9026d  START_id_390x844.png
```

## 6. GR-16's two variants

[GR-16](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01) — the smallest desktop text — stays open for the Owner's choice at the preview (SD-08; [DESIGN_SYSTEM_MAPPING §1](DESIGN_SYSTEM_MAPPING.md)). **Variant A**, the reference's standard: the desktop text unit `--tu` at `clamp(.92px, …, .96px)` from 1,025 px, so the 14 px body text renders 12.96 px at 1440×900 and 12.88 px at 1366×768. **Variant B**: `--tu` fixed at 1 px, so body text stays at 14 px; the 12, 11 and 12.5 px sizes are fixed in both, and the 10.5 px pill size is left out of both. Measured at 1366×768 (§3's type scale): body text, field labels, section titles, row titles and buttons render at 14, 13, 16.5, 14 and 14 px in variant B against 12.88, 11.96, 15.18, 12.88 and 12.88 px in variant A; the page title, which the reference sizes for the viewport by a rule of its own, renders 25.54 px in both. Every desktop screen and both out-of-scope pages are rendered in variant B at 1366×768, where the two differ most — the files ending `_b.png` in §5 —, beside variant A at the same size; on the sign-in pages PS-01–PS-07 and the out-of-scope page outside the shell the text unit is 1 px in both variants, so their two renders are identical. In the prototype the address carries `?varian=b`, which every link keeps, and the start page links each journey in both variants. The phone sizes are the same in both.

## 7. What stays for the Owner's preview and the phone test

- **The Owner's visual verdict (SD-08)** on the six journeys in both languages: the fidelity to the reference, R1's structure, the deviations GR-24–GR-35 and the copy marked "proposed" in each specification's §8 and §9 and in [PROTOTYPE_PLAN §9](PROTOTYPE_PLAN.md).
- **GR-16** — variant A or B (§6).
- **OQ-04** — value formats in the English interface: option (a), one format in both languages as on paper, is what the prototype shows until the preview (DIR-065).
- **The phone test** (OQ-07 (a)), at the preview the Owner attends: the server started with `--host` set to a private IPv4 address of the Owner's computer, on a trusted network the Owner sets to Private — never public Wi-Fi —, any firewall allowance for the Private profile only, no port forwarding, tunnel or hosting; the phone opens the start page at that address; the server stopped afterwards (SS-06; [PROTOTYPE_PLAN §5](PROTOTYPE_PLAN.md#5-the-phone-test-sp-6-ss-06)). S2-2 checked the phone screens by emulation only — the device metrics of 390×844 and 320×640 with a mobile, touch-enabled viewport —, served nothing to another device and changed no network or firewall setting; touch, the phone's own browser and its keyboard stay untested until then.
- **Before Stage 3**, not at the preview: OQ-02, OQ-03, OQ-05 and OQ-08, deferred by DIR-065, the prototype showing the fitting designs meanwhile ([OWNER_QUESTIONS](OWNER_QUESTIONS.md)).

## 8. What the runs found, and the limits

**Found and fixed within scope** (DIR-066 §7: a failing check fixed and run again), each re-checked by the final runs of §2:

| # | Found | Fixed |
| --- | --- | --- |
| 1 | AK-5: class names with an example page's prefix (`pur-drop…`) and a reference fixture, the product code TAS-LPT-14, in S2-J04's pages | The prototype's own `mc-file__…` classes for the file zone (GR-01's dashed edge); the synthetic code TAS-LPT-01 |
| 2 | Contrast: muted text on the canvas grey — the sign-in page and S2-J05's full-height sheets — below 4.5:1 | GR-04: the canvas grey's own muted-text token on those surfaces |
| 3 | Targets on the phone below 44 px: the segmented choices, the sign-in's "Tampilkan", the marker and a stand-alone link | GR-07's 44 px rules on the phone and with a coarse pointer |
| 4 | Unkeyed accessible names: the search field, the account trigger and its menu, the sign-in's language group and *Beranda*'s view switch | Each keyed; one string added, `shell.account.menu` |
| 5 | Focus obscured: a control focused beneath the top bar, the action bar or the phone's bars | GR-06: scroll padding clear of the sticky regions |
| 6 | Copy cut on the phone: S2-J03's app bar title, document names and a trailing icon; record numbers at 320 px; in the work home's group counts, a company's code and its count on two lines ("BTN" / "3") at 390 px, found by a scan of every screen | GR-35: titles and names wrap; the reference's long-title rows; a no-break space inside each code and count, the pairs wrapping at their separators |
| 7 | S2-J04: the line table clipped; the "Simulasi" chip stretched; an exact code confirmed by Enter added its line twice | R1 j)'s column grid with fixed widths; the result's own head; the list closed before the pick |
| 8 | S2-J05: closing a stacked sheet returned the focus to an inert element | The focus returns to the sheet beneath, or to the control that opened it |
| 9 | Text spacing (1.4.12): a sidebar label clipped; the segmented control and the bottom bar overflowing at 320 px; PS-06's icon; then the sign-in's text button below 44 px; *Beranda*'s KPI figures past the page's edge at 320 px | GR-35's spacing rules; GR-07's rule for the text button; below 375 px *Beranda*'s KPI tiles one per row ([S2-J02 §9](journeys/S2-J02.md#9-the-s2-2-build)) |
| 10 | The marker and the panel (SS-08), checked by `panel.mjs` from this run on: Escape on the panel opened from an S2-J05 line sheet also closed the line sheet; on the phone the panel's open flag stayed set after it closed | The line sheets leave an Escape that a layer above has handled; the flag cleared when the sheet closes |
| 11 | The tooling: fixed bars drawn mid-page in full-height renders; the first full run hung; the motion check reading the stylesheet's text; two false SV-01 differences — the top bar's search against a form field, the demonstration page's own brand tokens —; `--port` not passed to the server; the temporary browser profiles not always deleted on Windows; reflow checked in Indonesian only | The viewport grown to the document; a 60-second guard per job; the running animations measured; the form field compared, and a brand-coloured property against the reference's token; the port passed; longer retries with a warning, the leftover profiles deleted; reflow in both languages |

**Limits** of these checks, none worked around:

- **Contrast** is computed from the rendered page's computed colours, not sampled from the image's pixels, so anti-aliasing is not measured; no text of the prototype sits on a gradient or an image.
- **One browser**: Google Chrome headless on Windows. No other engine, no screen reader and no real phone; touch, the phone's browser and its keyboard by emulation only until the phone test (§7). WCAG 2.2 AA is checked at design level, not as a conformance audit.
- **Text spacing at widths between those checked**: under 1.4.12's spacing, *Beranda*'s longest KPI figure — 13 characters, its size following its tile's width — reaches past its tile's edge into the gap between the tiles: 8 px at the checked 390 px; at most 15.3 px from 375 to 500 px (at 470 px) and 11.1 px from 1,101 to 1,299 px. Its box, the trailing letter spacing included, meets the next tile's edge at 455–470 px and at 1,101 px by at most 1.3 px, its glyphs clear of it; no text overlaps other text and no page scrolls sideways. Measured on the final build; the figures read the same in both languages (OQ-04 (a)).
- **Two screens' renders do not show their state**, found by the zero-context check's comparison of the render hashes and confirmed by replaying the jobs: PS-22's five desktop renders show PS-23 — the page's scripted step from "PDF sedang dibuat" to "PDF siap" (SIM-10), 2.6 seconds after the state opens, ran before the full-height capture finished —, while PS-22's in-page and spacing checks ran on PS-22 itself, 1.7–2.1 seconds after the load; and PS-10's three renders at 1366×768 — Indonesian, English and variant B — show PS-09's page without the account menu, the capture's enlarged viewport having closed it, while PS-10's checks ran with the menu open and its 1440×900 and phone renders show it. The prototype commit had been made, and S2-2 allows that one commit only, so these renders stay as committed; a capture at the viewport's own size for a state an action opens, and PS-22's address holding its state, are left to the next authorized run.
- **States reached by an action**, PS-10 and PS-11, are walked by the journeys; `focus.mjs` and `panel.mjs` check their pages as their own screens.
- **Scripted answers (SIM-07)**: a figure other than the story's still gets the story's text, as [SIMULATION_REGISTER §6](SIMULATION_REGISTER.md#6-the-s2-2-build) states.
- **SV-01** measures eleven shared components on the reference's public demonstration page; the reference has no public sign-in page, so the sign-in screens are compared through its tokens; the reference's renders are not kept (AK-5).
- **A finding for Planning**, not changed: S2-J04's PS-27 shows "Catatan" as the specification states, while DATABASE §4.6 gives a purchase no notes field and REF-M3 left it out ([S2-J04 §9](journeys/S2-J04.md#9-the-s2-2-build)).
