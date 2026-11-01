// The aggregator of the required check `Browser — TL-09–TL-12 on Chromium and WebKit` (DIR-103): the four browser layers run
// on runners of their own, and the check passes only when the matrix's result is success and each layer's summary says PASS
// on this very commit. A layer failed, cancelled or skipped, a summary missing, EMPTY or of another commit: FAIL. On a pass
// the summaries are written together for the job's `browser-evidence`, which the release integrity report reads (RELEASE_PLAN §11).
//
//   node scripts/ci/check-browser-evidence.mjs --result <needs result> --commit <sha> --evidence <dir> --out <file>
//   node scripts/ci/check-browser-evidence.mjs --self-test
import { existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';

export const LAYERS = ['TL-09 E2E/browser', 'TL-10 Localization', 'TL-11 Responsive', 'TL-12 Accessibility'];

function summaries(dir) {
    if (!existsSync(dir)) return [];

    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) return summaries(path);

        return /^summary.*\.txt$/.test(entry) ? [path] : [];
    });
}

/** What keeps the check from passing, in words; none when every layer passed on this commit. */
export function problems(result, commit, dir) {
    if (result !== 'success') return [`the layers' result is ${result || 'missing'}, not success`];

    const lines = summaries(dir).flatMap((file) => readFileSync(file, 'utf8').split('\n'));

    return LAYERS.flatMap((layer) => {
        const found = lines.filter((line) => line.startsWith(`${layer}: `));

        if (found.length !== 1) return [`${layer}: ${found.length} summary lines, not one`];

        const match = /^[^:]+(?:\/[^:]+)?: (\S+) — Evidence: ([0-9a-f]{7,40}),/.exec(found[0]);

        if (match === null) return [`${layer}: no status and commit in "${found[0]}"`];
        if (match[1] !== 'PASS') return [`${layer}: ${match[1]}`];

        return commit.startsWith(match[2]) ? [] : [`${layer}: tested ${match[2]}, not ${commit}`];
    });
}

function merged(dir) {
    return `${summaries(dir).flatMap((file) => readFileSync(file, 'utf8').split('\n')).filter((line) => LAYERS.some((layer) => line.startsWith(`${layer}: `))).join('\n')}\n`;
}

function selfTest() {
    const commit = 'abcdef1234567890abcdef1234567890abcdef12';
    const root = mkdtempSync(join(tmpdir(), 'browser-evidence-'));
    const fixture = (lines) => {
        const dir = mkdtempSync(join(root, 'run-'));
        lines.forEach((line, index) => {
            mkdirSync(join(dir, `layer-${index}`, 'logs', 'verify'), { recursive: true });
            writeFileSync(join(dir, `layer-${index}`, 'logs', 'verify', `summary-browser-${index}.txt`), `Stack: PASS — Evidence: abcdef1, CI, command, log\n${line}\n`);
        });

        return dir;
    };
    const pass = (layer, sha = 'abcdef1') => `${layer}: PASS — Evidence: ${sha}, CI ubuntu-24.04 run 1, command, log`;
    const all = LAYERS.map((layer) => pass(layer));
    const expect = (name, found, wanted) => {
        if ((found.length === 0) !== wanted) throw new Error(`${name}: ${found.join('; ') || 'passed'}`);
    };

    expect('every layer passed on this commit', problems('success', commit, fixture(all)), true);
    for (const result of ['failure', 'cancelled', 'skipped', '']) expect(`a matrix ${result || 'with no result'}`, problems(result, commit, fixture(all)), false);
    expect('no evidence at all', problems('success', commit, join(root, 'none')), false);
    expect('a layer missing', problems('success', commit, fixture(all.slice(1))), false);
    expect('a layer twice', problems('success', commit, fixture([...all, all[0]])), false);
    expect('a layer failed', problems('success', commit, fixture([`${LAYERS[0]}: FAIL — Evidence: abcdef1, CI, command, log`, ...all.slice(1)])), false);
    expect('a layer empty', problems('success', commit, fixture([`${LAYERS[1]}: EMPTY — no test until TASK-062 — Evidence: abcdef1, CI, find, none`, all[0], ...all.slice(2)])), false);
    expect('a layer of another commit', problems('success', commit, fixture([pass(LAYERS[0], '1234567'), ...all.slice(1)])), false);

    const text = merged(fixture(all));
    if (LAYERS.some((layer) => !text.includes(`${layer}: PASS`)) || text.includes('Stack:')) throw new Error('the merged summary');

    rmSync(root, { recursive: true, force: true });
    console.log('PASS: the Browser check fails on a layer failed, cancelled, skipped, missing, empty or of another commit');
}

function argument(name) {
    const at = process.argv.indexOf(name);

    return at > -1 ? process.argv[at + 1] : undefined;
}

if (process.argv[1]?.endsWith('check-browser-evidence.mjs')) {
    if (process.argv.includes('--self-test')) {
        selfTest();
    } else {
        const dir = argument('--evidence');
        const commit = argument('--commit');
        const out = argument('--out');

        if (dir === undefined || commit === undefined || out === undefined) {
            console.error('usage: node scripts/ci/check-browser-evidence.mjs --result <result> --commit <sha> --evidence <dir> --out <file>');
            process.exit(2);
        }

        const found = problems(argument('--result') ?? '', commit, dir);

        if (found.length > 0) {
            found.forEach((problem) => { console.error(`FAIL: ${problem}`); });
            process.exit(1);
        }

        mkdirSync(dirname(out), { recursive: true });
        writeFileSync(out, merged(dir));
        console.log(`PASS: ${LAYERS.length} browser layers on ${commit}`);
    }
}
