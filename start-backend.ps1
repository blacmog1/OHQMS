# ===============================================
# OHAQRS System - Start Backend Server
# ===============================================
# PowerShell script to start PHP backend
# Location: C:\Users\pc\sql\start-backend.ps1

param(
    [int]$Port = 8000,
    [string]$Host = "localhost"
)

Write-Host "╔════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║  OHAQRS Backend Server - Starting...           ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Define PHP path
$phpPath = "C:\Users\pc\sql\tools\php\php.exe"
$projectPath = "C:\Users\pc\sql\patient-queue-system"

# Verify PHP exists
if (-not (Test-Path $phpPath)) {
    Write-Host "❌ ERROR: PHP not found at $phpPath" -ForegroundColor Red
    Write-Host "Please install PHP or update the path in this script." -ForegroundColor Yellow
    exit 1
}

# Verify project path exists
if (-not (Test-Path $projectPath)) {
    Write-Host "❌ ERROR: Project directory not found at $projectPath" -ForegroundColor Red
    exit 1
}

Write-Host "✅ PHP found: $phpPath" -ForegroundColor Green
Write-Host "✅ Project path: $projectPath" -ForegroundColor Green
Write-Host ""

# Check if port is already in use
try {
    $tcpClient = New-Object System.Net.Sockets.TcpClient
    $tcpClient.Connect($Host, $Port)
    Write-Host "⚠️  WARNING: Port $Port is already in use!" -ForegroundColor Yellow
    Write-Host "Trying port $(($Port + 1))..." -ForegroundColor Yellow
    $Port = $Port + 1
    $tcpClient.Close()
} catch {
    # Port is free, good!
}

Write-Host ""
Write-Host "🚀 Starting PHP development server..." -ForegroundColor Green
Write-Host "   Server: http://$Host`:$Port" -ForegroundColor Cyan
Write-Host "   Stop server: Press Ctrl+C" -ForegroundColor Yellow
Write-Host ""

# Start PHP server
cd $projectPath
& $phpPath -S "$Host`:$Port"
