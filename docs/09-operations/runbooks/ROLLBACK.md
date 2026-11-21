# Rollback decisions

Status: REVIEW | Updated: 2026-11-21 | Owner: Planning

Written by [TASK-076](../../11-tasks/TASK-076.md) (PRQ-15). This is [RELEASE_PLAN](../../10-release/RELEASE_PLAN.md) §6's rollback rules RBK-01–RBK-06 as runbook steps. Each one names its rule. Where this page and RELEASE_PLAN differ, RELEASE_PLAN wins.

| Rule | What the operator does | Who decides |
| --- | --- | --- |
| RBK-04 — **a failed step stops the release** | Put the application in maintenance mode when writes are at risk. Then choose between fixing forward, a code rollback (RBK-01) and a data recovery (RBK-02). Record the choice and its reason in the release record. | the operator and the Owner |
| RBK-01 — **code rollback** | Redeploy the rollback target, the previous release's commit, in a maintenance window. This is possible because the release's migrations were expand-only; no down migration of data runs. The queue is drained by the newer workers before the older code runs. A page of the newer release still open is reloaded, or its commands are refused. The deployment steps are [COOLIFY_DEPLOYMENT §6](../COOLIFY_DEPLOYMENT.md#6-a-production-deployment--the-release-runbook-rel-08rel-13). | the operator, on the Owner's decision |
| RBK-02 — **data recovery is separate** | Data is recovered only by [RESTORE](RESTORE.md), on the Owner's decision, into isolation and never over live writes. What happened after the recovery point is recorded as lost and recorded again under new numbers after the Owner's raise. | the Owner |
| RBK-03 — **a wrong opening fact** | Correct it on its record by contras and reversals by import identity, before and after go-live alike. A committed batch is never imported again ([CUTOVER §4](CUTOVER.md#4-when-a-step-fails-rbk-01rbk-06)). | the Owner, through the ordinary commands |
| RBK-05 — **an interrupted migration** | Recover it by the procedure its staging rehearsal proved (REL-03). | the operator |
| RBK-06 — **the second application is never touched** | A deployment, rollback, restore or maintenance mode of MULTIPLECORP acts on its own project, database, storage and backups alone, and the shared host proxy changes only by its tested reload. A Scenario B stops the whole host, so the client is told and involved ([KEY_COMPROMISE](KEY_COMPROMISE.md) step 3; TT-F06; architecture challenge 16a). | the operator; the client for the host |
