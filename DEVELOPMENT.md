# Development

How to start, test and build MULTIPLECORP locally. Everything runs in Docker Compose — the application's PHP runtime behind Nginx, PostgreSQL, Valkey and Node.js, each pinned in [`docker-compose.yml`](docker-compose.yml) and [`docker/php/Dockerfile`](docker/php/Dockerfile) — and CI runs the same commands on a fresh runner ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)). The versions are recorded in [TOOLCHAIN](docs/00-governance/TOOLCHAIN.md).

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
```

On Windows (PowerShell), the same commands, with the first two lines replaced by:

```powershell
Copy-Item .env.example .env
```

Docker Desktop maps file ownership itself, so `HOST_UID` and `HOST_GID` are not needed there; on Linux they let the containers write the project files as your user.

The application answers at <https://localhost:8443> — with the framework's "not found" page until the first routes exist — and <http://localhost:8080> redirects there. Its certificate is signed by a throwaway local authority kept in the `dev-certs` volume, so the browser warns about it; accept the warning for local work only.

## Test

With the stack started:

```sh
docker compose exec app vendor/bin/pint --test
docker compose run --rm node npm run lint
docker compose exec app vendor/bin/phpstan analyse
docker compose run --rm node npm run typecheck
docker compose exec app php artisan test
docker compose run --rm node npm run test:lint-rules
docker compose run --rm node npm run scan:secrets
docker compose run --rm node node scripts/ci/check-plan.mjs
docker compose exec app composer audit --locked
docker compose run --rm node npm audit
sh scripts/ci/check-commit-messages.sh origin/main..HEAD
```

They are the lint (TL-01: Pint, ESLint), the static analysis and typecheck (TL-02: Larastan/PHPStan, the TypeScript compiler), the feature, integration and architecture tests (TL-04, TL-05, and SM-03's architecture tests in `tests/Architecture/`), the tests of the project's ESLint rules, the secret-like scan (SX-06), the Task plan's integrity check, the dependency audits (TL-14) and the commit-message check of [TEST_STRATEGY](docs/08-testing/TEST_STRATEGY.md#2-test-layers); the last one runs on the machine, in Git Bash on Windows.

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
- **Money and quantity.** Exact decimals, never PHP float: money `numeric(18,2)` and base-unit quantities `numeric(18,3)`, carried as decimal strings by the `Money` and `Quantity` value objects of `app/Support` — [EXECUTION_CONTEXT invariant 1](docs/11-tasks/EXECUTION_CONTEXT.md#project-invariants), [DATABASE §17](docs/04-architecture/DATABASE/s13-17-history-scope-types.md#17-money-quantity-and-date-types), [ARCHITECTURE §6](docs/04-architecture/ARCHITECTURE.md#6-domain-model-value-objects-and-calculators).
- **Naming.** Code, identifiers, tables and columns in English ([AICWDF §4B.3](docs/00-governance/sources/AI_First_Complex_WebApp_Task_Framework_v4.3_EN.md#4b3-technical-naming-remains-english)); Indonesian only in user-facing copy, through the localization keys; tables and columns as DATABASE names them; migration file names prefixed by their module ([ARCHITECTURE §4](docs/04-architecture/ARCHITECTURE.md#4-code-organization)).
- **Prohibited patterns.** None of [ARCHITECTURE §19](docs/04-architecture/ARCHITECTURE.md#19-prohibited-patterns)'s — a catch-all `Services` folder, a repository per model, whole-request mass assignment, float for money or quantity, `MAX(number) + 1`, direct balance edits, morph links, delete flags, business decisions in model events, role-name checks, provider-specific authentication outside its boundary. Enforced by `tests/Architecture/ProhibitedPatternsTest.php`; raw HTML rendering (WS-09) and DESIGN_SYSTEM GR-04's per-surface greys by ESLint.
- **Commits and secrets.** The Owner's identity only, never AI, model or tool attribution ([AGENTS.md](AGENTS.md#git-commit-attribution)), checked by `scripts/ci/check-commit-messages.sh`; no secret, real data or host detail in the repository ([SECURITY SX-06](docs/05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain)), checked by the secret-like scan.

## Stop

```sh
docker compose down
```

`docker compose down -v` also removes the database and certificate volumes.
