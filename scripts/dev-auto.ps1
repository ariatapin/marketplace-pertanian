param(
    [string]$BindHost = "127.0.0.1",
    [int]$Port = 8000,
    [switch]$WithVite
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $repoRoot

function Test-RunningProcess {
    param([int]$ProcessId)

    if ($ProcessId -le 0) {
        return $false
    }

    try {
        Get-Process -Id $ProcessId -ErrorAction Stop | Out-Null
        return $true
    }
    catch {
        return $false
    }
}

function Stop-IfRunning {
    param([int]$ProcessId)

    if (Test-RunningProcess -ProcessId $ProcessId) {
        Stop-Process -Id $ProcessId -Force -ErrorAction SilentlyContinue
    }
}

function Read-PidFile {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return 0
    }

    $raw = (Get-Content $Path -Raw).Trim()
    if ($raw -match "^\d+$") {
        return [int]$raw
    }

    return 0
}

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    throw "PHP command tidak ditemukan di PATH."
}

$pidDir = Join-Path $repoRoot "storage\framework"
if (-not (Test-Path $pidDir)) {
    New-Item -Path $pidDir -ItemType Directory -Force | Out-Null
}

$schedulePidFile = Join-Path $pidDir "dev-schedule-work.pid"
$vitePidFile = Join-Path $pidDir "dev-vite.pid"

$oldSchedulePid = Read-PidFile -Path $schedulePidFile
if ($oldSchedulePid -gt 0) {
    Stop-IfRunning -ProcessId $oldSchedulePid
}
Remove-Item $schedulePidFile -Force -ErrorAction SilentlyContinue

$scheduleProcess = Start-Process `
    -FilePath $php.Source `
    -ArgumentList @("artisan", "schedule:work", "--no-ansi") `
    -WorkingDirectory $repoRoot `
    -PassThru `
    -WindowStyle Hidden

$scheduleProcess.Id | Set-Content -Path $schedulePidFile
Write-Host ("[dev-auto] schedule:work started (PID {0})" -f $scheduleProcess.Id)

$viteProcess = $null
if ($WithVite) {
    $npm = Get-Command npm -ErrorAction SilentlyContinue
    if (-not $npm) {
        throw "npm command tidak ditemukan di PATH."
    }

    $oldVitePid = Read-PidFile -Path $vitePidFile
    if ($oldVitePid -gt 0) {
        Stop-IfRunning -ProcessId $oldVitePid
    }
    Remove-Item $vitePidFile -Force -ErrorAction SilentlyContinue

    $viteProcess = Start-Process `
        -FilePath $npm.Source `
        -ArgumentList @("run", "dev") `
        -WorkingDirectory $repoRoot `
        -PassThru

    $viteProcess.Id | Set-Content -Path $vitePidFile
    Write-Host ("[dev-auto] npm run dev started (PID {0})" -f $viteProcess.Id)
}

Write-Host ("[dev-auto] serving app at http://{0}:{1}" -f $BindHost, $Port)
Write-Host "[dev-auto] tekan Ctrl+C untuk stop semua proses."

try {
    & $php.Source artisan serve --host=$BindHost --port=$Port
}
finally {
    Stop-IfRunning -ProcessId $scheduleProcess.Id
    Remove-Item $schedulePidFile -Force -ErrorAction SilentlyContinue

    if ($viteProcess) {
        Stop-IfRunning -ProcessId $viteProcess.Id
        Remove-Item $vitePidFile -Force -ErrorAction SilentlyContinue
    }
}
