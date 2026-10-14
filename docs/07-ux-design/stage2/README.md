# P7 UX re-baseline — Stage 2 drafts

Status: DRAFT | Updated: 2026-10-15 | Owner: Planning

Draft — not normative. This folder holds the non-normative drafts of Stage 2 of the P7 UX re-baseline ([DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-10, the Owner's approval P-7). Run S2-0 created this index under [DIR-062](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup), as an index only; S2-0 adds no other draft.

**Authority order (AK-1).** The repository's P0–P6 truth and the Owner's decisions govern every workflow, under the [source hierarchy](../../00-governance/SOURCE_OF_TRUTH.md#owner-reference-ingestion-and-provenance); the R1 structure stays (AK-3); the Golden Frontend Reference v1.0.0 governs presentation only and adapts to MULTIPLECORP, never the reverse (SD-02). A difference is resolved by adapting the reference; a conflict with P0–P6 is recorded as OWNER_DECISION_REQUIRED and never enacted (AK-2).

## What will live here

Each part is added only under its own run's authorization ([DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01):

| Run | Drafts | Status |
| --- | --- | --- |
| S2-1 | The documents: the complete IA and page inventory; the non-loss map against [SCR-01](../INFORMATION_ARCHITECTURE.md#81-system-account-and-beranda)–SCR-60; the design-system mapping; the localization architecture; the authentication journeys; the document preview and print producer ([GAP-053](../../00-governance/GAP_REGISTER.md#triage)); the [QB-01](../../06-api-performance/PERFORMANCE.md#6-query-budgets), PF-24 and QB-02 checks; and for S2-J01–S2-J06 the screen list, the per-step trace matrix, the S2-R checks, the simulation register and the deviation register | Done under DIR-064 |
| S2-2 | The verification records of the six journeys — [SV-01](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)–SV-06 and SS-01–SS-08 —, with the hashes of the render files kept in the prototype repository: [VERIFICATION_S2-2](VERIFICATION_S2-2.md), for the prototype repository's commit `a273207e3ea7c906e311cecd567c00ff19cf98ad`, and the S2-2 notes in S2-1's documents | Done under DIR-066 — [Gate S2-2](../evidence/P7_REBASELINE_GATE.md#gate-s2-2); its visual result not accepted (DIR-067) |
| GF-1 | The Golden-first visual-fidelity pilot's record, [GOLDEN_FIRST_PILOT](GOLDEN_FIRST_PILOT.md): four pages built from the Golden Frontend Reference's own implementation in the prototype repository's commit `9db43f805b4b1b62566aed084c78391a1ebd43ef`, compared side by side and in motion with the Golden lab; the classification of the 14 prototype pages with the estimate; the GF-1 notes in DEVIATION_REGISTER, OWNER_QUESTIONS and S2-J04 | Done under DIR-068 — [Gate GF-1](../evidence/P7_REBASELINE_GATE.md#gate-gf-1); the Owner's review of the pilot next |
| S2-3 | The usability kit for three roles, with K2's fallback | Not authorized |
| Checkpoint C2 and S2-4 | The results of the sessions the Owner moderates, their fixes and re-tests | Not authorized |

Stage 3 later uses these drafts to replace INFORMATION_ARCHITECTURE and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md#design-system). Nothing here is normative.

## S2-1 documents

The progress ledger of run S2-1 ([DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §9): one row per document, with its section of DIR-064, its purpose, its status — "not started", "in progress — written through" its last complete section, or "complete", set only after the document passes the checks of DIR-064 §11.6 — and the items it leaves pending for a later part, which Part 7's cross-document pass resolves. Every document is a non-normative draft. Part 7's cross-document pass also adds, by script, the per-section links to the owning documents that DIR-064 §11.3 asks for, in every document of the ledger.

| Document | DIR-064 | Purpose | Status | Pending |
| --- | --- | --- | --- | --- |
| [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) | §12.1 | The IA and the complete page inventory, with draft routes | complete | — |
| [NAVIGATION_MAP](NAVIGATION_MAP.md) | §12.2 | The navigation map per persona and device, with reachability | complete | — |
| [NON_LOSS_MATRIX](NON_LOSS_MATRIX.md) | §12.3 | The complete non-loss matrix | complete | — |
| [WRITTEN_FLOWS](WRITTEN_FLOWS.md) | §12.4 | The written flows: every [ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) journey and the cross-cutting flows | complete | — |
| [AUTH_JOURNEYS](AUTH_JOURNEYS.md) | §12.5 | The authentication journeys | complete | — |
| [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md) | §12.6 | The ID/EN localization architecture | complete | — |
| [DOCUMENT_PREVIEW_PRODUCER](DOCUMENT_PREVIEW_PRODUCER.md) | §12.7 | The draft-document preview and print producer ([GAP-053](../../00-governance/GAP_REGISTER.md#triage)) | complete | — |
| [QUERY_BUDGET_CHECKS](QUERY_BUDGET_CHECKS.md) | §12.8 | The [QB-01](../../06-api-performance/PERFORMANCE.md#6-query-budgets), PF-24 and QB-02 checks | complete | — |
| [DESIGN_SYSTEM_MAPPING](DESIGN_SYSTEM_MAPPING.md) | §12.9 | The design-system mapping | complete | — |
| [DEVIATION_REGISTER](DEVIATION_REGISTER.md) | §12.10 | The deviation register, [GR-24](DEVIATION_REGISTER.md#2-gr-24-onward) onward | complete | — |
| [SIMULATION_REGISTER](SIMULATION_REGISTER.md) | §12.11 | The simulation register, the marker, the panel and the out-of-scope page | complete | — |
| [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md) | §12.12 | S2-2's technical plan, the AK-5 checklist and the screen list for approval | complete | — |
| [S2-J01](journeys/S2-J01.md) | §§12.13, 12.14 | The journey specification of S2-J01, sign-in and session | complete | — |
| [S2-J02](journeys/S2-J02.md) | §§12.13, 12.14 | The journey specification of S2-J02, the Owner's *Beranda* | complete | — |
| [S2-J03](journeys/S2-J03.md) | §§12.13, 12.14 | The journey specification of S2-J03, *Pekerjaan* → the Project Journey → invoice issue, with its 390 px validation | complete | — |
| [S2-J04](journeys/S2-J04.md) | §§12.13, 12.14 | The journey specification of S2-J04, Buat Pembelian | complete | — |
| [S2-J05](journeys/S2-J05.md) | §§12.13, 12.14 | The journey specification of S2-J05, Barang Masuk with scanning on the phone, with S2-R01–S2-R09 | complete | — |
| [S2-J06](journeys/S2-J06.md) | §§12.13, 12.14 | The journey specification of S2-J06, a partial payment applied in the same command | complete | — |
| [OWNER_QUESTIONS](OWNER_QUESTIONS.md) | §14 | The register of the Owner's questions, OQ-01 onward | complete | — |

## What stays in force

[INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them.

## The prototype

The interactive prototype lives in a separate private repository, `https://github.com/yusufarst/multiplecorp-p7-prototype`, recorded in [SOURCE_OF_TRUTH](../../00-governance/SOURCE_OF_TRUTH.md#p7-ux-re-baseline-stage-2-decisions-and-s2-0-authorization) ([DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-03, SD-04). Rendered evidence lives there ([SV-06](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)); this repository keeps text only — commit SHAs, page lists, trace matrices, check results and hashes, never a binary (P-4). The prototype is not a source of truth and not application code.

## Pointers

- The Owner's decisions and the S2-0 authorization: [DIR-061 and DIR-062](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup).
- The S2-0 acceptance and the S2-1 authorization: [DIR-063 and DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents).
- The gate record's Stage 2 sections: [Owner decisions (DIR-061)](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061), [S2-0 verification](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-verification), [S2-0 prototype setup](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-prototype-setup), [S2-1 inputs](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-1-inputs), [S2-0 report](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-report), [S2-0 zero-context check](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-zero-context-check) and [Gate S2-0](../evidence/P7_REBASELINE_GATE.md#gate-s2-0).
- The gate record's S2-1 sections: [S2-0 acceptance and S2-1 authorization](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-0-acceptance-and-s2-1-authorization-dir-063-dir-064), [S2-1 verification](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-1-verification), [S2-1 documents](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-1-documents), [Owner questions after S2-1](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-questions-after-s2-1), [S2-2 inputs](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-2-inputs), [S2-1 report](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-1-report), [S2-1 zero-context check](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-1-zero-context-check) and [Gate S2-1](../evidence/P7_REBASELINE_GATE.md#gate-s2-1).
- The S2-2 decisions and authorization: [DIR-065 and DIR-066](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes); the Golden-first decisions and the pilot's authorization: [DIR-067 and DIR-068](../../00-governance/DECISION_LOG.md#dir-067-dir-068-obs-026-and-tech-034--p7-ux-re-baseline-golden-first-decisions-pilot-authorization-baseline-and-pilot).
- The gate record's S2-2 sections: [S2-2 decisions and authorization](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-2-decisions-and-authorization-dir-065-dir-066), [S2-2 verification](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-2-verification), [S2-2 build and checks](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-2-build-and-checks), [S2-2 report](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-2-report), [S2-2 zero-context check](../evidence/P7_REBASELINE_GATE.md#stage-2--s2-2-zero-context-check) and [Gate S2-2](../evidence/P7_REBASELINE_GATE.md#gate-s2-2).
- The gate record's GF-1 sections: [Golden-first decisions and authorization](../evidence/P7_REBASELINE_GATE.md#stage-2--golden-first-decisions-and-authorization-dir-067-dir-068), [GF-1 verification](../evidence/P7_REBASELINE_GATE.md#stage-2--gf-1-verification), [GF-1 report](../evidence/P7_REBASELINE_GATE.md#stage-2--gf-1-report), [GF-1 zero-context check](../evidence/P7_REBASELINE_GATE.md#stage-2--gf-1-zero-context-check) and [Gate GF-1](../evidence/P7_REBASELINE_GATE.md#gate-gf-1).
- The current state and the next safe action: [CURRENT_HANDOFF](../../handoff/CURRENT_HANDOFF.md).
