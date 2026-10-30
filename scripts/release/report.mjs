// The release integrity report (RELEASE_PLAN §11; PRQ-11; REL-06; TASK-073): compiled by script from the evidence —
// the Task plan's counters, the CI runs' verification summaries (scripts/verify.sh writes one per layer set), TOOLCHAIN's
// rows, the release's declarations and the operator's staging summary entered without secrets (TEST_STRATEGY §16) —,
// never typed by hand. A section with a nonzero failure count is FAIL; a section with no run's evidence — a check
// whose run tested another commit than `--commit` included — is NOT RUN, never PASS (GAP-017); RELEASE STATUS is PASS
// only when every section is.
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

/**
 * Every `NAME: STATUS — Evidence: <commit>, …` line of the summaries under a directory, the latest of a name winning,
 * with the commit its run tested.
 */
export function readSummaries(directory) {
    const checks = new Map();
    const walk = (path) => {
        for (const entry of existsSync(path) ? readdirSync(path) : []) {
            const full = join(path, entry);

            if (statSync(full).isDirectory()) walk(full);
            else if (/^summary.*\.txt$/.test(entry)) {
                for (const line of readFileSync(full, 'utf8').split('\n')) {
                    const match = /^(.+?): (PASS|FAIL|NOT RUN|EMPTY)[^—]*(?:— Evidence: (.*))?$/.exec(line.trim());
                    if (match) checks.set(match[1], { status: match[2], evidence: match[3] ?? '', commit: (/^([0-9a-f]{7,40}),/.exec(match[3] ?? '') ?? [])[1] ?? null });
                }
            }
        }
    };
    walk(directory);

    return checks;
}

/** The Task plan's counters (§1 of TASK_PLAN) and each Task's status from its table. */
export function readPlan(text) {
    const count = (label) => Number((new RegExp(`^${label}: (\\d+)`, 'm').exec(text) ?? [])[1] ?? NaN);
    const lines = text.split('\n');
    const header = lines.findIndex((line) => /^\| Task \| Title \|/.test(line));
    const column = header < 0 ? -1 : lines[header].split('|').map((cell) => cell.trim()).indexOf('Status');
    const statuses = new Map();

    for (let i = header + 2; header >= 0 && column > 0 && i < lines.length && lines[i].startsWith('|'); i++) {
        const cells = lines[i].split('|').map((cell) => cell.trim());
        const task = /^\[(TASK-\d{3})\]/.exec(cells[1] ?? '');
        if (task) statuses.set(task[1], cells[column]);
    }

    return { total: count('Current Total Tasks'), done: count('DONE'), ready: count('READY'), inProgress: count('IN_PROGRESS'), blocked: count('BLOCKED'), verifying: count('VERIFYING'), todo: count('TODO'), statuses };
}

/** TOOLCHAIN's capability rows whose project status still reads "Not yet verified". */
export function readToolchain(text) {
    const rows = text.split('\n').filter((line) => /^\| [^-|]/.test(line) && !/^\| Capability \|/.test(line));
    const capabilities = rows.map((line) => line.split('|').map((cell) => cell.trim())).filter((cells) => cells.length >= 5 && /§5\.\d/.test(cells[1]));

    return { rows: capabilities.length, unverified: capabilities.filter((cells) => /^not yet verified/i.test(cells[3])).map((cells) => cells[1]) };
}

const notRun = (reason) => ({ status: 'NOT RUN', failures: null, detail: reason });
const counted = (failures, detail) => ({ status: failures === 0 ? 'PASS' : 'FAIL', failures, detail });

/** The same commit: one hash a prefix of the other, at least seven hex digits each. */
const sameCommit = (a, b) => typeof a === 'string' && typeof b === 'string' && /^[0-9a-f]{7,40}$/.test(a) && /^[0-9a-f]{7,40}$/.test(b) && (a.startsWith(b) || b.startsWith(a));

/**
 * A section read from verification checks: each must have run and passed on the release's commit; one that never ran,
 * or whose run tested another commit — evidence downloaded for the wrong run —, keeps the section NOT RUN.
 */
function fromChecks(checks, patterns, commit) {
    const named = [...checks.entries()].filter(([name]) => patterns.some((pattern) => pattern.test(name)));
    const found = named.filter(([, check]) => sameCommit(check.commit, commit));
    const stale = named.filter(([, check]) => !sameCommit(check.commit, commit)).map(([name, check]) => `${name} (tested ${check.commit ?? 'no commit'}, not ${commit ?? 'the release commit'})`);
    const missing = patterns.filter((pattern) => !named.some(([name]) => pattern.test(name)));
    const notRunNames = found.filter(([, check]) => check.status === 'NOT RUN' || check.status === 'EMPTY').map(([name]) => name);
    const failed = found.filter(([, check]) => check.status === 'FAIL').map(([name]) => name);

    if (failed.length > 0) return counted(failed.length, `failed: ${failed.join('; ')}`);
    if (missing.length > 0 || notRunNames.length > 0 || stale.length > 0 || found.length === 0) {
        return notRun(`no run's evidence for ${[...missing.map((pattern) => pattern.source.replace(/[\^\\]/g, '').trim()), ...notRunNames, ...stale].join('; ')}`);
    }

    return counted(0, found.map(([name, check]) => `${name} — ${check.evidence}`).join('; '));
}

/** CJ-01–CJ-22: the journeys a release's Layer 3 runs (TEST_STRATEGY §7). */
const JOURNEYS = 22;

/**
 * PERFORMANCE_TARGETS §4's release-critical set, each case's count of measurements outside its target; PFM-03's plans
 * too when the release changes the schema (a registered query's change adds them by the operator's count).
 */
const PERFORMANCE = ['PFM-01 projects list', 'PFM-01 project detail', 'PFM-01 inventory', 'PFM-01 scanner lookup', 'PFM-01 receivables',
    'PFM-01 invoice detail', 'PFM-01 dashboard', 'PFM-02 every page', 'PFM-08', 'PFM-13', 'PFM-16', 'PFM-20'];
const PLANS = 'PFM-03 plans';

const isCount = (value) => Number.isInteger(value) && value >= 0;

/**
 * A staging section from the operator's summary: its counted failures once every field is a count — a field missing,
 * null, negative or not a whole number, as the template's are, keeps the section NOT RUN, never a measured zero
 * (GAP-017) —, and NOT RUN where `valid` refuses the counts as an incomplete run.
 */
function fromStaging(staging, key, fields, failuresOf, describe, valid = () => null) {
    const part = staging?.[key];
    const missing = part === undefined || part === null ? fields : fields.filter((field) => !isCount(part[field]));

    if (missing.length > 0) return notRun(`no staging count for ${missing.join(', ')} (RELEASE_PLAN REL-03, REL-04)`);
    const incomplete = valid(part);

    return incomplete === null ? counted(failuresOf(part), describe(part)) : notRun(incomplete);
}

/** The release's declaration of RL-03 and its approval: PASS for NONE, or for the exception with the Owner's approval named. */
function declared(value, approval, exception, label) {
    if (value === undefined || value === null) return null;
    const ok = value === 'NONE' || (value === exception && typeof approval === 'string' && approval.trim() !== '');

    return { failures: ok ? 0 : 1, detail: `${label}: ${value}${typeof approval === 'string' && approval.trim() !== '' ? ` (${approval})` : ''}` };
}

/**
 * The report's sections from the evidence; RELEASE STATUS PASS only when every section is. TASKS reads the plan's
 * BLOCKED and VERIFYING counters and the release's own Tasks — `declarations.tasks`, as the record lists them —, each
 * DONE in the plan's table ("required todo 0"); a Task outside the release, a later one still TODO, is not counted.
 */
export function compile({ plan, checks, toolchain, declarations, staging, commit }) {
    const sections = {};
    const tasks = Array.isArray(declarations?.tasks) ? declarations.tasks : [];

    if (plan === null || [plan.blocked, plan.verifying].some((value) => Number.isNaN(value))) sections.TASKS = notRun('no Task plan counters');
    else if (tasks.length === 0) sections.TASKS = notRun('no list of the release\'s Tasks (REL-01)');
    else {
        const open = tasks.filter((task) => plan.statuses.get(task) !== 'DONE');
        sections.TASKS = counted(plan.blocked + plan.verifying + open.length,
            `total ${plan.total}, done ${plan.done}; blocked ${plan.blocked}, verifying ${plan.verifying}; the release's ${tasks.length} Tasks, not DONE ${open.length}${open.length > 0 ? `: ${open.join(', ')}` : ''}`);
    }
    sections.ROUTES = fromChecks(checks, CHECKS.ROUTES, commit);
    sections.INTERACTIONS = fromChecks(checks, CHECKS.INTERACTIONS, commit);
    sections['CRITICAL JOURNEYS'] = fromStaging(staging, 'critical_journeys', ['total', 'passed', 'failed'], (part) => part.failed + (part.total - part.passed - part.failed),
        (part) => `CJ-01–CJ-22 at Layer 3: total ${part.total}, passed ${part.passed}, failed ${part.failed}`,
        (part) => (part.total !== JOURNEYS ? `CJ-01–CJ-22 are ${JOURNEYS} journeys; the staging summary counts ${part.total}` : part.passed + part.failed > part.total ? 'passed and failed exceed the total' : null));
    const browserUi = fromChecks(checks, CHECKS['UI/UX'], commit);
    const stagingUi = fromStaging(staging, 'ui_ux', ['missing_translations', 'redundant_critical_actions', 'failed_checks'],
        (part) => part.missing_translations + part.redundant_critical_actions + part.failed_checks,
        (part) => `Layer 3: missing translations ${part.missing_translations}, redundant critical actions ${part.redundant_critical_actions}, failed checks ${part.failed_checks}`);
    sections['UI/UX'] = browserUi.status === 'FAIL' || stagingUi.status === 'FAIL'
        ? counted((browserUi.failures ?? 0) + (stagingUi.failures ?? 0), [browserUi, stagingUi].filter((part) => part.status === 'FAIL').map((part) => part.detail).join('; '))
        : browserUi.status !== 'PASS' ? browserUi
            : stagingUi.status !== 'PASS' ? stagingUi : counted(0, `${browserUi.detail}; ${stagingUi.detail}`);
    sections.BROWSER = fromStaging(staging, 'browser', ['console_errors', 'failed_requests'], (part) => part.console_errors + part.failed_requests,
        (part) => `critical console errors ${part.console_errors}; critical failed requests ${part.failed_requests}`);
    sections.TOOLCHAIN = toolchain === null || toolchain.rows === 0
        ? notRun('no TOOLCHAIN rows read')
        : counted(toolchain.unverified.length, toolchain.unverified.length === 0 ? `${toolchain.rows} capabilities verified` : `not yet verified: ${toolchain.unverified.join('; ')}`);
    const cost = declared(declarations?.cost, declarations?.cost_approval, 'APPROVED EXCEPTION', 'ADDITIONAL RECURRING COST CHANGE');
    sections.COST = cost === null ? notRun('no cost declaration (RL-03)') : counted(cost.failures, cost.detail);
    const db = declared(declarations?.db, declarations?.db_approval, 'APPROVED CONTROLLED MIGRATION', 'DB CHANGE');
    const mutation = declarations?.agent_production_db_mutation;
    sections.DATABASE = db === null || mutation === undefined || mutation === null
        ? notRun('no database declaration (RL-03)')
        : counted(db.failures + (mutation === 'NO' ? 0 : 1), `${db.detail}; direct production DB mutation by a coding agent: ${mutation}`);
    sections.BUILD = fromChecks(checks, CHECKS.BUILD, commit);
    sections.SECURITY = fromChecks(checks, CHECKS.SECURITY, commit);
    const measured = [...PERFORMANCE, ...(declarations?.db === 'APPROVED CONTROLLED MIGRATION' || isCount(staging?.performance?.[PLANS]) ? [PLANS] : [])];
    sections.PERFORMANCE = fromStaging(staging, 'performance', measured, (part) => measured.reduce((sum, name) => sum + part[name], 0),
        (part) => {
            const outside = measured.filter((name) => part[name] > 0);

            return `PERFORMANCE_TARGETS §4's release-critical set: ${measured.length} cases, outside target ${outside.length}${outside.length > 0 ? `: ${outside.join(', ')}` : ''}`;
        });
    sections.INTEGRITY = fromStaging(staging, 'integrity', ['differences'], (part) => part.differences, (part) => `ReconcileGuards after Layer 3: ${part.differences} differences`);

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
    const row = (task, status) => `| [${task}](${task}.md) | A Task | Stage · module | HIGH | ${status} | — | — | NO | AGENT | NONE | NO |`;
    const plan = ['Current Total Tasks: 3', '', 'DONE: 2', 'READY: 0', 'IN_PROGRESS: 0', 'BLOCKED: 0', 'VERIFYING: 0', 'TODO: 1', '',
        '| Task | Title | Stage · module | Risk | Status | Depends on (TASK-) | Blocks (TASK-) | Parallel safe | Executor | DB impact | UI impact |',
        '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |', row('TASK-001', 'DONE'), row('TASK-002', 'DONE'), row('TASK-003', 'TODO'), ''].join('\n');
    const toolchain = '| Capability | Preferred tool | Project status | Verified by |\n| --- | --- | --- | --- |\n| Browser E2E (§5.3) | Playwright | Verified by TASK-003 | TASK-003 |\n';
    const lines = ['TL-06 Authorization', 'TL-07 Route integrity', 'TL-08 Interaction', 'TL-10 Localization', 'TL-11 Responsive', 'TL-12 Accessibility', 'TL-13 Frontend build', 'TL-14 Secret-like scan (SX-06)'];
    const summary = (status) => lines.map((name) => `${name}: ${status(name)} — Evidence: abc1234, CI ubuntu-24.04 run 1, command, log`).join('\n');
    const performance = Object.fromEntries(PERFORMANCE.map((name) => [name, 0]));
    const staging = { critical_journeys: { total: 22, passed: 22, failed: 0 }, ui_ux: { missing_translations: 0, redundant_critical_actions: 0, failed_checks: 0 },
        browser: { console_errors: 0, failed_requests: 0 }, performance, integrity: { differences: 0 } };
    const declarations = { tasks: ['TASK-001', 'TASK-002'], db: 'NONE', db_approval: null, cost: 'NONE', cost_approval: null, agent_production_db_mutation: 'NO' };
    const tested = `abc1234${'e'.repeat(33)}`;
    const run = (status, stagingSummary, planText = plan, declared = declarations, commit = tested) => {
        const dir = mkdtempSync(join(root, 'e-'));
        writeFileSync(join(dir, 'summary-local.txt'), summary(status));

        return compile({ plan: readPlan(planText), checks: readSummaries(dir), toolchain: readToolchain(toolchain), declarations: declared, staging: stagingSummary, commit });
    };
    const template = (name) => JSON.parse(readFileSync(new URL(`../../docs/10-release/records/_template/${name}`, import.meta.url), 'utf8'));
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

    expect('a later Task still TODO outside the release', pass.sections.TASKS.status, 'PASS');
    const open = run(() => 'PASS', staging, plan, { ...declarations, tasks: ['TASK-001', 'TASK-003', 'TASK-009'] });
    expect('the release\'s Tasks not DONE, one unknown', open.sections.TASKS.status, 'FAIL');
    expect('their count', open.sections.TASKS.failures, 2);
    const blocked = run(() => 'PASS', staging, plan.replace('BLOCKED: 0', 'BLOCKED: 1'));
    expect('a Task blocked in the plan', blocked.sections.TASKS.status, 'FAIL');
    expect('no list of the release\'s Tasks', run(() => 'PASS', staging, plan, { ...declarations, tasks: [] }).sections.TASKS.status, 'NOT RUN');

    const copied = run(() => 'PASS', template('staging-summary.json'), plan, template('declarations.json'));
    ['TASKS', 'CRITICAL JOURNEYS', 'UI/UX', 'BROWSER', 'COST', 'DATABASE', 'PERFORMANCE', 'INTEGRITY']
        .forEach((name) => expect(`the template's ${name}, untouched`, copied.sections[name].status, 'NOT RUN'));
    expect('a release from the untouched template', copied.status, 'FAIL');
    expect('journeys given as an empty object', run(() => 'PASS', { ...staging, critical_journeys: {} }).sections['CRITICAL JOURNEYS'].status, 'NOT RUN');
    expect('five of the twenty-two journeys', run(() => 'PASS', { ...staging, critical_journeys: { total: 5, passed: 5, failed: 0 } }).sections['CRITICAL JOURNEYS'].status, 'NOT RUN');
    expect('a negative count', run(() => 'PASS', { ...staging, browser: { console_errors: 2, failed_requests: -2 } }).sections.BROWSER.status, 'NOT RUN');
    expect('a count given as text', run(() => 'PASS', { ...staging, integrity: { differences: '0' } }).sections.INTEGRITY.status, 'NOT RUN');
    expect('one passing performance case of the set', run(() => 'PASS', { ...staging, performance: { 'PFM-01 projects list': 0 } }).sections.PERFORMANCE.status, 'NOT RUN');
    expect('a release-critical case outside its target', run(() => 'PASS', { ...staging, performance: { ...performance, 'PFM-20': 1 } }).sections.PERFORMANCE.status, 'FAIL');
    const plans = { ...declarations, db: 'APPROVED CONTROLLED MIGRATION', db_approval: 'APPR-0xx' };
    expect('a schema change without PFM-03\'s plans', run(() => 'PASS', staging, plan, plans).sections.PERFORMANCE.status, 'NOT RUN');
    expect('a schema change with them', run(() => 'PASS', { ...staging, performance: { ...performance, [PLANS]: 0 } }, plan, plans).sections.PERFORMANCE.status, 'PASS');
    const stale = run(() => 'PASS', staging, plan, declarations, `fff9999${'e'.repeat(33)}`);
    ['ROUTES', 'INTERACTIONS', 'UI/UX', 'BUILD', 'SECURITY'].forEach((name) => expect(`${name} from another commit's evidence`, stale.sections[name].status, 'NOT RUN'));
    expect('a release on another commit\'s evidence', stale.status, 'FAIL');
    expect('no release commit given', run(() => 'PASS', staging, plan, declarations, null).sections.BUILD.status, 'NOT RUN');
    expect('a Layer 3 translation missing', run(() => 'PASS', { ...staging, ui_ux: { ...staging.ui_ux, missing_translations: 1 } }).sections['UI/UX'].status, 'FAIL');

    const migration = { ...declarations, db: 'APPROVED CONTROLLED MIGRATION' };
    expect('a migration without the Owner\'s approval', run(() => 'PASS', staging, plan, migration).sections.DATABASE.status, 'FAIL');
    expect('a migration with it', run(() => 'PASS', staging, plan, { ...migration, db_approval: 'APPR-0xx' }).sections.DATABASE.status, 'PASS');
    expect('a paid exception without approval', run(() => 'PASS', staging, plan, { ...declarations, cost: 'APPROVED EXCEPTION' }).sections.COST.status, 'FAIL');

    const absent = compile({ plan: readPlan(plan), checks: new Map(), toolchain: readToolchain(toolchain), declarations: null, staging, commit: tested });
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

    console.log(`PASS: the report's section rules hold on fixture evidence (${SECTIONS.length} sections; a pass, a failure, a missing run, the untouched template, another commit's evidence)`);
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
        commit: argument('--commit'),
    });
    const text = render(report, { commit: argument('--commit'), tag: argument('--tag') });
    const out = argument('--out');

    if (out !== undefined) writeFileSync(out, text);
    process.stdout.write(text);
    // The report is written whatever it says; its exit names the status for the workflow's log.
    process.exitCode = report.status === 'PASS' ? 0 : 3;
}
