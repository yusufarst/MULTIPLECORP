# Costing & Expenses (CST)

Tier 3 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-007) but `purchase_charge_share_balances`', a guard keyed by its charge and line together, which an Eloquent key cannot be, and posted by one statement (DATABASE §1); `lot_cost_balances`' and `purchase_charge_balances`' have no factory, a balance belonging to its posting service (ARCHITECTURE §7); its other Tasks add the rest.

Tables ([DATABASE §4.8](../../../docs/04-architecture/DATABASE/s04-08-cst.md), 6): `lot_cost_entries`, `lot_cost_balances`, `cost_attributions`, `purchase_charge_balances`, `purchase_charge_share_balances`, `expenses`.
