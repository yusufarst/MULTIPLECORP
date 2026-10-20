<?php

// FX-02 (TEST_STRATEGY §4): the fictional group of INFRASTRUCTURE §3 — ARJ, BTN and CKP — with the
// shared stock pool and the accounts of SECURITY_TEST_MATRIX §2. Names are invented and e-mail
// addresses use a reserved example domain; passwords, keys, TOTP secrets and tokens are generated per
// run and never written here (STM §2). The masters and records of each company are created with their
// tables by the Tasks that build them.
return [
    'id' => 'FX-02',
    'title' => 'The synthetic group: ARJ, BTN and CKP with their masters, a shared stock pool and the accounts of STM §2',
    'source' => 'INFRASTRUCTURE §3; BACKUP_RECOVERY §6; STM §2',
    'used_by' => 'every suite',
    'documents' => ['docs/09-operations/INFRASTRUCTURE.md', 'docs/09-operations/BACKUP_RECOVERY.md', 'docs/08-testing/SECURITY_TEST_MATRIX.md'],
    'entries' => [
        'companies' => [
            'ARJ' => ['letter' => 'A', 'name' => 'PT Contoh Arunika Raya Jaya'],
            'BTN' => ['letter' => 'B', 'name' => 'CV Contoh Bentala Tirta Nusa'],
            'CKP' => ['letter' => 'C', 'name' => 'PT Contoh Cakrawala Karya Pratama'],
        ],
        'shared_pool' => [
            'companies' => ['ARJ', 'BTN'],
            'purpose' => 'lots of A and of B in one scope, so that pooled-view, allocation and unattributed-loss cases have a victim company',
        ],
        'accounts' => [
            'owner-1' => ['email' => 'owner-1@example.com', 'role' => 'OWNER', 'companies' => [], 'case' => 'the first of two Owners, for the last-Owner rule RG-09 and M-22'],
            'owner-2' => ['email' => 'owner-2@example.com', 'role' => 'OWNER', 'companies' => [], 'case' => 'the second Owner'],
            'admin-a' => ['email' => 'admin-a@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'Company A only, ADM capabilities including stock.view — AC-13\'s Admin operating against the shared pool'],
            'admin-ab' => ['email' => 'admin-ab@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ', 'BTN'], 'case' => 'Companies A and B, the positive twin of the isolation cases'],
            'admin-a-adm-plus' => ['email' => 'admin-a-adm-plus@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'Company A with ADM+ capabilities, high-risk and TOTP-enrolled'],
            'admin-a-no-stock-view' => ['email' => 'admin-a-no-stock-view@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'Company A without stock.view, for whom the pooled view is denied (AC-13)'],
            'admin-a-no-finance-view' => ['email' => 'admin-a-no-finance-view@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'Company A lacking finance.view (SM-25)'],
            'admin-a-no-cost-view' => ['email' => 'admin-a-no-cost-view@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'Company A lacking cost.view (SM-25)'],
            'admin-a-no-profit-view' => ['email' => 'admin-a-no-profit-view@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'Company A lacking profit.view (SM-25)'],
            'admin-a-no-evidence-view' => ['email' => 'admin-a-no-evidence-view@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'Company A lacking evidence.view (SM-25)'],
            'admin-a-no-projects-view' => ['email' => 'admin-a-no-projects-view@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'Company A lacking projects.view (SM-25)'],
            'admin-new' => ['email' => 'admin-new@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => [], 'case' => 'a new Admin with no grant (RG-04)'],
            'deactivated' => ['email' => 'deactivated@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'a deactivated account with an open session and a queued export'],
            'revoked-while-open' => ['email' => 'revoked-while-open@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'an account whose grant is revoked while its page is open'],
            'high-risk-no-totp' => ['email' => 'high-risk-no-totp@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'a high-risk account without TOTP in its enrolment-only session (AU-18)'],
            'google-linked' => ['email' => 'google-linked@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'an account with a linked Google identity and TOTP'],
            'google-unlinked' => ['email' => 'google-unlinked@example.com', 'role' => 'ADMIN_OPERASIONAL', 'companies' => ['ARJ'], 'case' => 'an unlinked Google identity'],
        ],
        // The operator has no user row (AU-14; RG-11), so it is no account here.
    ],
];
