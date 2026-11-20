# Device UAT preparation

Status: REVIEW | Updated: 2026-11-20 | Owner: Planning

*Written by the executor of [TASK-070](../11-tasks/TASK-070.md) (criteria 6 and 7; TEST_STRATEGY §12; RELEASE_PLAN §8). It prepares the device UAT the Owner and the Admin Operasional validators run on staging with synthetic data (TASK-086): the device and browser matrix, the real-device scripts, the UAT items with their scripts and the record form. The task scripts themselves are [USABILITY_KIT](../07-ux-design/stage2/USABILITY_KIT.md)'s UT-01–UT-24, reused rather than copied. Nothing here is a result: a UAT result exists only in a record a validator signed.*

## 1. Devices and browsers

| Device | Browser | Who holds it | Checked |
| --- | --- | --- | --- |
| The office PCs | the current stable Google Chrome and Microsoft Edge | the Owner and the Admin Operasional | every desktop task; the document composer at TEST_STRATEGY §9's widths (DIR-079 CD-04); USB scanners; printing |
| An Android phone | Chrome | the validator, the warehouse or field user | the phone tasks at 390 CSS px and S2-J05 at 320 CSS px |
| An iPhone, if a user holds one | Safari | as available | the same phone tasks |

The list is DIR-083 OE-02's and is corrected with what the users actually hold, the correction recorded in the UAT record. Each phone record names the device model, the browser and its version, and the CSS width the page reports.

## 2. Real-device scripts

| Script | What the validator does | The record holds |
| --- | --- | --- |
| R-01 — S2-J01 sign-in | sign in with the password and the authenticator code on the phone at 390 CSS px; sign out | device, browser, width; the steps done; any step that needed help |
| R-02 — S2-J03 work home and project | Pekerjaan, then a project and its lines, at 390 CSS px | the same; whether every control was reachable without zooming |
| R-03 — S2-J05 receiving | receive a purchase line with a scan at 390 and at 320 CSS px | the same at both widths; the scan field's focus after each scan |
| R-04 — UT-06, UT-12, UT-18–UT-24 | USABILITY_KIT's task cards for each, on the phone at 390 CSS px | each task's completion, time and errors as the kit's form records them (DIR-079 CD-03) |
| R-05 — the desktop composer | prepare and issue one document of each family the validator uses, the form beside the live preview | the widths used; whether the preview matched the issued PDF |
| R-06 — scanners | a USB wedge scanner on a desktop and on the phone where paired: scan speed, the Enter suffix, the scan field keeping focus, Bluetooth pairing; the phone camera through the file picker | each scanner model and its behaviour; camera barcode scanning is outside V1 (D-UX-09) |
| R-07 — printing | print a label and a document by the office's existing method; no new printer assumed (DEP-03) | the printer and the print's legibility |
| R-08 — the users' files | upload the formats the users really hold — photos, PDFs, spreadsheets — against FL-02's allowlist (DEP-04; DEP-05) | each format accepted or refused, and the message shown |
| R-09 — time on the device | note page and command times on the phone and the PC beside the server budgets (PFM-17); office connectivity as observed (DEP-09) | the observed times and the connection |

## 3. The UAT items

| Item | Script | Validator | Pass when |
| --- | --- | --- | --- |
| UXH-08 — copy and the duplicate window's presentation | read ADMIN_FLOW's message catalogue and the glossary in the pages they appear on; record a payment that matches an earlier one inside the window and read the warning | the Owner and the Admin Operasional | every message is clear Indonesian; the warning says which payment matches and what to do |
| HO-35 — the seven-day duplicate window | compare the window with the Owner's finance practice on two payments seven and eight days apart (BD-02) | the Owner | the Owner confirms or asks to change the window, the answer recorded |
| DOC-01–DOC-14 with FX-10 | issue each document type from FX-10's synthetic samples (`tests/Fixtures/FX-10.php`) and compare the PDF with the company's paper | the Owner | each type's content, number and layout accepted, or each difference recorded (GAP-019) |
| DEP-11 — authenticator phones | each high-risk account holder enrols an authenticator app on the phone they will use and signs in with it | each high-risk account holder | every holder signs in with a code; a phone that cannot is recorded |
| OS-01 — daily work | UAT-03's representative day: a project from quotation to payment, data entered once and reused through the flow | the Admin Operasional | every output correct without re-entry |
| The per-page copy lists | read TASK-067's per-page lists of new copy (the localization run's `copy-lists` evidence) against the pages | the Owner | each list accepted, or each wording to change recorded |
| UAT-01 — numbering and go-live | PG-061: the seeds from the import, the Owner's starts for the types no import covers, go-live recorded once by the operator; ADMIN_FLOW §12's numbering journeys | the Owner and the operator | each series starts where the Owner expects |
| UAT-02 — the opening import | preparation, the dry-run report, the reconciliation to the signed totals and the sign-off, rehearsed on staging (RELEASE_PLAN §5) | the validators | the control totals equal the signed legacy totals; both sign |
| UAT-03 — daily work and trust | OS-01's day, then repeated and concurrent work by two validators; stock and money checked at opening and after (OS-02) | the validators | stock and balances agree with what was done |
| UAT-04 — separate records | each validator records on their own form | the Owner and the Admin Operasional | two records exist, each signed by its validator |

## 4. The record form

One form per validator and session, kept with the release record:

| Field | Content |
| --- | --- |
| Validator | name and role |
| Date | the session's date and time (WIB) |
| Build | the release candidate's commit and the staging stack's start |
| Devices | each device, browser, version and CSS width used |
| Tasks | each script or item run, by its number above |
| Result | for each: PASS, FAIL or NOT RUN with its reason |
| Disposition | for each FAIL: fixed, accepted by the Owner, or deferred by the Owner with its reason |
| Issue log | every problem seen, in the validator's words, with the page and the time |
| Signature | the validator's |

Nothing is recorded that did not happen. A validator who is not available is a blocker reported to the Owner, never a skipped item (GAP-014). The validators' review time is scheduled by RELEASE_PLAN with UAT-01 and UAT-02 (UXH-10).
