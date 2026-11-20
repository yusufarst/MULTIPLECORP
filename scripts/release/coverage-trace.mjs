// The requirement-to-test traceability of the release (TASK-070, criterion 4; GAP-024; TEST_STRATEGY §5): every
// FEATURE_COVERAGE_MATRIX entry read with its Tasks or its disposition — an entry traced when every Task it maps to is
// DONE, a DONE Task having met its acceptance criteria with passing tests (DEFINITION_OF_DONE), and named "cited" when a
// test also cites the entry itself; an entry whose Task is not DONE yet reported open with that Task, never filled by
// assumption; a disposition checked against §3's list. It fails on a matrix it cannot read — an entry without a Task or
// a known disposition, a Task the plan does not list, an entry twice —, never on an open entry, which the release
// integrity report reads as such.
// Usage: node scripts/release/coverage-trace.mjs [repository root] | --self-test
import { existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const TOKEN = /\b([A-Z][A-Z0-9]*(?:-[A-Z][A-Z0-9]*)*)-(\d{1,3})(?:\s*–\s*(?:\1-)?(\d{1,3}))?\b/g;

/** Every identifier a text cites, a range expanded. */
export function cited(text) {
    const found = new Set();

    for (const [, family, from, to] of text.matchAll(TOKEN)) {
        const width = from.length;
        const last = to === undefined ? Number(from) : Number(to);

        for (let number = Number(from); number <= last && number - Number(from) < 400; number++) {
            found.add(`${family}-${String(number).padStart(width, '0')}`);
        }
    }

    return found;
}

function testFiles(directory) {
    const found = [];
    const walk = (path) => {
        for (const entry of existsSync(path) ? readdirSync(path) : []) {
            const full = join(path, entry);
            if (statSync(full).isDirectory()) walk(full);
            else if (/Test\.php$|\.spec\.ts$/.test(entry)) found.push(full);
        }
    };
    walk(directory);

    return found;
}

export function trace(root) {
    const matrix = readFileSync(join(root, 'docs', '11-tasks', 'FEATURE_COVERAGE_MATRIX.md'), 'utf8');
    const plan = readFileSync(join(root, 'docs', '11-tasks', 'TASK_PLAN.md'), 'utf8');
    const statuses = new Map([...plan.matchAll(/^\| \[(TASK-\d{3})\]\(TASK-\d{3}\.md\) \| [^|]* \| [^|]* \| [^|]* \| ([A-Z_]+)/gm)].map((m) => [m[1], m[2]]));
    const section = (from, to) => matrix.slice(matrix.indexOf(from), to ? matrix.indexOf(to) : undefined);
    const dispositions = new Set([...section('## 3. ', '## 4. ').matchAll(/^\| ([A-Z]+) \| /gm)].map((m) => m[1]).filter((name) => name !== 'Disposition'));
    const citations = new Set(testFiles(join(root, 'tests')).flatMap((file) => [...cited(readFileSync(file, 'utf8'))]));
    const errors = [];
    const seen = new Set();
    const result = { entries: 0, traced: 0, cited: 0, open: 0, dispositions: {}, openByTask: {} };

    for (const line of section('## 2. ', '## 3. ').split('\n')) {
        if (!line.startsWith('| ')) continue;
        const cells = line.split(' | ').map((cell) => cell.replace(/^\| |\s*\|$/g, '').trim());
        const [id, target] = [cells[0], cells[cells.length - 1]];
        if (cells.length < 4 || id === 'Identifier' || id === '---') continue;
        result.entries++;
        if (seen.has(id)) errors.push(`${id}: entered twice`);
        seen.add(id);
        const tasks = [...target.matchAll(/TASK-\d{3}/g)].map((m) => m[0]);

        if (tasks.length === 0) {
            const disposition = /\b([A-Z]{4,})\b/.exec(target)?.[1];
            if (!disposition || !dispositions.has(disposition)) errors.push(`${id}: neither a Task nor a disposition of §3 (${target.trim().slice(0, 40)})`);
            else result.dispositions[disposition] = (result.dispositions[disposition] ?? 0) + 1;
            continue;
        }

        const unknown = tasks.filter((task) => !statuses.has(task));
        if (unknown.length > 0) errors.push(`${id}: ${unknown.join(', ')} not in the plan`);
        const pending = tasks.filter((task) => statuses.get(task) !== 'DONE');

        if (pending.length === 0) {
            result.traced++;
            if (citations.has(id)) result.cited++;
        } else {
            result.open++;
            for (const task of pending) result.openByTask[`${task} ${statuses.get(task) ?? '?'}`] = (result.openByTask[`${task} ${statuses.get(task) ?? '?'}`] ?? 0) + 1;
        }
    }

    return { result, errors };
}

function selfTest() {
    const scratch = mkdtempSync(join(tmpdir(), 'coverage-trace-'));
    const write = (path, text) => {
        mkdirSync(join(scratch, path, '..'), { recursive: true });
        writeFileSync(join(scratch, path), text);
    };

    try {
        write('docs/11-tasks/TASK_PLAN.md', '| [TASK-001](TASK-001.md) | A | x | STANDARD | DONE | — |\n| [TASK-002](TASK-002.md) | B | x | STANDARD | BLOCKED — OPI-1 | — |\n');
        write('docs/11-tasks/FEATURE_COVERAGE_MATRIX.md', [
            '## 2. The matrix', '| Identifier | Family | Source | Task or disposition |', '| --- | --- | --- | --- |',
            '| O-01 | O- | s | TASK-001 |', '| O-02 | O- | s | TASK-001, TASK-002 |', '| D-01 | D- | s | DEFERRED — out of V1 |', '| X-09 | X- | s | NOWHERE |', '| O-03 | O- | s | TASK-009 |',
            '## 3. Non-Task dispositions', '| Disposition | Meaning | Entries |', '| --- | --- | --- |', '| DEFERRED | x | 1 |', '## 4. Entries per Task',
        ].join('\n'));
        write('tests/Feature/ATest.php', '<?php // O-01; X-01–X-03');
        const { result, errors } = trace(scratch);
        const expect = (what, actual, expected) => {
            if (JSON.stringify(actual) !== JSON.stringify(expected)) throw new Error(`${what}: ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)}`);
        };
        expect('a range cited', [...cited('X-01–X-03')], ['X-01', 'X-02', 'X-03']);
        expect('the counts', [result.entries, result.traced, result.cited, result.open, result.dispositions.DEFERRED], [5, 1, 1, 2, 1]);
        expect('the open Tasks', result.openByTask, { 'TASK-002 BLOCKED': 1, 'TASK-009 ?': 1 });
        expect('the errors', errors, ['X-09: neither a Task nor a disposition of §3 (NOWHERE)', 'O-03: TASK-009 not in the plan']);
    } finally {
        rmSync(scratch, { recursive: true, force: true });
    }

    console.log('PASS: the coverage trace reads entries, dispositions and Task statuses, and finds an unreadable entry');
}

if (process.argv[2] === '--self-test') {
    selfTest();
} else {
    const { result, errors } = trace(resolve(process.argv[2] ?? '.'));

    console.log(`Entries ${result.entries}: traced through DONE Tasks ${result.traced} (cited by a test themselves ${result.cited}); open ${result.open}; dispositions ${JSON.stringify(result.dispositions)}`);
    console.log('Open entries by the Task not DONE yet:');
    for (const [task, count] of Object.entries(result.openByTask).sort()) console.log(`  ${task}: ${count}`);

    if (errors.length > 0) {
        console.error(errors.map((error) => `FAIL: ${error}`).join('\n'));
        process.exit(1);
    }

    console.log('PASS: every entry maps to Tasks the plan lists or to a recorded disposition');
}
