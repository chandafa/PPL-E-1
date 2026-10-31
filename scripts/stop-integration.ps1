param([ValidateSet('all', 'e1', 'd2', 'f1')][string]$Service = 'all')
$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot
$runtimeFile = Join-Path $repo 'storage/app/private/integration/current.json'
if (-not (Test-Path -LiteralPath $runtimeFile -PathType Leaf)) {
    Write-Output 'Tidak ada runtime integrasi yang tercatat. Tidak ada proses yang dihentikan.'
    exit 0
}
try {
    $runtime = Get-Content -Raw -LiteralPath $runtimeFile | ConvertFrom-Json
} catch {
    Write-Output 'State runtime integrasi tidak dapat dibaca. Tidak ada proses yang dihentikan; composer integration dapat membuat runtime baru bila port kosong.'
    exit 0
}
if (-not $runtime.directory) {
    Write-Output 'State runtime integrasi tidak lengkap. Tidak ada proses yang dihentikan; composer integration dapat membuat runtime baru bila port kosong.'
    exit 0
}
$stateFile = Join-Path $runtime.directory 'processes.json'
if (-not (Test-Path -LiteralPath $stateFile -PathType Leaf)) {
    Write-Output 'State proses runtime sudah tidak ada. Tidak ada proses yang dihentikan; composer integration akan memulihkan runtime bila port kosong.'
    exit 0
}
try {
    $state = Get-Content -Raw -LiteralPath $stateFile | ConvertFrom-Json
} catch {
    Write-Output 'State proses runtime tidak dapat dibaca. Tidak ada proses yang dihentikan; composer integration dapat membuat runtime baru bila port kosong.'
    exit 0
}
foreach ($name in @('e1', 'd2', 'f1')) {
    if ($Service -ne 'all' -and $name -ne $Service) { continue }
    $entry = $state.$name
    if ($null -eq $entry -or -not $entry.id -or -not $entry.startedAt) { continue }
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
