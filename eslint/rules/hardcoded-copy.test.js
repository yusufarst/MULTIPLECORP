// LOCALIZATION §3.6's rule proven on fixtures, each hardcoded string detected (TASK-014; acceptance criterion 2):
// `npm run test:lint-rules`.
import { describe, it } from 'node:test';

import { RuleTester } from 'eslint';

import rule from './hardcoded-copy.js';

RuleTester.describe = describe;
RuleTester.it = it;

const errors = [{ messageId: 'hardcoded' }];

new RuleTester({
    languageOptions: { parserOptions: { ecmaFeatures: { jsx: true } } },
}).run('hardcoded-copy', rule, {
    valid: [
        { code: "<h1>{t('pages.pg_001.heading')}</h1>" },
        { code: "<div aria-label={t('glossary.gl_198')}><span aria-hidden=\"true\">|</span></div>" },
        { code: '<p>{count} · {total}</p>' },
        { code: '<button type="button" className="px-2" lang="en" data-copy="pages.pg_001.submit">{label}</button>' },
        { code: '<input name="email" type="email" autoComplete="email" />' },
        { code: '<p>{`${String(count)}`}</p>' },
    ],
    invalid: [
        { code: '<h1>Masuk ke MultipleCorp</h1>', errors },
        { code: '<button>Simpan</button>', errors },
        { code: "<p>{'Sign in'}</p>", errors },
        { code: '<p>{`Total ${String(count)} baris`}</p>', errors },
        { code: '<input placeholder="Cari" />', errors },
        { code: '<img alt="Logo" src="x.png" />', errors },
        { code: "<div aria-label={'Bahasa tampilan'} />", errors },
        { code: '<abbr title="Pajak Pertambahan Nilai">PPN</abbr>', errors: [...errors, ...errors] },
    ],
});
