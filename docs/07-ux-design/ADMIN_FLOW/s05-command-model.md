## 5. Command interaction model

A command is one user-intended business action (WORKFLOWS §1) sent as one state-changing request (RB-03). The patterns below hold for every command form and action button; the journeys of section 10 only name what is specific to them.

### IP-01 — Answers of a command

The server's answer decides what the screen says (D-UX-01). The screen never announces success before the answer, and never shows an optimistic result for a command. Each answer is announced to assistive technology and never conveyed by colour alone ([DESIGN_SYSTEM §12](../DESIGN_SYSTEM.md#12-status-outcome-and-state-patterns)).

| Answer | Meaning, as its owner defines it | What the screen does | Copy pattern |
| --- | --- | --- | --- |
| COMMITTED | every effect committed (SQ-01) | shows the recorded result from the answer — the record with its number, state and server totals — and ends the intent | MSG-01 |
| REJECTED | a precondition was false or a rule refused the write; nothing of the action committed (SQ-02, SQ-04, SQ-05, SQ-10) | keeps the form and its input, states the failed rule in business words with the current fact the answer carries, and marks the field or line concerned where there is one; a corrected submission is a new intent (CI-06) | MSG-02 |
| CONFLICT | the command was prepared against superseded facts, or its intent already exists (SQ-03, SQ-07, SQ-08; CI-05) | IP-03 — except DP-18's refusal of an import identity outside scope, which shows MSG-18 alone under the neutral label of the REJECTED face, identical for a batch and a row ([DESIGN_SYSTEM §12.2](../DESIGN_SYSTEM.md#122-outcome-presentation)) | MSG-03, MSG-04, MSG-18 |
| PENDING | only for background work after a committed action (WORKFLOWS §1) | IP-12: the committed action is shown as done, the derived work as in progress | MSG-05 |
| FAILED | the command could not be completed or confirmed for a technical reason; no outcome is recorded (SQ-11–SQ-20) | IP-04: never "saved", never "not saved" unless the answer itself says nothing was committed; offers the retry with the same `command_id` | MSG-06, MSG-07 |

The answers that are **not** outcomes of a command are presented differently from all five:

| Answer | Presentation | Copy pattern |
| --- | --- | --- |
| The AZ-11 denial — an address outside scope or nonexistent (SQ-09) | the not-found page of IP-21; identical wording, layout and status for both cases (WS-10) | MSG-09 |
| The AZ-11 denial — a reference inside a command that does not resolve or resolves outside scope (SQ-09; CS-04) | IP-14: an error at the field concerned, identical for both cases, with every input kept; never the not-found page | MSG-12 |
| The AZ-11 denial — in scope, capability missing (SQ-22) | the forbidden state of IP-21; nothing of the record is shown | MSG-10 |
| 401 and 419 — the session has ended or the request-forgery check failed | the in-place re-authentication of [section 6](s06-09-session-forms-lists.md#6-session-expiry-re-authentication-and-unsent-input) | MSG-11 |
| Validation errors — the request never reached the envelope's decision | IP-14: errors beside their fields, input kept | MSG-12 |
| Network loss | only when the browser reported itself offline before the request was dispatched is nothing sent, and the form is unchanged (MSG-13); every failure after dispatch — a transport error, a timeout, an interrupted visit — is the unknown-outcome branch of IP-04 | MSG-13, MSG-07 |
| A rate limit (WS-12) | the form stays; the message says when to try again — for a first attempt also that nothing was processed; for a retry of an open intent it says only when to try again, and the intent stays open (IP-02) | MSG-20 |
| A step-up required (AU-15) | a non-redirect answer the client intercepts as it intercepts a 401 (UXH-11): the step-up dialog of IP-10 opens, and after it the same `command_id` and payload are sent again | MSG-27 |

### IP-02 — One `command_id` per intent

| Moment | Rule |
| --- | --- |
| Minting | The client generates the identifier (CI-01) when the user triggers the submission — not when the form opens — and binds it to the exact payload it sends — only when no intent of the form is open: a trigger while an intent is in flight or unknown — a second click, Enter, KB-09, KB-10, the automatic retry — is dropped, or resends the open intent's identifier and payload, never a new identifier. A form that was never sent has no identifier (HO-02). |
| In flight | The submit control is disabled and marked busy while the request runs. This is a convenience only (HO-03): the client may be interrupted by another visit while the server completes the command, so the screen that follows shows the recorded outcome it receives — by the retry of IP-04 or by opening the record — and never assumes the command was lost. |
| Terminal outcome | COMMITTED, REJECTED and CONFLICT end the intent. A later submission — the corrected form after REJECTED, the re-applied form after CONFLICT — is a new intent with a new identifier (CI-06). |
| Answered, but not an outcome | After a validation error, an AZ-11 denial, a rate limit or a 401 or 419 answer to a **first attempt**, the command did not run. The identifier is kept while the payload is unchanged and replaced when the user changes the payload. To a **retry of an open intent**, any answer other than COMMITTED, REJECTED or CONFLICT leaves the intent open — inputs frozen, identifier and payload kept, MSG-07 shown — because an earlier attempt may have committed. When that answer is a denial, a validation error or a rate limit, its own text is shown beside MSG-07 with "Tinggalkan" and the list where the record would appear, so the user learns that access was lost or the request refused. |
| Unknown | After FAILED, after a network loss once the request had left, and while no answer has arrived, the intent stays open: the same identifier and the same payload are sent again by every automatic and manual retry (HO-02, HO-07), also after a re-authentication (ST-05). **The form's inputs are frozen until the intent ends** — the user can retry it or leave it, not edit it — so an edited resubmission can never become a second effect beside a first one that did commit (D-UX-02). |
| Leaving an open intent | Leaving the page, discarding the form or logging out while an intent is unknown asks for confirmation with MSG-07's warning and offers the list where the record would appear. The identifier is then gone; a later attempt is a new intent, and the business identities and duplicate warnings of CONCURRENCY_IDEMPOTENCY §11 are what stands behind it (IP-05). |
| Another account, or a deactivated one | A replay under another account is the CONFLICT of CI-05 that discloses nothing; a deactivated account has no session and meets section 6, where it cannot sign in (AU-04). |

### IP-03 — CONFLICT and stale state

The answer carries the cause and, where the actor may see it, the current state. Any authorized account may have made the change (PX-07; WORKFLOWS §11): the screen names the other account only where the audit projection lets the viewer see it (PJ-11).

| Cause | Shown | Offered |
| --- | --- | --- |
| A stale edit — the `lock_version` moved, also when another account edited a different line of the same draft (ST-01; M-38) | the current state of the record beside the user's own input, the fields that differ marked; the input is kept | **"Terapkan Ulang pada Versi Terbaru"**: only the fields and lines the user changed since the version they opened are carried onto the current version; a field or line changed on both sides is marked and needs the user's choice before sending; the form is then submitted as a new intent; or "Buang Isian Saya" |
| The transition was already made — a document already issued, a case already closed, a count already applied (ST-02) | the state reached, and by whom where visible | "Buka Catatan"; no re-apply |
| A stale count (ST-03) | the count is marked *Kedaluwarsa* and can no longer be applied | "Hitung Ulang" opens a new count of the scope; no re-apply |
| The Force Complete snapshot changed (ST-04) | the current blockers and residual obligations, with what changed since the confirmed snapshot | a fresh confirmation of the new snapshot; the reason is kept |
| The disposition list changed (ST-07) | the dependents that exist now | the user disposes of them again |
| A prefilled business date that is no longer today (ST-08) | both dates | IP-07 |
| The product's serial policy or quantity scale changed (ST-09) | the current policy | the lines concerned are entered again |
| A master key already used by another entity — a SKU, barcode, e-mail address, code or unit name (SQ-08; BD-16) | the key field marked "{nilai} sudah dipakai", every input kept, and a link to the holder when it is in scope | the corrected submission is a new intent |
| A business identity already exists — the same supplier delivery reference, shipment reference, confirmation, issue, billing act or settlement (SQ-08; BD-01, BD-04–BD-08) | **"Sudah tercatat — periksa sebelum mengulang"** with a link to the existing record when it is in scope; when it lies outside scope, only what DP-13 or DP-18 prescribes | "Buka Catatan"; **never a re-apply** |
| The intent itself was already recorded — a replay whose content differs, a replay under another account, or a command key found after the log entry expired (CI-05; SQ-07) | "Sudah tercatat — periksa sebelum mengulang", with no state and no identifier (DP-16) | the list where the record would be; **never a re-apply** |

### IP-04 — FAILED, the unknown outcome and retry

| Case | What the answer or its absence tells | Screen |
| --- | --- | --- |
| Not processed | the answer is FAILED and says nothing was committed — a lock wait, a statement timeout, an exhausted retry or restart budget (SQ-12–SQ-16, SQ-20; TX-01–TX-10; RY-06) | MSG-06: the system was busy, the action was not processed and repeating it is safe; **"Coba Lagi"** sends the same `command_id` and payload (RY-07). MSG-06 is used only for a first attempt, or when no earlier attempt of the intent can have reached the server; otherwise the answer is shown as MSG-07 |
| Outcome unknown | the answer is FAILED as *outcome unknown* (SQ-17), or no answer arrived after the request had left | MSG-07: it cannot be confirmed whether the action was saved; "Coba Lagi" either runs it or returns what it had already done (CI-05) — the screen claims neither saved nor unsaved |
| Defect | the answer is FAILED with a defect cause (SQ-11, SQ-18, SQ-19) | MSG-06's defect variant with the reference code of the answer (H7-08) and the advice to report it if it happens again; "Coba Lagi" stays available, because nothing committed |
| A background failure | a rendition, export or import step failed after its action committed | IP-12: the committed action stands (HO-01) |

The retry is manual by default (D-UX-04). The client retries by itself only once, after a network loss, when the connection returns while the page is still open — with the same `command_id`, which is what makes it safe. It never resends after a re-authentication without the user pressing "Coba Lagi" (section 6), and never retries a REJECTED or CONFLICT answer.

### IP-05 — Duplicate warnings

A warning stays a warning: none is a block and none is skipped (CONCURRENCY_IDEMPOTENCY §11; D-UX-06). The first submission that meets one is REJECTED with the rule and the matches the actor may see; the warning block lists each match with a link and asks for the reason.

| Warning | Who may continue | Second submission |
| --- | --- | --- |
| A likely duplicate of a fungible event — dispatch, delivery, restoration, adjustment or loss with the same line or case, quantity and date (L-49; BD-13) | the actor of the command | ticks each listed match, gives the reason and submits again; the confirmation is listed in QS-21 |
| A likely duplicate payment — the same company bank account and amount within the window of BD-02 | an actor holding `warnings.override` (ADM+) | as above, as an override. An actor without the capability sees the matches and MSG-15's path: check the listed payment, and if this one is a different receipt, ask an account with the authority, or the Owner, to record it. The form's input can be kept open meanwhile; nothing is recorded |
| Evidence without an issuer reference that matches on issuer, type, date and amount (L-45; BD-14), and a content or cross-type match the actor may view (FL-10; BD-15; H7-07) | an actor holding `warnings.override` | as the payment override, with MSG-15's wording for a record |

The second submission is a new intent that **names the matches it acknowledges**. If another match appeared meanwhile, it is REJECTED again with the current list, so a confirmation never covers a match the user did not see. A match the actor may not view produces no warning, no different wording, no extra step and no later trace for that actor (DP-13; UXI-08); it reaches the Owner through [the review queue](../INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues).

### IP-06 — A routed refusal

When an effect the actor chose touches another company's record without the grant or the allocation authority (SQ-23; AZ-08; XL-1), the answer is REJECTED and routed. The screen shows MSG-14: the action was not processed, and the Owner or an account holding the grants will see it. It names no company, lot owner or project of the other side — only the relation marker (PJ-04). The form stays as it was, so the actor can choose another lot or reservation where the workflow allows one. Whoever holds the grants finds the refusal in QS-20 as a notice with its age for 14 days and acts through the normal command; the notice has no state and no "done" control.

### IP-07 — Business date

Every command form carries the control **"Tanggal transaksi"** (PT-25; HO-08): one compact line in the form's header area, not a large field (D-UX-05). The numbering commands of section 12 show their date as a fixed fact instead — the day of recording, never choosable (NM-03) — and carry it in the payload as a prefilled date, so a confirmation left open across midnight returns CONFLICT (ST-08) and its period list is shown again.

- It is prefilled with the server's current day (WIB) and then reads "Hari ini, {tanggal}". The payload says that the date was only prefilled (ST-08).
- "Ubah" opens the date picker; no date after today can be chosen (L-09). A chosen date reads "{tanggal} — dipilih"; a chosen date before today adds the visible mark **"Tanggal Mundur"**, and on a numbered document the line "Nomor mengikuti periode {periode}" (NM-05). Owner and Admin have the same authority here (BR-DT-02); no capability hides the control.
- When a prefilled date is no longer today at the first execution, the answer is CONFLICT and the screen shows MSG-16 with both dates: the user picks the right one and submits a new intent. A chosen date is never questioned, and a replay never re-evaluates either (ST-08).
- After the command commits, a backdated record shows both dates wherever it appears in a timeline (PT-18).

### IP-08 — Confirming an irreversible command

The commands WORKFLOWS §1 marks irreversible — issue, billing, payment, dispatch, receiving, settlement, write-off, completion, Force Complete — and the numbering commands of section 12 are confirmed in a dialog (PT-26) that names the object and the consequence, with the action's own verb on the confirming button and "Kembali" as the other; never "Ya" or "OK". The dialog states what cannot be changed afterwards and how a mistake is corrected ("hanya dapat dikoreksi melalui {koreksi}"). On a phone the same content is a full-height sheet whose confirming button sits at the bottom. Confirmation is one step: the payload's formats are checked before the dialog opens, and the dialog's button sends the command; a server validation error after that returns the user to the form (IP-14) with the identifier kept while the payload is unchanged (IP-02). The dialog opens with focus on its first input or, when it has none, on "Kembali" — never on the confirming button ([DESIGN_SYSTEM §13.2](../DESIGN_SYSTEM.md#132-scanner)).

### IP-09 — Corrections, closures and destructive actions

Every ADM+ action, waiver, override, closure, cancellation and correction asks for its **reason** in the confirming step (WORKFLOWS §4; BR-CR-01) — a required text field that says what the reason will be used for ("tercatat di riwayat dan terlihat oleh Owner") — and for evidence where the rule requires it. The step lists what the action will change and what will stay ("Catatan asli tetap terlihat dan terhubung dengan koreksi ini"). Destructive styling is reserved for actions that end or reverse something (PT-11). An account without the capability does not get the control (H7-03); the server decides either way (AZ-01).

### IP-10 — Step-up confirmation

Before a command of AU-15 — account and grant changes, a company's bank accounts or identity assets, an import commit, a credential-link issue — the screen asks for the account's password when it was not confirmed in the last 15 minutes: the server answers such a command with a non-redirect status that the client intercepts as it intercepts a 401 (IP-01; UXH-11), and the dialog "Konfirmasi Kata Sandi" opens with one password field, paste and show-password allowed (H7-04), and MSG-27 — above any dialog or sheet already open (DESIGN_SYSTEM PT-16). The form behind it keeps its input in memory (UXH-02); after the confirmation the same `command_id` and payload are sent again. A command that also needs the confirmation of IP-08 shows that confirmation first; the step-up follows when the command is sent. A failed confirmation says only that the password is wrong; repeated failures meet the limiter's message (MSG-20). The confirmation is an authentication request, not a command (CI-09): it carries no `command_id`, and the command it guards is sent only after it succeeds.

### IP-19 — Several commands on one screen

Where a screen leads through several commands — a payment and the applications made later, the lines of a purchase receipt, a confirmation and the reservations it proposes, the rows of a correction — each command has its own button or step, its own `command_id` and its own answer, listed one below the other with its outcome. The screen never shows one combined "Tersimpan" for them, and a failure of a later command leaves the earlier ones committed and shown as such (PX-05). Only what WORKFLOWS §9 makes one indivisible action is one command — a receipt and the reservation it offers for the line's project are one (AX-04; D-UX-12), and so are a payment and the applications entered in its own form (AX-18), a restoration and the reservation it offers (CM-05). This rule is D-UX-07.

### IP-21 — Denial and not-found

| State | When | Shown |
| --- | --- | --- |
| Not found | the address does not resolve, or resolves outside the account's scope — the two are indistinguishable (AZ-11; CS-04) | the page frame with MSG-09, the reference code and the way back; the same for both cases in wording, layout, status and timing class |
| Forbidden | the record is in scope and the capability is missing | MSG-10 with the reference code; nothing of the record |
| Error | an unexpected failure of a page | a generic message with the reference code — the correlation id (H7-08; WS-10) — and no technical detail |

A control the account's capabilities do not allow is hidden; one that is temporarily unavailable — a draft that cannot be issued yet, a project that cannot be completed — is shown disabled with the reason beside it (H7-03). Neither is a security boundary.

