\set ON_ERROR_STOP on

-- =============================================================================
--  NCM-manager — local PostgreSQL sandbox provisioning
--  A separate, disposable database on the SAME local Postgres instance as
--  cnd_manager, used to try out risky features (Excel import, backup/restore
--  with attachment bundling) against fake seeded data instead of real data.
--  Reuses the existing cnd_owner/cnd_readonly roles (roles are cluster-wide)
--  and the postgres superuser as the app connection, matching the LOCAL
--  profile's current setup in .env — only the database itself is new.
--
--  Usage (run as the postgres superuser):
--    "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d postgres -f scripts/postgres-provision-sandbox.sql
-- =============================================================================

CREATE DATABASE cnd_manager_sandbox OWNER postgres ENCODING 'UTF8';

\connect cnd_manager_sandbox

GRANT CONNECT ON DATABASE cnd_manager_sandbox TO cnd_owner, cnd_readonly;
GRANT USAGE, CREATE ON SCHEMA public TO cnd_owner;
GRANT USAGE ON SCHEMA public TO cnd_readonly;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO cnd_owner;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO cnd_owner;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
  GRANT SELECT ON TABLES TO cnd_readonly;
