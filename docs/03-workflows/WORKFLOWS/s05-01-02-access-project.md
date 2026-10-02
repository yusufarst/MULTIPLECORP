## 5. Workflows

Each workflow uses one template: purpose; actors/trigger; preconditions; main path; branches; partial; exceptions; correction/cancellation (CM rows); records affected; invariants; result/completion; audit/authority/concurrency; traceability and later-phase obligations. `N/A — reason` replaces silence.

### 5.1 Access and master data

#### WF-ACC-01 — Session and company context `[both]`

- **Purpose:** authenticated, individually attributable entry and the actor's company scope. **Actors/trigger:** OWN, ADM; login.
- **Preconditions:** active individual account; not throttled.
- **Main path:** authenticate → load grants and capabilities → dashboard: Owner consolidated and per company; Admin action queue across granted companies (section 10) → work → logout ends the session.
- **Branches:** password reset through the approved channel (DEP-08); onboarding: the Owner creates the individual account and the initial credential travels through the approved channel; Owner-account recovery is a human operator procedure (P5/P9).
- **Company context:** scope = granted companies (Owner: all). A working filter may narrow views; every command targets exactly one record company and is re-checked by SF-CMD — a selected "current company" never substitutes for authorization.
- **Exceptions:** session expired mid-form → re-authenticate, then the form is submitted as a new intent through SF-CMD (stale checks apply); grant/capability revoked or user deactivated → the next command is REJECTED; that user's drafts stay available to other authorized users.
- **Records:** security/technical log (distinct from business audit, BR-XC-03). **Invariants:** BR-ACC-01, BR-XC-02/03.
- **Traceability:** CAP-01/13; AC-13; REF-001/004; IMG-01. Later: P5 authentication/session/throttling controls; P7 dashboards and queues; P8 denial and revoked-access tests.

#### WF-ACC-02 — Users, roles, capabilities and company grants `[desk]` — OWN!

- **Main path:** create individual user → role (Owner / Admin Operasional) → company grants → capabilities, including ADM+ authorities → activate; later change grants/capabilities; deactivate.
- **Exceptions:** deactivation ends sessions and keeps historical attribution; accounts are never shared; the last active Owner account cannot be deactivated or demoted (L-34, PLANNER-DETERMINED).
- **Audit:** permissions, grants and user access are critical audit areas (BR-XC-01). **Traceability:** CAP-01/13; AC-13; REF-002–004. Later: P5 capability catalogue and default grants.

#### WF-MD-01 — Company master `[desk]` — OWN!

- **Main path:** add legal company (name, NPWP/tax identity, addresses, contacts) → identity assets (logo, stamp/signature, director) → bank accounts → document identity and numbering rules → tax configuration (DIR-024 D-1: tax registration status and treatment settings, effective-dated, configured values only, no invented rate or formula) → activate.
- **Changes:** identity changes never alter issued snapshots (BR-SN-01); tax configuration applies by the business_date of the transaction it governs (D-1 item 3).
- **Deactivation:** blocks new projects, invoices and purchases; payments, applications, settlements, returns and corrections on existing records continue; history and snapshots stay intact (BR-ACC-03).
- **Traceability:** CAP-01; AC-01; REF-005/006. Later: P4 effective-dated configuration representation; P5 S3 protection of banks/identity assets.

#### WF-MD-02 — Client organization → unit → PIC `[both]`

- **Main path:** search the existing organization (no duplicate organizations) → select or create the unit with its unit address → select or create the PIC.
- **Archive with live history:** not selectable for new projects; open projects continue; issued outputs keep the address as used (BR-SN-02). No institution-specific module exists; UNY/UGM are ordinary rows.
- **Traceability:** CAP-02; AC-02; REF-009/010.

#### WF-MD-03 — Supplier master `[desk]`

- **Main path:** create or select a supplier directly; price history is derived per company from that company's purchases (S3; BR-ACC-02). No comparison, scoring or mandatory competing quotes.
- **Archive with live history:** blocks new purchases; open purchases, receipts and returns continue.
- **Traceability:** CAP-02; AC-02; REF-011; SH-01.

#### WF-MD-04 — Catalog, units, barcodes, serial policy and price defaults `[desk]`

- **Main path:** create product or service (the goods/service flag decides stock applicability) → SKU → base unit = the smallest unit in which the product is counted or sold, with alternate units as exact multiples (L-31) → barcode: manufacturer code if present, otherwise generate an internal code → serial policy → purchase/selling/SPJ-reference price defaults with history → images → activate.
- **Branches:** an unknown barcode scanned during work is offered to an authorized user for registration, never auto-created or auto-selected; a barcode already resolving to another active product is rejected (BR-INV-09); internal barcodes can be printed as labels (method validated under DEP-03).
- **Changes:** ratio and price changes never rewrite committed snapshots (BR-INV-03; BR-SN-01); switching a product to serialized requires zero on-hand stock or a registration count that assigns a serial to every on-hand unit; switching it off keeps serial history (L-35, PLANNER-DETERMINED).
- **Archive with live history:** not selectable for new demand or purchases; confirmed demand stays fulfillable; stock stays countable.
- **Traceability:** CAP-03; AC-03/16; REF-012–015; GAP-020/025.

#### WF-MD-05 — Locations and minimum stock `[desk]`

- **Main path:** maintain rack/location lookups; receiving and counts record locations; moving units between locations is a location-change movement with zero net pool quantity (L-36, PLANNER-DETERMINED); per-product minimum stock feeds QS-17 restock advice, which never creates a purchase (BR-INV-10).
- **Traceability:** CAP-06/17; AC-22/23; REF-023/028. No WMS.

### 5.2 Project and commercial workflows

#### WF-PRJ-01 — Project setup and amendment `[both]`

- **Purpose:** create the operational hub and capture demand. **Actors/trigger:** OWN, ADM (grant on the chosen company); client request.
- **Preconditions:** company active and granted; client/unit/PIC selectable.
- **Main path:** create project (DRAFT; project number assigned once, never reused) → primary company → client → unit → PIC (WF-MD-02) → channel REGULAR or SIPLAH; SIPLAH metadata (external reference, program/account codes, SIPLAH value, real purchase value, fee, VA fee, cashback/refund amounts, tax, notes) are manual informational values (BR-FIN-10; CAP-12) → demand lines: product/service, quantity, unit, selling price, tax components under the applicable configured treatment (D-1), proposed fulfillment mode → DRAFT → ACTIVE once company, client/unit/PIC and channel are set (SM:Project).
- **Branches:** SIPLAH projects follow the same flow and never depend on SIPLAH availability (AC-15); regular projects never require SIPLAH fields; a rare cross-company need uses a linked project in the other company (explicit reference and reason), each project staying single-company (OB §4).
- **Amendment:** before confirmation lines are freely editable; after confirmation commercial changes need a new quotation revision and approval, or a re-confirmation with client evidence for no-quotation projects (L-20); channel changes only before the first channel-dependent committed record (BR-PRJ-05); company changes only while DRAFT with no committed downstream record, otherwise cancel and re-create or use a linked project (L-21).
- **Exceptions:** a DRAFT with no committed downstream record may be discarded with audit, its number retired (L-37, PLANNER-DETERMINED); concurrent edits → CONFLICT for the stale editor.
- **Records:** Project, project items, channel metadata snapshots. **Invariants:** BR-PRJ-05, BR-SN-01/02, BR-XC-01. **Result:** ACTIVE project with demand lines.
- **Traceability:** CAP-04/12; AC-04/15/20; REF-017/038; IMG-01. Later: P4 project numbering; P7 project hub.

#### WF-QUO-01 — Quotation cycle `[desk]`

- **Main path:** draft from project lines (editable) → issue DOC-01 through SF-ISSUE (number, immutable snapshot, validity date; the revision becomes SENT) → an authorized user records the client's response: APPROVED (client acceptance, evidence attached where available) or REJECTED. A SENT revision past its validity date presents as EXPIRED and cannot be approved (L-25).
- **Branches:** any non-draft revision can be revised into a new DRAFT; the chain is preserved; only the latest SENT revision can be approved; revision after approval needs re-approval and reservation adjustment (BR-QUO-02); rejected → revise or cancel the project; partial acceptance → revise to the accepted subset, then approve (L-20).
- **Exceptions:** duplicate or concurrent approval → one decision (AX-13); approval recorded in error → CM-27.
- **Invariants:** BR-QUO-01–03 (a quotation never reserves or moves money); BR-CR-06 (quotations are never voided). **Result:** an APPROVED revision triggers WF-PRJ-02.
- **Traceability:** CAP-04/08; AC-04/08; DOC-01; REF-018; IMG-02.

#### WF-PRJ-02 — Commercial confirmation `[desk]`

- **Main path:** (A) approval of the latest SENT revision is the confirmation; or (B) no-quotation path: an explicit confirmation decision with the client's order evidence (client SPK, Surat Pesanan or SIPLAH order uploaded via SF-EVIDENCE) (BR-PRJ-06). SYS pins each demand line's conversion, price and tax snapshots (BR-INV-03; BR-SN-01) in the same action (AX-13), then proposes reservations for WAREHOUSE lines (L-02).
- **Re-confirmation:** approving a later revision re-pins changed lines; a reduction below dispatched, delivered or invoiced quantity needs a return or invoice revision first; affected reservations are adjusted in the same action (BR-QUO-02).
- **Optional output:** a company-issued SPK (DOC-09) for the confirmed work, never impersonating a client-issued SPK.
- **Exceptions/correction:** CM-27. **Traceability:** CAP-04; AC-04/16/22; DOC-09; OWN-04.

#### WF-FUL-01 — Fulfillment planning per demand line `[desk]`

- **Main path:** each confirmed line gets a mode — WAREHOUSE, DROP-SHIP or SERVICE (BR-XD-03). WAREHOUSE: read AVAILABLE (S1 quantity view) → reserve up to AVAILABLE via SF-RESERVE → any remainder is a shortage (QS-02) → WF-PUR-01. DROP-SHIP: project purchase flagged direct-to-client → WF-FUL-03. SERVICE: WF-FUL-04, with an optional service purchase for subcontracted work.
- **Re-plan:** only the unstarted remainder of a line can change mode: it is split into a new line with the same commercial snapshot and the new mode, audited, with no re-approval (L-21).
- **Per-line conservation (L-04):** WAREHOUSE: reserved remaining + net dispatched + cancelled ≤ pinned demand; DROP-SHIP: net confirmed + cancelled ≤ pinned demand; SERVICE: handed over + cancelled ≤ pinned demand.
- **Traceability:** CAP-06; AC-20/22; IMG-03/04; GAP-021/023.

