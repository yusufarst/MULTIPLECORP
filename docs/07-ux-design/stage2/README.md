# P7 UX re-baseline — Stage 2 drafts

Status: DRAFT | Updated: 2026-10-13 | Owner: Planning

Draft — not normative. This folder holds the non-normative drafts of Stage 2 of the P7 UX re-baseline ([DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-10, the Owner's approval P-7). Run S2-0 created this index under [DIR-062](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup), as an index only; S2-0 adds no other draft.

**Authority order (AK-1).** The repository's P0–P6 truth and the Owner's decisions govern every workflow, under the [source hierarchy](../../00-governance/SOURCE_OF_TRUTH.md#owner-reference-ingestion-and-provenance); the R1 structure stays (AK-3); the Golden Frontend Reference v1.0.0 governs presentation only and adapts to MULTIPLECORP, never the reverse (SD-02). A difference is resolved by adapting the reference; a conflict with P0–P6 is recorded as OWNER_DECISION_REQUIRED and never enacted (AK-2).

## What will live here

Each part is added only under its own run's authorization (DIR-061 SD-01):

| Run | Drafts | Status |
| --- | --- | --- |
| S2-1 | The documents: the complete IA and page inventory; the non-loss map against SCR-01–SCR-60; the design-system mapping; the localization architecture; the authentication journeys; the document preview and print producer (GAP-053); the QB-01, PF-24 and QB-02 checks; and for S2-J01–S2-J06 the screen list, the per-step trace matrix, the S2-R checks, the simulation register and the deviation register | Not authorized |
| S2-2 | The verification records of the six journeys — SV-01–SV-06 and SS-01–SS-08 —, with the hashes of the render files kept in the prototype repository | Not authorized |
| S2-3 | The usability kit for three roles, with K2's fallback | Not authorized |
| Checkpoint C2 and S2-4 | The results of the sessions the Owner moderates, their fixes and re-tests | Not authorized |

Stage 3 later uses these drafts to replace INFORMATION_ARCHITECTURE and DESIGN_SYSTEM. Nothing here is normative.

## What stays in force

[INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them.

## The prototype

The interactive prototype lives in a separate private repository, `https://github.com/yusufarst/multiplecorp-p7-prototype`, recorded in [SOURCE_OF_TRUTH](../../00-governance/SOURCE_OF_TRUTH.md#p7-ux-re-baseline-stage-2-decisions-and-s2-0-authorization) (DIR-061 SD-03, SD-04). Rendered evidence lives there (SV-06); this repository keeps text only — commit SHAs, page lists, trace matrices, check results and hashes, never a binary (P-4). The prototype is not a source of truth and not application code.

## Pointers

- The Owner's decisions and the S2-0 authorization: [DIR-061 and DIR-062](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup).
- The gate record's Stage 2 sections: [Owner decisions (DIR-061)](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061), [S2-0 verification](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-verification), [S2-0 prototype setup](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-prototype-setup), [S2-1 inputs](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-1-inputs), [S2-0 report](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-report), [S2-0 zero-context check](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-zero-context-check) and [Gate S2-0](../evidence/P7_REBASELINE_GATE.md#gate-s2-0).
- The current state and the next safe action: [CURRENT_HANDOFF](../../handoff/CURRENT_HANDOFF.md).
