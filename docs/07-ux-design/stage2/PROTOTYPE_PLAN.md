# Prototype plan

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.12. Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**What this plan is.** S2-2's technical plan, prototype-only: nothing here is built in S2-1, and nothing here applies to the application. Its screen list for the Owner's approval (§12.12 item 8) is [§8](#8-the-screen-list-for-the-owners-approval), written after the journey specifications.

**Sources.** The prototype's working copy at `503e83b` — its README, `VENDOR.md`, `tools/serve.mjs` and the 15 vendored files, read-only (SP-17); the gate record's [Stage 2 decisions](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061) (SD-03–SD-05, SV-01–SV-06, SS-01–SS-08) and [S2-0 setup](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-prototype-setup); DIR-062 SP-1–SP-6 and DIR-064 SP-13–SP-18; [DESIGN_SYSTEM_MAPPING](DESIGN_SYSTEM_MAPPING.md), [DEVIATION_REGISTER](DEVIATION_REGISTER.md), [SIMULATION_REGISTER](SIMULATION_REGISTER.md), [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md) and [DOCUMENT_PREVIEW_PRODUCER](DOCUMENT_PREVIEW_PRODUCER.md). **Official documentation, accessed 2026-10-14 (SP-18):** the Chrome DevTools Protocol's domain definitions in its official repository, `https://github.com/ChromeDevTools/devtools-protocol` (`pdl/domains/` and `pdl/js_protocol.pdl`), the documentation site `https://chromedevtools.github.io/devtools-protocol/` listing the tip-of-tree, stable 1.3 and V8 inspector versions; Node.js v24 documentation (v24.21.0 at access; v24.18.0 installed), `https://nodejs.org/docs/latest-v24.x/api/child_process.html` and `…/http.html`, both modules "Stability: 2 - Stable"; Lucide 1.56.0 (`https://github.com/lucide-icons/lucide/releases/tag/1.56.0`, licence `https://lucide.dev/license`); Google's [sign-in branding guidelines](https://developers.google.com/identity/branding-guidelines), last updated 2026-07-07; WCAG 2.2, W3C Recommendation of 12 December 2024.

## 1. The file layout S2-2 would add

Under the prototype repository, proposed (Level 1); S2-2's authorization fixes the final names.

| Path | Content | Written |
| --- | --- | --- |
| `site/id/` and `site/en/` | the PS screens and the out-of-scope page, one set per language, the same relative names in both, so that the switch swaps the first segment ([LOCALIZATION_ARCHITECTURE §4](LOCALIZATION_ARCHITECTURE.md#4-the-prototypes-mechanism-prototype-only)) | new |
| `site/index.html` | S2-0's shell, kept as the start page that links the six journeys under `/id/`; its marker stays | changed |
| `site/assets/mc/overrides.css` | the override layer, loaded after the five vendored stylesheets: the deviations of DEVIATION_REGISTER, [GR-16](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)'s variant B as a switchable class for the preview, the sign-in's text unit at 1 px, and the sign-in composition rebuilt on the tokens (§2.3) | new |
| `site/assets/mc/storage-guard.js` | [SP-13](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents)'s guard, the first script of every page that loads any script (§2.4) | new |
| `site/assets/mc/runtime.js` | the runtime provider — only if the Owner chooses option (b) of [OQ-01](OWNER_QUESTIONS.md#oq-01--the-runtime-for-the-vendored-scripts) (§2.2) | new |
| `site/assets/mc/prototype.js` | the prototype's own behaviour: the `mc-js` flag (SP-16), the scripted states, the panel, the logout question of [MSG-35](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue), the scan field, the composer of S2-J03 and anything §2.3 assigns to it | new |
| `site/assets/mc/data.js` | the synthetic data of the six journeys (SP-20), one story, every amount checked by arithmetic | new |
| `site/assets/mc/icons.svg` and `LICENSE-lucide.txt` | the prototype's sprite, written from Lucide 1.56.0's SVG files, with Lucide's ISC and MIT notices beside it (§2.5) | new |
| `tools/verify/` | the verification scripts of §4, Node.js built-ins only | new |
| `evidence/s2-2/` | the render files of SV-01–SV-06, outside the web root (SV-06) | new |
| `site/vendor/golden-frontend-reference-v1.0.0/` (15 files), `VENDOR_SHA256SUMS.txt`, `tools/serve.mjs`, `.gitattributes` | untouched — the vendored files byte-identical (SD-04); the server unchanged unless S2-2's authorization records a reason | — |
| `README.md`, `VENDOR.md` | the run history and the usage inventory brought up to date | changed |

## 2. The scripts

### 2.1 What the vendored scripts need

Read from the vendored files, with `VENDOR.md`'s inventory; "the runtime API" is what the reference's `project-config.js`, not vendored, would provide.

| Script | Needs | Guards and storage | In the prototype ([SP-14](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents)) |
| --- | --- | --- | --- |
| `router.js` | the runtime API; interactions.js and patterns.js | session storage in try/catch; a same-origin fetch | does not load — Inertia navigates in the application (GD-05); plain links, one page load per page |
| `guide.js` | the runtime API; interactions.js | local storage in one helper, inside try/catch | does not load — no guidance level, resume memory or walkthrough in V1 (GA1 c) 6) |
| `docs.js` | nothing | none | does not load: no journey screen needs it — S2-J03's paper is composed by the prototype's script, its totals the synthetic data's (DOCUMENT_PREVIEW_PRODUCER §5) |
| `composer.js` | `MCTime`; docs.js; guide.js's `MCProgress`, `MCStepper` and `MCChecks`, called without a guard; patterns.js | none | cannot load: guide.js does not, and its check counter calls `MCChecks` directly — S2-J03's composer is written new on patterns.css's `.pt-compose` and `.pt-paper` classes |
| `patterns.js` | `MCTime`, `MCBrand`, `MCProject`, `MCRoutes`, `MC_PROJECT`; interactions.js's dialogs, toasts and motion; router.js's `MCScope` and `MCRouter`, both guarded | session storage `mc:company`, inside try/catch; history `{ ptSheet: id }` for a sheet and the same state when it rewrites the address | under OQ-01 only |
| `interactions.js` | the runtime API — its project-shell, phone-navigation and account sections return at once without `MCProject`; patterns.js; router.js, guarded | local storage `mc-shell-sidebar` and session storage `mc:prev` and `mc:range`, all inside try/catch; history `{ mcNavDrawer: true }` for the phone drawer | under OQ-01 only |

**The runtime API they call** — counted in the vendored interactions.js, patterns.js and composer.js: `MCProject` — `accountMenu`, `bottomItems`, `can`, `feature`, `home`, `lang`, `navCovers`, `navCurrent`, `navIndex`, `navItem`, `navProvides`, `navTree`, `t`; `MC_PROJECT` — `account`, `dateFilter`, `locale`, `mobileDisclosure`, `navigation`, `user`; `MCRoutes` — `allowed`, `current`, `denial`, `get`, `href`, `modules`; `MCTime` — `band`, `greeting`, `parts`, `today`; `MCBrand` — `fill`, `title`. The interface language of interactions.js's own strings follows `MCProject.lang()` or `<html lang>`; patterns.js, composer.js and docs.js hold Indonesian strings only ([LOCALIZATION_ARCHITECTURE §2.6](LOCALIZATION_ARCHITECTURE.md#26-the-references-micro-copy-gr-17-gp-5)).

**Sections of interactions.js and what runs them.** Its project-shell section needs `MCProject`; its Shell section runs on any `body[data-page]` with `[data-shell]`; its Login section only on `data-page="login"`; its Motion Study section on `data-page="motion"`, and its command palette, in an application mode, on any other value but `login` where `[data-shell]` exists — the Motion Study's sample records only in the study mode, the application mode's groups coming from the page and the runtime API; its shared parts — select, upload, responsive shell, split action, long text, phone and tablet navigation, toasts, date range, account menu and dialogs, the persistent shell's lifecycle — on what each finds, the navigation and account parts needing `MCProject`.

### 2.2 The runtime — the analysis for OQ-01 (SP-15)

| Option | What it is | Fidelity | Risk | Effort |
| --- | --- | --- | --- | --- |
| (a) A modified copy of `project-config.js` | the reference's demonstration configuration, edited | highest wiring speed | it carries the reference's routes, modules, fixtures, personas, navigation trees and preview hooks in session storage (GD-04; AK-5), every one to be removed; SD-04 covers byte-identical copies only, so it needs the Owner's explicit permission | low, then a full audit |
| (b) A runtime provider written new | a prototype script defining `MCProject`, `MC_PROJECT`, `MCRoutes`, `MCTime` and `MCBrand` with exactly the members above, returning MultipleCorp's own prototype configuration — R1's navigation per persona, the account menu's *Akun Saya*, *Bahasa tampilan* and *Keluar*, the brand MultipleCorp, the fixed business day (SIM-13), the language of the address (SIM-14) | the reference's own interaction code for the shell, menus, dialogs, sheets, select, tabs and lists | members' shapes inferred from the vendored scripts' use and checked against the reference's documentation in a verified clone (SP-17), never copied from `project-config.js`; reference behaviours that differ from MultipleCorp's are switched off by configuration or not used — the logout question runs with `account.logout.confirm` false and the prototype's handler of the cancelable `mc:logout` event asking [MSG-35](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue)'s question only when input is unsent ([GR-09](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)) | medium |
| (c) No runtime | interactions.js and patterns.js do not load; the prototype's script provides the dialogs, menus, disclosures, tabs, sheets and drawer the screens use, on the reference's classes and motion tokens | the reference's styles and motion through its CSS; the interaction code written new | the lowest import risk; differences in keyboard and timing details, measured by SV-01, SV-04 and SV-05 | medium |

**Recommendation: (b).** It keeps the reference's own interaction code, which GD-02 makes primary for how an interaction is presented, with the vendored files byte-identical and no line of the demonstration configuration, and every difference from MultipleCorp's behaviour is handled by configuration that S2-2's checks can read. (a) is not recommended; (c) is the fallback if the Owner prefers the smallest import surface. The answer is needed before S2-2 (OQ-01).

### 2.3 Loading per page (SP-14)

- **Order.** The five vendored stylesheets, then `overrides.css`. In the head, before any other script, `storage-guard.js`, synchronous. At the end of the body, under option (b): `runtime.js`, `data.js`, `patterns.js`, `prototype.js`, then `interactions.js` deferred and last, the order the reference's own headers ask for; under option (c): `data.js` and `prototype.js` only.
- **`data-page` and `data-shell`.** Every page written new sets `data-page="mc"` — never `login`, `dashboard`, `purchase` or `motion` — so no demonstration section of interactions.js runs; a page inside the shell carries `[data-shell]` on its shell, which runs the Shell section and the palette's application mode. The values per page are listed in [§8.3](#83-data-page-data-shell-and-the-scripts-per-page) and checked by script (§7).
- **The sign-in pages.** PG-001 and PG-002 set `data-page="mc"` without `[data-shell]`, so interactions.js's shell sections stay idle there. The reference's sign-in lives in its Login section and its `login-` blocks, which are never used (SP-1; GA1 h)); S2-2 rebuilds the composition on the shared primitives and the tokens `--auth-*`, `--text-auth-*` and `--mc-auth-*`, in the override layer and `prototype.js`, and the override layer sets `--tu` and `--iu` to 1 px there, as the reference does for its own sign-in page. The fidelity audit compares it with the reference's rendered sign-in ([DESIGN_SYSTEM_MAPPING §8](DESIGN_SYSTEM_MAPPING.md#8-the-fidelity-audit)).
- **Per screen.** Which features of interactions.js and patterns.js each journey screen uses — dialogs, sheets, menus, disclosures, select, tabs, toasts —, and whether each depends on the runtime API, the sprite or the `mc-js` flag, is listed in [§8.4](#84-the-components-per-screen-and-what-they-depend-on-sp-14).

### 2.4 The storage guard (SP-13)

- **What it does.** `storage-guard.js`, written new, redefines `localStorage` and `sessionStorage` on the page's window as non-configurable accessors that throw a `SecurityError`, as a browser with storage blocked does; the vendored scripts stay byte-identical and take their own "storage blocked" paths.
- **Confirmed from the vendored files.** Every storage access sits inside a `try`/`catch`: interactions.js's sidebar state (2 accesses), previous page (2) and date range (2); patterns.js's company view (3); guide.js's one helper (3) and router.js's fallback and previous page (4), the last two never loading. No vendored script uses IndexedDB, cookies, the Cache API or a service worker — counted by pattern in all six.
- **History state.** The loadable scripts write `{ mcNavDrawer: true }` (interactions.js, the phone drawer) and `{ ptSheet: id }` (patterns.js, a sheet), and rewrite the address with the same state (patterns.js, filters and tabs): addresses and non-sensitive keys, within the Owner's statement under SP-13. Router.js's state, with its scroll position and keys, does not load.
- **S2-2's checks.** After each journey: `DOMStorage.getDOMStorageItems` for local and session storage, 0 entries each (the probe's item k); no uncaught exception in the console (`Runtime.exceptionThrown`); `history.state` holding only the keys above; and a scan of the files written new for `localStorage`, `sessionStorage`, `indexedDB`, `document.cookie` and `caches`, none used outside the guard.

### 2.5 Icons (SP-16)

- **The release.** Lucide 1.56.0, published 2026-10-13, pinned for S2-2; ISC licence, and MIT for the icons derived from Feather, both notices kept beside the sprite. How S2-2 obtains the release's SVG files is for its authorization; S2-1 obtains nothing.
- **The sprite.** `icons.svg`, written new: each symbol from a Lucide SVG under the `i-` id the scripts expect, drawn at the reference's strokes by base.css's `.mc-icon` rule, which scales `--icon-stroke`, `--icon-stroke-sm` and `--icon-stroke-xs` to the icon's box. The reference's `icons.js` and its sprite are never copied; `prototype.js` sets the `mc-js` flag on the root element.
- **The symbols the vendored files reference**, each checked present in the release's `icons/` folder on 2026-10-14:

| Symbol | Lucide 1.56.0 | Symbol | Lucide 1.56.0 |
| --- | --- | --- | --- |
| `i-archive` | `archive` | `i-image` | `image` |
| `i-arrow-left`, `i-arrow-right` | `arrow-left`, `arrow-right` | `i-info` | `info` |
| `i-box` | `box` | `i-invoice` | `receipt-text` |
| `i-calendar` | `calendar` | `i-link` | `link` |
| `i-cart` | `shopping-cart` | `i-loader` | `loader` |
| `i-check` | `check` | `i-logout` | `log-out` |
| `i-check-circle` | `circle-check` | `i-note` | `sticky-note` |
| `i-alert-circle`, `i-alert-triangle` | `circle-alert`, `triangle-alert` | `i-paperclip`, `i-plus` | `paperclip`, `plus` |
| `i-chevron-down`, `-left`, `-right`, `-up` | `chevron-down`, `chevron-left`, `chevron-right`, `chevron-up` | `i-receipt`, `i-refresh` | `receipt`, `refresh-cw` |
| `i-clock`, `i-copy` | `clock`, `copy` | `i-scan`, `i-search` | `scan`, `search` |
| `i-credit-card`, `i-eye` | `credit-card`, `eye` | `i-sliders`, `i-sprout` | `sliders-horizontal`, `sprout` |
| `i-file-text`, `i-folder` | `file-text`, `folder` | `i-user`, `i-x` | `user`, `x` |
| `i-home` | `house` | `i-ms-return`, `i-ms-rotate` | drawn by interactions.js itself, its palette's own symbols |
| `i-more-horizontal` | `ellipsis` — patterns.js's menu trigger and "Tindakan Lain"'s compact icon | `i-dot`, `i-sun` | `dot`, `sun` — interactions.js's fallbacks for an account item without an icon and for a greeting band; neither is reached when every item names its icon and no page has `[data-greeting]` |
| `i-logo-mark` | not drawn — the MultipleCorp mark (GD-08; SD-04); the phone drawer's brand shows the name only | — | — |

The reference's `i-google`, the Google "G" of its unvendored `icons.js`, is never drawn (GD-08; SD-04): the button is the Owner's choice (§6). The names a runtime configuration or a journey screen supplies — R1's navigation icons, the state shapes of [DESIGN_SYSTEM_MAPPING §5](DESIGN_SYSTEM_MAPPING.md#5-states-and-tones) — are mapped in [§8.5](#85-the-icons-the-journey-screens-need-sp-16).

## 3. Language, the marker, the panel and the out-of-scope page

- **Language** ([LOCALIZATION_ARCHITECTURE §4](LOCALIZATION_ARCHITECTURE.md#4-the-prototypes-mechanism-prototype-only)): the two page sets under `/id/` and `/en/`, built from one copy table per screen; each page's `<html lang>` set to `id` or `en`, which interactions.js's own strings read, and under option (b) `MCProject.lang()` answering from the address; nothing stored. The string and layout checks of LOCALIZATION_ARCHITECTURE §§2.7–2.8 run with §4's tooling.
- **The marker, the panel and the out-of-scope page** ([SIMULATION_REGISTER §4](SIMULATION_REGISTER.md#4-the-marker-the-panel-and-the-out-of-scope-page)): written new in each page set; the panel an application dialog — interactions.js's under option (b), the prototype's own under (c) —, a sheet on phones.

## 4. The verification tooling

**The probe** ([DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §13.3), run in S2-1 on 2026-10-14 against `site/index.html` at `503e83b`: the read-only server started from the prototype's working copy on 127.0.0.1:8780; a script outside both repositories, Node.js built-ins only, launching the installed Chrome 155.0.8059.39 headless with a new temporary profile and the DevTools protocol over a pipe — `child_process.spawn` with two extra pipes, the documented `stdio` form —; never signed in to anything. Afterwards the browser and the server stopped, the profile, the capture and the script deleted, the port no longer listening, no process left, and both working copies showing nothing new.

| Item | Check | Method | Result |
| --- | --- | --- | --- |
| a) | computed styles ([SV-01](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)) | `CSS.getComputedStyleForNode`; `CSS.getPlatformFontsForNode` | demonstrated — the title's font, size 27.38 px, weight 700, colour `rgb(15, 16, 20)`; the pill's tint `rgb(226, 236, 244)`, radius 9999 px, height 24 px; the rendered face "Inter Variable", a custom font |
| b) | device metrics, scroll width against client width (SV-02) | `Emulation.setDeviceMetricsOverride`; `Runtime.evaluate` | demonstrated — 1440×900, 1366×768, 390×844 and 320×640: no horizontal scroll at any |
| c) | the type scale per viewport (SV-03) | `Runtime.evaluate` on computed sizes | demonstrated — body 12.96 px at 1,440, 12.88 px at 1,366 (`--tu` at .92), 14 px on phones; title 27.38, 25.54 and 25 px |
| d) | reduced motion (SV-04) | `Emulation.setEmulatedMedia` with `prefers-reduced-motion: reduce` | demonstrated — the media query matched; three elements with a transition, two under reduced motion; no animation on the shell |
| e) | keyboard focus by dispatched Tab keys (SV-05) | `Input.dispatchKeyEvent` | demonstrated — the skip link focused, `:focus-visible`, a solid 2 px olive outline `rgb(84, 94, 33)` with a 2 px offset; the element at its centre the focused one, so no sticky region obscured it |
| f) | element boxes for target sizes (SV-05) | `DOM.getBoxModel` | demonstrated — the skip link 197 × 39 px, the pill 165 × 24 px |
| g) | contrast from rendered colours (SV-05) | computed colours and the WCAG relative-luminance formula | demonstrated — title 17.81:1, subtitle 4.50:1, pill 11.86:1 |
| h) | text spacing as WCAG 2.2 1.4.12 states it (SV-05) | an injected style with line height 1.5, letter spacing 0.12 em, word spacing 0.16 em and paragraph spacing 2 em | demonstrated — 0 clipped elements before and after; no horizontal scroll |
| i) | a screenshot written to a temporary file, then deleted (SV-06) | `Page.captureScreenshot` | demonstrated — 23,506 bytes, SHA-256 `1ccda7cdc79b52bbd4a33d56bf45a05df4fbb8958827799aba83825cc7466dec`, deleted |
| j) | requests, origins, console messages and page errors (SS-05) | `Network.requestWillBeSent`; `Runtime.consoleAPICalled`; `Log.entryAdded`; `Runtime.exceptionThrown` | demonstrated — 7 requests, all to `http://127.0.0.1:8780`: the page, the five stylesheets and `InterVariable.woff2`; 0 console messages, 0 log entries, 0 exceptions |
| k) | local and session storage (SS-07; SP-13) | `DOMStorage.getDOMStorageItems` | demonstrated — 0 entries in each |

**Result: every check SV-01–SV-06 and SS-05 and SS-07 asks for can run in S2-2 with the installed browser and Node.js built-ins; no package is needed, so no installation is put to the Owner.** On the shell, d) had little motion to measure, and e) found one focusable element: the journey screens give both their substance.

**How S2-2 runs the checks** (`tools/verify/`): the same pipe client; per screen and state, in both languages and at SV-02's viewports, the methods of the table above; the results as text in S2-2's verification records, the render files in `evidence/s2-2/` (SV-06). The CSS and DOMStorage domains are marked experimental in the protocol's definitions, so each run records the browser's version, and a method that fails is recorded, never worked around by an installation. The reference renders side by side from a temporary clone of tag v1.0.0, verified as DIR-064 §13.1 states, outside both repositories, served read-only on 127.0.0.1 by the run's own script and deleted afterwards; it is never altered.

## 5. The phone test (SP-6; SS-06)

S2-0 did not run it: the network's Windows profile was Public, and the Owner chose not to change it (SP-6). The prototype's server serves another device only with `--host` set to a private address of this computer, during a session the Owner moderates, on a network with the Private profile, never public Wi-Fi, any firewall allowance for the Private profile only, no port forwarding, tunnel or hosting, and the server stopped after use ([SS-06](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)). S2-1 changes no setting and starts no such server.

| Option | When | What it needs | What it gives |
| --- | --- | --- | --- |
| (a) At the Owner's preview | after S2-2's report ([SD-08](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup)) | the Owner present; a trusted network the Owner sets to Private | the real phone at the moment the Owner judges the prototype |
| (b) In S2-2 | during the build | the same, in S2-2's session | an earlier real-phone check, at the cost of the Owner's presence then |
| (c) Emulation only | S2-2 and the preview | nothing | SV-02's 390 and 320 px renders by `Emulation.setDeviceMetricsOverride`; touch, the phone's own browser and its keyboard untested until C2 |

**Recommendation: (a)** — S2-2 relies on emulation (§4), and the real phone joins the preview the Owner attends anyway. The arrangement is an Owner question ([OWNER_QUESTIONS](OWNER_QUESTIONS.md#how-this-register-works)).

## 6. The simulated Google button of S2-J01

Google's [sign-in branding guidelines](https://developers.google.com/identity/branding-guidelines) (last updated 2026-07-07): the label "Sign in with Google", "Sign up with Google" or "Continue with Google", which may be translated — R1's "Lanjutkan dengan Google"; the standard-colour "G", never modified, on the light, dark or neutral theme — light: fill `#FFFFFF`, a 1 px inside stroke `#747775`, text `#1F1F1F`; Google Sans Medium 14/20; on the web 12 px before the logo, 10 px after it and 12 px after the text; a custom button allowed if it keeps these rules and its aspect ratio; at least as prominent as any other third-party option; and the button "must always include" the standard-colour "G". The prototype may not use the reference's drawn "G" ([GD-08](../../00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation); SD-04), loads nothing from Google ([SS-03](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061); SS-05) and has no Google Sans file.

| Option | Drawing | Fit |
| --- | --- | --- |
| (a) A neutral placeholder | the light theme's fill, stroke, padding and label, with a plain grey disc in the logo's place and the text in Inter — as VC1's anchor used a placeholder | recognizable by its label and position; no Google artwork; differs from the guidelines in the logo and the font, which the marker and the panel already explain |
| (b) A text-only button | "Lanjutkan dengan Google" in an outline button | the guidelines require the "G": the furthest from them |
| (c) Google's own "G" | the official logo file, obtained and kept in the prototype only | closest to the guidelines; a download of Google's asset under its terms, which needs the Owner's permission, and still no Google Sans |

**Recommendation: (a)** for the prototype; the application's button follows the guidelines in full, the "G" and the font included, in P11 with [GAP-042](../../00-governance/GAP_REGISTER.md#triage) (GR-13). The choice is an Owner question.

## 7. The AK-5 checklist

Before S2-2's commit, each item is checked by script over the files written new — everything outside `site/vendor/` — and the result recorded; any finding is fixed before the commit. Nothing outside V1 — a route, workflow, role, persona, fixture, logic, copy or capability — is imported from the reference ([AK-5](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup)).

| Demonstration item of the reference | The check that finds it |
| --- | --- |
| its route identifiers — the routes of its `project-config.js` and the identifiers its scripts ask `MCRoutes.href` for, such as `pembelian.form`, `dokumen.list`, `akun` and `search` | no file written new defines or links one of them; under option (b) `MCRoutes.href` answers `null` for every identifier a vendored script asks for, so the entry is hidden, unless the screen list maps it to the out-of-scope page; the full list read from the verified clone at check time |
| its personas — Dimas as Owner, Sari as Admin | no "Sari" anywhere; "Dimas" only as SP-19's Admin Operasional, with that role on every screen that names him |
| its `multiplecorp.id` addresses | no `multiplecorp.id`; every e-mail address under example.com, example.org or the .test, .example or .invalid domains (SS-02) |
| its fixtures — the configuration's fixtures and the Motion Study's sample records in interactions.js (two company names, thirteen Rupiah amounts, seven document numbers, one company code, sample project, client, goods and warehouse names) | the values extracted from the vendored interactions.js and from the clone's fixtures; none appears in a file written new; every record of `data.js` from SP-20's set. **Named exclusions** — values MULTIPLECORP's own records held before S2-1 that the samples repeat, kept in MULTIPLECORP's roles and passed by the check by name: the company CV Arunika Jaya and its code ARJ ([DESIGN_SYSTEM](../DESIGN_SYSTEM.md#design-system)'s fictional group, 2026-10-02; the sample uses the name for a supplier, which C1 adapted to *PT Contoh Komputindo*); and VC1's purchase — *Laptop 14 inci*, LPT-14-I5, Rp202.200.000 + Rp22.242.000 = Rp224.442.000 ([the gate record's C1 data](../evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--anchors)) |
| its numbering formats and the record-number example in patterns.js's comments | every number in `data.js` and the pages one of SP-20's synthetic numbers |
| its demonstration copy — the four original pages' texts, the documents' examples, the components' default micro-copy (GP-5) | every visible string of a page written new comes from its journey specification's copy table (LOCALIZATION_ARCHITECTURE §2.7's keyed elements); a vendored string shown is one DEVIATION_REGISTER lists |
| its example-page CSS blocks — `login-`, `dash-`, `pur-` and `ms-` in components.css and motion.css; `atq-` and `prj-` in patterns.css | no class with these prefixes in a file written new |
| its private lab and its 239 demonstration files | never accessed; no path of it recorded; the clone is tag v1.0.0 of the public repository only |
| interactions.js's demonstration sections, keyed by `body[data-page]` | every page written new sets `data-page="mc"` — never `login`, `dashboard`, `purchase` or `motion` —, `[data-shell]` only on shell pages, and no `data-demo` attribute; the values per page listed in §8.3 |
| its unvendored files — `icons.js`, `template/page-skeleton.html`, `project-config.js` | absent from the prototype repository |
| its brand marks and imagery | the sprite holds Lucide symbols only; no `i-logo-mark` or `i-google` symbol; no image of the reference (GD-08) |
| the vendored files themselves | `sha256sum -c VENDOR_SHA256SUMS.txt`: 15 OK |

## 8. The screen list for the Owner's approval

**For the Owner's approval** ([DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.12 item 8; [OQ-09](OWNER_QUESTIONS.md#oq-09--the-screen-list-for-s2-2)): every PS screen of the six journey specifications and the out-of-scope page, with its page, journey, persona, viewports and languages. A screen's state, the controls that work on it and where every other control leads are its journey's §4, and the steps that reach it its journey's §5. S2-2 builds exactly this list; a screen added after the Owner's approval needs the Owner's approval again (SD-05).

**How to read it.** *Page* is [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md)'s page; *Journey*, the journeys whose steps use the screen, read from their trace matrices; *Persona*, the page set that renders the screen with that persona's grants (SIM-05); *Viewports*, [SV-02](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)'s — 1440×900 and 1366×768 on the desktop, 390×844 and 320 px wide on the phone; *Languages*, the two page sets under `/id/` and `/en/` (SIM-14), every row existing in both.

### 8.1 The screens

| PS | Page | Journey | Persona | Viewports | Languages |
| --- | --- | --- | --- | --- | --- |
| PS-01 | PG-001 | S2-J01 | Laras, Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-02 | PG-001 | S2-J01 | Laras, Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-03 | PG-002 | S2-J01 | Laras, Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-04 | PG-002 | S2-J01 | Laras, Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-05 | PG-002 | S2-J01 | Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-06 | PG-004 | S2-J01 | Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-07 | PG-002 | S2-J01 | Laras, Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-08 | PG-006 | S2-J01, S2-J02 | Laras | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-09 | PG-007 | S2-J01, S2-J03, S2-J04 | Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-10 | the account menu over any shell screen | S2-J01 | Laras, Dimas, Bayu | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-11 | PG-017 | S2-J01 | Dimas | 1440×900, 1366×768 | id, en |
| PS-12 | PG-006 | S2-J02 | Laras | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-13 | PG-006 | S2-J02 | Laras | 390×844, 320 | id, en |
| PS-14 | PG-044 | S2-J02 | Laras | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-15 | PG-011 | S2-J02 | Laras | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-16 | PG-007 | S2-J03 | Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-17 | PG-011 | S2-J03 | Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-18 | PG-011 | S2-J03 | Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-19 | PG-042 | S2-J03 | Dimas | 1440×900, 1366×768 | id, en |
| PS-20 | PG-042 | S2-J03 | Dimas | 1440×900, 1366×768 | id, en |
| PS-21 | PG-042 | S2-J03 | Dimas | 1440×900, 1366×768 | id, en |
| PS-22 | PG-042 | S2-J03 | Dimas | 1440×900, 1366×768 | id, en |
| PS-23 | PG-042 | S2-J03 | Dimas | 1440×900, 1366×768 | id, en |
| PS-24 | PG-043 | S2-J03 | Dimas | 1440×900, 1366×768, 390×844, 320 | id, en |
| PS-25 | PG-017 | S2-J04 | Dimas | 1440×900, 1366×768 | id, en |
| PS-26 | PG-017 | S2-J04 | Dimas | 1440×900, 1366×768 | id, en |
| PS-27 | PG-017 | S2-J04 | Dimas | 1440×900, 1366×768 | id, en |
| PS-28 | PG-016 | S2-J04 | Dimas | 1440×900, 1366×768 | id, en |
| PS-29 | PG-016 | S2-J04 | Dimas | 1440×900, 1366×768 | id, en |
| PS-30 | PG-016 | S2-J04 | Dimas | 1440×900, 1366×768 | id, en |
| PS-31 | PG-007 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-32 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-33 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-34 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-35 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-36 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-37 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-38 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-39 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-40 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-41 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-42 | PG-022 | S2-J05 | Bayu | 390×844, 320 | id, en |
| PS-43 | PG-022 | S2-J05 | Bayu (S2-R06 variant) | 390×844, 320 | id, en |
| PS-44 | PG-007 | S2-J05 | Bayu, or the S2-R06 variant | 390×844, 320 | id, en |
| PS-45 | PG-045 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| PS-46 | PG-046 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| PS-47 | PG-046 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| PS-48 | PG-046 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| PS-49 | PG-046 → PG-045 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| PS-50 | PG-046 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| PS-51 | PG-046 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| PS-52 | PG-046 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| PS-53 | PG-046 → PG-045 | S2-J06 | Dimas | 1440×900, 1366×768 | id, en |
| the out-of-scope page | *Di luar cakupan prototipe* ([SIMULATION_REGISTER §4](SIMULATION_REGISTER.md#4-the-marker-the-panel-and-the-out-of-scope-page)), with the shell, and bare from PS-06 | all six | every persona | the viewports of the screen that leads to it | id, en |

PS-10 is also reached from every shell screen of S2-J02–S2-J05 — the account menu, or *Lainnya* › *Akun* on the phone —, and PS-11 from S2-J04's form with its input unsent. The S2-R06 variant of PS-43 and PS-44 is Bayu's page set without `stock.reserve`, a recorded scenario rather than a persona ([S2-J05](journeys/S2-J05.md#1-scope)).

### 8.2 Totals

- **Screens:** 53, PS-01–PS-53, and the out-of-scope page in its two variants. Per journey, the screens its steps use: S2-J01 11, S2-J02 5, S2-J03 10, S2-J04 7, S2-J05 14, S2-J06 9 — 56 uses of 53 screens, PS-08 serving two journeys and PS-09 three.
- **Viewports:** 17 screens at all four — PS-01–PS-10, PS-12, PS-14–PS-18 and PS-24 —, 21 at the desktop's two — PS-11, PS-19–PS-23, PS-25–PS-30 and PS-45–PS-53 —, and 15 at the phone's two — PS-13 and PS-31–PS-44: 17 × 4 + 21 × 2 + 15 × 2 = 140 renders per language, 280 in both. The out-of-scope page adds 4 per variant and language, 16, so 296 in all, before GR-16's second variant where text size matters ([DESIGN_SYSTEM_MAPPING §8](DESIGN_SYSTEM_MAPPING.md#8-the-fidelity-audit)).
- **Pages:** 14 of the inventory's 65 — PG-001, PG-002, PG-004, PG-006, PG-007, PG-011, PG-016, PG-017, PG-022 and PG-042–PG-046 —, its prototype column; the other 49 lead to the out-of-scope page, and PG-003 and PG-005 have no entry ([SIMULATION_REGISTER §4](SIMULATION_REGISTER.md#4-the-marker-the-panel-and-the-out-of-scope-page)).
- **Personas:** Laras on PS-01–PS-04, PS-07, PS-08, PS-10 and PS-12–PS-15; Dimas on PS-01–PS-07, PS-09–PS-11, PS-16–PS-30 and PS-45–PS-53; Bayu on PS-10 and PS-31–PS-44, the S2-R06 variant on PS-43 and PS-44 (SP-19).

### 8.3 `data-page`, `data-shell` and the scripts per page

| Pages | `data-page` | `[data-shell]` | Scripts under option (b) | Under option (c) |
| --- | --- | --- | --- | --- |
| PS-01–PS-05 and PS-07 (PG-001, PG-002); PS-06 (PG-004); the out-of-scope page reached from PS-06 | `mc` | absent | `storage-guard.js` in the head; `runtime.js`, `data.js`, `patterns.js`, `prototype.js` and `interactions.js` at the end of the body (§2.3) | `storage-guard.js`; `data.js` and `prototype.js` |
| PS-08–PS-53 and the out-of-scope page with the shell | `mc` | on the shell's root | the same | the same |

No page sets `login`, `dashboard`, `purchase` or `motion`, and none carries `data-demo`. Without `[data-shell]`, interactions.js's Shell section, its palette and its phone navigation stay idle, and its Login section never runs (§2.1). §7's check reads both values from every page of both sets.

### 8.4 The components per screen and what they depend on (SP-14)

Under option (b), read from the vendored scripts' sections and globals; "the sprite" is §2.5's and §8.5's symbols, "the flag" the `mc-js` class that components.css's 38 rules key on — the shell's folded groups, the account trigger, the rail menu and the phone disclosures. Under option (c) every row is the prototype's own script on the same classes: the runtime dependencies fall away, the sprite and the flag stay.

| Component | Provided by | Depends on | Screens |
| --- | --- | --- | --- |
| Sidebar, *Menu lengkap*, icon rail, top bar, skip link | interactions.js — the project shell, the Shell section, the persistent shell | `MCProject` (`navTree`, `navCurrent`, `navItem`, `home`), `MC_PROJECT.navigation` and `.user`, `MCBrand`; the sprite; the flag | the shell screens at the desktop viewports |
| Phone app bar, bottom bar, *Lainnya* drawer and back | interactions.js — the responsive shell, the phone and tablet navigation | `MCProject` (`bottomItems`, `navTree`), `MC_PROJECT.mobileDisclosure`; history `{ mcNavDrawer: true }`; the sprite | PS-08–PS-10, PS-12–PS-18, PS-24 and PS-31–PS-44 on the phone |
| Account menu | interactions.js — `MCAccount` | `MCProject.accountMenu`, `MC_PROJECT.account` with `logout.confirm` false; the sprite; the flag | PS-10; *Keluar* over unsent input → PS-11 by prototype.js's `mc:logout` handler |
| Dialogs and confirmations | interactions.js — `MCDialog`, `MCConfirm` | `MCProject`: their section returns without it | PS-11 ([MSG-35](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue)), PS-21, PS-29 and PS-40 (MSG-39), PS-35 and PS-48 (IP-08); the panel on every screen |
| Search palette | interactions.js — the palette's application mode | `MCRoutes`, `MCProject`; the sprite | every shell screen; no screen of §8.1 is a palette's, so its entries, like "Cari atau pindai kode" and *Cari*, lead to the out-of-scope page |
| Sheets | patterns.js — `MCSheet` | history `{ ptSheet: id }`; no runtime member | a line's sheet on PS-33, PS-34, PS-37, PS-42 and PS-43; *Cari Produk* on PS-38 |
| Disclosures | interactions.js — `MCDisclosure`, phones only; prototype.js on the desktop | the flag; the sprite (`chevron-down`) | *Pekerjaan*'s groups on PS-09, PS-16 and PS-31; the journey's on PS-18; "Rincian lain" on PS-27; the sign-in's answers on PS-01 and PS-07 |
| Select and lookup | interactions.js — `MCSelect`; the lookup in prototype.js | no runtime member; the sprite (`chevron-down`, `check`) | *Pemasok* on PS-25; *Perusahaan*, *Klien*, the method, the account and the statement line on PS-46 and PS-47 |
| Date field | patterns.js — `MCDateField`, `MCBizDate` | `MCTime.today`, the business day (SIM-13) | PS-46 and PS-47 |
| Money and quantity entry | patterns.js — `MCForm` | none | PS-25–PS-27, PS-33, PS-34, PS-46, PS-47 and PS-50–PS-52 |
| Upload | interactions.js — `MCUpload` | no runtime member; the sprite (`paperclip`, `file-text`, `image`) | the bill's evidence on PS-27; the proof on PS-47 |
| Lists and tables | patterns.js — `MCList`, `MCUI` | `MCTime` for dates, guarded | PS-09, PS-14, PS-16, PS-31, PS-44, PS-45, PS-49 and PS-53; "Muat Lagi" → the out-of-scope page |
| Tabs | patterns.js — `MCTabs` | `MCProject.navCovers`, `MCBrand.title` | *Piutang*'s tabs on PS-14, the other tabs → the out-of-scope page |
| Menus — "Tindakan Lain", "Buat" | patterns.js — `MCMenu` | the sprite (`ellipsis`, `chevron-down`) | drawn on PS-15, PS-17, PS-24, PS-28 and the top bar; each leads to the out-of-scope page, so no menu opens |
| Long text | interactions.js — `MCLongText` | none | the lists and the record headers |
| Toasts | interactions.js — `MCToast` | none | none planned: every answer is inline, and a toast only repeats a background completion shown in place (GR-15) |
| Written new | prototype.js, under both options | the sprite; the flag, set here | the sign-in's composition, "Tampilkan" and the code entry (PS-01–PS-07); the stage track and the now strip (PS-15, PS-17, PS-18); the segmented stage indicator (PS-08, PS-12); the ageing chart with its table (PS-12); the composer and its paper (PS-19–PS-23); the rendition states and "Salin nomor" (PS-22, PS-23, PS-30, PS-41); the scan field (PS-32, PS-36); MSG-35's question (PS-11); the marker, the panel's content and the language switch (every screen) |

### 8.5 The icons the journey screens need (SP-16)

The names a runtime configuration or a journey screen supplies, beyond §2.5's: each a Lucide 1.56.0 name under an `i-` id, checked present in the release's `icons/` folder on 2026-10-14. Where the vendored scripts pair a symbol with the same concept, the screen keeps the pairing — the symbol only, never the entry's text, route or record (AK-5); the others are proposed (Level 1), approved with this list (OQ-09). The reference's tree draws a child row as a plain link without an icon; whether *Menu lengkap*'s modules are entries or children is the runtime configuration's (§2.2), so their symbols are listed for the case where they are entries.

| Element | Symbol → Lucide 1.56.0 | Basis |
| --- | --- | --- |
| *Beranda* | `i-home` → `house` | the reference's |
| *Pekerjaan* | `i-list-checks` → `list-checks` | proposed |
| *Proyek* | `i-folder` → `folder` | the reference's pairing for a project |
| *Tinjauan*; decision row 3, new activity ([QS-14](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)), which leads there | `i-eye` → `eye` | proposed, on the reference's symbol |
| *Menu lengkap* | `i-layout-grid` → `layout-grid` | proposed |
| *Cari*, "Cari atau pindai kode", *Cari Produk*; the lookup | `i-search` → `search` | the reference's |
| *Lainnya*; the menus' trigger | `i-more-horizontal` → `ellipsis` | the reference's (§2.5) |
| "Buat" | `i-plus` → `plus` | the reference's |
| The account menu's trigger and *Akun Saya*; *Keluar* | `i-user` → `user`; `i-logout` → `log-out` | the reference's pairings |
| *Bahasa tampilan* | `i-languages` → `languages` | proposed |
| The phone app bar's back; "Masuk"'s arrow (VD-05) | `i-arrow-left` → `arrow-left`; `i-arrow-right` → `arrow-right` | the reference's |
| The modules *Pembelian*, *Gudang*, *Keuangan*, *Dokumen* | `i-cart` → `shopping-cart`; `i-box` → `box`; `i-receipt` → `receipt`; `i-file-text` → `file-text` | the reference's pairings for a purchase, goods, receivables and a document |
| The modules *Laporan*, *Data Master*, *Aktivitas & Keamanan*, *Pengaturan* | `i-chart-column` → `chart-column`; `i-database` → `database`; `i-shield` → `shield`; `i-settings` → `settings` | proposed |
| The KPIs *Nilai penjualan*, *Kas masuk*, *Piutang aktif* | `i-invoice` → `receipt-text`; `i-credit-card` → `credit-card`; `i-receipt` → `receipt` | the reference's pairings for an issued invoice, a payment received and receivables |
| The KPI *Laba manajerial* | `i-coins` → `coins` | proposed |
| Decision row 1, the security notice; row 4, a routed refusal (QS-20); row 5, a residual obligation (QS-16) | `i-shield-alert` → `shield-alert`; `i-circle-x` → `circle-x`; `i-list-todo` → `list-todo` | proposed |
| Decision rows 2, 6 and 7 — QS-22, QS-10, QS-11 | `i-scale` → `scale`; `i-alert-triangle` → `triangle-alert`; `i-flag` → `flag` | their states' shapes, below |
| The period control; the date field | `i-calendar` → `calendar` | the reference's |
| *E-mail* and *Kata sandi* on PS-01, "labelled fields with icons" (VD-05) | `i-mail` → `mail`; `i-lock` → `lock` | proposed |
| "Salin nomor"; "Unduh"; "Cetak" | `i-copy` → `copy`; `i-download` → `download`; `i-printer` → `printer` | the reference's; proposed; proposed |
| The scan field; the upload and its files | `i-scan` → `scan`; `i-paperclip`, `i-file-text`, `i-image` | the reference's |
| Disclosures, selects, close; an answer's success, refusal and information | `i-chevron-down`, `i-check`, `i-x`; `i-check-circle`, `i-alert-circle`, `i-info` | the reference's |
| The blocker marker of "Proyek yang perlu perhatian" and the track's *Terhambat* | `i-alert-triangle` → `triangle-alert` | "a warning icon and its words" (R1) |

**The state shapes** — [DESIGN_SYSTEM_MAPPING §5](DESIGN_SYSTEM_MAPPING.md#5-states-and-tones)'s, each under the `i-` id of its Lucide name, for the states the journeys name: `circle-dashed` — *Belum Dimulai* (S2-J02, S2-J03) and *Belum Dicatat* (S2-J05); `contrast` — *Berjalan* (S2-J02, S2-J03); `check` — *Tuntas* (S2-J02, S2-J03) and *Tercatat* (S2-J05); `circle-minus` — *Tidak Diperlukan* (S2-J02, S2-J03); `triangle-alert` — *Lewat Jatuh Tempo* (S2-J02); `square` — *Belum Ditagihkan* (S2-J02, S2-J03, S2-J06); `flag` — *Sengketa* (S2-J02); `scale` — *Menunggu Penetapan* (S2-J02); `file-text` — *Terbit* (S2-J03, S2-J06); `ellipsis` — *PDF Sedang Dibuat* (S2-J03, S2-J04); `file-check` — *PDF Siap* (S2-J03); `circle-dot` — *Terbuka* (S2-J04); `file-question-mark` — *Tanpa Bukti* (S2-J06), the release's name for §12.1's `file-question`.

**Totals.** 24 Lucide names beyond §2.5's — `list-checks`, `layout-grid`, `languages`, `chart-column`, `database`, `shield`, `settings`, `coins`, `shield-alert`, `circle-x`, `list-todo`, `mail`, `lock`, `download` and `printer`, proposed, and the shapes `circle-dashed`, `contrast`, `circle-minus`, `square`, `flag`, `scale`, `file-check`, `circle-dot` and `file-question-mark`, fixed by §12.1 —, each checked present on 2026-10-14; `i-logo-mark` and the Google "G" stay not drawn (§2.5; §6).
