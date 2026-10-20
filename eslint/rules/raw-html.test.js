// WS-09's raw HTML restriction of eslint.config.js proven on fixtures: `npm run test:lint-rules`.
import { describe, it } from 'node:test';

import { RuleTester } from 'eslint';
import { builtinRules } from 'eslint/use-at-your-own-risk';

import { rawHtml } from '../plugin.js';

RuleTester.describe = describe;
RuleTester.it = it;

new RuleTester({
    languageOptions: { parserOptions: { ecmaFeatures: { jsx: true } } },
}).run('no-restricted-syntax', builtinRules.get('no-restricted-syntax'), {
    valid: [{ code: '<p>{text}</p>', options: [rawHtml] }],
    invalid: [
        {
            code: '<div dangerouslySetInnerHTML={{ __html: text }} />',
            options: [rawHtml],
            errors: [{ message: 'Raw HTML is prohibited (SECURITY WS-09).' }],
        },
    ],
});
