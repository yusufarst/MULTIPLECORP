# IA and page inventory

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.1. Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** The structure: the gate record's R1 sections — the [persona task analysis](../evidence/P7_REBASELINE_GATE.md#persona-task-analysis) with its [extension](../evidence/P7_REBASELINE_GATE.md#persona-extension), the [project journey model](../evidence/P7_REBASELINE_GATE.md#c1-rework--project-journey-model), [connected work and document readiness](../evidence/P7_REBASELINE_GATE.md#c1-rework--connected-work-and-document-readiness), the [revised direction](../evidence/P7_REBASELINE_GATE.md#c1-rework--revised-direction) a)–l), the [revised non-loss map](../evidence/P7_REBASELINE_GATE.md#c1-rework--revised-non-loss-map) and the [document composer](../evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--document-composer) —, as GD-01 and GD-03 approved it for Stage 2, with C1A-1–C1A-3 and OC-01–OC-12 as DIR-057 amended them. The behaviour: INFORMATION_ARCHITECTURE [§§5–11](../INFORMATION_ARCHITECTURE.md#5-product-map) where they state or apply P0–P6 — the company context, page addressing, query budgets and field visibility —, its decisions D-IA-01–D-IA-17 read with the gate record's [provisional dispositions](../evidence/P7_REBASELINE_GATE.md#d-ia--navigation-and-information-architecture) as R1's [disposition delta](../evidence/P7_REBASELINE_GATE.md#c1-rework--disposition-delta) revised them; [ADMIN_FLOW §10](../ADMIN_FLOW/s10-a-journeys.md)'s screens and entry points and its interaction patterns IP-01–IP-21; [PERMISSIONS_MATRIX](../../05-security/PERMISSIONS_MATRIX.md) §§3, 4, 6, 7 and 9; [DESIGN_SYSTEM §12.1](../DESIGN_SYSTEM.md#121-status-system)'s state labels and its keyboard rules KB-01–KB-03; [QUERY_BUDGET_CHECKS](QUERY_BUDGET_CHECKS.md), whose verdicts this inventory follows. No technology behaviour is stated: the draft routes follow INFORMATION_ARCHITECTURE §11's conventions and are input for [GAP-035](../../00-governance/GAP_REGISTER.md#triage), not a contract (BP-1).

## 1. Personas and devices

Personas are not roles: V1 has two launch roles, OWNER and ADMIN_OPERASIONAL, and every Admin capability is an individual grant (RG-01; RG-02). The personas are the repository's synthetic set ([AK-8](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup); [SS-01](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061); SP-19).

| Persona | Role | Companies | Devices and context | Lands on | Sources |
| --- | --- | --- | --- | --- | --- |
| **Laras** | OWNER — every capability through the role (RG-02) | every company, ARJ, BTN and CKP (CS-01; RG-03) | the desk for decisions and Owner-only work; the phone for monitoring and lookup | *Beranda* | persona task analysis, "Owner — desktop and phone"; R1 a) |
| **Dimas** | ADMIN_OPERASIONAL | ARJ and BTN (CS-02) | the desk — multi-line editors, issue and payment work, `[desk]` journeys; the phone for reading and small tasks | *Pekerjaan* | "Admin Operasional — desk work"; R1 and VC1's record of him |
| **Bayu** | ADMIN_OPERASIONAL | ARJ (CS-02) | the phone on the warehouse floor, `[floor]` journeys, a keyboard-wedge scanner where one is attached ([D-UX-09](../ADMIN_FLOW/s01-04-foundations.md#4-design-decisions); [GAP-020](../../00-governance/GAP_REGISTER.md#triage)) | *Pekerjaan* | "Warehouse/field — an Admin Operasional account with warehouse grants, phone-first"; R1 |

**Grant sets — illustrative, not roles (SP-19).** Completed only from [PERMISSIONS_MATRIX §4](../../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue), each grant with its prerequisites (RG-06), from the work the persona task analysis describes — not only what the journeys need. R1 and VC1 record Dimas with ARJ and BTN, `finance.view` and `cost.view`, and Bayu with ARJ warehouse grants and without `finance.view`.

| Capability | Class | Prerequisites | Dimas | Bayu | The work it serves (persona task analysis) |
| --- | --- | --- | --- | --- | --- |
| `projects.view` | ADM | grant | ✓ | ✓ | project lists and the journey; prerequisite of most commands below |
| `finance.view` | ADM | grant | ✓ | — | invoices, billing, payments, the *Keuangan* lists |
| `cost.view` | ADM | grant | ✓ | — | purchases with lines and charges |
| `stock.view` | ADM | — | ✓ | ✓ | prerequisite of reservation (Dimas); stock and racks, receiving, dispatch (Bayu) |
| `evidence.view` | ADM | grant | ✓ | ✓ | proofs and uploads of the families each may view (PJ-08) |
| `projects.manage` | ADM | `projects.view` | ✓ | — | project setup (J-PRJ-01) |
| `quotation.manage` | ADM | `projects.view`, `finance.view` | ✓ | — | quotations with many lines (J-QUO-01) |
| `projects.confirm` | ADM | `projects.view` | ✓ | — | the client's answer as confirmation (J-QUO-01; [AX-13](../../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions)) |
| `stock.reserve` | ADM | `stock.view`, `projects.view` | ✓ | ✓ | reservations (J-INV-02); the same-action offer at receipt (AX-04) — S2-R06's variant removes it from Bayu |
| `purchase.manage` | ADM | `cost.view` | ✓ | — | purchases (J-PUR-01, J-PUR-02) |
| `stock.receive` | ADM | `stock.view` | — | ✓ | receiving (J-INV-01) |
| `stock.dispatch` | ADM | `stock.view`, `projects.view` | — | ✓ | dispatch (J-INV-03) |
| `stock.count` | ADM | `stock.view` | — | ✓ | stock opname (J-INV-05) |
| `delivery.record` | ADM | `projects.view` | — | ✓ | delivery records (J-FUL-02) |
| `documents.issue` | ADM | the subject family's view capability | ✓ | ✓ | documents (J-DOC-01); the operational [DOC-03](../../01-product/V1_SCOPE.md#selectable-document-catalog) and DOC-12 under `projects.view` (PJ-15) for Bayu |
| `evidence.upload` | ADM | `evidence.view` | ✓ | ✓ | uploads (WF-DOC-02); delivery proof (DELIVERY_PROOF, PJ-08) |
| `invoice.issue` | ADM | `finance.view` | ✓ | — | invoices (J-FIN-01) |
| `billing.record` | ADM | `finance.view` | ✓ | — | billing (J-FIN-02) |
| `payment.record` | ADM | `finance.view` | ✓ | — | payments with their applications (J-FIN-03) |
| `payment.apply` | ADM | `finance.view` | ✓ | — | applications of money left unapplied (AX-19) |
| `requirements.manage` | ADM | `projects.view` | ✓ | — | *Dokumen Administrasi* (J-ADM-01) |
| `requirements.waive` | ADM | `projects.view` | ✓ | — | *Tidak Berlaku* on waiver-eligible items ([BR-ADM-02](../../02-domain/BUSINESS_RULES.md#administration-adm)) |
| `projects.complete` | ADM | `projects.view` | ✓ | — | project completion ([SCR-14](../INFORMATION_ARCHITECTURE.md#82-projects-and-quotations); AX-25) |
| `invoice.correct`, `billing.correct`, `documents.correct`, `finance.contra`, `payment.reallocate` | ADM_PLUS | `finance.view`; `documents.correct` the family's view capability | ✓ | — | corrections of the records the analysis names among the Admin's critical tasks — documents, invoices, billing acts and payments — stating what is reversed and what stays (D-UX-10) |
| `warnings.override` | ADM_PLUS | the record's view capability | ✓ | — | confirming a duplicate-payment or duplicate-evidence warning (D-UX-06; IP-05; BD-02) |
| `stock.condition`, `stock.adjust`, `stock.count_apply` | ADM_PLUS | `stock.view` | — | ✓ | counts and their findings, condition changes and losses (J-INV-04, J-INV-05, J-INV-06) |

Every capability above that the table does not mark for a persona is not held. Bayu holds no `finance.view` and no `cost.view`: his receiving runs under the receiving projection (PJ-18), without prices, taxes, charges or costs. Neither Admin holds an OWNER_ONLY capability (RG-02) or `stock.allocate_intercompany`, so another company's lot is never theirs to draw (XL-1). **Second factor:** all three accounts are high-risk under [AU-18](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials) — the OWNER role; an ADM_PLUS grant; `finance.view`, `cost.view` or `evidence.view` — so each signs in with TOTP ([AUTH_JOURNEYS](AUTH_JOURNEYS.md)).

**Variants and comparison columns.** Bayu without `stock.reserve`, which S2-R06 needs, is a recorded simulation scenario, not a persona ([SIMULATION_REGISTER](SIMULATION_REGISTER.md)). [NAVIGATION_MAP](NAVIGATION_MAP.md) adds a "full Admin" column — every ADM and ADM_PLUS capability in all three companies — to show every page an Admin can reach.

## 2. Product map

### 2.1 Primary entries

Task first: two to four primary entries per persona, the modules behind them in a collapsed *Menu lengkap* ([R1 a)](../evidence/P7_REBASELINE_GATE.md#a-navigation--task-first-oc-02-pl-4); [OC-02](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework); [D-IA-01](../INFORMATION_ARCHITECTURE.md#4-design-decisions) SUPERSEDE as R1 revised it).

| Persona | Primary entries, desktop | Bottom bar, phone | Lands on | Why each entry exists (R1 a)) |
| --- | --- | --- | --- | --- |
| Owner | *Beranda* · *Proyek* · *Pekerjaan* · *Tinjauan* | *Beranda* · *Proyek* · *Cari* · *Lainnya* | *Beranda* | the Owner's decisions, exceptions and figures (OC-05); the projects as journeys; the operational queues, demoted from *Beranda* and one click away; the review queue ([QS-14](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)) |
| Admin | *Pekerjaan* · *Proyek* | *Pekerjaan* · *Proyek* · *Cari* · *Lainnya* | *Pekerjaan* | work grouped by what has to happen next (OC-01); the projects as journeys |

Work is entered from *Beranda*'s decision rows, *Pekerjaan*'s work rows, the journey's *Berikutnya* and primary action, search and scan, and "Buat" (§6).

### 2.2 *Menu lengkap* — the modules and their sub-pages as tabs

Each module opens on its first sub-page, its sub-pages shown as tabs, and rarely used ones behind a *Lainnya* tab (R1 a), "as Gudang › Stok shows"). The places come from R1's [revised non-loss map](../evidence/P7_REBASELINE_GATE.md#revised-screens-scr-01scr-60).

| Module | Sub-pages, as tabs | Pages (§5) | Present when the account holds |
| --- | --- | --- | --- |
| *Pembelian* | *Daftar Pembelian* | PG-015–PG-017 | `cost.view` |
| *Gudang* | *Stok* · *Barang Masuk* · *Barang Keluar* · *Reservasi* · *Stok Opname* · *Lainnya* — *Riwayat Stok*, *Penyesuaian & Kondisi*, *Retur ke Pemasok*, *Selisih & Temuan*, *Pengiriman* | PG-018–PG-030 | `stock.view`, or a warehouse command capability (`stock.receive`, `stock.dispatch`); *Selisih & Temuan* the Owner and `stock.resolve_unattributed_evidence` (INFORMATION_ARCHITECTURE §5); *Pengiriman* `projects.view` |
| *Keuangan* | *Invoice* · *Piutang* · *Pembayaran* · *Potongan* · *Lainnya* — *Kredit Pelanggan*, *Pengeluaran & Kas Lain*, *Mutasi Bank* | PG-041–PG-050 | `finance.view` |
| *Dokumen* | *Dokumen* · *Penawaran* · *Bukti* · *Dokumen Administrasi* · *Kasus Koreksi* (§7.4) | PG-035–PG-040 | `projects.view`; *Bukti* `evidence.view` |
| *Laporan* | *Laporan* · *Laporan Gabungan* (Owner) · *Ekspor Saya* | PG-051–PG-053 | a report's read class (RD-02–RD-07); *Ekspor Saya* `reports.export` |
| *Data Master* | *Klien* · *Pemasok* · *Produk & Jasa* · *Lokasi Rak* · *Impor Data Awal* | PG-054–PG-058 | every active account for S0 names ([CS-06](../../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules)); *Impor Data Awal* `import.prepare` or the Owner |
| *Aktivitas & Keamanan* (Owner) | *Riwayat Aktivitas* · *Log Keamanan* | PG-063, PG-064 | `audit.view`, `security_log.view` (OWNER_ONLY) |
| *Pengaturan* (Owner) | *Perusahaan* · *Pengguna & Akses* · *Penomoran Dokumen* · *Pajak* | PG-059–PG-062 | `companies.manage`, `users.manage`, `permissions.manage`, `tax.configure` (OWNER_ONLY) |

The journey's own pages — the quotation, the completion page, the delivery record, direct shipment, the service handover and the client return — are reached from the project and from work rows, not from *Menu lengkap* (R1's revised map). [CAP-16](../../01-product/V1_SCOPE.md#must-ship)'s local backups have no screen, CAP-14 is the design as a whole, and SIPLAH is a project channel with its own fields, never an area (INFORMATION_ARCHITECTURE §5; V1_SCOPE).

### 2.3 Account menu, system page and the pages outside the shell

- **Account menu** — the account's name and role (*Owner* or *Admin Operasional*); *Akun Saya* (PG-065); *Bahasa tampilan: Indonesia | English* ([LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md)); *Keluar*, which asks first only when input is unsent ([MSG-35](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); GR-09 as corrected). On the phone, *Lainnya* › *Akun* holds the same three (R1 a)).
- **System page** — *Halaman Sistem* (PG-005): not found, forbidden and error, the not-found answer identical for an unknown, an out-of-scope and an Owner-only address (AZ-11; IP-21).
- **Outside the shell** — sign-in (PG-001), its second-factor step (PG-002), the credential-link page (PG-003) and the enrolment-only session's page (PG-004); their journeys are [AUTH_JOURNEYS](AUTH_JOURNEYS.md)'s.

## 3. The shell

### 3.1 Desktop and tablet

| Part | Content | Rules |
| --- | --- | --- |
| Navigation | the persona's primary entries (§2.1), then the collapsed *Menu lengkap* (§2.2) | the reference supplies only the treatment of the navigation components ([GD-03](../../00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation)); the current entry carries `aria-current="page"`; Ctrl+B collapses and expands the navigation ([KB-03](../DESIGN_SYSTEM.md#131-keyboard)) |
| Top bar | the field **"Cari atau pindai kode"** with the hint "Ctrl K" (KB-01; [D-IA-04](../INFORMATION_ARCHITECTURE.md#4-design-decisions) KEEP); **"Buat"** with its items (§6.3); the **account menu** (§2.3) | no company switch, company name, count or notification bell (INFORMATION_ARCHITECTURE §6.1; D-IA-02 KEEP); scanner input goes to the field when it has focus (§6.2) |
| Notice strip | absent unless a notice is due — the absolute session limit ([MSG-34](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue)) and a lost connection | the idle warning is a dialog, not a strip (D-IA-05 KEEP; [AU-07](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials); H7-05) |
| Content | the page header and the page; a list's company filter in its header (§4) | the page states its purpose through its title and its first actionable element (R1 g); OC-06) |

The *Pintasan* help stays reachable by its key, Ctrl+/ (KB-02); R1's top bar shows no "Pintasan" button, so whether a visible entry is added is a presentation choice settled in [DESIGN_SYSTEM_MAPPING](DESIGN_SYSTEM_MAPPING.md).

### 3.2 Phone

| Part | Content | Rules |
| --- | --- | --- |
| Top bar | a back arrow below a module's first page; the page title; the account button | the title is the screen's name, never a business number or name (H7-10) |
| Bottom bar | the persona's four entries (§2.1), each target at least 44 px | at most five entries (D-IA-03 ADAPT, R1's delta); an entry the account cannot open is absent, never disabled |
| *Lainnya* sheet | *Kerja* — for the Owner *Pekerjaan* and *Tinjauan* —; *Menu lengkap*; *Akun* with *Bahasa tampilan* and *Keluar* | grouped and ordered as on the desktop (R1 a)) |
| Focused tasks | a scan step, a full-height form sheet, a confirmation and the re-authentication dialog hide the bottom bar, the task's own action bar taking its place | leaving a task with unsent input asks first (ADMIN_FLOW §6; MSG-35) |

Starting new on the phone happens where the work is — "Buat Proyek" on the project list, "Mulai" on a work row — rather than through "Buat" (R1 a)).

### 3.3 Entries and capabilities

- An entry appears only when something behind it is open to the account's capabilities and companies; it is a hint, and the server decides every request ([AZ-01](../../05-security/PERMISSIONS_MATRIX.md#2-authorization-model); UXN-09).
- Owner-only entries — *Tinjauan*, *Aktivitas & Keamanan*, *Pengaturan*, *Laporan Gabungan* and the *Gabungan* view — never appear for an Admin, and their addresses answer the not-found page (UXN-18; AZ-11).
- No entry carries a badge or a count (D-IA-06 KEEP; CS-08; [PF-15](../../06-api-performance/PERFORMANCE.md#4-pagination)); nothing in the shell changes with a list's company filter.
- The prototype's marker "PROTOTIPE · data contoh" is not part of the IA; [SIMULATION_REGISTER](SIMULATION_REGISTER.md) places it.

## 4. The company context

The rules of [INFORMATION_ARCHITECTURE §7](../INFORMATION_ARCHITECTURE.md#7-company-context-model) that state or apply P0–P6 remain behaviour ([DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §4); they are cited, not restated:

- **Scope** is the companies of the account's active grants; the Owner has every company ([CS-01](../../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules); CS-02; RG-03).
- **A view filter, never an authorization:** "Tampilkan: Semua perusahaan" in the header of every list and of *Beranda* and *Pekerjaan* for an account with two or more companies; it narrows the per-company branches ([PF-41](../../06-api-performance/PERFORMANCE.md#4-pagination)), is intersected with scope by the server (DP-02), lives in the address as a whitelisted key and is stored nowhere else ([WF-ACC-01](../../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-acc-01--session-and-company-context-both); D-IA-02 KEEP).
- **Rows of several companies** carry their company code; counts and figures are per company, never summed for an Admin (CS-08; PF-15; PF-25).
- **A command names its company:** a new root's form has the required field "Perusahaan", never prefilled from the filter for an account with several companies; a child record follows its root and shows the root's company as a fixed label — as R1's purchase from a project shows "Perusahaan PT Bentara Niaga (BTN)" (CS-03; CS-04; L-05).
- **A company outside scope is never named:** the relation marker "perusahaan lain" at INFORMATION_ARCHITECTURE §7's listed places only, and the not-found page for its records' addresses (PJ-04; [H7-02](../../05-security/SECURITY.md#p7--presentation-only); AZ-11).
- **The Owner's *Gabungan*:** on *Beranda* the view switch *Gabungan* · ARJ · BTN · CKP filters the signal groups and the period figures (R1 c)); consolidated figures exist only on the Owner's consolidated views (`reports.consolidated`; [CALC-12](../../02-domain/BUSINESS_RULES.md#5-calculation-semantics); PF-25).
- **The pooled projection:** stock pages show S1 physical quantities pool-wide to `stock.view`, without costs, source references or another company's identity (PJ-01–PJ-04; PJ-22).

## 5. Page inventory

A page is an addressable view ([INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md#information-architecture) §11); a child line, ledger row or guard is reached through its root, and a dialog, sheet or confirmation is a state of its page. Each SCR maps to at least one page. A screen is split only where R1 places its purpose differently: SCR-05 and SCR-06 into *Beranda* and *Pekerjaan*, the Owner's operational groups moving from *Beranda* to *Pekerjaan* (R1 a) and c); [OC-05](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework)); SCR-10 into the journey workspace; SCR-14 into the journey's "Yang tersisa sebelum selesai" and the completion page (R1 e)); SCR-21 and SCR-22 each into a worklist and a scan-led task, because R1 enters the task directly from a work row and the warehouse home's "Mulai" (R1 f)); SCR-01 and SCR-02 into the sign-in and second-factor pages and the credential-link and enrolment pages that the authentication amendment adds ([H7-15](../../05-security/SECURITY.md#p7--presentation-only); [GAP-044](../../00-governance/GAP_REGISTER.md#triage); §7). Two tables follow, one row per page in each. The Indonesian names come from INFORMATION_ARCHITECTURE §8 and R1; the English names are **proposed** for the Owner's validation (GAP-037). The draft routes follow INFORMATION_ARCHITECTURE §11 — lowercase Indonesian segments after R1's product map, `{id}` a public identifier (UUIDv7), no search term, name, amount or business number in an address — and are input for GAP-035, not a contract (BP-1).

### 5.1 Identity, place and purpose

| PG | Name — Indonesian · English (proposed) | Draft route | Kind | Purpose | SCR · R1 element | Prototype |
| --- | --- | --- | --- | --- | --- | --- |
| PG-001 | *Masuk* · Sign in | `/masuk` | system | sign in with e-mail and password, or with Google; the second factor follows ([WF-ACC-01](../../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-acc-01--session-and-company-context-both); AU-04; AU-22) | SCR-01 · R1 k), RS-1 | S2-J01 |
| PG-002 | *Verifikasi Dua Langkah* · Two-step verification | `/masuk/verifikasi` | system | the second factor: a TOTP code, or a recovery code after a password; a code only after Google (AU-16; AU-19; AU-22) | SCR-01 · R1 k), "Masukkan kode verifikasi" | S2-J01 |
| PG-003 | *Atur Kata Sandi* · Set password | `/atur-kata-sandi`, the token in the fragment (AU-12) | system | set a password from a credential link (AU-03; AU-12) | SCR-02 · R1's map; §7.1 | not linked |
| PG-004 | *Aktifkan Verifikasi Dua Langkah* · Turn on two-step verification | `/verifikasi-dua-langkah` | task step | the enrolment-only session's single path for a high-risk account without TOTP: enrol, confirm a code, see the recovery codes once (AU-16; AU-18; AU-19; H7-15) | SCR-02's journey, and SCR-01's path for such an account (AU-04) · §7.1 | S2-J01 |
| PG-005 | *Halaman Sistem* · System page | the address requested | system | not found, forbidden, error (AZ-11; [IP-21](../ADMIN_FLOW/s05-command-model.md#ip-21--denial-and-not-found); WS-10) | SCR-04 · unchanged | not linked |
| PG-006 | *Beranda* · Home | `/beranda` | home | the Owner's decisions and exceptions first, the four KPIs in the same view, the projects needing attention, the Admin queues as one folded line (OC-05) | SCR-05 · R1 c), RS-3 | S2-J01, S2-J02 |
| PG-007 | *Pekerjaan* · Work | `/pekerjaan` | work queue | work grouped by what has to happen next — goods, documents and cases, invoices and payments, project completion —, rows of QS signals only (OC-01; PJ-13; QUERY_BUDGET_CHECKS §3) | SCR-06, and SCR-05's operational groups for the Owner · R1 b), f), RS-4, RS-5 | S2-J01, S2-J03, S2-J04, S2-J05 |
| PG-008 | *Tinjauan* · Review | `/tinjauan` | work queue | the Owner review queue — open conditions and activity to review (PERMISSIONS_MATRIX §10; QS-14) | SCR-07 · R1 a), the Owner's entry | out-of-scope page |
| PG-009 | *Cari* · Search | `/cari`, the term never in the address (INFORMATION_ARCHITECTURE §11) | system | results by type, an exact code first ([PF-19](../../06-api-performance/PERFORMANCE.md#5-search); IP-17) | SCR-08 · R1 a), the top-bar field and the phone's *Cari* | out-of-scope page |
| PG-010 | *Proyek* · Projects | `/proyek` | list | find and open projects; within QB-01 each row shows the state and the deadline (QUERY_BUDGET_CHECKS §2.5) | SCR-09 · R1 d), RS-6, as QB-01 fits it | out-of-scope page |
| PG-011 | *Perjalanan Proyek* · Project journey | `/proyek/{id}` | record workspace | the project as a guided journey — header and primary action, *Tahap proyek*, *Sekarang · Berikutnya · Hambatan*, "Yang tersisa sebelum selesai", "Kesiapan dokumen", "Posisi keuangan", "Rincian proyek" (OC-03; PL-1–PL-3; C1A-1; C1A-2) | SCR-10, with SCR-14's unmet conditions · R1 e), RS-7 | S2-J02, S2-J03 |
| PG-012 | *Penyelesaian Proyek* · Project completion | `/proyek/{id}/penyelesaian` | task step | all ten conditions, "Selesaikan Proyek" and the Owner's "Selesaikan Paksa" (WF-PRJ-03; AX-25; AX-26) | SCR-14 · the journey's *Penyelesaian* stage | out-of-scope page |
| PG-013 | *Formulir Proyek* · Project form | `/proyek/baru`; `/proyek/{id}/ubah` | form or editor | create and amend a project with its demand lines (WF-PRJ-01) | SCR-11 · "Buat" › *Proyek*, "Buat Proyek", "Ubah proyek" in *Tindakan Lain* | out-of-scope page |
| PG-014 | *Penawaran* · Quotation | `/proyek/{id}/penawaran` | form or editor | edit, issue and revise the quotation; record the client's answer (WF-QUO-01; WF-PRJ-02) | SCR-13 · the journey's *Kesepakatan* stage; *Rincian proyek* | out-of-scope page |
| PG-015 | *Pembelian* · Purchases | `/pembelian` | list | purchases (WF-PUR-01; WF-PUR-02) | SCR-15 · *Menu lengkap* › *Pembelian* | out-of-scope page |
| PG-016 | *Detail Pembelian* · Purchase | `/pembelian/{id}` | record workspace | lines with received and remaining, charges and their allocation, receipts, disbursements, documents (WF-PUR-01–WF-PUR-03) | SCR-16 · the list, a work row, *Rincian proyek* › *Pengadaan* | S2-J04 |
| PG-017 | *Buat Pembelian* · New purchase | `/pembelian/baru`, a project's shortage prefilled by its public id | form or editor | a purchase with lines and charges, a line linked to a project's demand (WF-PUR-01; WF-PUR-02; AX-29) | SCR-17 · "Buat" › *Pembelian*; the QS-02 work row; the journey's *Gudang* stage, RS-9 | S2-J01, S2-J04 |
| PG-018 | *Stok* · Stock | `/gudang/stok` | list | pooled availability per product ([CALC-01](../../02-domain/BUSINESS_RULES.md#5-calculation-semantics); PJ-01) | SCR-18 · *Gudang* › *Stok*, RS-2 | out-of-scope page |
| PG-019 | *Detail Stok Produk* · Product stock | `/gudang/stok/{id}` | record workspace | racks, lots, serials, reservations and recent movements of one product (PJ-01–PJ-04) | SCR-19 · from *Stok*, search and scan | out-of-scope page |
| PG-020 | *Riwayat Stok* · Stock history | `/gudang/riwayat-stok` | list | movement history (PJ-03) | SCR-20 · *Gudang* › *Lainnya*, dense | out-of-scope page |
| PG-021 | *Barang Masuk* · Goods receiving | `/gudang/barang-masuk` | work queue | the receiving worklist — open purchase remainders (QS-05; PJ-18) | SCR-21's worklist · *Gudang* › *Barang Masuk* | out-of-scope page |
| PG-022 | *Terima Barang* · Receive goods | `/gudang/barang-masuk/{id}`, the purchase | task step | receive one purchase line by line, scan-led — the three outcomes, the rack, the same-action reservation offer, the summary, [DOC-12](../../01-product/V1_SCOPE.md#selectable-document-catalog) on request (WF-INV-01; AX-04; IP-19) | SCR-21's task · R1 f), RS-10 — work rows, "Mulai Terima Barang", "Buat" › *Barang Masuk* | S2-J05 |
| PG-023 | *Barang Keluar* · Goods dispatch | `/gudang/barang-keluar` | work queue | the dispatch worklist of remaining reservations (CALC-02) | SCR-22's worklist · *Gudang* › *Barang Keluar*; *Pekerjaan*'s link (QUERY_BUDGET_CHECKS §3.4) | out-of-scope page |
| PG-024 | *Keluarkan Barang* · Dispatch goods | `/gudang/barang-keluar/{id}`, the project | task step | dispatch a project's reserved lines, scan-led, lots and serials confirmed; the Surat Jalan on request (WF-INV-03; WF-INV-07; AX-03) | SCR-22's task · the work rows "Catat Barang Keluar" and "Keluarkan barang" | out-of-scope page |
| PG-025 | *Reservasi* · Reservations | `/gudang/reservasi` | list | reserve, reduce and release (WF-INV-02; AX-01; AX-02) | SCR-23 · *Gudang* › *Reservasi*; *Rincian proyek* › *Item dan pemenuhan* | out-of-scope page |
| PG-026 | *Stok Opname* · Stock count | `/gudang/stok-opname` | list | counts, their lines and their application (WF-INV-05; AX-06) | SCR-24 · *Gudang* › *Stok Opname* | out-of-scope page |
| PG-027 | *Penyesuaian & Kondisi* · Adjustments and condition | `/gudang/penyesuaian` | form or editor | condition change, disposal, shrinkage and loss (WF-INV-04; WF-INV-06; AX-06; AX-30) | SCR-25 · *Gudang* › *Lainnya* | out-of-scope page |
| PG-028 | *Retur ke Pemasok* · Supplier return | `/gudang/retur-pemasok` | record workspace | a purchase return and its case (WF-PUR-03; AX-11) | SCR-26 · *Gudang* › *Lainnya*; the purchase page | out-of-scope page |
| PG-029 | *Selisih & Temuan* · Discrepancies and findings | `/gudang/selisih-temuan` | list | the Owner's attribution of pending losses and count surpluses; an ADM+ evidence resolution (SF-UNATTRIBUTED; AX-07; AX-37) | SCR-27 · *Beranda*'s decision rows (QS-22, QS-18); *Gudang* › *Lainnya* | out-of-scope page |
| PG-030 | *Pengiriman* · Deliveries | `/gudang/pengiriman` | list | shipments not yet delivered, and delivery records (WF-FUL-02) | SCR-28 · QS-06 rows; *Rincian proyek* › *Pengiriman*; *Gudang* › *Lainnya* › *Pengiriman* | out-of-scope page |
| PG-031 | *Catat Pengiriman* · Record delivery | `/gudang/pengiriman/{id}`, the shipment | form or editor | the delivery record and its discrepancy (WF-FUL-02; AX-09) | SCR-29 · the journey's primary action; the work row | out-of-scope page |
| PG-032 | *Kirim Langsung* · Direct shipment | `/proyek/{id}/kirim-langsung` | form or editor | drop-ship confirmation (WF-FUL-03; AX-08) | SCR-30 · the journey's *Kirim langsung* stage | out-of-scope page |
| PG-033 | *Serah Terima Jasa* · Service handover | `/proyek/{id}/serah-terima` | form or editor | the service handover (WF-FUL-04) | SCR-31 · the journey's *Jasa* stage and its primary action | out-of-scope page |
| PG-034 | *Retur dari Klien* · Client return | `/proyek/{id}/retur` | record workspace | a sales return and its case (WF-FUL-05; AX-10) | SCR-32 · *Rincian proyek* › *Pengiriman*; *Tindakan Lain*; §7.3 | out-of-scope page |
| PG-035 | *Dokumen* · Documents | `/dokumen` | list | generated documents (WF-DOC-01) | SCR-34 · *Menu lengkap* › *Dokumen*; the journey's "Kesiapan dokumen" › "Semua dokumen" (RS-8) | out-of-scope page |
| PG-036 | *Detail Dokumen* · Document | `/dokumen/{id}` | form or editor | a document's draft composer — carried and entered data, the preview marked "DRAF — belum terbit" —, its versions, rendition state, issue, revision and void (WF-DOC-01; WF-FIN-08; DC-1–DC-12) | SCR-35 · document readiness and the document list; VC1's composer | out-of-scope page |
| PG-037 | *Daftar Penawaran* · Quotations | `/dokumen/penawaran` | list | quotation revisions across projects (WF-QUO-01) | SCR-12 · *Dokumen* › *Penawaran* | out-of-scope page |
| PG-038 | *Bukti* · Evidence | `/dokumen/bukti` | list | evidence — upload, register, versions and links (WF-DOC-02; SF-EVIDENCE) | SCR-36 · *Dokumen* › *Bukti*; "Buat" › *Unggah Dokumen* | out-of-scope page |
| PG-039 | *Dokumen Administrasi* · Administrative documents | `/dokumen/administrasi` | list | the cross-project view of unmet requirement items; each project's checklist sits in its journey's document readiness (WF-ADM-01) | SCR-37 · *Dokumen* › *Dokumen Administrasi*; RS-8 | out-of-scope page |
| PG-040 | *Kasus Koreksi* · Correction cases | `/dokumen/kasus` | list | correction cases with their residuals (SF-CASE) | SCR-33 · *Pekerjaan* › *Dokumen dan kasus* (QS-07); the journey's *Retur dan koreksi* condition; §7.4 | out-of-scope page |
| PG-041 | *Invoice* · Invoices | `/keuangan/invoice` | list | invoices (WF-FIN-01) | SCR-38 · *Keuangan* › *Invoice* | out-of-scope page |
| PG-042 | *Buat Invoice* · New invoice | `/keuangan/invoice/baru`, the project by its public id | form or editor | the invoice composer — "Diambil dari proyek", "Diisi sekarang", the preview "DRAF — belum terbit", "Simpan Draf" and "Terbitkan Invoice" (WF-FIN-01; L-26; DC-1–DC-12; GAP-053) | SCR-38's "Buat Invoice" with SCR-39's draft · R1's connected creation, RS-8; VC1's composer, AN-4 | S2-J03 |
| PG-043 | *Detail Invoice* · Invoice | `/keuangan/invoice/{id}` | record workspace | lines, tax, position block, billing act, applications and settlements, documents; a draft opens in the composer (WF-FIN-01; WF-FIN-02; WF-FIN-05; WF-FIN-08) | SCR-39 · document readiness; *Rincian proyek* › *Keuangan*; QS-09–QS-11 rows | S2-J03 |
| PG-044 | *Piutang* · Receivables | `/keuangan/piutang` | list | active receivables with their due state; *Belum Ditagihkan*; *Sengketa* (WF-FIN-02) | SCR-40 · *Keuangan* › *Piutang*; *Beranda*'s QS-10 and QS-11 rows | S2-J02 |
| PG-045 | *Pembayaran* · Payments | `/keuangan/pembayaran` | list | payments and their applications (WF-FIN-03) | SCR-41 · *Keuangan* › *Pembayaran* | S2-J06 |
| PG-046 | *Catat Pembayaran* · Record payment | `/keuangan/pembayaran/baru` | form or editor | a payment with its same-command applications (WF-FIN-03; AX-18; SF-APPLY) | SCR-42 · "Buat" › *Pembayaran*; the journey's *Pembayaran* stage | S2-J06 |
| PG-047 | *Potongan* · Deductions | `/keuangan/potongan` | form or editor | fee and tax deduction settlements (WF-FIN-04; AX-21) | SCR-43 · *Keuangan* › *Potongan*; QS-13 rows | out-of-scope page |
| PG-048 | *Kredit Pelanggan* · Customer credit | `/keuangan/kredit-pelanggan` | list | customer credit and refunds (WF-FIN-06) | SCR-44 · *Keuangan* › *Lainnya*; QS-12 rows | out-of-scope page |
| PG-049 | *Pengeluaran & Kas Lain* · Disbursements and other cash | `/keuangan/kas-lain` | list | disbursements, expenses and other Cash-In (WF-FIN-07) | SCR-45 · *Keuangan* › *Lainnya* | out-of-scope page |
| PG-050 | *Mutasi Bank* · Bank statement | `/keuangan/mutasi-bank` | list | bank statement lines and their citation (L-22) | SCR-46 · *Keuangan* › *Lainnya*, dense | out-of-scope page |
| PG-051 | *Laporan* · Reports | `/laporan` | report | stock, purchase, sales, receivable, payment, profit and cash-flow reports (CAP-11; CAP-17) | SCR-47 · *Menu lengkap* › *Laporan*, dense | out-of-scope page |
| PG-052 | *Laporan Gabungan* · Consolidated report | `/laporan/gabungan` | report | consolidated figures and the inter-company allocation report (CALC-12) | SCR-48 · *Laporan* › *Laporan Gabungan*; *Beranda*'s "Rincian angka" | out-of-scope page |
| PG-053 | *Ekspor Saya* · My exports | `/laporan/ekspor` | list | the requester's own export requests (HO-09) | SCR-49 · *Laporan* › *Ekspor Saya* | out-of-scope page |
| PG-054 | *Klien* · Clients | `/data-master/klien` | list | client organizations, units and PICs, search-first (WF-MD-02; D-IA-14 KEEP) | SCR-50 · *Menu lengkap* › *Data Master* › *Klien* | out-of-scope page |
| PG-055 | *Pemasok* · Suppliers | `/data-master/pemasok` | list | suppliers (WF-MD-03) | SCR-51 · *Data Master* › *Pemasok* | out-of-scope page |
| PG-056 | *Produk & Jasa* · Products and services | `/data-master/produk` | list | products and services, their barcodes and labels (WF-MD-04) | SCR-52 · *Data Master* › *Produk & Jasa* | out-of-scope page |
| PG-057 | *Lokasi Rak* · Rack locations | `/data-master/lokasi-rak` | list | racks and minimum stock (WF-MD-05) | SCR-53 · *Data Master* › *Lokasi Rak* | out-of-scope page |
| PG-058 | *Impor Data Awal* · Opening import | `/data-master/impor` | list | batches, dry run, sign-off, commit and progress (WF-MIG-01) | SCR-58 · *Data Master* › *Impor Data Awal* | out-of-scope page |
| PG-059 | *Perusahaan* · Companies | `/pengaturan/perusahaan` | settings | the company master and its derived readiness (WF-MD-01) | SCR-54 · *Pengaturan* › *Perusahaan* | out-of-scope page |
| PG-060 | *Pengguna & Akses* · Users and access | `/pengaturan/pengguna` | settings | accounts, role, company grants, capabilities, sessions and credential links, with the Owner's account screens of H7-17 (WF-ACC-02) | SCR-57 · *Pengaturan* › *Pengguna & Akses*; *Beranda*'s security notice; §7.5 | out-of-scope page |
| PG-061 | *Penomoran Dokumen* · Document numbering | `/pengaturan/penomoran` | settings | schemes, counters, boundaries, go-live and covering batches; start and seed ([NM-03](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency); HO-39) | SCR-55 · *Pengaturan* › *Penomoran Dokumen* | out-of-scope page |
| PG-062 | *Pajak* · Tax | `/pengaturan/pajak` | settings | tax types, rate versions, company profiles and treatment rules (WF-MD-01) | SCR-56 · *Pengaturan* › *Pajak* | out-of-scope page |
| PG-063 | *Riwayat Aktivitas* · Activity history | `/aktivitas/riwayat` | report | workspace audit search (BR-XC-01) | SCR-59 · *Aktivitas & Keamanan* › *Riwayat Aktivitas*, dense | out-of-scope page |
| PG-064 | *Log Keamanan* · Security log | `/aktivitas/log-keamanan` | report | security events (LG-02) | SCR-60 · *Aktivitas & Keamanan* › *Log Keamanan*, dense; *Beranda*'s security notice | out-of-scope page |
| PG-065 | *Akun Saya* · My account | `/akun` | account | the account's own profile, role, capabilities and companies, its own events, its password, TOTP, recovery codes and Google link (AU-13) | SCR-03 · the account menu; *Lainnya* › *Akun*; §7.2 | out-of-scope page |

The prototype column is the plan for S2-2: the journey that shows the page, the out-of-scope page "Di luar cakupan prototipe" for every entry and link the journeys show but do not cover, or "not linked" where no prototype screen leads to the page ([SIMULATION_REGISTER](SIMULATION_REGISTER.md); the journey specifications list the PS screens).

### 5.2 Access, company, entries and behaviour

The capability that opens each page and its parts comes from INFORMATION_ARCHITECTURE §8's audience column and [PERMISSIONS_MATRIX §4](../../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue); the personas are those of §1 whose grants open the page. The field families are the classes of [PERMISSIONS_MATRIX §7](../../05-security/PERMISSIONS_MATRIX.md#7-resource-and-field-projection) — S0 masters, S1 pooled physical, S2 business, S3 by family (finance, cost, profit), S4 private files by family —; a family the viewer lacks is absent, never masked or shown as zero (§7.1). The context tag is WORKFLOWS §1's: a `[desk]` page opens on a phone for reading and small tasks and does not break (INFORMATION_ARCHITECTURE §8); documents follow DC-11. The dialogs, sheets and confirmations are states of the page, under ADMIN_FLOW's interaction patterns.

| PG | Personas · capability | Field families | Company | Entry points | Tag · phone | Hosts — dialogs, sheets, confirmations |
| --- | --- | --- | --- | --- | --- | --- |
| PG-001 | unauthenticated | — | — | the address; after logout or expiry | `[both]` · one narrow column | the switch "ID \| EN" (R1 k)); AU-04's generic answer (MSG-19) and the throttled answer (AU-05) |
| PG-002 | an account past its password or Google step (AU-16; AU-22) | — | — | PG-001 | `[both]` · one narrow column | "Gunakan kode pemulihan" after a password; "Tidak bisa memakai aplikasi?" after Google (R1 k)); the generic and cooldown answers (AU-05) |
| PG-003 | the link's holder (AU-12) | — | — | the credential link only | `[both]` | the password rules (AU-03); the answers for a used, expired or revoked link ([AUTH_JOURNEYS](AUTH_JOURNEYS.md)) |
| PG-004 | a high-risk account in its enrolment-only session (AU-18) | — | — | PG-003; PG-001 for such an account | `[both]` | the QR code and text key, the confirming code, the recovery codes shown once (AU-16; AU-19); the password re-confirmation after 15 minutes (H7-15) |
| PG-005 | every account | — | — | any unresolvable, out-of-scope or forbidden address | `[both]` | MSG-09 with the reference code and the way back (IP-21) |
| PG-006 | Laras (OWNER; *Gabungan* `reports.consolidated`) | each signal's read class (RD-02–RD-09); the KPIs' CALC families (PJ-22) | *Gabungan* or one company, by the view switch | primary — the Owner's landing page | `[both]` · two decision rows, then "Lihat semua keputusan"; the four KPIs in a two-by-two grid (R1 c)) | five deferred groups, each with its skeleton and error ([PT-19](../DESIGN_SYSTEM.md#94-actions-overlays-and-feedback); QB-10; PF-34); the chart "Umur piutang" with its table in the one-company view (R1 i)) |
| PG-007 | all three; each group by its read class (PJ-13), *Tagihan dan pembayaran* only with `finance.view` | S1, S2 and S3 as each signal's read class | a view filter over the companies in scope; counts per company (CS-08) | primary — the Admin's landing page, the Owner's entry | `[both]` · groups collapsible; Bayu's "Berikutnya" card first (R1 f)) | the deferred signal groups (QB-10); the link to *Barang Keluar*'s worklist (QUERY_BUDGET_CHECKS §3.4) |
| PG-008 | Laras (`review.view`) | each item's projection (RD-09; PJ-11) | branches and the items without a company | primary (Owner); *Beranda*'s QS-14 row | `[desk]` · rows with the window switch | four source tabs and the window filter (INFORMATION_ARCHITECTURE §9.2) |
| PG-009 | all three; company records by their view capability (PJ-10) | S0 masters; company records by family | branches for company records | the top bar, Ctrl K, the phone's *Cari* | `[both]` · full-screen search | the type-ahead and the 200-candidate notice (IP-17) |
| PG-010 | all three (`projects.view`); values with `finance.view` | S2; S3 values | a view filter | primary | `[both]` · rows and a filter sheet | the filter sheet (PT-07); "Buat Proyek" (`projects.manage`); "Muat Lagi" (IP-18) |
| PG-011 | all three (`projects.view`); *Posisi keuangan* `finance.view`; *Biaya dan laba* `cost.view` and `profit.view`; *Pengadaan* `cost.view`; availability `stock.view` | S2; S3 by family; S4 links by family | one company's record | primary, from *Proyek*, *Pekerjaan*, *Beranda* and search | `[both]` · header, primary action, the now card and the track first (R1 e)) | three deferred groups — predicates, documents, *Riwayat* (QB-02); *Tindakan Lain*; IP-08's confirmation for the commands it starts |
| PG-012 | all three read (`projects.view`); "Selesaikan Proyek" `projects.complete` (Laras, Dimas); "Selesaikan Paksa" Laras (`projects.force_complete`) | S2; snapshot money keys by family (PJ-12) | record | the journey's *Penyelesaian* stage; *Pekerjaan* › *Penyelesaian proyek*; *Beranda*'s QS-15 row | `[desk]` · stacked condition rows | IP-08's confirmation; Force Complete bound to its blocker snapshot, with its reason (AX-26; IP-09) |
| PG-013 | Laras, Dimas (`projects.manage`; prices `finance.view`) | S2; S3 prices | new root: the chosen company, "Perusahaan" (CS-03); amend: the record's | "Buat" › *Proyek*; "Buat Proyek"; *Tindakan Lain* | `[both]` · one column, lines as rows | the line editor (IP-13; PT-10); "Tanggal transaksi" (IP-07); discarding a draft |
| PG-014 | Laras, Dimas (`quotation.manage` with `finance.view`; `projects.confirm`) | S2; S3 values | record | the journey's *Kesepakatan* stage; *Rincian proyek* | `[desk]` · read and "Catat Jawaban Klien" | "Terbitkan Penawaran" with IP-08 (AX-15); "Catat Jawaban Klien" (AX-13); "Buat Revisi" |
| PG-015 | Laras, Dimas (`cost.view`) | S2; S3 cost | a view filter | *Menu lengkap* › *Pembelian* | `[desk]` | "Buat Pembelian" (`purchase.manage`); "Muat Lagi" (IP-18) |
| PG-016 | Laras, Dimas (`cost.view`; disbursements `finance.view`) | S2; S3 cost and finance | record | the list; work rows; *Rincian proyek* › *Pengadaan* | `[desk]` | "Terima Barang" (→ PG-022); "Terbitkan PO"; ADM+ corrections with their reason (IP-09), absent without the capability (H7-03) |
| PG-017 | Laras, Dimas (`purchase.manage` with `cost.view`) | S2; S3 cost | new root: the project's company, shown and not chosen, or the chosen company (CS-03; L-05) | "Buat" › *Pembelian*; the QS-02 row, prefilled; the journey's *Gudang* stage | `[desk]` · readable, small edits on the phone | the line editor and charges (IP-13; PT-10); "Rincian lain" (R1 j)); the summary an estimate (UXI-14); save (NX-04) |
| PG-018 | all three (`stock.view`) | S1; own companies' stock value `cost.view` | pool | *Gudang* › *Stok* | `[both]` · scan or search first | the filter sheet; the pending line (MSG-41) |
| PG-019 | all three (`stock.view`) | S1; lot cost `cost.view` (PJ-02) | pool, with the relation marker (PJ-04) | *Stok*; search; scan | `[both]` | "Pindah Lokasi", "Ubah Kondisi", "Catat Penyesuaian Stok", "Mulai Hitungan" by capability; the reservation cut (IP-20) |
| PG-020 | all three (`stock.view`; project `projects.view`; purchase `cost.view`) | S1; S2; S3 cost | branches and the pool branch | *Gudang* › *Lainnya* | `[both]` · dense | "Balikkan" (`stock.reverse`) with its reason (IP-09) |
| PG-021 | Laras, Bayu (`stock.receive`, PJ-18) | the receiving projection — no price, tax, charge or cost (PJ-18) | branches — purchases of companies in scope | *Gudang* › *Barang Masuk*; work rows | `[floor]` | "Muat Lagi" (IP-18) |
| PG-022 | Laras, Bayu (`stock.receive`; the offer `stock.reserve`, absent in S2-R06's variant) | S1; the receiving projection | the purchase's company | work rows; "Mulai Terima Barang"; "Buat" › *Barang Masuk* | `[floor]` · the line checklist, the scan field fixed at the bottom (D-UX-11) | the line sheet — three outcomes, rack, the reservation offer (AX-04; D-UX-12); IP-08's confirmation per line; "Cari Produk"; one answer per line (IP-19); IP-05's fungible-duplicate warning; the summary; "Terbitkan Nota Terima Barang" per receiving event, with IP-08 |
| PG-023 | Laras, Bayu (`stock.dispatch`) | S1; S2 | branches — projects in scope | *Gudang* › *Barang Keluar*; *Pekerjaan*'s link | `[floor]` | "Muat Lagi" (IP-18) |
| PG-024 | Laras, Bayu (`stock.dispatch`); another company's lot only with `stock.allocate_intercompany` (XL-1), held by neither Admin | S1; S2; the relation marker | the project's company; lots: pool | work rows; the worklist | `[floor]` · scan-led | the oldest-first aid (PJ-01); IP-08's confirmation; a routed refusal (IP-06; MSG-14); IP-05's fungible-duplicate warning; "Terbitkan Surat Jalan" |
| PG-025 | all three (`stock.reserve`, with `stock.view` and `projects.view`) | S1; S2 | branches; other projects as the marker (PJ-04) | *Gudang* › *Reservasi*; *Rincian proyek* | `[desk]` | "Reservasi", "Kurangi Reservasi", "Lepas" (AX-01; AX-02) |
| PG-026 | Laras, Bayu (`stock.count`; applying `stock.count_apply`) | S1 | pool | *Gudang* › *Stok Opname*; QS-18 rows | `[floor]` | "Terapkan Selisih" with its reason (AX-06; IP-09); the reservation cut (IP-20); the count sheet's print |
| PG-027 | Laras, Bayu (`stock.condition`; `stock.adjust`) | S1; the bearer company | pool | *Gudang* › *Lainnya*; the product page | `[floor]` | the reason step (IP-09); the reservation cut (IP-20) |
| PG-028 | Laras (`purchase_return.record`, with `stock.view` and `cost.view`) | S1; S2; S3 cost | record | *Gudang* › *Lainnya*; the purchase page | `[floor]` | opening the case; stock out (AX-11); "Tutup Kasus" (`cases.close`); the reason step (IP-09) |
| PG-029 | Laras (`stock.resolve_unattributed`, `stock.attribute_surplus`); an ADM+ holder of `stock.resolve_unattributed_evidence` under PJ-07 | S1; the case snapshot (RD-09) | pool | *Beranda*'s decision rows (QS-22, QS-18); *Gudang* › *Lainnya* | `[desk]` | "Tetapkan Perusahaan", "Tetapkan dengan Bukti", "Tetapkan Surplus", "Balikkan Penetapan", each with its reason (IP-09) |
| PG-030 | all three (`projects.view`; recording `delivery.record`) | S2 | branches | QS-06 rows; *Rincian proyek* › *Pengiriman*; *Gudang* › *Lainnya* › *Pengiriman* | `[both]` | "Catat Pengiriman" (→ PG-031) |
| PG-031 | Laras, Bayu (`delivery.record`; closure `delivery.close`; correction `fulfillment.correct`) | S2; S4 DELIVERY_PROOF (PJ-08) | record | the journey's primary action; the work row | `[both]` · one column with photo proof | the evidence upload (IP-16); IP-05's fungible-duplicate warning; "Tutup Pengiriman" with its reason (IP-09) |
| PG-032 | Laras (`dropship.confirm`; cost `cost.view`) | S2; S3 cost | record | the journey's *Kirim langsung* stage | `[desk]` | "Konfirmasi Kirim Langsung" (AX-08) |
| PG-033 | Laras (`handover.record`) | S2 | record | the journey's *Jasa* stage | `[both]` | the handover; the BAST draft |
| PG-034 | Laras (`sales_return.record`; companions by their capability) | S1; S2 | record | *Rincian proyek* › *Pengiriman*; *Tindakan Lain* | `[both]` | opening the case; the restoration with its reservation offer (AX-10; CM-05); "Tutup Kasus"; IP-09 |
| PG-035 | all three (`projects.view` for metadata; payload by family, PJ-15) | S2; S3 by family | branches | *Menu lengkap* › *Dokumen*; "Semua dokumen" | `[both]` | the exact number lookup (PF-19) |
| PG-036 | Laras, Dimas; Bayu for the operational family (`documents.issue`; revision and void `documents.correct`) | S2; S3 by family (PJ-15) | record | document readiness; the document list | `[desk]` · Edit → Preview → Finalize, full-screen, on the phone (DC-11) | the composer's preview (DC-2); IP-08's issue confirmation; revision and void with their reason (IP-09); "Tautkan Kuitansi" (AX-31) |
| PG-037 | all three (`projects.view`); values Laras, Dimas (`finance.view`) | S2; S3 values | branches | *Dokumen* › *Penawaran* | `[desk]` | "Muat Lagi" (IP-18) |
| PG-038 | all three (`evidence.view` with the family's view capability; `evidence.upload`) | S4 by family (PJ-08) | branches and pool evidence | *Dokumen* › *Bukti*; "Buat" › *Unggah Dokumen* | `[both]` | the upload sheet (IP-16); duplicate-evidence warnings (IP-05; FL-10); "Jadikan Versi Berlaku" |
| PG-039 | all three read (`projects.view`); Laras, Dimas act (`requirements.manage`, `requirements.waive`); the Owner's fields Laras (OD-05, OD-06) | S2 | branches | *Dokumen* › *Dokumen Administrasi* | `[desk]` | "Tandai Tidak Berlaku" with its reason (IP-09); the Owner's relaxations |
| PG-040 | all three (`projects.view` of the case company; money counters by family, PJ-16) | S2; S3 counters by family | branches | *Pekerjaan* › *Dokumen dan kasus*; the journey; *Dokumen* › *Kasus Koreksi* | `[desk]` | "Tutup Kasus" (`cases.close`) with its reason (IP-09) |
| PG-041 | Laras, Dimas (`finance.view`) | S3 finance | branches | *Keuangan* › *Invoice* | `[desk]` | "Buat Invoice" (`invoice.issue`); the exact number lookup |
| PG-042 | Laras, Dimas (`invoice.issue` with `finance.view`) | S2; S3 finance; the identity assets of PJ-19 | the project's company | the journey's *Invoice* stage and "Buat invoice"; document readiness; *Keuangan* › *Invoice* | `[desk]` · on the phone the issue step shows as WF-FIN-01's `[desk]` rule and DC-11 say (S2-J03) | the preview "DRAF — belum terbit" (GD-07; DC-2); "Simpan Draf"; MSG-39's one-step confirmation (IP-08; AX-16) |
| PG-043 | Laras, Dimas (`finance.view`; commands by capability) | S3 finance; S4 by family | record | readiness; *Rincian proyek* › *Keuangan*; QS-09–QS-11 rows | `[desk]` | "Tagihkan" (IP-08); "Tandai Sengketa"; corrections with their reason (IP-09); Laras's "Hapuskan Piutang" (IP-08; IP-09) |
| PG-044 | Laras, Dimas (`finance.view`) | S3 finance | branches; subtotals per company | *Keuangan* › *Piutang*; *Beranda*'s QS-10 and QS-11 rows | `[desk]` | the tabs *Aktif*, *Belum Ditagihkan*, *Sengketa*; "Catat Pembayaran"; "Tagihkan" |
| PG-045 | Laras, Dimas (`finance.view`; "Terapkan" `payment.apply`) | S3 finance | branches | *Keuangan* › *Pembayaran* | `[both]` | "Terapkan"; corrections with their reason (IP-09) |
| PG-046 | Laras, Dimas (`payment.record` with `finance.view`, which covers same-command applications) | S3 finance; S4 PAYMENT_PROOF (PJ-08) | new root: the chosen company (CS-03) | "Buat" › *Pembayaran*; the journey's *Pembayaran* stage; *Piutang* | `[both]` · one column with its application step | the application step (AX-18; SF-APPLY); the duplicate-payment warning, continued only with `warnings.override` (IP-05; BD-02); the missing-proof reason (L-29); IP-08's confirmation |
| PG-047 | Laras (`settlement.record`, with `finance.view` and `evidence.view`) | S3; S4 | record | *Keuangan* › *Potongan*; QS-13 rows | `[desk]` | "Catat Potongan" (AX-21) with its reason and evidence (IP-09) |
| PG-048 | Laras, Dimas (`finance.view`; `payment.apply`); refund Laras (`refund.record`) | S3 | branches | *Keuangan* › *Lainnya*; QS-12 rows | `[desk]` | "Terapkan ke Invoice"; "Kembalikan Dana" (AX-23; IP-09) |
| PG-049 | Laras, Dimas (`finance.view`; expenses `cost.view`); recording by capability — Laras | S3 | branches | *Keuangan* › *Lainnya* | `[desk]` | each kind's form by capability; a correction (IP-09) |
| PG-050 | Laras (`bank_statement.record`, with `finance.view` and `evidence.upload`); for another viewer QS-12's bank-line row is text without a link (INFORMATION_ARCHITECTURE §9.1) | S3; S4 BANK_STATEMENT | branches | *Keuangan* › *Lainnya* | `[desk]` · dense | recording a line; a void (`cash.correct`, IP-09) |
| PG-051 | each report by its read class (RD-02–RD-06) — Laras all; Dimas those of `stock.view`, `projects.view`, `finance.view` and `cost.view`; Bayu the stock report | by read class | branches; one section per company for an Admin (D-IA-12 KEEP) | *Menu lengkap* › *Laporan* | `[desk]` · dense | "Ekspor" (`reports.export`, → PG-053) |
| PG-052 | Laras (`reports.consolidated`) | consolidated (RD-07) | all companies | *Laporan* › *Laporan Gabungan*; *Beranda*'s "Rincian angka" | `[desk]` · dense | "Ekspor" |
| PG-053 | Laras (`reports.export`, the requester only) | own requests | own | *Laporan* › *Ekspor Saya* | `[both]` | "Unduh"; "Minta Lagi" |
| PG-054 | all three — S0 names; units, addresses, PICs `projects.view`; edits `parties.manage` (Laras) | S0; S2 relations (PJ-10) | global; related records by branches | *Data Master* › *Klien* | `[both]` · search-first | search-first creation (PT-27) |
| PG-055 | all three — S0 names; details `projects.view` or `cost.view`; price history `cost.view`; edits `parties.manage` (Laras) | S0; S2; S3 per company (PJ-09) | global; history by branches | *Data Master* › *Pemasok* | `[desk]` | creation and archive |
| PG-056 | all three; price defaults by family (PJ-14); stock `stock.view`; edits `products.edit` (Laras) | S0; S3 defaults | global | *Data Master* › *Produk & Jasa* | `[desk]` | "Buat Barcode Internal"; "Cetak Label" |
| PG-057 | all three read (`stock.view`); edits `warehouse.manage` (Laras) | S1 | pool | *Data Master* › *Lokasi Rak* | `[desk]` | creation and edit |
| PG-058 | Laras — preparing `import.prepare` with every batch company in scope, committing `import.commit` | PJ-20 | the batch's companies | *Data Master* › *Impor Data Awal* | `[desk]` | the step-up before the commit (IP-10; AU-15); IP-08 |
| PG-059 | Laras (`companies.manage`) | PJ-19 | all companies | *Pengaturan* › *Perusahaan* | `[desk]` | the step-up for bank accounts and identity assets (IP-10) |
| PG-060 | Laras (`users.manage`, `permissions.manage`) | PJ-17 | global | *Pengaturan* › *Pengguna & Akses*; *Beranda*'s security notice | `[desk]` | the step-up (IP-10; AU-15); a credential link shown once (AU-12); H7-17's TOTP reset and Google unlink with their reason ([AUTH_JOURNEYS](AUTH_JOURNEYS.md)) |
| PG-061 | Laras (`companies.manage`) | PERMISSIONS_MATRIX §7.2 rows 10–12 | all companies | *Pengaturan* › *Penomoran Dokumen* | `[desk]` | "Mulai Penomoran" and "Tetapkan Nomor Awal" with IP-08's confirmation (NM-03) |
| PG-062 | Laras (`tax.configure`) | PERMISSIONS_MATRIX §7.2 rows 13–16 | global and per company | *Pengaturan* › *Pajak* | `[desk]` | configuration forms |
| PG-063 | Laras (`audit.view`) | PJ-11 | branches and events without a company | *Aktivitas & Keamanan* › *Riwayat Aktivitas* | `[desk]` · dense | filters on IX-13 |
| PG-064 | Laras (`security_log.view`) | PJ-21 | global | *Aktivitas & Keamanan* › *Log Keamanan*; *Beranda*'s security notice | `[desk]` · dense | the filter by kind |
| PG-065 | every account, itself (SELF; PJ-17) | own | global | the account menu; *Lainnya* › *Akun* | `[both]` | the password change (AU-13); TOTP, recovery codes and the Google link behind the step-up (AU-15) — [AUTH_JOURNEYS](AUTH_JOURNEYS.md) |

**Global states of every page in the shell** — not pages: the idle warning dialog and the re-authentication covering the page after expiry (D-UX-03 DECIDE-IN-STAGE-2, its redesign in [AUTH_JOURNEYS](AUTH_JOURNEYS.md); AU-07; H7-05), the notice strip (§3.1), the logout question when input is unsent (MSG-35), the *Pintasan* help (KB-02) and a lost-connection answer (IP-04).

## 6. Search, scan and "Buat"

[INFORMATION_ARCHITECTURE §10](../INFORMATION_ARCHITECTURE.md#10-search-lookup-and-scanner-entry-points), [IP-15](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-15--scanner) and IP-17 remain the behaviour — what is found, under which scope.

### 6.1 Search

- **Where:** the top-bar field "Cari atau pindai kode" and Ctrl+K on the desktop; *Cari* in the phone's bottom bar, a full-screen search (PG-009; D-IA-04 KEEP).
- **What is found:** a code — SKU, barcode, serial, project number, document number — exactly first; one hit opens it, several rows sharing the key within scope are listed ([PF-19](../../06-api-performance/PERFORMANCE.md#5-search)). A name of at least three consecutive letters or digits searches product, client and supplier names (S0) and the titles of projects in scope, names equal to or beginning with the term first, a type-ahead of at most 20 rows and PG-009 for the full result, cut at 200 candidates with IP-17's notice; at most 100 characters (PF-20–PF-23).
- **Under which scope:** company records only within the viewer's scope and view capability; a result, count or empty state never hints at a record outside it ([CS-07](../../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules); DP-06; PJ-10). A search term never appears in an address or a title (INFORMATION_ARCHITECTURE §11; [H7-10](../../05-security/SECURITY.md#p7--presentation-only)).

### 6.2 Scan

- **Into search:** the scanner types into the search field when it has focus, or into the phone's full-screen search; exact lookup only (QB-06) — a product, serial or lot found opens its stock detail (PG-019); unknown and ambiguous codes as IP-15 states.
- **Inside a warehouse task:** the scan field of PG-019, PG-022, PG-024, PG-026, PG-027, PG-028 and PG-034, with manual SKU entry and "Cari Produk" always beside it ([PT-20](../DESIGN_SYSTEM.md#93-forms-and-entry); IP-15); a keyboard wedge only, no camera scanning (D-UX-09; [GAP-020](../../00-governance/GAP_REGISTER.md#triage)).
- **Lookups inside forms:** exact code first, then names from three characters (PT-27); a client is created search-first (PG-054).
- **Exact number lookups on lists:** the project list (PG-010), the documents (PG-035) and the invoices (PG-041).

### 6.3 "Buat"

Each item is present only with its capability (AZ-01, a hint), and each new root names its company (CS-03; INFORMATION_ARCHITECTURE §7):

| Item | Capability | Opens | Its company |
| --- | --- | --- | --- |
| *Proyek* | `projects.manage` | PG-013 | chosen in the form's "Perusahaan", never prefilled from the filter for several companies |
| *Pembelian* | `purchase.manage` | PG-017 | the project's company, shown and not chosen, when started from a project's shortage; otherwise chosen (L-05) |
| *Barang Masuk* | `stock.receive` | PG-021, to choose the purchase, then PG-022 | the purchase's company — a receipt follows its purchase |
| *Pembayaran* | `payment.record` | PG-046 | chosen in the form |
| *Unggah Dokumen* | `evidence.upload` | PG-038's upload sheet | the evidence's company, or the shared warehouse for pool evidence (PJ-08) |

Laras sees all five; Dimas *Proyek*, *Pembelian*, *Pembayaran* and *Unggah Dokumen*; Bayu *Barang Masuk* and *Unggah Dokumen*. On the phone, new work starts where it is — "Buat Proyek" on PG-010, "Mulai" on a work row (§3.2).

## 7. The five DECIDE-IN-STAGE-2 items

R1 kept five Stage 1 notes for Stage 2 ([revised non-loss map](../evidence/P7_REBASELINE_GATE.md#revised-screens-scr-01scr-60)): three about content the authentication amendment adds — [SCR-02](../INFORMATION_ARCHITECTURE.md#81-system-account-and-beranda), SCR-03, SCR-57 — and two about a location — SCR-32, SCR-33. Each resolution below is proposed for the Owner's review with the S2-1 report; none needs a rule the sources do not give, so none is an Owner question.

### 7.1 SCR-02 — the credential link and the second-factor enrolment

- **Question.** Whether a high-risk account enrols its second factor in the same journey as setting its password ([H7-15](../../05-security/SECURITY.md#p7--presentation-only); [GAP-044](../../00-governance/GAP_REGISTER.md#triage)).
- **Options.** (a) The credential-link page itself opens the enrolment after the password is set. (b) The person sets the password on the credential-link page (PG-003), signs in with it (PG-001), and the password step leads a high-risk account without TOTP into the enrolment-only session (PG-004), which reaches only the enrolment, the password confirmation and logout until a device is confirmed; the recovery codes are shown once; then the landing page.
- **Proposed resolution.** (b), one continuous journey across the three pages. (a) is not open: "a credential link never creates a session and never bypasses TOTP" (AU-20), and the enrolment-only session begins only "after a correct password" (AU-18).
- **Grounds.** AU-03; AU-12; AU-16; AU-18; AU-19; AU-20; H7-15. The pages, steps and copy are designed in [AUTH_JOURNEYS](AUTH_JOURNEYS.md).

### 7.2 SCR-03 — *Akun Saya*

- **Question.** How AU-13's self-service grows with TOTP, the recovery codes and the Google link (H7-15; H7-16; H7-18).
- **Options.** (a) One page (PG-065) with sections — the account's profile, role, capabilities and companies, read-only; sign-in and security — the password, TOTP, the recovery codes and the Google link; its own events and last login. (b) A separate page for each function.
- **Proposed resolution.** (a): every function of AU-13 on one page, each sensitive action behind the step-up (AU-15) — enrolling or replacing TOTP, viewing and regenerating the recovery codes with a current code (AU-19), disabling TOTP only for an account that is not high-risk (AU-16; AU-18), linking and unlinking Google (AU-23; AU-24) —, and the first-login notice of AU-13 and H7-18. AU-13 lists no language setting: the switch sits in the account menu under C-1 and R1 k), and a per-account preference is [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md)'s question.
- **Grounds.** AU-13; AU-15; AU-16; AU-19; AU-20 a and f; AU-23; AU-24; [PJ-17](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections); H7-15; H7-16; H7-18. Designed in [AUTH_JOURNEYS](AUTH_JOURNEYS.md).

### 7.3 SCR-32 — *Retur dari Klien*, its cross-project entry

- **Question.** Whether a sales return has a cross-project entry — *Proyek* › *Pengiriman* or *Kasus Koreksi*, since a return opens a case.
- **Options.** (a) From the project only — *Rincian proyek* › *Pengiriman* and *Tindakan Lain*. (b) Also from the cross-project *Pengiriman* list (PG-030), on a delivered record. (c) Also from *Kasus Koreksi* (PG-040), as "a new return".
- **Proposed resolution.** (a) with (b): a correction always starts from the record it corrects, and a sales return starts from the delivery — "Retur dari Klien" ([ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) §11, CM-10–CM-13; J-FUL-05) —, which the journey and the cross-project *Pengiriman* list both show. *Kasus Koreksi* lists the case the return opens and starts none (INFORMATION_ARCHITECTURE §5).
- **Grounds.** ADMIN_FLOW §11; [WF-FUL-05](../../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-ful-05--sales-return-after-delivery-both); INFORMATION_ARCHITECTURE §5; `sales_return.record`.

### 7.4 SCR-33 — *Kasus Koreksi*, its cross-project home

- **Question.** Where the cross-project list of correction cases lives, since cases also arise from purchases and stock (SCR-26), not only from projects.
- **Options.** (a) *Menu lengkap* › *Dokumen* › *Kasus Koreksi* (PG-040), beside the documents, as *Pekerjaan*'s group *Dokumen dan kasus* pairs them. (b) A module of its own in *Menu lengkap*, as INFORMATION_ARCHITECTURE §5 had an area *Kasus Koreksi*. (c) Under *Proyek*, as Stage 1 prototyped it — narrower than the cases' origins.
- **Proposed resolution.** (a), keeping R1's module list unchanged; the open cases also appear in *Pekerjaan* (QS-07) and in the journey's *Retur dan koreksi* condition, and each case is reached from the record that opened it.
- **Grounds.** INFORMATION_ARCHITECTURE §5; R1 a) and b); QS-07; PJ-16; XL-3.

### 7.5 SCR-57 — *Pengguna & Akses*, the Owner's account screens

- **Question.** Where the Owner's account screens of H7-17 live — each account's TOTP status and reset, the Google unlink and its history.
- **Proposed resolution.** In the account's detail on PG-060: its TOTP status and enrolment time; "reset TOTP" — for an account without TOTP, a password reissue — with the step-up, a reason and the out-of-band RESET link shown once; the RESET link that a role assignment, a grant or an activation issues when it makes an account without TOTP high-risk; the Google unlink with its reason; the account's Google link history — never a secret, a code or a provider subject.
- **Grounds.** AU-12; AU-15; AU-18; AU-20 c and d; AU-24; PJ-17; H7-17. Designed in [AUTH_JOURNEYS](AUTH_JOURNEYS.md).

## 8. Counts

Derived by script from [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md#information-architecture) §8 and the two tables of §5:

| Count | Value |
| --- | --- |
| Pages | **65** — PG-001–PG-065: 4 outside the shell (PG-001–PG-004) and 61 in the shell (PG-005–PG-065), the system page PG-005 among them |
| SCR coverage | **60 of 60** — every SCR-01–SCR-60 of INFORMATION_ARCHITECTURE §8 maps to at least one page; none is unplaced. Eight map to two or three pages, as §5 explains: SCR-01, SCR-02, SCR-05, SCR-14, SCR-21, SCR-22, SCR-38 and SCR-39 |
| Pages without an SCR | **0** — the pages added by splits keep their SCR; no page exists without an SCR source |
| Pages per persona, in the shell (PG-005–PG-065) | **Laras 61**; **Dimas 36**; **Bayu 30** — each from table 5.2's persona column under the grant sets of §1; all three also meet PG-001 and PG-002 at sign-in, and PG-003 and PG-004 only through a credential link or an enrolment-only session |
| Prototype places | the plan of §5.1, confirmed with the screen list: **14** pages shown by S2-J01–S2-J06 — PG-001, PG-002, PG-004, PG-006, PG-007, PG-011, PG-016, PG-017, PG-022, PG-042, PG-043, PG-044, PG-045 and PG-046; **49** reached through the out-of-scope page; **2** not linked — PG-003 and PG-005 |
