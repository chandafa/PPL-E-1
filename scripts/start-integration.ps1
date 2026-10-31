param([string]$F1Path = '', [string]$D2Path = '', [switch]$Main)
$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot
$parent = Split-Path $repo
if (-not $F1Path) { $F1Path = Join-Path $parent '_integrasi/F1' }
if (-not $D2Path) { $D2Path = Join-Path $parent '_integrasi/D2' }
$F1Path = (Resolve-Path -LiteralPath $F1Path).Path
$D2Path = (Resolve-Path -LiteralPath $D2Path).Path
$runtimeFile = Join-Path $repo 'storage/app/private/integration/current.json'
if (-not (Test-Path -LiteralPath $runtimeFile)) { throw 'Jalankan php scripts/prepare-integration.php dahulu.' }
if (-not (Test-Path -LiteralPath (Join-Path $F1Path 'node_modules/jsonwebtoken/index.js'))) { throw 'Pasang dependensi F-1 dengan npm ci dahulu.' }
if (-not (Test-Path -LiteralPath (Join-Path $D2Path 'vendor/autoload.php'))) { throw 'Pasang dependensi D-2 dengan composer install dahulu.' }
$runtime = Get-Content -Raw -LiteralPath $runtimeFile | ConvertFrom-Json
$stateFile = Join-Path $runtime.directory 'processes.json'
$existingRuntime = Test-Path -LiteralPath $stateFile
if ($existingRuntime) {
    $previousState = Get-Content -Raw -LiteralPath $stateFile | ConvertFrom-Json
    foreach ($name in @('f1','d2','e1')) {
        $entry = $previousState.$name
        $live = Get-Process -Id $entry.id -ErrorAction SilentlyContinue
        if ($live -and $live.StartTime.ToString('o') -eq $entry.startedAt) { throw 'Runtime masih berjalan. Hentikan dengan composer integration:stop sebelum menjalankan ulang.' }
    }
}
$e1Port = if ($Main) { 8080 } else { 8087 }
foreach ($port in @(4100, 8026, $e1Port)) {
    if (Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue) { throw "Port $port sedang dipakai; proses yang ada tidak dihentikan." }
}
$saved = @{}
$processes = @{}
function Set-ProcessEnv([string]$key, [string]$value) {
    if (-not $saved.ContainsKey($key)) { $saved[$key] = [Environment]::GetEnvironmentVariable($key, 'Process') }
    [Environment]::SetEnvironmentVariable($key, $value, 'Process')
}
function Start-Owned([string]$name, [string]$program, [string[]]$arguments, [string]$cwd, [int]$port) {
    $p = Start-Process -FilePath $program -ArgumentList $arguments -WorkingDirectory $cwd -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime.directory "$name.out.log") -RedirectStandardError (Join-Path $runtime.directory "$name.err.log")
    $processes[$name] = @{ id = $p.Id; startedAt = $p.StartTime.ToString('o'); port = $port }
}
try {
    Set-ProcessEnv 'JWT_SECRET' $runtime.jwtSecret
    Set-ProcessEnv 'JWT_EXPIRES_IN' '3600'
    Set-ProcessEnv 'E1_DEPENDENCY_TRACE' (Join-Path $runtime.directory 'f1-requests.ndjson')
    Start-Owned 'f1' (Get-Command node).Source @('--require', ('"' + (Join-Path $PSScriptRoot 'trace-f1.cjs') + '"'), 'demo/local-server.js') $F1Path 4100
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
        if (-not $existingRuntime) { $migrationArguments += '--seed' }
        & php @migrationArguments *> (Join-Path $runtime.directory 'd2-setup.log')
        if ($LASTEXITCODE -ne 0) { throw 'Migrasi/seed D-2 gagal; lihat d2-setup.log. Kode D-2 tidak diubah.' }
    } finally { Pop-Location }
    Set-ProcessEnv 'E1_DEPENDENCY_TRACE' (Join-Path $runtime.directory 'd2-requests.ndjson')
    Set-ProcessEnv 'E1_DEPENDENCY_REPO' $D2Path
    Start-Owned 'd2' (Get-Command php).Source @('-S', '127.0.0.1:8026', '-t', 'public', ('"' + (Join-Path $PSScriptRoot 'trace-d2.php') + '"')) $D2Path 8026
    foreach ($key in @($saved.Keys)) { [Environment]::SetEnvironmentVariable($key, $saved[$key], 'Process') }
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
    $processes | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $stateFile -Encoding UTF8
    $deadline = (Get-Date).AddSeconds(15)
    foreach ($name in @('f1','d2','e1')) {
        $entry = $processes[$name]
        do {
            $listener = Get-NetTCPConnection -State Listen -LocalPort $entry.port -ErrorAction SilentlyContinue
            $live = Get-Process -Id $entry.id -ErrorAction SilentlyContinue
            if (-not $live) { throw "Proses $name gagal; lihat log runtime." }
            if ($listener -and $listener.OwningProcess -eq $entry.id) { break }
            if ((Get-Date) -gt $deadline) { throw "Proses $name belum siap pada port miliknya." }
            Start-Sleep -Milliseconds 100
        } while ($true)
    }
    Write-Output "Siap diuji: http://127.0.0.1:$e1Port/login; F-1 4100; D-2 8026."
    if ($Main) { Write-Output 'E-1 memakai database utama yang dipertahankan; D-2 memakai database integrasi terpisah. Stop: composer integration:stop.' }
    else { Write-Output 'Profil pengujian terpisah; database utama tidak digunakan.' }
} catch {
    foreach ($p in $processes.Values) {
        $live = Get-Process -Id $p.id -ErrorAction SilentlyContinue
        if ($live -and $live.StartTime.ToString('o') -eq $p.startedAt) { Stop-Process -Id ([int]$p.id) -Force }
    }
    throw
} finally {
    foreach ($key in $saved.Keys) { [Environment]::SetEnvironmentVariable($key, $saved[$key], 'Process') }
}
