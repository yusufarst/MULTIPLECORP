# Finance (FIN)

Tier 6 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-009); `invoice_balances`', `application_source_balances`' and `money_fact_balances`' have no factory, a balance belonging to its posting service (ARCHITECTURE §7); its other Tasks add the rest.

Tables ([DATABASE §4.12](../../../docs/04-architecture/DATABASE/s04-12-fin.md), 19): `invoices`, `invoice_versions`, `invoice_version_lines`, `invoice_tax_components`, `invoice_balances`, `invoice_billing_acts`, `payments`, `payment_claimed_deductions`, `application_source_balances`, `payment_applications`, `deduction_settlements`, `receivable_writeoffs`, `receivable_dispute_holds`, `opening_customer_credits`, `prepayment_kuitansi_links`, `disbursements`, `other_cash_receipts`, `financial_contras`, `money_fact_balances`.
