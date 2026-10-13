#requires -Version 7.0
param([string] $LabPath = 'D:\Project\WordPress-Lab')
$ErrorActionPreference = 'Stop'
$sourceRoot = $PSScriptRoot
$labRoot = [IO.Path]::GetFullPath($LabPath).TrimEnd('\')
if ($labRoot.TrimEnd('\') -ne 'D:\Project\WordPress-Lab') {
    throw 'This installer is scoped to D:\Project\WordPress-Lab.'
}
New-Item -ItemType Directory -Path $labRoot -Force | Out-Null
$archive = Join-Path $labRoot ('backups/config-' + (Get-Date -Format 'yyyy-MM-dd_HH-mm-ss-fff'))
New-Item -ItemType Directory -Path $archive | Out-Null
foreach ($relative in @('compose.yaml', 'docker-compose.yml', 'README.md', 'scripts/wp.ps1', 'scripts/backup.ps1')) {
    $current = Join-Path $labRoot $relative
    if (Test-Path -LiteralPath $current) {
        $destination = Join-Path $archive $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force | Out-Null
        Copy-Item -LiteralPath $current -Destination $destination
    }
}
foreach ($relative in @('compose.yaml', '.env.example', '.gitignore', 'README.md')) {
    Copy-Item -LiteralPath (Join-Path $sourceRoot $relative) -Destination (Join-Path $labRoot $relative) -Force
}
foreach ($folder in @('scripts', 'php', 'mu-plugins')) {
    $destination = Join-Path $labRoot $folder
    New-Item -ItemType Directory -Path $destination -Force | Out-Null
    foreach ($file in Get-ChildItem -LiteralPath (Join-Path $sourceRoot $folder) -File) {
        Copy-Item -LiteralPath $file.FullName -Destination (Join-Path $destination $file.Name) -Force
    }
}
$legacy = Join-Path $labRoot 'docker-compose.yml'
if (Test-Path -LiteralPath $legacy) {
    Move-Item -LiteralPath $legacy -Destination (Join-Path $archive 'docker-compose.original.yml')
}
Write-Output "Laboratory configuration installed. Previous files preserved: $archive"
