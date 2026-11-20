// The requirement-to-test traceability of the release (TASK-070, criterion 4; GAP-024; TEST_STRATEGY §5): every
// FEATURE_COVERAGE_MATRIX entry read with its Tasks or its disposition, sorted by its proof —
// - proven: every Task it maps to is DONE and a test of the suites names the entry (TEST_STRATEGY §5's naming of tests by
//   requirement), the suites passing in the run that carries this check;
// - named by no test: every Task is DONE with its completion evidence, but no test names the entry — a gap reported as
//   such, the entry resting on its Tasks' acceptance criteria and their evidence alone, never counted as proven;
// - open: a Task it maps to is not DONE, reported with that Task;
// - a disposition, checked against §3's list.
// It fails when it cannot judge: no test of the suites found, an entry without a Task or a known disposition, a Task the
// plan does not list, an entry twice, or a DONE Task whose contract lacks its checked test checklist or its completion
// evidence. A gap or an open entry is reported, never a failure: the release integrity report reads them.
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

/** Why a DONE Task's contract does not show its evidence, or null when it does. */
function missingEvidence(root, task) {
    const file = join(root, 'docs', '11-tasks', `${task}.md`);
    if (!existsSync(file)) return 'has no contract';
    const text = readFileSync(file, 'utf8');
    const between = (from, to) => text.slice(text.indexOf(from), to && text.indexOf(to) > text.indexOf(from) ? text.indexOf(to) : undefined);
    const checklist = between('**TEST CHECKLIST:**', '**COMPLETION EVIDENCE:**');
    const evidence = between('**COMPLETION EVIDENCE:**', '**SAFE NEXT TASK:**');
    if (!/^\*\*STATUS:\*\* DONE\b/m.test(text)) return 'is DONE in the plan but not in its contract';
    if (!checklist.includes('- [x]') || /^- \[ \]/m.test(checklist)) return 'has an unchecked test checklist';
    if (!/^- Test outputs/m.test(evidence) || evidence.includes('each required layer\'s result in')) return 'has no completion evidence of its tests';

    return null;
}

export function trace(root) {
    const matrix = readFileSync(join(root, 'docs', '11-tasks', 'FEATURE_COVERAGE_MATRIX.md'), 'utf8');
    const plan = readFileSync(join(root, 'docs', '11-tasks', 'TASK_PLAN.md'), 'utf8');
    const statuses = new Map([...plan.matchAll(/^\| \[(TASK-\d{3})\]\(TASK-\d{3}\.md\) \| [^|]* \| [^|]* \| [^|]* \| ([A-Z_]+)/gm)].map((m) => [m[1], m[2]]));
    const section = (from, to) => matrix.slice(matrix.indexOf(from), to ? matrix.indexOf(to) : undefined);
    const dispositions = new Set([...section('## 3. ', '## 4. ').matchAll(/^\| ([A-Z]+) \| /gm)].map((m) => m[1]).filter((name) => name !== 'Disposition'));
    const tests = testFiles(join(root, 'tests'));
    const citations = new Set(tests.flatMap((file) => [...cited(readFileSync(file, 'utf8'))]));
    const errors = tests.length === 0 ? ['no test of the suites found under tests/'] : [];
    const evidence = new Map();
    const seen = new Set();
    const result = { entries: 0, proven: 0, unnamed: 0, open: 0, dispositions: {}, openByTask: {}, unnamedByFamily: {} };

    for (const line of section('## 2. ', '## 3. ').split('\n')) {
        if (!line.startsWith('| ')) continue;
        const cells = line.split(' | ').map((cell) => cell.replace(/^\| |\s*\|$/g, '').trim());
        const [id, family, target] = [cells[0], cells[1], cells[cells.length - 1]];
        if (cells.length < 4 || id === 'Identifier' || id === '---') continue;
        result.entries++;
        if (seen.has(id)) errors.push(`${id}: entered twice`);
        seen.add(id);
        const tasks = [...target.matchAll(/TASK-\d{3}/g)].map((m) => m[0]);

        if (tasks.length === 0) {
            const disposition = /\b([A-Z]{4,})\b/.exec(target)?.[1];
            if (!disposition || !dispositions.has(disposition)) errors.push(`${id}: neither a Task nor a disposition of §3 (${target.slice(0, 40)})`);
            else result.dispositions[disposition] = (result.dispositions[disposition] ?? 0) + 1;
            continue;
        }

        const unknown = tasks.filter((task) => !statuses.has(task));
        if (unknown.length > 0) errors.push(`${id}: ${unknown.join(', ')} not in the plan`);
        const pending = tasks.filter((task) => statuses.get(task) !== 'DONE');

        if (pending.length > 0) {
            result.open++;
            for (const task of pending) result.openByTask[`${task} ${statuses.get(task) ?? '?'}`] = (result.openByTask[`${task} ${statuses.get(task) ?? '?'}`] ?? 0) + 1;
            continue;
        }

        for (const task of tasks) {
            if (!evidence.has(task)) evidence.set(task, missingEvidence(root, task));
        }

        if (citations.has(id)) {
            result.proven++;
        } else {
            result.unnamed++;
            result.unnamedByFamily[family] = (result.unnamedByFamily[family] ?? 0) + 1;
        }
    }

    for (const [task, reason] of evidence) {
        if (reason) errors.push(`${task} is DONE but ${reason}`);
    }

    return { result, errors };
}

function selfTest() {
    const scratch = mkdtempSync(join(tmpdir(), 'coverage-trace-'));
    const write = (path, text) => {
        mkdirSync(join(scratch, path, '..'), { recursive: true });
        writeFileSync(join(scratch, path), text);
    };
    const contract = (checked, evidenced) => `**STATUS:** DONE\n\n**TEST CHECKLIST:**\n\n- [${checked ? 'x' : ' '}] Lint\n\n**COMPLETION EVIDENCE:**\n\n- Test outputs: ${evidenced ? 'run 1 PASS' : 'each required layer\'s result in TEST_STRATEGY'}\n\n**SAFE NEXT TASK:**\n`;
    const expect = (what, actual, expected) => {
        if (JSON.stringify(actual) !== JSON.stringify(expected)) throw new Error(`${what}: ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)}`);
    };

    try {
        write('docs/11-tasks/TASK_PLAN.md', '| [TASK-001](TASK-001.md) | A | x | STANDARD | DONE | — |\n| [TASK-002](TASK-002.md) | B | x | STANDARD | BLOCKED — OPI-1 | — |\n| [TASK-003](TASK-003.md) | C | x | STANDARD | DONE | — |\n');
        write('docs/11-tasks/TASK-001.md', contract(true, true));
        write('docs/11-tasks/TASK-003.md', contract(true, false));
        write('docs/11-tasks/FEATURE_COVERAGE_MATRIX.md', [
            '## 2. The matrix', '| Identifier | Family | Source | Task or disposition |', '| --- | --- | --- | --- |',
            '| O-01 | O- | s | TASK-001 |', '| O-04 | O- | s | TASK-001 |', '| O-02 | O- | s | TASK-001, TASK-002 |', '| D-01 | D- | s | disposition DEFERRED |',
            '| X-09 | X- | s | NOWHERE |', '| O-03 | O- | s | TASK-009 |', '| O-05 | O- | s | TASK-003 |',
            '## 3. Non-Task dispositions', '| Disposition | Meaning | Entries |', '| --- | --- | --- |', '| DEFERRED | x | 1 |', '## 4. Entries per Task',
        ].join('\n'));
        write('tests/Feature/ATest.php', '<?php // O-01; X-01–X-03');

        const { result, errors } = trace(scratch);
        expect('a range cited', [...cited('X-01–X-03')], ['X-01', 'X-02', 'X-03']);
        expect('the counts', [result.entries, result.proven, result.unnamed, result.open, result.dispositions.DEFERRED], [7, 1, 2, 2, 1]);
        expect('the gaps', result.unnamedByFamily, { 'O-': 2 });
        expect('the open Tasks', result.openByTask, { 'TASK-002 BLOCKED': 1, 'TASK-009 ?': 1 });
        expect('the errors', errors, ['X-09: neither a Task nor a disposition of §3 (NOWHERE)', 'O-03: TASK-009 not in the plan', 'TASK-003 is DONE but has no completion evidence of its tests']);

        rmSync(join(scratch, 'tests'), { recursive: true, force: true });
        expect('no test found', trace(scratch).errors[0], 'no test of the suites found under tests/');
    } finally {
        rmSync(scratch, { recursive: true, force: true });
    }

    console.log('PASS: the coverage trace proves an entry only by a test naming it, reports the gaps, and fails without tests or evidence');
}

if (process.argv[2] === '--self-test') {
    selfTest();
} else {
    const { result, errors } = trace(resolve(process.argv[2] ?? '.'));

    console.log(`Entries ${result.entries}: proven by a test naming them ${result.proven}; named by no test, resting on their DONE Tasks' criteria and evidence ${result.unnamed}; open ${result.open}; dispositions ${JSON.stringify(result.dispositions)}`);
    console.log('Named by no test, by family:');
    for (const [family, count] of Object.entries(result.unnamedByFamily).sort((a, b) => b[1] - a[1])) console.log(`  ${family}: ${count}`);
    console.log('Open, by the Task not DONE yet:');
    for (const [task, count] of Object.entries(result.openByTask).sort()) console.log(`  ${task}: ${count}`);

    if (errors.length > 0) {
        console.error(errors.map((error) => `FAIL: ${error}`).join('\n'));
        process.exit(1);
    }

    console.log('PASS: every entry judged — proven, a gap named by no test, open with its Task, or a recorded disposition');
}
