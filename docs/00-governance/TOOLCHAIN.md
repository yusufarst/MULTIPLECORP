# Toolchain

Status: REVIEW | Updated: 2026-10-03 | Owner: Planning

The project toolchain registry (AICWDF §5–§7, §6A). It records capabilities, their honest status and the operating rules for tools and Git in this repository. The capability is the requirement; a named tool is the preference (AICWDF §5).

## Capabilities

Nothing was installed, configured or verified by the AICWDF migration (DIR-039 forbids installation). "Not yet verified" means the named phase checks availability and compatibility and records the result here (AICWDF §6.8).

| Capability | Preferred tool | Project status | Verified by |
| --- | --- | --- | --- |
| Current documentation (§5.1) | Context7, or current official documentation | Not yet verified. Earlier phases cited official documentation, recorded in each specification's source and version evidence | The first execution Task after P11; version-sensitive design work before that |
| Codebase impact analysis (§5.2) | Graphify, or equivalent targeted analysis | Not yet verified; no application code exists, so it is deferred (§6.4) | The first Tasks after P11 |
| Browser E2E (§5.3) | Playwright | Not yet verified. [ENGINEERING_PRINCIPLES](ENGINEERING_PRINCIPLES.md#verification-and-completion) records the brief's UI/E2E regression tool, TestSprite; P8 chooses the E2E capability (§6.5) | P8 |
| UI components (§5.4) | shadcn/ui on Tailwind CSS | Owner baseline ([ARCHITECTURE §1](../04-architecture/ARCHITECTURE.md#1-baseline-and-constraints)); nothing installed | The first UI Tasks after P11 |
| Visual reference (§5.5) | DesainPakai AI; the Owner-chosen Ramp design reference (D3) | Not yet verified in this repository. DesainPakai AI was used in P7 as an exploration tool outside the repository and is never a source of truth ([P7 gate](../07-ux-design/evidence/P7_QUALITY_GATE.md)); Ramp guides the P7 re-baseline | The P7 re-baseline |
| Static and automated verification (§5.6) | Lint, typecheck, unit, feature, integration, authorization, route, E2E, build and security checks in CI | Not yet verified; the strategy is P8's | P8 |

A tool becomes required only when a Task or phase needs its capability; an existing equivalent is reused (§6.5). Installation happens only inside an authorized task, project-local first, never as a silent major upgrade, and is recorded with its version, scope and verification (§6.2–§6.8). Paid tools follow [COST_POLICY](COST_POLICY.md).

## MCP servers

Installing a tool and exposing its MCP schema to every session are separate decisions (§6A). Heavy or specialized MCP servers default to OFF and are activated only for the session whose Task needs them; a native CLI or package is preferred when it suffices. No MCP server is recorded as installed for this project. When a Task uses one, add a row:

| MCP server | Capability | Installed | Session default | Activate when | Deactivate when | Scope | Heavy context risk |
| --- | --- | --- | --- | --- | --- | --- | --- |
| — | — | — | — | — | — | — | — |

## Git line endings and staging

The Owner's Windows checkout uses `core.autocrlf=true`, so some Markdown working copies hold CRLF while their committed blobs are LF. A Git without CRLF normalization (for example a Linux shell over the same folder) lists such files as modified although each equals its blob after CR removal: confirm with `git diff --ignore-cr-at-eol --stat` (empty) before reporting a dirty tree. Stage new and changed Markdown with CRLF normalization, for example `git -c core.autocrlf=input add`, so blobs stay LF; never rewrite a file only to normalize its line endings. Source records under `docs/00-governance/sources/` are byte-preserved through their own `.gitattributes` entries.

Commits use only the Owner's configured identity with no AI attribution ([DIR-022](../../AGENTS.md#git-commit-attribution)); pushes are normal and non-force; history is never rewritten.

## Public repository

[yusufarst/MULTIPLECORP](https://github.com/yusufarst/MULTIPLECORP) is public. Never commit real client or personal data, secrets, credentials, live `.env` files, production configuration, backups or raw migration files; sanitized examples hold placeholders only ([ENGINEERING_PRINCIPLES](ENGINEERING_PRINCIPLES.md#secrets-public-repository-and-production-boundary)).
