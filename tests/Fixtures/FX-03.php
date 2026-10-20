<?php

// FX-03 (TEST_STRATEGY §4): the eighteen scenarios of P3's final adversarial verification, each with
// its walk-through and end state as written; amounts in rupiah. Scenario 6's retention safeguard is
// superseded by DIR-027 (P3_QUALITY_GATE's post-approval clarification): a pending case blocks no
// dispatch. The Task that lands a scenario's commands runs it with real PostgreSQL (TL-05).
return [
    'id' => 'FX-03',
    'title' => 'The eighteen adversarial scenarios of P3\'s final verification, each with its walk-through and end state',
    'source' => 'P3_QUALITY_GATE',
    'used_by' => 'TL-05; §5',
    'documents' => ['docs/03-workflows/evidence/P3_QUALITY_GATE.md'],
    'entries' => [
        1 => [
            'title' => 'Refund then attempted reallocation',
            'walkthrough' => 'Payment 1,200,000: A applied 1,000,000; 200,000 credit refunded (disbursement D consumes application R). Moving R to invoice B → REJECTED (L-46). Σ applications = payment; Cash-In 1,200,000, Cash-Out 200,000; receivables settled 1,000,000 = net cash',
        ],
        2 => [
            'title' => 'Payment contra after refund',
            'walkthrough' => 'The payment was really 1,000,000: contra; A moves to the re-recorded 1,000,000; R cannot fit → unbacked-refund residual 200,000 blocks case closure and completion. Client returns 200,000 → new payment applied to D → residual 0. Cash-In 1,200,000, Cash-Out 200,000, settled 1,000,000',
        ],
        3 => [
            'title' => 'Cross-company damaged return then repair',
            'walkthrough' => 'A\'s lot (cost 50,000) dispatched to B\'s project X with allocation; damaged return restores the unit to UNUSABLE, HPP −50,000 and reverses the allocation (net 0); repair makes it usable on A\'s lot. ON HAND +1, UNUSABLE +1 then −1; allocation 0 = cross-company consumption 0',
        ],
        4 => [
            'title' => 'Cross-company damaged return then loss',
            'walkthrough' => 'From 3 before repair: disposal stocks out the unit with SF-LOSS 50,000 on X in one action and a new allocation on the loss consumption. X cost 50,000 once; allocation 1 unit/50,000 = consumption; ON HAND −1, UNUSABLE −1',
        ],
        5 => [
            'title' => 'Cross-company damaged return then supplier return',
            'walkthrough' => 'From 3 before repair: returned to the supplier with no allocation to unwind; refund 30,000 → purchase-value contra 30,000 and non-income Cash-In when received; unrecovered 20,000 → SF-LOSS on X with a cost-only allocation. Closure: 50,000 = 30,000 + 0 + 20,000',
        ],
        6 => [
            'title' => 'Unknown-company fungible loss',
            'walkthrough' => 'Scope: A\'s lot 5 @10,000, B\'s lot 5 @12,000; ON HAND 10, RESERVED 8. Count finds 8, no evidence → pending case: ON HAND 8, AVAILABLE 0, lots unchanged, no loss or company P&L. Each company retains 2 → 6 consumable, a 7th dispatch REJECTED and routed (QS-22). The Owner assigns 2 to B\'s lot → loss 24,000 on company B; the rest becomes consumable. Found-again alternative: a recount of 10 reverses the pending 2 exactly',
            'superseded' => 'DIR-027: a pending unexplained-loss case blocks no dispatch; physical truth is corrected at once (FX-05)',
        ],
        7 => [
            'title' => 'Wrong serial dispatch',
            'walkthrough' => 'S1 (A\'s lot, 100) recorded, S2 (B\'s lot, 110) shipped and delivered to A\'s project; count finds S1. CM-36: S1 back usable, S2 out, HPP −100 +110, new B→project allocation under grants; quantities and delivery unchanged',
        ],
        8 => [
            'title' => 'Erroneous restoration',
            'walkthrough' => 'A return of 2 recorded but the goods never came back: CM-37 re-establishes the consumption, HPP at the contra\'d amount, allocation and net delivered; cut if the units were reserved; invoice value restored by a replacement inheriting billing dates. Never SF-LOSS',
        ],
        9 => [
            'title' => 'Charge spanning multiple receipts',
            'walkthrough' => 'Lines A 10 and B 30 @100,000; freight 100,000 on value → A 25,000, B 75,000. Receipts A 10 (+25,000), B 20 (+50,000, 25,000 pending), B 10 (+25,000). Allocated + pending = 100,000 at every step',
        ],
        10 => [
            'title' => 'Purchase remainder closure',
            'walkthrough' => 'Closing B\'s last 10 instead re-spreads the pending 25,000 over received value: A +8,333, B +16,667 → 33,333 + 66,667 = 100,000; consumed units re-attributed in the same action (AX-34)',
        ],
        11 => [
            'title' => 'Duplicate source bill',
            'walkthrough' => 'Freight bill total 100,000 split 60,000 + 40,000 across two purchases; a further 100,000 charge or an expense citing it → REJECTED (Σ > total)',
        ],
        12 => [
            'title' => 'Invoice void with settlements',
            'walkthrough' => 'Annex 13 invoice (11,100,000; Cash-In 9,850,000; tax settlements 1,100,000 + 150,000) revised to 5,550,000 after a half return: VAT 550,000 and PPh 150,000 re-recorded, cash 4,850,000 re-targeted, 5,000,000 released to credit, VAT 550,000 kept as an explicit residual. Outstanding 0; Cash-In = applied + credit; settlements 1,250,000 = 700,000 + 550,000',
        ],
        13 => [
            'title' => 'Replacement invoice after partial payment',
            'walkthrough' => 'Billed 10,000,000 (billed 1 Aug, due 31 Aug), paid 4,000,000; void-and-replace with 9,000,000 re-targets 4,000,000; the replacement inherits both dates, outstanding 5,000,000 ages from 31 Aug. A void without replacement citing only "entry error" → REJECTED',
        ],
        14 => [
            'title' => 'Tax + fee on the same payment',
            'walkthrough' => 'Billed 11,100,000; remitted 9,550,000 with VAT 1,100,000, PPh 150,000 and platform fee 300,000 evidenced → three settlements, outstanding 0; payment + settlements = claimed gross; fee expense once; revenue 10,000,000; cashflow 9,550,000',
        ],
        15 => [
            'title' => 'Duplicate tax/fee evidence',
            'walkthrough' => 'The same withholding slip (issuer, number, date) uploaded again versions the evidence; a second settlement citing it exceeds the proven amount → REJECTED',
        ],
        16 => [
            'title' => 'Recovery after write-off',
            'walkthrough' => 'Billed 1,000,000, paid 600,000, 400,000 written off; the client later pays 400,000 → the Owner supersedes the write-off and applies it in one action: applications 1,000,000, disposals 0 with history kept; no refundable credit fabricated',
        ],
        17 => [
            'title' => 'Cancelled project receiving a late correction',
            'walkthrough' => 'CANCELLED project; a retained DP payment recorded 3,000,000 was really 2,500,000: CM-19 applies without a state change; 500,000 stays active and collectable; QS-16 shows it',
        ],
        18 => [
            'title' => 'Alternate-unit partial return',
            'walkthrough' => 'Box of 12 @100,000: X 5 pcs (41,667), Y 7 pcs (58,333). X returns 2 → contra 16,667 (proportional), X keeps 25,000; the lot re-issues 16,667 to Z; X\'s later return of 3 takes the remaining 25,000. Y 58,333 + Z 16,667 + lot 25,000 = 100,000',
        ],
    ],
];
