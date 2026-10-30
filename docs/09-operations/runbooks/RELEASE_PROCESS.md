# Release process

Status: REVIEW | Updated: 2026-10-30 | Owner: Planning

Written by [TASK-073](../../11-tasks/TASK-073.md) under DIR-096: the process part of the release runbook every release follows, from [RELEASE_PLAN](../../10-release/RELEASE_PLAN.md) §§1–4 and §11 and [INFRASTRUCTURE §5](../INFRASTRUCTURE.md#5-the-deployment-path). The operator's deployment steps REL-08–REL-13 in detail are TASK-072's runbook; this page orders them and names what the release record keeps of each. Every value here is a placeholder or a repository path — no secret, host detail or real data (SX-06; RL-05).

## 1. Branches and tags

- **Branches** (RELEASE_PLAN §2; DIR-083 OE-07): work happens on `task/*`, `feature/*` or `hotfix/*` branches, each merged into `main` through a pull request whose CI — TEST_STRATEGY Layer 1, the three jobs of `.github/workflows/ci.yml` — passed. `main` is the one long-lived branch, protected as [BRANCH_PROTECTION](BRANCH_PROTECTION.md) lists; there is no `develop` branch.
- **A release is a tag** on a commit of `main` whose CI passed. The tag's name — the executor's routine choice recorded here — is `v<YYYY>.<MM>.<DD>-<n>`: the business day the release is cut in WIB and its number that day from 1, for example `v2026.11.20-1`. A hotfix release is a later tag on `main` after the hotfix branch merges; nothing is released from a branch.
- **The gate** (`.github/workflows/release.yml`; INF-03; RL-01): a pushed tag runs `scripts/release/gate.mjs`, which refuses a commit that is not on `main` and one whose CI run on `main` is missing, still running, failed or cancelled. An accepted tag gets the report's CI part (§4). A tag pushed before its commit's CI run on `main` completes is refused; once that run has passed, re-run the refused Release run (`gh run rerun <release-run>`), which reads the runs again — the tag is never moved or pushed again. CI itself runs on branches and pull requests, never on a tag. No workflow deploys, migrates, imports, backs up, restores or reaches staging or production; the operator alone deploys, from `main` (RL-01).
- **A gate check** uses a `release-check/<task>-<n>` tag, which runs the same gate and names no release; such tags are never deployed and never enter a release record.

## 2. The sequence and what the record keeps

Each release follows REL-01–REL-13 in order; a failed step stops the release for the operator's decision (RELEASE_PLAN §6). The release record is a folder `docs/10-release/records/<tag>/` made from [the template](../../10-release/records/README.md).

| REL | Who | Check | The record keeps |
| --- | --- | --- | --- |
| REL-01 Ready | the executor | the release's required Tasks DONE — listed in `declarations.json`'s `tasks`, each DONE in the Task plan's table, and no Task BLOCKED or VERIFYING in its counters (TASKS); CI green on the release commit — the gate's accepted run; Layer 2 passed for every module the release touches (TEST_STRATEGY §3); DEFINITION_OF_DONE §5's aggregation for each module counted | the CI run, the modules and their Layer 2 runs |
| REL-02 Declarations | the executor, the Owner's approval for an exception | `DB CHANGE: NONE / APPROVED CONTROLLED MIGRATION`, a migration naming the Owner's approval in `db_approval`; `ADDITIONAL RECURRING COST CHANGE: NONE / APPROVED EXCEPTION`, an exception naming it in `cost_approval` (RL-03; COST_POLICY); a declaration left `null`, as the template's are, reads NOT RUN; the rollback target — the previous release's commit, its schema version (the last migration's name) and the pre-deployment backup REL-08 verifies (BK-03) | `declarations.json` and the record's rollback target |
| REL-03 Staging rehearsal | the operator | the release deployed on staging by the production steps, with the interrupted-migration case, a page posting across the deployment and a rollback by RBK-01 (HO-31) | each step's measured duration |
| REL-04 Layer 3 and drills | the operator | Layer 3 on staging; the failure and restore drills for the first release and after a change to alerting or backup tooling (RELEASE_PLAN §7) | `staging-summary.json` — journeys, UI/UX, browser, performance and integrity counts, no secret |
| REL-05 UAT | the Owner and the validators | whenever the release changes what a user does or sees (RELEASE_PLAN §8; TEST_STRATEGY §12) | the UAT record's reference |
| REL-06 Report | the executor | `scripts/release/report.mjs` over the CI evidence, the declarations and the staging summary (§4) | `release-integrity-report.md` |
| REL-07 Approval | the Owner | the report PASS, the declarations and the UAT record | the approval's date and words |
| REL-08 Backup | the operator | a pre-deployment backup whose verification completed; a failed or unverified one stops the deployment (BK-03) | the backup's identifier and its verified completion time, no path or host |
| REL-09 Drain | the operator | in-flight cascades drained, the queue at rest (HO-34) | the drain's result |
| REL-10 Deploy | the operator | INFRASTRUCTURE §5 steps 3–5 in the announced window outside business hours for the first three months (DIR-083 OE-08) | the window and the deployed commit |
| REL-11 Verify | the operator | the heartbeat's health and smoke checks; `ReconcileGuards` after a release that changes a posting service; WS-10's gates and GLC-07's items read on production | each check's result |
| REL-12 Monitor and record | the operator, the executor | the first business day watched; the deployed commit — the tagged commit of `main` the report covers (INF-03) — and its schema version; the actual effort beside the estimates (GAP-014) | the record completed; one entry in the [change log](../../10-release/CHANGE_LOG.md) |
| REL-13 Contract later | the executor | a contract migration only in a later release (RL-08) | the contract step's release, when there is one |

## 3. The principles as checks

| RL | The check at each release |
| --- | --- |
| RL-01 | no workflow step deploys, migrates, imports, backs up or restores; the deployed commit is the tagged commit of `main` the gate accepted |
| RL-02 | no date shortens REL-01–REL-07; a scope or date tradeoff goes to the Owner with its evidence, recorded in the record's notes |
| RL-03 | both declarations in `declarations.json`; the report's DATABASE and COST sections read them |
| RL-04 | the record's rollback target is code and schema only; data is recovered only by BACKUP_RECOVERY's procedure on the Owner's decision |
| RL-05 | the record, the report and the change log hold synthetic or summary values only; the secret-like scan (`scripts/ci/scan-secrets.sh`) passes on them |
| RL-06 | RELEASE STATUS PASS and the Owner's approval, nothing else, let REL-08 start |
| RL-07 | a change outside the release's Tasks after the freeze enters only on RL-07's grounds with the Owner's approval (DIR-083 OE-20), named in the record |
| RL-08 | the release's migrations are expand-only; a contract step names its later release |

## 4. Compiling the report

1. When the tag is pushed, the gate accepts it and `release.yml` downloads its CI run's evidence — each job's `summary-*.txt` kept 90 days — and uploads `release-integrity-report.md` as the run's artifact. Before staging its staging sections read NOT RUN, so its status is FAIL: that is expected and is not the release's report.
2. After REL-03–REL-05 the operator writes `docs/10-release/records/<tag>/staging-summary.json` — whole counts only, no secret, host detail or real data; a count left `null`, as the template's are, keeps its section NOT RUN; CRITICAL JOURNEYS needs all 22 of CJ-01–CJ-22 counted, and PERFORMANCE each case of PERFORMANCE_TARGETS §4's release-critical set — the count of its measurements outside target —, PFM-03's plans too when the release changes the schema — and the executor commits it with `declarations.json`.
3. The executor compiles the release's report from the same CI run's evidence — the gate's accepted run, whose summaries name the release commit; a check whose summary names another commit reads NOT RUN, so evidence downloaded for the wrong run never passes:

   ```sh
   gh run download <ci-run> --repo <owner>/<repository> --dir evidence
   node scripts/release/report.mjs --evidence evidence --commit <commit> --tag <tag> \
     --declarations docs/10-release/records/<tag>/declarations.json \
     --staging docs/10-release/records/<tag>/staging-summary.json \
     --out docs/10-release/records/<tag>/release-integrity-report.md
   ```

4. The report is never edited by hand. A section with a nonzero failure count reads FAIL; a section without its run's evidence reads NOT RUN and is never counted as passed (GAP-017; TEST_STRATEGY §16); RELEASE STATUS is PASS only when all thirteen sections of RELEASE_PLAN §11 are.
