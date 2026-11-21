# Dated key register — blank form

Status: REVIEW | Updated: 2026-11-21 | Owner: Planning

Written by [TASK-076](../../../11-tasks/TASK-076.md) (TT-F04; AU-21; BKR-08). This file is the blank form. The register is kept in the operator's secret store, outside the repository and the VPS. It holds dated entries only, never a key value.

The first entry is written when the installation's key is created (TASK-085). Every later key is entered at its creation. An entry's name is what `BACKUP_KEY_REGISTER_ENTRY` and each backup manifest name.

| Entry name | Key (`APP_KEY`, HMAC, copy key pair, other secret of SX-01) | Created (date and time, WIB) | Current from | Listed as previous from | Retired on | Reason (rotation, incident, installation) | Generations paired with it (until when retained) |
| --- | --- | --- | --- | --- | --- | --- | --- |
| | | | | | | | |

A leaked key's entry is marked leaked and listed in `BACKUP_LEAKED_KEY_ENTRIES`. It is never installed again: its generations are restored only by Scenario B ([POST_INCIDENT_RECOVERY](../POST_INCIDENT_RECOVERY.md) step 5).
