// Which artifact is each browser layer's evidence (DIR-103; DIR-105). A rerun keeps the earlier attempt's artifact of the same
// name in the run, so the name alone does not say which is current: a layer's artifact is the one its latest job uploaded —
// this run, this head, not expired, created while that job ran, the job itself a success. Anything else is a problem and the
// check fails: no layer falls back to an older result. It prints "<id> <name>" per layer for the aggregator to fetch.
//
//   node scripts/ci/select-browser-evidence.mjs --artifacts <ndjson> --jobs <ndjson> --run <run id> --head <sha>
//   node scripts/ci/select-browser-evidence.mjs --self-test
import { readFileSync } from 'node:fs';

export const LAYERS = ['e2e', 'localization', 'responsive', 'accessibility'];

const at = (value) => Date.parse(value ?? '');

/** @returns {{ chosen: { id: number, name: string }[], problems: string[] }} */
export function select(artifacts, jobs, run, head) {
    const chosen = [];
    const problems = [];

    for (const layer of LAYERS) {
        const name = `browser-evidence-${layer}`;
        const latest = jobs.filter((job) => job.name === `Browser layer — ${layer}`);

        if (latest.length !== 1) {
            problems.push(`${layer}: ${latest.length} latest jobs, not one`);
            continue;
        }

        const [job] = latest;

        if (job.conclusion !== 'success') {
            problems.push(`${layer}: its latest job's conclusion is ${job.conclusion ?? 'missing'}`);
            continue;
        }

        const from = at(job.started_at);
        const to = at(job.completed_at);

        if (Number.isNaN(from) || Number.isNaN(to)) {
            problems.push(`${layer}: its latest job has no start or end`);
            continue;
        }

        const own = artifacts.filter((artifact) => artifact.name === name && at(artifact.created_at) >= from && at(artifact.created_at) <= to);

        if (own.length !== 1) {
            problems.push(`${layer}: ${own.length} artifacts from its latest job, not one`);
            continue;
        }

        const [artifact] = own;

        if (artifact.expired === true) {
            problems.push(`${layer}: its artifact has expired`);
        } else if (String(artifact.workflow_run?.id) !== String(run)) {
            problems.push(`${layer}: its artifact is of run ${artifact.workflow_run?.id ?? 'unknown'}, not ${run}`);
        } else if (artifact.workflow_run?.head_sha !== head) {
            problems.push(`${layer}: its artifact is of ${artifact.workflow_run?.head_sha ?? 'an unknown commit'}, not ${head}`);
        } else {
            chosen.push({ id: artifact.id, name });
        }
    }

    return { chosen, problems };
}

function selfTest() {
    const head = 'abcdef1234567890abcdef1234567890abcdef12';
    const job = (layer, started, completed, conclusion = 'success') => ({ name: `Browser layer — ${layer}`, conclusion, started_at: `2026-11-01T${started}Z`, completed_at: `2026-11-01T${completed}Z` });
    const artifact = (layer, id, created, extra = {}) => ({ id, name: `browser-evidence-${layer}`, expired: false, created_at: `2026-11-01T${created}Z`, workflow_run: { id: 7, head_sha: head }, ...extra });
    const jobs = LAYERS.map((layer) => job(layer, '10:00:00', '10:10:00'));
    const artifacts = LAYERS.map((layer, index) => artifact(layer, index + 1, '10:09:00'));
    const expect = (name, found, ids) => {
        const got = found.problems.length > 0 ? 'FAIL' : found.chosen.map((one) => one.id).join(',');
        if (got !== ids) throw new Error(`${name}: ${got} (${found.problems.join('; ')})`);
    };

    expect('one artifact per layer', select(artifacts, jobs, 7, head), '1,2,3,4');

    // The case DIR-105 records: the rerun's job is the latest, its older attempt's failing artifact listed with it, in either order.
    const rerun = [job('e2e', '11:00:00', '11:10:00'), ...jobs.slice(1)];
    const stale = artifact('e2e', 1, '10:09:00');
    const fresh = artifact('e2e', 9, '11:09:00');
    expect('a rerun: the latest job\'s artifact, listed first', select([fresh, stale, ...artifacts.slice(1)], rerun, 7, head), '9,2,3,4');
    expect('a rerun: the latest job\'s artifact, listed last', select([stale, ...artifacts.slice(1), fresh], rerun, 7, head), '9,2,3,4');
    expect('a rerun that uploaded nothing never falls back', select([stale, ...artifacts.slice(1)], rerun, 7, head), 'FAIL');

    expect('no artifact', select(artifacts.slice(1), jobs, 7, head), 'FAIL');
    expect('no job', select(artifacts, jobs.slice(1), 7, head), 'FAIL');
    expect('two latest jobs', select(artifacts, [...jobs, jobs[0]], 7, head), 'FAIL');
    expect('two artifacts of one job', select([artifact('e2e', 9, '10:09:30'), ...artifacts], jobs, 7, head), 'FAIL');
    for (const conclusion of ['failure', 'cancelled', 'skipped', null]) expect(`a job ${conclusion}`, select(artifacts, [job('e2e', '10:00:00', '10:10:00', conclusion), ...jobs.slice(1)], 7, head), 'FAIL');
    expect('a job with no end', select(artifacts, [{ ...jobs[0], completed_at: null }, ...jobs.slice(1)], 7, head), 'FAIL');
    expect('an expired artifact', select([artifact('e2e', 1, '10:09:00', { expired: true }), ...artifacts.slice(1)], jobs, 7, head), 'FAIL');
    expect('another run\'s artifact', select([artifact('e2e', 1, '10:09:00', { workflow_run: { id: 8, head_sha: head } }), ...artifacts.slice(1)], jobs, 7, head), 'FAIL');
    expect('another commit\'s artifact', select([artifact('e2e', 1, '10:09:00', { workflow_run: { id: 7, head_sha: '1234567' } }), ...artifacts.slice(1)], jobs, 7, head), 'FAIL');

    console.log('PASS: a layer\'s evidence is its latest job\'s artifact on this run and commit, with no fallback');
}

function argument(name) {
    const index = process.argv.indexOf(name);

    return index > -1 ? process.argv[index + 1] : undefined;
}

const rows = (file) => readFileSync(file, 'utf8').split('\n').filter((line) => line.trim() !== '').map((line) => JSON.parse(line));

if (process.argv[1]?.endsWith('select-browser-evidence.mjs')) {
    if (process.argv.includes('--self-test')) {
        selfTest();
    } else {
        const [artifacts, jobs, run, head] = ['--artifacts', '--jobs', '--run', '--head'].map(argument);

        if (artifacts === undefined || jobs === undefined || run === undefined || head === undefined) {
            console.error('usage: node scripts/ci/select-browser-evidence.mjs --artifacts <ndjson> --jobs <ndjson> --run <run id> --head <sha>');
            process.exit(2);
        }

        const found = select(rows(artifacts), rows(jobs), run, head);

        if (found.problems.length > 0) {
            found.problems.forEach((problem) => { console.error(`FAIL: ${problem}`); });
            process.exit(1);
        }

        found.chosen.forEach((one) => { console.log(`${one.id} ${one.name}`); });
    }
}
