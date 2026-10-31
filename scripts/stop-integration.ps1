param([ValidateSet('all', 'e1', 'd2', 'f1')][string]$Service = 'all')
$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot
$runtime = Get-Content -Raw -LiteralPath (Join-Path $repo 'storage/app/private/integration/current.json') | ConvertFrom-Json
$stateFile = Join-Path $runtime.directory 'processes.json'
$state = Get-Content -Raw -LiteralPath $stateFile | ConvertFrom-Json
foreach ($name in @('e1', 'd2', 'f1')) {
    if ($Service -ne 'all' -and $name -ne $Service) { continue }
    $entry = $state.$name
    $live = Get-Process -Id $entry.id -ErrorAction SilentlyContinue
    if (-not $live) { continue }
    if ($live.StartTime.ToString('o') -ne $entry.startedAt) { throw "PID $name telah dipakai proses lain; tidak dihentikan." }
    $listener = Get-NetTCPConnection -State Listen -LocalPort $entry.port -ErrorAction SilentlyContinue
    if ($listener -and $listener.OwningProcess -ne $entry.id) { throw "Port $name bukan milik runtime ini." }
    try {
        Stop-Process -Id ([int]$entry.id) -Force -ErrorAction Stop
    } catch {
        if (Get-Process -Id ([int]$entry.id) -ErrorAction SilentlyContinue) { throw }
    }
}
Write-Output 'Proses integrasi milik runtime ini dihentikan. Database dan bukti tetap disimpan.'
