/*
================================================================================
QGIS / PostGIS database bootstrap for Layer Version Manager
================================================================================

Use this file in three levels:

1) SERVER LEVEL, run once on the PostgreSQL server while connected to postgres.
   This creates login users and group roles before restore, fixing errors like:
   ERROR: role "gis_admin" does not exist

2) postgres DATABASE LEVEL, run once while connected to database postgres.
   This only grants the basic ability to connect to postgres when needed.

3) TARGET DATABASE LEVEL, run once inside each GIS database.
   This creates schemas, audit objects, LVM plugin objects, privileges, and
   optional helper functions.

Important:
- No event trigger is used.
- No automatic created_at / updated_at / created_by / updated_by columns are
  added to new QGIS tables.
- If a layer needs created_at / updated_at, create those fields in QGIS when
  the layer is created.
- Audit tracking is separate and works through row triggers that write to
  audit.user_action_logs.
================================================================================
*/


/* =============================================================================
   A) SERVER LEVEL - run once on the PostgreSQL server
   Connect to: postgres
   Run as: postgres superuser
============================================================================= */

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'qgis_admin') THEN
        CREATE ROLE qgis_admin NOLOGIN INHERIT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'qgis_editor') THEN
        CREATE ROLE qgis_editor NOLOGIN INHERIT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'qgis_viewer') THEN
        CREATE ROLE qgis_viewer NOLOGIN INHERIT;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'ibrahem') THEN
        CREATE ROLE ibrahem LOGIN PASSWORD 'Ibrahem@2025?data';
    ELSE
        ALTER ROLE ibrahem WITH LOGIN PASSWORD 'Ibrahem@2025?data';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'mosaab') THEN
        CREATE ROLE mosaab LOGIN PASSWORD 'Mosaab@2025?data';
    ELSE
        ALTER ROLE mosaab WITH LOGIN PASSWORD 'Mosaab@2025?data';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'mkhalil') THEN
        CREATE ROLE mkhalil LOGIN PASSWORD 'Mkhalil@GIS2026';
    ELSE
        ALTER ROLE mkhalil WITH LOGIN PASSWORD 'Mkhalil@GIS2026';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'ahmadr') THEN
        CREATE ROLE ahmadr LOGIN PASSWORD 'Ahmad@gis?2026';
    ELSE
        ALTER ROLE ahmadr WITH LOGIN PASSWORD 'Ahmad@gis?2026';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'sariya') THEN
        CREATE ROLE sariya LOGIN PASSWORD 'Sariya@GIS?2265';
    ELSE
        ALTER ROLE sariya WITH LOGIN PASSWORD 'Sariya@GIS?2265';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'gis_admin') THEN
        CREATE ROLE gis_admin LOGIN PASSWORD 'Naser@gis?2026';
    ELSE
        ALTER ROLE gis_admin WITH LOGIN PASSWORD 'Naser@gis?2026';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'naser') THEN
        CREATE ROLE naser LOGIN PASSWORD 'Naser@GIS2026';
    ELSE
        ALTER ROLE naser WITH LOGIN PASSWORD 'Naser@GIS2026';
    END IF;
END $$;

GRANT qgis_admin  TO ibrahem, mosaab, sariya, gis_admin, naser;
GRANT qgis_editor TO mkhalil, ahmadr;

ALTER ROLE ibrahem   WITH INHERIT CREATEROLE CREATEDB LOGIN;
ALTER ROLE mosaab    WITH INHERIT CREATEROLE CREATEDB LOGIN;
ALTER ROLE sariya    WITH INHERIT CREATEROLE CREATEDB LOGIN;
ALTER ROLE gis_admin WITH INHERIT CREATEROLE CREATEDB LOGIN;
ALTER ROLE naser     WITH INHERIT CREATEROLE CREATEDB LOGIN;
ALTER ROLE mkhalil   WITH INHERIT LOGIN;
ALTER ROLE ahmadr    WITH INHERIT LOGIN;


/* =============================================================================
   B) postgres DATABASE LEVEL - optional but useful
   Connect to: postgres
============================================================================= */

GRANT CONNECT, TEMPORARY ON DATABASE postgres
TO qgis_admin, qgis_editor, qgis_viewer, ibrahem, mosaab, sariya, gis_admin, naser, mkhalil, ahmadr;


/* =============================================================================
   C) TARGET DATABASE LEVEL - run inside each GIS database
   Connect to: the target GIS database, for example aleppo_db
============================================================================= */

CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;

CREATE SCHEMA IF NOT EXISTS gis_data     AUTHORIZATION gis_admin;
CREATE SCHEMA IF NOT EXISTS gis_public   AUTHORIZATION gis_admin;
CREATE SCHEMA IF NOT EXISTS gis_projects AUTHORIZATION gis_admin;
CREATE SCHEMA IF NOT EXISTS gis_project  AUTHORIZATION gis_admin;
CREATE SCHEMA IF NOT EXISTS audit        AUTHORIZATION gis_admin;
CREATE SCHEMA IF NOT EXISTS lvm          AUTHORIZATION gis_admin;

REVOKE ALL ON SCHEMA gis_data, gis_public, gis_projects, gis_project, audit, lvm FROM PUBLIC;
REVOKE ALL ON ALL TABLES    IN SCHEMA gis_data, gis_public, gis_projects, gis_project, audit, lvm FROM PUBLIC;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA gis_data, gis_public, gis_projects, gis_project, audit, lvm FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA gis_data, gis_public, gis_projects, gis_project, audit, lvm FROM PUBLIC;

DO $$
BEGIN
    EXECUTE format(
        'GRANT CONNECT, TEMPORARY ON DATABASE %I TO qgis_admin, qgis_editor, qgis_viewer, ibrahem, mosaab, sariya, gis_admin, naser, mkhalil, ahmadr',
        current_database()
    );
END $$;


/* =============================================================================
   Core privileges
============================================================================= */

GRANT USAGE ON SCHEMA gis_data, gis_public, gis_projects, gis_project TO qgis_viewer;
GRANT SELECT ON ALL TABLES IN SCHEMA gis_data, gis_public, gis_projects, gis_project TO qgis_viewer;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA gis_data, gis_public, gis_projects, gis_project TO qgis_viewer;

GRANT USAGE, CREATE ON SCHEMA gis_data, gis_public, gis_projects, gis_project TO qgis_editor;
GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER
ON ALL TABLES IN SCHEMA gis_data, gis_public, gis_projects, gis_project TO qgis_editor;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA gis_data, gis_public, gis_projects, gis_project TO qgis_editor;
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA gis_data, gis_public, gis_projects, gis_project TO qgis_editor;

GRANT USAGE, CREATE ON SCHEMA gis_data, gis_public, gis_projects, gis_project, audit, lvm TO qgis_admin;
GRANT ALL PRIVILEGES ON ALL TABLES    IN SCHEMA gis_data, gis_public, gis_projects, gis_project, audit, lvm TO qgis_admin;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA gis_data, gis_public, gis_projects, gis_project, audit, lvm TO qgis_admin;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA gis_data, gis_public, gis_projects, gis_project, audit, lvm TO qgis_admin;


/* =============================================================================
   Default privileges for future objects
============================================================================= */

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA gis_data, gis_public, gis_projects, gis_project
GRANT SELECT ON TABLES TO qgis_viewer;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA gis_data, gis_public, gis_projects, gis_project
GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER ON TABLES TO qgis_editor;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA gis_data, gis_public, gis_projects, gis_project
GRANT ALL ON TABLES TO qgis_admin;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA gis_data, gis_public, gis_projects, gis_project
GRANT USAGE, SELECT ON SEQUENCES TO qgis_viewer;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA gis_data, gis_public, gis_projects, gis_project
GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO qgis_editor;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA gis_data, gis_public, gis_projects, gis_project
GRANT ALL ON SEQUENCES TO qgis_admin;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA gis_data, gis_public, gis_projects, gis_project
GRANT EXECUTE ON FUNCTIONS TO qgis_editor, qgis_admin;


/* =============================================================================
   Audit log objects
============================================================================= */

CREATE TABLE IF NOT EXISTS audit.user_action_logs (
    id bigserial PRIMARY KEY,
    action_type text NOT NULL CHECK (action_type IN ('INSERT', 'UPDATE', 'DELETE')),
    table_schema text NOT NULL,
    table_name text NOT NULL,
    record_pk text,
    changed_by text NOT NULL DEFAULT session_user,
    old_data jsonb,
    new_data jsonb,
    changed_at timestamptz NOT NULL DEFAULT now(),
    txid bigint NOT NULL DEFAULT txid_current(),
    client_addr inet DEFAULT inet_client_addr(),
    application_name text DEFAULT current_setting('application_name', true)
);

ALTER TABLE audit.user_action_logs
    ADD COLUMN IF NOT EXISTS txid bigint DEFAULT txid_current(),
    ADD COLUMN IF NOT EXISTS client_addr inet DEFAULT inet_client_addr(),
    ADD COLUMN IF NOT EXISTS application_name text DEFAULT current_setting('application_name', true);

UPDATE audit.user_action_logs
SET txid = txid_current()
WHERE txid IS NULL;

ALTER TABLE audit.user_action_logs
    ALTER COLUMN txid SET NOT NULL,
    ALTER COLUMN txid SET DEFAULT txid_current(),
    ALTER COLUMN client_addr SET DEFAULT inet_client_addr(),
    ALTER COLUMN application_name SET DEFAULT current_setting('application_name', true);

CREATE INDEX IF NOT EXISTS idx_user_action_logs_layer_time
ON audit.user_action_logs(table_schema, table_name, changed_at DESC);

CREATE INDEX IF NOT EXISTS idx_user_action_logs_changed_by
ON audit.user_action_logs(changed_by);

CREATE INDEX IF NOT EXISTS idx_user_action_logs_record_pk
ON audit.user_action_logs(table_schema, table_name, record_pk);

CREATE OR REPLACE FUNCTION audit.get_record_pk(row_data jsonb)
RETURNS text
LANGUAGE plpgsql
IMMUTABLE
AS $$
BEGIN
    IF row_data ? 'id' THEN
        RETURN row_data ->> 'id';
    ELSIF row_data ? 'fid' THEN
        RETURN row_data ->> 'fid';
    ELSIF row_data ? 'gid' THEN
        RETURN row_data ->> 'gid';
    ELSIF row_data ? 'objectid' THEN
        RETURN row_data ->> 'objectid';
    END IF;

    RETURN NULL;
END;
$$;

CREATE OR REPLACE FUNCTION audit.log_layer_changes()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, audit
AS $$
DECLARE
    v_old jsonb;
    v_new jsonb;
    v_pk text;
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_new := to_jsonb(NEW);
        v_pk := audit.get_record_pk(v_new);

        INSERT INTO audit.user_action_logs(action_type, table_schema, table_name, record_pk, changed_by, old_data, new_data)
        VALUES ('INSERT', TG_TABLE_SCHEMA, TG_TABLE_NAME, v_pk, session_user, NULL, v_new);

        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        v_old := to_jsonb(OLD);
        v_new := to_jsonb(NEW);
        v_pk := COALESCE(audit.get_record_pk(v_new), audit.get_record_pk(v_old));

        INSERT INTO audit.user_action_logs(action_type, table_schema, table_name, record_pk, changed_by, old_data, new_data)
        VALUES ('UPDATE', TG_TABLE_SCHEMA, TG_TABLE_NAME, v_pk, session_user, v_old, v_new);

        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        v_old := to_jsonb(OLD);
        v_pk := audit.get_record_pk(v_old);

        INSERT INTO audit.user_action_logs(action_type, table_schema, table_name, record_pk, changed_by, old_data, new_data)
        VALUES ('DELETE', TG_TABLE_SCHEMA, TG_TABLE_NAME, v_pk, session_user, v_old, NULL);

        RETURN OLD;
    END IF;

    RETURN NULL;
END;
$$;

CREATE OR REPLACE FUNCTION lvm.attach_audit_to_table(target_table regclass)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, audit, lvm
AS $$
BEGIN
    EXECUTE format('DROP TRIGGER IF EXISTS trg_lvm_audit_changes ON %s', target_table);
    EXECUTE format(
        'CREATE TRIGGER trg_lvm_audit_changes AFTER INSERT OR UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION audit.log_layer_changes()',
        target_table
    );
END;
$$;

CREATE OR REPLACE FUNCTION lvm.attach_audit_to_schema(target_schema text)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, audit, lvm
AS $$
DECLARE
    r record;
BEGIN
    FOR r IN
        SELECT c.oid::regclass AS table_regclass
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = target_schema
          AND c.relkind IN ('r', 'p')
    LOOP
        PERFORM lvm.attach_audit_to_table(r.table_regclass);
    END LOOP;
END;
$$;

DROP EVENT TRIGGER IF EXISTS trg_auto_timestamps;
DROP EVENT TRIGGER IF EXISTS trg_auto_audit_gis_data_tables;
DROP EVENT TRIGGER IF EXISTS trg_auto_created_updated_fields;
DROP EVENT TRIGGER IF EXISTS trg_apply_created_updated_fields;
DROP FUNCTION IF EXISTS gis_data.auto_add_timestamp_columns() CASCADE;
DROP FUNCTION IF EXISTS gis_data.apply_audit_to_new_gis_data_tables() CASCADE;
DROP FUNCTION IF EXISTS gis_data.set_audit_fields() CASCADE;
DROP FUNCTION IF EXISTS gis_data.apply_qgis_audit_to_table(regclass) CASCADE;
DROP FUNCTION IF EXISTS public.add_audit_trigger_on_create() CASCADE;
DROP FUNCTION IF EXISTS public.apply_created_updated_fields(regclass) CASCADE;
DROP FUNCTION IF EXISTS public.auto_apply_created_updated_fields() CASCADE;
DROP FUNCTION IF EXISTS public.set_audit_fields() CASCADE;
DROP FUNCTION IF EXISTS public.set_created_updated_fields() CASCADE;

SELECT lvm.attach_audit_to_schema('gis_data');


/* =============================================================================
   Plugin-compatible audit view and restore function
============================================================================= */

DROP VIEW IF EXISTS lvm.v_version_clusters;
DROP VIEW IF EXISTS audit.v_layer_versions CASCADE;

CREATE OR REPLACE VIEW audit.v_layer_versions AS
SELECT
    id AS version_id,
    id,
    action_type,
    table_schema,
    table_name,
    table_name AS layer_name,
    record_pk,
    changed_by,
    changed_at,
    old_data,
    new_data,
    txid,
    client_addr,
    application_name
FROM audit.user_action_logs
WHERE action_type IN ('INSERT', 'UPDATE', 'DELETE')
ORDER BY changed_at DESC, id DESC;

COMMENT ON VIEW audit.v_layer_versions IS 'Layer Version Manager source view. layer_name is kept for plugin compatibility.';

CREATE OR REPLACE FUNCTION audit.restore_layer_version(version_id_input bigint)
RETURNS text
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, audit, gis_data, public
AS $$
DECLARE
    v_log record;
    v_target regclass;
    v_restore jsonb;
    v_pk text;
    v_pk_col text;
BEGIN
    SELECT * INTO v_log
    FROM audit.user_action_logs
    WHERE id = version_id_input;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Version id % was not found', version_id_input;
    END IF;

    IF v_log.old_data IS NULL THEN
        RETURN 'No old_data exists for this version. Nothing restored.';
    END IF;

    v_restore := v_log.old_data;
    v_pk := audit.get_record_pk(v_restore);

    IF v_pk IS NULL THEN
        RAISE EXCEPTION 'Cannot restore version %, no id, fid, gid, or objectid found in old_data', version_id_input;
    END IF;

    IF v_restore ? 'id' THEN
        v_pk_col := 'id';
    ELSIF v_restore ? 'fid' THEN
        v_pk_col := 'fid';
    ELSIF v_restore ? 'gid' THEN
        v_pk_col := 'gid';
    ELSE
        v_pk_col := 'objectid';
    END IF;

    v_target := format('%I.%I', v_log.table_schema, v_log.table_name)::regclass;

    EXECUTE format('DELETE FROM %s WHERE %I::text = $1', v_target, v_pk_col)
    USING v_pk;

    EXECUTE format('INSERT INTO %s SELECT * FROM jsonb_populate_record(NULL::%s, $1)', v_target, v_target)
    USING v_restore;

    RETURN format('Version %s restored into %s.%s', version_id_input, v_log.table_schema, v_log.table_name);
END;
$$;


/* =============================================================================
   Layer Version Manager plugin tables
============================================================================= */

CREATE TABLE IF NOT EXISTS lvm.policy_rules (
    id bigserial PRIMARY KEY,
    layer_name text,
    rule_name text NOT NULL DEFAULT 'Default policy',
    blocked_actions text[] NOT NULL DEFAULT ARRAY[]::text[],
    max_versions integer,
    is_enabled boolean NOT NULL DEFAULT true,
    created_by text NOT NULL DEFAULT session_user,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lvm.preview_sessions (
    id bigserial PRIMARY KEY,
    scenario_name text NOT NULL,
    layer_name text NOT NULL,
    db_user text NOT NULL DEFAULT session_user,
    selected_version_ids bigint[] NOT NULL DEFAULT ARRAY[]::bigint[],
    impact_score numeric(12, 3) NOT NULL DEFAULT 0,
    conflict_count integer NOT NULL DEFAULT 0,
    explain_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    status text NOT NULL DEFAULT 'draft',
    reviewer text,
    review_notes text,
    tags text[],
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lvm.revert_patches (
    id bigserial PRIMARY KEY,
    scenario_name text NOT NULL,
    layer_name text NOT NULL,
    patch_sql text NOT NULL,
    selected_version_ids bigint[] NOT NULL DEFAULT ARRAY[]::bigint[],
    created_by text NOT NULL DEFAULT session_user,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lvm.layer_snapshots (
    id bigserial PRIMARY KEY,
    layer_name text NOT NULL,
    scenario_name text NOT NULL,
    snapshot_reason text NOT NULL DEFAULT 'pre_save',
    selected_version_ids bigint[] NOT NULL DEFAULT ARRAY[]::bigint[],
    created_by text NOT NULL DEFAULT session_user,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_lvm_preview_sessions_layer_time
ON lvm.preview_sessions(layer_name, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_lvm_revert_patches_layer_time
ON lvm.revert_patches(layer_name, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_lvm_layer_snapshots_layer_time
ON lvm.layer_snapshots(layer_name, created_at DESC);

CREATE OR REPLACE VIEW lvm.v_version_clusters AS
WITH ordered_versions AS (
    SELECT
        version_id,
        layer_name,
        changed_by,
        changed_at,
        CASE
            WHEN lag(changed_at) OVER w IS NULL THEN 1
            WHEN changed_at - lag(changed_at) OVER w > interval '30 minutes' THEN 1
            ELSE 0
        END AS starts_new_cluster
    FROM audit.v_layer_versions
    WINDOW w AS (PARTITION BY layer_name, changed_by ORDER BY changed_at, version_id)
),
clustered_versions AS (
    SELECT
        *,
        sum(starts_new_cluster) OVER (
            PARTITION BY layer_name, changed_by
            ORDER BY changed_at, version_id
        ) AS cluster_seq
    FROM ordered_versions
)
SELECT
    layer_name,
    changed_by,
    cluster_seq,
    min(changed_at) AS cluster_start_at,
    max(changed_at) AS cluster_end_at,
    count(*)::integer AS versions_count,
    array_agg(version_id ORDER BY changed_at, version_id) AS version_ids
FROM clustered_versions
GROUP BY layer_name, changed_by, cluster_seq;


/* =============================================================================
   Final audit and LVM privileges
============================================================================= */

REVOKE ALL ON SCHEMA audit, lvm FROM PUBLIC, qgis_viewer, qgis_editor;
REVOKE ALL ON ALL TABLES    IN SCHEMA audit, lvm FROM PUBLIC, qgis_viewer, qgis_editor;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA audit, lvm FROM PUBLIC, qgis_viewer, qgis_editor;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA audit, lvm FROM PUBLIC, qgis_viewer, qgis_editor;

GRANT USAGE ON SCHEMA audit, lvm TO qgis_admin;
GRANT ALL PRIVILEGES ON ALL TABLES    IN SCHEMA audit, lvm TO qgis_admin;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA audit, lvm TO qgis_admin;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA audit, lvm TO qgis_admin;

GRANT SELECT ON audit.v_layer_versions TO qgis_admin;
GRANT EXECUTE ON FUNCTION audit.restore_layer_version(bigint) TO qgis_admin;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA audit, lvm
GRANT ALL ON TABLES TO qgis_admin;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA audit, lvm
GRANT ALL ON SEQUENCES TO qgis_admin;

ALTER DEFAULT PRIVILEGES FOR ROLE gis_admin IN SCHEMA audit, lvm
GRANT ALL ON FUNCTIONS TO qgis_admin;


/* =============================================================================
   Default privileges for objects created by QGIS login users
============================================================================= */

DO $$
DECLARE
    owner_role text;
    schema_name text;
BEGIN
    FOREACH owner_role IN ARRAY ARRAY['ibrahem', 'mosaab', 'sariya', 'gis_admin', 'naser', 'mkhalil', 'ahmadr']
    LOOP
        FOREACH schema_name IN ARRAY ARRAY['gis_data', 'gis_public', 'gis_projects', 'gis_project']
        LOOP
            EXECUTE format(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA %I GRANT SELECT ON TABLES TO qgis_viewer',
                owner_role,
                schema_name
            );
            EXECUTE format(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA %I GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER ON TABLES TO qgis_editor',
                owner_role,
                schema_name
            );
            EXECUTE format(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA %I GRANT ALL ON TABLES TO qgis_admin',
                owner_role,
                schema_name
            );
            EXECUTE format(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA %I GRANT USAGE, SELECT ON SEQUENCES TO qgis_viewer',
                owner_role,
                schema_name
            );
            EXECUTE format(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA %I GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO qgis_editor',
                owner_role,
                schema_name
            );
            EXECUTE format(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA %I GRANT ALL ON SEQUENCES TO qgis_admin',
                owner_role,
                schema_name
            );
            EXECUTE format(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA %I GRANT EXECUTE ON FUNCTIONS TO qgis_editor, qgis_admin',
                owner_role,
                schema_name
            );
        END LOOP;
    END LOOP;
END $$;


/* =============================================================================
   Optional: apply audit to new QGIS layers after creating/importing them
============================================================================= */

-- For one table:
-- SELECT lvm.attach_audit_to_table('gis_data.my_layer'::regclass);

-- For all current layer tables in gis_data:
-- SELECT lvm.attach_audit_to_schema('gis_data');


/* =============================================================================
   Verification queries
============================================================================= */

SELECT rolname
FROM pg_roles
WHERE rolname IN (
    'qgis_admin', 'qgis_editor', 'qgis_viewer',
    'ibrahem', 'mosaab', 'mkhalil', 'ahmadr', 'sariya', 'gis_admin', 'naser'
)
ORDER BY rolname;

SELECT schema_name
FROM information_schema.schemata
WHERE schema_name IN ('gis_data', 'gis_public', 'gis_projects', 'gis_project', 'audit', 'lvm')
ORDER BY schema_name;

SELECT table_schema, table_name
FROM information_schema.views
WHERE (table_schema, table_name) IN (('audit', 'v_layer_versions'), ('lvm', 'v_version_clusters'))
ORDER BY table_schema, table_name;

SELECT
    n.nspname AS schema_name,
    c.relname AS table_name,
    tg.tgname AS trigger_name
FROM pg_trigger tg
JOIN pg_class c ON c.oid = tg.tgrelid
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'gis_data'
  AND tg.tgname = 'trg_lvm_audit_changes'
ORDER BY c.relname;

SELECT evtname, evtenabled
FROM pg_event_trigger
WHERE evtname = 'trg_auto_audit_gis_data_tables';


/* =============================================================================
   Role table after applying this file
============================================================================= */

/*
| user name | group role  | operational level | audit/lvm access | main permissions |
|-----------|-------------|-------------------|------------------|------------------|
| ibrahem   | qgis_admin  | admin             | yes              | manage GIS schemas, projects, audit, lvm |
| mosaab    | qgis_admin  | admin             | yes              | manage GIS schemas, projects, audit, lvm |
| sariya    | qgis_admin  | admin             | yes              | manage GIS schemas, projects, audit, lvm |
| gis_admin | qgis_admin  | admin/owner       | yes              | object owner and restore-safe role |
| naser     | qgis_admin  | admin             | yes              | manage GIS schemas, projects, audit, lvm |
| mkhalil   | qgis_editor | editor            | no               | create/edit/delete layers and project data |
| ahmadr    | qgis_editor | editor            | no               | create/edit/delete layers and project data |
| viewer users | qgis_viewer | viewer         | no               | read-only on GIS/project schemas |
*/
