# Document preview producer

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.7 (GAP-053; BP-5). Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** [GAP-053](../../00-governance/GAP_REGISTER.md#gap-053--a-draft-documents-preview-and-print-have-no-defined-producer); WORKFLOWS [WF-DOC-01](../../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) and [§6](../../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-ISSUE and SF-RENDER; BUSINESS_RULES [BR-DOC-01–BR-DOC-04](../../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization); CONCURRENCY_IDEMPOTENCY [NM-03](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency), [JB-01 and JB-02](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue); ADMIN_FLOW [UXI-14](../ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements), [IP-13](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry) and [J-DOC-01](../ADMIN_FLOW/s10-b-journeys.md#105-documents-and-administration); [DIR-057](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors) VD-07 and DIR-058 DC-1–DC-12, re-verified in the gate record's [document composer](../evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--document-composer); GD-07 ([Checkpoint C1 closure](../evidence/P7_REBASELINE_GATE.md#checkpoint-c1--closure-on-the-golden-frontend-reference-dir-059)); DESIGN_SYSTEM [§14.3](../DESIGN_SYSTEM.md#143-document-preview) and [§17](../DESIGN_SYSTEM.md#17-print-views); PERMISSIONS_MATRIX [PJ-15 and PJ-19](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections); SECURITY [§5](../../05-security/SECURITY.md#5-file-security) FL-07 and FL-08 and [§4](../../05-security/SECURITY.md#4-web-and-input-security) WS-07 and WS-08; PERFORMANCE [AS-01 and AS-06](../../06-api-performance/PERFORMANCE.md#1-assumptions-and-workload), [D-PF-05](../../06-api-performance/PERFORMANCE.md#2-design-decisions), [QB-16 and QB-17](../../06-api-performance/PERFORMANCE.md#6-query-budgets) and [SZ-02](../../06-api-performance/PERFORMANCE.md#13-resource-inputs-for-p9); [ARCHITECTURE §11](../../04-architecture/ARCHITECTURE.md#11-queue-and-background-jobs); [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) PG-036 and PG-042. The PDF renderer is infrastructure behind a small interface, its technology not yet chosen ([ARCHITECTURE §2](../../04-architecture/ARCHITECTURE.md#2-layers); [DEP-07](../../01-product/V1_SCOPE.md#dependencies-and-evidence-needed)); no statement here depends on it, and what it must support is marked "needs verification at implementation".

## 1. The problem and its constraints

**The problem, as [GAP-053](../../00-governance/GAP_REGISTER.md#gap-053--a-draft-documents-preview-and-print-have-no-defined-producer) states it.** WF-DOC-01 lets a preparation draft print with draft marking, never satisfying a requirement; J-DOC-01 prints the draft with the draft marking; DESIGN_SYSTEM §14.3 has a draft "print and preview"; the composer of VD-07 shows a live preview of the draft beside its inputs. The only defined producer of a document's appearance is SF-RENDER — the background rendering of an issued version by JB-01, dispatched by the issue (AX-15) —, and a draft has no issued version, snapshot or rendition. Without a defined producer, the draft preview, its print and the issued rendition can differ in layout or totals, and a later stage could invent a server rendering of drafts that conflicts with BR-DOC-01–BR-DOC-03.

**The constraints**, each a rule the producer keeps:

| Source | What it fixes for a draft's preview and print |
| --- | --- |
| [WF-DOC-01](../../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) | A DRAFT is editable and unnumbered; a preparation draft prints with draft marking and never satisfies a requirement; issue goes through SF-ISSUE, rendering through SF-RENDER |
| [BR-DOC-01](../../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization) | An issued version is an immutable snapshot of content, company identity assets and number — a draft is none of these |
| BR-DOC-02 | Numbers are unique per company, type and period and drawn at issue from the business date's period, never `MAX(number)+1` — so a preview shows no number, not even a likely next one |
| BR-DOC-03 | A render artifact is not an issue: rendering is derived, retryable and reproduces the issued snapshot; layout selection has zero business effect |
| BR-DOC-04 | One business record, many layouts; a company draft never satisfies a required client-issued original |
| SF-ISSUE ([§6](../../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows)) | Issue validates the inputs, the issuer and the record-bound precondition, takes the next available number and writes the immutable snapshot (AX-15; AX-16 for the invoice) |
| SF-RENDER; [JB-01, JB-02](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) | The background rendering of an issued version from the issued payload alone; PENDING and FAILED visible (QS-08); a retry reproduces the snapshot and never re-issues or renumbers |
| [NM-03](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency) | The allocator fails closed; numbers come only from an issue |
| [UXI-14](../ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements); [IP-13](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry) | Money and quantities are decimal strings; authoritative totals are server values; a client preview is labelled provisional — "Perkiraan" |
| VD-07 ([DIR-057](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)) | Business data → prefilled draft → the allowed inputs → document preview → validation → explicit finalization; on the desktop a composer with a live preview of about 40–45 % of the workspace, with page, zoom and full-preview controls, showing changes to the allowed inputs; the PDF never the editable source; a draft preview never satisfying issuance; on the phone no forced split — Edit → Preview → Finalize or a full-screen preview |
| DC-1 ([composer](../evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--document-composer)) | The draft editable and unnumbered, printing with its marking, never satisfying a requirement, issued explicitly with an immutable snapshot |
| DC-2; GD-07 | The marking "DRAF — belum terbit" in a band and as a pale watermark marked decorative; the number line "Nomor diberikan saat terbit"; DESIGN_SYSTEM §14.3's "DRAF — bukan dokumen terbit" aligned in Stage 3 |
| DC-3 | "Ringkasan (perkiraan)" and the bar's "Total invoice (perkiraan)"; server totals authoritative |
| DC-4 | No stamp or signature image in the draft preview — the signature block is text, the director's name and title; the identity assets PJ-19 lets the viewer see appear |
| DC-5 | Carried data read-only with its source and one link to correct it at the source |
| DC-6 | Only the defined inputs; free notes or clauses, payment-terms wording, the choice of signatory and bank details on the invoice need definition under GAP-019 and are not shown |
| DC-7, DC-8 | The composer issues only, confirmed with MSG-39 for the invoice; afterwards the number and the rendition's state |
| DC-9, DC-10 | Client originals stay uploads; the names of L-25 and DIR-024 kept |
| DC-11 | Documents stay desk journeys; on the phone Edit → Preview → Finalize, full-screen |
| DC-12 | The producer is a missing definition, not a changed one |
| [DESIGN_SYSTEM §14.3](../DESIGN_SYSTEM.md#143-document-preview) | A system rendition shown inline in a frame with "Unduh" and "Cetak"; a draft prints and previews with its marking |
| [DESIGN_SYSTEM §17](../DESIGN_SYSTEM.md#17-print-views) | A print view is the page prepared for paper (DP-19); generated documents print from their rendition, never from a screen |
| [PJ-15](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections) | Payload and rendition by family — finance `finance.view`, cost `cost.view`, operational `projects.view` —, within the document's company; identity assets embedded by the renderer are part of the rendered document |
| PJ-19 | The identity assets — NPWP, addresses, contacts, director and logo — with `projects.view`, `finance.view` or `cost.view`; bank accounts with `finance.view`; raw stamp and signature files only to the Owner, the renderer using them in system context |
| [FL-07](../../05-security/SECURITY.md#5-file-security) | Downloads stream through a controller that authorizes the owning record at request time; renditions alone are displayed inline, under their header profile |
| FL-08 | The renderer renders only the immutable issued payload and allowlisted local assets — identity assets by file id, bundled fonts —, with no network access and JavaScript disabled, as a job in system context |
| [WS-07](../../05-security/SECURITY.md#4-web-and-input-security); WS-08 | Page props come from resources that list each field; a prop needing a capability is not defined for an actor without it; the content security policy admits scripts, styles and fonts from the application's own origin only |
| [D-PF-05](../../06-api-performance/PERFORMANCE.md#2-design-decisions) | Heavy work — rendering included — is a job; doing it in the request is the rejected alternative |
| [QB-16, QB-17](../../06-api-performance/PERFORMANCE.md#6-query-budgets); AS-01; SZ-02 | A file request within two queries and 100 ms before its stream; a rendition from issue to READY within 30 s at 1x; one VPS of four vCPU shared by PostgreSQL, the application, the queue and the renderer, with two queue workers |

The draft's paper is a document: it stays Indonesian whatever the interface language ([LOCALIZATION_ARCHITECTURE §2.5](LOCALIZATION_ARCHITECTURE.md#25-what-never-translates); [CAP-14](../../01-product/V1_SCOPE.md#must-ship)).

## 2. The options

[GAP-053](../../00-governance/GAP_REGISTER.md#triage) names two producers — a client-side composition of the same template from the draft's data, and a non-persistent server preview that never writes a rendition or a number. A third combines them; a fourth, no preview at all, is listed so that the comparison is complete. Each is weighed against the same criteria.

### 2.1 Option A — a client-side composition of the same template

The composer page receives the draft's data as page props — only the fields the viewer may see ([WS-07](../../05-security/SECURITY.md#4-web-and-input-security); [PJ-15](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections); PJ-19) — and the browser composes the paper from the document layout's template, the one the renderer uses for the issued rendition, updating it as the allowed inputs change.

- **Fidelity.** The same template and the same bundled fonts give the same layout. While the user types, the totals are provisional, computed on the entered strings ([IP-13](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry)) and labelled "perkiraan" ([DC-3](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors); UXI-14); after "Simpan Draf" the preview shows the server's totals, which are authoritative. The browser and the renderer may still break pages or set type slightly differently: P8 measures it (§4).
- **Marking.** The band and the watermark "DRAF — belum terbit", the number line "Nomor diberikan saat terbit" (DC-2; GD-07) and the text signature block (DC-4) are part of the template's draft mode, on the screen and in its print style.
- **Print.** The browser prints the composed paper with its marking, as a print view of the page ([DESIGN_SYSTEM](../DESIGN_SYSTEM.md#design-system) §17); nothing is written.
- **The phone.** Edit → Preview → Finalize, the preview full-screen (DC-11; VD-07), composed on the device.
- **Security and projection.** Only projected fields reach the browser; no stamp or signature file is sent (PJ-19; DC-4); the logo, where PJ-19 lets the viewer see it, comes through FL-07's authorized file route — how the page shows it is P6's and P11's, needs verification at implementation. No rendition, file, number or snapshot is written, and the printed draft is visibly a draft: marked, with no number, stamp or signature.
- **Performance.** No server rendering and no job: the server's cost is the page's own load and the draft's save command; the composition's time on the device is P8's device evidence ([AS-06](../../06-api-performance/PERFORMANCE.md#1-assumptions-and-workload)).
- **What P6 or P11 add.** P6: nothing — no route class, budget or job. P11: the template shared by the browser and the renderer, the fonts served from the application's origin, the draft mode, the print style and the data contract (§3).

### 2.2 Option B — a non-persistent server preview

On request — "Pratinjau PDF" —, the server renders the saved draft through the renderer and streams a marked PDF, writing no rendition, number or file.

- **Fidelity.** The highest: the renderer itself, the server's totals; only the marking, the number line and the signature block differ.
- **Marking.** Added by the renderer in a draft mode.
- **Print.** The PDF prints as a rendition would; of the four options it is the draft printout that looks most like an issued document.
- **The phone.** A PDF in the device's viewer, full-screen.
- **Security and projection.** FL-08 limits the renderer to the immutable issued payload in system context; a draft is neither, so FL-08 would change (P5). The renderer holds the stamp and signature in system context, and its draft mode must leave them out (DC-4; PJ-19). The response would need its own header profile under FL-07 and a throttle, a render being heavy work.
- **Performance.** D-PF-05 makes rendering a job and rejects doing it in the request. A render per preview would compete with PostgreSQL and the queue on four shared cores (AS-01; SZ-02), or become a new job with a status request. The preview follows the inputs only on request, short of VD-07's live preview.
- **What P6 or P11 add.** P5: FL-08 amended. P6: a route class with its budget and throttle, or a new job with its status route. P9: the capacity.

### 2.3 Option C — both

Option A for the live preview, option B for a printable draft. It carries B's changes to P5 and P6 for a print that A already gives with the same template, and two producers of one draft can disagree.

### 2.4 Option D — no draft preview

The paper appears only after issue, as the rendition; the draft prints as a form. Every rule stays as it is, but VD-07's live preview — the Admin sees the document before it is finalized — is lost, and [WF-DOC-01](../../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk)'s draft print shows no document.

### 2.5 Comparison

| Criterion | A — client composition | B — server preview | C — both | D — none |
| --- | --- | --- | --- | --- |
| Fidelity to the issued rendition | the same template and fonts; page breaks measured by P8 | exact | live as A, printed as B | none before issue |
| The marking (DC-2; GD-07) | on the screen and on paper | in the PDF | both | — |
| Print | the composed paper | the PDF | the PDF | a form |
| The phone (DC-11) | a full-screen preview step | a PDF viewer | both | — |
| Security and projection | projected fields only; no stamp or signature file; nothing written | FL-08 changed; stamp and signature excluded in system context; a throttle | as B | unchanged |
| Performance | no server render | a render per preview, against D-PF-05 | as B, fewer renders | none |
| Live preview (VD-07) | yes | no | yes | no |
| P0–P6 change | none | P5 and P6 | P5 and P6 | none; VD-07 unmet |

## 3. The recommendation

**Option A, the client-side composition of the same template.** It gives [VD-07](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)'s live preview and [WF-DOC-01](../../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk)'s marked draft print while every rule stays as it is: no rendition, number, snapshot, job or route class; [FL-08](../../05-security/SECURITY.md#5-file-security)'s renderer limited to issued payloads; [D-PF-05](../../06-api-performance/PERFORMANCE.md#2-design-decisions)'s job rule kept; stamp and signature files kept out of drafts (DC-4; [PJ-19](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections)). Its weakness — the browser and the renderer may break pages or set type slightly differently — is bounded by one template and measured by P8 (§4). The issued rendition stays SF-RENDER's, and an issued document is printed from its rendition, never from a screen ([DESIGN_SYSTEM §17](../DESIGN_SYSTEM.md#17-print-views)).

Option A needs no P0–P6 change — no new route class, budget or job —, so it raises no Owner question and no gap. [GAP-053](../../00-governance/GAP_REGISTER.md#triage) stays OPEN: its continuation note records this recommendation, and Stage 3's normative definition, the Architect's part in P11 and P8's tests close it.

**The Architect's part, for P11:**

1. **One template per document layout**, read both by the composer in the browser and by the renderer of FL-08, so that a layout changes in one place. The renderer's technology is not yet chosen ([ARCHITECTURE §2](../../04-architecture/ARCHITECTURE.md#2-layers)); P11 chooses it under [DEP-07](../../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) among the local, licence-free capabilities V1_SCOPE prefers, such that it renders that template with JavaScript disabled, as FL-08 requires — needs verification at implementation.
2. **The fonts** FL-08 bundles, served to the browser from the application's own origin (WS-08).
3. **The template's draft mode**: the band and the watermark "DRAF — belum terbit", the number line "Nomor diberikan saat terbit", the text signature block — the director's name and title —, and the totals labelled "perkiraan" until the server's are shown (DC-2–DC-4; GD-07).
4. **The print style** of the composed paper: A4 with the renderer's margins, the marking on every page.
5. **The data contract**: the draft's fields as page props under WS-07, by family (PJ-15) and with PJ-19's identity assets — the fields the issued snapshot will hold, without the number, the stamp and the signature.
6. **The totals**: provisional on the entered strings while typing ([IP-13](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry)), the server's after each save (UXI-14), the issued rendition reproducing the server's ([BR-DOC-03](../../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization)).

## 4. What P8 must test

1. **A draft preview never satisfies a requirement.** While its document is a draft, a document requirement stays unsatisfied, whatever was previewed or printed ([WF-DOC-01](../../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk); [DC-1](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)); a company draft never satisfies a client-issued original ([BR-DOC-04](../../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization)).
2. **A draft preview never writes.** Composing, zooming and printing a draft create no rendition row, no file, no number and no counter, and change no version (BR-DOC-02; [NM-03](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency); SF-RENDER): the database is unchanged apart from the draft's own saves.
3. **The preview matches the issued layout.** For each document layout, a draft composed from fixed data and then issued gives a rendition whose blocks, lines, totals and page breaks equal the preview's; only the marking, the number line and the signature block differ, within a tolerance P8 sets for type and page breaks.
4. **Totals.** The preview's provisional totals are labelled; after a save they equal the server's, and the issued rendition shows the server's ([UXI-14](../ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements); DC-3).
5. **Projection.** The composer receives only the fields its family and [PJ-19](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections) allow; no stamp or signature file reaches the browser in a draft (PJ-15; PJ-19; DC-4).
6. **Language.** The composed paper is Indonesian whatever the interface language; the composer's controls follow it ([LOCALIZATION_ARCHITECTURE §2.5](LOCALIZATION_ARCHITECTURE.md#25-what-never-translates)).
7. **The phone.** Edit → Preview → Finalize at 390 px, the preview full-screen (DC-11).

## 5. S2-J03's composer, as a simulation

S2-J03's invoice composer, PG-042 *Buat Invoice* ([IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md)), follows option A as a simulation: the page composes the paper from the synthetic draft ([SP-20](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents)), with "DRAF — belum terbit", "Nomor diberikan saat terbit", the text signature block and the totals "perkiraan". "Simpan Draf" and "Terbitkan Invoice" are simulated: no server, no number allocator; the number shown after the simulated issue carries the "Simulasi" chip, and the rendition's states — "PDF sedang dibuat", then "PDF siap" — are scripted, with no PDF produced ([SS-08](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)). [SIMULATION_REGISTER](SIMULATION_REGISTER.md) lists each simulated step, and [S2-J03](journeys/S2-J03.md) specifies the screens; whether the vendored `composer.js` draws the paper — it loads only if the answer to [OQ-01](OWNER_QUESTIONS.md#oq-01--the-runtime-for-the-vendored-scripts) provides its runtime API (SP-14; SP-15) — is settled in [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md).

## 6. What stays open

- **Needs definition — not proposed:** the content of each layout beyond its defined inputs — free notes or clauses, payment-terms wording, the choice of signatory, bank details on the invoice — stays with [GAP-019](../../00-governance/GAP_REGISTER.md#triage) ([DC-6](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)).
- **For later stages:** the normative producer (Stage 3); the template, the renderer's technology and the data contract (P11, [DEP-07](../../01-product/V1_SCOPE.md#dependencies-and-evidence-needed)); the tests of §4 and their tolerance (P8).
