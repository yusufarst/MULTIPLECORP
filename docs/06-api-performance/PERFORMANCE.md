# Query and runtime performance design

Status: APPROVED | Updated: 2026-10-03 | Owner: Planning

Approval: [APPR-007](../00-governance/DECISION_LOG.md#appr-007--p6-concurrency-idempotency-and-performance-approved), explicit conditional Owner approval on 2026-09-30 (DIR-032) of this document as committed in the P6 finalization checkpoint; the approved file hash, the verified conditions and the exclusions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: narrowly amended on 2026-10-01 as a Level-1 technical clarification under [TECH-022](../00-governance/DECISION_LOG.md#dir-033-obs-012-and-tech-022--p7-authorization-entry-baseline-and-ux-documentation): the QS-14 row of the signal register and the IX-14 row of the index register each gain one clause, marked `TECH-022`, naming the typed source and the index of the two Owner-review items that had no stored record (GAP-034) — the two security-event kinds that [SECURITY LG-02](../05-security/SECURITY.md#6-audit-and-security-logging) adds. No budget, rule or assumption changes; the pre-amendment SHA-256 is recorded in the decision log, and the amended revision is approved under [APPR-008](../00-governance/DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved).

Amendment — REVIEW, pending Owner approval: on 2026-10-03, under [DIR-043](../00-governance/DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment), the index register gains IX-18 for the IAM table `user_external_identities` that the authentication design adds to apply the Owner's decision D6 ([DIR-037](../00-governance/DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration)), the traceability names it, and the stale planned path of P8's performance targets in the boundary paragraph is corrected to `docs/08-testing/`; no budget, rule or assumption changes. [TECH-025](../00-governance/DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment) records the SHA-256 of the last approved revision. The amended wording is not approved until the Owner approves it; the Owner decision it applies is binding within its subject.

Authority: P6 — Concurrency, Idempotency & Performance, authorized by the Owner's fast-track directive [DIR-032](../00-governance/DECISION_LOG.md#dir-032-obs-011-and-tech-021--p6-authorization-entry-baseline-and-concurrency-documentation) (twenty-eighth source record). This document owns **query and runtime performance design**: N+1 prevention, query budgets per page and action class, pagination, search, dashboards and reports, exports, images, Inertia prop loading, growth thresholds, cache policy, the index register and its verification procedure, and the resource inputs handed to P9. Locking, idempotency and retry are owned by [CONCURRENCY_IDEMPOTENCY](CONCURRENCY_IDEMPOTENCY/README.md); the index and search design it verifies by [DATABASE §20–§21](../04-architecture/DATABASE/s20-24-index-storage-migration.md#20-index-and-query-design); phase evidence by [P6_QUALITY_GATE](evidence/P6_QUALITY_GATE.md).

**Anti-duplication contract:** business meaning stays in V1_SCOPE, DOMAIN_MODEL, BUSINESS_RULES and WORKFLOWS; the logical model and its indexes in [DATABASE](../04-architecture/DATABASE/README.md); application structure in [ARCHITECTURE](../04-architecture/ARCHITECTURE.md); who may read what in [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md); security controls and limiter values in [SECURITY](../05-security/SECURITY.md). This document cites their identifiers and never restates, relaxes or extends a rule; no budget here may be met by showing less than the approved scope.

**Boundary — nothing here is a measurement.** DATABASE §20 and §27, ARCHITECTURE §14 and §20 and SECURITY WS-12 and H6-07 speak of measured plans, targets and limit values from P6, but no application exists to measure. DIR-032 §7-B settles this for ARCHITECTURE §20 — design budgets and target values stated as assumptions, with the procedure that will verify them — and this document reads the other five sentences the same way: every number below is a **design budget, starting value or sizing sentence stated as an assumption** tied to DEP-09 and GAP-017, together with the procedure that will verify it. P8 owns `docs/08-testing/PERFORMANCE_TARGETS.md` — the measured acceptance targets and how they are measured — and turns these budgets into evidence or corrects them. P9 owns the configured values. Documentation only: no code, configuration, package, infrastructure file, test or load script.

## Source and version evidence

Read on 2026-09-30 from current official documentation; versions are evidence, not pins (DEP-07). Facts marked *source* or *old* are P8 verification items (HO-16, HO-20).

| Source | Version seen | Facts used | Standing |
| --- | --- | --- | --- |
| PostgreSQL manual — pg_trgm | 18.6 | GIN and GiST trigram operator classes serve similarity operators and LIKE, ILIKE and regular-expression searches without left anchoring; non-alphanumeric characters are ignored when trigrams are extracted; default thresholds 0.3 (similarity), 0.6 (word similarity), 0.5 (strict word similarity); a pattern with no extractable trigram scans the whole index; nearest-neighbour ordering is efficient with GiST, not GIN | documented; behaviour of terms under three characters is not described |
| PostgreSQL manual — Indexes | 18.6 | a partial index is used only when the planner can prove at plan time that the query condition implies its predicate, which a parameter cannot; index-only scans need the visibility map; INCLUDE columns; a multicolumn B-tree is ordered by its leading column first; B-tree skip scan (new in 18) | documented |
| PostgreSQL manual — Operator Classes; btree_gin; Generated Columns | 18.6 | a B-tree index serves a `LIKE` prefix only under the C locale or in a pattern operator class (`text_pattern_ops`); `btree_gin`, a trusted contrib module, lets one multicolumn GIN index combine a B-tree-indexable column with a GIN-indexable one instead of a bitmap AND of two indexes; a generated column is virtual unless it is declared STORED (new in 18) | documented; whether a CHECK or an index predicate may read a virtual generated column is not stated on the pages read (HO-30) |
| PostgreSQL manual — row comparisons; LIMIT and OFFSET | 18.6 | row-value comparison semantics, a NULL member making the comparison unknown; rows skipped by OFFSET are still computed | documented; use of a row comparison as a B-tree index condition is stated only in the 8.2 release notes (old) |
| PostgreSQL manual — EXPLAIN; auto_explain; pg_stat_statements | 18.6 | `EXPLAIN ANALYZE` executes the statement and, in 18, includes buffer counts; the two contrib modules log slow plans and statement statistics | documented |
| PostgreSQL manual — BRIN; partitioning | 18.6 | BRIN suits very large tables whose column follows physical order; partitioning pays off for tables larger than memory | documented |
| PostgreSQL manual — connections | 18.6 | `max_connections` is typically 100 by default and each connection is a server process | documented |
| Laravel — Eloquent relationships, pagination | 13.x (13.34.0) | lazy-loading prevention with a violation handler; eager loading of named columns; count, sum and existence aggregates; chunking by id; cursor pagination needs a unique, non-null ordering and offers no page numbers; automatic eager loading exists since 12.8 | documented; that the cursor paginator builds its predicate as nested OR conditions and that its cursor is unsigned, readable JSON of the key values are source facts |
| Laravel — HTTP Session | 13.x | the `database` driver reads and rewrites the session row on every request through the session middleware, which offers no way to skip the write | source |
| Laravel — Cache; Rate Limiting; Deployment | 13.x | the limiter uses the default cache store unless another is configured; clearing the application cache removes every key of that store; configuration, route, view and event caches are files written at deployment | documented |
| Inertia — partial reloads, deferred and optional props, polling, prefetching, shared data | v3 (3.7.1) | a closure prop is evaluated only when requested, a plain value always; a deferred prop costs one extra request per group after the first render; an optional prop is loaded only on request; a poll or reload without `only` is a full visit; shared data travels with every response | documented |

## 1. Assumptions and workload

| ID | Assumption | Grounds; confirmed by |
| --- | --- | --- |
| AS-01 | One VPS with 4 vCPU, 16 GB RAM and 200 GB NVMe, shared by PostgreSQL, the application, the queue and cache store, files and backups | DEP-01; P9 measures free capacity |
| AS-02 | About five office PCs plus phones; about ten named accounts; five people working at the same moment, ten at a peak | DEP-09; DATABASE §27; the operator's figures |
| AS-03 | A peak of 10 page and command requests per second in total, 2 of them commands; ordinary load far below. File requests — thumbnails, previews, downloads — come on top of that in bursts of one page of thumbnails per list view and are budgeted separately (QB-16) | DEP-09; P8 load evidence (HO-16) |
| AS-04 | Volume at **1x**, a design guess for the first live year: 5,000 products; 500 client organizations with 2,000 units; 300 suppliers; 2,000 projects with 20,000 lines; 3,000 purchases with 15,000 lines; 40,000 stock movements with 120,000 ledger entries; 15,000 lots; 3,000 invoices; 4,000 payments; 15,000 document versions; 20,000 evidence files; 300,000 audit events; 100,000 security events; 30,000 import rows, written once at the opening import | OB §30; DEP-05, DEP-09; the data steward's counts |
| AS-05 | Fixture scales 1x, 10x and 100x multiply every table of AS-04; 100x — 12 million ledger entries, 30 million audit rows — is far beyond the "hundreds of thousands of rows per busy table" of DATABASE §27 and exists to expose growth, not to predict it | GAP-017 |
| AS-06 | Budgets are server time per request; device, rendering and network time are P8's device evidence | DEP-03, DEP-09 |
| AS-07 | No measurement exists. A budget that P8 cannot meet is corrected here as a reviewed change — to the query, the index or the budget; it never becomes a reason to cut approved scope | GAP-017; CHANGE_CONTROL |
| AS-08 | A handful of legal companies — fewer than ten — so a cost proportional to the number of companies in scope is bounded | DATABASE §27 ("several companies"); the Owner's group |

## 2. Design decisions

| ID | Decision | Rejected alternatives and why |
| --- | --- | --- |
| D-PF-01 | Hot reads come from guard rows and aggregate-root columns; no list aggregates the ledger (DATABASE §20, §27) | Summing history on read: cost grows with data and every page would race the writers |
| D-PF-02 | Keyset pagination without totals for every list that can grow | Offset pagination with totals: the count and the skipped rows are computed again for every page |
| D-PF-03 | PostgreSQL only — exact B-tree lookups and trigram GIN search (D-DB-11) | An external search service or full-text engine: excluded (OB §30) and not needed for names and codes |
| D-PF-04 | No data cache in V1 ([section 9](#9-cache-policy)) | Caching lists or dashboards: a second copy of scoped, S3 data to key, invalidate and secure (H6-05), for a workload (AS-02, AS-03) that one host is assumed to carry |
| D-PF-05 | Heavy work is a job: exports, large reports, rendering, image derivatives (OB §35) | Doing it in the request: long transactions and timeouts |
| D-PF-06 | A request's query count is constant: it never depends on how many rows are shown | Budgets as averages: hide N+1 until the data grows |
| D-PF-07 | Partitioning, materialized views, read replicas and external caches stay out of V1 (DATABASE §27; ARCHITECTURE §14); BRIN and partitioning are recorded only as candidates with thresholds ([section 11](#11-growth-thresholds)) | Introducing them now: operational cost without a measured need |
| D-PF-08 | Lists are read per company and merged ([section 4](#4-pagination)); the cursor is this design's own — the framework's cursor paginator is not used | One index order across companies: a company-leading index cannot return several companies' rows in date order, and an index without the company would read out-of-scope rows before filtering. The framework paginator: its predicate is a nested OR form, for which no index-range use is documented, and its cursor is unsigned, readable JSON that would expose internal ids (D-DB-02) |

## 3. N+1 prevention and loading rules

| ID | Rule |
| --- | --- |
| PF-01 | N+1 is a defect (OB §29; ARCHITECTURE §9): the number of queries of a request never depends on the number of rows it shows (D-PF-06). |
| PF-02 | Lazy loading is prevented outside production by the framework's strict checks (WS-01), so a missed eager load fails a test; production runs without them, and the query-count tests of PF-09 are the guard. |
| PF-03 | Each page and report has one query class that names the relations it eager-loads and the columns of each, keys included; nothing else is loaded. The framework's automatic eager loading is not relied on: it would hide a missing declaration from the query-count tests. |
| PF-04 | Counts, sums and existence come from guard columns or from aggregate sub-selects in the same query — never from loaded child collections. |
| PF-05 | A list row reads aggregate-root columns and guard totals (DATABASE §20); a list never issues a per-row child query. |
| PF-06 | No `SELECT *` on a table with wide columns — a document version's payload, audit before and after values, an import row's payload, a session payload; those columns are selected only by the view that shows them. |
| PF-07 | The full catalogue, client list or ledger is never loaded; selection fields search on the server (OB §29). |
| PF-08 | Every page prop is a closure, so a partial reload evaluates only what it asks for; secondary data is a deferred or optional prop ([section 8](#8-images-and-inertia-props)). |
| PF-09 | Query-count assertions on the OB §29 pages are P8 regression tests (HO-16); changing a budget is a reviewed change to this document. |
| PF-10 | A query that relies on a partial index writes the index predicate as a literal: the planner proves the implication when it plans, and a bound parameter cannot qualify. |
| PF-11 | Bulk reads — exports, reconciliation, import validation — read in keyed chunks and stream; bulk business writes happen only through commands, row by row (JB-07). |
| PF-12 | A page read takes no row lock and never waits on one (TX-06). |

## 4. Pagination

| ID | Rule |
| --- | --- |
| PF-13 | Every list that grows without bound is paginated by keyset, newest first, on a registered key that ends in `id`, the unique tiebreaker. **Company-owned rows** — projects, purchases, invoices, payments, documents, evidence, company-attributed movements — are read **one company at a time** on (`company_id`, sort key, `id`); the default sort key is `id` itself, served by the routine UNIQUE (`company_id`, `id`) of DATABASE §1. **Rows of no company** — pool movements, pool evidence and audit events without a company — are one more branch, `company_id IS NULL`, on the same index, for the audience PERMISSIONS_MATRIX gives them (PJ-03, PJ-08, `audit.view`). **Audit events** are global records without that routine key: their lists are keyed (`company_id`, `occurred_at`, `id`) or, for one record's timeline, (`entity_type`, `entity_id`, `occurred_at`, `id`) (IX-13). **Master and pool lists** — products, client organizations, suppliers, lots, serials, counts, pending cases — have no company member and are keyed (sort key, `id`), `id` alone by default (IX-11). |
| PF-14 | The cursor is opaque: encrypted and authenticated under a server key, so it can be neither read nor forged, and it binds the last key values, the sort key and direction, the NULL bucket (PF-42), a digest of the filters and a digest of the actor's company scope. It is re-validated on use (WS-03; DP-02): a cursor that fails a check, or was made for other filters or another scope, is a validation error — never a silent first page. No internal id appears in a URL (D-DB-02). |
| PF-15 | Such a list shows "more", never a total or page numbers. A count a screen needs — a badge, a queue size — comes from a guard or from a bounded count under the rules of PF-24, **per company**; a figure counted or summed over S3 rows of several companies is a consolidated figure and follows PF-25 (CS-08; PJ-13). |
| PF-16 | Offset pagination is used only for small bounded sets, such as the lines of one project or the accounts of one company. |
| PF-17 | Page size is 25 by default and 100 at most (WS-03). |
| PF-18 | The keyset predicate is a row-value comparison on (sort key, `id`) inside one company's index range. Because its use as an index condition is documented only in old release notes, every keyset query is verified by EXPLAIN ([section 12](#12-index-register-and-verification-procedure)). |
| PF-41 | **Several companies.** An actor with several companies in scope — the Owner has all of them — gets one bounded branch per company, and one more for the rows of no company where the list has them (PF-13), merged by (sort key, `id`) in the same statement. An unfiltered branch is an index range scan of page size + 1 rows; a filtered one is bounded by PF-42. No branch reads a row outside scope, no index order across companies is needed, and the cost is the number of branches times the page size (AS-08). A company filter of the request narrows the branches and is intersected with scope (DP-02). |
| PF-42 | **Sort and filter keys.** A whitelisted sort key (WS-03) is offered only when its order is total, it is a column of the list's own row and an index (`company_id`, key, `id`) is registered for it (IX-11) — an invoice's date and number live on its current version and are therefore not offered. A nullable key states where its NULL rows go, the cursor carries that bucket and the predicate compares inside one bucket: a receivable's current due date — NULL while an invoice is BELUM DITAGIHKAN or aged as MIGRASI — and a project's deadline sort with their NULL rows last. A whitelisted filter (CS-08) is offered the same way: with a registered index that leads with (`company_id`, filter columns) and ends in (sort key, `id`) — a project's state or client, a payment's or an invoice's client, a movement's type — or, where no such index is registered, with a stated bound on the rows one branch may read before its page is returned short, with "more". A key without these properties is not offered. |

## 5. Search

| ID | Rule |
| --- | --- |
| PF-19 | **Exact lookup first.** A barcode (`code_key`) or SKU resolves to at most one active product. A serial key, a project number and a formatted document number are unique only within their scope — per product, per company, per company and type — so their lookup returns the few rows that share the key under a small LIMIT: an ambiguous scan is never auto-selected (SF-SCAN), and number lookups carry the scope filter of PF-22 (DP-06). Scanner input always goes here and never to trigram search (QB-06). |
| PF-20 | **Name and title search** uses the GIN trigram indexes of DATABASE §20 — a company-owned title only as PF-22 allows — by `ILIKE` with the term's wildcards escaped for fragments and word similarity for ranking, for a term that yields a trigram: at least three consecutive letters or digits after normalization. WS-03's three-character minimum is necessary but not sufficient, because other characters are ignored and a term without a trigram scans the whole index; such a term is answered only by the lookups of PF-23. |
| PF-21 | **Thresholds and ranking.** `pg_trgm.word_similarity_threshold` 0.6 and `similarity_threshold` 0.3, the defaults, as starting values; a hit is a substring match or a word-similarity match. Names that equal the term or begin with it come first, from a separate bounded query on a pattern-class index (IX-17), so the record a search-first creation looks for is never lost in a long list. A GIN index cannot return rows in similarity order, so the other hits are ranked within a bounded candidate set of 200 matches and then cut to 20 rows for a type-ahead or one page for a result list; a search that reaches the bound says that its list is cut and asks for a narrower term (HO-10). A GiST trigram index is the recorded candidate if the ranked type-ahead misses QB-03. P8 measures thresholds, candidate bound and plans on fixtures with Indonesian institution names and fragments such as "SMAN 1", "UGM" or "SD 1" (HO-16); a change is a reviewed change to this document (AS-07). |
| PF-22 | **Scope first (CS-07; H6-08).** A search over company-owned rows carries the company filter in the same statement, so ranking, limits and counts never see a row outside scope, and its plan reads no entry outside scope either: for an actor whose scope is not every company, the company is the index condition — the statement walks that company's own range and the fragment filters it there, which at AS-04's volumes is a few thousand rows per company — while the trigram index on `projects.title` serves a scope of all companies. A plan that lets the fragment choose first — a filter after the trigram scan, or a bitmap AND, which still walks the index entries of other companies' titles — is refused by the procedure of section 12, because its response time would follow other companies' data. If the per-company filter misses its budget at 10x, the recorded candidate is one multicolumn GIN index over company and title, which needs the `btree_gin` contrib module and goes through change control. S0 master names — products, client organizations, suppliers — need no scope filter; a client unit's name is S2 and is searched only with `projects.view` and a company in scope (PJ-10; CS-06). |
| PF-23 | Search input is at most 100 characters and the search limiter of WS-12 applies; a term without a trigram (PF-20) is answered only by exact lookups on keyed columns and by the equality and prefix lookup of PF-21. |

## 6. Query budgets

Every authenticated request pays an **envelope** before its own queries: the session read, the account row, the three grant reads of the resolvers (EN-02) and — for a user-initiated request only — the session write: six queries, five for a background request. Budgets below are **data queries beyond the envelope**, constant with respect to the rows shown (PF-01) and independent of table size, and **server time at the 95th percentile at 1x and 10x**. All are assumptions (AS-07).

| ID | Page or action class (OB §29) | Data queries, first response | Deferred or optional groups | Server time |
| --- | --- | --- | --- | --- |
| QB-01 | Projects list | ≤ 4 — the list query holds one branch per company (PF-41) | filter lookups: 1 group, ≤ 2 | 300 ms |
| QB-02 | Project detail | ≤ 10 | timeline, completion predicates, documents: 3 groups, ≤ 10 each | 400 ms; 500 ms per group |
| QB-03 | Products list and search | ≤ 3 | — | 300 ms; 200 ms for a type-ahead |
| QB-04 | Product detail | ≤ 6 | price history, recent movements: 2 groups, ≤ 3 each | 400 ms |
| QB-05 | Inventory: stock by product and location, lots, movement history | ≤ 4 | lot and serial detail: 1 group, ≤ 3 | 300 ms |
| QB-06 | Scanner exact lookup | ≤ 2 | — | 150 ms |
| QB-07 | Billing and receivables (*Piutang*), aging | ≤ 4 | aging buckets: 1 group, ≤ 2 | 300 ms |
| QB-08 | Invoices list | ≤ 4 | — | 300 ms |
| QB-09 | Invoice detail | ≤ 8 | applications and settlements, documents: 2 groups, ≤ 4 each | 400 ms |
| QB-10 | Dashboard and action queue | ≤ 5 | QS signals: ≤ 4 groups, ≤ 12 statements each — the register's statements, the ten completion predicates of QS-15 forming a group of their own; period figures (CALC-08–CALC-14): 1 group, ≤ 6, read in one snapshot (TX-10) | 300 ms; 500 ms per signal group; 1 s for the period figures |
| QB-11 | Report on screen | ≤ 6, read in one snapshot (TX-10) | — | 1 s |
| QB-12 | Payments, documents and evidence lists; audit timeline | ≤ 4, plus one per kind of linked record present on the page where a list follows an exclusive arc (evidence links, audit entities) | — | 300 ms |
| QB-13 | Command of retry class A | about 40 statements: a constant plus one per lock class and one per guard table touched, once the multi-row lock form of LR-05 is verified (HO-13) — otherwise one per locked row, still independent of table size (GU-01) — and never one per row read | — | 500 ms for a reference command of ten lines |
| QB-14 | Command of retry class B | statements proportional to the rows it must change, set-based wherever the posting rules allow | — | 2 s at 1x |
| QB-15 | Export job | keyed chunks, bounded memory | — | 60 s per 100,000 rows |
| QB-16 | File request — thumbnail, preview, download | ≤ 2; a thumbnail of an S0 product image needs only authentication, so its envelope is the session read and the account row (three queries in all) | — | 100 ms before the stream starts; no session write for a thumbnail or a preview, one for a download (RV-06) |
| QB-17 | Rendition: issue to READY (JB-01) | — | — | 30 s at 1x with the workers of SZ-02 |

## 7. Dashboards, reports and exports

| ID | Rule |
| --- | --- |
| PF-24 | Each QS signal is one bounded statement — QS-15 one per predicate —, registered below with its source and its index or starting point and verified like every hot query (section 12). A signal whose rows carry their company reads one bounded branch per company in scope inside that statement (PF-41); a pool signal (QS-17, QS-18, QS-22) has no company and is read once. A signal over a guard that carries no company starts from that company's open roots — ACTIVE projects, OPEN purchases, receivables with an outstanding amount — and reaches the guard by its key, so it reads no row of another company and its cost follows the company's open work. Five sources are not open work but sets that only grow, slowly: FAILED rendition attempts (QS-08), payments flagged as unevidenced (QS-12), closed projects with residual obligations (QS-16), STALE counts (QS-18) and pre-payment Kuitansi chains (QS-19); each is one set query whose cost follows that number and is measured at 10x and 100x (HO-16). Three reads go beyond the actor's scope and return nothing from there (CS-07): renditions still owed (QS-08) and bank lines not fully cited (QS-12) read the workspace's rows of their partial index before the company filter of the same statement, and the every-company test of QS-20 reads the events of the other companies of a refused command, as PJ-13 requires. If P8 finds the first two material, the remedy is a company key on those guards, proposed through change control. Signals are grouped into at most four deferred props; no queue is stored (WORKFLOWS §10). |
| PF-25 | Figures that count or sum S3 values across companies exist only in the Owner's consolidated query classes (`reports.consolidated`; CS-08); an Admin's multi-company queue lists rows, labelled by company, and never totals across companies. |
| PF-26 | A dashboard refreshes on the user's action or by polling no faster than once a minute, the poll naming its props; polls are background requests that never extend a session (RV-06). |
| PF-27 | An on-screen report is bounded by a date range and a company filter on the registered date indexes (IX-11; the sales path is a known candidate of section 12) and reads all its figures in one snapshot (TX-10), so its totals agree with each other (AC-23). A report that misses QB-11 is corrected under AS-07 — its query, its index or its budget — and stays on the screen; an export is offered beside it, never instead of it. |
| PF-28 | An export (JB-03) uses the page's own query class and filters (ARCHITECTURE §14) inside one snapshot (TX-05), reads in keyed chunks and writes through a streaming writer. A row bound — 200,000 rows as a starting value, a P9 disk guard — is checked before generation: a request above it is refused with a message asking for a narrower period instead of failing midway. Files are private and short-lived (FL-09). |
| PF-29 | A download is an ordinary browser request to the authorizing controller, never an Inertia visit (RV-08). |
| PF-30 | Reconciliation (JB-06) is set-based per guard family; its duration at 100x is a P8 measurement against a 10-minute budget. |
| PF-43 | Period figures — CALC-08–CALC-14 — are aggregates over facts bounded by company and date range on the same registered indexes. They are one deferred group, read in one snapshot (TX-10) so that revenue, cost and profit are figures of one instant, with the report budget — never part of a first response — and the consolidated ones follow PF-25. |

**Signal register.** "Reads" names the rows a signal is computed from; "Index or start" the DATABASE §20 index that serves it, the register row that adds one, or the open root its statement starts from (PF-24). Every source is a typed column: a signal never reads `metadata` (DATABASE §17).

| Signal | Reads | Index or start |
| --- | --- | --- |
| QS-01, QS-06 | the lines of the company's ACTIVE projects whose guard shows WAREHOUSE demand not yet reserved (QS-01), or a dispatched or confirmed quantity not yet delivered (QS-06) | `projects (company_id, state)` (IX-14), then the lines and `project_line_balances` by key |
| QS-02 | the open-demand lines of QS-01 against their product guard rows | IX-14; primary key of `stock_product_balances` |
| QS-03 | for the lines of QS-01, the purchase lines linked to them (`purchase_lines.project_line_id`) whose guard shows a received quantity | the foreign-key index on that column; `purchase_line_balances` by key |
| QS-04 | `reservation_events` of kind CUT on the reservations of the lines of QS-01 | the foreign-key index on `reservation_events (reservation_id)` |
| QS-05 | the lines of the company's OPEN purchases whose guard shows an open remainder | `purchases (company_id, state)` (IX-14), then `purchase_line_balances` by key; DATABASE §20's partial index serves a scope of all companies |
| QS-07 | the company's OPEN correction cases with their residual counters — the refund shortfalls and RESIDUAL settlements assigned to them | DATABASE §20 |
| QS-08 | document versions whose latest rendition attempt is PENDING or FAILED; the company comes from the version | DATABASE §20; IX-07 — the workspace's rows of that partial index, every FAILED attempt recorded among them (PF-24) |
| QS-09, QS-10, QS-11 | `invoice_balances` with outstanding above zero, by current act and due date; the uncleared dispute holds of those invoices | DATABASE §20; the partial UNIQUE key of C-42 |
| QS-12 | `application_source_balances` with unapplied capacity, including credit linked to a written-off invoice; payments whose `unevidenced_reason` is set and that still have neither a cited statement line nor linked proof, and bank payments that cite no statement line yet; bank lines whose evidence guard is not fully cited | IX-14 for the source guard, which carries its company, and for the two payment lists; line-keyed `evidence_balances` — the workspace's open rows (PF-24) |
| QS-13 | `application_source_balances` with claimed deductions not yet settled | IX-14 |
| QS-14 | audit events of authority class ADM_PLUS or OWNER_ONLY; the acknowledged warnings of QS-21; the items WORKFLOWS lists for the Owner — recovered money linked to a written-off invoice (`payments.recovery_for_invoice_id`), refunds with a shortfall, invoices left with an outstanding amount after a payment; pool evidence (`evidence_documents` of scope POOL); import rows in state DUPLICATE whose committed twin lies in a company outside the preparer's grants (DP-18); AUTHORIZATION_ANOMALY security events. Two P5 items have no typed record yet and are handed over: a duplicate match the uploader may not see, and an import batch collision outside the preparer's scope (GAP-034; CONCURRENCY_IDEMPOTENCY HO-38) (`TECH-022`: settled — both are read, in the statement that reads the AUTHORIZATION_ANOMALY events, from the `security_events` kinds EVIDENCE_DUPLICATE_UNDISCLOSED and IMPORT_COLLISION_UNDISCLOSED and their typed columns, SECURITY LG-02) | IX-13; IX-14; DATABASE §20; the keys of the named tables |
| QS-15 | the ten completion predicates for the projects of the page shown — one set query per predicate over those project ids, never one per project; confirmed value not yet invoiced, from `project_commercial_balances`; invoicing above delivered value, as one aggregate over `project_line_balances` and the pins of those projects | primary keys; `projects (company_id, state)` (IX-14) |
| QS-16 | projects in state COMPLETED_FORCED or CANCELLED joined to their open guards | IX-14; one set query whose cost follows the number of such projects — measured at 10x and 100x (HO-16) |
| QS-17 | products that carry a minimum joined to their product guard | IX-14; one set query whose cost follows the number of such products — measured at 10x and 100x (HO-16) |
| QS-18 | OPEN count findings; STALE counts | the partial UNIQUE key of C-34; IX-14 — the STALE counts are a set that only grows, measured at 10x and 100x (HO-16) |
| QS-19 | the company's pre-payment Kuitansi — DOC-05 chains of mode PRE_PAYMENT — whose issued version is neither linked nor voided | IX-14; one set query whose cost follows the number of such chains — measured at 10x and 100x (HO-16) |
| QS-20 | audit events in the routed variant (CONCURRENCY_IDEMPOTENCY SQ-23) of the last 14 days, one item per actor, refused action and target; an item is shown when, for one of its refused commands, every event of that command lies in a company in scope (PJ-13) | IX-13 |
| QS-21 | audit events in the acknowledged variant — a duplicate warning overridden or confirmed (CONCURRENCY_IDEMPOTENCY section 11) | IX-13 |
| QS-22 | cases with pending quantity above zero | DATABASE §20 |

## 8. Images and Inertia props

| ID | Rule |
| --- | --- |
| PF-31 | Lists show thumbnails only — derivatives of fixed sizes made by JB-04 (OB §30); the original is loaded only by a detail view. |
| PF-32 | Thumbnail and preview requests are background requests: they read the session and write nothing (RV-06), and each is a file request of QB-16. A list view asks for at most one page of thumbnails — 25 at the default page size — and images below the fold load when they become visible. |
| PF-33 | Shared props stay minimal (WS-07); page props are closures (PF-08). |
| PF-34 | A page uses at most three deferred groups, because each group is one more full request with its own envelope; the dashboard, whose groups are independent signals, may use five (QB-10). Rarely needed data is an optional prop, fetched on demand. |
| PF-35 | Prefetching is off on S3 and S4 pages (WS-07) and never used for a command. |
| PF-36 | A partial reload names its props; a poll or reload without them re-evaluates the whole page and is a defect. |

**Open technical item (no Owner decision):** SECURITY FL-07 serves private files with `Cache-Control: private, no-store`, so an authenticated thumbnail is fetched again on every list view; PF-32 and QB-16 bound that cost. This document recommends that SECURITY's owner allow a short private cache lifetime for thumbnails of S0 product images alone — a Level-1 change of FL-07 that P6 does not make — and P8 measures the cost first (HO-16).

## 9. Cache policy

| ID | Rule |
| --- | --- |
| CP-01 | V1 needs no data cache: guard rows make the hot reads single-row reads. No S2–S4 query result is cached (DP-10). |
| CP-02 | The store holds the queue, limiter counters, scheduler, unique-job, job-overlap and verification-cap locks and the worker-restart signal — never business data. The framework's configuration, route, view and event caches are files written at deployment, not entries of the store. |
| CP-03 | PostgreSQL is the only system of record; nothing is correct merely because a cache entry exists (OB §30). |
| CP-04 | A cache introduced later follows H6-05: keyed by the actor's full effective scope set and capability set — or limited to S0, S1 or already-masked data filtered per request — written and invalidated only after commit, short-lived, and justified by a P8 measurement against a budget of [section 6](#6-query-budgets). |
| CP-05 | Per-request memoization of the scope and capability resolvers (EN-02) is not a cache: it ends with the request. |
| CP-06 | Browser caching of authenticated responses stays `no-store` (WS-08; FL-07). |
| CP-07 | Limiter and lock keys must survive a deployment: they live where no release step flushes them, and the release procedure never clears the application cache store wholesale — a flush would reset the login cooldowns of AU-05 and release overlap, unique-job and cap locks in mid-run (HO-23, HO-31). |

## 10. Session and authentication load

| ID | Rule |
| --- | --- |
| PF-37 | The PostgreSQL session store costs one read per request and one row rewrite per user-initiated request — the driver rewrites the row without a dirty check — and no write for a background request, which needs the session middleware variant of RV-06. At AS-03's peak that is a few single-row writes per second on a table of tens of rows. |
| PF-38 | The per-request active-account and credential checks read no extra row: both compare columns of the account row the session guard already loads (RV-05, RV-10). |
| PF-39 | The password-verification cap of H6-12 admits three hashes at once — two for unauthenticated verifications and one reserved for authenticated ones — on four cores that PostgreSQL, the renderer and the queue share (AS-01). At AU-02's 0.25–0.5 s per hash the unauthenticated slots serve 4–8 verifications per second, which AS-02 assumes to be far above the real need. A refused unauthenticated verification waits at most 250 ms, so that the waiting requests of a flood cannot hold the worker pool: wait × excess rate must stay below the spare workers of SZ-01, and the proxy's login limit (H9-02) is the first bound on that rate. Inputs only — tuning Argon2id and the cap is P9's (HO-26). |
| PF-40 | Login, credential-link and step-up requests read only the account row and the limiter; they run no business query. |

## 11. Growth thresholds

| ID | Table or store | V1 design | Candidate and threshold |
| --- | --- | --- | --- |
| GT-01 | `audit_events` | the B-tree indexes of DATABASE §20 and IX-13 | a BRIN index on `occurred_at` when the table passes 10 million rows or its time-range scans stop meeting QB-12 |
| GT-02 | `stock_movement_entries` | the (`lot_id`, `id`), (`product_id`, `id`) and (`serial_unit_id`, `id`) B-trees | a BRIN index on `recorded_at` at the same threshold, for time-range scans of history |
| GT-03 | any table | no partitioning (DATABASE §27) | time partitioning only when a table outgrows memory, the manual's rule of thumb; at 100x the audit table with its indexes approaches the host's memory, so the review of GT-07 decides, not an assumption that the threshold is far away |
| GT-04 | `command_log` | bounded by retention (CI-07) | none; about thirty days of commands |
| GT-05 | sessions, credential tokens, export requests, queue and failed-job rows | bounded by their purges (HO-27, HO-29; SECURITY H9-04) | none |
| GT-06 | files: evidence, renditions, exports, thumbnails | private disk (DATABASE §22) | a P9 disk-budget input: renditions and thumbnails are reproducible and exports short-lived |
| GT-07 | review trigger | P9 reports table and index sizes monthly (HO-25) | crossing half of a threshold opens a measured review, never an automatic change |

## 12. Index register and verification procedure

DATABASE §20 owns the index design and asks P6 to confirm each index with query plans. P6 adds the lookups its own mechanisms and rules need and defines how every index is verified; nothing is measured until P8 runs the procedure. The `TECH-021` clause of DATABASE §20 points here.

| ID | Index (logical) | Serves |
| --- | --- | --- |
| IX-01 | `payments (company_bank_account_id, amount, business_date)` | the duplicate query of BD-02 |
| IX-02 | `file_objects (sha256)` | the content lookups of FL-10 (BD-15) |
| IX-03 | `command_log (expires_at)`, beside its primary key | the purge JB-08; CI-03 |
| IX-04 | credential-token store: UNIQUE (token hash); partial UNIQUE (account, purpose) over unused, unrevoked links; (expiry) | H6-11 |
| IX-05 | `export_requests (requester, state)`, `(state, claimed_at)` and `(state, recorded_at)` | H6-04; admission, the access changes of RV-08, the sweep JB-09 and cleanup |
| IX-06 | `payment_applications (payment_id)` and `(opening_customer_credit_id)` over every state | the derivations of AX-20, AX-23 and NX-08 — DATABASE §20 indexes ACTIVE rows only |
| IX-07 | `document_renditions (state, recorded_at)` over PENDING, RENDERING and FAILED | the sweeper JB-02 — DATABASE §20 indexes PENDING and FAILED for QS-08 |
| IX-08 | the foreign-key indexes the cascade derivations read: `stock_consumptions (lot_id)`, `cost_attributions (stock_consumption_id)` and `(lot_id)`, `lot_cost_entries (purchase_line_id)`, `stock_restorations (stock_consumption_id)`, `dispatch_lines (project_line_id)`, `delivery_lines (project_line_id)`, `evidence_links` by linked record | the protocol of CONCURRENCY_IDEMPOTENCY §5 and BD-13; covered by DATABASE §20's foreign-key rule and listed so that they are verified |
| IX-09 | `sessions (user_id)` — the framework's table | RV-05; an index on its last-activity column is left to P8's measurement, because it would make every session rewrite an index update on a table of tens of rows |
| IX-10 | the pending-case index by scope of DATABASE §20 | the recognition derivation of AX-06 |
| IX-11 | keyset order: (`company_id`, key, `id`) for every offered sort key — `business_date` on `payments`, `disbursements`, `expenses` and `other_cash_receipts`, where the tiebreaker extends DATABASE §20's indexes of the same leading columns, and on `stock_movements` — pool movements included — and on `purchases`, which are new; the current due date on the receivable guard; the deadline of open projects — and the filter indexes PF-42 registers, such as `projects (company_id, state, id)` and `projects (company_id, client_organization_id, id)`; (key, `id`) for the master and pool lists of PF-13 | PF-13, PF-41, PF-42 |
| IX-12 | `stock_scope_balances (location_id, product_id, condition)` | stock by rack or location (QB-05; AC-22) — the guard's own key leads with the product |
| IX-13 | `audit_events`: the tiebreaker `id` added to DATABASE §20's `(company_id, occurred_at)` and `(entity_type, entity_id, occurred_at)`; partial `(company_id, occurred_at, id)` over authority class ADM_PLUS and OWNER_ONLY, over the acknowledged action codes and over the routed ones, each predicate written as a literal list (PF-10); partial `(command_id)` over the routed codes, for the every-company test of QS-20 | PF-13; QS-14, QS-20, QS-21 |
| IX-14 | indexes for the signals DATABASE §20 does not yet serve: `projects (company_id, state)` and `purchases (company_id, state)`, the open roots of PF-24; partial `project_line_balances` with an open remainder and `project_commercial_balances` with confirmed value above invoiced, for a scope of all companies; partial `application_source_balances (company_id)` with unapplied capacity or unsettled claimed deductions; partial `payments (company_id)` for bank payments that cite no statement line and for payments whose `unevidenced_reason` is set; partial line-keyed `evidence_balances` not fully cited; partial `documents (company_id)` over the pre-payment Kuitansi chains; `stock_counts` in state STALE; products that carry a minimum; partial `import_rows` over the state DUPLICATE; partial `security_events (occurred_at)` over the kind AUTHORIZATION_ANOMALY (`TECH-022`: and, in the same literal list — PF-10 —, over the kinds EVIDENCE_DUPLICATE_UNDISCLOSED and IMPORT_COLLISION_UNDISCLOSED) | QS-01–QS-06, QS-12–QS-19 |
| IX-15 | `file_objects (uploaded_by, recorded_at)`; partial `import_batches` with a commit request and state SIGNED_OFF | the upload budget of H6-07; the sweep JB-09 |
| IX-16 | the first-use proofs of CONCURRENCY_IDEMPOTENCY §8: ledger entries by (product, location, condition); projects by (company, period); the other proofs read keys DATABASE already indexes — entries of a lot, entries citing a reversed movement, postings of a share, issued versions by company, type and period | FU-01–FU-07; run only when a first-use insert really creates a row |
| IX-17 | B-tree indexes in a pattern operator class — or the default class under the C collation — on the lower-cased names PF-21 looks up by equality and by prefix: `products.name`, `client_organizations.name`, `client_units.name`, `suppliers.name` and, led by `company_id`, `projects.title` | PF-21, PF-23 |
| IX-18 | `user_external_identities`: the partial UNIQUE keys (`provider`, `provider_subject`) and (`user_id`, `provider`) WHERE `unlinked_at IS NULL`, and (`user_id`) — DATABASE §20; the "in recovery" read of SECURITY AU-20 b uses `audit_events (entity_type, entity_id, occurred_at)` with IX-13's tiebreaker | the Google sign-in lookup by subject and the one-link rule (SECURITY AU-22, AU-23; CONCURRENCY_IDEMPOTENCY BD-19); the account's link history and the unlinks of RV-05, RV-14–RV-16 and H6-11 |

**Known candidates for P8's measurement (HO-16):** movement history of one product within a company scope — the ledger entry carries no company, so either the (`product_id`, `id`) walk discards other companies' entries or a company-driven walk discards other products; if neither meets step 4 at 10x, a company key on the entry row is proposed through change control. The sales report and the revenue figure of a period (CALC-08) — the date lives on `invoice_versions` and the company on `invoices`, so the path is the company's invoices joined to their current versions; a company key on `invoice_versions`, as `document_versions` has one, is proposed the same way if that path misses QB-11 at 10x. The rendition indexes hold every FAILED attempt ever recorded (QS-08; JB-02); if failures accumulate, a marker of the latest attempt on the version is proposed the same way. A GiST trigram index for the ranked type-ahead (PF-21). A multicolumn GIN index over company and title (PF-22).

**Verification procedure** (run by P8 on generated fixtures; re-run on a schema or query change and on a PostgreSQL major upgrade):

1. Generate synthetic fixtures at 1x, 10x and 100x (AS-05) and analyze them.
2. Register every hot query: each query class of QB-01–QB-12 with every filter it offers (PF-42), the file-request lookups of QB-16, each per-company keyset branch and its merge, each exact lookup and search, each QS signal and period figure, each lock statement and each derivation of CONCURRENCY_IDEMPOTENCY §5 and §7, each reconciliation query, each purge and sweep.
3. Capture the plan of each with `EXPLAIN (ANALYZE, BUFFERS)` at every scale; a data-changing statement runs inside a transaction that is rolled back.
4. Accept a query when: no sequential scan touches a table above 10,000 rows in an interactive path; a list reads at most one page and one row per branch (PF-41), or the stated bound of PF-42 in a filtered branch, and a signal no more than the rows PF-24 allows it; its keyset predicate is an index condition (PF-18); a partial index the design relies on is chosen (PF-10); a multi-row lock statement sorts below its row-locking node (LR-05); a search plan is scope-first as PF-22 defines it — a scoped title search may read its own company's whole range — and ranks a bounded candidate set (PF-21); no plan depends on a feature of one PostgreSQL version, such as skip scan, without saying so (HO-30); and buffers and time grow sub-linearly from 1x to 100x for lists and lookups.
5. Keep the plans as P8 evidence beside the query-count tests (HO-16).
6. In staging and production P9 watches statement statistics and slow plans with the contrib modules `pg_stat_statements` and `auto_explain` — no recurring cost — and reports the heaviest statements and the table and index sizes monthly (HO-25).

## 13. Resource inputs for P9

| ID | Input |
| --- | --- |
| SZ-01 | **Web workers:** up to 16 PHP workers as a starting ceiling, bound by memory — assumed at 60–100 MB each until P9 measures; each holds at most one database connection and only while a request runs. |
| SZ-02 | **Queue workers:** two on the single main queue, plus the scheduler; rendering and exports are the heavy jobs; each job's timeout stays shorter than the queue's retry-after. At most one export runs at a time across accounts — the overlap lock of JB-03 — so a rendition never waits behind two exports (QB-17). |
| SZ-03 | **PostgreSQL connections:** web workers, queue workers, the scheduler, administration and a reserve — about 16 + 3 + 6 = 25, inside the usual default limit of 100. No pooler is expected; P9 decides. The design uses transaction-scoped settings and locks (`SET LOCAL`) with one exception: the registration keys of evidence are session-level advisory locks that an attempt holds from before its transaction until it has ended (CONCURRENCY_IDEMPOTENCY FU-10). Database sessions therefore end with the request; persistent connections or a transaction-pooling proxy added later would need those keys to move to the lock store (HO-22). |
| SZ-04 | **Memory split**, as a tuning start: about a quarter of RAM for PostgreSQL's shared buffers; the rest for the file cache, PHP workers, the store, the renderer and the hashes of H6-12 (three slots times the Argon2id memory cost). |
| SZ-05 | **Timeouts and retry budgets:** TX-01–TX-10, RY-02, RY-03 and RY-08 (HO-21), the role-level default statement timeout that page reads rely on included. |
| SZ-06 | **Logging for verification:** lock waits, every deadlock, statements slower than 500 ms; statement statistics and automatic plan logging from the contrib modules (HO-25). |
| SZ-07 | **Limiter and cap values:** WS-12, AU-05 and H6-12, as assumptions until measured. |
| SZ-08 | **Disk:** the growth inputs GT-04–GT-06 for the shared-disk budget of DEP-01. |
| SZ-09 | **The queue, cache and limiter store:** a small memory budget — payloads are identifiers, plus counters and locks. It needs no persistence for correctness, because every piece of owed work has a durable row and a sweep (JB-02, JB-09); it is sized and given an eviction policy under which limiter keys can neither evict queue payloads and locks nor fill the store (HO-24). |

## 14. Traceability

- **Owner brief:** §29 → PF-01–PF-12, QB-01–QB-12; §30 → sections 4, 5, 8, 9 and 11; §31 and §35 → PF-28, D-PF-05.
- **DATABASE and ARCHITECTURE:** DATABASE §20–§21 → sections 4, 5 and 12; §27 → AS-01–AS-08, GT-01–GT-07; ARCHITECTURE §9 and §14 → sections 3, 6 and 7; the "measured" sentences of DATABASE §20 and §27 and ARCHITECTURE §14 and §20 → the boundary above and HO-16.
- **SECURITY and PERMISSIONS_MATRIX:** H6-05 → CP-01–CP-07; H6-08 → PF-22, PF-41, IX-01–IX-17; H6-12 → PF-39; H6-14 and AU-22–AU-24 → IX-18; WS-03 → PF-14, PF-17, PF-20, PF-23, PF-42; CS-07, CS-08 → PF-15, PF-22, PF-25; DP-02, DP-06 → PF-14, PF-19.
- **Acceptance and references:** AC-23 → sections 5–7; REF-050 → section 5; REF-059 → sections 3, 4 and 12.
- **Dependencies and gaps:** DEP-09 → AS-02–AS-05; DEP-07 → the evidence table; GAP-017 and GAP-006 receive P6 continuation notes — the budgets are assumptions until P8 measures them, and scope-first plans are verified there. **OWNER_DECISION_REQUIRED = 0.**
