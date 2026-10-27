import { defineConfig, devices } from '@playwright/test';

// Each layer keeps its own failures' screenshots and traces: scripts/verify.sh runs E2E, Localization, Responsive and
// Accessibility one after the other, and one shared folder, cleaned at every start, would hold only the last layer's.
const layer = process.argv.map((argument) => /tests\/Browser\/(\w+)/.exec(argument)?.[1]).find((name) => name !== undefined) ?? 'all';

// The browser suite of TL-09–TL-12 (TEST_STRATEGY §7, §17; TASK-003): Chromium and WebKit against the
// Docker Compose stack over HTTPS, in the pinned Playwright image (`docker compose run --rm e2e`). A
// failing test is never retried into a pass (TEST_STRATEGY §16).
export default defineConfig({
    testDir: '.',
    testMatch: '**/*.spec.ts',
    forbidOnly: true,
    retries: 0,
    outputDir: `../../storage/test-results/browser/artifacts/${layer}`,
    reporter: [['list'], ['junit', { outputFile: '../../storage/test-results/browser/junit.xml' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'https://web:8443',
        // The stack's certificate is issued by its throwaway local authority (docker/certs), never staging's.
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
});
