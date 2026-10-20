# Development

How to start, test and build MULTIPLECORP locally. Everything runs in Docker Compose — the application's PHP runtime behind Nginx, PostgreSQL, Valkey, Node.js and Playwright, each pinned in [`docker-compose.yml`](docker-compose.yml) and [`docker/php/Dockerfile`](docker/php/Dockerfile) — and CI runs the same commands on a fresh runner ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)). The versions are recorded in [TOOLCHAIN](docs/00-governance/TOOLCHAIN.md).

## Requirements

- Git. The repository's `.gitattributes` keeps text files with LF line endings on every system.
- Docker with the Compose plugin: Docker Engine on Linux; Docker Desktop with the WSL 2 backend on Windows.
- Ports 8080 and 8443 free on 127.0.0.1.

PHP, Composer and Node.js are not needed on the machine: they run in containers.

## Start

From the repository root, on Linux (bash):

```sh
cp .env.example .env
export HOST_UID="$(id -u)" HOST_GID="$(id -g)"
docker compose build app
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose up -d --wait
docker compose exec app php artisan migrate --database=migration
```

On Windows (PowerShell), the same commands, with the first two lines replaced by:

```powershell
Copy-Item .env.example .env
```

Docker Desktop maps file ownership itself, so `HOST_UID` and `HOST_GID` are not needed there; on Linux they let the containers write the project files as your user.

PostgreSQL holds the database roles of [DATABASE §28](docs/04-architecture/DATABASE/s25-28-transactions-handoffs.md#28-security-ready-data-design), which the `db-roles` service creates from the `DB_` lines of `.env` on every start: the application runs as the runtime role, which runs no DDL and holds only the privileges the migrations grant its tables; the migrations run only as the migration/owner role, through the `migration` connection — `php artisan migrate --database=migration` —, whose login owns the database and every table; the administration role is the runtime's plus the audited guard rebuild's; the server's bootstrap superuser only creates them. A stack started before TASK-005 is brought over once: add the new `DB_` lines of `.env.example` to `.env`, then `docker compose down -v` before the commands above.

The application answers at <https://localhost:8443> — with the framework's "not found" page until the first routes exist — and <http://localhost:8080> redirects there. Its certificate is signed by a throwaway local authority kept in the `dev-certs` volume, so the browser warns about it; accept the warning for local work only.

## Test

One command runs every check a machine can run — the repository gates and the layers TL-01–TL-08, TL-13 and TL-14 of [TEST_STRATEGY §2](docs/08-testing/TEST_STRATEGY.md#2-test-layers) — and CI runs it again on a fresh runner ([TEST_STRATEGY §17](docs/08-testing/TEST_STRATEGY.md#17-tooling)). It starts the stack, reports each check in TEST_STRATEGY §16's form and keeps each log in `storage/logs/verify/`; on Windows, run it in Git Bash:

```sh
sh scripts/verify.sh
sh scripts/verify.sh browser performance migration
```

The second line runs the browser suite (TL-09–TL-12) on Chromium and WebKit in the pinned Playwright image, the performance layer (TL-15) with the load tool's smoke run and the migrations from an empty database (TL-16, on a CI runner only). A layer whose first test is not written yet reports EMPTY with the Task that writes it, and runs as soon as one exists; any FAIL or NOT RUN fails the command. The production check removes the development packages, which a local run then reinstalls. `VERIFY_PHP`, `VERIFY_NODE` and `VERIFY_E2E` replace the commands that run PHP, Node.js and Playwright on a machine that cannot build the application image.

Each check alone, with the stack started:

```sh
docker compose exec app vendor/bin/pint --test
docker compose run --rm node npm run lint
docker compose exec app vendor/bin/phpstan analyse
docker compose run --rm node npm run typecheck
docker compose exec app php artisan test --parallel
docker compose run --rm node npm run test:lint-rules
docker compose run --rm e2e
docker compose run --rm k6 run tests/Performance/load/smoke.js
docker compose run --rm node npm run scan:secrets
docker compose run --rm node node scripts/ci/check-plan.mjs
docker compose exec app sh scripts/ci/audit-dependencies.sh php
docker compose run --rm node sh scripts/ci/audit-dependencies.sh js
sh scripts/ci/check-commit-messages.sh origin/main..HEAD
```

They are the lint (TL-01: Pint, ESLint), the static analysis and typecheck (TL-02: Larastan/PHPStan, the TypeScript compiler), the PHP suites of `phpunit.xml` — one per layer, in `tests/<Layer>/` —, the tests of the project's ESLint rules, the browser suite with its accessibility engine, the load tool's smoke run against the stack, the secret-like scan (SX-06), the Task plan's integrity check, the dependency audits (TL-14) and the commit-message check; the last one runs on the machine.

**Test data.** A test that migrates, refreshes, truncates or wraps the database runs on PostgreSQL in the database parallel testing creates for its process, `<database>_test_<n>`, and never in the development database, so the suites run with `--parallel` (TEST_STRATEGY TS-03). The migration role creates that database and migrates it, and the test runs as the application runtime, so a test proves a command under the runtime's privileges: a database test uses `Tests\Support\RefreshDatabase` or `Tests\Support\DatabaseTruncation`, never the framework's traits directly, and `--recreate-databases` is not used — the runtime may drop no database. Fixtures are synthetic (TS-04): the sets FX-01–FX-10 of [TEST_STRATEGY §4](docs/08-testing/TEST_STRATEGY.md#4-environments-and-data) in `tests/Fixtures/`, loaded by `Tests\Support\FixtureSets` and checked against the documents they come from; invented values from a fixed seed by `Tests\Support\Synthetic`; the controlled clock of FX-08 by `Tests\Support\Clock`; and the parallel PostgreSQL sessions of §6's race tests, held at a named point by a barrier, by `Tests\Support\Concurrency` — a race test commits its data, so it uses `DatabaseTruncation`. After every integration test the guards are reconciled (TS-11). Factories and seeders arrive with their models and tables (TASK-005–TASK-009), on the same synthetic values.

## Build

```sh
docker compose run --rm node npm run build
docker compose exec app sh scripts/ci/production-check.sh
docker compose exec app composer install
```

The first command is the frontend production build; the second builds the framework's production caches and checks the production configuration — debug off, no test route, no development dependency (TL-13). It removes the development packages, which the third command restores.

## Conventions

The rules every Task follows, each with its source, which wins where this summary differs; the checks named enforce them.

- **Layers and modules.** Presentation → application → domain → infrastructure, dependencies downward only; a module writes only through the actions and posting services of a lower tier, never in a cycle and never through another module's models, and reads another module only through its query classes; Reporting only reads — [ARCHITECTURE §2–§4](docs/04-architecture/ARCHITECTURE.md#2-layers). Enforced by `tests/Architecture/DependencyRulesTest.php`.
- **Actions.** One action class per business command in `app/Modules/<Module>/Actions`, named as the command — `RecordPayment`, `ApproveQuotation` —, the unit of work, authorization re-check and transaction of [ARCHITECTURE §5](docs/04-architecture/ARCHITECTURE.md#5-actions--the-command-envelope); it receives a typed data object built only from validated input, never the request, and no model is unguarded ([SECURITY WS-01](docs/05-security/SECURITY.md#4-web-and-input-security)). A controller only authorizes, validates the allowed fields, calls one action and shapes the output.
- **Queries.** Each page and report has one query class in `Queries/` that names the relations it eager-loads and their columns; counts and sums come from guard columns or aggregate sub-selects; no list issues a per-row query and no `SELECT *` reads a wide column — [PERFORMANCE §3](docs/06-api-performance/PERFORMANCE.md#3-n1-prevention-and-loading-rules) PF-01–PF-12. Outside production a lazy-loaded relation or a silently discarded attribute fails (`AppServiceProvider`; `tests/Architecture/StrictModelsTest.php`); the framework's automatic eager loading is not used.
- **Schema.** One migration stream, each file named by its module (`…_iam_create_users_table.php`) and run as the migration role; expand-only — a release adds, never drops or renames what a running release still reads (RELEASE_PLAN PRQ-04) — with a `down()` that TL-16 runs; DATABASE as written, a missing column an executor discovery for Planning, never invented. `App\Support\Database\Columns` gives §16's identity key and UUIDv7 `public_id`, §1's routine columns and command identity (C-48) and §17's types; `TableRules` adds each CHECK, key and index named after its row (`users_c62_…`, `audit_events_ix13_…`), codes as text with a CHECK list, and the runtime's privileges by table class (§28) — nothing it does not name. Models of append-only tables use `AppendOnly` (C-53); a public identifier comes from `HasUuids` on `public_id`; business dates are WIB days from `App\Support\BusinessDate`. [DATABASE §1–§3](docs/04-architecture/DATABASE/s01-03-foundations.md#1-conventions), [§13–§20](docs/04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation), [§28](docs/04-architecture/DATABASE/s25-28-transactions-handoffs.md#28-security-ready-data-design); checked by `tests/Integration/Schema/` and `tests/Architecture/DatabaseRolesTest.php`.
- **Money and quantity.** Exact decimals, never PHP float: money `numeric(18,2)` and base-unit quantities `numeric(18,3)`, carried as decimal strings — by the framework's exact `decimal` cast until the `Money` and `Quantity` value objects of `app/Support` arrive with the decimal library DEP-07 chooses — [EXECUTION_CONTEXT invariant 1](docs/11-tasks/EXECUTION_CONTEXT.md#project-invariants), [DATABASE §17](docs/04-architecture/DATABASE/s13-17-history-scope-types.md#17-money-quantity-and-date-types), [ARCHITECTURE §6](docs/04-architecture/ARCHITECTURE.md#6-domain-model-value-objects-and-calculators).
- **Naming.** Code, identifiers, tables and columns in English ([AICWDF §4B.3](docs/00-governance/sources/AI_First_Complex_WebApp_Task_Framework_v4.3_EN.md#4b3-technical-naming-remains-english)); Indonesian only in user-facing copy, through the localization keys; tables and columns as DATABASE names them; migration file names prefixed by their module ([ARCHITECTURE §4](docs/04-architecture/ARCHITECTURE.md#4-code-organization)).
- **Prohibited patterns.** None of [ARCHITECTURE §19](docs/04-architecture/ARCHITECTURE.md#19-prohibited-patterns)'s — a catch-all `Services` folder, a repository per model, whole-request mass assignment, float for money or quantity, `MAX(number) + 1`, direct balance edits — a guard balance changes only through its posting service and the shared `GuardUpdate` helper ([ARCHITECTURE §7](docs/04-architecture/ARCHITECTURE.md#7-posting-services-and-guards)) —, morph links, delete flags, business decisions in model events, role-name checks, provider-specific authentication outside its boundary. Enforced by `tests/Architecture/ProhibitedPatternsTest.php`; raw HTML rendering (WS-09) and DESIGN_SYSTEM GR-04's per-surface greys by ESLint.
- **Commits and secrets.** The Owner's identity only, never AI, model or tool attribution ([AGENTS.md](AGENTS.md#git-commit-attribution)), checked by `scripts/ci/check-commit-messages.sh`; no secret, real data or host detail in the repository ([SECURITY SX-06](docs/05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain)), checked by the secret-like scan.

## Stop

```sh
docker compose down
```

`docker compose down -v` also removes the database and certificate volumes.
