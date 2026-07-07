# Hospital Queue Database Setup
# Usage:
#   $env:PGPASSWORD = 'your_postgres_password'
#   .\setup.ps1
#
# Or run without env var to be prompted securely:
#   .\setup.ps1

$ErrorActionPreference = 'Stop'

$Psql = 'C:\Program Files\PostgreSQL\18\bin\psql.exe'
$DbUser = 'postgres'
$DbHost = '127.0.0.1'
$DbPort = '5432'
$DbName = 'hospital_queue'

if (-not (Test-Path $Psql)) {
    throw "psql not found at $Psql. Adjust the path in setup.ps1."
}

if (-not $env:PGPASSWORD) {
    $secure = Read-Host 'Enter PostgreSQL password for user postgres' -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $env:PGPASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
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

# Create database (skip if already exists)
$dbExists = & $Psql -h $DbHost -p $DbPort -U $DbUser -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$DbName';"
if ($dbExists -match '1') {
    Write-Host "Database '$DbName' already exists. Skipping creation."
} else {
    Invoke-PsqlFile -Database 'postgres' -FilePath (Join-Path $PSScriptRoot 'schema\01_create_database.sql')
}

Invoke-PsqlFile -Database $DbName -FilePath (Join-Path $PSScriptRoot 'schema\02_schema.sql')
Invoke-PsqlFile -Database $DbName -FilePath (Join-Path $PSScriptRoot 'schema\03_functions_triggers.sql')

Write-Host ""
Write-Host "Database '$DbName' is ready."
Write-Host "Connect with: psql -h $DbHost -U $DbUser -d $DbName"
