# ===============================================
# OHAQRS System - Start Frontend Server
# ===============================================
# PowerShell script to start React/Vite frontend
# Location: C:\Users\pc\sql\start-frontend.ps1

param(
    [int]$Port = 5173
)

Write-Host "╔════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║  OHAQRS Frontend Server - Starting...          ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

$frontendPath = "C:\Users\pc\sql\patient-queue-system\frontend"

# Verify frontend path exists
if (-not (Test-Path $frontendPath)) {
    Write-Host "❌ ERROR: Frontend directory not found at $frontendPath" -ForegroundColor Red
    exit 1
}

Write-Host "✅ Frontend path: $frontendPath" -ForegroundColor Green
Write-Host ""

# Check if npm/pnpm is available
$npmPath = Get-Command npm -ErrorAction SilentlyContinue
$pnpmPath = Get-Command pnpm -ErrorAction SilentlyContinue

if (-not $npmPath -and -not $pnpmPath) {
    Write-Host "❌ ERROR: npm or pnpm not found!" -ForegroundColor Red
    Write-Host "Please install Node.js from: https://nodejs.org" -ForegroundColor Yellow
    Write-Host "Then restart PowerShell." -ForegroundColor Yellow
    exit 1
}

cd $frontendPath

# Determine package manager
$packageManager = "npm"
if ($pnpmPath) {
    $packageManager = "pnpm"
    Write-Host "✅ Using pnpm" -ForegroundColor Green
} else {
    Write-Host "✅ Using npm" -ForegroundColor Green
}

Write-Host ""

# Check if node_modules exists
if (-not (Test-Path "node_modules")) {
    Write-Host "📦 Installing dependencies..." -ForegroundColor Yellow
    & $packageManager install
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Failed to install dependencies" -ForegroundColor Red
        exit 1
    }
    Write-Host ""
}

Write-Host "🚀 Starting Vite development server..." -ForegroundColor Green
Write-Host "   Server: http://localhost:$Port" -ForegroundColor Cyan
Write-Host "   Stop server: Press Ctrl+C" -ForegroundColor Yellow
Write-Host ""

# Start dev server
& $packageManager run dev
