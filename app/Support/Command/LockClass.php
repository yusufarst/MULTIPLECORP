<?php

namespace App\Support\Command;

use InvalidArgumentException;

/**
 * The canonical lock order of CONCURRENCY_IDEMPOTENCY §4.2, LK-00–LK-31: every row lock of a transaction is
 * taken in ascending class position, then by the class's own table order, then by key (LR-01, LR-05). LK-00 is
 * the command's identity row, inserted first (CI-03); LK-04, LK-05 and LK-28 are taken by their own mechanisms
 * — the single-use token update, the session row and the advisory registration keys — never by a lock set.
 */
enum LockClass: int
{
    case CommandIdentity = 0;
    case Project = 1;
    case OwnerSet = 2;
    case User = 3;
    case CredentialToken = 4;
    case Session = 5;
    case Company = 6;
    case CompanyBankAccount = 7;
    case MasterConfiguration = 8;
    case Import = 9;
    case CorrectionCase = 10;
    case UnattributedLoss = 11;
    case StockCount = 12;
    case Product = 13;
    case Scope = 14;
    case Lot = 15;
    case Reversal = 16;
    case Serial = 17;
    case Reservation = 18;
    case ProjectLine = 19;
    case ProjectCommercial = 20;
    case Purchase = 21;
    case Charge = 22;
    case LotCost = 23;
    case Consumption = 24;
    case Invoice = 25;
    case ApplicationSource = 26;
    case MoneyFact = 27;
    case EvidenceRegistrationKeys = 28;
    case Evidence = 29;
    case Document = 30;
    case NumberCounter = 31;

    /**
     * The tables of the class in their canonical order within it (§4.2).
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return match ($this) {
            self::CommandIdentity => ['command_log'],
            self::Project => ['projects'],
            self::OwnerSet => ['roles'],
            self::User => ['users'],
            self::CredentialToken => ['credential_tokens'],
            self::Session => ['sessions'],
            self::Company => ['companies'],
            self::CompanyBankAccount => ['company_bank_accounts'],
            self::MasterConfiguration => ['client_organizations', 'client_units', 'client_addresses', 'client_contacts', 'suppliers', 'products', 'units', 'product_units', 'product_barcodes', 'product_images', 'locations', 'numbering_schemes', 'tax_types', 'tax_rate_versions', 'company_tax_profiles', 'tax_treatment_rules', 'document_types'],
            self::Import => ['import_batches', 'import_rows'],
            self::CorrectionCase => ['correction_cases'],
            self::UnattributedLoss => ['unattributed_loss_cases', 'unattributed_loss_exposures', 'unattributed_loss_group_exposures'],
            self::StockCount => ['stock_counts', 'stock_count_findings'],
            self::Product => ['stock_product_balances'],
            self::Scope => ['stock_scope_balances'],
            self::Lot => ['stock_lot_balances'],
            self::Reversal => ['stock_reversal_balances'],
            self::Serial => ['serial_units'],
            self::Reservation => ['reservations'],
            self::ProjectLine => ['project_line_balances'],
            self::ProjectCommercial => ['project_commercial_balances'],
            self::Purchase => ['purchases', 'purchase_lines', 'purchase_line_balances'],
            self::Charge => ['purchase_charge_balances', 'purchase_charge_share_balances'],
            self::LotCost => ['lot_cost_balances'],
            self::Consumption => ['stock_consumption_balances'],
            self::Invoice => ['invoices', 'invoice_versions', 'invoice_balances', 'receivable_dispute_holds', 'prepayment_kuitansi_links'],
            self::ApplicationSource => ['payments', 'opening_customer_credits', 'application_source_balances', 'payment_applications', 'deduction_settlements'],
            self::MoneyFact => ['money_fact_balances'],
            self::EvidenceRegistrationKeys => [],
            self::Evidence => ['evidence_documents', 'bank_statement_lines', 'evidence_balances', 'evidence_links'],
            self::Document => ['documents', 'document_versions', 'document_renditions'],
            self::NumberCounter => ['number_sequences'],
        };
    }

    /** Whether a lock set takes the class's rows; the others have mechanisms of their own (LR-02; FU-10). */
    public function isLockedBySet(): bool
    {
        return ! in_array($this, [self::CommandIdentity, self::CredentialToken, self::Session, self::EvidenceRegistrationKeys], true);
    }

    /**
     * The tables §4.2 orders by key kind before id, each kind one key column, in its sub-order: LK-24's
     * consumption then drop-ship line, LK-26's payment then credit, LK-27's five money facts, LK-29's evidence
     * then line.
     */
    private const KEY_KINDS = [
        'stock_consumption_balances' => ['stock_consumption_id', 'dropship_confirmation_line_id'],
        'application_source_balances' => ['payment_id', 'opening_customer_credit_id'],
        'money_fact_balances' => ['disbursement_id', 'other_cash_receipt_id', 'expense_id', 'deduction_settlement_id', 'receivable_writeoff_id'],
        'evidence_balances' => ['evidence_document_id', 'bank_statement_line_id'],
    ];

    /**
     * A key's kind within its table — its position in §4.2's sub-order —, 0 for a table keyed one way.
     *
     * @param  array<string, int|string>  $key
     */
    public static function kindOf(string $table, array $key): int
    {
        if (! isset(self::KEY_KINDS[$table])) {
            return 0;
        }

        $kind = count($key) === 1 ? array_search(array_key_first($key), self::KEY_KINDS[$table], true) : false;

        if ($kind === false) {
            throw new InvalidArgumentException("A {$table} row is keyed by one of its kinds: ".implode(', ', self::KEY_KINDS[$table]).' (§4.2).');
        }

        return $kind;
    }

    /** The class that holds a table's rows. */
    public static function of(string $table): self
    {
        foreach (self::cases() as $class) {
            if (in_array($table, $class->tables(), true)) {
                return $class;
            }
        }

        throw new InvalidArgumentException("{$table} holds no lock class of CONCURRENCY_IDEMPOTENCY §4.2.");
    }
}
