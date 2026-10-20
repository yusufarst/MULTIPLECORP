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
# Local and CI only: production's roles are provisioned by its deployment (TASK-072), under the same
# group names. Idempotent: an existing role keeps its memberships and receives the configured password.
set -eu

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
    -v admin_login="$DB_ADMIN_USERNAME" -v admin_password="$DB_ADMIN_PASSWORD" <<'SQL'
SELECT 'CREATE ROLE multiplecorp_runtime NOLOGIN'
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'multiplecorp_runtime') \gexec
SELECT 'CREATE ROLE multiplecorp_administration NOLOGIN'
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'multiplecorp_administration') \gexec
GRANT multiplecorp_runtime TO multiplecorp_administration;

-- The migration/owner login: CREATEDB locally and in CI only, for the test databases parallel testing uses.
SELECT format('CREATE ROLE %I LOGIN', :'migration_login')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'migration_login') \gexec
SELECT format('ALTER ROLE %I LOGIN NOSUPERUSER CREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'migration_login', :'migration_password') \gexec

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
