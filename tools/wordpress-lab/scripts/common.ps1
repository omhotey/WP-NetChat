#requires -Version 7.0
$ErrorActionPreference = 'Stop'
$script:LabRoot = Split-Path -Parent $PSScriptRoot
$script:ComposeArguments = @('--project-directory', $script:LabRoot, '-f', (Join-Path $script:LabRoot 'compose.yaml'))

function Invoke-LabCompose {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]] $DockerArguments)
    & docker compose @script:ComposeArguments @DockerArguments
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose failed (exit $LASTEXITCODE)." }
}

function Get-LabEnvironment {
    $values = @{}
    $envPath = Join-Path $script:LabRoot '.env'
    if (Test-Path -LiteralPath $envPath) {
        foreach ($line in Get-Content -LiteralPath $envPath) {
            if ($line -match '^([A-Z_][A-Z0-9_]*)=(.*)$') {
                $values[$Matches[1]] = $Matches[2].Trim().Trim('"').Trim("'")
            }
        }
    }
    return $values
}

function Initialize-LabEnvironment {
    $envPath = Join-Path $script:LabRoot '.env'
    if (Test-Path -LiteralPath $envPath) { return }
    $content = Get-Content -LiteralPath (Join-Path $script:LabRoot '.env.example') -Raw
    foreach ($placeholder in @('__DB_PASSWORD__', '__ROOT_PASSWORD__', '__ADMIN_PASSWORD__')) {
        $bytes = [byte[]]::new(24)
        $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        try { $random.GetBytes($bytes) } finally { $random.Dispose() }
        $content = $content.Replace($placeholder, ([BitConverter]::ToString($bytes)).Replace('-', '').ToLowerInvariant())
    }
    [IO.File]::WriteAllText($envPath, $content, [Text.UTF8Encoding]::new($false))
    Write-Output 'Created .env with generated local passwords. Existing .env is always preserved.'
}
