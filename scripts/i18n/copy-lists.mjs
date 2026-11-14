// The per-page copy lists of TASK-067 (LOCALIZATION §5, §5.1; `DIR-081` OB-04; TEST_STRATEGY §10, §12; RELEASE_PLAN §8):
// for every page PG-001–PG-065, the keys added to the catalogue after APPR-011 that the page uses — named by its page
// file or by the components and features it renders, or named for it (`pages.pg_014.…`) —, each with its Indonesian and
// English from the string files, its kind and source from the added-keys registry, and its state under the Owner's OB-04
// rule: accepted by construction, or proposed for the Owner's review at the UAT. LOCALIZATION §5.1's validated keys stay
// validated and are not listed. Each page's list also names the checks behind its language items of AICWDF §27, among
// them the Localization specs that audit the page in both languages (tests/Browser/localization-audit.ts): a page no spec
// audits fails the script. The lists are generated, never edited: `--check` fails while the folder's lists differ from
// what the script would write (tests/Frontend/copy-lists.test.ts writes them, then checks them).
//
//   node scripts/i18n/copy-lists.mjs [--out <dir>]            writes the lists (storage/logs/verify/copy-lists)
//   node scripts/i18n/copy-lists.mjs --check [--out <dir>]    exits 1 when the lists differ from what it would write
//   node scripts/i18n/copy-lists.mjs --self-test              proves its rules on fixtures
import { existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, posix, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import ts from 'typescript';

const ROOT = fileURLToPath(new URL('../../', import.meta.url));
// The test evidence area TASK-003 set up (CI keeps storage/logs/verify/ as the verify-evidence artifact): the lists are evidence
// for the Owner's review at the UAT (TEST_STRATEGY §12), generated on every run, never committed or edited by hand.
export const OUT = 'storage/logs/verify/copy-lists';
// The lists' links to the documents, from OUT.
const DOCS = '../../../../docs';
const LOCALIZATION = 'docs/07-ux-design/LOCALIZATION.md';
const REGISTRY = 'lang/registry/added-keys.json';
const CODE = 'resources/js';
const PAGES = 'resources/js/Pages';
const SHELL = 'resources/js/layouts/AppShell.tsx';
const SERVER = ['app', 'routes', 'resources/views', 'config', 'database'];
const SPECS = 'tests/Browser/Localization';

const byCode = (a, b) => (a < b ? -1 : a > b ? 1 : 0);
const sorted = (values) => [...values].sort(byCode);
const read = (root, path) => readFileSync(join(root, path), 'utf8').replace(/\r\n/g, '\n');
const escapeRegExp = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/** Every file under a folder whose name passes the test, as a root-relative path with forward slashes, sorted. */
function files(root, folder, test) {
    const found = [];
    const walk = (path) => {
        for (const entry of readdirSync(join(root, path), { withFileTypes: true })) {
            const child = posix.join(path, entry.name);

            if (entry.isDirectory()) walk(child);
            else if (test(entry.name)) found.push(child);
        }
    };

    if (existsSync(join(root, folder))) walk(folder);

    return found.sort(byCode);
}

/** A Markdown table row's cells; an escaped `\|` stays inside its cell. */
function cells(line) {
    const out = [];
    let cell = '';

    for (let i = 1; i < line.length; i++) {
        if (line[i] === '\\' && line[i + 1] === '|') {
            cell += '\\|';
            i++;
        } else if (line[i] === '|') {
            out.push(cell.trim());
            cell = '';
        } else {
            cell += line[i];
        }
    }

    return out;
}

/**
 * LOCALIZATION's rule and inventory: the Owner's OB-04 rule as §5 quotes it, and §6's keys with their kinds — a key an
 * amendment after APPR-011 added (its kind marked `DIR-100`, `DIR-101`, …) is new; a data or never-translated key is off
 * §5.1's list; every other key is on it, validated in the Owner's pass —, held to §5.1's own count and named keys.
 */
export function readContract(text) {
    const rule = /\*\*Copy written after APPR-011\*\*[^\n]*?«([^»]+)»/.exec(text)?.[1];
    const list = /\*\*(\d+) keys\*\* are on the list; (\d+) are not: ((?:`[^`]+`(?:, )?)+)/.exec(text);

    if (rule === undefined || list === null) {
        throw new Error(`${LOCALIZATION}: the OB-04 rule of §5 or the count of §5.1 is not where the script reads it`);
    }

    const lines = text.split('\n');
    const start = lines.findIndex((line) => line.startsWith('## 6. '));
    const end = lines.findIndex((line) => line.startsWith('## 7. '));
    const inventory = new Map();

    for (const line of lines.slice(start, end)) {
        if (!/^\| `[^`]+` \|/.test(line)) continue;
        const [key = '', id = '', en = '', kind = '', source = ''] = cells(line);
        inventory.set(key.replaceAll('`', ''), { id, en, kind, source });
    }

    const amended = new Map([...inventory].filter(([, row]) => /`DIR-\d+`/.test(row.kind)));
    const off = sorted([...inventory].filter(([key, row]) => !amended.has(key) && (row.kind === 'data' || row.kind.startsWith('never translated'))).map(([key]) => key));
    const validated = new Set([...inventory.keys()].filter((key) => !amended.has(key) && !off.includes(key)));
    const named = sorted([...list[3].matchAll(/`([^`]+)`/g)].map((match) => match[1]));

    if (validated.size !== Number(list[1]) || off.length !== Number(list[2]) || off.join() !== named.join()) {
        throw new Error(`${LOCALIZATION}: §6 reads ${String(validated.size)} validated and ${String(off.length)} off-list keys; §5.1 states ${list[1]} and ${list[2]}`);
    }

    return { rule, inventory, amended, validated, off };
}

/** The validated texts by their two languages and by their Indonesian alone — what OB-04 compares a new key with. */
export function indexValidated(validated) {
    const pairs = new Map();
    const indonesian = new Map();
    const glossaryFirst = (a, b) => (a.startsWith('glossary.') === b.startsWith('glossary.') ? byCode(a, b) : a.startsWith('glossary.') ? -1 : 1);

    for (const [key, text] of validated) {
        const pair = `${text.id}\u0000${text.en}`;
        pairs.set(pair, [...(pairs.get(pair) ?? []), key].sort(glossaryFirst));
        indonesian.set(text.id, [...(indonesian.get(text.id) ?? []), key].sort(glossaryFirst));
    }

    return { pairs, indonesian, validated };
}

/**
 * The Owner's OB-04 rule (LOCALIZATION §5), applied as written: a new key is accepted by construction when its Indonesian
 * is a GL term or a label of an approved source and its English is that term's or label's validated rendering — here,
 * when its two texts are, character for character, the Indonesian and the validated English of a key on §5.1's list,
 * which the list names —; any other new key is proposed. A proposed key whose Indonesian alone is validated names that
 * key and its validated English, for the Owner's review.
 */
export function judge(id, en, index) {
    if (typeof id !== 'string' || typeof en !== 'string' || id === '' || en === '') return { state: 'proposed', basis: [], near: [] };
    const basis = index.pairs.get(`${id}\u0000${en}`) ?? [];

    if (basis.length > 0) return { state: 'accepted', basis, near: [] };

    return { state: 'proposed', basis: [], near: index.indonesian.get(id) ?? [] };
}

const blank = () => ({ keys: new Set(), patterns: new Map(), refs: new Set() });

/** Whether an identifier names a value the code uses, rather than a property, a JSX attribute or a declaration's name. */
function isReference(node) {
    const { parent } = node;

    if (ts.isPropertyAccessExpression(parent)) return parent.expression === node;
    if (ts.isQualifiedName(parent)) return parent.left === node;
    if (ts.isBindingElement(parent)) return false;
    if (
        ts.isPropertyAssignment(parent) || ts.isPropertyDeclaration(parent) || ts.isPropertySignature(parent) || ts.isMethodDeclaration(parent) ||
        ts.isJsxAttribute(parent) || ts.isGetAccessorDeclaration(parent) || ts.isSetAccessorDeclaration(parent) || ts.isEnumMember(parent) ||
        ts.isVariableDeclaration(parent) || ts.isParameter(parent) || ts.isFunctionDeclaration(parent) || ts.isClassDeclaration(parent)
    ) {
        return parent.name !== node;
    }

    return true;
}

/**
 * The values a run-time part of a key can take, as a pattern and as shown: the alternatives the code spells — a literal,
 * either branch of a condition, a nested template —, else any run of a key's characters, dots included, since a
 * recorded code can hold one (`activity.action.ADM.…`, `capability.name.projects.view`).
 */
function valuePattern(expression) {
    if (ts.isParenthesizedExpression(expression) || ts.isAsExpression(expression)) return valuePattern(expression.expression);
    if (ts.isStringLiteral(expression) || ts.isNoSubstitutionTemplateLiteral(expression)) return { source: escapeRegExp(expression.text), shown: expression.text };
    if (ts.isTemplateExpression(expression)) return templatePattern(expression);
    if (ts.isConditionalExpression(expression)) {
        const yes = valuePattern(expression.whenTrue);
        const no = valuePattern(expression.whenFalse);

        return { source: `(?:${yes.source}|${no.source})`, shown: `{${yes.shown},${no.shown}}` };
    }

    return { source: '[A-Za-z0-9_.-]*', shown: '*' };
}

function templatePattern(node) {
    let source = escapeRegExp(node.head.text);
    let shown = node.head.text;

    for (const span of node.templateSpans) {
        const value = valuePattern(span.expression);
        source += value.source + escapeRegExp(span.literal.text);
        shown += value.shown + span.literal.text;
    }

    return { source, shown };
}

/**
 * A key built at run time — `payments.tab.${tab}` —, as the pattern of the keys it can name: its static parts kept and
 * each run-time part as `valuePattern` reads it. Only a template whose group is static and of the catalogue is a key.
 */
function keyPattern(node, groups) {
    const group = /^([a-z][a-z0-9_]*)\./.exec(node.head.text)?.[1];

    if (group === undefined || !groups.has(group)) return null;
    const pattern = templatePattern(node);

    return { source: `^${pattern.source}$`, shown: pattern.shown };
}

/** The catalogue keys, run-time key patterns and names a piece of code uses; types are never rendered and are skipped. */
function usage(node, catalogue, groups, found = blank()) {
    const visit = (current) => {
        if (ts.isTypeNode(current) && !ts.isExpressionWithTypeArguments(current)) return;

        if (ts.isStringLiteral(current) || ts.isNoSubstitutionTemplateLiteral(current)) {
            if (catalogue.has(current.text)) found.keys.add(current.text);
        } else if (ts.isTemplateExpression(current)) {
            const pattern = keyPattern(current, groups);
            if (pattern !== null) found.patterns.set(pattern.source, pattern.shown);
        } else if (ts.isIdentifier(current) && isReference(current)) {
            found.refs.add(current.text);
        }

        ts.forEachChild(current, visit);
    };

    visit(node);

    return found;
}

/** The module an import names: `@/…` under resources/js, or a path relative to the importer; a package is no module of ours. */
function resolveImport(from, specifier, known) {
    let base;

    if (specifier.startsWith('@/')) base = posix.join(CODE, specifier.slice(2));
    else if (specifier.startsWith('.')) base = posix.join(posix.dirname(from), specifier);
    else return null;

    return [base, `${base}.tsx`, `${base}.ts`, `${base}/index.tsx`, `${base}/index.ts`].find((path) => known.has(path)) ?? null;
}

/**
 * A module's top-level declarations, each with the keys, run-time key patterns and names it uses; its value imports
 * (a type-only import renders nothing), its exports and re-exports, and the code that runs when it loads.
 */
export function parseModule(path, text, catalogue, groups, known) {
    const source = ts.createSourceFile(path, text, ts.ScriptTarget.Latest, true, path.endsWith('.tsx') ? ts.ScriptKind.TSX : ts.ScriptKind.TS);
    const module = { path, decls: new Map(), imports: new Map(), exports: new Map(), reexports: new Map(), stars: [], always: blank() };
    const has = (node, kind) => node.modifiers?.some((modifier) => modifier.kind === kind) ?? false;
    const target = (specifier) => (ts.isStringLiteral(specifier) ? resolveImport(path, specifier.text, known) : null);

    for (const statement of source.statements) {
        if (ts.isImportDeclaration(statement)) {
            const clause = statement.importClause;
            const from = target(statement.moduleSpecifier);

            if (clause === undefined || clause.isTypeOnly) continue;
            if (clause.name !== undefined) module.imports.set(clause.name.text, { from, name: 'default' });
            const bindings = clause.namedBindings;

            if (bindings !== undefined && ts.isNamespaceImport(bindings)) module.imports.set(bindings.name.text, { from, name: '*' });
            if (bindings !== undefined && ts.isNamedImports(bindings)) {
                for (const element of bindings.elements) {
                    if (!element.isTypeOnly) module.imports.set(element.name.text, { from, name: (element.propertyName ?? element.name).text });
                }
            }
        } else if (ts.isExportDeclaration(statement)) {
            if (statement.isTypeOnly) continue;
            const from = statement.moduleSpecifier === undefined ? null : target(statement.moduleSpecifier);

            if (statement.exportClause === undefined) {
                if (from !== null) module.stars.push(from);
            } else if (ts.isNamespaceExport(statement.exportClause)) {
                module.reexports.set(statement.exportClause.name.text, { from, name: '*' });
            } else {
                for (const element of statement.exportClause.elements) {
                    if (element.isTypeOnly) continue;
                    const name = (element.propertyName ?? element.name).text;

                    if (statement.moduleSpecifier === undefined) module.exports.set(element.name.text, name);
                    else module.reexports.set(element.name.text, { from, name });
                }
            }
        } else if (ts.isInterfaceDeclaration(statement) || ts.isTypeAliasDeclaration(statement)) {
            continue;
        } else if (ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement) || ts.isEnumDeclaration(statement)) {
            const name = statement.name?.text ?? 'default';
            module.decls.set(name, usage(statement, catalogue, groups));
            if (has(statement, ts.SyntaxKind.ExportKeyword)) module.exports.set(has(statement, ts.SyntaxKind.DefaultKeyword) ? 'default' : name, name);
        } else if (ts.isVariableStatement(statement)) {
            const exported = has(statement, ts.SyntaxKind.ExportKeyword);

            for (const declaration of statement.declarationList.declarations) {
                const found = usage(declaration, catalogue, groups);
                const names = [];
                const bind = (name) => {
                    if (ts.isIdentifier(name)) names.push(name.text);
                    else for (const element of name.elements) if (!ts.isOmittedExpression(element)) bind(element.name);
                };
                bind(declaration.name);

                for (const name of names) {
                    module.decls.set(name, found);
                    if (exported) module.exports.set(name, name);
                }
            }
        } else if (ts.isExportAssignment(statement)) {
            module.decls.set('default', usage(statement.expression, catalogue, groups));
            module.exports.set('default', 'default');
        } else {
            usage(statement, catalogue, groups, module.always);
        }
    }

    return module;
}

/**
 * The keys and run-time key patterns a page renders: its own declarations and, import by import, the declarations of
 * the components and features it uses — never past the boundary, the shell's own export, whose keys are listed once.
 */
export function reach(modules, entries, boundary = new Set()) {
    const keys = new Set();
    const patterns = new Map();
    const done = new Set();
    const loaded = new Set();

    const take = (module, found) => {
        for (const key of found.keys) keys.add(key);
        for (const [source, shown] of found.patterns) patterns.set(source, shown);
        for (const name of found.refs) if (module.decls.has(name) || module.imports.has(name)) local(module.path, name);
    };
    const load = (module) => {
        if (loaded.has(module.path)) return;
        loaded.add(module.path);
        take(module, module.always);
    };
    const local = (path, name) => {
        const module = modules.get(path);

        if (module === undefined || done.has(`${path}#${name}`)) return;
        done.add(`${path}#${name}`);
        load(module);
        const declared = module.decls.get(name);
        const imported = module.imports.get(name);

        if (declared !== undefined) take(module, declared);
        else if (imported !== undefined && imported.from !== null) exported(imported.from, imported.name);
    };
    const provides = (path, name, seen = new Set()) => {
        const module = modules.get(path);

        if (module === undefined || seen.has(path)) return false;
        seen.add(path);

        return module.exports.has(name) || module.reexports.has(name) || module.stars.some((star) => provides(star, name, seen));
    };
    const exported = (path, name) => {
        const module = modules.get(path);

        if (module === undefined || boundary.has(`${path}#${name}`) || done.has(`${path}@${name}`)) return;
        done.add(`${path}@${name}`);

        if (name === '*') {
            load(module);
            for (const declared of module.decls.keys()) local(path, declared);

            return;
        }

        const own = module.exports.get(name);
        const again = module.reexports.get(name);

        if (own !== undefined) local(path, own);
        else if (again !== undefined && again.from !== null) exported(again.from, again.name);
        else for (const star of module.stars) if (provides(star, name)) exported(star, name);
    };

    for (const entry of entries) exported(entry.path, entry.name);

    return { keys, patterns };
}

/** The catalogue keys each pattern can name, a pattern compiled once. */
function expand(patterns, catalogueKeys, cache) {
    const found = new Map();

    for (const [source, shown] of patterns) {
        if (!cache.has(source)) {
            const expression = new RegExp(source);
            cache.set(source, catalogueKeys.filter((key) => expression.test(key)));
        }

        for (const key of cache.get(source)) found.set(key, [...(found.get(key) ?? []), shown]);
    }

    return found;
}

/**
 * The server's PHP files: the page components each renders — `Inertia::render('PRJ/Quotation', …)` or a constant holding
 * the name — and the keys each names, exactly (`__('users.validation.email_taken')`) or as a prefix a key is built on
 * (`"adm.answer.{$kind}"`, `'parties.answer.client_'.$kind`), so that an answer, a refusal or a validation message
 * reaches the page its controller renders.
 */
function scanServer(root, catalogueKeys, groups, components) {
    const catalogue = new Set(catalogueKeys);

    return SERVER.flatMap((folder) => files(root, folder, (name) => name.endsWith('.php'))).map((file) => {
        const text = read(root, file);
        const keys = new Map();
        const renders = new Set();

        for (const match of text.matchAll(/(['"])([A-Za-z0-9_./-]+)\1/g)) {
            if (catalogue.has(match[2])) keys.set(match[2], `\`${file}\``);
            if (components.has(match[2])) renders.add(match[2]);
        }
        for (const match of text.matchAll(/(['"])([a-z][a-z0-9_]*\.[A-Za-z0-9_.-]*)(?=\{\$|\$|\1\s*\.)/g)) {
            if (!groups.has(match[2].split('.')[0])) continue;
            for (const key of catalogueKeys) if (key.startsWith(match[2]) && !keys.has(key)) keys.set(key, `\`${file}\` (built at run time)`);
        }

        return { file, keys, renders };
    });
}

/** Where the code names a key — for the keys no list reaches: a module of the interface, or a server file. */
function references(modules, server, catalogueKeys) {
    const named = new Map();
    const add = (key, where) => {
        named.set(key, new Set([...(named.get(key) ?? []), where]));
    };
    const cache = new Map();

    for (const module of modules.values()) {
        const all = [module.always, ...module.decls.values()];

        for (const found of all) for (const key of found.keys) add(key, `\`${module.path}\``);
        for (const found of all) for (const key of expand(found.patterns, catalogueKeys, cache).keys()) add(key, `\`${module.path}\` (built at run time)`);
    }
    for (const each of server) for (const [key, where] of each.keys) add(key, where);

    return named;
}

/**
 * The pages the Localization specs audit (TASK-067; LOCALIZATION §3.6, §3.7): every `auditPage(page, language, label)` call
 * and each page code its label names — a label chosen at run time names every page it can be —, with the specs naming it.
 */
export function audited(sources) {
    const pages = new Map();

    for (const [path, source] of sources) {
        for (const call of source.matchAll(/\bauditPage\(\s*page\s*,\s*[^,]+,\s*([^,)]+)/g)) {
            for (const pg of new Set(call[1].match(/PG-\d{3}/g) ?? [])) pages.set(pg, sorted(new Set([...(pages.get(pg) ?? []), path])));
        }
    }

    return pages;
}

/** Everything the lists are compiled from, read from the repository. */
export function collect(root) {
    const contract = readContract(read(root, LOCALIZATION));
    const id = JSON.parse(read(root, 'lang/id.json'));
    const en = JSON.parse(read(root, 'lang/en.json'));
    const registry = JSON.parse(read(root, REGISTRY)).keys;
    const catalogue = new Set([...Object.keys(id), ...Object.keys(en)]);
    const catalogueKeys = sorted(catalogue);
    const groups = new Set(catalogueKeys.map((key) => key.split('.')[0]));
    const index = indexValidated(new Map([...contract.validated].map((key) => [key, { id: id[key], en: en[key] }])));

    // The new keys: the registry's, the inventory's added by an amendment after APPR-011, and any key of the string
    // files in neither — never a key on §5.1's list, which stays validated.
    const fresh = new Map();

    for (const key of sorted(Object.keys(registry))) {
        if (contract.inventory.has(key) && !contract.amended.has(key)) continue;
        const entry = registry[key];
        fresh.set(key, { kind: `Indonesian ${String(entry.indonesian)} · English ${String(entry.english)}`, source: `${String(entry.source)} (${String(entry.task)})`, task: String(entry.task), markdown: false });
    }
    for (const [key, row] of contract.amended) {
        const marker = /`(DIR-\d+)`/.exec(row.kind)?.[1] ?? '';
        fresh.set(key, { kind: row.kind, source: `${row.source} (LOCALIZATION §6, \`${marker}\`)`, task: marker, markdown: true });
    }
    for (const key of catalogueKeys) {
        if (!contract.inventory.has(key) && !fresh.has(key)) fresh.set(key, { kind: 'unregistered', source: `no record in \`${REGISTRY}\``, task: 'unregistered', markdown: true });
    }
    for (const [key, entry] of fresh) Object.assign(entry, { key, id: id[key], en: en[key] }, judge(id[key], en[key], index));

    // The code: every module under resources/js; a page is the file under Pages whose first line names it.
    const paths = files(root, CODE, (name) => /\.(ts|tsx)$/.test(name) && !name.endsWith('.d.ts'));
    const known = new Set(paths);
    const modules = new Map(paths.map((path) => [path, parseModule(path, read(root, path), catalogue, groups, known)]));
    const pages = new Map();

    for (const key of catalogueKeys) {
        const match = /^pages\.pg_(\d{3})\.title$/.exec(key);
        if (match !== null) pages.set(`PG-${match[1]}`, { pg: `PG-${match[1]}`, files: [] });
    }
    for (const path of paths.filter((each) => each.startsWith(`${PAGES}/`))) {
        const pg = /^\/\/ (PG-\d{3})\b/.exec(read(root, path))?.[1];
        if (pg === undefined) continue;
        if (!pages.has(pg)) pages.set(pg, { pg, files: [] });
        pages.get(pg).files.push(path);
    }

    const boundary = new Set([`${SHELL}#default`]);
    const cache = new Map();
    const usedBy = (entries, limit) => {
        const code = reach(modules, entries, limit);

        return { literal: code.keys, dynamic: expand(code.patterns, catalogueKeys, cache) };
    };
    const component = (path) => path.slice(PAGES.length + 1).replace(/\.tsx?$/, '');
    const server = scanServer(root, catalogueKeys, groups, new Set([...pages.values()].flatMap((page) => page.files.map(component))));
    const specs = files(root, SPECS, (name) => name.endsWith('.spec.ts'));
    const audits = audited(new Map(specs.map((path) => [path, read(root, path)])));

    for (const page of pages.values()) {
        const segment = `pg_${page.pg.slice(3)}`;
        page.audits = audits.get(page.pg) ?? [];
        const used = usedBy(page.files.map((path) => ({ path, name: '*' })), boundary);
        const names = new Set(page.files.map(component));
        page.shell = page.files.some((path) => [...(modules.get(path)?.imports.values() ?? [])].some((each) => each.from === SHELL));
        page.literal = used.literal;
        page.dynamic = used.dynamic;
        page.named = new Set(catalogueKeys.filter((key) => key.split('.').includes(segment)));
        page.server = new Map();

        for (const each of server.filter((file) => [...file.renders].some((name) => names.has(name)))) {
            for (const [key, where] of each.keys) page.server.set(key, [...(page.server.get(key) ?? []), where]);
        }

        page.keys = new Set([...page.literal, ...page.dynamic.keys(), ...page.named, ...page.server.keys()]);
    }

    const shellUse = usedBy([{ path: SHELL, name: 'default' }]);
    const shell = { literal: shellUse.literal, dynamic: shellUse.dynamic, named: new Set(), server: new Map(), keys: new Set([...shellUse.literal, ...shellUse.dynamic.keys()]), files: [SHELL] };
    const onPage = new Set([...shell.keys, ...[...pages.values()].flatMap((page) => [...page.keys])]);
    const unused = sorted([...fresh.keys()].filter((key) => !onPage.has(key)));
    const named = references(modules, server, catalogueKeys);

    const listed = [...pages.values()].sort((a, b) => byCode(a.pg, b.pg));

    return { contract, id, en, registry, fresh, index, pages: listed, shell, unused, named, unaudited: listed.filter((page) => page.audits.length === 0).map((page) => page.pg) };
}

const text = (value) => (typeof value === 'string' ? value.replaceAll('\\', '\\\\').replaceAll('|', '\\|').replaceAll('\n', ' ') : '*(missing)*');
const code = (value) => `\`${value}\``;
const some = (values, limit = 3) => (values.length > limit ? `${values.slice(0, limit).join(', ')} and ${String(values.length - limit)} more` : values.join(', '));
const plural = (count, one, other) => `${String(count)} ${count === 1 ? one : other}`;

/** The note a proposed key carries: how its list found it, and the validated key its Indonesian alone matches. */
function note(entry, scope, model) {
    const notes = [];

    if (scope !== null && !scope.literal.has(entry.key)) {
        const shown = scope.dynamic.get(entry.key);
        const server = scope.server.get(entry.key);

        if (shown !== undefined) notes.push(`named by a key built at run time, ${some(sorted(new Set(shown)).map(code), 2)}`);
        else if (server !== undefined) notes.push(`named by the server code that renders this page, ${some(sorted(new Set(server)), 2)}`);
        else if (scope.named.has(entry.key)) notes.push('named for this page; its code does not name it');
    }
    if (entry.state === 'proposed' && entry.near.length > 0) {
        notes.push(`its Indonesian is validated as ${some(entry.near.map((key) => `${code(key)} “${text(model.en[key])}”`), 2)}`);
    }

    return notes.join('; ');
}

/** One table of keys — the key, its two texts, its kind and source, then the list's own columns —, or "None." */
function table(entries, columns) {
    if (entries.length === 0) return ['None.'];

    return [
        `| Key | Indonesian | English | Kind | Source | ${columns.map(([name]) => name).join(' | ')} |`,
        `| --- | --- | --- | --- | --- | ${columns.map(() => '---').join(' | ')} |`,
        ...entries.map((entry) => {
            const fixed = [code(entry.key), text(entry.id), text(entry.en), entry.markdown ? entry.kind : text(entry.kind), entry.markdown ? entry.source : text(entry.source)];

            return `| ${[...fixed, ...columns.map(([, value]) => value(entry))].join(' | ')} |`;
        }),
    ];
}

/** A list's two tables: the proposed keys for the Owner's review, then the keys accepted by construction. */
function sections(keys, model, scope, where = null) {
    const entries = sorted(keys).filter((key) => model.fresh.has(key)).map((key) => model.fresh.get(key));
    const named = where === null ? [] : [['Named in', where]];
    const notes = ['Note', (entry) => note(entry, scope, model)];

    return [
        '## Proposed — for the Owner\'s review',
        '',
        ...table(entries.filter((entry) => entry.state === 'proposed'), [...named, notes]),
        '',
        '## Accepted by construction (OB-04)',
        '',
        ...table(entries.filter((entry) => entry.state === 'accepted'), [['Accepted as', (entry) => some(entry.basis.map(code))], ...named, notes]),
    ];
}

const GENERATED = 'Generated by `scripts/i18n/copy-lists.mjs` — never edited by hand; the rule, the method and every page are in the [index](README.md).';

/** Counts of a list: its new keys, proposed and accepted, and the validated keys it uses but does not list. */
function counts(keys, model) {
    const fresh = [...keys].filter((key) => model.fresh.has(key));
    const proposed = fresh.filter((key) => model.fresh.get(key).state === 'proposed').length;

    return { fresh: fresh.length, proposed, accepted: fresh.length - proposed, validated: [...keys].filter((key) => model.contract.validated.has(key)).length };
}

function tasks(keys, model) {
    return sorted(new Set([...keys].filter((key) => model.fresh.has(key)).map((key) => model.fresh.get(key).task)));
}

/**
 * A page's language items of AICWDF §27 and the checks behind each — TASK-067's evidence, which TASK-070 enters in
 * TASK-065's gate record; the checks' results are their CI runs'.
 */
function languageItems(page) {
    const specs = page.audits.length === 0 ? '**no Localization spec — the page is not audited**' : page.audits.map(code).join(', ');
    const change = page.shell
        ? 'the account\'s switch, kept in `users.locale` (INT-002; CJ-18)'
        : 'the sign-in pages\' switch, carried in the `bahasa` address key and stored nowhere (INT-001)';

    return [
        '| Item | Checked by |',
        '| --- | --- |',
        `| Indonesian default | \`tests/Feature/LocaleTest.php\`: Indonesian without a stored language and before any choice, a choice before sign-in never reaching the account (LOCALIZATION §2); the page rendered and audited in Indonesian by ${specs} |`,
        '| Plain Indonesian | the page\'s new keys below, each accepted by construction or proposed for the Owner\'s review (OB-04); its validated keys stay validated |',
        `| English available | the page rendered and audited in English by ${specs}, at the desktop width and at 390 px: no Indonesian interface string beyond LOCALIZATION §3.4's values, names and document type names |`,
        '| Missing translations: 0 | `tests/Frontend/i18n-keys.test.ts` over the code — every literal key in both string files with its placeholders, no sentence built from pieces —; the audit over the rendered page — no key shown as itself, no unfilled placeholder, no control without a name |',
        `| Language switch | ${change}; the layout in either language compared by the audit — the same regions and actions in the same order, no clipped label, no horizontal scroll outside a table, targets at their sizes |`,
    ];
}

function pageFile(page, model) {
    const count = counts(page.keys, model);
    const title = `pages.pg_${page.pg.slice(3)}.title`;
    const where = page.files.length === 0 ? 'no page file names this page; its list holds the keys named for it' : `${some(page.files.map(code), 9)}, with the components and features it renders`;
    const shellCount = counts(model.shell.keys, model).fresh;

    return [
        `# ${page.pg} — ${text(model.id[title])} · ${text(model.en[title])}`,
        '',
        GENERATED,
        '',
        `- **Page file:** ${where}.`,
        `- **Shell:** ${page.shell ? `inside the application shell; the shell's ${plural(shellCount, 'new key is', 'new keys are')} listed once, in [shell.md](shell.md).` : 'outside the application shell.'}`,
        `- **New keys:** ${String(count.fresh)} — ${String(count.proposed)} proposed, ${String(count.accepted)} accepted by construction${count.fresh === 0 ? '' : `; added by ${tasks(page.keys, model).join(', ')}`}.`,
        `- **Validated keys:** it also uses ${plural(count.validated, 'key', 'keys')} of LOCALIZATION §5.1's list, which stay validated and are not listed.`,
        '',
        '## Language items (AICWDF §27)',
        '',
        ...languageItems(page),
        '',
        ...sections(page.keys, model, page),
    ];
}

function shellFile(model) {
    const count = counts(model.shell.keys, model);
    const inside = model.pages.filter((page) => page.shell).length;

    return [
        '# The application shell',
        '',
        GENERATED,
        '',
        `- **Shell file:** ${code(SHELL)}, with the navigation, menus, search, notices and dialogs it renders around every page inside it — ${String(inside)} of the ${String(model.pages.length)} pages.`,
        `- **New keys:** ${String(count.fresh)} — ${String(count.proposed)} proposed, ${String(count.accepted)} accepted by construction${count.fresh === 0 ? '' : `; added by ${tasks(model.shell.keys, model).join(', ')}`}. Each page inside the shell shows them; they are reviewed once, here.`,
        `- **Validated keys:** it also uses ${plural(count.validated, 'key', 'keys')} of LOCALIZATION §5.1's list, which stay validated and are not listed.`,
        '',
        ...sections(model.shell.keys, model, model.shell),
    ];
}

function unusedFile(model) {
    const count = counts(model.unused, model);
    const where = (entry) => {
        const named = model.named.get(entry.key);

        return named === undefined ? 'named nowhere in the code' : some(sorted(named), 3);
    };

    return [
        '# Keys no page uses',
        '',
        GENERATED,
        '',
        `- **New keys:** ${String(count.fresh)} — ${String(count.proposed)} proposed, ${String(count.accepted)} accepted by construction — that no page's code, no component it renders, the shell and no key named for a page reach. A server answer, a validation message or a key built from recorded data is shown on a page the code alone does not tell; each key stands under OB-04 all the same, the proposed ones listed here for the Owner's review.`,
        '- **Named in:** the files that name the key — server code, a local fixture page, or code no page renders —, or "named nowhere in the code".',
        '',
        ...sections(model.unused, model, null, where),
    ];
}

function indexFile(model) {
    const fresh = [...model.fresh.values()];
    const proposed = fresh.filter((entry) => entry.state === 'proposed').length;
    const unused = counts(model.unused, model);
    const registry = fresh.filter((entry) => !entry.markdown).length;
    const amended = fresh.filter((entry) => entry.markdown && entry.task !== 'unregistered');
    const unregistered = fresh.filter((entry) => entry.task === 'unregistered').length;
    const inside = model.pages.filter((page) => page.shell).length;
    const row = (link, name, keys) => {
        const count = counts(keys, model);

        return `| ${link} | ${name} | ${String(count.fresh)} | ${String(count.proposed)} | ${String(count.accepted)} | ${String(count.validated)} |`;
    };
    const markers = sorted(new Set(amended.map((entry) => entry.task))).map(code).join(', ');

    return [
        '# Per-page copy lists — the copy written after APPR-011',
        '',
        `Generated by \`scripts/i18n/copy-lists.mjs\` — never edited by hand: \`node scripts/i18n/copy-lists.mjs\` rewrites these files, and \`node scripts/i18n/copy-lists.mjs --check\`, which \`tests/Frontend/copy-lists.test.ts\` runs, fails while they differ from what it would write. They are [TASK-067](${DOCS}/11-tasks/TASK-067.md)'s per-page lists of new copy, compiled for the Owner's review at the UAT ([TEST_STRATEGY §10](${DOCS}/08-testing/TEST_STRATEGY.md#10-localization), [§12](${DOCS}/08-testing/TEST_STRATEGY.md#12-devices-and-uat); [RELEASE_PLAN §8](${DOCS}/10-release/RELEASE_PLAN.md#8-uat)): one list per page, each new key with its Indonesian and English from \`lang/id.json\` and \`lang/en.json\`, its kind and source from \`${REGISTRY}\`, and its state.`,
        '',
        '## The rule',
        '',
        `[LOCALIZATION §5](${DOCS}/07-ux-design/LOCALIZATION.md#5-copy-validation-ca-04-items-710) quotes the Owner's rule for copy written after APPR-011 (\`DIR-081\` OB-04), word for word:`,
        '',
        `> «${model.contract.rule}»`,
        '',
        'Applied as written, key by key:',
        '',
        `- **Accepted by construction** — the key's Indonesian and English are, character for character, the Indonesian and the validated English of a key on [LOCALIZATION §5.1](${DOCS}/07-ux-design/LOCALIZATION.md#51-the-review-list-cb-15-cd-07)'s list — a GL term of §6.2, or a label §6 holds from an approved source, with the rendering the Owner validated under APPR-011 —; the list names that key.`,
        '- **Proposed** — every other new key, listed with its page for the Owner\'s review and accepted except the keys the Owner marks. The registry\'s "canonical" Indonesian is shown as the key\'s kind but does not accept a key alone: the rule asks for the English to be the term\'s or label\'s validated rendering, and every English text the registry records is proposed. Where the Indonesian alone is a validated key\'s, the note names that key and its validated English.',
        `- **Validated** — the ${String(model.contract.validated.size)} keys on §5.1's list stay validated and are never listed; the ${String(model.contract.off.length)} data or never-translated keys are not copy for review.`,
        '',
        '## How a key reaches a list',
        '',
        `- **The new keys** — every key of \`${REGISTRY}\` (${String(registry)}), the keys LOCALIZATION §6 marks with an amendment after APPR-011, ${markers} (${String(amended.length)}), and any key of the string files in neither (${String(unregistered)}).`,
        `- **A page** — the file under \`${PAGES}/\` whose first line names it (\`// PG-014 …\`). It uses the keys its file names and those the components and features it renders name, followed import by import to the declarations it uses: a literal key — \`t('…')\`, \`tc('…')\` or a key held in a constant — or a key built at run time, \`payments.tab.\${…}\`, shown as \`payments.tab.*\`, which names every key its pattern can. A key whose segment names a page, \`pages.pg_014.…\`, belongs to that page too, and so does a key the server code that renders the page names — an answer, a refusal or a validation message of its controller, exactly or as the prefix a key is built on. A local fixture page under \`${PAGES}/Fixtures/\` is no page of the application. A note says how a key not named by the page's own code reached its list.`,
        `- **The shell** — \`${SHELL}\`, rendered by the ${String(inside)} pages inside it: its keys are listed once, in [shell.md](shell.md), and not again on each page.`,
        '- **No page** — a new key no list reaches is listed in [unmapped.md](unmapped.md) with the files that name it.',
        '',
        '## Summary',
        '',
        '| Count | Value |',
        '| --- | --- |',
        `| Pages | ${String(model.pages.length)} — ${String(inside)} inside the shell, ${String(model.pages.length - inside)} outside it |`,
        `| New keys | ${String(fresh.length)} |`,
        `| Accepted by construction | ${String(fresh.length - proposed)} |`,
        `| Proposed for the Owner's review | ${String(proposed)} |`,
        `| Keys used by no page | ${String(unused.fresh)} — ${String(unused.proposed)} proposed, ${String(unused.accepted)} accepted |`,
        `| LOCALIZATION §5.1's validated keys | ${String(model.contract.validated.size)}, kept validated and not listed |`,
        `| Pages audited in both languages | ${String(model.pages.length - model.unaudited.length)} of ${String(model.pages.length)}${model.unaudited.length === 0 ? '' : ` — not audited: ${model.unaudited.join(', ')}`} |`,
        '',
        '## The lists',
        '',
        '| List | Indonesian · English | New keys | Proposed | Accepted | Validated keys used |',
        '| --- | --- | --- | --- | --- | --- |',
        ...model.pages.map((page) => {
            const title = `pages.pg_${page.pg.slice(3)}.title`;

            return row(`[${page.pg}](${page.pg}.md)`, `${text(model.id[title])} · ${text(model.en[title])}`, page.keys);
        }),
        row('[Shell](shell.md)', 'every page inside the shell', model.shell.keys),
        row('[No page](unmapped.md)', 'keys no list reaches', new Set(model.unused)),
    ];
}

/** Every file of the lists, by its name in the output folder. */
export function render(model) {
    const out = new Map([['README.md', indexFile(model)], ['shell.md', shellFile(model)], ['unmapped.md', unusedFile(model)]]);

    for (const page of model.pages) out.set(`${page.pg}.md`, pageFile(page, model));

    return new Map([...out].map(([name, lines]) => [name, `${lines.join('\n')}\n`]));
}

/** What differs between the folder and the lists: a file missing, stale, or no longer written. */
export function compare(folder, generated) {
    const problems = [];
    const present = existsSync(folder) ? readdirSync(folder).filter((name) => name.endsWith('.md')) : [];

    for (const [name, content] of generated) {
        const path = join(folder, name);

        if (!existsSync(path)) problems.push(`missing ${name}`);
        else if (readFileSync(path, 'utf8').replace(/\r\n/g, '\n') !== content) problems.push(`stale ${name}`);
    }
    for (const name of sorted(present)) if (!generated.has(name)) problems.push(`extra ${name}`);

    return problems;
}

export function write(folder, generated) {
    mkdirSync(folder, { recursive: true });
    for (const name of readdirSync(folder)) if (name.endsWith('.md') && !generated.has(name)) rmSync(join(folder, name));
    for (const [name, content] of generated) writeFileSync(join(folder, name), content);
}

function selfTest() {
    const failures = [];
    const expect = (condition, message) => {
        if (!condition) failures.push(message);
    };

    // OB-04: both texts a validated key's — accepted; the Indonesian alone, or neither — proposed.
    const index = indexValidated(new Map([
        ['glossary.gl_076', { id: 'Stok fisik', en: 'On hand' }],
        ['actions.cancel_remainder', { id: 'Batalkan Sisa', en: 'Cancel remainder' }],
        ['forms.company', { id: 'Perusahaan', en: 'Company' }],
        ['glossary.gl_033', { id: 'Perusahaan', en: 'Company' }],
    ]));
    const accepted = judge('Perusahaan', 'Company', index);
    expect(accepted.state === 'accepted' && accepted.basis.join() === 'glossary.gl_033,forms.company', 'a GL term with its validated English is accepted, the GL key first');
    const near = judge('Batalkan Sisa', 'Cancel Remainder', index);
    expect(near.state === 'proposed' && near.near.join() === 'actions.cancel_remainder', 'a validated Indonesian with another English is proposed, naming the validated key');
    expect(judge('Baru', 'New', index).state === 'proposed', 'copy no validated key holds is proposed');
    expect(judge('Stok fisik', undefined, index).state === 'proposed', 'a key without its English is proposed');

    // The contract: the rule's quote and §5.1's count are read; a count §6 does not bear out stops the script.
    const contract = [
        '**Copy written after APPR-011** (`DIR-081` OB-04) — the Owner\'s rule, word for word: «Rule text.» P11\'s template.',
        '**2 keys** are on the list; 1 are not: `documents.draft_marking`. The English…',
        '## 6. Key inventory',
        '| `glossary.gl_001` | Beranda | Home | canonical · en validated (PC-3) | IA §3 |',
        '| `status.export.failed` | Gagal | Failed | canonical · en validated (PC-3) | DESIGN_SYSTEM §12.1 |',
        '| `documents.draft_marking` | DRAF | DRAF | never translated (§3.4) | S2-J03 §8 |',
        '| `actions.abort_batch` | Batalkan Batch | Abort batch | canonical · en proposed (`DIR-100`) | ROUTE-276 |',
        '## 7. Counts',
    ].join('\n');
    const read = readContract(contract);
    expect(read.rule === 'Rule text.' && read.validated.size === 2 && read.amended.has('actions.abort_batch') && read.off.join() === 'documents.draft_marking', 'the rule, the validated, the amended and the off-list keys are read');
    let stopped = false;
    try {
        readContract(contract.replace('**2 keys**', '**3 keys**'));
    } catch {
        stopped = true;
    }
    expect(stopped, 'a §5.1 count §6 does not bear out stops the script');

    // The code: literal keys, a key held in a constant, a key built at run time, a type-only import that renders nothing,
    // the declarations a page uses and not their neighbours, and the shell's boundary.
    const catalogue = new Set(['x.a', 'x.b', 'x.held', 'x.tab.one', 'x.tab.two', 'x.shell', 'x.type']);
    const groups = new Set(['x']);
    const sources = {
        'resources/js/Pages/P.tsx': "import AppShell from '@/layouts/AppShell';\nimport { A } from '../lib';\nimport type { T } from './types';\nexport default function P() { const k: T = 'x.type' as T; return <AppShell><A />{k}</AppShell>; }",
        'resources/js/lib.tsx': "const HELD = { label: 'x.held' };\nexport function A(v) { return t(`x.tab.${v}`) + t(`x.${v ? 'a' : 'b'}` as K) + t('x.a') + HELD.label; }\nexport function B() { return t('x.b'); }",
        'resources/js/Pages/types.ts': "export type T = 'x.type';\nexport const unused = 'x.b';",
        'resources/js/layouts/AppShell.tsx': "export default function AppShell() { return t('x.shell'); }",
    };
    const known = new Set(Object.keys(sources));
    const modules = new Map(Object.entries(sources).map(([path, source]) => [path, parseModule(path, source, catalogue, groups, known)]));
    const page = reach(modules, [{ path: 'resources/js/Pages/P.tsx', name: '*' }], new Set([`${SHELL}#default`]));
    const literal = sorted(page.keys).join();
    expect(literal === 'x.a,x.held,x.type', `the page reaches its own and its components' literal keys only (${literal})`);
    const built = sorted(page.patterns.values()).join(' ');
    expect(built === 'x.tab.* x.{a,b}', `a key built at run time is kept as its pattern, a condition's branches spelled out (${built})`);
    const conditional = new RegExp([...page.patterns.keys()].find((source) => source.includes('(?:')) ?? '^$');
    expect(conditional.test('x.a') && conditional.test('x.b') && !conditional.test('x.held'), 'a condition\'s pattern names its branches\' keys only');
    expect(sorted(reach(modules, [{ path: SHELL, name: 'default' }]).keys).join() === 'x.shell', 'the shell reaches its own keys');

    // The audits: each page a label names, a label chosen at run time naming each of its pages, an options argument ignored.
    const audits = audited(new Map([
        ['a.spec.ts', "await auditPage(page, locale, 'PG-014');\nawait auditPage(page, 'id', 'PG-001 with the typed input', { phone: false });"],
        ['b.spec.ts', "await auditPage(page, lang, kind === 'totp' ? 'PG-002' : 'PG-004');\nawait auditPage(page, lang, 'PG-014');\nawait audit(page, 'id', 'PG-099');"],
    ]));
    const seen = sorted([...audits].map(([pg, specs]) => `${pg}:${specs.join('+')}`)).join(' ');
    expect(seen === 'PG-001:a.spec.ts PG-002:b.spec.ts PG-004:b.spec.ts PG-014:a.spec.ts+b.spec.ts', `the audited pages are read from the specs' calls (${seen})`);

    // The check: a stale, a missing and an extra file are each reported.
    const scratch = mkdtempSync(join(tmpdir(), 'copy-lists-'));
    try {
        write(scratch, new Map([['README.md', 'index\n'], ['PG-001.md', 'page\n']]));
        writeFileSync(join(scratch, 'PG-001.md'), 'edited by hand\n');
        writeFileSync(join(scratch, 'PG-099.md'), 'left over\n');
        const problems = compare(scratch, new Map([['README.md', 'index\n'], ['PG-001.md', 'page\n'], ['PG-002.md', 'page\n']]));
        expect(problems.join() === 'stale PG-001.md,missing PG-002.md,extra PG-099.md', `the check reports each difference (${problems.join()})`);
        expect(compare(scratch, new Map([['PG-001.md', 'edited by hand\n'], ['PG-099.md', 'left over\n'], ['README.md', 'index\n']])).length === 0, 'the same files pass the check');
    } finally {
        rmSync(scratch, { recursive: true, force: true });
    }

    for (const failure of failures) console.error(`FAIL: ${failure}`);
    if (failures.length === 0) console.log('PASS: the copy lists\' rules hold on their fixtures');

    return failures.length === 0 ? 0 : 1;
}

function main(argv) {
    if (argv.includes('--self-test')) return selfTest();
    const at = argv.indexOf('--out');
    const folder = at >= 0 && argv[at + 1] !== undefined ? resolve(argv[at + 1]) : join(ROOT, OUT);
    const model = collect(ROOT);
    const generated = render(model);
    const shown = relative(process.cwd(), folder) || '.';

    if (model.unaudited.length > 0) {
        console.error(`FAIL: no Localization spec audits ${model.unaudited.join(', ')} (tests/Browser/localization-audit.ts; LOCALIZATION §3.6, §3.7)`);

        return 1;
    }

    if (argv.includes('--check')) {
        const problems = compare(folder, generated);

        if (problems.length > 0) {
            console.error(`FAIL: the copy lists in ${shown} differ from what scripts/i18n/copy-lists.mjs writes — run it to write them:`);
            for (const problem of problems) console.error(`  ${problem}`);

            return 1;
        }

        console.log(`PASS: the copy lists in ${shown} are current (${String(generated.size)} files)`);

        return 0;
    }

    write(folder, generated);
    const fresh = [...model.fresh.values()];
    const proposed = fresh.filter((entry) => entry.state === 'proposed').length;
    console.log(`Wrote ${String(generated.size)} files to ${shown}: ${String(model.pages.length)} pages; ${String(fresh.length)} new keys, ${String(fresh.length - proposed)} accepted by construction, ${String(proposed)} proposed; ${String(model.unused.length)} used by no page`);

    return 0;
}

if (process.argv[1] !== undefined && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    process.exitCode = main(process.argv.slice(2));
}
