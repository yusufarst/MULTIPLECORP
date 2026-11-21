# Key rotation and re-encryption

Status: REVIEW | Updated: 2026-11-21 | Owner: Planning

Written by [TASK-076](../../11-tasks/TASK-076.md) (PRQ-15). This is [INFRASTRUCTURE §7.4](../INFRASTRUCTURE.md#74-the-key-rotation-and-re-encryption-runbook) as the operator works it, with AU-21, SX-02 and H9-14. It covers a planned rotation, never an incident: an incident is [KEY_COMPROMISE](KEY_COMPROMISE.md). Each step names its source step. Where this page and the source differ, the source wins.

| # | Step | Source |
| --- | --- | --- |
| 1 | **The register.** Each key's creation, use and retirement is a dated entry of the [key register](forms/KEY_REGISTER.md), kept in the operator's secret store and never in the repository. The register's entry is the name the backups pair with (`BACKUP_KEY_REGISTER_ENTRY`; BKR-08). | §7.4 step 1; AU-21; TT-F04 |
| 2 | **The new key current, the old one listed.** Make the new `APP_KEY` current and put the old one into `APP_PREVIOUS_KEYS`, so every existing secret stays readable. Set `BACKUP_KEY_REGISTER_ENTRY` to the new entry. Rotate the HMAC keys only when the rotation's reason requires it: their rotation breaks log linkage and invalidates every outstanding credential link, which the Owner then reissues. | §7.4 step 2; SX-02 |
| 3 | **Re-encryption.** Run `php artisan iam:reencrypt-secrets --operator="<name>"`. It re-encrypts every TOTP secret and recovery-code set under the current key and records TOTP_SECRETS_REENCRYPTED. | §7.4 step 3; AU-21 |
| 4 | **The verified count.** Retire the old key from `APP_PREVIOUS_KEYS`, and enter its retirement in the register, only when the re-encrypted count equals the enrolled accounts. | §7.4 step 4 |
| 5 | **The backups.** A generation taken before the rotation stays paired with the key valid at its time. That key is kept in the secret store, apart from the VPS and the copies, while the generation is retained ([BACKUP_RESTORE §3](BACKUP_RESTORE.md#3-the-off-server-copy-bkr-09-dir-083-oe-09)). | §7.4 step 5; BKR-08 |
| 6 | **When.** On SX-02's triggers. Also once after the first Owner's set-up and before any business data, so the re-encryption touches only the first Owner's TOTP data; that run is TASK-085's (DIR-083 OE-05; CUT-02; GLC-23). Then yearly. | §7.4 step 6; P9Q-16 |

**Beside it: a planned hashing change** (AU-02; HO-26). A change of the hashing parameters or driver is announced, bounded and recorded, never a silent switch: it ends each account's other sessions at the next sign-in. Its measured parameters are TASK-079's.
