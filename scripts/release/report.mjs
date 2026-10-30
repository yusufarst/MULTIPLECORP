// The release integrity report (RELEASE_PLAN §11; PRQ-11; REL-06; TASK-073): compiled by script from the evidence —
// the Task plan's counters, the CI runs' verification summaries (scripts/verify.sh writes one per layer set), TOOLCHAIN's
// rows, the release's declarations and the operator's staging summary entered without secrets (TEST_STRATEGY §16) —,
// never typed by hand. A section with a nonzero failure count is FAIL; a section with no run's evidence is NOT RUN,
// never PASS (GAP-017); RELEASE STATUS is PASS only when every section is.
//
//   node scripts/release/report.mjs --evidence <dir> [--staging <json>] [--declarations <json>] [--plan <md>]
//        [--toolchain <md>] [--commit <sha>] [--tag <tag>] [--out <md>]
//   node scripts/release/report.mjs --self-test
import { existsSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/** RELEASE_PLAN §11's sections, in its order. */
export const SECTIONS = ['TASKS', 'ROUTES', 'INTERACTIONS', 'CRITICAL JOURNEYS', 'UI/UX', 'BROWSER', 'TOOLCHAIN', 'COST', 'DATABASE', 'BUILD', 'SECURITY', 'PERFORMANCE', 'INTEGRITY'];

/** The verification checks, by the name verify.sh reports, each section reads. */
const CHECKS = {
    ROUTES: [/^TL-07 /],
    INTERACTIONS: [/^TL-08 /],
    'UI/UX': [/^TL-10 /, /^TL-11 /, /^TL-12 /],
    BUILD: [/^TL-13 /],
    SECURITY: [/^TL-06 /, /^TL-14 /],
};

/** Every `NAME: STATUS — Evidence: …` line of the summaries under a directory, the latest of a name winning. */
export function readSummaries(directory) {
    const checks = new Map();
    const walk = (path) => {
        for (const entry of existsSync(path) ? readdirSync(path) : []) {
            const full = join(path, entry);

            if (statSync(full).isDirectory()) walk(full);
            else if (/^summary.*\.txt$/.test(entry)) {
                for (const line of readFileSync(full, 'utf8').split('\n')) {
                    const match = /^(.+?): (PASS|FAIL|NOT RUN|EMPTY)[^—]*(?:— Evidence: (.*))?$/.exec(line.trim());
                    if (match) checks.set(match[1], { status: match[2], evidence: match[3] ?? '' });
                }
            }
        }
    };
    walk(directory);

    return checks;
}

/** The Task plan's counters (§1 of TASK_PLAN). */
export function readPlan(text) {
    const count = (label) => Number((new RegExp(`^${label}: (\\d+)`, 'm').exec(text) ?? [])[1] ?? NaN);

    return { total: count('Current Total Tasks'), done: count('DONE'), ready: count('READY'), inProgress: count('IN_PROGRESS'), blocked: count('BLOCKED'), verifying: count('VERIFYING'), todo: count('TODO') };
}

/** TOOLCHAIN's capability rows whose project status still reads "Not yet verified". */
export function readToolchain(text) {
    const rows = text.split('\n').filter((line) => /^\| [^-|]/.test(line) && !/^\| Capability \|/.test(line));
    const capabilities = rows.map((line) => line.split('|').map((cell) => cell.trim())).filter((cells) => cells.length >= 5 && /§5\.\d/.test(cells[1]));

    return { rows: capabilities.length, unverified: capabilities.filter((cells) => /^not yet verified/i.test(cells[3])).map((cells) => cells[1]) };
}

const notRun = (reason) => ({ status: 'NOT RUN', failures: null, detail: reason });
const counted = (failures, detail) => ({ status: failures === 0 ? 'PASS' : 'FAIL', failures, detail });

/** A section read from verification checks: each must have run and passed; one that never ran keeps the section NOT RUN. */
function fromChecks(checks, patterns) {
    const found = [...checks.entries()].filter(([name]) => patterns.some((pattern) => pattern.test(name)));
    const missing = patterns.filter((pattern) => !found.some(([name]) => pattern.test(name)));
    const notRunNames = found.filter(([, check]) => check.status === 'NOT RUN' || check.status === 'EMPTY').map(([name]) => name);
    const failed = found.filter(([, check]) => check.status === 'FAIL').map(([name]) => name);

    if (failed.length > 0) return counted(failed.length, `failed: ${failed.join('; ')}`);
    if (missing.length > 0 || notRunNames.length > 0 || found.length === 0) {
        return notRun(`no run's evidence for ${[...missing.map((pattern) => pattern.source.replace(/[\^\\]/g, '').trim()), ...notRunNames].join('; ')}`);
    }

    return counted(0, found.map(([name, check]) => `${name} — ${check.evidence}`).join('; '));
}

/** A staging section from the operator's summary: its counted failures, or NOT RUN without a summary. */
function fromStaging(staging, key, failuresOf, describe) {
    const part = staging?.[key];

    return part === undefined || part === null ? notRun('no staging summary for this section (RELEASE_PLAN REL-03, REL-04)') : counted(failuresOf(part), describe(part));
}

/** The report's sections from the evidence; RELEASE STATUS PASS only when every section is. */
export function compile({ plan, checks, toolchain, declarations, staging }) {
    const sections = {};
    const open = plan === null ? null : [plan.ready, plan.inProgress, plan.blocked, plan.verifying, plan.todo];

    sections.TASKS = plan === null || open.some((value) => Number.isNaN(value))
        ? notRun('no Task plan counters')
        : counted(open.reduce((sum, value) => sum + value, 0), `total ${plan.total}, done ${plan.done}; ready ${plan.ready}, in progress ${plan.inProgress}, blocked ${plan.blocked}, verifying ${plan.verifying}, todo ${plan.todo}`);
    sections.ROUTES = fromChecks(checks, CHECKS.ROUTES);
    sections.INTERACTIONS = fromChecks(checks, CHECKS.INTERACTIONS);
    sections['CRITICAL JOURNEYS'] = fromStaging(staging, 'critical_journeys', (part) => Number(part.failed ?? 0) + Math.max(0, Number(part.total ?? 0) - Number(part.passed ?? 0) - Number(part.failed ?? 0)),
        (part) => `CJ-01–CJ-22 at Layer 3: total ${part.total}, passed ${part.passed}, failed ${part.failed ?? 0}`);
    const browserUi = fromChecks(checks, CHECKS['UI/UX']);
    const stagingUi = staging?.ui_ux;
    sections['UI/UX'] = browserUi.status !== 'PASS' || stagingUi === undefined || stagingUi === null
        ? (browserUi.status === 'FAIL' ? browserUi : notRun(browserUi.status === 'PASS' ? 'no staging summary for this section (TEST_STRATEGY §§9–11 at Layer 3)' : browserUi.detail))
        : counted(Number(stagingUi.missing_translations ?? 0) + Number(stagingUi.redundant_critical_actions ?? 0) + Number(stagingUi.failed_checks ?? 0),
            `${browserUi.detail}; Layer 3: missing translations ${stagingUi.missing_translations ?? 0}, redundant critical actions ${stagingUi.redundant_critical_actions ?? 0}, failed checks ${stagingUi.failed_checks ?? 0}`);
    sections.BROWSER = fromStaging(staging, 'browser', (part) => Number(part.console_errors ?? 0) + Number(part.failed_requests ?? 0),
        (part) => `critical console errors ${part.console_errors ?? 0}; critical failed requests ${part.failed_requests ?? 0}`);
    sections.TOOLCHAIN = toolchain === null || toolchain.rows === 0
        ? notRun('no TOOLCHAIN rows read')
        : counted(toolchain.unverified.length, toolchain.unverified.length === 0 ? `${toolchain.rows} capabilities verified` : `not yet verified: ${toolchain.unverified.join('; ')}`);
    sections.COST = declarations?.cost === undefined
        ? notRun('no cost declaration (RL-03)')
        : counted(declarations.cost === 'NONE' || (declarations.cost === 'APPROVED EXCEPTION' && Boolean(declarations.cost_approval)) ? 0 : 1,
            `ADDITIONAL RECURRING COST CHANGE: ${declarations.cost}${declarations.cost_approval ? ` (${declarations.cost_approval})` : ''}`);
    sections.DATABASE = declarations?.db === undefined
        ? notRun('no database declaration (RL-03)')
        : counted((['NONE', 'APPROVED CONTROLLED MIGRATION'].includes(declarations.db) ? 0 : 1) + (declarations.agent_production_db_mutation === 'NO' ? 0 : 1),
            `DB CHANGE: ${declarations.db}; direct production DB mutation by a coding agent: ${declarations.agent_production_db_mutation ?? 'not declared'}`);
    sections.BUILD = fromChecks(checks, CHECKS.BUILD);
    sections.SECURITY = fromChecks(checks, CHECKS.SECURITY);
    sections.PERFORMANCE = fromStaging(staging, 'performance', (part) => Number(part.outside_target ?? 0) + Math.max(0, Number(part.cases ?? 0) - Number(part.within_target ?? 0) - Number(part.outside_target ?? 0)),
        (part) => `PERFORMANCE_TARGETS §4's release-critical set: ${part.within_target ?? 0} of ${part.cases ?? 0} within target`);
    sections.INTEGRITY = fromStaging(staging, 'integrity', (part) => Number(part.differences ?? 0), (part) => `ReconcileGuards after Layer 3: ${part.differences ?? 0} differences`);

    return { sections, status: SECTIONS.every((name) => sections[name].status === 'PASS') ? 'PASS' : 'FAIL' };
}

/** The report as the release record keeps it: plain text, no secret, host detail or real data (RL-05; SX-06). */
export function render({ sections, status }, { commit = 'unknown', tag = 'untagged', generated = new Date().toISOString() } = {}) {
    const rows = SECTIONS.map((name) => `| ${name} | ${sections[name].status} | ${sections[name].failures ?? '—'} | ${sections[name].detail.replaceAll('|', '/')} |`);

    return [
        `# Release integrity report — ${tag}`,
        '',
        `Commit: \`${commit}\` · compiled ${generated} by scripts/release/report.mjs from the evidence (RELEASE_PLAN §11; PRQ-11)`,
        '',
        '| Section | Status | Failures | Evidence |',
        '| --- | --- | --- | --- |',
        ...rows,
        '',
        `RELEASE STATUS: ${status}`,
        '',
    ].join('\n');
}

function argument(name) {
    const at = process.argv.indexOf(name);

    return at >= 0 ? process.argv[at + 1] : undefined;
}

function json(path) {
    return path !== undefined && existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : null;
}

function selfTest() {
    const root = mkdtempSync(join(tmpdir(), 'release-report-'));
    const plan = 'Current Total Tasks: 3\n\nDONE: 3\nREADY: 0\nIN_PROGRESS: 0\nBLOCKED: 0\nVERIFYING: 0\nTODO: 0\n';
    const toolchain = '| Capability | Preferred tool | Project status | Verified by |\n| --- | --- | --- | --- |\n| Browser E2E (§5.3) | Playwright | Verified by TASK-003 | TASK-003 |\n';
    const lines = ['TL-06 Authorization', 'TL-07 Route integrity', 'TL-08 Interaction', 'TL-10 Localization', 'TL-11 Responsive', 'TL-12 Accessibility', 'TL-13 Frontend build', 'TL-14 Secret-like scan (SX-06)'];
    const summary = (status) => lines.map((name) => `${name}: ${status(name)} — Evidence: abc1234, CI ubuntu-24.04 run 1, command, log`).join('\n');
    const staging = { critical_journeys: { total: 22, passed: 22, failed: 0 }, ui_ux: { missing_translations: 0, redundant_critical_actions: 0, failed_checks: 0 },
        browser: { console_errors: 0, failed_requests: 0 }, performance: { cases: 5, within_target: 5, outside_target: 0 }, integrity: { differences: 0 } };
    const declarations = { db: 'NONE', cost: 'NONE', agent_production_db_mutation: 'NO' };
    const run = (status, stagingSummary, planText = plan) => {
        const dir = mkdtempSync(join(root, 'e-'));
        writeFileSync(join(dir, 'summary-local.txt'), summary(status));

        return compile({ plan: readPlan(planText), checks: readSummaries(dir), toolchain: readToolchain(toolchain), declarations, staging: stagingSummary });
    };
    const failures = [];
    const expect = (label, actual, wanted) => { if (actual !== wanted) failures.push(`${label}: ${actual} ≠ ${wanted}`); };

    const pass = run(() => 'PASS', staging);
    expect('every section passing', pass.status, 'PASS');
    SECTIONS.forEach((name) => expect(`section ${name} passing`, pass.sections[name].status, 'PASS'));

    const failed = run((name) => (name.startsWith('TL-07') ? 'FAIL' : 'PASS'), staging);
    expect('a failed route check', failed.sections.ROUTES.status, 'FAIL');
    expect('its failure count', failed.sections.ROUTES.failures, 1);
    expect('the release with one failure', failed.status, 'FAIL');

    const journeys = run(() => 'PASS', { ...staging, critical_journeys: { total: 22, passed: 21, failed: 1 } });
    expect('a failed journey', journeys.sections['CRITICAL JOURNEYS'].status, 'FAIL');

    const missing = run(() => 'PASS', null);
    expect('no staging summary', missing.sections['CRITICAL JOURNEYS'].status, 'NOT RUN');
    expect('no staging summary for the integrity', missing.sections.INTEGRITY.status, 'NOT RUN');
    expect('a release with a section not run', missing.status, 'FAIL');

    const empty = run((name) => (name.startsWith('TL-08') ? 'EMPTY — no test until TASK-x' : 'PASS'), staging);
    expect('a layer with no test is no run', empty.sections.INTERACTIONS.status, 'NOT RUN');

    const open = run(() => 'PASS', staging, plan.replace('TODO: 0', 'TODO: 2'));
    expect('tasks still open', open.sections.TASKS.status, 'FAIL');
    expect('their count', open.sections.TASKS.failures, 2);

    const absent = compile({ plan: readPlan(plan), checks: new Map(), toolchain: readToolchain(toolchain), declarations: null, staging });
    expect('no verification evidence at all', absent.sections.BUILD.status, 'NOT RUN');
    expect('no declarations', absent.sections.DATABASE.status, 'NOT RUN');

    const text = render(pass, { commit: 'abc1234', tag: 'v2026.10.30-1', generated: '2026-10-30T00:00:00Z' });
    SECTIONS.forEach((name) => expect(`the rendered ${name} row`, text.includes(`| ${name} | PASS |`), true));
    expect('the rendered release status', text.includes('RELEASE STATUS: PASS'), true);

    rmSync(root, { recursive: true, force: true });

    if (failures.length > 0) {
        console.error(`FAIL: ${failures.join('\n')}`);
        process.exit(1);
    }

    console.log(`PASS: the report's section rules hold on fixture evidence (${SECTIONS.length} sections; a pass, a failure, a missing run)`);
}

if (process.argv.includes('--self-test')) {
    selfTest();
} else if (import.meta.url === `file://${process.argv[1]}` || process.argv[1]?.endsWith('report.mjs')) {
    const evidence = argument('--evidence');

    if (evidence === undefined) {
        console.error('usage: node scripts/release/report.mjs --evidence <dir> [--staging <json>] [--declarations <json>] [--plan <md>] [--toolchain <md>] [--commit <sha>] [--tag <tag>] [--out <md>]');
        process.exit(2);
    }

    const planPath = argument('--plan') ?? 'docs/11-tasks/TASK_PLAN.md';
    const toolchainPath = argument('--toolchain') ?? 'docs/00-governance/TOOLCHAIN.md';
    const report = compile({
        plan: existsSync(planPath) ? readPlan(readFileSync(planPath, 'utf8')) : null,
        checks: readSummaries(evidence),
        toolchain: existsSync(toolchainPath) ? readToolchain(readFileSync(toolchainPath, 'utf8')) : null,
        declarations: json(argument('--declarations')),
        staging: json(argument('--staging')),
    });
    const text = render(report, { commit: argument('--commit'), tag: argument('--tag') });
    const out = argument('--out');

    if (out !== undefined) writeFileSync(out, text);
    process.stdout.write(text);
    // The report is written whatever it says; its exit names the status for the workflow's log.
    process.exitCode = report.status === 'PASS' ? 0 : 3;
}
