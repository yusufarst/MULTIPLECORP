// The release gate (RELEASE_PLAN §2; INF-03; RL-01; DIR-083 OE-07; TASK-073): a release is a tagged commit of `main`
// whose CI passed. The gate accepts a tag only when its commit is on `main` and that commit's CI run on `main` — the push
// run of ci.yml — completed with success; anything else is refused with its reason, a run still going included. It
// reads the repository's history and the CI's runs, and changes nothing: no deploy, migration, import, backup or
// restore (RL-01).
//
//   node scripts/release/gate.mjs --repo <owner/name> --sha <commit> [--main <ref>]
//   node scripts/release/gate.mjs --self-test
import { execFileSync } from 'node:child_process';
import { appendFileSync } from 'node:fs';

/**
 * The gate's decision from the facts it read.
 *
 * @param {{ onMain: boolean, runs: { id: number, status: string, conclusion: string | null, event: string, head_branch: string }[] }} facts
 * @returns {{ accepted: boolean, reason: string, run: number | null }}
 */
export function decide({ onMain, runs }) {
    if (!onMain) {
        return { accepted: false, reason: 'the tagged commit is not a commit of main (RELEASE_PLAN §2; INF-03)', run: null };
    }

    const onMainRuns = runs.filter((run) => run.event === 'push' && run.head_branch === 'main').sort((a, b) => b.id - a.id);
    const latest = onMainRuns[0];

    if (latest === undefined) {
        return { accepted: false, reason: 'no CI run of this commit on main — a check that never ran is not passed (GAP-017)', run: null };
    }

    if (latest.status !== 'completed') {
        return { accepted: false, reason: `the commit's CI run ${latest.id} on main has not finished (${latest.status})`, run: latest.id };
    }

    if (latest.conclusion !== 'success') {
        return { accepted: false, reason: `the commit's CI run ${latest.id} on main did not pass (${latest.conclusion})`, run: latest.id };
    }

    return { accepted: true, reason: `a commit of main whose CI run ${latest.id} passed`, run: latest.id };
}

function argument(name) {
    const at = process.argv.indexOf(name);

    return at >= 0 ? process.argv[at + 1] : undefined;
}

function onMain(sha, main) {
    try {
        execFileSync('git', ['merge-base', '--is-ancestor', sha, main], { stdio: 'ignore' });

        return true;
    } catch {
        return false;
    }
}

function runs(repo, sha) {
    const path = `repos/${repo}/actions/workflows/ci.yml/runs?head_sha=${sha}&branch=main&event=push&per_page=20`;
    const answer = JSON.parse(execFileSync('gh', ['api', path], { encoding: 'utf8' }));

    return (answer.workflow_runs ?? []).map((run) => ({ id: run.id, status: run.status, conclusion: run.conclusion, event: run.event, head_branch: run.head_branch }));
}

function selfTest() {
    const run = (id, status, conclusion, event = 'push', branch = 'main') => ({ id, status, conclusion, event, head_branch: branch });
    const cases = [
        ['a commit off main', { onMain: false, runs: [run(1, 'completed', 'success')] }, false],
        ['a commit of main with no CI run', { onMain: true, runs: [] }, false],
        ['a commit of main whose only run is a pull request\'s', { onMain: true, runs: [run(1, 'completed', 'success', 'pull_request', 'feature')] }, false],
        ['a commit of main whose CI is still running', { onMain: true, runs: [run(1, 'in_progress', null)] }, false],
        ['a commit of main whose CI failed', { onMain: true, runs: [run(1, 'completed', 'failure')] }, false],
        ['a commit of main whose CI was cancelled', { onMain: true, runs: [run(1, 'completed', 'cancelled')] }, false],
        ['a commit of main whose latest run failed after an earlier pass', { onMain: true, runs: [run(1, 'completed', 'success'), run(2, 'completed', 'failure')] }, false],
        ['a commit of main whose CI passed', { onMain: true, runs: [run(1, 'completed', 'failure'), run(2, 'completed', 'success')] }, true],
    ];
    const failures = cases.filter(([, facts, accepted]) => decide(facts).accepted !== accepted).map(([label]) => label);

    if (failures.length > 0) {
        console.error(`FAIL: the gate decided wrongly for ${failures.join('; ')}`);
        process.exit(1);
    }

    console.log(`PASS: the release gate refuses a tag off main and on a commit whose CI did not pass, and accepts a passed commit of main (${cases.length} cases)`);
}

if (process.argv.includes('--self-test')) {
    selfTest();
} else {
    const repo = argument('--repo');
    const sha = argument('--sha');

    if (repo === undefined || sha === undefined || !/^[0-9a-f]{7,40}$/.test(sha)) {
        console.error('usage: node scripts/release/gate.mjs --repo <owner/name> --sha <commit> [--main <ref>]');
        process.exit(2);
    }

    // The CI's runs are named by the full commit, which an abbreviated one never matches.
    const full = execFileSync('git', ['rev-parse', '--verify', `${sha}^{commit}`], { encoding: 'utf8' }).trim();
    const decision = decide({ onMain: onMain(full, argument('--main') ?? 'origin/main'), runs: runs(repo, full) });
    console.log(`${decision.accepted ? 'ACCEPTED' : 'REFUSED'}: ${full} — ${decision.reason}`);

    if (process.env.GITHUB_OUTPUT !== undefined && decision.run !== null) {
        appendFileSync(process.env.GITHUB_OUTPUT, `run=${decision.run}\n`);
    }

    process.exit(decision.accepted ? 0 : 1);
}
