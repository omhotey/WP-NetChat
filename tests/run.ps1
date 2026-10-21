#requires -Version 7.0
param([string] $LabRoot = 'D:\Project\WordPress-Lab', [switch] $Browser)
$ErrorActionPreference = 'Stop'
if ($Browser) { throw 'General Browser runner writes legacy results. Run service-forms-e2e.mjs separately with NETCHAT_TEST_OUTPUT.' }
$projectRoot = Split-Path -Parent $PSScriptRoot
$releaseRoot = Join-Path $projectRoot 'dist/release-1.2.2'
$historicalArchive = Join-Path $releaseRoot 'historical-v1.0.0.zip'
if (-not (Test-Path -LiteralPath $historicalArchive -PathType Leaf)) { throw 'Download and verify historical-v1.0.0.zip as described in tests/README.md before running tests.' }
if ((Get-FileHash -LiteralPath $historicalArchive -Algorithm SHA256).Hash -ne 'EAD4077F9A746370715D286546927F449E2B28EF3434F03C1A9C587FD1D38B7D') { throw 'Historical v1.0.0 ZIP hash mismatch; stop before running tests.' }
$compose = @('compose', '--project-directory', $LabRoot, '-f', (Join-Path $LabRoot 'compose.yaml'))
function Invoke-TestDocker([string[]] $Arguments) {
    & docker @compose @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker test failed (exit $LASTEXITCODE)." }
}
foreach ($file in Get-ChildItem -LiteralPath (Join-Path $projectRoot 'tests') -Filter '*.test.cjs') {
    & node $file.FullName
    if ($LASTEXITCODE -ne 0) { throw "Node test failed: $($file.Name)" }
}
Invoke-TestDocker @('exec', '-T', 'wordpress', 'sh', '-c', 'find /var/www/html/wp-content/plugins/netchat -name "*.php" -print0 | xargs -0 -r -n 1 php -l')
foreach ($file in Get-ChildItem -LiteralPath $PSScriptRoot -Filter 'storage*.php') {
    Invoke-TestDocker @('exec', '-T', 'wordpress', 'php', ('/var/www/html/wp-content/plugins/netchat/tests/' + $file.Name))
}
Invoke-TestDocker @('run', '--pull', 'never', '--rm', '--no-deps', 'wpcli', '--skip-plugins=netchat', 'eval-file', '/var/www/html/wp-content/plugins/netchat/tests/wp-integration.php')
Invoke-TestDocker @('run', '--pull', 'never', '--rm', '--no-deps', 'wpcli', '--skip-plugins=netchat', 'eval-file', '/var/www/html/wp-content/plugins/netchat/tests/leads-storage.php')
Invoke-TestDocker @('run', '--pull', 'never', '--rm', '--no-deps', 'wpcli', '--skip-plugins=netchat', 'eval-file', '/var/www/html/wp-content/plugins/netchat/tests/service-forms-storage.php')
Invoke-TestDocker @('run', '--pull', 'never', '--rm', '--no-deps', 'wpcli', '--skip-plugins=netchat', 'eval-file', '/var/www/html/wp-content/plugins/netchat/tests/forms-editor-storage.php')
Invoke-TestDocker @('run', '--pull', 'never', '--rm', '--no-deps', 'wpcli', '--skip-plugins=netchat', 'eval-file', '/var/www/html/wp-content/plugins/netchat/tests/forms-lifecycle-storage.php')
Invoke-TestDocker @('run', '--pull', 'never', '--rm', '--no-deps', 'wpcli', '--skip-plugins=netchat', 'eval-file', '/var/www/html/wp-content/plugins/netchat/tests/data-transfer-storage.php')
& (Join-Path $projectRoot 'tools/build-release.ps1')
Invoke-TestDocker @('run', '--pull', 'never', '--rm', '--no-deps', '--entrypoint', 'sh', 'wpcli', '/var/www/html/wp-content/plugins/netchat/tests/release-smoke.sh')
Write-Output 'NetChat checks passed.'
