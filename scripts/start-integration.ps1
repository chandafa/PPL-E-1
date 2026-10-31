param([string]$F1Path = '', [string]$D2Path = '', [switch]$Main)
$ErrorActionPreference = 'Stop'

# Repo pendukung hanya diambil saat belum ada. Checkout yang sudah dibuat anggota
# tidak pernah diubah/diperbarui oleh launcher agar pekerjaan lokal mereka aman.
$F1Repository = 'https://github.com/Izra29/PPL_F1.git'
$F1Revision = '0feb37aafe9df9e871aaba9a5457093696ce38a0'
$D2Repository = 'https://github.com/eldyfg/PPL-D-2.git'
$D2Revision = 'b93fc4dadcf7916995c9e6c2f981f95cc039dd97'

function Invoke-RequiredCommand([string]$name, [string[]]$arguments, [string]$failureMessage) {
    $command = Get-Command $name -ErrorAction SilentlyContinue
    if ($null -eq $command) { throw "Perintah $name tidak ditemukan. $failureMessage" }
    & $command.Source @arguments
    if ($LASTEXITCODE -ne 0) { throw "$failureMessage (perintah $name berakhir dengan kode $LASTEXITCODE)." }
}

function Ensure-IntegrationRepository([string]$name, [string]$path, [string]$repository, [string]$revision, [string]$requiredFile) {
    if (Test-Path -LiteralPath $path -PathType Container) {
        if (-not (Test-Path -LiteralPath (Join-Path $path $requiredFile) -PathType Leaf)) {
            throw "Folder $name sudah ada, tetapi bukan checkout yang dapat dipakai (berkas $requiredFile tidak ditemukan). Periksa folder tersebut; launcher tidak menimpanya."
        }
        return
    }

    $container = Split-Path -Parent $path
    New-Item -ItemType Directory -Force -Path $container | Out-Null
    Write-Output "$name belum ada; mengunduh repo resmi ke $path..."
    Invoke-RequiredCommand 'git' @('clone', $repository, $path) "Gagal mengunduh $name dari $repository. Pastikan Git dan koneksi internet tersedia"
    Invoke-RequiredCommand 'git' @('-C', $path, 'checkout', '--detach', $revision) "Gagal memakai revisi $name yang telah diuji ($revision)"
}

function Ensure-F1Dependencies([string]$path) {
    if (Test-Path -LiteralPath (Join-Path $path 'node_modules/jsonwebtoken/package.json') -PathType Leaf) { return }
    Write-Output 'Memasang dependensi F-1 (npm ci)...'
    Push-Location $path
    try {
        Invoke-RequiredCommand 'npm' @('ci', '--ignore-scripts') 'Pemasangan dependensi F-1 gagal'
    } finally { Pop-Location }
}

function Ensure-D2Dependencies([string]$path) {
    if (Test-Path -LiteralPath (Join-Path $path 'vendor/autoload.php') -PathType Leaf) { return }
    Write-Output 'Memasang dependensi D-2 (composer install)...'
    Push-Location $path
    try {
        Invoke-RequiredCommand 'composer' @('install', '--no-interaction', '--prefer-dist') 'Pemasangan dependensi D-2 gagal'
    } finally { Pop-Location }
}

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
    Write-Output "Proses $name masih ada tetapi port $($entry.port) tidak siap; menjalankan ulang layanan tersebut."
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
$parent = Split-Path $repo
if (-not $F1Path) { $F1Path = Join-Path $parent '_integrasi/F1' }
if (-not $D2Path) { $D2Path = Join-Path $parent '_integrasi/D2' }
Ensure-IntegrationRepository 'F-1' $F1Path $F1Repository $F1Revision 'demo/local-server.js'
Ensure-IntegrationRepository 'D-2' $D2Path $D2Repository $D2Revision 'artisan'
$F1Path = (Resolve-Path -LiteralPath $F1Path).Path
$D2Path = (Resolve-Path -LiteralPath $D2Path).Path
$runtimeFile = Join-Path $repo 'storage/app/private/integration/current.json'
if (-not (Test-Path -LiteralPath $runtimeFile)) { throw 'Jalankan php scripts/prepare-integration.php dahulu.' }
Ensure-F1Dependencies $F1Path
Ensure-D2Dependencies $D2Path
$runtime = Get-Content -Raw -LiteralPath $runtimeFile | ConvertFrom-Json
$stateFile = Join-Path $runtime.directory 'processes.json'
$setupMarker = Join-Path $runtime.directory 'd2-setup.complete'
$e1Port = if ($Main) { 8080 } else { 8087 }
$expectedPorts = @{ f1 = 4100; d2 = 8026; e1 = $e1Port }
$existingRuntime = Test-Path -LiteralPath $stateFile
$processes = @{}
if ($existingRuntime) {
    $previousState = Get-Content -Raw -LiteralPath $stateFile | ConvertFrom-Json
    foreach ($name in @('f1','d2','e1')) {
        $entry = $previousState.$name
        if (Test-OwnedListener $entry) {
            if ($entry.port -ne $expectedPorts[$name]) { throw 'E-1 berjalan dengan profil/port berbeda. Jalankan composer integration:stop sebelum mengganti profil.' }
            $processes[$name] = $entry
        } elseif (Test-OwnedProcess $entry) {
            Stop-OwnedProcess $name $entry
        }
    }
}
if ($processes.Count -eq 3) {
    foreach ($name in @('f1','d2','e1')) { Wait-OwnedListener $name $processes[$name] }
    Write-Output "Integrasi sudah berjalan: http://127.0.0.1:$e1Port/login; F-1 4100; D-2 8026."
    Write-Output 'Layanan dan data tetap dipertahankan. Untuk restart penuh: composer integration:stop lalu composer integration.'
    return
}
foreach ($name in @('f1','d2','e1')) {
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
}
try {
    Set-ProcessEnv 'JWT_SECRET' $runtime.jwtSecret
    Set-ProcessEnv 'JWT_EXPIRES_IN' '3600'
    Set-ProcessEnv 'E1_DEPENDENCY_TRACE' (Join-Path $runtime.directory 'f1-requests.ndjson')
    if (-not $processes.ContainsKey('f1')) {
        Start-Owned 'f1' (Get-Command node).Source @('--require', ('"' + (Join-Path $PSScriptRoot 'trace-f1.cjs') + '"'), 'demo/local-server.js') $F1Path 4100
    }
    if (-not $processes.ContainsKey('d2')) {
        Set-ProcessEnv 'APP_ENV' 'local'
        Set-ProcessEnv 'APP_DEBUG' 'false'
        $keyBytes = New-Object byte[] 32
        $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
        try { $rng.GetBytes($keyBytes) } finally { $rng.Dispose() }
        Set-ProcessEnv 'APP_KEY' ('base64:' + [Convert]::ToBase64String($keyBytes))
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
        Push-Location $D2Path
        try {
            $migrationArguments = @('artisan', 'migrate', '--force', '--no-interaction')
            # Seed hanya pertama kali; restart tidak menimpa status/nominal invoice D-2.
            if (-not $existingRuntime -and -not (Test-Path -LiteralPath $setupMarker)) { $migrationArguments += '--seed' }
            & php @migrationArguments *> (Join-Path $runtime.directory 'd2-setup.log')
            if ($LASTEXITCODE -ne 0) { throw 'Migrasi/seed D-2 gagal; lihat d2-setup.log. Kode D-2 tidak diubah.' }
            [IO.File]::WriteAllText($setupMarker, (Get-Date).ToUniversalTime().ToString('o'))
        } finally { Pop-Location }
        Set-ProcessEnv 'E1_DEPENDENCY_TRACE' (Join-Path $runtime.directory 'd2-requests.ndjson')
        Set-ProcessEnv 'E1_DEPENDENCY_REPO' $D2Path
        Start-Owned 'd2' (Get-Command php).Source @('-S', '127.0.0.1:8026', '-t', 'public', ('"' + (Join-Path $PSScriptRoot 'trace-d2.php') + '"')) $D2Path 8026
    }
    foreach ($key in @($saved.Keys)) { [Environment]::SetEnvironmentVariable($key, $saved[$key], 'Process') }
    if (-not $processes.ContainsKey('e1')) {
        if (-not $Main) {
            Set-ProcessEnv 'APP_ENV' 'integration'
            Push-Location $repo
            try {
                & php artisan migrate --env=integration --force --no-interaction *> (Join-Path $runtime.directory 'e1-setup.log')
                if ($LASTEXITCODE -ne 0) { throw 'Migrasi E-1 gagal; lihat e1-setup.log.' }
            } finally { Pop-Location }
        }
        # -Main memakai .env utama yang sudah diaktifkan enable-integration.php;
        # tidak memigrasikan, seeding, atau mereset database utama.
        Start-Owned 'e1' (Get-Command php).Source @('-d', 'upload_max_filesize=6M', '-d', 'post_max_size=16M', '-S', "127.0.0.1:$e1Port", '-t', 'public', 'server.php') $repo $e1Port
    }
    $processes | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $stateFile -Encoding UTF8
    foreach ($name in @('f1','d2','e1')) { Wait-OwnedListener $name $processes[$name] }
    Write-Output "Siap diuji: http://127.0.0.1:$e1Port/login; F-1 4100; D-2 8026."
    Write-Output ('Layanan yang dinyalakan: ' + (($started.Keys | Sort-Object) -join ', ') + '. Layanan lain tetap berjalan.')
    if ($Main) { Write-Output 'E-1 memakai database utama yang dipertahankan; D-2 memakai database integrasi terpisah. Stop: composer integration:stop.' }
    else { Write-Output 'Profil pengujian terpisah; database utama tidak digunakan.' }
} catch {
    foreach ($p in $started.Values) {
        $live = Get-Process -Id $p.id -ErrorAction SilentlyContinue
        if ($live -and $live.StartTime.ToString('o') -eq $p.startedAt) { Stop-Process -Id ([int]$p.id) -Force }
    }
    throw
} finally {
    foreach ($key in $saved.Keys) { [Environment]::SetEnvironmentVariable($key, $saved[$key], 'Process') }
}
