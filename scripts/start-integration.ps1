param([ValidateSet('mock','http')][string]$D2Driver = 'http')
$ErrorActionPreference = 'Stop'

function Test-OwnedProcess($entry) {
    if ($null -eq $entry -or -not $entry.id -or -not $entry.startedAt) { return $false }
    $live = Get-Process -Id $entry.id -ErrorAction SilentlyContinue
    return ($null -ne $live -and $live.StartTime.ToString('o') -eq $entry.startedAt)
}
function Get-ListeningProcessIds([int]$port) {
    $pattern = '^\s*TCP\s+\S+:' + [regex]::Escape($port.ToString()) + '\s+\S+\s+LISTENING\s+(\d+)\s*$'
    $ids = @()
    foreach ($line in @(netstat -ano -p tcp)) {
        if ($line -match $pattern) { $ids += [int]$Matches[1] }
    }
    return $ids
}
function Test-OwnedListener($entry) {
    if (-not (Test-OwnedProcess $entry)) { return $false }
    return @(Get-ListeningProcessIds ([int]$entry.port) | Where-Object { $_ -eq $entry.id }).Count -gt 0
}
function Stop-OwnedProcess([string]$name, $entry) {
    if (-not (Test-OwnedProcess $entry)) { return }
    Write-Output "Proses $name perlu dimulai ulang pada port $($entry.port); menghentikan proses milik runtime ini."
    Stop-Process -Id ([int]$entry.id) -Force -ErrorAction Stop
}
function Wait-OwnedListener([string]$name, $entry) {
    $deadline = (Get-Date).AddSeconds(15)
    do {
        if (-not (Test-OwnedProcess $entry)) { throw "Proses $name berhenti; lihat log runtime." }
        $listenerIds = @(Get-ListeningProcessIds ([int]$entry.port))
        if ($listenerIds.Count -gt 0) {
            if (@($listenerIds | Where-Object { $_ -ne $entry.id }).Count -gt 0) {
                throw "Port $($entry.port) dipakai proses lain; proses tersebut tidak dihentikan."
            }
            return
        }
        if ((Get-Date) -gt $deadline) { throw "Proses $name belum siap pada port $($entry.port); lihat log runtime." }
        Start-Sleep -Milliseconds 100
    } while ($true)
}
$repo = Split-Path $PSScriptRoot
$runtimeJson = & php (Join-Path $PSScriptRoot 'integration-runtime.php') $D2Driver
if ($LASTEXITCODE -ne 0) { throw 'Setup belum lengkap; jalankan composer integration.' }
$runtime = $runtimeJson | ConvertFrom-Json
$F1Path = $runtime.setup.repositories.f1.path
$D2Path = if ($D2Driver -eq 'http') { $runtime.setup.repositories.d2.path } else { '' }
if (-not (Test-Path (Join-Path $F1Path 'node_modules/jsonwebtoken/package.json'))) { throw 'Dependensi F-1 belum tersedia; jalankan composer integration.' }
if ($D2Driver -eq 'http' -and -not (Test-Path (Join-Path $D2Path 'vendor/autoload.php'))) { throw 'Dependensi D-2 belum tersedia; jalankan composer integration.' }
& (Join-Path $PSScriptRoot 'integration-node.ps1') -Repository $F1Path
$stateFile = Join-Path $runtime.directory 'processes.json'
$e1Port = 8087
$expectedPorts = @{ f1 = 4100; e1 = $e1Port }
if ($D2Driver -eq 'http') { $expectedPorts['d2'] = 8026 }
$serviceNames = @('f1', 'e1')
if ($D2Driver -eq 'http') { $serviceNames = @('f1', 'd2', 'e1') }
$d2Label = if ($D2Driver -eq 'mock') { 'D-2 dummy lokal (2 invoice SQLite)' } else { 'D-2 8026' }
$modeFile = Join-Path $runtime.directory 'd2-mode.txt'
$previousMode = if (Test-Path -LiteralPath $modeFile) { (Get-Content -Raw -LiteralPath $modeFile).Trim() } else { 'http' }
$existingRuntime = Test-Path -LiteralPath $stateFile
$processes = @{}
if ($existingRuntime) {
    $previousState = Get-Content -Raw -LiteralPath $stateFile | ConvertFrom-Json
    if ($previousMode -ne $D2Driver -and (Test-OwnedProcess $previousState.e1)) { Stop-OwnedProcess 'e1' $previousState.e1 }
    if ($D2Driver -eq 'mock' -and (Test-OwnedProcess $previousState.d2)) { Stop-OwnedProcess 'd2' $previousState.d2 }
    foreach ($name in $serviceNames) {
        $entry = $previousState.$name
        if (Test-OwnedListener $entry) {
            if ($entry.port -ne $expectedPorts[$name]) {
                if ($name -eq 'e1' -and $entry.port -eq 8080 -and $expectedPorts[$name] -eq 8087) {
                    Stop-OwnedProcess $name $entry
                    continue
                }
                throw 'E-1 berjalan dengan profil/port berbeda. Jalankan composer integration:stop sebelum mengganti profil.'
            }
            $processes[$name] = $entry
        } elseif (Test-OwnedProcess $entry) {
            Stop-OwnedProcess $name $entry
        }
    }
}
if ($processes.Count -eq $serviceNames.Count) {
    foreach ($name in $serviceNames) { Wait-OwnedListener $name $processes[$name] }
    Write-Output "Integrasi sudah berjalan: http://127.0.0.1:$e1Port/login; F-1 4100; $d2Label."
    Write-Output 'Layanan dan data tetap dipertahankan. Untuk restart penuh: composer integration:stop lalu composer integration run.'
    return
}
foreach ($name in $serviceNames) {
    $port = $expectedPorts[$name]
    $listenerIds = @(Get-ListeningProcessIds $port)
    if ($listenerIds.Count -gt 0 -and (-not $processes.ContainsKey($name) -or @($listenerIds | Where-Object { $_ -ne $processes[$name].id }).Count -gt 0)) {
        throw "Port $port sedang dipakai proses di luar runtime ini; proses tersebut tidak dihentikan."
    }
}
$saved = @{}
$started = @{}
function Set-ProcessEnv([string]$key, [string]$value) {
    if (-not $saved.ContainsKey($key)) { $saved[$key] = [Environment]::GetEnvironmentVariable($key, 'Process') }
    [Environment]::SetEnvironmentVariable($key, $value, 'Process')
}
function Start-Owned([string]$name, [string]$program, [string[]]$arguments, [string]$cwd, [int]$port) {
    $p = Start-Process -FilePath $program -ArgumentList $arguments -WorkingDirectory $cwd -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime.directory "$name.out.log") -RedirectStandardError (Join-Path $runtime.directory "$name.err.log")
    $processes[$name] = @{ id = $p.Id; startedAt = $p.StartTime.ToString('o'); port = $port }
    $started[$name] = $processes[$name]
    [IO.File]::WriteAllText($stateFile, ($processes | ConvertTo-Json -Depth 5))
}
try {
    Set-ProcessEnv 'JWT_SECRET' $runtime.jwtSecret
    Set-ProcessEnv 'JWT_EXPIRES_IN' '3600'
    Set-ProcessEnv 'PORT' '4100'
    Set-ProcessEnv 'E1_DEPENDENCY_TRACE' (Join-Path $runtime.directory 'f1-requests.ndjson')
    if (-not $processes.ContainsKey('f1')) {
        Start-Owned 'f1' (Get-Command node).Source @('--require', ('"' + (Join-Path $PSScriptRoot 'trace-f1.cjs') + '"'), 'demo/local-server.js') $F1Path 4100
    }
    if ($D2Driver -eq 'http' -and -not $processes.ContainsKey('d2')) {
        Set-ProcessEnv 'APP_ENV' 'local'
        Set-ProcessEnv 'APP_DEBUG' 'false'
        Set-ProcessEnv 'APP_KEY' $runtime.d2.key
        Set-ProcessEnv 'APP_CONFIG_CACHE' (Join-Path $runtime.directory 'd2-config.php')
        Set-ProcessEnv 'DB_CONNECTION' 'pgsql'
        Set-ProcessEnv 'DB_URL' ''
        Set-ProcessEnv 'DB_HOST' $runtime.db.host
        Set-ProcessEnv 'DB_PORT' $runtime.db.port
        Set-ProcessEnv 'DB_USERNAME' $runtime.db.username
        Set-ProcessEnv 'DB_PASSWORD' $runtime.db.password
        Set-ProcessEnv 'DB_DATABASE' $runtime.d2.database
        Set-ProcessEnv 'LARAVEL_STORAGE_PATH' $runtime.d2.storage
        Set-ProcessEnv 'CACHE_STORE' 'array'
        Set-ProcessEnv 'SESSION_DRIVER' 'array'
        Set-ProcessEnv 'LOG_CHANNEL' 'single'
        Set-ProcessEnv 'E1_DEPENDENCY_TRACE' (Join-Path $runtime.directory 'd2-requests.ndjson')
        Set-ProcessEnv 'E1_DEPENDENCY_REPO' $D2Path
        Start-Owned 'd2' (Get-Command php).Source @('-S', '127.0.0.1:8026', '-t', 'public', ('"' + (Join-Path $PSScriptRoot 'trace-d2.php') + '"')) $D2Path 8026
    }
    foreach ($key in @($saved.Keys)) { [Environment]::SetEnvironmentVariable($key, $saved[$key], 'Process') }
    Set-ProcessEnv 'D2_DRIVER' $D2Driver
    foreach ($property in $runtime.e1Environment.PSObject.Properties) {
        Set-ProcessEnv $property.Name ([string]$property.Value)
    }
    if (-not $processes.ContainsKey('e1')) {
        Start-Owned 'e1' (Get-Command php).Source @('-d', 'upload_max_filesize=6M', '-d', 'post_max_size=16M', '-S', "127.0.0.1:$e1Port", '-t', 'public', 'server.php') $repo $e1Port
    }
    $processes | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $stateFile -Encoding UTF8
    foreach ($name in $serviceNames) { Wait-OwnedListener $name $processes[$name] }
    [IO.File]::WriteAllText($modeFile, $D2Driver)
    Write-Output "Siap diuji: http://127.0.0.1:$e1Port/login; F-1 4100; $d2Label."
    Write-Output ('Layanan yang dinyalakan: ' + (($started.Keys | Sort-Object) -join ', ') + '. Layanan lain tetap berjalan.')
    if ($D2Driver -eq 'mock') { Write-Output 'Dummy D-2 berada di database E-1; tanpa server D-2. Stop: composer integration:stop.' }
    else { Write-Output 'E-1 memakai database utama yang dipertahankan; D-2 memakai database integrasi terpisah. Stop: composer integration:stop.' }
} catch {
    foreach ($p in $started.Values) {
        $live = Get-Process -Id $p.id -ErrorAction SilentlyContinue
        if ($live -and $live.StartTime.ToString('o') -eq $p.startedAt) { Stop-Process -Id ([int]$p.id) -Force }
    }
    throw
} finally {
    foreach ($key in $saved.Keys) { [Environment]::SetEnvironmentVariable($key, $saved[$key], 'Process') }
}
