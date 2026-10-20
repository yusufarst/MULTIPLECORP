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
docker compose run --rm node npm run typecheck
docker compose exec app php artisan test
docker compose exec app composer audit --locked
docker compose run --rm node npm audit
```

They are the lint (TL-01), the typecheck (TL-02), the feature and integration tests (TL-04, TL-05) and the dependency audits (TL-14) of [TEST_STRATEGY](docs/08-testing/TEST_STRATEGY.md#2-test-layers).

## Build

```sh
docker compose run --rm node npm run build
docker compose exec app sh scripts/ci/production-check.sh
docker compose exec app composer install
```

The first command is the frontend production build; the second builds the framework's production caches and checks the production configuration — debug off, no test route, no development dependency (TL-13). It removes the development packages, which the third command restores.

## Stop

```sh
docker compose down
```

`docker compose down -v` also removes the database and certificate volumes.
