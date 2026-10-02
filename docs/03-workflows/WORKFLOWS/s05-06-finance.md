### 5.6 Financial workflows

#### WF-FIN-01 — Invoice (sales record) issuance `[desk]`

- **Preconditions:** confirmed commercial value; company active; project cap: Σ non-void sales values ≤ Σ confirmed demand value (L-16, checked in AX-16).
- **Main path:** draft the sales record from confirmed lines (itemized) or as a value-only DP/termin record → tax components under the treatment applicable to the transaction (company, transaction context, business_date, evidence), recorded separately from the sales value (DIR-024 D-1) → issue as DOC-06 Invoice or DOC-04 Nota through SF-ISSUE (L-26) → Sales/Transaction Value snapshotted; status BELUM DITAGIHKAN; no Cash-In and no active receivable (BR-FIN-02). Operating revenue excludes output-tax components (CALC-08 as amended by DIR-024).
- **Branches:** several invoices per project (DP/termin) are normal; invoicing above delivered value is flagged (QS-15), not blocked; advance money already held as customer credit is applied after issue (WF-FIN-03).
- **Corrections:** CM-23 unbilled, CM-24 billed.
- **Traceability:** CAP-10/11; AC-10/11; DOC-04/06; REF-039; IMG-07.

#### WF-FIN-02 — Billing activation and receivable monitoring (*Piutang*) `[desk]`

- **Main path:** mark an issued non-void invoice DITAGIHKAN with billed_at and an entered due_date (no payment-terms engine) (AX-17) → the unpaid remainder becomes active receivable = billed value − Σ(payment applications + fee settlements + tax settlements + Owner write-offs) ≥ 0 (BR-FIN-04 as amended) → optional billing package: DOC-13 and, where the client's administration requires it, a Kuitansi untuk Proses Pembayaran (WF-FIN-08) → aging from due_date per BR-FIN-15 (QS-10).
- **Branches:** advance applications reduce the activated amount (annex 2); a replacement invoice — named explicitly in the void-and-replace or revision action (AX-22), never inferred — inherits billed_at/due_date, another due date only through CM-25 with entry-error evidence (L-30); a wrong billing act or due date → CM-25; dispute → WF-FIN-05.
- **Traceability:** CAP-10/17; AC-10/23; DOC-13; REF-039/040.

#### WF-FIN-03 — Payment receipt and application (*Pembayaran*) `[both]`

- **Preconditions:** evidence that money was actually received (bank statement line, transfer proof or cash receipt); a company bank account or cash method.
- **Main path:** record the payment fact (AX-18): business_date, amount > 0, method, company bank destination, the bank statement line it is evidenced by (flagged until cited), reference, proof, paying client (a treasurer or platform that paid on the client's behalf is recorded as intermediary metadata) and, where one exists, the remittance advice with claimed gross and claimed deductions → duplicate check: same company bank account and amount within a date window, regardless of reference → warning; proceeding needs an ADM+ override with audited reason (QS-21); Σ Cash-In facts citing one statement line, net of contras, never exceed the line amount, and a line not yet fully cited is a reconciliation signal (QS-12; L-22) → application through SF-APPLY (AX-19): issued invoices of the same client and company (billed or unbilled, never drafts), absolute amount per target, each ≤ outstanding, Σ ≤ payment → any remainder is customer credit (BR-FIN-03/05) → evidenced deductions of the same remittance through WF-FIN-04 → link to an outstanding Kuitansi untuk Proses Pembayaran if one exists (WF-FIN-08).
- **Multi-client bank line:** one payment fact per client, all sharing one bank-line identity whose total is conserved (L-22).
- **Missing proof:** allowed only with a reason; flagged; fails the audit predicate until evidenced; its credit cannot be refunded; a company-issued document such as a Kuitansi is never proof of receipt (L-29).
- **Partial, advance and overpayment:** numeric annex 1–3.
- **Corrections:** CM-19 wrong payment fact (contra), CM-20 wrong company (BR-FIN-07), CM-21 reallocation — never of an application consumed by a refund (L-46) and always carrying the settlements anchored to the old (payment, invoice) with it or keeping them with reason (L-28).
- **Traceability:** CAP-10; AC-10/12/16; DOC-05/07; REF-041–043; IMG-08.

#### WF-FIN-04 — Deduction settlement: verified intermediary fee and tax withheld/collected `[desk]`

- **Preconditions:** the payment fact of the same remittance exists (settlements are anchored to it, L-28); evidence uploaded through SF-EVIDENCE — fee: platform or bank advice; tax: the official withholding/collection proof; target invoice issued and non-void with outstanding ≥ amount.
- **Main path:** record one settlement (AX-21): type, amount, invoice, payment reference, evidence, business_date, actor → reduces that invoice's active receivable; creates no Cash-In and no payment; the gross invoice value stays unchanged.
  - **Fee type** (SIPLAH platform fee; verified bank/VA/payment-intermediary fee): project-attributed expense exactly once under all seven DIR-020 conditions (BR-FIN-09). The fee expense category is closed to manual entry, so the same fee cannot be expensed twice.
  - **Tax type** (tax withheld or collected by a client, government treasurer, SIPLAH operator or other legitimate intermediary): not a payment, not a write-off, not operating revenue and not automatically an expense — it becomes an expense only through an explicit, configured and evidenced treatment recorded once; tax type recorded; reported separately for reconciliation with the company's tax records (DIR-024 D-1; BR-FIN-16).
- **Conservation:** one settlement per (payment, invoice, class fee/tax, specific type — e.g., separate settlements for tax collected and tax withheld); each cites its evidence identity (L-45) and proven amount, and Σ settlements per evidence identity ≤ that amount across all payments and termins; payment + Σ its settlements ≤ the claimed gross of its remittance advice; collected output tax ≤ the invoice's tax component; same client and company; Σ reductions ≤ billed (BR-FIN-04).
- **Late evidence:** the payment is recorded and applied for the net amount; claimed-but-unevidenced deductions stay outstanding and visible (QS-13 = claimed − evidenced) until the evidence arrives; no settlement without evidence.
- **Guard:** an unexplained short payment cannot be labelled a fee or tax; it stays outstanding, is disputed, or is written off by the Owner (QS-14 exception view).
- **Corrections:** CM-22; a reallocation off the invoice moves or keeps the anchored settlements (CM-21); a void or downward revision re-records them or keeps them as explicit residuals (CM-24). **Traceability:** CAP-10/11/12; AC-10/11/15; REF-042/044; OWN-01; DIR-019/020/024.

#### WF-FIN-05 — Dispute hold and Owner write-off `[desk]`

- **Dispute:** ADM sets an indicator with reason; the receivable stays active and keeps aging from due_date; it blocks normal completion; clearing needs a reason (BR-FIN-08).
- **Resolution:** a real payment; an invoice correction where value is not owed (CM-23/24); or Owner write-off (OWN!, AX-24): amount ≤ outstanding, reason, before/after, audit; removes the amount from active receivable and collectible aging while preserving it as disposed history; no Cash-In, no profit (DIR-019). If the same client and company hold unapplied credit, the Owner applies it first or records why not (L-17). Money that was actually received is reconstructed as a real payment, never written off.
- **Recovery after write-off:** money later received for a written-off amount is recorded as a real payment; meanwhile its unapplied amount is credit linked to the written-off invoice and listed for the Owner (QS-12/QS-14); the Owner supersedes the write-off by up to that amount and the payment is applied to the invoice in the same action (AX-24; L-17), keeping the original write-off as disposed history. A dispute hold lapses when the invoice's outstanding reaches zero through payment, correction or write-off.
- **Traceability:** CAP-10; AC-10/21; OWN-01; DIR-019.

#### WF-FIN-06 — Customer credit and refund `[desk]`

- **Credit:** derived from unapplied payment money, applications released by corrections and opening customer credit carried by migration — always traceable to real payment money or to the opening credit's import identity and signed opening evidence (BR-FIN-05; BR-XD-06); spent only by application to another invoice of the same client and company, or by refund.
- **Refund (AX-23, ADM+):** a refund disbursement (Cash-Out) consuming one application of evidenced credit, with transfer evidence; never negative Cash-In, never cost. The consumed application is final while the disbursement stands: no reallocation (CM-21) supersedes, releases or re-targets it; only a contra of the refund disbursement (CM-38: entry error, or the transfer came back) releases it to credit (L-46).
- **Unbacked refund:** when a payment contra (CM-19) leaves a refund disbursement not fully backed by applications of real payments of the same client and company, the shortfall is an explicit residual on the correction case (QS-07/QS-14); it blocks case closure and normal completion until a later real payment of that client and company is applied to the refund disbursement or the disbursement itself is contra'd as an entry error (L-46).
- **Rules:** credit is never revenue or profit (FS-08); project-originated credit needs a disposition (apply, refund, or hold with reason) before normal completion.
- **Traceability:** CAP-10; AC-10; REF-042.

#### WF-FIN-07 — Expenses, disbursements and other cash `[desk]`

- **Expense record:** actual cost with company, optional causal project attribution and evidence; system-created kinds: fee settlements (WF-FIN-04), losses (SF-LOSS), non-attributable purchase charges (DIR-024 D-2); a charge is never both acquisition cost and expense.
- **Disbursement (Cash-Out):** against a purchase, expense or refund reference, with evidence (BR-FIN-11). Creating a purchase is never Cash-Out.
- **Other Cash-In:** income categories (e.g., actually received cashback/pengembalian, BR-FIN-10) versus non-income restitution (supplier refunds for returned, cancelled or overpaid purchases, L-08).
- **Tax remittance:** tax the company itself pays to the state is a disbursement (Cash-Out) referencing its tax payment evidence (BR-FIN-11); remitting output tax, which is not revenue under the NET model, never becomes cost; any other tax payment follows its configured, evidenced treatment; tax that forms acquisition cost enters cost solely through SF-CHARGE on the purchase line, and a tax settlement becomes an expense only through an explicit configured, evidenced treatment — nothing is counted twice (DIR-024 D-1; L-43).
- **Cross-company money:** a real inter-bank transfer is recorded as Cash-Out (A) + Cash-In (B) facts (BR-FIN-07); never automatic.
- **Corrections:** CM-38 wrong disbursement, expense or refund (SF-CONTRA; AX-33).
- **Traceability:** CAP-11; AC-11; REF-044–046.

#### WF-FIN-08 — Kuitansi untuk Proses Pembayaran (pre-payment Kuitansi) `[desk]`

- **Purpose:** support institutional/government payment processing that requires a signed Kuitansi before funds are transferred (DIR-024 D-4).
- **Preconditions:** issued non-void invoice (normally billed); Σ open pre-payment Kuitansi on that invoice, including this one, ≤ its outstanding; reason recorded.
- **Main path:** issue DOC-05 in pre-payment mode through SF-ISSUE (Kuitansi number; the snapshot and output state *untuk proses pembayaran*) → no Cash-In, no payment fact, no settlement, no paid state; the receivable is unchanged → QS-19 lists it until linked or voided.
- **Payment arrives:** the real payment is recorded normally (WF-FIN-03) and linked per (payment, invoice); the Kuitansi amount must equal the linked payments' applications to that invoice plus that invoice's evidenced settlements, otherwise it is revised or voided and reissued; a later correction lowering that total unlinks it (QS-19); a receipt-mode Kuitansi is REJECTED while a pre-payment Kuitansi is open on the invoice, and money already linked receives no second Kuitansi (no duplicate recognition); the pre-payment Kuitansi is never evidence that money was received.
- **Corrections:** the issued Kuitansi stays immutable under normal revision/void rules (CM-26); voiding it never touches payments.
- **Traceability:** CAP-08/10; AC-08/10; DOC-05/07; BR-DOC-04 as amended by DIR-024.

