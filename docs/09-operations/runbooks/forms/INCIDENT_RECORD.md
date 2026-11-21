# Private incident record — blank form

Status: REVIEW | Updated: 2026-11-21 | Owner: Planning

Written by [TASK-076](../../../11-tasks/TASK-076.md) (GLC-16; TT-F03; TT-F28). This file is the blank form. Each filled copy lives outside the repository, the application and the VPS, in the operator's private store. A filled copy holds no key value, password, token, link or credential: evidence is masked.

## 1. The alarm (KEY_COMPROMISE step 1)

| Field | Entry |
| --- | --- |
| Detection time (WIB) | |
| Incident type | |
| Source of the alarm (alert channel, a sign found, a report) | |
| Environments possibly affected | |
| Status (suspected → confirmed → contained → closed) | |
| Evidence, masked | |
| Client told (when, by whom) | |

## 2. The window's start (KEY_COMPROMISE step 2; POST_INCIDENT_RECOVERY step 7)

| Entry | Start (ISO 8601 with offset) | Basis (dated evidence or key-register entry) | Set with the Owner (when) | Correction reason (later entries only) |
| --- | --- | --- | --- | --- |
| 1 | | | | |

The history is never deleted. A corrected start is a new entry and a new run.

## 3. The inventory and the scenario (KEY_COMPROMISE step 3)

| Field | Entry |
| --- | --- |
| Host access, network, firewall, credentials, logs, the other application's access | |
| Write access excluded? (yes/no, and why) | |
| Scenario (A or B) and when chosen | |

## 4. Steps and decisions

| Time (WIB) | Step (runbook and step number) | Done by (role) | Result | Decision and its authority |
| --- | --- | --- | --- | --- |
| | | | | |

## 5. Owner recoveries (OWNER_RECOVERY)

| Account (role only, never a contact detail) | Why the codes and another Owner cannot serve | Identity verified in person (when, by whom; "the Owner is the operator" when so) | Confirmer (role) | Link issued / used (times only, never the token) | Result |
| --- | --- | --- | --- | --- | --- |
| | | | | | |

## 6. After a restore (RESTORE step 7)

| Field | Entry |
| --- | --- |
| Recovery point | |
| Elapsed time | |
| What was lost after it | |
| Per company, type and period: the highest number on the papers issued after the recovery point | |
| Lost facts recorded again: new number, beside the paper's number | |

## 7. Open matters and the end

| Field | Entry |
| --- | --- |
| Disputes open (POST_INCIDENT_RECOVERY step 4) | |
| Owners stated unavailable | |
| End of recovery recorded (time, run) | |
