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

-- TX-06 and the backstop of every class (CONCURRENCY_IDEMPOTENCY §3; HO-21): the runtime login's defaults in the
-- application's database — a page read's 5 s statement timeout, which every other class overrides with SET LOCAL, and
-- 15 s idle in a transaction. The test databases keep the server's defaults.
SELECT format('ALTER ROLE %I IN DATABASE %I SET statement_timeout = %L', :'runtime_login', :'database', '5s') \gexec
SELECT format('ALTER ROLE %I IN DATABASE %I SET idle_in_transaction_session_timeout = %L', :'runtime_login', :'database', '15s') \gexec
SQL

echo "provision-roles: the roles of DATABASE §28 are in place"

if [ "${DB_ROLES_FOR_TESTS:-yes}" = no ]; then
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
