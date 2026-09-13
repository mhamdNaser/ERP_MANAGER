# =============================================================================
#  Start the project-owned PostgreSQL cluster (local development)
#  -----------------------------------------------------------------------
#  This project does NOT use the system-wide "postgresql-x64-18" service on
#  port 5432. It runs its own cluster, owned by the current Windows user, on
#  port 5433 — so no Administrator rights are ever needed.
#
#      Data directory : C:\Users\GIS\pgdata-cnd
#      Port           : 5433
#      Superuser      : postgres
#
#  Usage:
#      powershell -ExecutionPolicy Bypass -File .\scripts\start-local-postgres.ps1
#
#  Registered to run at logon via the scheduled task "CND Manager PostgreSQL".
#  Safe to run repeatedly — it exits quietly if the server is already up.
# =============================================================================

$PgCtl   = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
$DataDir = 'C:\Users\GIS\pgdata-cnd'
$LogFile = Join-Path $DataDir 'log\server.log'

if (-not (Test-Path $DataDir)) {
    throw "PostgreSQL data directory not found: $DataDir"
}

& $PgCtl status -D $DataDir 2>&1 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Host 'PostgreSQL (CND Manager, port 5433) is already running.'
    exit 0
}

New-Item -ItemType Directory -Force -Path (Split-Path $LogFile) | Out-Null
& $PgCtl start -D $DataDir -l $LogFile -w
exit $LASTEXITCODE
