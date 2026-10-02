## 6. Session, expiry, re-authentication and unsent input

### IP-11 — Session limits, expiry and re-authentication

This section is D-UX-03. The limits are SECURITY's: 60 minutes idle, 12 hours absolute, no remember-me (AU-07, AU-08). Only user-initiated requests count as activity; polls, deferred groups, thumbnails and prefetches are sent as background requests and never extend a session (H7-13; RV-06) — the client marks them with a request header, which the polling and reload helpers accept (section 2, documented); that deferred-group and prefetch requests carry it as well is inferred and proved in P8 (UXH-02), and thumbnails and previews are background by their route (RV-06).

| Situation | Behaviour |
| --- | --- |
| Idle warning | Five minutes before the idle limit — counted from the last user-initiated request of this browser tab — a dialog shows MSG-33 with the remaining time and **"Tetap Masuk"**, which sends one user-initiated request and so extends the session; the dialog is announced to assistive technology and can be used any number of times within the absolute limit (WCAG 2.2.1). When the count ends, the page asks the server by a background request whether the session is still valid — a request that extends nothing (RV-06) — and either starts counting again, because another tab of the same browser kept the session alive, or treats the session as ended (below) |
| Absolute limit | Fifteen minutes and again five minutes before the 12-hour limit a notice says when the session ends, that it cannot be extended and that input not sent by then will be lost (MSG-34); a page loaded within the last fifteen minutes shows it at once and keeps it until the deadline, and with an open intent it adds MSG-07's warning and the list where the record would appear. The limit is a security control and is not extendable; it is the one session limit that ends with a full document load of the sign-in page. Its time reaches the client as a non-sensitive timestamp with each authenticated response, outside the page data, so the shared props of WS-07 stay as they are — read from the response headers, which is inferred; the documented fallback is the standalone request helper's status check (UXH-11) |
| A request answered 401 or 419 while a page is open and before the absolute limit — idle expiry, a password changed elsewhere (AU-09), a session the Owner ended, a rotated token | The page is not left (section 2: the client intercepts the answer). At once the client clears the Inertia history and its page and prefetch caches (H7-12), and an **in-place dialog** covers the page with an opaque layer — what was on screen is no longer readable — and asks for the account's e-mail address and password (MSG-11), both with `autocomplete`, paste and password managers allowed. The page component stays mounted, so the form's input, its `lock_version` and a pending `command_id` stay in memory, and nowhere else |
| After the e-mail and password are accepted | The session identifier and the forgery token are new (AU-06), and the client clears history and caches again at this authentication boundary before the dialog closes, so no page saved under the ended session can be shown by Back; the current page is shown again with its input. A **form that was never sent** is submitted — when the user presses its button — as a new intent that meets the stale checks of IP-03 (WF-ACC-01; ST-05). A **submission whose answer is unknown** is shown with MSG-07 and "Coba Lagi", which sends the same `command_id` (HO-02): if it had committed, the recorded outcome comes back; if not, it runs now with every check |
| The password is not accepted | The generic failure of AU-04 (MSG-19), throttled as AU-05 says; the dialog stays. A deactivated account can never pass it, and its unsent input ends with its access (UXS-24) |
| "Keluar" from the dialog, or another person at the device | A warning that input not sent will be lost (MSG-35), then a full document load of the sign-in page — nothing of the old page remains in memory or history |
| The absolute limit passes, or the user logs out | A full document load of the sign-in page with history and caches cleared (H7-12). Input cannot be kept here: the absolute limit was announced twice beforehand, and a logout with unsent input asks first (MSG-35) |
| Leaving a page with unsent input by navigation, reload or closing the tab | The application's own guard for in-app navigation — built on the client's navigation events, which is inferred and proved in P8 (UXH-02) — and the browser's standard leave prompt for a reload or close; never a silent loss (H7-05) |
| The sign-in page and the first page after sign-in | Loaded as full documents with history cleared (AU-06); Back from the sign-in page never shows an application page |

Rules that hold throughout: a form's data, a `command_id`, S3 and S4 values and identifiers of out-of-scope records are never written to localStorage, sessionStorage, IndexedDB or Cache Storage, and no form uses the history-state remembering that the form helper and `useRemember` offer (WS-07, WS-08; section 2). History encryption — SECURITY's control — keeps only its own key in session storage. The in-place dialog accepts only the account whose page it covers: after a successful sign-in the client compares the new session's shared props — display name, capabilities and granted companies — with those of the covered page and, on any difference, makes the full document load before anything is uncovered or resent (UXH-02); signing in as someone else is therefore always the full document load. The dialog's password field allows paste and password managers (WCAG 3.3.8; H7-04).

**Basis and residual.** The interception of a 401 or 419 answer without leaving the page, the clearing of history and the standalone request are documented (section 2). That the in-memory input survives the interception follows from the page not being left; it is not stated by the documentation and is a P8 proof obligation (section 16), as is the survival of a payload resent after a transport failure and after a step-up. Residual of the same-account check: two accounts with the same display name, capabilities and granted companies pass the comparison; the second sees only its own projection, and P8 tests the case (UXH-02). Should the proof fail, the fallback is the full document load after a warning — the rule of the absolute limit — and never browser storage; no SECURITY control is weakened either way. For the default redirect of an unauthenticated request to be replaced by a 401 answer to an Inertia request, the build units configure the authentication middleware; this changes how the expiry is answered, not who is authenticated.

## 7. Forms, multi-line entry and validation

### IP-13 — Entry

- **Reuse before retyping.** A form opened from a project takes its company, client, unit, PIC, lines, prices and tax treatment from the confirmed data (AC-04; WCAG 3.3.7); the user changes only what differs. A master is chosen by lookup (IP-17), never retyped.
- **Company.** A form that creates a company-owned root — a project, a purchase without a project, a payment, an expense, evidence — has the required field "Perusahaan"; a form on an existing record shows that record's company as a fixed label in its header ([INFORMATION_ARCHITECTURE §7](../INFORMATION_ARCHITECTURE.md#7-company-context-model)). An account with one company sees the label only.
- **Money and quantities.** A value is submitted as the decimal string the user entered, after a purely textual normalization of the Indonesian separators — a dot accepted only as the thousands separator between groups of three digits and removed, the decimal comma turned into a point — and never through a floating-point number (ARCHITECTURE §10); any other dot or character is a validation error (IP-14), never a silent reinterpretation. On leaving the field the value is shown formatted, so the user sees what will be sent. More decimals than the field's scale — two for rupiah, the product's quantity scale for a quantity — is a validation error, never a silent rounding. A quantity is entered in a chosen unit and shown with its base equivalent ("2 dus (10 rim)") from the server's conversion.
- **Totals.** Line and form totals shown while typing are labelled **"Perkiraan"** and are a display aid computed on the entered strings; availability, remaining quantities, outstanding amounts, credit and every saved total are server values and are shown as such after the answer (UXI-14).
- **Multi-line entry** (PT-10) — the lines of a project, quotation, purchase, invoice or count: on desktop a table of form rows with a fixed focus order, an always-present empty last row, a product lookup in the first cell, row numbers, and keyboard actions to add, remove and move a row ([DESIGN_SYSTEM §13](../DESIGN_SYSTEM.md#13-keyboard-and-scanner)); it is a form laid out as rows — no free cell grid, no formulas, no paste of arbitrary blocks. On a phone each line is a dense row that opens an editing sheet. A draft is saved as a whole by its own command and carries the `lock_version` of its root (ST-01).
- **Reasonable phone forms.** Every form of a `[floor]` or `[both]` workflow is usable on a phone in one column with the action bar fixed at the bottom; long desk forms open on a phone for reading and small corrections and do not break.

### IP-14 — Validation

Validation runs on submit, on the server; the client may check formats earlier as a convenience. Errors return to the same form with every input kept; each message stands beside its field and says what is expected; the first invalid field takes focus and a summary line says how many fields need attention (MSG-12). A message never contains another record's data (WS-07), a password field is always returned empty (WS-10), and a reference that does not resolve reads exactly like one that does not exist (CS-04). A cursor that fails its check is a validation error of the list (IP-18).

## 8. Background work

### IP-12 — Derived background work

The committed action is always shown as done; only the derived work has a state (HO-01).

| Work | States shown | Behaviour |
| --- | --- | --- |
| A document rendition (JB-01, JB-02) | *PDF sedang dibuat* (PENDING, RENDERING) · *PDF siap* (READY) · *PDF gagal dibuat* (FAILED) | The document shows its number and *Terbit* from the moment the issue commits, and the number never changes (NM-07). While pending, the record's page polls its rendition group no faster than once a minute as a background request, and the user may refresh it. FAILED shows MSG-08 with the attempt count and that the system tries again by itself; after the fifth failed attempt MSG-08's exhausted variant says the operator has been told (JB-01); there is no "terbitkan ulang". The same states appear in QS-08 |
| An export (JB-03; H6-04) | in the requester's own list "Ekspor Saya": *Diminta* · *Diproses* · *Siap Diunduh* · *Gagal* · *Tidak Tersedia* (WITHHELD) | The request is admitted or refused at once: a third request while two are in progress gets MSG-22 (WS-12), a request above the row bound MSG-21 (PF-28) — at once where admission counts the rows, otherwise as *Gagal* carrying MSG-21's text when the job finds the bound before generating (P11 chooses; UXH-11). "Unduh" is an ordinary browser download (RB-04; PF-29), a user-initiated request. A withheld request offers no download, and its address answers as AZ-11 (RV-08). *Gagal* offers "Minta Lagi" — a new request. Only the requester sees the list |
| An import — validation and commit (JB-05, JB-07) | section 13 | — |
| Image derivatives (JB-04) | none of its own | A thumbnail that is not ready yet shows the neutral placeholder of PT-22 |

A page with background work refreshes by the user's action or by polling no faster than once a minute, naming its props (PF-26, PF-36); the polling stops when the work has reached a final state.

## 9. Lists, search, scanner and evidence

### IP-18 — Continuing a list

A list that can grow shows 25 rows and the control **"Muat Lagi"** (PF-13, PF-17); it shows no total, no page number and no "x dari y" (PF-15). "Muat Lagi" is a user-initiated request that appends the next rows; when nothing is left the control is replaced by "Semua sudah ditampilkan.". A filtered list whose branch reached its read bound before filling the page says so and still offers "Muat Lagi" (PF-42). A cursor that fails its check — after a change of filters in another tab, a change of grants or a tampered address — is answered with MSG-26 and the offer to load the list from the start; the list never silently restarts (PF-14). Sort and filter controls exist only for the keys the screen inventory names ([INFORMATION_ARCHITECTURE §8](../INFORMATION_ARCHITECTURE.md#8-screen-inventory)); a column without a registered key has no sort affordance.

### IP-17 — Search and lookup

| Input | Behaviour |
| --- | --- |
| A code — barcode, SKU, serial, project number, document number | exact lookup first (PF-19): one hit opens or selects it; several rows that share the key within scope are listed for the user to choose (never chosen by the system); none says so (MSG-25) |
| A name with at least three consecutive letters or digits | names equal to the term or beginning with it first, then similar ones (PF-20, PF-21); a type-ahead shows at most 20 rows, a result list one page |
| A shorter term, or one without a trigram | exact and prefix matches only, with MSG-24 as a hint; never a wider search (PF-23) |
| More than 200 candidates | the list is shown with MSG-23: it was cut, and a narrower term is needed |
| More than 100 characters | not accepted by the field (PF-23) |

Company-owned records are found only within scope (PF-22; DP-06); a result never hints at a record outside it. The search limiter's refusal is MSG-20.

### IP-15 — Scanner

The scanner is a keyboard wedge: it types its code and usually ends with Enter (D-UX-09). Camera scanning is not part of V1 — SECURITY's Permissions-Policy denies the camera (WS-08), and V1_SCOPE D-05 promises none — and is recorded as a handoff under GAP-020, not designed here.

1. **One scan target.** A screen that takes scans has one scan field (PT-20) that says it is listening ("Siap Memindai") and holds focus while the scan step is active; on entering the step the field is focused, and after each scan it is cleared and focused again.
2. **Exact lookup only.** The field's content goes to the exact lookup of IP-17 under the scanner's own limiter (QB-06; WS-12) — never to name search.
3. **Enter never submits a command.** In the scan field Enter means "look this code up and add or select it". The command of the screen is sent only by its own button and confirmation (IP-08). A burst that arrives while focus is outside a field that accepts scans — on a button, in a dialog, in any other field — is discarded with its Enter and DESIGN_SYSTEM §13.2's message; the fields that accept scans are the scan fields (PT-20), *Cari* and the product picker of a line (PT-27), each as an exact lookup.
4. **Shortcuts are off.** No shortcut is a single printable key, and none acts while the scan field has focus ([DESIGN_SYSTEM §13](../DESIGN_SYSTEM.md#13-keyboard-and-scanner)), so scanned characters cannot trigger one.
5. **Unknown code** → MSG-25 with the code shown; an account holding `products.edit` is offered "Daftarkan Barcode", a command of its own (WF-MD-04); nothing is created by the scan.
6. **Ambiguous code** → the candidates are listed and the user chooses; nothing is selected by the system (SF-SCAN).
7. **A serial scanned twice** in one command counts once (MSG-36). A serial whose state does not fit the operation — in stock at receiving, not in stock or unusable at dispatch, not dispatched on that line at a return — is refused at the scan with the state in words.
8. **Quantity items** are scanned once and their quantity entered; nothing forces a scan per unit (AC-03).
9. **No scanner attached:** the same field accepts a typed SKU or code, and a "Cari Produk" lookup by name sits beside it; the flow is otherwise identical (V1_SCOPE).
10. Every scan gives immediate feedback in text and by a non-colour cue — the line it matched and the running count — announced through a polite live region while focus stays in the field, scan errors assertively, and, where the device allows, a short vibration; feedback never relies on sound alone.

### IP-16 — Evidence upload

1. **Choose the file** — by the file picker, which on a phone may open the camera application for a photo. The field states the allowed types and the size limit of its purpose (FL-02); a file outside them is refused before or at the upload with MSG-31, and nothing else is sent.
2. **Upload** runs outside any command (FL-01; CI-09) with a progress bar and "Batalkan"; it counts against the upload limiter (WS-12).
3. **Register** — the command that follows (NX-07): evidence type, issuer, the issuer's reference number, document date, and for amount-bearing types the amount; company, or "Gudang bersama" for the two pool types. A pool upload requires the tick of H7-11 — MSG-32: the file shows only the physical condition of shared stock and is no company's document. An account sees and can choose only the evidence types of the families it may view (PJ-08).
4. **Identity.** Registering an identity that already exists in the same company offers "Tambahkan sebagai Versi Baru" — versions, never a second record (L-45). A replacement of evidence already in use is two steps, register and then "Jadikan Versi Berlaku" (NX-14), each with its own answer (IP-19).
5. **Warnings** — a reference-less match and a content or cross-type match the actor may view — follow IP-05 with `warnings.override`. A match the actor may not view shows nothing (UXI-08). An upload that equals a system rendition can never satisfy a client original (FS-12; FL-05). The upload is always recorded as a reproduction (FL-05; DATABASE §10); the flag and its note are shown to a viewer only when that viewer may see the matched rendition — its company in scope and its family visible (PJ-15). To any other viewer, the uploader included, nothing is shown — no flag, no note, no reason — and the client-original requirement the upload was linked to stays open in its ordinary state; the uploaded bytes are themselves that rendition, whose content names its company and number, and the residual is recorded in GAP-034.
6. **Viewing.** An uploaded file is opened only as a download; an image shows the re-encoded preview; a system rendition is displayed inline ([DESIGN_SYSTEM §14](../DESIGN_SYSTEM.md#14-domain-presentations)).

### IP-20 — Choosing a reservation cut

When a supply-reducing action — a condition change, a count loss, an adjustment, a receiving reversal, a purchase return — would leave usable supply below the active reservations, the same form shows the block "Stok Tidak Cukup untuk Semua Reservasi": the shortfall, and the active reservations of the product with their quantities — the project named where its company is in scope, otherwise the relation marker. The actor enters how much to cut from which reservation until the shortfall is covered; no order is proposed and nothing is cut by the system (BR-RSV-04). The cut commits with the action (AX-02) and needs `stock.reserve`. If a chosen cut touches a project of a company outside the actor's grants, the action is refused and routed (IP-06). The affected projects are flagged in QS-04.

