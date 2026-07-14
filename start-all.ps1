# ===============================================
# OHAQRS System - Complete Startup
# ===============================================
# PowerShell script to start both backend and frontend
# Location: C:\Users\pc\sql\start-all.ps1

Write-Host "╔═══════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║           OHAQRS Hospital Queue System - Startup              ║" -ForegroundColor Cyan
Write-Host "╚═══════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

Write-Host "📋 System Check..." -ForegroundColor Yellow

# Check PHP
$phpPath = "C:\Users\pc\sql\tools\php\php.exe"
if (Test-Path $phpPath) {
    Write-Host "✅ PHP 8.2 found" -ForegroundColor Green
} else {
    Write-Host "❌ PHP not found" -ForegroundColor Red
    exit 1
}

# Check Node.js
$npmPath = Get-Command npm -ErrorAction SilentlyContinue
if ($npmPath) {
    Write-Host "✅ Node.js/npm found" -ForegroundColor Green
} else {
    Write-Host "⚠️  Node.js/npm not found - frontend won't start" -ForegroundColor Yellow
    Write-Host "   Install from: https://nodejs.org" -ForegroundColor Yellow
}

# Check .env
$envFile = "C:\Users\pc\sql\.env"
if (Test-Path $envFile) {
    Write-Host "✅ Configuration (.env) found" -ForegroundColor Green
} else {
    Write-Host "❌ Configuration (.env) not found" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🚀 Starting Services..." -ForegroundColor Green
Write-Host ""

Write-Host "Instructions:" -ForegroundColor Yellow
Write-Host "1. Backend will start in THIS window" -ForegroundColor White
Write-Host "2. Open ANOTHER PowerShell window" -ForegroundColor White
Write-Host "3. Run: C:\Users\pc\sql\start-frontend.ps1" -ForegroundColor White
Write-Host ""
Write-Host "Or run in separate windows:" -ForegroundColor Cyan
Write-Host "  Window 1: .\start-backend.ps1" -ForegroundColor Cyan
Write-Host "  Window 2: .\start-frontend.ps1" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press Enter to start backend..." -ForegroundColor Yellow
$null = Read-Host

# Start backend
C:\Users\pc\sql\start-backend.ps1
