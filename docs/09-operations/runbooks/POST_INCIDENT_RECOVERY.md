# Post-incident recovery and Scenario B

Status: REVIEW | Updated: 2026-11-21 | Owner: Planning

Written by [TASK-076](../../11-tasks/TASK-076.md) (PRQ-15). This is [INFRASTRUCTURE §7.3](../INFRASTRUCTURE.md#73-the-post-incident-recovery-runbook) as the operator and the recovered Owners work it, with AU-25, H9-14, GAP-050's carried cases and the tabletop's findings. Each step names its source step, and none adds a path, deciding authority, role or bypass. Where this page and the source differ, the source wins.

The operator runs the commands, never an agent. The Owners decide in the application only through its ordinary commands, and outside it as step 6 names.

| # | Step | Source |
| --- | --- | --- |
| 1 | **The order.** Restoration first, then recovery through [OWNER_RECOVERY](OWNER_RECOVERY.md) (path (e)), then the review. No account is touched before its restoration. | §7.3 step 1 |
| 2 | **Restoration.** Verify each pre-window Owner removed during the window in person ([OWNER_RECOVERY](OWNER_RECOVERY.md) step 2). Restore them, temporarily, with `php artisan iam:restore-owner <e-mail> --window-start=<the recorded start> --operator="<name>"`. Before the review, the restored Owners are shown with the account, and with the actor and time of the removal each one reversed (TT-F14). The command refuses three things: another window start, an account that is not a pre-window Owner, and a second restoration of one account in one run (M-46). | §7.3 step 2 |
| 3 | **Recovery.** Recover each pre-window Owner through OWNER_RECOVERY, each with its own audit event and the recorded window start. | §7.3 step 3 |
| 4 | **The review.** The recovered pre-window Owners review the accounts, roles, grants, credential links and Google links created or changed during the window. They leave untouched the account of a pre-window Owner not yet restored and recovered. What each kind of account receives is set out in the table after these steps. A change the recovered Owners **dispute** is neither confirmed nor reversed while the dispute lasts. Neither Owner is presumed right, no new deciding authority is invented, and the protections stay: the account stays as the incident command left it, with no RESET link. The change is not treated as validated. The review and the end of recovery (step 8) stay incomplete until the dispute is settled by the governance recorded below. | §7.3 step 4; AU-25; TT-F17 |
| 5 | **Scenario B.** No step of Scenario A runs on the untrusted state, which is replaced rather than recovered. The host goes out of service as soon as Scenario B is chosen; the second application stops with it, and the client is told and involved (TT-F06). An incident command already run there serves only to contain it. Recovery uses **only a backup or snapshot independently verified** as predating the compromise and trustworthy: its integrity, date and source. A backup on the compromised host is never presumed clean, and an off-server copy proves nothing by existing (TT-F21; TT-F24; BKR-09). **If no such backup or snapshot exists, the in-application path stops** for the Owner's incident decision outside the application. The restored state comes up on a clean environment, by [RESTORE](RESTORE.md) steps 2–5, only once the inventory excludes the attacker's write access to the restored database and server; otherwise recovery stops for that decision. Before it serves any request, five conditions hold: the maintenance mode is kept; new keys are installed, the leaked key never listed; every other affected secret of SX-01 is rotated again; the incident command runs in full under the new keys ([KEY_COMPROMISE](KEY_COMPROMISE.md) steps 4–7); and every precondition is verified (TT-F23). Pre-window status is read at the window's start, or at the backup's time when the backup is older, and the restored Owners are shown with that time. MULTIPLECORP's recovery stays isolated from the second application, which recovers by its own procedure (TT-F08). | §7.3 step 5; AU-25 |
| 6 | **The halts.** When no pre-window Owner is certain, or none is available: on a trusted state the halt goes first to the Owner's incident decision outside the application, with the client, which may choose Scenario B's restore or keep the trusted state; on a restored state, it goes to the Owner's decision outside the application (DIR-083 OE-10; TT-F29). An undatable leak of the installation's key always meets this halt (TT-F05; P9Q-16). **No Owner is created or guessed, and OWNER authority is never given to the operator, the client or anyone not authorized** (TT-F16; TT-F25). The decision outside the application covers isolation, evidence, recovery from verifiable sources, a clean rebuild and every data-loss risk, each recorded and approved by its authority (TT-F22). | §7.3 step 6 |
| 7 | **A corrected window start** (narrower, earlier or mistyped) means a new run of the incident command with that start, and AU-25's recovery again, with the earlier run's decisions shown to the recovered Owners. The start's history, its corrections and their reasons stay in the private record, never deleted (TT-F28; GAP-050). | §7.3 step 7 |
| 8 | **The end of recovery.** Record it with `php artisan iam:end-recovery --window-start=<the recorded start> --operator="<name>"` only when every condition holds:<br>– every active OWNER account is a pre-window Owner of the run, or has a recovery issue naming a recovered pre-window Owner as its confirmer (AU-14's guard, which the command checks);<br>– the review is complete;<br>– every removed pre-window Owner has been restored, or is stated unavailable in the Owner's record;<br>– no account has been newly made an Owner before the end (one that was is demoted before it and reinstated after it);<br>– every action AU-14 and AU-25 require is done and recorded;<br>– no security decision is open or disputed (TT-F18).<br>Recorded early, the end would leave a removed pre-window Owner unrestorable. | §7.3 step 8 |
| 9 | **The record.** Every decision, reversal and open matter goes in the [private incident record](forms/INCIDENT_RECORD.md) with its audit events. Nothing secret goes in it or in the repository. | §7.3 step 9 |

**What the review gives each account (step 4).**

| Account | What it receives |
| --- | --- |
| Suspect Google link | Unlinked through the OWNER unlink (AU-24). |
| Confirmed OWNER account | Recovered through path (e); loses its Google link. |
| Account activated during the window, confirmed, without the OWNER role | A plain RESET link. |
| Account or change the review does not confirm | Reversed by the Owner through the ordinary commands: revoking the grant, removing the role or deactivating the account. A removal confirmed as legitimate is made again, which ends its temporary restoration (GAP-048). |
| Every other account | A plain AU-12 RESET link, which keeps its Google link. An account created or activated during the window gets one only once the review confirms it. |
| Grants revoked during the window | Restored through the ordinary Owner commands. |

Each person then sets a password, signs in and, when high-risk, enrols TOTP (AU-18).

## The governance that settles a disputed change (P9Q-15; DIR-083 OE-04; GLC-21)

> *Recorded before go-live by TASK-083: the business's own governance outside the application, named in advance. Until it is recorded here, a dispute keeps the review and the end of recovery open (step 4).*
>
> Governance: `<named by TASK-083>`
> Recorded on: `<date>` — by: `<role>`
