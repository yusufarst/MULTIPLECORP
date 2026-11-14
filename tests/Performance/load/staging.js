// The staging measurements TASK-079's operator runs (TASK-068; PERFORMANCE_TARGETS PFM-01 times, PFM-13; PERFORMANCE AS-03 and
// QB-01–QB-10's times): never CI, which records no time target (PERFORMANCE_TARGETS §1). Two modes:
//
//   MODE=warm  PFM-01 — each page's cold request apart, then 50 warm requests one after another; its p95 against the page's
//              server-time budget
//   MODE=peak  PFM-13 — AS-03's peak, 10 requests a second for DURATION: 8 page reads across the pages, 2 commands (the
//              account's own language, set to the language it already has); every p95 within its budget, no error
//
// It signs in as one persona — EMAIL and PASSWORD, an account without a second step — or uses COOKIE, the Cookie header of
// a session the operator opened in a browser. BASE_URL is the environment's address; PROJECT_PATH, INVOICE_PATH and
// SCAN_PATH add the detail pages and the scanner lookup of the staging data. INSECURE=1 accepts a local stack's own
// certificate. Example, on the local stack:
//
//   docker compose run --rm -e MODE=warm -e BASE_URL=https://web:8443 -e INSECURE=1 -e EMAIL=… -e PASSWORD=… k6 run tests/Performance/load/staging.js
/* global __ENV */
import { check, fail } from 'k6';
import http from 'k6/http';

const base = __ENV.BASE_URL || 'https://web:8443';
const mode = __ENV.MODE || 'warm';
// PAGES limits the run to the named budgets (e.g. PAGES=QB-01,QB-10), the ones the persona's capabilities open.
const chosen = (__ENV.PAGES || '').split(',').map((name) => name.trim()).filter(Boolean);
const pages = [
    { name: 'QB-01', path: '/proyek', budget: 300 },
    { name: 'QB-05', path: '/gudang/stok', budget: 300 },
    { name: 'QB-07', path: '/keuangan/piutang', budget: 300 },
    { name: 'QB-10', path: '/beranda', budget: 300 },
    ...(__ENV.PROJECT_PATH ? [{ name: 'QB-02', path: __ENV.PROJECT_PATH, budget: 400 }] : []),
    ...(__ENV.INVOICE_PATH ? [{ name: 'QB-09', path: __ENV.INVOICE_PATH, budget: 400 }] : []),
    ...(__ENV.SCAN_PATH ? [{ name: 'QB-06', path: __ENV.SCAN_PATH, budget: 150 }] : []),
].filter((page) => chosen.length === 0 || chosen.includes(page.name));

// The sign-in's own answers — an Inertia 409 among them — are not the measurement's.
const thresholds = { 'http_req_failed{phase:warm}': ['rate==0'] };
for (const page of pages) {
    thresholds[`http_req_duration{page:${page.name},phase:warm}`] = [`p(95)<${page.budget}`];
}

export const options = {
    insecureSkipTLSVerify: __ENV.INSECURE === '1',
    thresholds,
    scenarios:
        mode === 'peak'
            ? {
                  pages: { executor: 'constant-arrival-rate', exec: 'read', rate: 8, timeUnit: '1s', duration: __ENV.DURATION || '5m', preAllocatedVUs: 20 },
                  commands: { executor: 'constant-arrival-rate', exec: 'command', rate: 2, timeUnit: '1s', duration: __ENV.DURATION || '5m', preAllocatedVUs: 10 },
              }
            : { warm: { executor: 'per-vu-iterations', exec: 'warm', vus: 1, iterations: 1, maxDuration: '30m' } },
};

const inertia = { 'X-Requested-With': 'XMLHttpRequest' };

function xsrf(jar) {
    const value = jar.cookiesForURL(base)['XSRF-TOKEN'];
    return value ? decodeURIComponent(value[0]) : '';
}

// One session for the whole run, its cookies handed to every virtual user.
export function setup() {
    if (__ENV.COOKIE) {
        return { cookie: __ENV.COOKIE };
    }
    if (!__ENV.EMAIL || !__ENV.PASSWORD) {
        fail('Give EMAIL and PASSWORD of a persona without a second step, or COOKIE of an open session.');
    }
    const jar = http.cookieJar();
    http.get(`${base}/masuk`);
    const answer = http.post(`${base}/masuk`, JSON.stringify({ email: __ENV.EMAIL, password: __ENV.PASSWORD }), {
        headers: { ...inertia, 'Content-Type': 'application/json', 'X-Inertia': 'true', 'X-XSRF-TOKEN': xsrf(jar) },
        redirects: 0,
    });
    const landing = String(answer.headers.Location || answer.headers['X-Inertia-Location'] || '');
    if (![302, 303, 409].includes(answer.status) || /\/masuk(\/|$|\?)/.test(landing)) {
        fail(`The sign-in answered ${answer.status} towards "${landing}": check the persona, its password and that it needs no second step.`);
    }
    const cookies = jar.cookiesForURL(base);
    const session = { cookie: Object.keys(cookies).map((name) => `${name}=${cookies[name][0]}`).join('; ') };
    for (const page of pages) {
        const probe = http.get(`${base}${page.path}`, { headers: { Cookie: session.cookie }, redirects: 0 });
        if (probe.status !== 200) {
            fail(`The persona cannot open ${page.path} (${probe.status}): choose one whose capabilities open it, or leave it out with PAGES.`);
        }
    }

    return session;
}

function get(session, page, phase) {
    const response = http.get(`${base}${page.path}`, { headers: { Cookie: session.cookie }, redirects: 0, tags: { page: page.name, phase } });
    check(response, { [`${page.name} answers 200`]: (r) => r.status === 200 });
}

export function warm(session) {
    for (const page of pages) {
        get(session, page, 'cold');
        for (let n = 0; n < Number(__ENV.WARM || 50); n += 1) {
            get(session, page, 'warm');
        }
    }
}

export function read(session) {
    get(session, pages[Math.floor(Math.random() * pages.length)], 'warm');
}

export function command(session) {
    const token = (session.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '';
    const response = http.put(`${base}/akun/bahasa`, JSON.stringify({ locale: 'id' }), {
        headers: { ...inertia, Cookie: session.cookie, 'Content-Type': 'application/json', 'X-Inertia': 'true', 'X-XSRF-TOKEN': decodeURIComponent(token) },
        redirects: 0,
        tags: { page: 'command', phase: 'warm' },
    });
    check(response, { 'the command answers': (r) => [200, 302, 303].includes(r.status) });
}
