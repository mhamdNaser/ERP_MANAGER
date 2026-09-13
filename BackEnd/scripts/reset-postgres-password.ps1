# =============================================================================
#  Reset the PostgreSQL superuser password (Windows)
#  -----------------------------------------------------------------------
#  Run ONCE, as Administrator:
#      powershell -ExecutionPolicy Bypass -File .\scripts\reset-postgres-password.ps1
#
#  What it does:
#    1. Backs up pg_hba.conf
#    2. Switches localhost auth to "trust" and restarts PostgreSQL
#    3. Sets the "postgres" password to $NewPassword
#    4. Restores the original pg_hba.conf and restarts again
#
#  The trust window lasts only a few seconds and only covers 127.0.0.1.
# =============================================================================

$ErrorActionPreference = 'Stop'

$PgRoot      = 'C:\Program Files\PostgreSQL\18'
$DataDir     = Join-Path $PgRoot 'data'
$HbaFile     = Join-Path $DataDir 'pg_hba.conf'
$BackupFile  = Join-Path $DataDir 'pg_hba.conf.before-reset'
$Psql        = Join-Path $PgRoot 'bin\psql.exe'
$ServiceName = 'postgresql-x64-18'
$NewPassword = 'Postgres?2026'

if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()
        ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'This script must be run from an elevated (Administrator) PowerShell.'
}

Write-Host '[1/4] Backing up pg_hba.conf ...'
Copy-Item $HbaFile $BackupFile -Force

try {
    Write-Host '[2/4] Enabling temporary trust auth on localhost ...'
    (Get-Content $HbaFile) -replace 'scram-sha-256', 'trust' |
        Set-Content $HbaFile -Encoding utf8
    Restart-Service $ServiceName -Force
    Start-Sleep -Seconds 3

    Write-Host '[3/4] Setting the postgres password ...'
    & $Psql -U postgres -d postgres -h 127.0.0.1 -c "ALTER USER postgres WITH PASSWORD '$NewPassword';"
    if ($LASTEXITCODE -ne 0) { throw "psql failed with exit code $LASTEXITCODE" }
}
finally {
    Write-Host '[4/4] Restoring the original pg_hba.conf ...'
    Copy-Item $BackupFile $HbaFile -Force
    Restart-Service $ServiceName -Force
    Start-Sleep -Seconds 3
}

Write-Host ''
Write-Host "Done. The postgres password is now: $NewPassword"
