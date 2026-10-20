<?php

// FX-04 (TEST_STRATEGY §4): the targeted-review fixtures DATABASE §31 hands to P8, each mapped to the
// scenarios of P4's final adversarial re-test that walk it through, as written; amounts in rupiah.
// Numbering with retired numbers has no walk-through there: its rule is P4's numbering conservation.
return [
    'id' => 'FX-04',
    'title' => 'The targeted-review fixtures of P4: price and charge correction between partial receipts, the Rp1 refund-shortfall attack, the −5/+6 location move, replacement versus normal receipt caps, overlapping unattributed-loss cases, replacement invoices after partial payment, residual settlements, alternate-type evidence, corrective receipts, drop-ship reversals, cross-company bank accounts, multi-project revalidation, numbering with retired numbers, guard rebuild after corrections and serial-state reconciliation including drop-shipped serials',
    'source' => 'DATABASE §31',
    'used_by' => 'TL-05; §5',
    'documents' => ['docs/04-architecture/DATABASE/s29-31-diagrams-traceability.md', 'docs/04-architecture/evidence/P4_QUALITY_GATE.md'],
    'entries' => [
        [
            'fixture' => 'price and charge correction between partial receipts',
            'scenarios' => [1, 2],
        ],
        [
            'fixture' => 'the Rp1 refund-shortfall attack',
            'scenarios' => [3],
        ],
        [
            'fixture' => 'the −5/+6 location move',
            'scenarios' => [4],
        ],
        [
            'fixture' => 'replacement versus normal receipt caps',
            'scenarios' => [5],
        ],
        [
            'fixture' => 'overlapping unattributed-loss cases',
            'scenarios' => [6],
        ],
        [
            'fixture' => 'replacement invoices after partial payment',
            'scenarios' => [7],
        ],
        [
            'fixture' => 'residual settlements',
            'scenarios' => [8],
        ],
        [
            'fixture' => 'alternate-type evidence',
            'scenarios' => [9],
        ],
        [
            'fixture' => 'corrective receipts',
            'scenarios' => [10],
        ],
        [
            'fixture' => 'drop-ship reversals',
            'scenarios' => [11],
        ],
        [
            'fixture' => 'cross-company bank accounts',
            'scenarios' => [12],
        ],
        [
            'fixture' => 'multi-project revalidation',
            'scenarios' => [13],
        ],
        [
            'fixture' => 'numbering with retired numbers',
            'scenarios' => [],
            'rule' => 'Numbering: unique per (company, type, period), monotonic, append-only, never reused, concurrency-safe, no `MAX+1`; retired project numbers are tombstoned; no gapless claim.',
        ],
        [
            'fixture' => 'guard rebuild after corrections',
            'scenarios' => [14],
        ],
        [
            'fixture' => 'serial-state reconciliation including drop-shipped serials',
            'scenarios' => [11, 14],
        ],
    ],
    'scenarios' => [
        1 => [
            'title' => 'Partial receipt → price correction → later receipt',
            'walkthrough' => 'A box of 12 for 100,000: receive 5 → GOODS 41,667; 2 dispatched (HPP 16,667). Price → 120,000: target(5) = 50,000 → COST_CORRECTION +8,333 on lot 1, dispatch share 3,333 (HPP 20,000), lot keeps 30,000; posted 50,000. Receive 7 → 70,000. Total 120,000 exactly',
        ],
        2 => [
            'title' => 'Partial receipt → charge correction → later receipt',
            'walkthrough' => 'Lines A 10 and B 30 @ 100,000; freight 100,000 on value (A 25,000, B 75,000); receipts A 10 (+25,000), B 20 (+50,000; B pending 25,000). The bill is really 120,000: its evidence is corrected first, then CHARGE_CORRECTION +20,000 — charge 120,000, bill cited 120,000 ≤ 120,000, new shares A 30,000, B 90,000, old postings contra\'d and re-posted (A 30,000; B 60,000; B pending 30,000). Receive B 10 → 30,000. Σ = 120,000; a correction above the bill\'s total without correcting the bill is REJECTED (C-20)',
        ],
        3 => [
            'title' => 'Payment → application → refund → Rp1 contra → reallocation',
            'walkthrough' => 'Payment 1,200,000; invoice application 1,000,000; refund 200,000. Contra Rp1: releasing the refund backing into a shortfall is REJECTED (a source with a shortfall must hold no invoice application and no capacity); the only committable disposition releases Rp1 of the invoice application. Capacity 0, so any further application of 199,999 is REJECTED. Cash-In 1,199,999 = settled 999,999 + refunded 200,000',
        ],
        4 => [
            'title' => 'Location move −5/+6',
            'walkthrough' => 'Deferred net-zero trigger: Σ per (lot, condition) = +1 → REJECTED at commit; −5/+5 commits',
        ],
        5 => [
            'title' => 'Replacement receipt versus normal receipt cap',
            'walkthrough' => 'Ordered 10, 8 received, 2 returned with replacement expected 2: a normal receipt of 4 → REJECTED (8 + 4 > 10); normal 2 → accepted; replacement 2 → accepted (≤ 2); a third replacement unit → REJECTED. GOODS posted = the line value at q = 10; replacement lots carry REPLACEMENT_BASIS',
        ],
        6 => [
            'title' => 'Overlapping unattributed-loss cases',
            'walkthrough' => 'A 5, B 5 claims; cases of 2, 3 and 2 (prior pending 0, 2, 5; CHECKs 2 ≤ 10, 3 ≤ 8, 2 ≤ 5); group bound A 5, B 5. Assigning 2 and 3 to A fills A\'s bound; assigning the third case to A → REJECTED (7 > 5); to B → accepted. A fourth case of 4 → REJECTED (4 > 10 − 7)',
        ],
        7 => [
            'title' => 'Replacement invoice after partial payment',
            'walkthrough' => 'Billed 10,000,000 (billed 1 Aug, due 31 Aug), paid 4,000,000; void-and-replace with 9,000,000 and an INHERITED root citing the current act → commits; 4,000,000 re-targeted; outstanding 5,000,000 ages from 31 Aug. A BILLING root dated 1 Oct, a missing act, or an act citing a superseded act → REJECTED at commit',
        ],
        8 => [
            'title' => 'Settlement ACTIVE → RESIDUAL → resolution',
            'walkthrough' => 'Fee 300,000 on invoice X; X voided for a return (typed case basis): ACTIVE → RESIDUAL — invoice and payment legs released, evidence still cited, fee expense kept, case residual 300,000 (no closure). The platform\'s corrected advice places the fee on invoice Y of the same payment: RESIDUAL → CONTRAED (citation and expense released) with a new ACTIVE settlement on Y (cited again, expense once); case residual 0 → closable. RESIDUAL → ACTIVE or a second exit → REJECTED',
        ],
        9 => [
            'title' => 'The same real evidence under an alternate type',
            'walkthrough' => 'Fee advice FA-77 (300,000) as FEE_ADVICE is cited in full; registered again as OTHER it gains a second identity row but no role accepts OTHER for fees → no second capacity; registered again as FEE_ADVICE it versions the existing evidence. The cross-type warning is P5',
        ],
        10 => [
            'title' => 'Corrective receiving under the same delivery reference',
            'walkthrough' => 'ORIGINAL receipt 8 under DN-100; CM-04 SUPPLEMENT 2 under DN-100 citing it → accepted; a second ORIGINAL under DN-100 → REJECTED; after reversing the original\'s lot, a RE_RECEIPT under DN-100 → accepted; a RE_RECEIPT citing an unreversed receipt → REJECTED',
        ],
        11 => [
            'title' => 'Drop-ship confirmation reversal and return after delivery',
            'walkthrough' => 'A confirmation of 5 (50,000) recorded in error → DROPSHIP_CONFIRMATION_REVERSAL without a movement: HPP −50,000, project and purchase confirmed −5, posted goods −50,000, serial NEVER_RECEIVED; a second reversal → REJECTED by the consumption cap; the reversal itself recorded in error → one exact DROPSHIP_ERROR_REVERSAL. Another confirmation of 5 is delivered (L-40); the client returns 2 straight to the supplier → DROPSHIP_SUPPLIER_RETURN with `after_delivery`: confirmed net 3 and delivered net 3 (the DROPSHIP CHECK holds), HPP of the 2 units moved to SUPPLIER_RETURN under a PURCHASE_RETURN case, settled by refund, replacement confirmation or unrecovered loss; no ledger entry',
        ],
        12 => [
            'title' => 'Cross-company bank-account selection',
            'walkthrough' => 'A Company B DOC-06 version naming Company A\'s account → REJECTED by the (company, account) key; a Company B payment likewise',
        ],
        13 => [
            'title' => 'Multi-project cost-correction revalidation',
            'walkthrough' => 'A lot consumed by completed projects P1 (3 units) and P2 (2 units); a correction of +5,000 on the lot re-attributes 3,000 and 2,000 and writes two REVALIDATE rows in one command under (`command_id`, `project_id`) → commits (previously the second row violated `command_id` UNIQUE)',
        ],
        14 => [
            'title' => 'Guard rebuild after corrections',
            'walkthrough' => 'The §3.1 formulas reproduce the live guards after scenarios 1, 3 and 6: posted goods 41,667 + 8,333 + 70,000 = 120,000; the payment\'s invoice applied 999,999, refund backed 200,000, shortfall 0, capacity 0; A\'s exposure and group rows 5 ≤ 5; drop-shipped serial states from their confirmation events. `GuardMaintenance` has nothing to write',
        ],
    ],
];
