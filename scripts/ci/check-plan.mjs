// The Task plan's integrity (TASK-002, acceptance criterion 7; RELEASE_PLAN §12.2; PMC-15):
// TASK_PLAN §1's counters equal to its §4 list, §3's checked boxes equal to the DONE Tasks, each
// contract's STATUS, DEPENDS ON and BLOCKS equal to its §4 row, every dependency mirrored, the graph
// acyclic, and the relative links and anchors of the Markdown files resolving.
// Usage: node scripts/ci/check-plan.mjs [repository root] | --self-test
import { cpSync, existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, relative, resolve } from 'node:path';

const STATUSES = ['DONE', 'READY', 'IN_PROGRESS', 'BLOCKED', 'VERIFYING', 'TODO', 'SUPERSEDED'];

function numbers(text) {
    return new Set(text.match(/\d{3}/g) ?? []);
}

function same(a, b) {
    return a.size === b.size && [...a].every((item) => b.has(item));
}

function section(text, start, end) {
    const from = text.indexOf(start);
    const to = text.indexOf(end, from + start.length);

    return from === -1 || to === -1 ? null : text.slice(from + start.length, to);
}

export function checkPlan(root) {
    const folder = join(root, 'docs', '11-tasks');
    const plan = readFileSync(join(folder, 'TASK_PLAN.md'), 'utf8');
    const errors = [];
    const rows = new Map();

    for (const line of plan.split('\n')) {
        const match = /^\| \[TASK-(\d{3})\]\(TASK-\d{3}\.md\) \| (.*)$/.exec(line);

        if (!match) {
            continue;
        }

        const cells = match[2].replace(/\s*\|\s*$/, '').split(' | ').map((cell) => cell.trim());

        rows.set(match[1], { status: cells[3], dep: numbers(cells[4]), blk: numbers(cells[5]) });
    }

    const counts = Object.fromEntries(STATUSES.map((status) => [status, 0]));

    for (const row of rows.values()) {
        counts[row.status.split(' ')[0]] = (counts[row.status.split(' ')[0]] ?? 0) + 1;
    }

    const block = /```text\n([\s\S]*?)```/.exec(plan)?.[1] ?? '';
    const counter = (name) => Number(new RegExp(`^${name}: (\\d+)$`, 'm').exec(block)?.[1] ?? NaN);

    for (const status of STATUSES) {
        if (counter(status) !== counts[status]) {
            errors.push(`§1 ${status}: ${counter(status)}, but §4 lists ${counts[status]}`);
        }
    }

    if (counter('Current Total Tasks') !== rows.size) {
        errors.push(`§1 Current Total Tasks: ${counter('Current Total Tasks')}, but §4 lists ${rows.size}`);
    }

    const progress = /^Progress:\n(\d+) \/ (\d+)\nPercentage: ([\d.]+) %$/m.exec(block);
    const percentage = rows.size === 0 ? '0.0' : ((counts.DONE / rows.size) * 100).toFixed(1);

    if (!progress || Number(progress[1]) !== counts.DONE || Number(progress[2]) !== rows.size || progress[3] !== percentage) {
        errors.push(`§1 progress is not ${counts.DONE} / ${rows.size}, ${percentage} %`);
    }

    const boxes = [...plan.matchAll(/^- \[( |x)\] \[TASK-(\d{3})\]/gm)];
    const checked = new Set(boxes.filter((box) => box[1] === 'x').map((box) => box[2]));
    const done = new Set([...rows].filter(([, row]) => row.status === 'DONE').map(([id]) => id));

    if (boxes.length !== rows.size) {
        errors.push(`§3 lists ${boxes.length} Tasks, §4 ${rows.size}`);
    }

    if (!same(checked, done)) {
        errors.push(`§3's checked boxes (${[...checked].join(', ') || 'none'}) differ from the DONE Tasks (${[...done].join(', ') || 'none'})`);
    }

    for (const [id, row] of rows) {
        const file = join(folder, `TASK-${id}.md`);

        if (!existsSync(file)) {
            errors.push(`TASK-${id}: no contract`);
            continue;
        }

        const contract = readFileSync(file, 'utf8');
        const status = /^\*\*STATUS:\*\* (.*)$/m.exec(contract)?.[1].replace('waiting on ', '');
        const depends = section(contract, '**DEPENDS ON:**', '**BLOCKS:**');
        const blocks = section(contract, '**BLOCKS:**', '**PARALLEL SAFE:**');

        if (status !== row.status) {
            errors.push(`TASK-${id}: STATUS "${status}" differs from §4's "${row.status}"`);
        }

        if (depends === null || !same(new Set([...depends.matchAll(/^- TASK-(\d{3})/gm)].map((m) => m[1])), row.dep)) {
            errors.push(`TASK-${id}: DEPENDS ON differs from §4`);
        }

        if (blocks === null || !same(new Set([...blocks.matchAll(/^- TASK-(\d{3})/gm)].map((m) => m[1])), row.blk)) {
            errors.push(`TASK-${id}: BLOCKS differs from §4`);
        }

        for (const other of row.dep) {
            if (!rows.get(other)?.blk.has(id)) {
                errors.push(`TASK-${id} depends on TASK-${other}, which does not list it in BLOCKS`);
            }
        }

        for (const other of row.blk) {
            if (!rows.get(other)?.dep.has(id)) {
                errors.push(`TASK-${id} blocks TASK-${other}, which does not list it in DEPENDS ON`);
            }
        }
    }

    const state = new Map();
    const visit = (id, path) => {
        if (state.get(id) === 'done') {
            return;
        }

        if (state.get(id) === 'open') {
            errors.push(`dependency cycle: ${[...path, id].map((item) => `TASK-${item}`).join(' → ')}`);
            return;
        }

        state.set(id, 'open');
        rows.get(id)?.dep.forEach((other) => visit(other, [...path, id]));
        state.set(id, 'done');
    };

    rows.forEach((_, id) => visit(id, []));

    return errors;
}

function markdownFiles(folder, files = []) {
    for (const entry of readdirSync(folder, { withFileTypes: true })) {
        if (['.git', 'node_modules', 'vendor', 'storage', 'public'].includes(entry.name)) {
            continue;
        }

        const path = join(folder, entry.name);

        if (entry.isDirectory()) {
            markdownFiles(path, files);
        } else if (entry.name.endsWith('.md')) {
            files.push(path);
        }
    }

    return files;
}

function slug(heading) {
    const text = heading
        .replace(/!\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/<[^>]+>/g, '')
        .replace(/[`*]/g, '')
        .trim()
        .toLowerCase();

    return [...text].filter((ch) => /[\p{L}\p{N}\p{M}\-_ ]/u.test(ch)).join('').replaceAll(' ', '-');
}

const anchorCache = new Map();

function anchors(file) {
    if (!anchorCache.has(file)) {
        const found = new Set();
        const seen = new Map();
        let fenced = false;
        const text = readFileSync(file, 'utf8');

        for (const line of text.split('\n')) {
            if (line.startsWith('```')) {
                fenced = !fenced;
                continue;
            }

            const heading = fenced ? null : /^(#{1,6})\s+(.*?)\s*#*\s*$/.exec(line);

            if (heading) {
                const base = slug(heading[2]);
                const count = seen.get(base) ?? 0;

                found.add(count === 0 ? base : `${base}-${count}`);
                seen.set(base, count + 1);
            }
        }

        for (const match of text.matchAll(/<a\s+(?:id|name)="([^"]+)"/g)) {
            found.add(match[1]);
        }

        anchorCache.set(file, found);
    }

    return anchorCache.get(file);
}

export function checkLinks(root) {
    const errors = [];

    for (const file of markdownFiles(root)) {
        const text = readFileSync(file, 'utf8').replace(/```[\s\S]*?```/g, '').replace(/`[^`\n]*`/g, '');

        for (const match of text.matchAll(/\[[^\]]*\]\(([^)\s]+)\)/g)) {
            const link = match[1];

            if (/^[a-z]+:/i.test(link)) {
                continue;
            }

            const [path, fragment] = link.split('#', 2);
            const target = path ? resolve(dirname(file), decodeURIComponent(path)) : file;

            if (!existsSync(target)) {
                errors.push(`${relative(root, file)}: ${link} — no such file`);
            } else if (fragment && target.endsWith('.md') && !anchors(target).has(fragment)) {
                errors.push(`${relative(root, file)}: ${link} — no such anchor`);
            }
        }
    }

    return errors;
}

function selfTest(root) {
    const scratch = mkdtempSync(join(tmpdir(), 'check-plan-'));

    try {
        cpSync(join(root, 'docs', '11-tasks'), join(scratch, 'docs', '11-tasks'), { recursive: true });

        const planFile = join(scratch, 'docs', '11-tasks', 'TASK_PLAN.md');
        const plan = readFileSync(planFile, 'utf8');
        writeFileSync(planFile, plan.replace(/^TODO: (\d+)$/m, (_, count) => `TODO: ${Number(count) + 1}`));

        if (checkPlan(scratch).length === 0) {
            throw new Error('a counter that disagrees with §4 was not detected');
        }

        writeFileSync(planFile, plan);
        const contract = join(scratch, 'docs', '11-tasks', 'TASK-003.md');
        writeFileSync(contract, readFileSync(contract, 'utf8').replace(/^\*\*STATUS:\*\* .*$/m, '**STATUS:** DONE'));

        if (checkPlan(scratch).length === 0) {
            throw new Error('a contract STATUS that disagrees with §4 was not detected');
        }

        const links = join(scratch, 'links');
        mkdirSync(links);
        writeFileSync(join(links, 'target.md'), '# TASK-001 — Repository scaffold\n');
        writeFileSync(join(links, 'source.md'), '[ok](target.md#task-001--repository-scaffold) [anchor](target.md#missing) [file](missing.md)\n');

        if (checkLinks(links).length !== 2) {
            throw new Error('a broken anchor and a missing file were not both detected');
        }
    } finally {
        rmSync(scratch, { recursive: true, force: true });
    }

    console.log('PASS: the plan and link checks detect their fixtures');
}

const root = resolve(process.argv[2] === '--self-test' ? '.' : (process.argv[2] ?? '.'));

if (process.argv[2] === '--self-test') {
    selfTest(root);
} else {
    const errors = [...checkPlan(root), ...checkLinks(root)];

    if (errors.length > 0) {
        console.error(errors.map((error) => `FAIL: ${error}`).join('\n'));
        process.exit(1);
    }

    console.log('PASS: the Task plan reconciles and every relative link and anchor resolves');
}
