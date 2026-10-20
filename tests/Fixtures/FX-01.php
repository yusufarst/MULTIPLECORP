<?php

// FX-01 (TEST_STRATEGY §4): the sixteen binding examples of BUSINESS_RULES' numeric annex, each with
// its text as written and the amounts and quantities it states — never re-derived (GAP-004). The Task
// that lands an example's rule asserts its calculator against it (TASK-003, acceptance criterion 3).
return [
    'id' => 'FX-01',
    'title' => 'The numeric annex\'s sixteen examples, each with its exact expected values',
    'source' => 'BUSINESS_RULES, numeric annex (GAP-004)',
    'used_by' => 'TL-03, TL-05; §5',
    'documents' => ['docs/02-domain/BUSINESS_RULES.md'],
    'entries' => [
        1 => [
            'title' => 'AC-10 baseline',
            'text' => 'Invoice Rp1,000,000 issued → sales value 1,000,000; BELUM DITAGIHKAN; no Cash-In; no active receivable. Billed (billed_at/due_date) → outstanding 1,000,000. Payments 300,000 then 700,000 → Cash-In each; outstanding 700,000 then 0.',
            'values' => [
                'invoice' => 1_000_000,
                'sales_value' => 1_000_000,
                'outstanding_when_billed' => 1_000_000,
                'payment_1' => 300_000,
                'payment_2' => 700_000,
                'outstanding_after_payment_1' => 700_000,
                'outstanding_after_payment_2' => 0,
            ],
        ],
        2 => [
            'title' => 'Advance',
            'text' => 'Payment 400,000 before billing, applied to the issued-unbilled invoice → Cash-In 400,000 once; billing later activates 600,000 only.',
            'values' => [
                'advance_payment' => 400_000,
                'cash_in' => 400_000,
                'activated_on_billing' => 600_000,
            ],
        ],
        3 => [
            'title' => 'Overpayment',
            'text' => 'Payment 1,200,000 against outstanding 1,000,000 → application 1,000,000; unapplied 200,000 = customer credit; refunding it = one application of 200,000 to a refund disbursement (Cash-Out); the same 200,000 can never also be applied to an invoice (BR-FIN-03).',
            'values' => [
                'payment' => 1_200_000,
                'outstanding' => 1_000_000,
                'application' => 1_000_000,
                'unapplied_credit' => 200_000,
                'refund_application' => 200_000,
            ],
        ],
        4 => [
            'title' => 'Reallocation',
            'text' => 'Application 400,000 moved from invoice A to invoice B = superseding application; payment total unchanged; both steps audited.',
            'values' => [
                'moved_application' => 400_000,
            ],
        ],
        5 => [
            'title' => 'Void after partial payment',
            'text' => 'Invoice billed 1,000,000, applied 400,000, then voided → void commits only with disposition of the 400,000 (credit or re-target); no orphaned money (BR-CR-03).',
            'values' => [
                'billed' => 1_000_000,
                'applied' => 400_000,
                'disposition' => 400_000,
            ],
        ],
        6 => [
            'title' => 'SIPLAH net settlement (DIR-019)',
            'text' => 'Gross 10,000,000; remitted 9,700,000; verified fee 300,000 → Cash-In 9,700,000 applied; fee-deduction settlement 300,000 closes the receivable and attributes 300,000 project expense once; project profit reflects revenue 10,000,000 − (other costs + 300,000); cashflow shows 9,700,000.',
            'values' => [
                'gross' => 10_000_000,
                'remitted' => 9_700_000,
                'verified_fee' => 300_000,
                'cash_in' => 9_700_000,
                'fee_settlement' => 300_000,
                'project_expense' => 300_000,
                'revenue' => 10_000_000,
                'cashflow' => 9_700_000,
            ],
        ],
        7 => [
            'title' => 'AC-22 stock',
            'text' => 'ON HAND 10, RESERVED 3, UNUSABLE 2 → AVAILABLE 5; confirmed reservation +2 → 10/5/2 → AVAILABLE 3; quotation alone changes nothing; dispatch of 5 reserved units → ON HAND 5, RESERVED 0, AVAILABLE 3 (one physical effect).',
            'values' => [
                'on_hand' => 10,
                'reserved' => 3,
                'unusable' => 2,
                'available' => 5,
                'reservation' => 2,
                'reserved_after_reservation' => 5,
                'available_after_reservation' => 3,
                'dispatched' => 5,
                'on_hand_after_dispatch' => 5,
                'reserved_after_dispatch' => 0,
                'available_after_dispatch' => 3,
            ],
        ],
        8 => [
            'title' => 'Reservation bound',
            'text' => 'ON HAND 10, UNUSABLE 2 → max reservable 8 (AVAILABLE ≥ 0); attempting 10 is rejected (BR-RSV-01).',
            'values' => [
                'on_hand' => 10,
                'unusable' => 2,
                'max_reservable' => 8,
                'rejected_reservation' => 10,
            ],
        ],
        9 => [
            'title' => 'Shrinkage',
            'text' => 'RESERVED 8, then opname loss of 2 usable units → same operation must cut chosen reservation(s) by 2 and flag the project(s) (BR-RSV-04).',
            'values' => [
                'reserved' => 8,
                'opname_loss' => 2,
                'reservation_cut' => 2,
            ],
        ],
        10 => [
            'title' => 'Multi-unit cost split',
            'text' => 'Buy 1 box = 12 pcs @ Rp100,000 → per-pc 8,333.33…; dispatch 5 pcs to X and 7 to Y → X: 41,667; Y: 58,333 (residual absorber); Σ = 100,000 exactly (BR-FIN-13).',
            'values' => [
                'box_pieces' => 12,
                'box_cost' => 100_000,
                'pieces_to_x' => 5,
                'pieces_to_y' => 7,
                'cost_x' => 41_667,
                'cost_y' => 58_333,
                'total' => 100_000,
            ],
        ],
        11 => [
            'title' => 'Ratio change',
            'text' => 'Demand "2 box" confirmed at ratio 12 → 24 base pinned; master ratio later 10 → fulfillment still compares in the pinned 24 base units (BR-INV-03).',
            'values' => [
                'demand_boxes' => 2,
                'confirmed_ratio' => 12,
                'pinned_base_units' => 24,
                'later_ratio' => 10,
            ],
        ],
        12 => [
            'title' => 'Inter-company attribution',
            'text' => 'A\'s lot 10 @ 12,000; B\'s project allocates 6 → allocation pins 6 × 12,000 to B\'s project HPP; A\'s purchase return afterwards limited to 4 (BR-INV-05/06).',
            'values' => [
                'lot_units' => 10,
                'unit_cost' => 12_000,
                'allocated_units' => 6,
                'return_limit' => 4,
            ],
        ],
        13 => [
            'title' => 'Tax settlement (`DIR-024` D-1; illustrative configured values, not a tax rule)',
            'text' => 'Sales value 10,000,000 + output-tax component 1,100,000 → billed 11,100,000; operating revenue 10,000,000. The treasurer transfers 9,850,000 with evidence of 1,100,000 tax collected and 150,000 tax withheld → Cash-In 9,850,000 applied; two tax settlements 1,100,000 and 150,000; outstanding 0; payment + settlements = 11,100,000; no settlement is revenue, payment or expense (BR-FIN-16/17).',
            'values' => [
                'sales_value' => 10_000_000,
                'output_tax' => 1_100_000,
                'billed' => 11_100_000,
                'operating_revenue' => 10_000_000,
                'transfer' => 9_850_000,
                'tax_collected' => 1_100_000,
                'tax_withheld' => 150_000,
                'cash_in' => 9_850_000,
                'outstanding' => 0,
                'payment_plus_settlements' => 11_100_000,
            ],
        ],
        14 => [
            'title' => 'Charge allocation (`DIR-024` D-2)',
            'text' => 'Lines A 10 pcs × 100,000 and B 30 pcs × 100,000; attributable freight 100,000 on a line-value basis → A 25,000, B 75,000 → both lots 102,500 per piece; a non-attributable 20,000 administration fee is expense only (BR-PUR-05).',
            'values' => [
                'line_a_pieces' => 10,
                'line_b_pieces' => 30,
                'unit_price' => 100_000,
                'freight' => 100_000,
                'freight_a' => 25_000,
                'freight_b' => 75_000,
                'lot_cost_per_piece' => 102_500,
                'administration_fee' => 20_000,
            ],
        ],
        15 => [
            'title' => 'Loss in transit (`DIR-024` D-3)',
            'text' => 'Two units dispatched to project X at 50,000 (HPP 100,000); one lost in transit → contra-attribution 50,000 + loss 50,000 on X; project cost stays 100,000, one unit delivered, nothing counted twice (BR-INV-13).',
            'values' => [
                'dispatched_units' => 2,
                'unit_cost' => 50_000,
                'hpp' => 100_000,
                'lost_units' => 1,
                'contra_attribution' => 50_000,
                'loss' => 50_000,
                'project_cost' => 100_000,
                'delivered_units' => 1,
            ],
        ],
        16 => [
            'title' => 'Client-damaged return (`DIR-024` D-3)',
            'text' => 'One delivered unit (lot cost 50,000) returns damaged → back into UNUSABLE on its lot with HPP contra 50,000 and an open case naming the project; on disposal the loss 50,000 is charged to that project once (BR-INV-13).',
            'values' => [
                'lot_cost' => 50_000,
                'hpp_contra' => 50_000,
                'loss_on_disposal' => 50_000,
            ],
        ],
    ],
];
