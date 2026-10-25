// The token rule proven on fixtures, each violation detected: `npm run test:lint-rules`.
import { describe, it } from 'node:test';

import { RuleTester } from 'eslint';

import rule from './token-values.js';

RuleTester.describe = describe;
RuleTester.it = it;

new RuleTester({
    languageOptions: { parserOptions: { ecmaFeatures: { jsx: true } } },
}).run('token-values', rule, {
    valid: [
        { code: '<div className="p-4 gap-0.5 px-m-gutter md:px-gutter -mt-2 inset-x-0" />' },
        { code: '<div className="h-control-md w-32 max-w-page-form size-4.5 rounded-sm text-body" />' },
        { code: '<div className="z-(--z-modal) bottom-[calc(var(--m-action-bar-h)+var(--safe-bottom))]" />' },
        { code: '<div className="data-[phone=full]:top-(--safe-top) has-[[aria-invalid=true]]:border-danger-fg [&>button]:ring-1" />' },
        { code: '<div className="transition-[opacity,scale] [overflow-wrap:anywhere] rounded-[inherit]" />' },
        { code: "<div className={cn('p-3', active && 'gap-2', open ? 'mt-4' : 'mt-6')} />" },
        { code: "const variants = cva('px-4 py-2', { variants: { size: { sm: 'h-control-sm px-3' } } });" },
        { code: '<div style={{ height }} />' },
    ],
    invalid: [
        { code: '<div className="w-[13px]" />', errors: [{ messageId: 'arbitrary' }] },
        { code: '<div className="max-[400px]:hidden" />', errors: [{ messageId: 'arbitrary' }] },
        { code: '<div className="bg-[#fff] text-[14px]" />', errors: [{ messageId: 'arbitrary' }, { messageId: 'arbitrary' }] },
        { code: '<div className="max-h-[33vh]" />', errors: [{ messageId: 'arbitrary' }] },
        { code: '<div className="p-7" />', errors: [{ messageId: 'spacing' }] },
        { code: '<div className="md:gap-1.5 -mt-9" />', errors: [{ messageId: 'spacing' }, { messageId: 'spacing' }] },
        { code: "<div className={cn('pb-32', open && 'px-2.5')} />", errors: [{ messageId: 'spacing' }, { messageId: 'spacing' }] },
        { code: "const variants = cva('px-7', {});", errors: [{ messageId: 'spacing' }] },
        { code: "<div style={{ width: '13px' }} />", errors: [{ messageId: 'style' }] },
    ],
});
