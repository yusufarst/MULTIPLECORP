# Production data safety

Status: REVIEW | Updated: 2026-10-03 | Owner: Planning

This document owns the production-database rules of AICWDF §29–§30 for agents. The production boundary — no production credentials for agents, secrets kept out of the repository, forbidden destructive commands, controlled migrations — is owned by [ENGINEERING_PRINCIPLES](ENGINEERING_PRINCIPLES.md#secrets-public-repository-and-production-boundary); the security controls by [SECURITY §7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain); the backup policy by DIR-009 and the [local backup contract](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract). This document links them and adds the agent rules below.

## Zero-touch rule (§29.1, §29.2)

- No agent connects to, queries or mutates the production database, its volumes or its credentials. A general request such as "fix production" authorizes none of it.
- The default for every Task and maintenance change is **DB CHANGE: NONE** and **DIRECT PRODUCTION DB ACCESS: FORBIDDEN**; a Task that needs a schema change says so in its database impact assessment ([Task contract](AGENT_OPERATING_MODEL.md#task-contract)).
- Production problems are reproduced in development or staging with synthetic or sanitized data (§31); old records are preserved and never edited to hide an application defect.

## Forbidden production data actions (§30)

The commands and actions §30 lists — wiping, resetting, refreshing or reseeding the database, deleting volumes or storage, restoring over the live database, bulk deletion as a quick fix, editing data to hide a bug — are forbidden in production, together with the project's own list in [ENGINEERING_PRINCIPLES](ENGINEERING_PRINCIPLES.md#secrets-public-repository-and-production-boundary) (`TRUNCATE`, `DROP DATABASE`, destructive seeds, careless bulk deletion).

## Legitimate schema evolution (§29.3)

An agent may design a migration, write the migration file, test it outside production, document its risk and prepare its rollback and recovery. Only the authorized human production operator runs it, after a staging rehearsal, a verified backup and the Owner's approval, then verifies the result. Schema changes favour expand → backfill → switch → contract, and a migration rollback is not backup recovery.

## Procedures owed

The operational procedures belong to later phases and do not exist yet: database roles and access, backup, restore and post-restore verification to P9 ([reservation](../09-operations/README.md)); migration rehearsal, cutover, rollback and the release declaration of DB change to P10 ([reservation](../10-release/README.md)).
