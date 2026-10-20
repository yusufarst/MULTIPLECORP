<?php

// FX-05 (TEST_STRATEGY §4): DIR-027's unattributed-loss cases as DATABASE §31 hands them to P8, with
// the clarification they prove, as GAP-030 records it. Quantities, lots and companies are FX-02's,
// chosen by the Task that builds unattributed-loss recognition and resolution.
return [
    'id' => 'FX-05',
    'title' => "DIR-027's unattributed-loss cases: a pending case with dispatch continuing, an Owner resolution with and without substitution, an evidence resolution, stock found before and after resolution, an error reversal and a condition-change conversion",
    'source' => 'DATABASE §31; GAP-030',
    'used_by' => 'TL-05; §5',
    'documents' => ['docs/04-architecture/DATABASE/s29-31-diagrams-traceability.md', 'docs/00-governance/GAP_REGISTER.md'],
    'rule' => 'the per-company retention invariant is superseded — no stock is frozen while a case is pending; physical truth is corrected at once with an immutable recognition snapshot; definitive later evidence may resolve a case; the resolution closes lot claims (the assigned company\'s own first, any shortfall with an inter-company allocation) without re-pointing later movements; found stock is never recognized twice.',
    'entries' => [
        'pending-with-dispatch-continuing' => 'pending case with dispatch continuing',
        'owner-resolution-with-substitution' => 'Owner resolution with substitution',
        'owner-resolution-without-substitution' => 'Owner resolution without substitution',
        'evidence-resolution' => 'evidence resolution',
        'found-before-resolution' => 'found before resolution',
        'found-after-resolution' => 'found after resolution',
        'error-reversal' => 'error reversal',
        'condition-change-conversion' => 'condition-change conversion',
    ],
];
