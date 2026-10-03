# Migration map — AICWDF structural migration

Status: REVIEW | Updated: 2026-10-03 | Owner: Planning

This record resolves every path of the pre-migration baseline to its place after the AICWDF v4.3 structural migration authorized by DIR-039 and recorded under [TECH-023](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration), and holds the evidence that the moves and splits changed no content. It is evidence, not a specification: moved and split documents keep their own status and approval, and historical records that cite old paths — decision-log entries and approval tables, the changelog, quality gates, the archived handoff and the source records — are resolved through this map instead of being rewritten.

## Reference points

| Point | Commit | Meaning |
| --- | --- | --- |
| Tag `pre-aicwdf-migration` | `c511d7b0d4683e07717c962927c9f113854f227b` | P7 checkpoint and pre-migration baseline: 71 tracked files |
| Commit 0 — stage 0 | `c9ba88b28f398c8c6177ab2b2a6df8d8ef50e017` | `docs: record AICWDF adoption directives and migration baseline`: seven source records added and registered |
| Stage 1 | `2a89d9ea892cacf5ddae57ec3b5911b9ce2ac7d8` | `docs: move planning documents into AICWDF phase structure`: the 21 moves below; link-target paths recomputed |
| Stage 2 — WORKFLOWS | `3f8bd90c2545517c3c66489e60dbf79e50b9b64e` | `docs: split WORKFLOWS into section files`: `docs/03-workflows/WORKFLOWS.md` split into 9 section files and a README |
| Stage 2 — DATABASE | `8b712a829676464035a4af6718f72ef3dab366b8` | `docs: split DATABASE into section files`: `docs/04-architecture/DATABASE.md` split into 24 section files and a README |
| Stage 2 — CONCURRENCY_IDEMPOTENCY | `db4e1f524579ae60812dd45a06bc6d89cad0938a` | `docs: split CONCURRENCY_IDEMPOTENCY into section files`: `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY.md` split into 8 section files and a README |
| Stage 2 — ADMIN_FLOW | `64431fcbca0134b4b268762cb765dc6520241ac2` | `docs: split ADMIN_FLOW into section files`: `docs/07-ux-design/ADMIN_FLOW.md` split into 8 section files and a README |
| Stage 3 | `0dbbb44f3ceb167a6243c8cad796a89c4d394239` | `docs: align governance and operating structure with AICWDF v4.3`: governance and operating-structure alignment (DIR-039 §10); no move or split |
| Finalization | the commit `docs: finalize AICWDF structural migration`, child of `0dbbb44` | Owner approval APPR-009 recorded, three narrow corrections and the stage-3 lifecycle from pending to approved (DIR-041); see [Approval](#approval) |

A commit cannot record its own SHA; a later commit is resolved with `git log -1 --format='%H %s' --grep='^<exact message>$'`.

## Proof procedure

Anyone can reproduce each proof from the tag with any Git client and any SHA-256 and text tool:

1. Read every file as its committed content (`git show <revision>:<path>`, LF line endings), never as a working copy, at the tag, at commit 0 and at the stage-1 commit. Hashes are SHA-256 of that content.
2. **Mapping.** Each path at the tag maps to the path the move table gives, or to itself when it is not listed. Every mapped path exists at stage 1 and no two old paths share one. The only paths at stage 1 that are neither a mapped tag path nor one of the seven stage-0 source records are this file and `docs/handoff/archive/README.md`.
3. **Bytes.** Every file under `docs/00-governance/sources/` and every non-Markdown file is byte-identical between commit 0 and stage 1, and the 29 source records of the tag are byte-identical to the tag.
4. **Neutralization.** For every other Markdown file, take its commit-0 version and its stage-1 version. In each, replace the target of every inline link — the text inside the parentheses that immediately follow a link label's closing `]` — outside fenced code blocks and inline code with the fixed text `LINK`. In the ownership-registry rows of SOURCE_OF_TRUTH, also replace each backtick-quoted path that names a moved file with `PATH` — the old path in the commit-0 version, the new one in the stage-1 version; CONTEXT_INDEX, AGENTS.md, CLAUDE.md and README.md, the other files where such tokens may be remapped, hold none. The two results must be byte-identical and have the same number of lines.
5. **Link equivalence.** List the links of both versions in order: their number and labels must be equal. Resolve each target relative to its own file — the commit-0 location for the old version, the stage-1 location for the new — and map the old result through the move table: it must equal the new result, and the anchors (the part after `#`) must be identical. The remapped path tokens, mapped through the move table in order, must equal the new tokens.
6. **Resolution.** Every relative link target at stage 1 outside `sources/` names an existing file and, where it has an anchor, a heading of that file whose GitHub-style anchor equals it — the heading text lower-cased, characters other than letters, digits, spaces, hyphens and underscores removed, spaces replaced by hyphens.

## Moves

| Old path (tag) | New path (stage 1) |
| --- | --- |
| `docs/00-governance/P0_QUALITY_GATE.md` | `docs/00-governance/evidence/P0_QUALITY_GATE.md` |
| `docs/01-product/P1_QUALITY_GATE.md` | `docs/01-product/evidence/P1_QUALITY_GATE.md` |
| `docs/02-domain/P2_QUALITY_GATE.md` | `docs/02-domain/evidence/P2_QUALITY_GATE.md` |
| `docs/02-domain/WORKFLOWS.md` | `docs/03-workflows/WORKFLOWS.md` |
| `docs/02-domain/P3_QUALITY_GATE.md` | `docs/03-workflows/evidence/P3_QUALITY_GATE.md` |
| `docs/02-domain/PERMISSIONS_MATRIX.md` | `docs/05-security/PERMISSIONS_MATRIX.md` |
| `docs/03-architecture/DATABASE.md` | `docs/04-architecture/DATABASE.md` |
| `docs/03-architecture/ARCHITECTURE.md` | `docs/04-architecture/ARCHITECTURE.md` |
| `docs/03-architecture/P4_QUALITY_GATE.md` | `docs/04-architecture/evidence/P4_QUALITY_GATE.md` |
| `docs/03-architecture/SECURITY.md` | `docs/05-security/SECURITY.md` |
| `docs/03-architecture/P5_QUALITY_GATE.md` | `docs/05-security/evidence/P5_QUALITY_GATE.md` |
| `docs/03-architecture/CONCURRENCY_IDEMPOTENCY.md` | `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY.md` |
| `docs/03-architecture/PERFORMANCE.md` | `docs/06-api-performance/PERFORMANCE.md` |
| `docs/03-architecture/API_AND_INTEGRATIONS.md` | `docs/06-api-performance/API_AND_INTEGRATIONS.md` |
| `docs/03-architecture/P6_QUALITY_GATE.md` | `docs/06-api-performance/evidence/P6_QUALITY_GATE.md` |
| `docs/04-ux/ADMIN_FLOW.md` | `docs/07-ux-design/ADMIN_FLOW.md` |
| `docs/04-ux/INFORMATION_ARCHITECTURE.md` | `docs/07-ux-design/INFORMATION_ARCHITECTURE.md` |
| `docs/04-ux/DESIGN_SYSTEM.md` | `docs/07-ux-design/DESIGN_SYSTEM.md` |
| `docs/04-ux/P7_QUALITY_GATE.md` | `docs/07-ux-design/evidence/P7_QUALITY_GATE.md` |
| `docs/07-handoff/CURRENT_STATE.md` | `docs/handoff/archive/CURRENT_STATE_2026-10-02.md` |
| `docs/07-handoff/NEXT_ACTION.md` | `docs/handoff/archive/NEXT_ACTION_2026-10-02.md` |

Every other tracked file keeps its path.

## Baseline file map

Proof codes: **bytes** — byte-identical at the tag, commit 0 and stage 1; **neutral (n/m)** — identical after neutralization at stage 1, with n of its m link targets rewritten; **+ k tokens** — k remapped registry path tokens; **G0 +** — the file first received the stage-0 additions verified by G0.

| Old path (tag) | SHA-256 at the tag | New path (stage 1) | SHA-256 at stage 1 | Proof |
| --- | --- | --- | --- | --- |
| `.gitattributes` | `0FA4988D8C5E5D5C269F43E5B0440B974E485C5BEE19DD2C2DB4FAC89CF63B75` | `.gitattributes` | `92771946B3DCE2AADF05D897B05C1D07735AF3CB37C3476515D989668ADE79B5` | G0 + bytes |
| `.gitignore` | `6603B4F23A3A7F5927967354DAAE0E271446F169B02AFE45F839F11C985BD36B` | `.gitignore` | `6603B4F23A3A7F5927967354DAAE0E271446F169B02AFE45F839F11C985BD36B` | bytes |
| `AGENTS.md` | `22504486383E653B6E1AFFDFA79BD26B4E70A285567F597E1F37DEFB9DD9CFF4` | `AGENTS.md` | `0C8F9600EEFDA71944E8C56469143D1B2A384CADB12BE4D5E275B1F2BB073932` | neutral (20/43) |
| `CHANGELOG.md` | `A4C21EDF41F6B14F9740DC29B223995540447A7209EAD22AD9F2E5D82FC7204F` | `CHANGELOG.md` | `E34D79E59A37CC0D6A9502521DDDAAECE36521CB1F6B250B10E5CEC4379CDD0C` | G0 + bytes |
| `CLAUDE.md` | `140250A11D1002913D05B2B2936AE1D84EAF6DA339AE34D886F93C72C37D9DF9` | `CLAUDE.md` | `140250A11D1002913D05B2B2936AE1D84EAF6DA339AE34D886F93C72C37D9DF9` | bytes |
| `README.md` | `27EDF2E57C9C35FE4C03356BCD9CD62C9FD7F46CFE92AAA73A9B4DE287549D68` | `README.md` | `62E66CA28F547937802AE574B7C768B743B4B5711B3F515BC8A9DD5732B69F51` | neutral (13/34) |
| `docs/00-governance/AGENT_OPERATING_MODEL.md` | `7900720E66914DFE1D3874256329305F520082C2C32F8C8A10A1F628430F5611` | `docs/00-governance/AGENT_OPERATING_MODEL.md` | `758DA660200AA7F99A7863DFB752CE37931D11E3760D1E486077246E28CD6240` | neutral (2/17) |
| `docs/00-governance/CHANGE_CONTROL.md` | `1D41271CBA8101910D2C12C135504BCD1DB9B6E230C40F935A700D6EF60EB41F` | `docs/00-governance/CHANGE_CONTROL.md` | `1D41271CBA8101910D2C12C135504BCD1DB9B6E230C40F935A700D6EF60EB41F` | bytes |
| `docs/00-governance/DECISION_LOG.md` | `833B763D30ADBE5EB438F2CFF86D653EFCB6A4EBDB528E0A834E9BABA67F991F` | `docs/00-governance/DECISION_LOG.md` | `008D8C5BAC53A9256806EDAC0DC6E18A444B1A8C0B564B3BE948270D3D83FCA4` | G0 + neutral (58/138) |
| `docs/00-governance/ENGINEERING_PRINCIPLES.md` | `FF1D7948C94B5DD94DB1D99DD194CC1A1369436E2312C3E8409C90E1D3361FF4` | `docs/00-governance/ENGINEERING_PRINCIPLES.md` | `8D74105268DC8B3C812A21ED7CEECFF87C9A02CFC873D2445BE5261E8B5BC71E` | neutral (9/27) |
| `docs/00-governance/GAP_REGISTER.md` | `6C0D7724410126226FC2F2E05046B3FA9E3421B9E6C046C879C5BF3FBD90C6DC` | `docs/00-governance/GAP_REGISTER.md` | `A3B10BBFB36AB1DA04D29DF1FC33F6C3BE4AEC95DA993E2CB4689446583994EC` | neutral (26/43) |
| `docs/00-governance/P0_QUALITY_GATE.md` | `11CD1E0FAAB8B6CBC3FA84377C33CA83F734B0D6E52818E71D6E3FB28CCDD686` | `docs/00-governance/evidence/P0_QUALITY_GATE.md` | `F6BD692E9FEA5728D04A7E72ACA92E11F6E2F898902E36ECA9DD5051FD575EF4` | neutral (9/9) |
| `docs/00-governance/PROJECT_CHARTER.md` | `D96AB5BB37390A7367645D130BB66978A7F8AAF303245317A319D51025319BEA` | `docs/00-governance/PROJECT_CHARTER.md` | `D96AB5BB37390A7367645D130BB66978A7F8AAF303245317A319D51025319BEA` | bytes |
| `docs/00-governance/SOURCE_OF_TRUTH.md` | `E5486A99E5DB07104649E85E3C3E711E0A712BF77C25687DFB11007403C8B285` | `docs/00-governance/SOURCE_OF_TRUTH.md` | `8557AC3BD40E217AB3CF1DBDCFB4BDAC74E4B094AA891A08EF50F05EC5DD7663` | G0 + neutral (15/82) + 19 tokens |
| `docs/00-governance/sources/ARCHITECT_MANDATE_2026-09-27.txt` | `1FAB6231FB214E0147C0F22C4FEA357B72BA7C5118D5D24C9770F093901EB56E` | `docs/00-governance/sources/ARCHITECT_MANDATE_2026-09-27.txt` | `1FAB6231FB214E0147C0F22C4FEA357B72BA7C5118D5D24C9770F093901EB56E` | bytes |
| `docs/00-governance/sources/ChatGPT Image Sep 27, 2026, 07_54_30 PM.png` | `101FA4399201C90CA0045BC36BB3973CD4304FBAC8296842B1D2CDF78988E585` | `docs/00-governance/sources/ChatGPT Image Sep 27, 2026, 07_54_30 PM.png` | `101FA4399201C90CA0045BC36BB3973CD4304FBAC8296842B1D2CDF78988E585` | bytes |
| `docs/00-governance/sources/MultipleCorp_Scope_Flow_Definition_of_Done_Astra_Reference.pdf` | `C60DC64A140F734752FCD97C4AEE5F4F6D33EAE644031A37F6FB0360D31577BF` | `docs/00-governance/sources/MultipleCorp_Scope_Flow_Definition_of_Done_Astra_Reference.pdf` | `C60DC64A140F734752FCD97C4AEE5F4F6D33EAE644031A37F6FB0360D31577BF` | bytes |
| `docs/00-governance/sources/OWNER_BRIEF_2026-09-27.txt` | `D455EBA17A9D596BEFCC0C374F68F9EF6B077214E0D279498EBC090AC2FA7A55` | `docs/00-governance/sources/OWNER_BRIEF_2026-09-27.txt` | `D455EBA17A9D596BEFCC0C374F68F9EF6B077214E0D279498EBC090AC2FA7A55` | bytes |
| `docs/00-governance/sources/P1_FINAL_DECISIONS_RECOVERY_2026-09-28.txt` | `443B64CCD1ED92445682EF7F8F157DB75D1FDAFD0E37E10042D25B5FEB3E30C3` | `docs/00-governance/sources/P1_FINAL_DECISIONS_RECOVERY_2026-09-28.txt` | `443B64CCD1ED92445682EF7F8F157DB75D1FDAFD0E37E10042D25B5FEB3E30C3` | bytes |
| `docs/00-governance/sources/P1_FINAL_OWNER_DECISIONS_2026-09-28.txt` | `8F6ACB2A9849AAF49FEA6410A4C2DC2AA927CB8F36808B79FE5B67E1D7406E98` | `docs/00-governance/sources/P1_FINAL_OWNER_DECISIONS_2026-09-28.txt` | `8F6ACB2A9849AAF49FEA6410A4C2DC2AA927CB8F36808B79FE5B67E1D7406E98` | bytes |
| `docs/00-governance/sources/P1_OWNER_APPROVAL_2026-09-28.txt` | `755041DE544AAF00277DC584E0FB04664001F9AE468D2387E449BC804137CA17` | `docs/00-governance/sources/P1_OWNER_APPROVAL_2026-09-28.txt` | `755041DE544AAF00277DC584E0FB04664001F9AE468D2387E449BC804137CA17` | bytes |
| `docs/00-governance/sources/P1_OWNER_BACKUP_POLICY_2026-09-27.txt` | `1C2D27FC4F6E0D4F9D5404BEAC723F25E2474EBB9E177B76905956CC0629E571` | `docs/00-governance/sources/P1_OWNER_BACKUP_POLICY_2026-09-27.txt` | `1C2D27FC4F6E0D4F9D5404BEAC723F25E2474EBB9E177B76905956CC0629E571` | bytes |
| `docs/00-governance/sources/P1_OWNER_CHECKPOINT_AUTHORIZATION_2026-09-28.txt` | `AE18F2F6A5E5AE0A6F013AD7638D77A86520937D2F946383F0AAAE8B76E77E65` | `docs/00-governance/sources/P1_OWNER_CHECKPOINT_AUTHORIZATION_2026-09-28.txt` | `AE18F2F6A5E5AE0A6F013AD7638D77A86520937D2F946383F0AAAE8B76E77E65` | bytes |
| `docs/00-governance/sources/P1_OWNER_CLARIFICATIONS_2026-09-27.txt` | `C89ED0128EFA759C33AA8A1E88024420A08526F735308ECCA66838D8D657AA51` | `docs/00-governance/sources/P1_OWNER_CLARIFICATIONS_2026-09-27.txt` | `C89ED0128EFA759C33AA8A1E88024420A08526F735308ECCA66838D8D657AA51` | bytes |
| `docs/00-governance/sources/P1_OWNER_DIRECTIVE_2026-09-27.txt` | `B3DBFCE778AA4CEED126ED16AC0113F983BC66595604CD893FC5AB391C566B0E` | `docs/00-governance/sources/P1_OWNER_DIRECTIVE_2026-09-27.txt` | `B3DBFCE778AA4CEED126ED16AC0113F983BC66595604CD893FC5AB391C566B0E` | bytes |
| `docs/00-governance/sources/P1_OWNER_PUBLICATION_CONFIRMATION_2026-09-29.txt` | `1939FED4EF714C75FC1A3762FC56F6ADE2DB66CCB3F835DD3044EABE8C632931` | `docs/00-governance/sources/P1_OWNER_PUBLICATION_CONFIRMATION_2026-09-29.txt` | `1939FED4EF714C75FC1A3762FC56F6ADE2DB66CCB3F835DD3044EABE8C632931` | bytes |
| `docs/00-governance/sources/P1_REFERENCE_INGESTION_DIRECTIVE_2026-09-27.txt` | `B0F182F2BE5F976C571C6547210BD7D445AC730CD53FEF74BC6C254915B932C4` | `docs/00-governance/sources/P1_REFERENCE_INGESTION_DIRECTIVE_2026-09-27.txt` | `B0F182F2BE5F976C571C6547210BD7D445AC730CD53FEF74BC6C254915B932C4` | bytes |
| `docs/00-governance/sources/P2_OWNER_APPROVAL_2026-09-29.txt` | `E414A6C2304329930A7F81D10F1D9E5588EC6EBC279B279C99025DDD5AA685CA` | `docs/00-governance/sources/P2_OWNER_APPROVAL_2026-09-29.txt` | `E414A6C2304329930A7F81D10F1D9E5588EC6EBC279B279C99025DDD5AA685CA` | bytes |
| `docs/00-governance/sources/P2_OWNER_AUTHORIZATION_2026-09-29.txt` | `A710619AFD59D788E042E7D55308164CE1F0FC16B8C85AB43909A1E95C837535` | `docs/00-governance/sources/P2_OWNER_AUTHORIZATION_2026-09-29.txt` | `A710619AFD59D788E042E7D55308164CE1F0FC16B8C85AB43909A1E95C837535` | bytes |
| `docs/00-governance/sources/P2_OWNER_DEEP_REVIEW_DIRECTIVE_2026-09-29.txt` | `97C0528D6B25F0760F9CDDA9E70829419F64F40D80E8BA1C1B5D674129DE1B8E` | `docs/00-governance/sources/P2_OWNER_DEEP_REVIEW_DIRECTIVE_2026-09-29.txt` | `97C0528D6B25F0760F9CDDA9E70829419F64F40D80E8BA1C1B5D674129DE1B8E` | bytes |
| `docs/00-governance/sources/P2_OWNER_FEE_GENERALIZATION_DECISION_2026-09-29.txt` | `4F361A7F4E57343D1347879FBE85AA0939EAF4D0AC17E835CF219F594769ED44` | `docs/00-governance/sources/P2_OWNER_FEE_GENERALIZATION_DECISION_2026-09-29.txt` | `4F361A7F4E57343D1347879FBE85AA0939EAF4D0AC17E835CF219F594769ED44` | bytes |
| `docs/00-governance/sources/P2_OWNER_FINAL_DECISIONS_2026-09-29.txt` | `71EA03F2930380069E97C0BF706B0BA5841E29A55394509CFBD3BE981A5272A5` | `docs/00-governance/sources/P2_OWNER_FINAL_DECISIONS_2026-09-29.txt` | `71EA03F2930380069E97C0BF706B0BA5841E29A55394509CFBD3BE981A5272A5` | bytes |
| `docs/00-governance/sources/P2_OWNER_Q1_DATES_NUMBERING_DECISION_2026-09-29.txt` | `FF04E60CB98CA5C7BAF47E933C22B0D2062D40FA6016929FE2B4916DE421DF1D` | `docs/00-governance/sources/P2_OWNER_Q1_DATES_NUMBERING_DECISION_2026-09-29.txt` | `FF04E60CB98CA5C7BAF47E933C22B0D2062D40FA6016929FE2B4916DE421DF1D` | bytes |
| `docs/00-governance/sources/P3_OWNER_AUTHORIZATION_2026-09-29.txt` | `310426188804BFF3963DBC18493E2F5FFB288803B397DA072633C16AAA7228EA` | `docs/00-governance/sources/P3_OWNER_AUTHORIZATION_2026-09-29.txt` | `310426188804BFF3963DBC18493E2F5FFB288803B397DA072633C16AAA7228EA` | bytes |
| `docs/00-governance/sources/P3_OWNER_DECISIONS_2026-09-29.txt` | `24B54C7A5087D140CEAC89172E0399D87379A718657DA5924341782BF3008A20` | `docs/00-governance/sources/P3_OWNER_DECISIONS_2026-09-29.txt` | `24B54C7A5087D140CEAC89172E0399D87379A718657DA5924341782BF3008A20` | bytes |
| `docs/00-governance/sources/P3_OWNER_LOSS_ATTRIBUTION_AND_FINALIZATION_2026-09-29.txt` | `22784B8CB8741A23965B0669DAFC52B88D451826E8692779DFB98E22F6D28FDE` | `docs/00-governance/sources/P3_OWNER_LOSS_ATTRIBUTION_AND_FINALIZATION_2026-09-29.txt` | `22784B8CB8741A23965B0669DAFC52B88D451826E8692779DFB98E22F6D28FDE` | bytes |
| `docs/00-governance/sources/P3_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-29.txt` | `71BBF59B622808756069A161B19B9BF4EC55D2C91FC0E351E6453875D9770D97` | `docs/00-governance/sources/P3_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-29.txt` | `71BBF59B622808756069A161B19B9BF4EC55D2C91FC0E351E6453875D9770D97` | bytes |
| `docs/00-governance/sources/P4_OWNER_AUTHORIZATION_2026-09-29.txt` | `FFD64781EFB95B5A8E12D77105E1ABC60EFF6011EEE7ACAD3A6E9F6DABBBD337` | `docs/00-governance/sources/P4_OWNER_AUTHORIZATION_2026-09-29.txt` | `FFD64781EFB95B5A8E12D77105E1ABC60EFF6011EEE7ACAD3A6E9F6DABBBD337` | bytes |
| `docs/00-governance/sources/P4_OWNER_CORRECTION_APPROVAL_AND_PUBLICATION_2026-09-30.txt` | `7743A14D60360A550E3C006E2DF430C6DB1608EC62DD7CFBFF9C197588EE9A90` | `docs/00-governance/sources/P4_OWNER_CORRECTION_APPROVAL_AND_PUBLICATION_2026-09-30.txt` | `7743A14D60360A550E3C006E2DF430C6DB1608EC62DD7CFBFF9C197588EE9A90` | bytes |
| `docs/00-governance/sources/P4_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-30.txt` | `B83B06F8C5129EB41920A20E1CB3BB955B89B98647571B25736317831B3F35C8` | `docs/00-governance/sources/P4_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-30.txt` | `B83B06F8C5129EB41920A20E1CB3BB955B89B98647571B25736317831B3F35C8` | bytes |
| `docs/00-governance/sources/P5_OWNER_AUTHORIZATION_2026-09-30.txt` | `9220904F8AB5A2A801223B7C4DA621679C33B0E1E39057648AC96CDB73F3C282` | `docs/00-governance/sources/P5_OWNER_AUTHORIZATION_2026-09-30.txt` | `9220904F8AB5A2A801223B7C4DA621679C33B0E1E39057648AC96CDB73F3C282` | bytes |
| `docs/00-governance/sources/P6_OWNER_AUTHORIZATION_2026-09-30.txt` | `38F89EE64D3FA138322875DB10771C50D0BC8F0268785F37A6351558AC2021DC` | `docs/00-governance/sources/P6_OWNER_AUTHORIZATION_2026-09-30.txt` | `38F89EE64D3FA138322875DB10771C50D0BC8F0268785F37A6351558AC2021DC` | bytes |
| `docs/00-governance/sources/P7_OWNER_AUTHORIZATION_2026-10-01.txt` | `BA5CDD8ADDA52803B1FE8187ECE9D4E65AA88690933BFAD2053E984C7BEF8F60` | `docs/00-governance/sources/P7_OWNER_AUTHORIZATION_2026-10-01.txt` | `BA5CDD8ADDA52803B1FE8187ECE9D4E65AA88690933BFAD2053E984C7BEF8F60` | bytes |
| `docs/01-product/ACCEPTANCE_CRITERIA.md` | `648738078489AC3CC304050FCE12AF9A62AEF417E38A6DF1A245488DEC470A0D` | `docs/01-product/ACCEPTANCE_CRITERIA.md` | `648738078489AC3CC304050FCE12AF9A62AEF417E38A6DF1A245488DEC470A0D` | bytes |
| `docs/01-product/P1_QUALITY_GATE.md` | `B2DEA405FC43B58EE60CF6F2449FBA4E11C8566B7D51FB4F7DBBD62E8F5E7F49` | `docs/01-product/evidence/P1_QUALITY_GATE.md` | `BA0DD394BEAEFF9A760CF6E079C8B4A4A0D5A236011F6E9D738FD4609AA75E24` | neutral (1/1) |
| `docs/01-product/PRODUCT_OVERVIEW.md` | `DB6D444C9A7DF7054168E78FCEA935DB99617E698E6984773189F72395258F00` | `docs/01-product/PRODUCT_OVERVIEW.md` | `B52E2B37207037AEE1127D02A6263E1833CA039E3BFBE21B86DE39C5B28F1510` | neutral (1/16) |
| `docs/01-product/REFERENCE_COVERAGE.md` | `BFA9B4914EFD66BB0477040983DDD865BD7F771870D4DD7B0AB96E7BE0E874EC` | `docs/01-product/REFERENCE_COVERAGE.md` | `BFA9B4914EFD66BB0477040983DDD865BD7F771870D4DD7B0AB96E7BE0E874EC` | bytes |
| `docs/01-product/V1_SCOPE.md` | `743D55BB7DD28F21FFE8142E1A843368AF8A7938C06AFEE630F95D323ADF7B5A` | `docs/01-product/V1_SCOPE.md` | `743D55BB7DD28F21FFE8142E1A843368AF8A7938C06AFEE630F95D323ADF7B5A` | bytes |
| `docs/02-domain/BUSINESS_RULES.md` | `48B45AA59DBA6C713BEC729DB81E16DECFDDCDB159FAAF183370FD77F31E0DE1` | `docs/02-domain/BUSINESS_RULES.md` | `6CB65FA9E77C0C2E014F849865A3E29DBE2F24E231E71BF1D3CDF9421953E8AE` | neutral (3/16) |
| `docs/02-domain/DOMAIN_MODEL.md` | `8027099D2FE050860E9AD4EFD8D5CB44838D601C85B45A12839EFE27E9BCE86A` | `docs/02-domain/DOMAIN_MODEL.md` | `644985C8E4634F2AF005B2855125A4BA5A3C1AEF6F6558B7CDC04087005651EF` | neutral (2/14) |
| `docs/02-domain/P2_QUALITY_GATE.md` | `2F2570621A575CFB101FAA248EA1B31AD4BE72A24A7588C0418D7CCEF7DFF7A4` | `docs/02-domain/evidence/P2_QUALITY_GATE.md` | `2739A6E2E60AA8F1E0A3E11BD187AC6F494054CC4870A00755341679BE02D1DD` | neutral (6/6) |
| `docs/02-domain/P3_QUALITY_GATE.md` | `166686FF0D970332425A3A902B2F93869DE520B0B3FB616BE9EF93E69158C66F` | `docs/03-workflows/evidence/P3_QUALITY_GATE.md` | `AE7E7E44A335BCEAEB160F6C047DB5FF8C446630BA41D47B3321D662EA409321` | neutral (5/5) |
| `docs/02-domain/PERMISSIONS_MATRIX.md` | `D3BF4D13DF2188E05FF3A89864C54C2074BD1EA372E5E6F537FE04E1507229ED` | `docs/05-security/PERMISSIONS_MATRIX.md` | `D1C2C296A519E8875072678A16C4545086C52D8F4CD2EF3D6F33DE19BFEE73C6` | neutral (18/27) |
| `docs/02-domain/WORKFLOWS.md` | `6E413D012A8768E51612D1D11357EA41FF20B2E126673C1B10E292B56C712035` | `docs/03-workflows/WORKFLOWS.md` | `9B0BED95FAB3874963BFC0FEB73AEE8B9401B216A14ED89BFFF452D4D2A58002` | neutral (5/15) |
| `docs/03-architecture/API_AND_INTEGRATIONS.md` | `8B8515D52A33786B1017699A314435C54B19BE2BBDAB79C5FD04702345F4259C` | `docs/06-api-performance/API_AND_INTEGRATIONS.md` | `B7DB27550B52ADEFC123D8C1A6DA110C8F19F2D71F00A5ACA4A8AB28741B3FFE` | neutral (4/8) |
| `docs/03-architecture/ARCHITECTURE.md` | `E1AEF4658899E656FA5A8BEB1201CBD712DA48FC7CB79792A98CB0B965632312` | `docs/04-architecture/ARCHITECTURE.md` | `DF197C0318D97A1E673EDB1EC10CF6380D97EF3C2970D4D34815EF1FB06B46C3` | neutral (7/16) |
| `docs/03-architecture/CONCURRENCY_IDEMPOTENCY.md` | `454D8784788E4E20BDBAEF136A03C82F09C3BB3FE71A99DB0987069CEFC22E21` | `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY.md` | `3D2BBBEAAB5BB0EFD3E950790F5821C2990F68413679C1B0BF8271D7BCA18D6A` | neutral (15/55) |
| `docs/03-architecture/DATABASE.md` | `2CCCF57CA224A0B8A154D7B21602E6AD164095858CF849A805D7C9DC70BEE50D` | `docs/04-architecture/DATABASE.md` | `01B6B2F16B55E8FB9AC364FF7BD2B44814D86428BA302935BCBEDE6A9E6B9877` | neutral (15/32) |
| `docs/03-architecture/P4_QUALITY_GATE.md` | `94BA40420F06A5091A8A9D059C4A6946A1B102008E796EF2E4794A0C59CB5A29` | `docs/04-architecture/evidence/P4_QUALITY_GATE.md` | `09367964B5C9A558CFF0F9389BAFC3FC62269DF4F7D6ED5747C82A589D5E7DFC` | neutral (6/6) |
| `docs/03-architecture/P5_QUALITY_GATE.md` | `8D7C1A8C473A48822337AB986C679F6C64AC466E4295F9CF932C99A6410B6CF2` | `docs/05-security/evidence/P5_QUALITY_GATE.md` | `343DBEE9FF0070FED259CCA9DC3590D01A1A18310C7DC23B5CD58B6E290EBBB3` | neutral (9/9) |
| `docs/03-architecture/P6_QUALITY_GATE.md` | `6349169B6C9A9C9FCB46EAA6ED2DC86C54E8FF39A8037F9DF4FAEB9C4A383683` | `docs/06-api-performance/evidence/P6_QUALITY_GATE.md` | `3D2D0CCA8A1CD32FA1DC6C0A083C2408D94E24A138A4AFC36C2FB515C693108C` | neutral (16/19) |
| `docs/03-architecture/PERFORMANCE.md` | `C603D287B59F378E8327FA4B6B4FE7BEC28330C867F76E156A961542DDCD2949` | `docs/06-api-performance/PERFORMANCE.md` | `A8A57508F36AF5153FB8E29ED74AA2EFF5B278384EE75717D2665B4C38D1FDE8` | neutral (7/18) |
| `docs/03-architecture/SECURITY.md` | `C9D358A6A643BA631B2E4662B210E74CD7404D24A536B78A2EB0DBE6F3B29AF4` | `docs/05-security/SECURITY.md` | `6BD8806828402CBA60F1BC8C058B842AA48C2FAAF017B5F210A98697F3785245` | neutral (6/11) |
| `docs/04-ux/ADMIN_FLOW.md` | `5C78B9AF5EBBE072C17C204989FB1B9879C0494435D722368F7D241E84640DAC` | `docs/07-ux-design/ADMIN_FLOW.md` | `0E0496530C3DEF6AF98473C768E09A87193E1D1853534B1A77AB77E60D9CBF42` | neutral (12/56) |
| `docs/04-ux/DESIGN_SYSTEM.md` | `26B5A41812A9D1F8DF3328830BC88280F8609B2CE8530D710672731E23258AA6` | `docs/07-ux-design/DESIGN_SYSTEM.md` | `17C7A55F8A52DDE4FBEAAEBD982BF03DA6B63053A3AD44797E182E6506CA054A` | neutral (9/33) |
| `docs/04-ux/INFORMATION_ARCHITECTURE.md` | `F6D7E571CBEA90F2AB801BB8C42705B44FD2799FEAE4DFF55CC2AED344F3B448` | `docs/07-ux-design/INFORMATION_ARCHITECTURE.md` | `FFC7019E014A4B8B21C87EF4AFE7F3418D3BF9E030F56E7DFA3CBB0D468F7806` | neutral (9/79) |
| `docs/04-ux/P7_QUALITY_GATE.md` | `04500FB15DA14C549338BD2A740E5FEC9A800DD23C99DB8784F316369DBAEDD7` | `docs/07-ux-design/evidence/P7_QUALITY_GATE.md` | `62834038BD0948EA91CA5549B64EC36A8A057B86877E34C677B46F5D9BEFC1DB` | neutral (66/74) |
| `docs/07-handoff/CURRENT_STATE.md` | `0157FC3680A8F1169AA38A94533677D8CE643A0CC537422194019D717747C666` | `docs/handoff/archive/CURRENT_STATE_2026-10-02.md` | `C55ED1783F5C3DF9D05C0819D0F268359A10355DEEC7065F3821E115CE60B779` | neutral (41/41) |
| `docs/07-handoff/NEXT_ACTION.md` | `945F626195DAAD290216C43CFEB158F92AF02DF615E01D64A10B8071E162E448` | `docs/handoff/archive/NEXT_ACTION_2026-10-02.md` | `50F88451504B4EBEC1F6342FF8EC4915885DE7EC0DE297779760255A87ADF88F` | neutral (5/5) |
| `docs/CONTEXT_INDEX.md` | `6B354398AA7E8BEDF6503E95C346355BD25671BFA80933359D09E9922935394C` | `docs/CONTEXT_INDEX.md` | `DF7DDE88CF0418C06F893A0D4BC3B26C2B54F5A6E7810492D677510714BFA481` | neutral (26/95) |
| `docs/adr/ADR-001-repository-governance.md` | `E99136C8C82D5843AB54601348229CB06E8B61EF8B9D7292355849B18AF42CF6` | `docs/adr/ADR-001-repository-governance.md` | `E99136C8C82D5843AB54601348229CB06E8B61EF8B9D7292355849B18AF42CF6` | bytes |

## Files added at stage 0

| Path | SHA-256 at commit 0 and stage 1 | Proof |
| --- | --- | --- |
| `docs/00-governance/sources/AICWDF_MIGRATION_OWNER_AUTHORIZATION_2026-10-03.txt` | `9458DBBA8CB50B95AC04DF9C4D49656FEFD08305233D7204E295414ECE3C1A05` | bytes |
| `docs/00-governance/sources/AICWDF_OWNER_BLUEPRINT_APPROVAL_2026-10-03.txt` | `22DCBA9ED1F4E262DC9DD26BB0243EAF09DAAD009C56716C41E55885CD837143` | bytes |
| `docs/00-governance/sources/AICWDF_OWNER_CORRECTION_2026-10-03.txt` | `06E412EBF9E21899C2FCCBFD880921472B0A595463DC7DF3CA67A77005340588` | bytes |
| `docs/00-governance/sources/AICWDF_OWNER_DECISIONS_ROUND1_2026-10-03.txt` | `4201B95357520A469295A76EBF87B774289B79D199A97308E1014DEBD28E431A` | bytes |
| `docs/00-governance/sources/AICWDF_OWNER_FINAL_DECISIONS_2026-10-03.txt` | `313E25A7BB8BD9FDFF9E32EE121EE02B8DED1CF417E0D34B209367BDF58063DD` | bytes |
| `docs/00-governance/sources/AICWDF_OWNER_STRUCTURAL_CONFORMANCE_2026-10-03.txt` | `5568517B8CB31B54B1A97EEE36BBDADBE853A08BD380FCB66C01F30D8CF40875` | bytes |
| `docs/00-governance/sources/AI_First_Complex_WebApp_Task_Framework_v4.3_EN.md` | `BB578ADABCD8EBDDCB97C278E6589B935137F26F851B7218DD86C141744B70FE` | bytes |

## Files added at stage 1

| Path | Purpose |
| --- | --- |
| `docs/00-governance/MIGRATION_MAP.md` | This resolver and evidence record |
| `docs/handoff/archive/README.md` | States that the archived handoff pair is a historical snapshot, not the current state |

## Stage 2 — splits

Four documents became folders named after the document (DIR-039 §9). Each part holds a contiguous line range of the stage-1 file, in order; the status, approval and amendment header stays at the top of the first part, and each folder's README maps section numbers to parts. Section references such as "DATABASE §4.7" are unchanged. G2 below is the proof of each split.

### WORKFLOWS

`docs/03-workflows/WORKFLOWS.md` — 707 lines, SHA-256 `9B0BED95FAB3874963BFC0FEB73AEE8B9401B216A14ED89BFFF452D4D2A58002` at the previous commit — became the folder `docs/03-workflows/WORKFLOWS/`: its [README](../03-workflows/WORKFLOWS/README.md) and 9 parts, each a contiguous line range of the stage-1 file, in order.

| File | Baseline lines | Lines | SHA-256 |
| --- | --- | --- | --- |
| `docs/03-workflows/WORKFLOWS/s01-04-foundations.md` | 1–123 | 123 | `D96F67DE2F27E4D6F30DB50931A7E4F3001AA303AC713C4B5B16BB230B9679A8` |
| `docs/03-workflows/WORKFLOWS/s05-01-02-access-project.md` | 124–213 | 90 | `86BF14B3E4469BC6E3F959B97FB68B8D37B0D3026D160DA2D0A29BC6B3B76958` |
| `docs/03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md` | 214–297 | 84 | `5CBCDA2D4F7697DC78723A79C354C5168560864AB750F3DD71821F0755C000F7` |
| `docs/03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md` | 298–375 | 78 | `BD29E1FF88BDEE08930BE09DA017B5A77A8CA20F3EA234F44B5D8A47A59F2BA7` |
| `docs/03-workflows/WORKFLOWS/s05-06-finance.md` | 376–446 | 71 | `C7F13DE6296323AA114D3EBD63B82E336B505DA1D7DCB4774DA46EDC297FD352` |
| `docs/03-workflows/WORKFLOWS/s05-07-08-completion-migration.md` | 447–488 | 42 | `EE358C701A5D50ED8F9227804BDE1807002E1217355B081A5F346A4DA9261CF4` |
| `docs/03-workflows/WORKFLOWS/s06-07-subflows-partial.md` | 489–524 | 36 | `E83EDB6D11D7994C9AB990C60F98DA9292934E72A7520B3AE9772A460BEF5653` |
| `docs/03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md` | 525–613 | 89 | `AD26137E6B2A2875CA17796B76470FDB35D2B0757784350681A3FB4F628A82BE` |
| `docs/03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md` | 614–707 | 94 | `5F21B64BE7FA476589C9C6D65409F37D4DA126697D475C1F5A181B9470BFE45D` |
| `docs/03-workflows/WORKFLOWS/README.md` | — | 17 | `6BD100F81533FBCC339614C5531D4BB24FF7431A15D983582298707F884FF093` |

Link targets rewritten: 14 inside the parts — in-document anchors whose heading moved to another part, and paths recomputed for the deeper folder — and 24 in 17 other files: links without an anchor now point to the README, anchored links to the part holding the heading, with the same anchor. The SOURCE_OF_TRUTH registry token `docs/03-workflows/WORKFLOWS.md` became `docs/03-workflows/WORKFLOWS/README.md`. Each part except the last ends with the blank line that separated it from the next section in the single file, so `git diff --check` reports a blank line at the end of those parts; the ranges are fixed by DIR-039 and nothing is removed.

### DATABASE

`docs/04-architecture/DATABASE.md` — 1100 lines, SHA-256 `D41180F9410F8E4FE8F87C106E6A340496D8AA1DA30861142E4F3B8BAD06C557` at the previous commit — became the folder `docs/04-architecture/DATABASE/`: its [README](../04-architecture/DATABASE/README.md) and 24 parts, each a contiguous line range of the stage-1 file, in order.

| File | Baseline lines | Lines | SHA-256 |
| --- | --- | --- | --- |
| `docs/04-architecture/DATABASE/s01-03-foundations.md` | 1–127 | 127 | `39DD82CE9430294D2EB5B8BB4BF60C9585FD9DBF79BB97FE96F3E710657E2E6F` |
| `docs/04-architecture/DATABASE/s04-00-module-map.md` | 128–172 | 45 | `A491F17C5A75DEFE5EF6988837294FC07EE026BC92768EC74E7931AD6A8D5EFE` |
| `docs/04-architecture/DATABASE/s04-01-iam.md` | 173–184 | 12 | `848378C5413B605B1C24967207A0219A2228E017E351874C24AFFEEBA30DB534` |
| `docs/04-architecture/DATABASE/s04-02-org.md` | 185–198 | 14 | `6D709F87AD59929625CCB567BFE18DB938F8027EB7A09554EE7CBAFEA5193840` |
| `docs/04-architecture/DATABASE/s04-03-pty.md` | 199–208 | 10 | `8C4EF69689C9C2C9972517B3A1133E67BCE8A5628567CE8E3BBAFFE980DB7AE4` |
| `docs/04-architecture/DATABASE/s04-04-cat.md` | 209–219 | 11 | `9B28E8B69833BC32128E1DF397D64D05E8663CEFE9AD41CA67648374C2F3715B` |
| `docs/04-architecture/DATABASE/s04-05-prj.md` | 220–235 | 16 | `8704CD8C114A24F9D5581C2639543731261AE87071D8214CC0C3A30624C79F00` |
| `docs/04-architecture/DATABASE/s04-06-pur.md` | 236–248 | 13 | `46987728A1C5000513ED6BD46F5AEB3ECCBD253D343080D752EC8A2371638B33` |
| `docs/04-architecture/DATABASE/s04-07-inv.md` | 249–281 | 33 | `E1F578400A00BEEDFE379E9537E2A850893083DF1DA7F2CDE91B11E4D5EC68B7` |
| `docs/04-architecture/DATABASE/s04-08-cst.md` | 282–294 | 13 | `BDD9FECA137AC16BF2E5F83FF122BD9E46DA95D1048422463D834863F4CBC78A` |
| `docs/04-architecture/DATABASE/s04-09-ful.md` | 295–306 | 12 | `39004FCE419E1FA250E90B1BEFE88973772C733D563F22F3838752D5524F7B9C` |
| `docs/04-architecture/DATABASE/s04-10-doc.md` | 307–321 | 15 | `726BBBC80B479B69562B555C2A736B1A1271532BA1DA19DDBCAB5CF2976FDE90` |
| `docs/04-architecture/DATABASE/s04-11-adm.md` | 322–329 | 8 | `1FEBD32CCEBB539AE4B0D045FA6221631340E516C2D54879D1E78E5294C01F49` |
| `docs/04-architecture/DATABASE/s04-12-fin.md` | 330–353 | 24 | `491ECE61F3A1B35D6E5BB17481D1098C1B786F126DE677950A8B33D143173027` |
| `docs/04-architecture/DATABASE/s04-13-ops.md` | 354–365 | 12 | `36FA20812C8E6FF5EA749727A86AF0CE5FB24D77B7EF6B03AD6BFEDECD8E74E6` |
| `docs/04-architecture/DATABASE/s05-inventory.md` | 366–462 | 97 | `0A20C5DCC1BA59ABB0D77B75ABD2F94B0184E7A6B7AF243D0045657B8DCE65FE` |
| `docs/04-architecture/DATABASE/s06-lot-cost-hpp.md` | 463–529 | 67 | `F65FB8F1E2D1CB6722ECE854EC81258CE224875F9B5900A5224E733C2ECBA359` |
| `docs/04-architecture/DATABASE/s07-10-representations.md` | 530–579 | 50 | `46EC2834A4B74DD84E6855C3CA237477C72C9D1336A1EF736872BF783149A3EC` |
| `docs/04-architecture/DATABASE/s11-12-finance-tax.md` | 580–639 | 60 | `AF6152DD90337706B5122ED234E472E24177572EA616113B913A7FC2B6B6FAC5` |
| `docs/04-architecture/DATABASE/s13-17-history-scope-types.md` | 640–736 | 97 | `17C9690616C44A8BDD829757EB0E6F60D3C80F027558893E2CC2C761AB2FCAB2` |
| `docs/04-architecture/DATABASE/s18-19-constraints-integrity.md` | 737–839 | 103 | `47DD83D3A3B4A3BA7B525989E0FE673610F29F2AC5E6DB3E154BC0A66CFCD50A` |
| `docs/04-architecture/DATABASE/s20-24-index-storage-migration.md` | 840–893 | 54 | `9D06241C3F64280AC0ADF33ABA1FF60AFEFD5DAE45EC8C6E7FD83BF9F0F9B5CA` |
| `docs/04-architecture/DATABASE/s25-28-transactions-handoffs.md` | 894–967 | 74 | `21407A9DDF2B6F4A6A0C74D15D6A51556B9C1EFAF54BECD20AF5402918851149` |
| `docs/04-architecture/DATABASE/s29-31-diagrams-traceability.md` | 968–1100 | 133 | `399720D123B1DC6A5690434B3BE89C069255D9167584C1C251447F8AC7EF0C45` |
| `docs/04-architecture/DATABASE/README.md` | — | 32 | `C080364F5C597C8923BE7D13D94190496F101A09C1A4743978177109AB925A06` |

Link targets rewritten: 32 inside the parts — in-document anchors whose heading moved to another part, and paths recomputed for the deeper folder — and 27 in 16 other files: links without an anchor now point to the README, anchored links to the part holding the heading, with the same anchor. The SOURCE_OF_TRUTH registry token `docs/04-architecture/DATABASE.md` became `docs/04-architecture/DATABASE/README.md`. Each part except the last ends with the blank line that separated it from the next section in the single file, so `git diff --check` reports a blank line at the end of those parts; the ranges are fixed by DIR-039 and nothing is removed.

### CONCURRENCY_IDEMPOTENCY

`docs/06-api-performance/CONCURRENCY_IDEMPOTENCY.md` — 708 lines, SHA-256 `64C899B5A8284C28C28FAE8EFD2876F288760183E97E36AF62A0A74F91332580` at the previous commit — became the folder `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/`: its [README](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) and 8 parts, each a contiguous line range of the stage-1 file, in order.

| File | Baseline lines | Lines | SHA-256 |
| --- | --- | --- | --- |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s00-03-foundations.md` | 1–102 | 102 | `D5C7E4CA49995D84EF41B7000F3FEC3BA16C5DC77C8D53625D21303A962E0842` |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md` | 103–243 | 141 | `D266A10FB1322242DC82095B3AEB889BA8EC21E1FB33210323C6D1CDF87148FE` |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md` | 244–360 | 117 | `606F3149F73C0E3053BDB07C5C5BE9B78475702FDD7D3A7DB4014A2668C32BCD` |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md` | 361–431 | 71 | `E0011915F8A4B8FEBE50D629B6AB356D60D4DCF4A379925B94D78127FAC5C84E` |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md` | 432–527 | 96 | `2A1F67167AD9F6FBFD0D0381453B141C69FC8E052E60FC8F228A52F75434A3D9` |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md` | 528–608 | 81 | `A27A9E793DEDCAF97490294A2647767BB2FECA26DCEAF88B4DA729201BC379A3` |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md` | 609–653 | 45 | `6A429C8C7760662D2E82A178304EF3C1A6D5EF094077637E29B38FB68105F927` |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md` | 654–708 | 55 | `6180D329FCA6289563EBB2A9571B28C11A86C18FD50CF5515923B767161C010B` |
| `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md` | — | 16 | `71573C7EF360FC8DA351F2866A729A9E64C95C9034D9B27239BCE2D6AEC53FD2` |

Link targets rewritten: 51 inside the parts — in-document anchors whose heading moved to another part, and paths recomputed for the deeper folder — and 37 in 23 other files: links without an anchor now point to the README, anchored links to the part holding the heading, with the same anchor. The SOURCE_OF_TRUTH registry token `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY.md` became `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md`. Each part except the last ends with the blank line that separated it from the next section in the single file, so `git diff --check` reports a blank line at the end of those parts; the ranges are fixed by DIR-039 and nothing is removed.

### ADMIN_FLOW

`docs/07-ux-design/ADMIN_FLOW.md` — 685 lines, SHA-256 `2EA3E3A9C6BF306BCDB4F30C3989AC19114F2A12A7664B1D2B7E9320317104AB` at the previous commit — became the folder `docs/07-ux-design/ADMIN_FLOW/`: its [README](../07-ux-design/ADMIN_FLOW/README.md) and 8 parts, each a contiguous line range of the stage-1 file, in order.

| File | Baseline lines | Lines | SHA-256 |
| --- | --- | --- | --- |
| `docs/07-ux-design/ADMIN_FLOW/s01-04-foundations.md` | 1–101 | 101 | `502A788C7A3FFF99ADFDD441A589118E7B8346D3B4E71B5EDC2D9DE1F515E6B7` |
| `docs/07-ux-design/ADMIN_FLOW/s05-command-model.md` | 102–221 | 120 | `BB63D748AF4B17AC9729DFF1CC87B9F47C50BF468C3FE4932A6DFF20149E3BBD` |
| `docs/07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md` | 222–319 | 98 | `F3AE2BBF82FF6FF6A60C411C929B7A3E9AD6BA7EE7CBF190AF31FE5C446BCEA1` |
| `docs/07-ux-design/ADMIN_FLOW/s10-a-journeys.md` | 320–371 | 52 | `42E93CEE055BD86C7BBC289539E52C6EC55ED6C902BB93C609EAC65C3C45FD42` |
| `docs/07-ux-design/ADMIN_FLOW/s10-b-journeys.md` | 372–411 | 40 | `67F6B3E692E188DE6F26EC511CBF1BA27BC6949ED877CF17719997A1BDD911AE` |
| `docs/07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md` | 412–528 | 117 | `9A3EF7140E4FF8E6D85F4FD5D2C47C06993333F5A613019863AE509ECE0869DD` |
| `docs/07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md` | 529–659 | 131 | `371F8A07166BF55C6AE887DB09E7D2FBD23AD86FB7228CB24391C9690E27F012` |
| `docs/07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md` | 660–685 | 26 | `1310D3DFE91EDC92A77C17FC14F1FC96FF703A8D01FC21E6D91E24D0BFEEC37F` |
| `docs/07-ux-design/ADMIN_FLOW/README.md` | — | 16 | `EAECAFF2D183156DA02B4B5C8A5F9BB13768DAE557083C0202B94D55C6B5DB6D` |

Link targets rewritten: 56 inside the parts — in-document anchors whose heading moved to another part, and paths recomputed for the deeper folder — and 62 in 12 other files: links without an anchor now point to the README, anchored links to the part holding the heading, with the same anchor. The SOURCE_OF_TRUTH registry token `ADMIN_FLOW.md`, written relative to its row's first path, became `ADMIN_FLOW/README.md`. Each part except the last ends with the blank line that separated it from the next section in the single file, so `git diff --check` reports a blank line at the end of those parts; the ranges are fixed by DIR-039 and nothing is removed.

## Stage 3 — governance and operating-structure alignment

Stage 3 moves and splits nothing. It amends thirteen existing files and creates twelve, as DIR-039 §10 lists, and adds records to the decision log, the changelog and this map. [TECH-023](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration) records each amended file with its SHA-256 at the tag, before stage 3 and after, and the directive it implements, and the hash of each new document. Every stage-3 amendment was pending the Owner's approval when stage 3 was committed; the Owner approved them on 2026-10-03 under APPR-009 ([Approval](#approval)).

## Gate results

### G0 — stage 0 (commit 0): PASS

- Baseline (OBS-013): local HEAD, local origin/main and live origin/main equal `c511d7b0d4683e07717c962927c9f113854f227b`; clean tree and index; no operation in progress; no tag and no `migration/aicwdf` branch locally or on the remote; the 29 source hashes of SOURCE_OF_TRUTH, the seven APPR-008 blob hashes and the gap totals (34 — 3 CLOSED, 30 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK) verified.
- Tag and branch: the annotated tag `pre-aicwdf-migration` resolves to `c511d7b0d4683e07717c962927c9f113854f227b`; the reflog of `migration/aicwdf` records its creation from that commit.
- Additions only: seven files added, all under `docs/00-governance/sources/`; four files modified by additions only — `.gitattributes` (+8 lines after the P7 entry: one comment and seven entries), SOURCE_OF_TRUTH, DECISION_LOG and CHANGELOG — whose only replaced line is each `Updated:` date; the new subsection, index rows, detail section and changelog entry sit where each file's convention places them.
- Source hashes: 36 source files and 36 distinct hashes in SOURCE_OF_TRUTH; each new record's lines, bytes and hash re-verified from disk, working copy equal to staged blob.
- No secrets: a credential-pattern scan of the seven new files found one match, the AICWDF §4A.4 template line "Client Secret: secret/environment-managed", which holds no value; no credential or client data.
- Links: 40 Markdown files outside `sources/`, 1,115 links (2 external), none broken.

### G1 — stage 1: PASS

- Mapping: 71 tag paths, 21 moved; every mapped path exists and none is shared.
- Neutralization: 38 files byte-identical (36 source records, `.gitattributes`, `.gitignore`); 40 Markdown files identical after neutralization, 32 of them with rewritten link targets (446 targets of 1,115 links) and SOURCE_OF_TRUTH also with 19 remapped registry tokens; line counts unchanged.
- Sources: 36 byte-identical to commit 0; the 29 original records byte-identical to the tag.
- Links: 1,118 relative links at stage 1 outside `sources/`, 0 broken; every link of the 40 commit-0 Markdown files keeps its count, label, logical target and anchor.
- Added files: exactly this file and `docs/handoff/archive/README.md`.

### G2 — WORKFLOWS: PASS

- Concatenation: the 9 parts in table order, with link targets neutralized on both sides, are byte-identical to the stage-1 file.
- Lines: 123 + 90 + 84 + 78 + 71 + 42 + 36 + 89 + 94 = 707, the stage-1 count; every part ends with a newline; code fences are balanced inside every part (3 pairs).
- Headings: the concatenation's 62 headings equal the stage-1 sequence; each of the 22 numbered sections lies in exactly one part and appears exactly once in the README table, in its part's row.
- Anchors: all 26 links into or inside WORKFLOWS resolve to the same heading as before — 7 anchored links to the part holding the heading, 19 links without an anchor to the README; every other link keeps its target; the other changed files differ only in link targets (`AGENTS.md`, `README.md`, `docs/00-governance/DECISION_LOG.md`, `docs/00-governance/GAP_REGISTER.md`, `docs/00-governance/SOURCE_OF_TRUTH.md`, `docs/02-domain/BUSINESS_RULES.md`, `docs/03-workflows/evidence/P3_QUALITY_GATE.md`, `docs/04-architecture/ARCHITECTURE.md`, `docs/04-architecture/DATABASE.md`, `docs/05-security/PERMISSIONS_MATRIX.md`, `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY.md`, `docs/06-api-performance/evidence/P6_QUALITY_GATE.md`, `docs/07-ux-design/ADMIN_FLOW.md`, `docs/07-ux-design/DESIGN_SYSTEM.md`, `docs/07-ux-design/INFORMATION_ARCHITECTURE.md`, `docs/CONTEXT_INDEX.md`, `docs/handoff/archive/CURRENT_STATE_2026-10-02.md`), SOURCE_OF_TRUTH also in its registry token.
- Repository-wide link check: 1,129 relative links outside `sources/`, none broken.

### G2 — DATABASE: PASS

- Concatenation: the 24 parts in table order, with link targets neutralized on both sides, are byte-identical to the stage-1 file.
- Lines: 127 + 45 + 12 + 14 + 10 + 11 + 16 + 13 + 33 + 13 + 12 + 15 + 8 + 24 + 12 + 97 + 67 + 50 + 60 + 97 + 103 + 54 + 74 + 133 = 1100, the stage-1 count; every part ends with a newline; code fences are balanced inside every part (8 pairs).
- Headings: the concatenation's 64 headings equal the stage-1 sequence; each of the 63 numbered sections lies in exactly one part and appears exactly once in the README table, in its part's row.
- Anchors: all 27 links into or inside DATABASE resolve to the same heading as before — 11 anchored links to the part holding the heading, 16 links without an anchor to the README; every other link keeps its target; the other changed files differ only in link targets (`AGENTS.md`, `README.md`, `docs/00-governance/DECISION_LOG.md`, `docs/00-governance/GAP_REGISTER.md`, `docs/00-governance/SOURCE_OF_TRUTH.md`, `docs/04-architecture/ARCHITECTURE.md`, `docs/04-architecture/evidence/P4_QUALITY_GATE.md`, `docs/05-security/PERMISSIONS_MATRIX.md`, `docs/05-security/SECURITY.md`, `docs/05-security/evidence/P5_QUALITY_GATE.md`, `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY.md`, `docs/06-api-performance/PERFORMANCE.md`, `docs/06-api-performance/evidence/P6_QUALITY_GATE.md`, `docs/07-ux-design/ADMIN_FLOW.md`, `docs/CONTEXT_INDEX.md`, `docs/handoff/archive/CURRENT_STATE_2026-10-02.md`), SOURCE_OF_TRUTH also in its registry token.
- Repository-wide link check: 1,155 relative links outside `sources/`, none broken.

### G2 — CONCURRENCY_IDEMPOTENCY: PASS

- Concatenation: the 8 parts in table order, with link targets neutralized on both sides, are byte-identical to the stage-1 file.
- Lines: 102 + 141 + 117 + 71 + 96 + 81 + 45 + 55 = 708, the stage-1 count; every part ends with a newline; code fences are balanced inside every part (2 pairs).
- Headings: the concatenation's 29 headings equal the stage-1 sequence; each of the 27 numbered sections lies in exactly one part and appears exactly once in the README table, in its part's row.
- Anchors: all 66 links into or inside CONCURRENCY_IDEMPOTENCY resolve to the same heading as before — 47 anchored links to the part holding the heading, 19 links without an anchor to the README; every other link keeps its target; the other changed files differ only in link targets (`AGENTS.md`, `README.md`, `docs/00-governance/DECISION_LOG.md`, `docs/00-governance/ENGINEERING_PRINCIPLES.md`, `docs/00-governance/GAP_REGISTER.md`, `docs/00-governance/SOURCE_OF_TRUTH.md`, `docs/03-workflows/WORKFLOWS/s01-04-foundations.md`, `docs/04-architecture/ARCHITECTURE.md`, `docs/04-architecture/DATABASE/s01-03-foundations.md`, `docs/04-architecture/DATABASE/s04-00-module-map.md`, `docs/04-architecture/DATABASE/s04-13-ops.md`, `docs/04-architecture/DATABASE/s18-19-constraints-integrity.md`, `docs/04-architecture/DATABASE/s20-24-index-storage-migration.md`, `docs/04-architecture/DATABASE/s25-28-transactions-handoffs.md`, `docs/05-security/PERMISSIONS_MATRIX.md`, `docs/06-api-performance/API_AND_INTEGRATIONS.md`, `docs/06-api-performance/PERFORMANCE.md`, `docs/06-api-performance/evidence/P6_QUALITY_GATE.md`, `docs/07-ux-design/ADMIN_FLOW.md`, `docs/07-ux-design/DESIGN_SYSTEM.md`, `docs/07-ux-design/INFORMATION_ARCHITECTURE.md`, `docs/CONTEXT_INDEX.md`, `docs/handoff/archive/CURRENT_STATE_2026-10-02.md`), SOURCE_OF_TRUTH also in its registry token.
- Repository-wide link check: 1,165 relative links outside `sources/`, none broken.

### G2 — ADMIN_FLOW: PASS

- Concatenation: the 8 parts in table order, with link targets neutralized on both sides, are byte-identical to the stage-1 file.
- Lines: 101 + 120 + 98 + 52 + 40 + 117 + 131 + 26 = 685, the stage-1 count; every part ends with a newline; code fences are balanced inside every part (0 pairs).
- Headings: the concatenation's 57 headings equal the stage-1 sequence; each of the 28 numbered sections lies in exactly one part and appears exactly once in the README table, in its part's row.
- Anchors: all 73 links into or inside ADMIN_FLOW resolve to the same heading as before — 59 anchored links to the part holding the heading, 14 links without an anchor to the README; every other link keeps its target; the other changed files differ only in link targets (`AGENTS.md`, `README.md`, `docs/00-governance/DECISION_LOG.md`, `docs/00-governance/ENGINEERING_PRINCIPLES.md`, `docs/00-governance/GAP_REGISTER.md`, `docs/00-governance/SOURCE_OF_TRUTH.md`, `docs/07-ux-design/DESIGN_SYSTEM.md`, `docs/07-ux-design/INFORMATION_ARCHITECTURE.md`, `docs/07-ux-design/evidence/P7_QUALITY_GATE.md`, `docs/CONTEXT_INDEX.md`, `docs/handoff/archive/CURRENT_STATE_2026-10-02.md`, `docs/handoff/archive/NEXT_ACTION_2026-10-02.md`), SOURCE_OF_TRUTH also in its registry token.
- Repository-wide link check: 1,175 relative links outside `sources/`, none broken.

### G3 — stage 3: PASS

- **Scope.** The stage-3 diff changes thirteen files and adds twelve, all named in DIR-039 §10, plus the three records of §10.3 (DECISION_LOG, CHANGELOG, this map). No split part changes; no P0–P6 document outside the list changes; BUSINESS_RULES is inspected and left unchanged.
- **Directives and hashes.** Each amended approved document states in its header the directive it applies and that the wording is pending the Owner's approval; TECH-023 records the before and after hashes. DESIGN_SYSTEM, INFORMATION_ARCHITECTURE and SECURITY change only their Updated date and gain one status line.
- **No semantic change beyond §10.1.** The V1_SCOPE, ACCEPTANCE_CRITERIA, GAP_REGISTER, PROJECT_CHARTER and SOURCE_OF_TRUTH changes are those §10.1 lists; TECH-023 names each amended sentence and what was left unchanged.
- **One owner per fact.** Phase status appears only in PHASE_STATUS, which CURRENT_HANDOFF, EXECUTION_CONTEXT, the charter, README and CONTEXT_INDEX point to; DECISION_INDEX holds subjects and statuses, no rule text; every list line of EXECUTION_CONTEXT cites its owner or an identifier; no ten-word sequence of the framework source appears in the new or amended documents, which cite framework sections instead; CURRENT_HANDOFF holds the current state only.
- **Content truth unchanged.** Derived the same way at the tag and now: 18 MUST capabilities, 14 document types, 124 tables in 13 modules (module-map total 124), 80 capabilities; CAP-01–CAP-18 all present, so no V1_REQUIRED capability is removed; no Owner authority is weakened.
- **Hygiene.** No placeholder text and no secret or credential pattern in any new or changed file.
- **Links and identifiers.** 1,491 relative links outside `sources/`, none broken; the 186 distinct identifiers that the new and rewritten documents cite are all defined in the repository.
- **Self-review of the whole branch** (tag → stage 3) against broken references, duplicate sources of truth, lost historical facts, lost approval provenance, ambiguous current state and unnecessary framework deviation: links, anchors and identifiers resolve and no current-state document names a pre-migration path; phase history left the entry documents for the decision log and Git, while the approval chain, checkpoints and source records stay intact and every APPR record is unedited; the remaining stale sentences outside the amendment list are named in CURRENT_HANDOFF and TECH-023; every deviation from the framework is a project exception with its authority in [AICWDF_ADOPTION](AICWDF_ADOPTION.md#project-exceptions-and-stronger-invariants).

#### Zero-context test

A fresh read-only agent, given none of the migration conversation and only the working tree of `migration/aicwdf` (stage 3 complete in the tree but not yet committed or recorded), started from the repository's own entry point and answered from the repository alone.

| Question | Answer from the repository | Result |
| --- | --- | --- |
| What is authoritative? | The repository; the seven-level hierarchy of SOURCE_OF_TRUTH; the framework file is provenance; one owner per concern through the ownership registry and CONTEXT_INDEX; DECISION_INDEX for binding decisions; SECURITY unamended while D5 and D6 prevail; DESIGN_SYSTEM and INFORMATION_ARCHITECTURE under replacement | Correct |
| What is the current project state? | Planning documentation only; P0–P6 DONE with their open amendments, P7 IN_PROGRESS, P8 BLOCKED, P9–P11 TODO; the migration VERIFYING with `main` at `c511d7b`; 37 gaps (PHASE_STATUS, CURRENT_HANDOFF, GAP_REGISTER) | Correct |
| What Task is active? | None before P11; the current authorization is DIR-039 | Correct |
| What can I safely change? | Level-1 improvements inside authorized work, REVIEW documents, and within DIR-039 only its §10 files, committed with the Owner's identity and CRLF-normalized staging | Correct |
| What must I not change? | The "Do not do" list of CURRENT_HANDOFF and the exclusions of DIR-039; binding decisions; source records; the project invariants; the production database; the public-repository and cost rules | Correct |
| What should I read next? | The reading order of AGENTS.md, the DIR-034–039 entry, this map and GAP-035–GAP-037, then only the exact sections through CONTEXT_INDEX | Correct |
| AU-07 | `docs/05-security/SECURITY.md` §2 | Correct |
| AX-04 | `docs/03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md` §9 | Correct |
| DATABASE §4.7 | `docs/04-architecture/DATABASE/s04-07-inv.md`, through the folder README | Correct |
| CS-08 | `docs/05-security/PERMISSIONS_MATRIX.md` §6 | Correct |
| D-UX-01 | `docs/07-ux-design/ADMIN_FLOW/s01-04-foundations.md` §4 | Correct |
| DIR-009 | `docs/00-governance/DECISION_LOG.md`, index row and the DIR-009 and RISK-001 entry | Correct |
| CAP-14 | `docs/01-product/V1_SCOPE.md` MUST SHIP | Correct |
| BR-CR-01 | `docs/02-domain/BUSINESS_RULES.md` §2 | Correct |
| CI-01 | `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md` §10 | Correct |
| UXS-43 | `docs/07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md` §15 | Correct |
| Current phase | P7, the UX re-baseline | Correct |
| Why is P8 BLOCKED? | It waits for the P5 authentication amendment (GAP-036) and the P7 re-baseline (GAP-035, GAP-037), as the Owner decided | Correct |
| What is not authorized? | The security amendment, the UX re-baseline, P8–P11 and any Task, application code, migrations, packages, tests, infrastructure, tool installation, source-record edits, any change to `main`, history rewrites, AI attribution | Correct |
| Next safe action | The Owner reviews and approves the migration; `main` is then fast-forwarded and published; then the security amendment and the UX re-baseline, each under its own authorization | Correct |

**Result: PASS** — every answer correct on the first run, so no repeat was required. The agent's observations and their dispositions:

| Observation | Disposition |
| --- | --- |
| The handoff named the stage-3 commit, the G3 result, the zero-context record and the TECH-023 hashes, which did not yet exist | Expected at the time of the test; supplied by TECH-023, this section and the stage-3 commit |
| VERIFYING was used before G3 was recorded | Resolved by this record |
| IN_PROGRESS read as "authorized and under way" although the re-baseline awaits authorization | Fixed: PHASE_STATUS defines IN_PROGRESS as opened by the Owner, with each piece of work still needing authorization, and the P7 row says so |
| GAP-036 was listed as non-blocking although it blocks P8 | Fixed: CURRENT_HANDOFF lists it under the blockers of P8 |
| The handoff did not state publication | Fixed: a publication row in CURRENT_HANDOFF; a new session verifies the pushes |
| Stale sentences outside the amendment list (PRODUCT_OVERVIEW, ENGINEERING_PRINCIPLES, BUSINESS_RULES FS-14, REFERENCE_COVERAGE, the decision log's pointer to the archived NEXT_ACTION) | Not edited, being outside DIR-039 §10.1; named in CURRENT_HANDOFF and TECH-023 for a separately authorized amendment; the GAP-014 heading keeps its anchor and its continuation note supersedes the deadline component |
| One link in EXECUTION_CONTEXT served three identifier families | Fixed: separate links to PERMISSIONS_MATRIX §2, §6 and §9 |
| Whether UXS-43 still binds while its screens and patterns are replaced | Clarified in PHASE_STATUS: the re-baseline revisits ADMIN_FLOW's references to replaced screens and patterns |
| Owner decisions 8 and 9 are nowhere defined | Recorded in TECH-023: none under those numbers is recorded or pending |
| D, D- and C series collide | Fixed: CONTEXT_INDEX's identifier map tells D1–D7, D10, C1 and C2 apart from D-1–D-5, D-01–D-06 and C1–C3 |
| A missing "and" before APPR-007 in SOURCE_OF_TRUTH | A pre-existing editorial slip in approved text outside the amendment list; reported, not changed |
| Commits must carry no AI attribution | Followed (DIR-022) |

## Supporting evidence — rename detection

Output of `git diff -M --summary <commit 0> <stage 1>`. Git's rename detection is heuristic and is not a gate.

```text
 create mode 100644 docs/00-governance/MIGRATION_MAP.md
 rename docs/00-governance/{ => evidence}/P0_QUALITY_GATE.md (91%)
 rename docs/01-product/{ => evidence}/P1_QUALITY_GATE.md (99%)
 rename docs/02-domain/{ => evidence}/P2_QUALITY_GATE.md (93%)
 rename docs/{02-domain => 03-workflows}/WORKFLOWS.md (99%)
 rename docs/{02-domain => 03-workflows/evidence}/P3_QUALITY_GATE.md (93%)
 rename docs/{03-architecture => 04-architecture}/ARCHITECTURE.md (92%)
 rename docs/{03-architecture => 04-architecture}/DATABASE.md (97%)
 rename docs/{03-architecture => 04-architecture/evidence}/P4_QUALITY_GATE.md (98%)
 rename docs/{02-domain => 05-security}/PERMISSIONS_MATRIX.md (96%)
 rename docs/{03-architecture => 05-security}/SECURITY.md (98%)
 rename docs/{03-architecture => 05-security/evidence}/P5_QUALITY_GATE.md (95%)
 rename docs/{03-architecture => 06-api-performance}/API_AND_INTEGRATIONS.md (95%)
 rename docs/{03-architecture => 06-api-performance}/CONCURRENCY_IDEMPOTENCY.md (97%)
 rename docs/{03-architecture => 06-api-performance}/PERFORMANCE.md (98%)
 rename docs/{03-architecture => 06-api-performance/evidence}/P6_QUALITY_GATE.md (95%)
 rename docs/{04-ux => 07-ux-design}/ADMIN_FLOW.md (99%)
 rename docs/{04-ux => 07-ux-design}/DESIGN_SYSTEM.md (98%)
 rename docs/{04-ux => 07-ux-design}/INFORMATION_ARCHITECTURE.md (98%)
 rename docs/{04-ux => 07-ux-design/evidence}/P7_QUALITY_GATE.md (92%)
 rename docs/{07-handoff/CURRENT_STATE.md => handoff/archive/CURRENT_STATE_2026-10-02.md} (73%)
 rename docs/{07-handoff/NEXT_ACTION.md => handoff/archive/NEXT_ACTION_2026-10-02.md} (63%)
 create mode 100644 docs/handoff/archive/README.md
```

## Approval

**State: OWNER APPROVED, PUBLICATION PENDING.** On 2026-10-03 the Owner approved the migration under [APPR-009](DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved) (DIR-040 F1 and F2): stages 0–3 as reviewed at the stage-3 commit `0dbbb44f3ceb167a6243c8cad796a89c4d394239`, the stage-3 amendments and new documents, and three narrow corrections made under DIR-041 in the finalization commit `docs: finalize AICWDF structural migration`, a child of `0dbbb44`. APPR-001–APPR-008 carry over unchanged to the moved and split files on the proofs above. Publication is pending: `main` is still at the P7 checkpoint `c511d7b0d4683e07717c962927c9f113854f227b` until DIR-041 fast-forwards it to the finalization commit, and the publication is recorded only after the live remote confirms it.

### Gate A — finalization: PASS

- **Baseline (OBS-014).** Local and live `main` equal `c511d7b0d4683e07717c962927c9f113854f227b`; local and live `migration/aicwdf` equal `0dbbb44f3ceb167a6243c8cad796a89c4d394239`; the annotated tag `pre-aicwdf-migration` points to `c511d7b0d4683e07717c962927c9f113854f227b` locally and on the remote; `main` is an ancestor of the branch; clean tree and index; the 36 source hashes verified.
- **Scope.** The finalization diff changes 30 files, all allowed by DIR-041 §7: the two new source records, `.gitattributes`, SOURCE_OF_TRUTH, DECISION_LOG, DECISION_INDEX, CHANGELOG, this map, GAP_REGISTER, PRODUCT_OVERVIEW, ENGINEERING_PRINCIPLES, PHASE_STATUS and CURRENT_HANDOFF, and the documents whose pending note or status changes under §6.5 — AGENTS.md, CLAUDE.md, README.md, CONTEXT_INDEX, PROJECT_CHARTER, AGENT_OPERATING_MODEL, V1_SCOPE, ACCEPTANCE_CRITERIA, AICWDF_ADOPTION, TOOLCHAIN, COST_POLICY, PRODUCTION_DATA_SAFETY, EXECUTION_CONTEXT and the four reservation READMEs of `docs/08-testing/` to `docs/11-tasks/`.
- **Corrections.** Outside the lifecycle header, the PRODUCT_OVERVIEW diff is the target sentence alone, the ENGINEERING_PRINCIPLES diff the language clause alone, and the decision log's "Open decisions" diff the NEXT_ACTION sentence alone; putting each named text back reproduces the earlier body exactly.
- **Unchanged.** BUSINESS_RULES, REFERENCE_COVERAGE, every file of the four split folders, SECURITY, PERMISSIONS_MATRIX, DESIGN_SYSTEM and INFORMATION_ARCHITECTURE — 59 files — are byte-identical to `0dbbb44`, so no SECURITY, PERMISSIONS_MATRIX, DATABASE or CONCURRENCY rule changes.
- **Lifecycle.** No "pending Owner approval" amendment note remains in an active document; SECURITY's "Pending amendment" note and the "Replacement" notes of DESIGN_SYSTEM and INFORMATION_ARCHITECTURE are kept; the new normative documents are APPROVED and the living records REVIEW (TECH-024).
- **Current state.** PHASE_STATUS, CURRENT_HANDOFF and this section say OWNER APPROVED and PUBLICATION PENDING; no document says that the migration is published, DONE or on `main`.
- **Sources.** The 36 earlier records are byte-identical to `0dbbb44`; records 37 and 38 are registered in SOURCE_OF_TRUTH, their hashes re-verified from disk; 38 source files and 38 distinct hashes.
- **Content truth.** Derived the same way at the tag and now: 18 MUST capabilities, 14 document types, 124 tables in 13 modules (module-map total 124), 80 capabilities (ADM 37, ADM_PLUS 26, OWNER_ONLY 17).
- **One owner per fact.** Phase status is recorded only in PHASE_STATUS; CURRENT_HANDOFF holds the current state and the last action, not history; DECISION_INDEX holds subjects and statuses, no rule text.
- **Links and identifiers.** 1,548 relative links in 103 Markdown files outside `sources/`, none broken, anchors included; every DIR, OBS, TECH, APPR, PROP, RISK and GAP identifier the changed files cite is defined.
- **Hygiene.** No secret or credential pattern and no placeholder text in the added lines.

#### Zero-context check

A fresh read-only agent, given none of the finalization conversation and only the working tree of `migration/aicwdf` (the finalization in the tree but not yet committed), started from the repository's own entry point and answered from the repository alone.

| Question | Answer from the repository | Result |
| --- | --- | --- |
| (a) What is the state of the structural migration, and under which approval? | OWNER APPROVED, PUBLICATION PENDING, under APPR-009 (DIR-040 F1 and F2; DIR-041), with `main` still at the P7 checkpoint — PHASE_STATUS, CURRENT_HANDOFF, this section and the decision log | Correct |
| (b) What is the current phase, and why is P8 BLOCKED? | P7, IN_PROGRESS, its UX re-baseline awaiting its own authorization; P8 waits for the P5 authentication amendment (GAP-036) and for the P7 re-baseline, which owes GAP-035 and GAP-037 | Correct |
| (c) What is the safe next action, and what is not authorized? | Publication under DIR-041 — `main` fast-forwarded with `git merge --ff-only`, normal pushes, the live-remote verification, then the publication-confirmation commit; afterwards the P5 amendment and the P7 re-baseline, each under its own authorization. Not authorized: P8–P11 and Tasks, application code, the security amendment or any SECURITY, PERMISSIONS_MATRIX, DATABASE or CONCURRENCY rule change, the UX re-baseline, semantic changes beyond the three corrections, edits to BUSINESS_RULES, REFERENCE_COVERAGE, source records or split parts, an early publication claim, merge commits, force pushes or history rewrites, deleting the branch or the tag, AI attribution | Correct |
| (d) Does any document still send a reader to NEXT_ACTION or to a 15 October target as current truth? | None — every hit is a source record, the archive, a historical log, changelog or gate entry, a quoted before-text, a row marked superseded, a pointer away from the archived handoff or a statement that the date is no longer a constraint | Correct |

**Result: PASS** — every answer correct on the first run, so no repeat was required. The agent's observations and their dispositions:

| Observation | Disposition |
| --- | --- |
| The handoff, PHASE_STATUS and this section describe the finalization commit, which did not yet exist | Expected at the time of the check; supplied by the finalization commit |
| The zero-context result was cited before it was recorded, and TECH-024 gave a hash of this map that no longer matched the tree | Expected while this section was being written; resolved by this record, and the TECH-024 and APPR-009 hashes are computed from the staged blobs before the commit |
| ENGINEERING_PRINCIPLES' anti-slop sentence (D4) had left CURRENT_HANDOFF's list of stale sentences, although only the three corrected sentences were to leave it | Fixed: CURRENT_HANDOFF names it again; the sentence itself is unchanged and reported under TECH-024 |
| TECH-024 cited without a link in CURRENT_HANDOFF; the stage-3 commit given only by its message in the reference points | Fixed: link added; the stage-3 SHA recorded in the reference points, which also name the finalization commit |
| Superseded sentences of GAP-014, GAP-033 and GAP-037 are corrected only by later continuation notes in the same entry, and the GAP-014 heading keeps "Eighteen days" | The register's convention — continuation notes supersede within their entry and headings keep their anchors; not changed |
| Earlier planned paths in PERFORMANCE (`docs/05-quality/`) and REFERENCE_COVERAGE (`docs/06-delivery/`), and P1-era present-tense lines in REFERENCE_COVERAGE | Outside DIR-041 §7, or in a file it forbids editing; the planned-path table of [AICWDF_ADOPTION](AICWDF_ADOPTION.md#terminology-and-planned-paths) resolves the paths; reported, not changed |
| The closing lines of the P0–P7 quality gates read like live instructions and link the archived handoff | Historical evidence, resolved through this map and the archive README; not changed |
| Wording on the P7 re-baseline (AICWDF_ADOPTION §1, AGENTS.md rule 10) and date phrases in PRODUCT_OVERVIEW ("the remaining window"), CHANGE_CONTROL and ADR-001 | Accurate in context or historical; PRODUCT_OVERVIEW's sentence is kept as DIR-041 §6.4 (1) requires; not changed |
| DECISION_INDEX says "P8 not started" where PHASE_STATUS says BLOCKED; GAP-036 restates P8's status; SOURCE_OF_TRUTH's approval paragraph lacks "and" before APPR-007 and does not list APPR-009 | Compatible, PHASE_STATUS owning the status; the editorial slip in approved text and the approval paragraph lie outside the three corrections, and APPR-009 is cited in SOURCE_OF_TRUTH's amendment note and registry; not changed |
| Commits must carry no AI attribution | Followed (DIR-022) |
