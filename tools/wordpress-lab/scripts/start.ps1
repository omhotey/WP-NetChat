#requires -Version 7.0
. (Join-Path $PSScriptRoot 'common.ps1')
Initialize-LabEnvironment
$labEnvironment = Get-LabEnvironment
$source = $labEnvironment['NETCHAT_PROJECT_PATH']
if (-not $source) { $source = '../WP NetChat' }
if (-not [IO.Path]::IsPathRooted($source)) { $source = Join-Path $script:LabRoot $source }
if (-not (Test-Path -LiteralPath (Join-Path $source 'netchat.php'))) {
    throw 'NetChat source not found. Set NETCHAT_PROJECT_PATH in .env.'
}
& docker info --format '{{.ServerVersion}}'
if ($LASTEXITCODE -ne 0) { throw 'Start Docker Desktop with the Linux engine first.' }
Invoke-LabCompose @('config', '--quiet')
Invoke-LabCompose @('up', '-d', '--wait', '--wait-timeout', '180')
Invoke-LabCompose @('run', '--rm', '--no-deps', '--entrypoint', 'sh', 'wpcli', '/lab-scripts/bootstrap.sh')
$port = $labEnvironment['WORDPRESS_PORT']
if (-not $port) { $port = '8080' }
$mailPort = $labEnvironment['MAILPIT_PORT']
if (-not $mailPort) { $mailPort = '8025' }
Write-Output "WordPress: http://localhost:$port/netchat-test/"
Write-Output "Admin: http://localhost:$port/wp-admin/"
Write-Output "Mailpit: http://localhost:$mailPort"
Write-Output 'Administrator credentials are stored in .env. Site data is preserved on repeated starts.'
