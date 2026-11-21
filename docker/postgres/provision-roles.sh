#!/bin/sh
# The database roles of DATABASE §28 for the local and CI stack (TASK-005; SECURITY H9-01; INFRASTRUCTURE
# §4.4), created by the server's bootstrap superuser and kept on every start:
#
#   multiplecorp_runtime         NOLOGIN group: the per-table privileges the migrations grant
#   multiplecorp_administration  NOLOGIN group: the runtime's privileges and what GuardMaintenance adds
#   DB_USERNAME                  the application runtime's login, a member of multiplecorp_runtime
#   DB_ADMIN_USERNAME            the administration login, a member of multiplecorp_administration
#   DB_MIGRATION_USERNAME        the migration/owner login: it owns the database and every table, and is
#                                used only by migrations, through the `migration` connection
#
# The same script provisions staging's and production's roles (deploy/coolify/compose.yml, TASK-072) with
# DB_ROLES_FOR_TESTS=no: the migration login without CREATEDB, and no test extension. Idempotent: an existing
# role keeps its memberships and receives the configured password.
set -eu

# The test databases' needs — CREATEDB for parallel testing, pgrowlocks for the lock-order tests — locally and in CI only.
case ${DB_ROLES_FOR_TESTS:-yes} in
    yes) createdb=CREATEDB ;;
    no) createdb=NOCREATEDB ;;
    *) echo "provision-roles: DB_ROLES_FOR_TESTS is yes or no" >&2; exit 1 ;;
esac

# Staging and production give the superuser, migration and administration logins as files, by Docker's `_FILE`
# convention — NAME_FILE names the file that holds NAME —, so none is in any container's configuration
# (deploy/coolify/compose.yml; TASK-078's staging findings). Read as the server's image reads POSTGRES_PASSWORD_FILE: the
# whole file, its trailing newlines dropped. The superuser's login is then also psql's, unless PGUSER or PGPASSWORD is given.
for name in DB_SUPERUSER DB_SUPERUSER_PASSWORD DB_MIGRATION_USERNAME DB_MIGRATION_PASSWORD DB_ADMIN_USERNAME DB_ADMIN_PASSWORD; do
    eval "file=\${${name}_FILE:-}"
    if [ -n "$file" ]; then
        if [ ! -r "$file" ]; then
            echo "provision-roles: ${name}_FILE names no readable file" >&2
            exit 1
        fi
        value=$(cat "$file")
        export "$name=$value"
    fi
done
if [ -n "${DB_SUPERUSER_FILE:-}" ] && [ -z "${PGUSER:-}" ]; then
    export PGUSER="$DB_SUPERUSER"
fi
if [ -n "${DB_SUPERUSER_PASSWORD_FILE:-}" ] && [ -z "${PGPASSWORD:-}" ]; then
    export PGPASSWORD="$DB_SUPERUSER_PASSWORD"
fi

for name in DB_DATABASE DB_SUPERUSER DB_USERNAME DB_PASSWORD DB_MIGRATION_USERNAME DB_MIGRATION_PASSWORD DB_ADMIN_USERNAME DB_ADMIN_PASSWORD; do
    eval "value=\${$name:-}"
    if [ -z "$value" ]; then
        echo "provision-roles: $name is not set; copy the DB_ lines of .env.example into .env" >&2
        exit 1
    fi
done

# Each login is a role of its own: the runtime must never be the owner or the superuser.
logins="$DB_SUPERUSER $DB_USERNAME $DB_MIGRATION_USERNAME $DB_ADMIN_USERNAME multiplecorp_runtime multiplecorp_administration"
if [ "$(printf '%s\n' $logins | sort -u | wc -l)" -ne 6 ]; then
    echo "provision-roles: DB_SUPERUSER, DB_USERNAME, DB_MIGRATION_USERNAME and DB_ADMIN_USERNAME must be four different roles, none named multiplecorp_runtime or multiplecorp_administration" >&2
    exit 1
fi

psql -v ON_ERROR_STOP=1 -q \
    -v database="$DB_DATABASE" \
    -v runtime_login="$DB_USERNAME" -v runtime_password="$DB_PASSWORD" \
    -v migration_login="$DB_MIGRATION_USERNAME" -v migration_password="$DB_MIGRATION_PASSWORD" \
    -v admin_login="$DB_ADMIN_USERNAME" -v admin_password="$DB_ADMIN_PASSWORD" -v createdb="$createdb" <<'SQL'
SELECT 'CREATE ROLE multiplecorp_runtime NOLOGIN'
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'multiplecorp_runtime') \gexec
SELECT 'CREATE ROLE multiplecorp_administration NOLOGIN'
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'multiplecorp_administration') \gexec
GRANT multiplecorp_runtime TO multiplecorp_administration;

-- The migration/owner login: CREATEDB locally and in CI only, for the test databases parallel testing uses
-- (DB_ROLES_FOR_TESTS); NOCREATEDB on staging and production.
SELECT format('CREATE ROLE %I LOGIN', :'migration_login')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'migration_login') \gexec
SELECT format('ALTER ROLE %I LOGIN NOSUPERUSER %s NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'migration_login', :'createdb', :'migration_password') \gexec

SELECT format('CREATE ROLE %I LOGIN', :'runtime_login')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'runtime_login') \gexec
SELECT format('ALTER ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'runtime_login', :'runtime_password') \gexec
SELECT format('GRANT multiplecorp_runtime TO %I', :'runtime_login') \gexec

SELECT format('CREATE ROLE %I LOGIN', :'admin_login')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'admin_login') \gexec
SELECT format('ALTER ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'admin_login', :'admin_password') \gexec
SELECT format('GRANT multiplecorp_administration TO %I', :'admin_login') \gexec

-- The owner of the database owns its public schema, so only the migration login creates objects there.
SELECT format('ALTER DATABASE %I OWNER TO %I', :'database', :'migration_login') \gexec
SQL

echo "provision-roles: the roles of DATABASE §28 are in place"

# HO-21 (TASK-072; CONCURRENCY_IDEMPOTENCY TX-06 and its backstops): on staging and production the runtime's login
# defaults to TX-06's 5 s statement timeout, which page reads rely on, and the 15 s idle-in-transaction backstop; every
# longer class raises its own bounds by SET LOCAL (the command envelope; App\Support\Database\Timeouts). Local and CI
# keep the server's defaults, so a loaded test runner never trips a production bound; TL-05's deployment check reads
# them on the stack.
if [ "${DB_ROLES_FOR_TESTS:-yes}" = no ]; then
    psql -v ON_ERROR_STOP=1 -q -v runtime_login="$DB_USERNAME" <<'SQL'
SELECT format('ALTER ROLE %I SET statement_timeout = %L', :'runtime_login', '5s') \gexec
SELECT format('ALTER ROLE %I SET idle_in_transaction_session_timeout = %L', :'runtime_login', '15s') \gexec
SQL
    echo "provision-roles: the runtime login's timeouts of HO-21 are in place"
    exit 0
fi

# HO-13's lock-order tests read the row locks a command actually holds through the contrib extension
# pgrowlocks, which only a superuser installs (TASK-013): in template1, which every test database is created
# from, and in each test database already created. Local and CI only — never the application's own database
# here, never production.
for test_database in template1 $(psql -At -c "SELECT datname FROM pg_database WHERE datname LIKE '${DB_DATABASE}\_test\_%'"); do
    psql -v ON_ERROR_STOP=1 -q -d "$test_database" -c 'CREATE EXTENSION IF NOT EXISTS pgrowlocks'
done

echo "provision-roles: pgrowlocks is in place for the test databases"
