// The traceability register of TASK-069 (APPR-040; SECURITY_TEST_MATRIX §1, §3–§7; TEST_STRATEGY §8; criteria 1, 3, 4,
// 7 and 9): every row of the security matrix — SM-01–SM-25, the H8 rows, the data paths, the W4 rows, the Owner-only
// denials, the threats and the race cases its rows name — and every INT- and NAV- contract mapped to the tests that
// prove it, read from the registers themselves. A row is proved by a test that cites it — a range such as
// `INT-114–INT-116` citing each row in it —, an H8 row also by its SM row, a threat by the SM rows the matrix gives
// it, a data path also by the tests PERMISSIONS_MATRIX §11's register lists, a W4 row also by the authorization
// tests exercising each capability of the row. A row with no test fails unless it is held open below with its
// reason, and an open row that has its test fails too, so the list stays exact. The register is printed for the
// evidence.
// Usage: node scripts/ci/check-traceability.mjs [repository root] | --self-test
import { existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, relative, resolve } from 'node:path';

/** The rows held open, each with the reason TASK-069's security record gives and the Task that closes it. */
export const OPEN = {
    'INT-277': 'TASK-072\'s health check: registered at /up where ROUTE-277 writes /kesehatan, its 503 answer untested (TASK-069 record F-01)',
    'INT-278': 'staging only and not built — the CSP report route of TASK-072 (ROUTE-278; TASK-069 record F-01)',
};

const WIDTH = { SM: 2, H8: 2, DP: 2, W4: 2, OD: 2, TM: 2, M: 2, INT: 3, NAV: 3 };
const KINDS = Object.keys(WIDTH);
const CITATION = /\b(SM|H8|DP|W4|OD|TM|M|INT|NAV)-(\d{2,3})(?:\s*–\s*(?:\1-)?(\d{2,3}))?/g;

/** Every row ID a text cites, each range expanded. */
export function cited(text) {
    const found = new Set();

    for (const [, kind, from, to] of text.matchAll(CITATION)) {
        const last = to === undefined ? Number(from) : Number(to);

        for (let number = Number(from); number <= last && number - Number(from) < 400; number++) {
            found.add(`${kind}-${String(number).padStart(WIDTH[kind], '0')}`);
        }
    }

    return found;
}

/** The table rows of a Markdown file whose first cell is an ID of the kind, as their cells. */
function rows(file, kind) {
    return readFileSync(file, 'utf8')
        .split('\n')
        .filter((line) => new RegExp(`^\\| ${kind}-\\d+ \\|`).test(line))
        .map((line) => line.split('|').slice(1, -1).map((cell) => cell.trim()));
}

/** The registers: each row ID with what may prove it besides a test citing it. */
export function registers(root) {
    const matrix = join(root, 'docs', '08-testing', 'SECURITY_TEST_MATRIX.md');
    const catalogue = readFileSync(join(root, 'app', 'Modules', 'IAM', 'Data', 'CapabilityCatalogue.php'), 'utf8');
    const register = new Map();
    const add = (id, via = {}) => register.set(id, { id, kind: id.split('-')[0], ...via });

    for (const cells of rows(matrix, 'SM')) {
        const [id, proof] = cells;
        add(id);
        const h8 = /H8-\d{2}/.exec(proof)?.[0];

        if (h8) add(h8, { sm: [id] });

    }

    // The race cases the matrix names — §1's concurrent cases and each row's (M-13, M-20–M-25, M-39–M-46).
    for (const race of cited(readFileSync(matrix, 'utf8'))) {
        if (race.startsWith('M-')) add(race);
    }

    for (const [id] of rows(matrix, 'DP')) add(id);

    for (const [id] of rows(matrix, 'W4')) {
        const codes = new RegExp(`'${id}' => \\['[^']*', \\[([^\\]]*)\\]\\]`).exec(catalogue)?.[1] ?? '';
        add(id, { codes: [...codes.matchAll(/'([a-z_]+\.[a-z_]+)'/g)].map((match) => match[1]) });
    }

    for (const [id] of rows(matrix, 'OD')) add(id);

    for (const [id, , , cases] of rows(matrix, 'TM')) add(id, { sm: [...cited(cases ?? '')].filter((row) => row.startsWith('SM-')) });

    for (const [id] of rows(join(root, 'docs', '03-workflows', 'INTERACTION_CONTRACTS.md'), 'INT')) add(id);
    for (const [id] of rows(join(root, 'docs', '07-ux-design', 'NAVIGATION_CONTRACTS.md'), 'NAV')) add(id);

    return register;
}

function files(directory, keep) {
    const found = [];
    const walk = (path) => {
        for (const entry of existsSync(path) ? readdirSync(path) : []) {
            const full = join(path, entry);

            if (statSync(full).isDirectory()) {
                if (!['Support', 'Fixtures', 'node_modules'].includes(entry)) walk(full);
            } else if (keep(entry)) found.push(full);
        }
    };
    walk(directory);

    return found;
}

/**
 * Each row ID with the tests citing it: the test files of the suites, and the verification gates whose self-tests
 * prove SM-15's detections (scripts/verify.sh and scripts/ci).
 */
export function citations(root) {
    const sources = [
        ...files(join(root, 'tests'), (name) => /Test\.php$|\.spec\.ts$/.test(name)),
        ...[join(root, 'scripts', 'verify.sh')].filter(existsSync),
        ...files(join(root, 'scripts', 'ci'), (name) => /\.(sh|mjs)$/.test(name) && name !== 'check-traceability.mjs'),
    ];
    const map = new Map();

    for (const file of sources) {
        for (const id of cited(readFileSync(file, 'utf8'))) {
            map.set(id, [...(map.get(id) ?? []), relative(root, file).replaceAll('\\', '/')]);
        }
    }

    return map;
}

/** Every row with how it is proved, and each failure. */
export function trace(root, open = OPEN) {
    const register = registers(root);
    const cites = citations(root);
    const dataPaths = existsSync(join(root, 'tests', 'Support', 'DataPaths.php')) ? readFileSync(join(root, 'tests', 'Support', 'DataPaths.php'), 'utf8') : '';
    const tests = files(join(root, 'tests'), (name) => /Test\.php$/.test(name)).map((file) => readFileSync(file, 'utf8')).join('\n');
    // A capability a route declares is refused and admitted at that route by RouteDenialsTest (SM-04).
    const denials = existsSync(join(root, 'tests', 'Authorization', 'RouteDenialsTest.php'));
    const declared = new Set(denials && existsSync(join(root, 'routes', 'web.php')) ? [...readFileSync(join(root, 'routes', 'web.php'), 'utf8').matchAll(/'capability:([a-z_.,]+)'/g)].flatMap((match) => match[1].split(',')) : []);
    const exercised = (code) => declared.has(code) || tests.includes(`'${code}'`);
    const proved = new Map();
    const proof = (row) => {
        if (proved.has(row.id)) return proved.get(row.id);
        const direct = cites.get(row.id) ?? [];
        let via = direct.length > 0 ? `${direct.length} test file(s): ${direct.slice(0, 3).join(', ')}${direct.length > 3 ? ', …' : ''}` : null;

        if (!via && row.sm?.length && row.sm.every((sm) => register.has(sm) && proof(register.get(sm)))) via = `through ${row.sm.join(', ')}`;

        if (!via && row.kind === 'DP') {
            const listed = new RegExp(`'${row.id}' => \\[\\[([^\\]]*)\\]`).exec(dataPaths)?.[1] ?? '';
            const tests = [...listed.matchAll(/'([^']+)'/g)].map((match) => match[1]).filter((test) => existsSync(join(root, test)));
            if (tests.length > 0) via = `the data-path register: ${tests.join(', ')}`;
        }

        if (!via && row.codes?.length && row.codes.every(exercised)) via = `each of ${row.codes.join(', ')} refused and admitted at its routes or by a test, its class in CapabilityClassesTest`;

        proved.set(row.id, via);

        return via;
    };
    const lines = [];
    const errors = [];

    for (const row of register.values()) {
        const via = proof(row);
        lines.push(`${row.id}\t${via ?? (open[row.id] ? `OPEN — ${open[row.id]}` : 'NO TEST')}`);

        if (!via && !open[row.id]) errors.push(`${row.id}: no test proves it`);
        if (via && open[row.id]) errors.push(`${row.id}: held open but proved by ${via}; remove it from OPEN`);
    }

    for (const id of Object.keys(open)) {
        if (!register.has(id)) errors.push(`${id}: held open but not in its register`);
    }

    const counts = KINDS.map((kind) => {
        const ofKind = [...register.values()].filter((row) => row.kind === kind);
        return `${kind} ${ofKind.filter((row) => proof(row)).length}/${ofKind.length}`;
    });

    return { lines, errors, counts };
}

function selfTest() {
    const scratch = mkdtempSync(join(tmpdir(), 'traceability-'));
    const write = (path, text) => {
        mkdirSync(join(scratch, path, '..'), { recursive: true });
        writeFileSync(join(scratch, path), text);
    };

    try {
        write('docs/08-testing/SECURITY_TEST_MATRIX.md', [
            '| SM-01 | H8-01 — isolation | x | TL-06 | DP-01 |',
            '| SM-02 | H8-02 — routes | x | TL-07 | M-20–M-21 |',
            '| DP-01 | URL | x | SM-01 |',
            '| DP-02 | query | x | SM-01 |',
            '| W4-01 | users | OWN! | x |',
            '| OD-01 | x |',
            '| TM-01 | x | H8-01 | SM-01, SM-02 |',
        ].join('\n'));
        write('app/Modules/IAM/Data/CapabilityCatalogue.php', "'W4-01' => ['OWN!', ['users.manage', 'permissions.manage']],");
        write('docs/03-workflows/INTERACTION_CONTRACTS.md', '| INT-001 | a |\n| INT-002 | b |\n| INT-003 | c |\n');
        write('docs/07-ux-design/NAVIGATION_CONTRACTS.md', '| NAV-001 | a |\n');
        write('tests/Support/DataPaths.php', "'DP-02' => [['tests/Feature/QueryTest.php'], []],");
        write('tests/Feature/QueryTest.php', '<?php // a query test');
        write('tests/Feature/RowsTest.php', '<?php // SM-01; SM-02; M-20; OD-01; INT-001–INT-002; NAV-001; DP-01');
        write('tests/Authorization/UsersTest.php', "<?php 'users.manage' 'permissions.manage'");

        const expect = (what, actual, expected) => {
            if (JSON.stringify(actual) !== JSON.stringify(expected)) throw new Error(`${what}: ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)}`);
        };

        expect('a range cited', [...cited('INT-114–INT-116 and M-20–25')], ['INT-114', 'INT-115', 'INT-116', 'M-20', 'M-21', 'M-22', 'M-23', 'M-24', 'M-25']);
        expect('the missing rows', trace(scratch, {}).errors, ['M-21: no test proves it', 'INT-003: no test proves it']);
        expect('a row held open', trace(scratch, { 'M-21': 'r', 'INT-003': 'r' }).errors, []);
        expect('an open row proved', trace(scratch, { 'M-21': 'r', 'INT-003': 'r', 'INT-001': 'r' }).errors, ['INT-001: held open but proved by 1 test file(s): tests/Feature/RowsTest.php; remove it from OPEN']);
        expect('an open row outside its register', trace(scratch, { 'M-21': 'r', 'INT-003': 'r', 'INT-009': 'r' }).errors, ['INT-009: held open but not in its register']);

        const lines = Object.fromEntries(trace(scratch, { 'M-21': 'r', 'INT-003': 'r' }).lines.map((line) => line.split('\t')));
        expect('H8 through its SM row', lines['H8-01'], 'through SM-01');
        expect('a threat through its SM rows', lines['TM-01'], 'through SM-01, SM-02');
        expect('a data path through its register', lines['DP-02'], 'the data-path register: tests/Feature/QueryTest.php');
        expect('a W4 row through its capabilities', lines['W4-01'], 'each of users.manage, permissions.manage refused and admitted at its routes or by a test, its class in CapabilityClassesTest');

        write('tests/Authorization/UsersTest.php', "<?php 'users.manage'");
        expect('a W4 row with a capability untested', trace(scratch, { 'M-21': 'r', 'INT-003': 'r' }).errors, ['W4-01: no test proves it']);

        write('routes/web.php', "<?php Route::put('/x')->middleware(['auth', 'capability:permissions.manage']);");
        write('tests/Authorization/RouteDenialsTest.php', '<?php // every declared capability refused and admitted');
        expect('a W4 capability declared by a route', trace(scratch, { 'M-21': 'r', 'INT-003': 'r' }).errors, []);
    } finally {
        rmSync(scratch, { recursive: true, force: true });
    }

    console.log('PASS: the traceability check expands ranges, maps H8, TM, DP and W4 rows, and detects a missing or stale row');
}

if (process.argv[2] === '--self-test') {
    selfTest();
} else {
    const root = resolve(process.argv[2] ?? '.');
    const { lines, errors, counts } = trace(root);

    console.log(lines.join('\n'));
    console.log(`\n${counts.join('; ')}`);

    if (errors.length > 0) {
        console.error(errors.map((error) => `FAIL: ${error}`).join('\n'));
        process.exit(1);
    }

    console.log('PASS: every security matrix row and every interaction and navigation contract has its test');
}
