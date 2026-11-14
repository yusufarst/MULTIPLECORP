// TL-03 (TASK-067; LOCALIZATION §5, §5.1; `DIR-081` OB-04; TEST_STRATEGY §10, §12): the per-page copy lists are
// generated, never edited — the generator's own rules hold on their fixtures, the lists are written from the string files,
// the added-keys registry, LOCALIZATION and the code into the test evidence area (storage/logs/verify/copy-lists, kept by CI
// as the verify-evidence artifact for the Owner's review at the UAT), and a stale list fails the check.
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const script = fileURLToPath(new URL('../../scripts/i18n/copy-lists.mjs', import.meta.url));
const run = (...args: string[]) => spawnSync(process.execPath, [script, ...args], { encoding: 'utf8' });
const output = (result: ReturnType<typeof run>): string => `${result.stdout}${result.stderr}`;

void test('the generator\'s rules hold on their fixtures — OB-04, the contract\'s counts, the code\'s keys, the check', () => {
    const result = run('--self-test');

    assert.equal(result.status, 0, output(result));
});

void test('writes every page\'s copy list into the test evidence area, current by construction', () => {
    const written = run();
    assert.equal(written.status, 0, output(written));
    const checked = run('--check');
    assert.equal(checked.status, 0, output(checked));
});

void test('a list edited by hand or left stale fails the check', () => {
    const folder = mkdtempSync(join(tmpdir(), 'copy-lists-'));

    try {
        const written = run('--out', folder);
        assert.equal(written.status, 0, output(written));
        assert.equal(run('--check', '--out', folder).status, 0);

        const page = join(folder, 'PG-014.md');
        writeFileSync(page, readFileSync(page, 'utf8').replace('## Proposed', '## Accepted'));
        const stale = run('--check', '--out', folder);

        assert.equal(stale.status, 1);
        assert.match(output(stale), /stale PG-014\.md/);
    } finally {
        rmSync(folder, { recursive: true, force: true });
    }
});
