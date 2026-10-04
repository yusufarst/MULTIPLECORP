# P7 UX re-baseline — evidence and gates

Status: REVIEW | Updated: 2026-10-04 | Owner: Planning

Authority: the Owner's authorization [DIR-054](../../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction) (fifty-first source record) for Stage 0 and Stage 1 of the P7 UX re-baseline only, ending at Checkpoint C1, under the Owner's decisions K1–K4, C-1 and C-2 of [DIR-053](../../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction) (fiftieth source record) and the standing intent of D3, D4 and D7 and DIR-040 F6.

This is the evidence record of the re-baseline: its baseline, the DesainPakeAI readiness, the stage plan with its checkpoints and the gates; the Stage 1 work authorized by DIR-054 adds its sections here. It is evidence, not a specification, and nothing in it is normative: [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement (D3), and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage replaces or aligns them. DesainPakeAI is an exploration workspace outside the repository and never a source of truth. The record stays REVIEW, following the gate convention.

## Baseline

Verified read-only on 2026-10-04 before any change and recorded as OBS-019:

| Item (DIR-054 §4) | Result |
| --- | --- |
| 1. Repository and origin | Root `C:\Projects\MultipleCorp`; origin `https://github.com/yusufarst/MULTIPLECORP.git` (fetch and push) |
| 2. Live remote | After `git fetch origin`, `git ls-remote origin` showed `HEAD` and `refs/heads/main` at `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978`, `refs/heads/amendment/p5-auth` at `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978`, `refs/heads/migration/aicwdf` at `c26f31e9b053689b07a29877fe1016c6b8103e37` and `refs/tags/pre-aicwdf-migration` as tag object `2f8875e8b92d64b2db6c8e8dbc5991899f5d92de`, peeled to `c511d7b0d4683e07717c962927c9f113854f227b`; no `refs/heads/rebaseline/p7-ux` and no other ref |
| 3. Local branches | `main` and `amendment/p5-auth` at `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978`, `amendment/p5-auth` checked out; `origin/main` and `origin/amendment/p5-auth` equal to the live branches; ahead/behind 0 and 0 for both |
| 4. Published P5 publication-record commit | `git log -1 --format='%H %P %s' a526daf` gives `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978`, parent `e6cb6ac8d89f9a50670b75cd053699cc7ebede4d`, subject `docs: record P5 authentication amendment publication` — the commit whose SHA [OBS-018](../../00-governance/DECISION_LOG.md#obs-018--p5-authentication-amendment-publication-verified) left to the next authorized task, recorded here literally |
| 5. Working tree and index | `git status` clean; `git diff --ignore-cr-at-eol --stat`, `git diff --stat` and `git diff --cached --stat` empty; no untracked file; no merge, rebase, cherry-pick, revert or bisect state |
| 6. Source records | All 49 files under `docs/00-governance/sources/` match their SHA-256 in [SOURCE_OF_TRUTH](../../00-governance/SOURCE_OF_TRUTH.md#authority-and-provenance) and every size and line count stated there (four early records state lines only, the PDF and the PNG bytes only); the committed blobs at `a526daf` give the same result |
| 7. APPR-010 hashes | The 24 SHA-256 values of [APPR-010](../../00-governance/DECISION_LOG.md#appr-010--p5-authentication-amendment-approved) equal the committed blobs at `a526daf` |
| 8. Gap register | 51 findings — 4 CLOSED, 38 OPEN, 0 OWNER_DECISION_REQUIRED, 9 ACCEPTED_RISK, counted from the triage table and equal to the stated totals |
| 9. Identifiers | DIR-053, DIR-054, OBS-019, TECH-027, APPR-011, GAP-052 and RISK-010 occur nowhere at `a526daf` (`git grep`); the highest in use were DIR-052, OBS-018, TECH-026, APPR-010, GAP-051 and RISK-009 |
| 10. Paths | The seven paths of DIR-054 §4 item 10 do not exist; no application code — the tracked files are 105 Markdown files (one of them a source record), the 46 text, one PDF and one PNG source records, `.gitattributes` and `.gitignore` |
| Informational | `git config user.name` and `user.email` are set (the e-mail address is not recorded); `core.autocrlf` is `true` |

All ten items passed. The authorization arrived as one pasted text block without an accompanying sentence; after these checks the executor asked in session whether to execute it in full, exactly as written, and the Owner selected "Ya, jalankan penuh" (DIR-054). The branch `rebaseline/p7-ux` was then created from `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978` with `git switch -c`.

## DesainPakeAI readiness

The four read-only commands of DIR-054 §7, run on 2026-10-04 from a temporary directory outside the repository, before any file of the repository was edited. Non-secret evidence only: the key field, which the tool shows only masked, was not recorded, and the credential path was redacted before display; nothing was installed, upgraded, re-authenticated or reconfigured, the active project was not switched, and no paid plan was used.

| Item | Evidence |
| --- | --- |
| CLI version | 0.2.2 — `dpai --version`; the tool's banner names the product "DesainPakeAI CLI" |
| Authenticated | yes — `dpai auth status --pretty` |
| Active project | **MULTIPLECORP**, role owner — `dpai project current --pretty`; not switched |
| Context revision | `sha256-3fe26282863db253` — `dpai context --pretty` |
| Retrieval time | 2026-10-04 15:44 WIB; the context kept outside the repository |
| Ramp design system | found — the context's design summary names the design system "Ramp", version alpha.3, with 18 colour tokens, 8 typography tokens and 8 sections: the K3 reference |
| Project pages | 54 — the probe page `dpb-probe` and the 53 DPB exploration pages that [P7_QUALITY_GATE](P7_QUALITY_GATE.md#desainpakai-exploration) records (DPB-01 to DPB-18); no page in a working state |
| Setup steps needed | none — no stop |

`git status` showed nothing new in the repository after the commands.

## Plan

The stage plan of the re-baseline as DIR-054 §§9, 20 and 21 record it. K1 places two Owner checkpoints before the normative documents are frozen. Only Stage 0 and Stage 1 are authorized; the rest is the approved plan, recorded and not authorized.

| Stage | Content | Authorization |
| --- | --- | --- |
| Stage 0 — baseline and records | The baseline (OBS-019); source records 50 and 51; DIR-053, DIR-054, OBS-019 and TECH-027; the DesainPakeAI readiness; the narrow continuity cleanup of CONTEXT_INDEX, SECURITY, ENGINEERING_PRINCIPLES and TOOLCHAIN; gate G0; commit 1 `docs: open P7 UX re-baseline`; a normal push of `rebaseline/p7-ux` | Authorized — DIR-054 |
| Stage 1 — diagnosis and direction | Intake; official-documentation verification; the diagnosis of the approved P7 baseline, with the Planning hypotheses H-1 to H-5 verified (K4); the Ramp reference record DESIGN_REFERENCES as a Stage 1 draft; the persona task analysis; provisional dispositions of the 83 earlier P7 decisions and anti-slop rules; a non-normative direction proposal; prototypes of eight key surfaces in DesainPakeAI; a preliminary non-loss map; the C1 package; a zero-context check; gate G1; commit 2 `docs: record P7 UX re-baseline direction for checkpoint C1`; a normal push of the branch | Authorized — DIR-054 |
| Checkpoint C1 | The Owner's design-direction review: approve, approve with named adjustments or reject the direction; one choice per contentious axis; whether a suitable warehouse/field user is reasonably available for Stage 2 | The Owner |
| Stage 2 — core design and usability | The complete IA and page inventory; the design system — tokens, shadcn/ui mapping, patterns, states, density; the localization architecture under C-1; the authentication journeys (SECURITY H7-15–H7-19, AICWDF §4A.10, GAP-044, J-ACC-01 under AU-03); end-to-end prototypes of the critical journeys on phone and desktop in both languages; the usability kit in Indonesian with the K2 participants | Not authorized |
| Checkpoint C2 | The usability results and the Owner's verdict, with fixes and re-tests when needed. K2: the Owner, the Admin Operasional validator and one real warehouse/field mobile user if reasonably available, otherwise the first two only; short task-based sessions on the prototype with synthetic data, moderated by the Owner from the executor's script and recorded by role without personal names | The Owner |
| Stage 3 — normative documents | INFORMATION_ARCHITECTURE and DESIGN_SYSTEM replaced in place with stable IDs; NAVIGATION_CONTRACTS, LOCALIZATION and DESIGN_REFERENCES; ROUTE_CONTRACTS and INTERACTION_CONTRACTS as a P3 addition; the ADMIN_FLOW alignment; the full non-loss matrix and the governance records; any schema change only through C-1 and change control | Not authorized |
| Stage 4 — review and validation | Self-review, three independent adversarial reviews (UX, usability and accessibility; business, security and authorization fidelity; contracts, localization and zero-context) and one final focused review, a further one only under the DIR-049 convergence rule applied to UX, C-2 governing LOW non-material findings; the gates of AICWDF §18.2, §27, §4B.5, §25 and WCAG 2.2 AA at design level; a stop for the Owner's approval | Not authorized |
| Stage 5 — approval and publication | APPR-011; the approval commit, a fast-forward of `main`, normal pushes and the live-remote verification, then a separate publication-record commit; P7 DONE, P8 still needing its own authorization | Not authorized |

APPR-011, reserved and not granted, requires: the Owner's explicit verdict after C2 that the UI is user friendly (F6); every critical task completed without help by every participant in the final round, or an Owner-accepted disposition; 100% non-loss, with no V1 capability lost; the contracts complete; the gates PASS; 0 unresolved CRITICAL, HIGH, MEDIUM and LOW findings and 0 OWNER_DECISION_REQUIRED, "unresolved" as C-2 defines it; P0–P6 semantics unchanged except for recorded narrow amendments; and no DesainPakeAI output or credential in the repository.

## Gate G0

Run on the complete staged change of commit 1 before the commit (DIR-054 §9.2), with scripts kept outside the repository. Every item passed.

| Item | Check | Result | Evidence |
| --- | --- | --- | --- |
| G0-01 | The baseline equals DIR-054 §4 | PASS | [Baseline](#baseline): items 1–10 verified before any change (OBS-019) |
| G0-02 | The staged diff touches only the listed files | PASS | `git diff --cached --name-status a526daf` lists 14 paths — `.gitattributes`, CHANGELOG, the two new source records (added), SOURCE_OF_TRUTH, DECISION_LOG, DECISION_INDEX, CONTEXT_INDEX, SECURITY, ENGINEERING_PRINCIPLES, TOOLCHAIN, this record (added), PHASE_STATUS and CURRENT_HANDOFF; GAP_REGISTER unchanged, Stage 0 having found no new gap |
| G0-03 | Records 50 and 51 | PASS | Both follow the header convention of records 48 and 49 — the first line, Received, Subject and Delivery, the transcript sentence, a separator of 68 `=` characters and a blank line before the transcript — with LF bytes, no byte-order mark and a final line break; their lines, bytes and SHA-256 in SOURCE_OF_TRUTH equal the staged blobs; the 49 earlier records are byte-identical to `a526daf`; the folder holds 51 source records |
| G0-04 | The four amended approved documents | PASS | A word-level diff of CONTEXT_INDEX, SECURITY, ENGINEERING_PRINCIPLES and TOOLCHAIN shows only the change of DIR-054 §8, the new "Amendment:" note and, in all but SECURITY, the `Updated` date; their last-approved, before and after hashes under TECH-027 equal the APPR-009 or APPR-010 record, the blobs at `a526daf` and the staged blobs |
| G0-05 | Readiness evidence | PASS | [DesainPakeAI readiness](#desainpakeai-readiness) records the CLI version, authentication, the active project, the context revision, the retrieval time in WIB, the Ramp design system found and no setup step; no key, key identifier, token, credential path or project identifier is recorded |
| G0-06 | The current-state records agree | PASS | PHASE_STATUS, CURRENT_HANDOFF, DECISION_INDEX and DECISION_LOG record DIR-053 and DIR-054, Stage 0 done on `rebaseline/p7-ux`, Stage 1 next and `main` at `a526daf`; none claims Stage 1 work, a push or publication |
| G0-07 | Links and identifiers | PASS | 0 broken relative links and anchors in every Markdown file outside `docs/00-governance/sources/`; DIR-053, DIR-054, OBS-019 and TECH-027 each have one row in the decision log's index and one entry heading, and no other new identifier is defined |
| G0-08 | No secret, placeholder, binary, image or DesainPakeAI output | PASS | The new files are two LF text records and this Markdown record; a search of the staged diff finds no key, token, credential path, key or project identifier, e-mail address or placeholder — the angle-bracket templates inside record 51 are the Owner's verbatim text; no page source, export or capture of DesainPakeAI is in the repository |
| G0-09 | Content-truth counts unchanged | PASS | V1_SCOPE, ACCEPTANCE_CRITERIA, PERMISSIONS_MATRIX, DATABASE and GAP_REGISTER are unchanged: 18 MUST capabilities and 14 document types (V1_SCOPE), 80 capabilities (PERMISSIONS_MATRIX), 125 logical tables in 13 modules (DATABASE §4) and the gap totals 51 — 4 CLOSED, 38 OPEN, 0 OWNER_DECISION_REQUIRED, 9 ACCEPTED_RISK |
| G0-10 | No P7 normative document and no other P0–P6 change | PASS | INFORMATION_ARCHITECTURE, DESIGN_SYSTEM and ADMIN_FLOW are unchanged; among the P0–P6 specifications only the DIR-054 §8 lines and notes of CONTEXT_INDEX, SECURITY, ENGINEERING_PRINCIPLES and TOOLCHAIN change, and SOURCE_OF_TRUTH only as the continuity record of DIR-054 §6.2 and §9.1 |
