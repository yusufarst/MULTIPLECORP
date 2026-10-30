# Release record — <tag>

Status: REVIEW | Updated: <YYYY-MM-DD> | Owner: Planning

The record of one release (RELEASE_PLAN PRQ-14; REL-02; REL-12; INF-03), copied from `docs/10-release/records/_template/` into `docs/10-release/records/<tag>/`. Placeholders are in angle brackets; no secret, host detail or real data (SX-06; RL-05).

## The release

| Field | Value |
| --- | --- |
| Tag | `<tag>` |
| Commit — the tagged commit of `main` the gate accepted and the report covers (INF-03) | `<commit>` |
| The gate's accepting run | `<release workflow run>` |
| The commit's CI run on `main` | `<ci run>` |
| Schema version — the last migration's name | `<migration>` |
| The release's Tasks — as `declarations.json`'s `tasks` lists them, the report's TASKS | `<TASK-…>` |
| Modules counted, each with its Layer 2 run (REL-01; DEFINITION_OF_DONE §5) | `<module — run>` |

## Declarations (REL-02; RL-03)

| Declaration | Value |
| --- | --- |
| DB CHANGE | `NONE` / `APPROVED CONTROLLED MIGRATION — <the Owner's approval>` |
| ADDITIONAL RECURRING COST CHANGE | `NONE` / `APPROVED EXCEPTION — <the Owner's approval>` |
| Direct production DB mutation by a coding agent | `NO` |

## Rollback target (REL-02; BK-03; RL-04)

| Field | Value |
| --- | --- |
| Previous release's tag and commit | `<tag>` · `<commit>` |
| Its schema version | `<migration>` |
| The pre-deployment backup | `<backup identifier>`, verification completed `<YYYY-MM-DD HH:MM WIB>` — a failed or unverified backup stops the deployment |

A code rollback is not data recovery: data is recovered only by BACKUP_RECOVERY's procedure, by the operator, on the Owner's decision (RL-04).

## Evidence and approval

| Step | Result |
| --- | --- |
| REL-03 Staging rehearsal — each step's measured duration | `<step — minutes>` |
| REL-04 Layer 3 and drills | `staging-summary.json` |
| REL-05 UAT | `<the UAT record>` / not required: `<reason>` |
| REL-06 The release integrity report | `release-integrity-report.md` — RELEASE STATUS `<PASS / FAIL>` |
| REL-07 The Owner's release approval | `<YYYY-MM-DD>` — `<the Owner's words>` |

## Deployment and after (REL-08–REL-13)

| Step | Result |
| --- | --- |
| REL-08 Pre-deployment backup verified | `<yes, at …>` |
| REL-09 Drain | `<result>` |
| REL-10 Deployment window | `<YYYY-MM-DD HH:MM–HH:MM WIB>` |
| REL-11 Verification — heartbeat, smoke checks, `ReconcileGuards` when required, WS-10 and GLC-07 read | `<results>` |
| REL-12 Deployed commit and schema version | `<commit>` · `<migration>` |
| REL-12 First business day watched | `<YYYY-MM-DD>` — `<result>` |
| REL-13 Contract step | `<none>` / `<its later release>` |

## Effort (REL-12; GAP-014)

| Estimate | Actual | Note |
| --- | --- | --- |
| `<days>` | `<days>` | `<what differed>` |

## Notes

`<a tradeoff the Owner decided with its evidence (RL-02); a change admitted after the freeze on RL-07's grounds with the Owner's approval; anything the operator stopped on>`
