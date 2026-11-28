// TL-03 (TASK-100; CR-002 K-5; the Owner's correction 4): every skeleton waits its 300 ms — the stage column of PG-010 included —, and no
// placeholder stands for a figure the account may not read: the stock values' skeleton is for an account with `cost.view` alone.
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const root = fileURLToPath(new URL('../../', import.meta.url));

function sources(directory: string): string[] {
    return readdirSync(join(root, directory)).flatMap((name) => {
        const path = join(directory, name);

        return statSync(join(root, path)).isDirectory() ? sources(path) : /\.tsx$/.test(name) ? [path] : [];
    });
}

const files = sources('resources/js').map((path) => ({ path: path.replaceAll('\\', '/'), text: readFileSync(join(root, path), 'utf8') }));

void test('K-5: a skeleton is drawn only after the 300 ms — every file that draws one waits for the delay, or uses the delayed skeleton', () => {
    const immediate = files
        .filter((file) => !file.path.endsWith('components/ui/skeleton.tsx') && /<Skeleton[\s/>]/.test(file.text) && !/useDelayed\(/.test(file.text))
        .map((file) => file.path);

    assert.deepEqual(immediate, [], 'a skeleton that appears at once');
});

void test('K-5: the stage column of PG-010 uses the delayed skeleton, one status line saying "Memuat tahap…" for all of them', () => {
    const projects = files.find((file) => file.path.endsWith('Pages/PRJ/Projects.tsx'))?.text ?? '';

    assert.match(projects, /<DelayedSkeleton /);
    assert.doesNotMatch(projects, /<Skeleton[\s/>]/);
    assert.match(projects, /role="status"/);
});

void test('K-5 and PJ-13: no placeholder for the stock values of an account without `cost.view`', () => {
    const stock = files.find((file) => file.path.endsWith('Pages/INV/Stock.tsx'))?.text ?? '';
    const deferred = /<Deferred data="values" fallback=\{([^}]*(?:\{[^}]*\}[^}]*)*)\}>/.exec(stock)?.[1] ?? '';

    assert.match(deferred, /capabilities\.includes\('cost\.view'\)/, 'the fallback depends on the capability');
    assert.match(deferred, /<ValuesLoading \/> : null/, 'and is nothing without it');
});
