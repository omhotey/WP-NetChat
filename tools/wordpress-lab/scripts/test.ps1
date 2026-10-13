#requires -Version 7.0
. (Join-Path $PSScriptRoot 'common.ps1')
Invoke-LabCompose @('exec', '-T', 'wordpress', 'sh', '-c', 'find /var/www/html/wp-content/plugins/netchat -name "*.php" -exec php -l {} \;')
Invoke-LabCompose @('run', '--rm', '--no-deps', 'wpcli', 'eval-file', '/lab-scripts/check-netchat.php')
$labEnvironment = Get-LabEnvironment
$port = $labEnvironment['WORDPRESS_PORT']
if (-not $port) { $port = '8080' }
$mailPort = $labEnvironment['MAILPIT_PORT']
if (-not $mailPort) { $mailPort = '8025' }
foreach ($url in @("http://localhost:$port/netchat-test/", "http://localhost:$port/wp-json/", "http://localhost:$mailPort/api/v1/messages")) {
    $response = Invoke-WebRequest -Uri $url -TimeoutSec 30
    if ($response.StatusCode -ne 200) { throw "HTTP check failed: $url" }
    if ($url.EndsWith('/api/v1/messages')) {
        $mail = $response.Content | ConvertFrom-Json
        if ($mail.total -lt 1) { throw 'Mailpit did not capture the SMTP test message.' }
    }
    Write-Output "HTTP 200: $url"
}
