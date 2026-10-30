# Release records

Status: REVIEW | Updated: 2026-10-30 | Owner: Planning

Written by [TASK-073](../../11-tasks/TASK-073.md) under DIR-096: one folder per release, `records/<tag>/`, made from [`_template/`](_template/RELEASE_RECORD.md) at REL-01 and completed at REL-12 ([RELEASE_PLAN](../RELEASE_PLAN.md) PRQ-14; REL-02; REL-12; INF-03). The [release process](../../09-operations/runbooks/RELEASE_PROCESS.md) orders the steps.

| File | Holds | Written by |
| --- | --- | --- |
| `RELEASE_RECORD.md` | the commit and its tag, the schema version, both declarations, the rollback target with its verified pre-deployment backup, the report's status, the Owner's approval, the rehearsal's durations, the deployment window, the verification, the first business day watched, the contract step and the actual effort beside the estimates | the executor and the operator |
| `declarations.json` | the release's Tasks (`tasks`, the report's TASKS), RL-03's two declarations with the Owner's approval of an exception (`db_approval`, `cost_approval`) and that no coding agent mutated the production database; left `null`, a declaration reads NOT RUN | the executor; an exception with the Owner's approval |
| `staging-summary.json` | Layer 3's and the drills' counts on staging — journeys, UI/UX, browser, performance, integrity —, whole counts only; left `null`, a section reads NOT RUN, never a measured zero | the operator |
| `release-integrity-report.md` | RELEASE_PLAN §11's report, compiled by `scripts/release/report.mjs`, never edited | the script |

Nothing in a record holds a secret, a host detail, a path on a host or real data (SX-06; RL-05); the secret-like scan of every commit reads these files too.
