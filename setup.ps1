# Hospital Queue Database Setup
# Usage:
#   Copy .env.example to .env, fill in values, then run:
#   .\setup.ps1

$ErrorActionPreference = 'Stop'

$envFile = Join-Path $PSScriptRoot '.env'
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        if ($_ -match '^\s*#' -or $_ -match '^\s*$') { return }
        $name, $value = $_ -split '=', 2
        if ($name -and $null -ne $value) {
            Set-Item -Path "Env:$($name.Trim())" -Value $value.Trim()
        }
    }
}

$Provider = if ($env:DB_PROVIDER) { $env:DB_PROVIDER.ToLower() } else { 'local' }
$Psql = if ($env:PSQL_PATH) { $env:PSQL_PATH } else { 'C:\Program Files\PostgreSQL\18\bin\psql.exe' }
$DbUser = if ($env:PGUSER) { $env:PGUSER } else { 'postgres' }
$DbHost = if ($env:PGHOST) { $env:PGHOST } else { '127.0.0.1' }
$DbPort = if ($env:PGPORT) { $env:PGPORT } else { '5432' }
$DbName = if ($env:PGDATABASE) { $env:PGDATABASE } else { 'hospital_queue' }

if (-not (Test-Path $Psql)) {
    throw "psql not found at $Psql. Adjust PSQL_PATH in .env."
}

if (-not $env:PGPASSWORD) {
    $secure = Read-Host "Enter PostgreSQL password for user $DbUser" -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $env:PGPASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
}

if ($Provider -eq 'neon') {
    $env:PGSSLMODE = if ($env:PGSSLMODE) { $env:PGSSLMODE } else { 'require' }
}

function Invoke-PsqlFile {
    param(
        [string]$Database,
        [string]$FilePath
    )
    Write-Host "Running $FilePath ..."
    & $Psql -h $DbHost -p $DbPort -U $DbUser -d $Database -v ON_ERROR_STOP=1 -f $FilePath
    if ($LASTEXITCODE -ne 0) { throw "Failed executing $FilePath" }
}

if ($Provider -eq 'neon') {
    Write-Host "Neon mode: using existing database '$DbName' (skipping CREATE DATABASE)."
} else {
    $dbExists = & $Psql -h $DbHost -p $DbPort -U $DbUser -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$DbName';"
    if ($dbExists -match '1') {
        Write-Host "Database '$DbName' already exists. Skipping creation."
    } else {
        Invoke-PsqlFile -Database 'postgres' -FilePath (Join-Path $PSScriptRoot 'schema\01_create_database.sql')
    }
}

Invoke-PsqlFile -Database $DbName -FilePath (Join-Path $PSScriptRoot 'schema\02_schema.sql')
Invoke-PsqlFile -Database $DbName -FilePath (Join-Path $PSScriptRoot 'schema\03_functions_triggers.sql')
Invoke-PsqlFile -Database $DbName -FilePath (Join-Path $PSScriptRoot 'schema\04_auth_users.sql')

Write-Host ""
Write-Host "Database '$DbName' is ready on $Provider."
if ($env:DATABASE_URL) {
    Write-Host "Connection URL: $($env:DATABASE_URL)"
} else {
    Write-Host "Connect with: psql -h $DbHost -U $DbUser -d $DbName"
}
