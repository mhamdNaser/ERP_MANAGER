\set ON_ERROR_STOP on

-- =============================================================================
--  CND Manager — PostgreSQL provisioning
--  Run as a PostgreSQL superuser (postgres) on the machine that hosts the DB.
--  Identical on the development machine and on the deployment server: the
--  application always connects to PostgreSQL over 127.0.0.1.
--
--  Usage:
--    psql -U postgres -f scripts/postgres-provision.sql
--
--  The passwords below must match the [LOCAL] / [SERVER] database block in .env
-- =============================================================================

-- Roles -----------------------------------------------------------------------
-- cnd_owner    owns the schema; runs migrations and backups
-- cnd_app      the application's day-to-day read/write role
-- cnd_readonly reporting / export only
CREATE ROLE cnd_owner    LOGIN PASSWORD 'CndOwner?2026';
CREATE ROLE cnd_app      LOGIN PASSWORD 'CndApp?2026';
CREATE ROLE cnd_readonly LOGIN PASSWORD 'CndRead?2026';

CREATE DATABASE cnd_manager OWNER cnd_owner ENCODING 'UTF8';

\connect cnd_manager

GRANT CONNECT ON DATABASE cnd_manager TO cnd_app, cnd_readonly;
GRANT USAGE, CREATE ON SCHEMA public TO cnd_owner;
GRANT USAGE ON SCHEMA public TO cnd_app, cnd_readonly;

-- Laravel migrates as cnd_app, so it needs to create objects too.
GRANT CREATE ON SCHEMA public TO cnd_app;

ALTER DEFAULT PRIVILEGES FOR ROLE cnd_owner IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO cnd_app;
ALTER DEFAULT PRIVILEGES FOR ROLE cnd_owner IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO cnd_app;
ALTER DEFAULT PRIVILEGES FOR ROLE cnd_owner IN SCHEMA public
  GRANT SELECT ON TABLES TO cnd_readonly;

ALTER DEFAULT PRIVILEGES FOR ROLE cnd_app IN SCHEMA public
  GRANT SELECT ON TABLES TO cnd_readonly;
ALTER DEFAULT PRIVILEGES FOR ROLE cnd_app IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO cnd_owner;
