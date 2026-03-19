param(
    [string]$ApiBaseUrl = "http://127.0.0.1:8090",
    [bool]$StartLocalPhpServer = $true
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$reportsDir = Join-Path $repoRoot "reports\lecturer-qa"
$jsonReport = Join-Path $reportsDir "lecturer-qa-$timestamp.json"
$mdReport = Join-Path $reportsDir "lecturer-qa-$timestamp.md"

if (-not (Test-Path $reportsDir)) {
    New-Item -ItemType Directory -Path $reportsDir | Out-Null
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw "Required command 'php' was not found in PATH."
}
if (-not (Get-Command python -ErrorAction SilentlyContinue)) {
    throw "Required command 'python' was not found in PATH."
}

$mysqlListening = (netstat -ano | Select-String ":3306").Count -gt 0
if (-not $mysqlListening) {
    throw "MySQL is not listening on port 3306. Start XAMPP MySQL and retry."
}

$phpServerProcess = $null
$needsLocalServer = $ApiBaseUrl -match "^http://127\.0\.0\.1:8090/?$"

try {
    if ($StartLocalPhpServer -and $needsLocalServer) {
        $healthOk = $false
        try {
            $null = Invoke-WebRequest -Uri "$ApiBaseUrl/api/lecturer_login_api.php" -Method Get -TimeoutSec 3
            $healthOk = $true
        } catch {
            $healthOk = $false
        }

        if (-not $healthOk) {
            $phpServerProcess = Start-Process -FilePath "php" -ArgumentList "-S", "127.0.0.1:8090", "-t", "." -WorkingDirectory $repoRoot -PassThru
            Start-Sleep -Seconds 2
        }
    }

    # Ensure API path is reachable (401 is acceptable for invalid login probe).
    try {
        $body = '{"login":"invalid_user","password":"invalid_pass"}'
        $null = Invoke-WebRequest -Uri "$ApiBaseUrl/api/lecturer_login_api.php" -Method Post -ContentType "application/json" -Body $body -TimeoutSec 10
    } catch {
        if ($null -eq $_.Exception.Response) {
            throw "API is not reachable at $ApiBaseUrl"
        }
    }

    # Seed production-like data (idempotent for QA-tagged records).
    php (Join-Path $repoRoot "scripts/run_sql_file.php") (Join-Path $repoRoot "scripts/seed_lecturer_production_test_data.sql") | Out-Host

    # Run matrix and save JSON report.
    $matrixJson = python (Join-Path $repoRoot "scripts/run_lecturer_matrix_test.py") --base-url $ApiBaseUrl
    $matrixJson | Set-Content -Path $jsonReport -Encoding UTF8

    $report = Get-Content -Path $jsonReport -Raw | ConvertFrom-Json
    $summary = $report.summary

    $lecturerCount = [int]$summary.lecturer_count_with_timetable
    $multiLecturerModules = [int]$summary.modules_with_multi_lecturer_teaching
    $invalidLoginRejected = [bool]$summary.invalid_login_rejected
    $unownedBlocked = [bool]$summary.unowned_publish_blocked_all

    $maxSharedItems = 0
    foreach ($p in $report.shared_module_ids_by_module.PSObject.Properties) {
        $count = [int]$p.Value.items_count
        if ($count -gt $maxSharedItems) {
            $maxSharedItems = $count
        }
    }

    $checks = @(
        @{ Name = "At least 3 lecturers with timetables"; Pass = ($lecturerCount -ge 3); Detail = "$lecturerCount found" }
        @{ Name = "Shared modules taught by multiple lecturers"; Pass = ($multiLecturerModules -ge 1); Detail = "$multiLecturerModules found" }
        @{ Name = "Invalid login is rejected"; Pass = $invalidLoginRejected; Detail = "success=false expected" }
        @{ Name = "Unowned module publish is blocked"; Pass = $unownedBlocked; Detail = "403 expected" }
        @{ Name = "Shared calendar has overlap evidence"; Pass = ($maxSharedItems -ge 1); Detail = "max shared items=$maxSharedItems" }
    )
    $overallPass = ($checks | Where-Object { -not $_.Pass }).Count -eq 0

    $lines = @()
    $lines += "# Lecturer QA Report"
    $lines += ""
    $lines += "- Timestamp (UTC): $($report.timestamp_utc)"
    $lines += "- Base URL: $($report.base_url)"
    $lines += "- Overall Result: " + ($(if ($overallPass) { "PASS" } else { "FAIL" }))
    $lines += ""
    $lines += "## Summary"
    $lines += ""
    $lines += "- Lecturer count with timetable: $lecturerCount"
    $lines += "- Modules with multi-lecturer teaching: $multiLecturerModules"
    $lines += "- Invalid login rejected: $invalidLoginRejected"
    $lines += "- Unowned publish blocked: $unownedBlocked"
    $lines += "- Max shared-calendar items on a module: $maxSharedItems"
    $lines += ""
    $lines += "## Criteria"
    $lines += ""
    $lines += "| Check | Result | Detail |"
    $lines += "|---|---|---|"
    foreach ($c in $checks) {
        $result = if ($c.Pass) { "PASS" } else { "FAIL" }
        $lines += "| $($c.Name) | $result | $($c.Detail) |"
    }
    $lines += ""
    $lines += "## Artifacts"
    $lines += ""
    $lines += "- JSON: $jsonReport"
    $lines += "- Markdown: $mdReport"

    $lines | Set-Content -Path $mdReport -Encoding UTF8

    Write-Host ""
    Write-Host "Lecturer QA run complete."
    Write-Host "JSON report: $jsonReport"
    Write-Host "Markdown report: $mdReport"
    Write-Host "Overall: $(if ($overallPass) { 'PASS' } else { 'FAIL' })"
}
finally {
    if ($null -ne $phpServerProcess -and -not $phpServerProcess.HasExited) {
        Stop-Process -Id $phpServerProcess.Id -Force
    }
}

