$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$version = (Get-Content (Join-Path $root 'VERSION.txt') -Raw).Trim()
if ($version -notmatch '^\d+\.\d+\.\d+$') {
    throw "VERSION.txt tidak valid: $version"
}

$outDir = Join-Path $root 'release'
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$packageRoot = Join-Path $env:TEMP ("WebTestOnline-update-" + $version + "-" + [guid]::NewGuid().ToString('N'))
$stage = Join-Path $packageRoot 'WebTestOnline'
New-Item -ItemType Directory -Force -Path $stage | Out-Null

$exclude = @('.git','storage','uploads','release','config.php')
Get-ChildItem -Force $root | Where-Object {
    $exclude -notcontains $_.Name
} | ForEach-Object {
    Copy-Item $_.FullName -Destination $stage -Recurse -Force
}

$manifest = Join-Path $stage 'update-manifest.json'
if (-not (Test-Path $manifest)) { throw 'update-manifest.json tidak ditemukan di paket.' }
$manifestVersion = ((Get-Content $manifest -Raw | ConvertFrom-Json).version).ToString()
if ($manifestVersion -ne $version) {
    throw "Versi manifest ($manifestVersion) tidak sama dengan VERSION.txt ($version)."
}

$zip = Join-Path $outDir ("WebTestOnline-Update-V" + $version + '.zip')
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $packageRoot '*') -DestinationPath $zip -CompressionLevel Optimal

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
    $entries = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\\','/') })

    # VERSION.txt bisa berada di root ZIP atau di satu folder project pembungkus.
    $hasVersion = $entries | Where-Object { $_ -match '(^|/)VERSION\.txt$' }
    if (-not $hasVersion) {
        throw 'ZIP hasil tidak memiliki VERSION.txt.'
    }

    $hasUpdate = $entries | Where-Object { $_ -match '(^|/)admin/update\.php$' }
    if (-not $hasUpdate) {
        throw 'ZIP hasil tidak memiliki file aplikasi yang diperlukan.'
    }

    $hasManifest = $entries | Where-Object { $_ -match '(^|/)update-manifest\.json$' }
    if (-not $hasManifest) {
        throw 'ZIP hasil tidak memiliki update-manifest.json.'
    }

    if ($entries | Where-Object { $_ -match '(^|/)(storage|uploads|\.git)(/|$)|(^|/)config\.php$' }) {
        throw 'ZIP hasil masih berisi file/folder runtime yang harus dikecualikan.'
    }
}
finally {
    $archive.Dispose()
    Remove-Item $packageRoot -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host "PAKET SIAP: $zip"
Write-Host "VERSI: $version"
Write-Host "Struktur ZIP tervalidasi: VERSION.txt, update-manifest.json, dan admin/update.php ditemukan."
