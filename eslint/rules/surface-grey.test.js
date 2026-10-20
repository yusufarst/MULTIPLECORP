// GR-04's rule proven on fixtures, each violation detected: `npm run test:lint-rules`.
import { describe, it } from 'node:test';

import { RuleTester } from 'eslint';

import { surfaceGreys } from '../plugin.js';
import rule from './surface-grey.js';

RuleTester.describe = describe;
RuleTester.it = it;

const options = [surfaceGreys];

new RuleTester({
    languageOptions: { parserOptions: { ecmaFeatures: { jsx: true } } },
}).run('surface-grey', rule, {
    valid: [
        { code: '<div className="bg-canvas"><p className="text-text-muted-canvas">a</p></div>', options },
        { code: '<div className="bg-surface p-4"><p className="text-text-muted">a</p></div>', options },
        { code: '<p className="bg-surface md:text-text-muted">a</p>', options },
        { code: '<p className="text-text-muted">a</p>', options },
        { code: '<div className="bg-canvas"><p className="text-text-primary">a</p></div>', options },
    ],
    invalid: [
        {
            code: '<div className="bg-surface"><p className="text-text-muted-canvas">a</p></div>',
            options,
            errors: [{ messageId: 'wrongSurface' }],
        },
        {
            code: '<p className="bg-canvas text-text-muted">a</p>',
            options,
            errors: [{ messageId: 'wrongSurface' }],
        },
        {
            code: '<div className={cn("bg-canvas", active && "p-2")}><span className="hover:text-text-muted">a</span></div>',
            options,
            errors: [{ messageId: 'wrongSurface' }],
        },
        {
            code: '<div className="bg-[var(--color-surface)]"><p className={`text-[var(--color-text-muted-canvas)]`}>a</p></div>',
            options,
            errors: [{ messageId: 'wrongSurface' }],
        },
    ],
});
