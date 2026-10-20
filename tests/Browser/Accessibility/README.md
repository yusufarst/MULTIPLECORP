# TL-12 — Accessibility critical checks

[TEST_STRATEGY §11](../../../docs/08-testing/TEST_STRATEGY.md#11-accessibility)'s automated checks against WCAG 2.2 AA, run by axe-core inside the browser suite on Chromium and WebKit. Until the first page exists, `engine.spec.ts` checks the one page the stack serves, the framework's "not found" page — a proof that the engine is available, not of the application's accessibility (APPR-017); TASK-066 covers every page. `scripts/verify.sh browser` runs every `*.spec.ts` here.
