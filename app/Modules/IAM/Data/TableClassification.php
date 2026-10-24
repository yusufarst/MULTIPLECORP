<?php

namespace App\Modules\IAM\Data;

use InvalidArgumentException;

/**
 * The table classification of PERMISSIONS_MATRIX §7.2: each of the 125 tables of DATABASE §4 with its scope
 * and the sensitivity classes of its own rows and columns (DOMAIN_MODEL S0–S4). A split table carries
 * several classes, its field exceptions applied by its prop resources; a table whose class follows the
 * record that owns it, and `command_log`, which is never projected, carry their own markers. A test holds
 * this list to the document (TASK-012).
 */
final class TableClassification
{
    /** The class of a table whose rows take the class of the record that owns them. */
    public const OWNER_RECORD = 'owner record';

    /** The class of a table never projected (`command_log`). */
    public const NONE = 'none';

    /**
     * table => [the row number of §7.2, scope, class as §7.2 states it]
     *
     * @var array<string, array{0: int, 1: string, 2: string}>
     */
    public const TABLES = [
        'users' => [1, 'GLOBAL', 'S2'],
        'roles' => [2, 'GLOBAL', 'S2'],
        'capabilities' => [3, 'GLOBAL', 'S2'],
        'role_capabilities' => [4, 'GLOBAL', 'S2'],
        'user_roles' => [5, 'GLOBAL', 'S2'],
        'user_capability_grants' => [6, 'GLOBAL', 'S2'],
        'user_company_grants' => [7, 'GLOBAL', 'S2'],
        'companies' => [8, 'COMPANY', 'S2 / S3'],
        'company_bank_accounts' => [9, 'COMPANY', 'S3'],
        'numbering_schemes' => [10, 'COMPANY', 'S2'],
        'number_sequences' => [11, 'COMPANY', 'S2'],
        'retired_numbers' => [12, 'COMPANY', 'S2'],
        'tax_types' => [13, 'GLOBAL', 'S0'],
        'tax_rate_versions' => [14, 'GLOBAL', 'S0'],
        'company_tax_profiles' => [15, 'COMPANY', 'S3'],
        'tax_treatment_rules' => [16, 'GLOBAL / COMPANY', 'S0 / S3'],
        'client_organizations' => [17, 'MASTER', 'S0 / S2'],
        'client_units' => [18, 'MASTER', 'S2'],
        'client_addresses' => [19, 'MASTER', 'S2'],
        'client_contacts' => [20, 'MASTER', 'S2'],
        'suppliers' => [21, 'MASTER', 'S0 / S2'],
        'products' => [22, 'MASTER', 'S0 / S1'],
        'units' => [23, 'MASTER', 'S0'],
        'product_units' => [24, 'MASTER', 'S0'],
        'product_barcodes' => [25, 'MASTER', 'S0'],
        'product_price_history' => [26, 'MASTER', 'S3'],
        'product_images' => [27, 'MASTER', 'S0'],
        'projects' => [28, 'PROJECT', 'S2'],
        'project_siplah_details' => [29, 'PROJECT', 'S2 / S3'],
        'project_lines' => [30, 'PROJECT', 'S2 / S3'],
        'commercial_confirmations' => [31, 'PROJECT', 'S2'],
        'confirmation_line_pins' => [32, 'PROJECT', 'S2 / S3'],
        'quotation_revisions' => [33, 'PROJECT', 'S2'],
        'quotation_revision_lines' => [34, 'PROJECT', 'S2 / S3'],
        'line_scope_cancellations' => [35, 'PROJECT', 'S2'],
        'project_line_balances' => [36, 'PROJECT', 'S2'],
        'project_commercial_balances' => [37, 'PROJECT', 'S3'],
        'project_state_transitions' => [38, 'PROJECT', 'S2 / S3'],
        'purchases' => [39, 'COMPANY', 'S3'],
        'purchase_lines' => [40, 'COMPANY', 'S3'],
        'purchase_line_tax_components' => [41, 'COMPANY', 'S3'],
        'purchase_charges' => [42, 'COMPANY', 'S3'],
        'purchase_charge_line_shares' => [43, 'COMPANY', 'S3'],
        'purchase_line_balances' => [44, 'COMPANY', 'S3'],
        'purchase_line_closures' => [45, 'COMPANY', 'S3'],
        'purchase_line_adjustments' => [46, 'COMPANY', 'S3'],
        'locations' => [47, 'POOL', 'S1'],
        'inventory_lots' => [48, 'POOL × COMPANY', 'S1 / S3'],
        'serial_units' => [49, 'POOL', 'S1'],
        'stock_movements' => [50, 'POOL', 'S1 / S3'],
        'stock_movement_entries' => [51, 'POOL', 'S1'],
        'receiving_lines' => [52, 'COMPANY', 'S2 / S3'],
        'dispatch_lines' => [53, 'PROJECT', 'S2'],
        'stock_consumptions' => [54, 'POOL × COMPANY', 'S3'],
        'stock_consumption_balances' => [55, 'POOL × COMPANY', 'S2'],
        'stock_restorations' => [56, 'PROJECT / COMPANY', 'S2'],
        'stock_product_balances' => [57, 'POOL', 'S1'],
        'stock_scope_balances' => [58, 'POOL', 'S1'],
        'stock_lot_balances' => [59, 'POOL', 'S1'],
        'stock_reversal_balances' => [60, 'POOL', 'S1'],
        'reservations' => [61, 'PROJECT ↔ POOL', 'S1 / S2'],
        'reservation_events' => [62, 'PROJECT ↔ POOL', 'S1 / S2'],
        'stock_counts' => [63, 'POOL', 'S1'],
        'stock_count_scopes' => [64, 'POOL', 'S1'],
        'stock_count_lines' => [65, 'POOL', 'S1'],
        'stock_count_findings' => [66, 'POOL', 'S1 / S3'],
        'unattributed_loss_cases' => [67, 'POOL', 'S1'],
        'unattributed_loss_candidates' => [68, 'POOL × COMPANY', 'S3'],
        'unattributed_loss_exposures' => [69, 'POOL × COMPANY', 'S3'],
        'unattributed_loss_group_exposures' => [70, 'POOL × COMPANY', 'S3'],
        'unattributed_loss_resolutions' => [71, 'POOL', 'S1 / S3'],
        'unattributed_loss_resolution_lines' => [72, 'POOL × COMPANY', 'S3'],
        'lot_cost_entries' => [73, 'POOL × COMPANY', 'S3'],
        'lot_cost_balances' => [74, 'POOL × COMPANY', 'S3'],
        'cost_attributions' => [75, 'POOL × COMPANY', 'S3'],
        'purchase_charge_balances' => [76, 'COMPANY', 'S3'],
        'purchase_charge_share_balances' => [77, 'COMPANY', 'S3'],
        'expenses' => [78, 'COMPANY', 'S3'],
        'dropship_confirmations' => [79, 'PROJECT', 'S2'],
        'dropship_confirmation_lines' => [80, 'PROJECT', 'S2'],
        'deliveries' => [81, 'PROJECT', 'S2'],
        'delivery_lines' => [82, 'PROJECT', 'S2'],
        'delivery_closures' => [83, 'PROJECT', 'S2'],
        'service_handovers' => [84, 'PROJECT', 'S2'],
        'service_handover_lines' => [85, 'PROJECT', 'S2'],
        'document_types' => [86, 'GLOBAL', 'S0'],
        'documents' => [87, 'COMPANY / PROJECT', 'S2'],
        'document_versions' => [88, 'COMPANY', 'S2 / S3'],
        'document_renditions' => [89, 'COMPANY', 'as its version'],
        'file_objects' => [90, 'per owner record', 'S0–S4'],
        'evidence_documents' => [91, 'COMPANY / POOL', 'S4'],
        'evidence_document_versions' => [92, 'COMPANY / POOL', 'S4'],
        'bank_statement_lines' => [93, 'COMPANY', 'S3'],
        'evidence_balances' => [94, 'COMPANY', 'S3'],
        'evidence_links' => [95, 'COMPANY / POOL', 'S2'],
        'admin_requirements' => [96, 'PROJECT', 'S2'],
        'admin_requirement_links' => [97, 'PROJECT', 'S2'],
        'admin_requirement_waivers' => [98, 'PROJECT', 'S2'],
        'invoices' => [99, 'COMPANY', 'S3'],
        'invoice_versions' => [100, 'COMPANY', 'S3'],
        'invoice_version_lines' => [101, 'COMPANY', 'S3'],
        'invoice_tax_components' => [102, 'COMPANY', 'S3'],
        'invoice_balances' => [103, 'COMPANY', 'S3'],
        'invoice_billing_acts' => [104, 'COMPANY', 'S3'],
        'payments' => [105, 'COMPANY', 'S3'],
        'payment_claimed_deductions' => [106, 'COMPANY', 'S3'],
        'application_source_balances' => [107, 'COMPANY', 'S3'],
        'payment_applications' => [108, 'COMPANY', 'S3'],
        'deduction_settlements' => [109, 'COMPANY', 'S3'],
        'receivable_writeoffs' => [110, 'COMPANY', 'S3'],
        'receivable_dispute_holds' => [111, 'COMPANY', 'S3'],
        'opening_customer_credits' => [112, 'COMPANY', 'S3'],
        'prepayment_kuitansi_links' => [113, 'COMPANY', 'S3'],
        'disbursements' => [114, 'COMPANY', 'S3'],
        'other_cash_receipts' => [115, 'COMPANY', 'S3'],
        'financial_contras' => [116, 'COMPANY', 'S3'],
        'money_fact_balances' => [117, 'COMPANY', 'S3'],
        'audit_events' => [118, 'per audited entity', 'S0–S4'],
        'security_events' => [119, 'GLOBAL', 'S2'],
        'command_log' => [120, 'GLOBAL', '— (operational)'],
        'correction_cases' => [121, 'COMPANY', 'S2 / S3'],
        'import_batches' => [122, 'COMPANY set', 'S3'],
        'import_rows' => [123, 'COMPANY', 'S3'],
        'legacy_archive_items' => [124, 'COMPANY', 'S3'],
        'user_external_identities' => [125, 'GLOBAL', 'S2'],
    ];

    /**
     * The classes a table's own rows and columns carry; empty for `command_log`, every class for a table
     * classed per owning record, whose rows take the owner's.
     *
     * @return list<Sensitivity>
     */
    public static function classesOf(string $table): array
    {
        $class = self::TABLES[$table][2] ?? throw new InvalidArgumentException("{$table} is not a table of PERMISSIONS_MATRIX §7.2.");

        return match (true) {
            str_starts_with($class, '—') => [],
            $class === 'S0–S4', $class === 'as its version' => Sensitivity::cases(),
            default => array_map(fn (string $code): Sensitivity => Sensitivity::from(trim($code)), explode('/', $class)),
        };
    }

    /** Whether a table's rows take their class from the record that owns them (§7.2's "per owner" rows). */
    public static function followsOwner(string $table): bool
    {
        $class = self::TABLES[$table][2] ?? throw new InvalidArgumentException("{$table} is not a table of PERMISSIONS_MATRIX §7.2.");

        return $class === 'S0–S4' || $class === 'as its version';
    }
}
