#requires -Version 7.0
param(
    [Parameter(Mandatory = $true)][string] $Destination,
    [string] $LabRoot = 'D:\Project\WordPress-Lab'
)
$ErrorActionPreference = 'Stop'
$exportPath = [IO.Path]::GetFullPath($Destination)
# Prevent accidentally exposing the export through known public/source roots.
foreach ($root in @($LabRoot, (Split-Path -Parent $PSScriptRoot))) {
    $blockedRoot = [IO.Path]::GetFullPath($root).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
    if ($exportPath.StartsWith($blockedRoot, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Choose a private backup directory outside the lab and plugin source.'
    }
}
if (Test-Path -LiteralPath $exportPath) { throw 'Destination already exists; choose a new file.' }
$wpCode = 'if (netchat_get_migration_error()) { WP_CLI::error("NetChat storage is not ready."); } echo wp_json_encode(netchat_get_config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);'
$output = & docker compose --project-directory $LabRoot -f (Join-Path $LabRoot 'compose.yaml') run --rm --no-deps wpcli eval $wpCode
if ($LASTEXITCODE -ne 0) { throw 'Export failed.' }
$json = ($output -join "`n").Trim()
$config = $json | ConvertFrom-Json -AsHashtable
if (-not $config.ContainsKey('links') -or -not $config.ContainsKey('enabled')) { throw 'Unexpected export format.' }
$directory = Split-Path -Parent $exportPath
New-Item -ItemType Directory -Force -Path $directory | Out-Null
[IO.File]::WriteAllText($exportPath, $json + "`n", [Text.UTF8Encoding]::new($false))
Write-Output "Settings exported: $exportPath"

