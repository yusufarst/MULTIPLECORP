# Procurement (PUR)

Tier 4 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-007) — `purchase_line_balances`' without a factory, a balance belonging to its posting service (ARCHITECTURE §7) —; its other Tasks add the rest.

Tables ([DATABASE §4.6](../../../docs/04-architecture/DATABASE/s04-06-pur.md), 8): `purchases`, `purchase_lines`, `purchase_line_tax_components`, `purchase_charges`, `purchase_charge_line_shares`, `purchase_line_balances`, `purchase_line_closures`, `purchase_line_adjustments`.
