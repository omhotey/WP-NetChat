#requires -Version 7.0
param([string] $LabRoot = 'D:\Project\WordPress-Lab')
$ErrorActionPreference = 'Stop'
$compose = @('compose', '--project-directory', $LabRoot, '-f', (Join-Path $LabRoot 'compose.yaml'))
$read = 'if (netchat_get_migration_error() || !get_option(NETCHAT_SETTINGS_OPTION)) { WP_CLI::error("Migration must succeed before archival."); } if (NETCHAT_DATA !== WP_CONTENT_DIR . "/netchat-data.json") { WP_CLI::error("Unexpected legacy path."); } if (!is_file(NETCHAT_DATA)) { echo "ABSENT"; return; } $raw=file_get_contents(NETCHAT_DATA); if ($raw === false) { WP_CLI::error("Cannot read legacy file."); } echo base64_encode($raw);'
$encoded = & docker @compose run --rm --no-deps wpcli eval $read
if ($LASTEXITCODE -ne 0) { throw 'Could not read legacy settings.' }
$encoded = ($encoded -join '').Trim()
if ($encoded -eq 'ABSENT') { Write-Output 'Legacy JSON is already absent from the public directory.'; return }
$bytes = [Convert]::FromBase64String($encoded)
$backupRoot = Join-Path $LabRoot ('backups/legacy-netchat-' + (Get-Date -Format 'yyyy-MM-dd_HH-mm-ss-fff'))
New-Item -ItemType Directory -Path $backupRoot | Out-Null
$archive = Join-Path $backupRoot 'netchat-data.json'
[IO.File]::WriteAllBytes($archive, $bytes)
$hash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
$expected = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($bytes)).ToLowerInvariant()
if ($hash -ne $expected) { throw 'Archive verification failed; public source retained.' }
$remove = 'if (netchat_get_migration_error()) { WP_CLI::error("Storage became unavailable; source retained."); } if (NETCHAT_DATA !== WP_CONTENT_DIR . "/netchat-data.json" || !is_file(NETCHAT_DATA) || hash_file("sha256", NETCHAT_DATA) !== "' + $hash + '") { WP_CLI::error("Legacy file changed; source retained."); } if (!unlink(NETCHAT_DATA)) { WP_CLI::error("Could not remove public source; archive exists."); } echo "Legacy settings archived outside the web root.";'
& docker @compose run --rm --no-deps wpcli eval $remove
if ($LASTEXITCODE -ne 0) { throw 'Archive created, but public source was not removed.' }
Write-Output "Verified private legacy archive: $archive"
