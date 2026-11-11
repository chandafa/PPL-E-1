param([Parameter(Mandatory=$true)][string]$Repository, [switch]$Install, [switch]$Build)
$ErrorActionPreference = 'Stop'
if (-not (Get-Command nvm -ErrorAction SilentlyContinue)) { throw 'nvm-windows tidak ditemukan. Pasang pada akun pengguna.' }
$versionFile = Join-Path $Repository '.nvmrc'
if (-not (Test-Path $versionFile)) { $versionFile = Join-Path (Split-Path $PSScriptRoot) '.nvmrc' }
$version = if (Test-Path $versionFile) { (Get-Content -Raw $versionFile).Trim().TrimStart('v') } else { (& nvm current | Out-String).Trim().TrimStart('v') }
if ($version -notmatch '^\d+\.\d+\.\d+$') { throw 'Aktifkan versi Node melalui nvm terlebih dahulu, atau sediakan .nvmrc berisi versi lengkap.' }
& nvm use $version
if ($LASTEXITCODE -ne 0) {
    if (-not ($Install -or $Build)) { throw 'Versi Node belum tersedia; jalankan composer integration.' }
    & nvm install $version
    if ($LASTEXITCODE -ne 0) { throw 'nvm install gagal.' }
    & nvm use $version
    if ($LASTEXITCODE -ne 0) { throw 'nvm use gagal.' }
}
& node (Join-Path $PSScriptRoot 'check-integration-node.cjs')
if ($LASTEXITCODE -ne 0) { throw 'F-1 memerlukan Node.js >=18 melalui nvm.' }
if ($Build) {
    & node (Join-Path $PSScriptRoot 'check-integration-node.cjs') --frontend
    if ($LASTEXITCODE -ne 0) { throw 'Versi Node belum mendukung build Vite. Aktifkan Node 24 melalui nvm.' }
}
if ($Install -or $Build) {
    Push-Location $Repository
    try {
        if ($Build) { & npm ci } else { & npm ci --ignore-scripts }
        if ($LASTEXITCODE -ne 0) { throw 'npm ci gagal.' }
        if ($Build) {
            & npm run build
            if ($LASTEXITCODE -ne 0) { throw 'Build frontend gagal. Gunakan versi Node yang didukung Vite melalui nvm.' }
        }
    } finally { Pop-Location }
}
