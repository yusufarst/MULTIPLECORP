# Cost policy

Status: APPROVED | Updated: 2026-10-04 | Owner: Planning

Approval: [APPR-009](DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved), explicit Owner approval on 2026-10-03 (DIR-040) of this document as committed in the AICWDF migration finalization commit; the approved file hash is recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-03, under [DIR-043](DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment), the recurring-cost inventory was amended to list the three zero-cost services that the Owner's authentication decisions D5 and D6 ([DIR-037](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration)) bring in, with their free-tier record; on 2026-10-04, under [DIR-045](DECISION_LOG.md#dir-044-dir-045-risk-006-risk-007-and-tech-025--p5-authentication-amendment-continuation-after-the-adversarial-review), the Pwned Passwords record follows the Owner's decision on an unreachable service (DIR-044 R-07). No paid service and no exception are added. [TECH-025](DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment) records the SHA-256 of the last approved revision, and the amended revision is approved under [APPR-010](DECISION_LOG.md#appr-010--p5-authentication-amendment-approved).

This document owns the procedure of AICWDF §4C for technology and service choices. The project's cost facts — the near-zero incremental monthly target, the existing VPS and domain, the cost areas and the evidence still needed — are owned by the [V1_SCOPE cost boundary](../01-product/V1_SCOPE.md#cost-boundary) and are not restated here. A recurring cost or a significant infrastructure change is a Level 2 decision ([CHANGE_CONTROL](CHANGE_CONTROL.md#decision-authority)).

## Procedure

1. **Selection order (§4C.1).** Prefer an existing project capability, then a native framework or platform capability, then a free open-source package, then a self-hosted open-source capability on the existing infrastructure, then a reliable free tier; a paid external service comes last and only with the Owner's explicit approval.
2. **Paid-service stop condition (§4C.2).** When a paid service is proposed, that decision stops. Record the requirement, the free, open-source and self-hosted alternatives, the trade-offs and the estimated recurring cost, and ask the Owner; dependent work waits, independent work continues.
3. **No paid production AI dependency (§4C.3).** None is introduced unless it is a real product requirement the Owner approves.
4. **Free-tier caution (§4C.4).** A critical free-tier dependency records its limits, quota behaviour, lock-in, exit strategy and data portability before it is adopted.

Development and QA tools follow the same order; their availability and installation are recorded in [TOOLCHAIN](TOOLCHAIN.md).

## Recurring-cost inventory

Additional recurring production services selected: none at a cost. The authentication design relies on three zero-cost services and the operator's alerts on a fourth, listed with their free-tier record (§4C.4).

| Service | Purpose | Monthly cost | Approval |
| --- | --- | --- | --- |
| Google OAuth client, one per environment | Google sign-in for existing linked accounts ([SECURITY AU-22](../05-security/SECURITY.md#2-authentication-sessions-and-credentials)) | Rp0 — sign-in only, no Google API beyond it | Owner decision D6 (DIR-037); in the authentication amendment approved under APPR-010 (TECH-025); set up by P9 (GAP-042) |
| Pwned Passwords range API (haveibeenpwned.com) | The breached-password check of password setting (SECURITY AU-03) | Rp0 — free, without key or subscription | Owner decision D5 (DIR-037); in the same amendment |
| Authenticator apps on users' own phones | TOTP for high-risk accounts (SECURITY AU-16) | Rp0 — free apps on the staff's own devices | Owner decisions D5 and K1 (DIR-037, DIR-042); in the same amendment |
| Cloudflare Email Service, on the Owner's existing Cloudflare account | The operator's alert channel, OPI-05: e-mail to the Owner's own inbox on the Owner's phone ([INFRASTRUCTURE §9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs)) | Rp0 — only to verified destination addresses, under the free plan | The Owner's decision of 2026-11-21 ([DIR-129](DECISION_LOG.md#dir-129--the-owners-task-071-reconciliation-2026-11-21)); set up and tested by TASK-080 and TASK-081 |

**Free-tier record (§4C.4).** *Google OAuth client* — limits, quotas and consent-screen rules are confirmed at setup (GAP-042); no lock-in: password login stays for every account, and switching Google sign-in off leaves the links inert; no business data is held by Google. *Pwned Passwords* — the API documentation states no rate limit and no key; when it fails or does not answer, the check is skipped and recorded and the password is accepted only if it passes the local list and every other rule (SECURITY AU-03; DIR-044 R-07); exit: the locally bundled list alone — no offline copy of the corpus is required. *Authenticator apps* — any TOTP-compatible app; no lock-in, because a device is replaced through SECURITY AU-20; nothing to port. *Cloudflare Email Service* (OPI-05; recorded 2026-11-21 before adoption, from the provider's documentation as read that day) — **limits:** on the free plan a message reaches only a destination address verified in the account — sending to any other recipient needs the paid Workers plan, which is not adopted (§4C.2) —, the sender on a domain the account holds, at most 50 recipients per message; **quota behaviour:** sends to verified destination addresses are free on every plan and count toward no monthly quota or daily sending limit; an unverified recipient is refused, never billed, so the alternate operator's address is verified before it is added (INFRASTRUCTURE P9Q-14); the account's Email Routing summary lists a Worker's sends as dropped even when delivered, so delivery is proved by receipt in the inbox, never by that summary (TASK-080, TASK-081); **dependency risks:** the free allowance is the provider's to change, so it is re-read whenever the channel is set up or changed; the channel shares the Cloudflare account with the domain's DNS (OPI-04), so a lost or locked account loses both — the in-app notice at the next login and the daily look stay the floor (INFRASTRUCTURE §6); an e-mail is no guaranteed push — the inbox's filtering and the phone's notification settings decide whether it is seen, which the end-to-end test checks; the sending credential is a secret of SX-01 on the host, scoped to sending; whatever the domain's mail records need is checked by the operator against any existing mail use of the domain before it is changed; **lock-in and exit:** the delivery goes through one small replaceable adapter (TASK-075), so another zero-cost channel the Owner already holds replaces it by changing the adapter and its environment values; **data portability:** nothing to port — each message is a neutral signal name and its time, with no S3 or S4 value or business data, and no history is kept with the provider beyond its own delivery logs.

## Paid-service exception registry

Approved paid-service exceptions: none.

| Exception | Requirement | Alternatives considered | Recurring cost | Owner approval |
| --- | --- | --- | --- | --- |
| — | — | — | — | — |
