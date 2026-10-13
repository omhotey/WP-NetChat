#requires -Version 7.0
. (Join-Path $PSScriptRoot 'common.ps1')
$backupPath = Join-Path $script:LabRoot ('backups/' + (Get-Date -Format 'yyyy-MM-dd_HH-mm-ss-fff'))
New-Item -ItemType Directory -Path $backupPath | Out-Null

function Save-DockerStream {
    param([string[]] $Arguments, [string] $Destination)
    $startInfo = [Diagnostics.ProcessStartInfo]::new('docker')
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    foreach ($argument in $Arguments) { $startInfo.ArgumentList.Add($argument) }
    $process = [Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    $file = $null
    try {
        [void] $process.Start()
        $errorTask = $process.StandardError.ReadToEndAsync()
        $file = [IO.File]::Open($Destination, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write)
        $process.StandardOutput.BaseStream.CopyTo($file)
        $file.Dispose()
        $file = $null
        $process.WaitForExit()
        $errorText = $errorTask.GetAwaiter().GetResult()
        if ($process.ExitCode -ne 0) { throw "Backup stream failed: $errorText" }
    } finally {
        if ($file) { $file.Dispose() }
        $process.Dispose()
    }
}

$dockerBase = @('compose') + $script:ComposeArguments
Save-DockerStream ($dockerBase + @('exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb-dump --user=root --single-transaction --default-character-set=utf8mb4 "$MARIADB_DATABASE"')) (Join-Path $backupPath 'database.sql')
Save-DockerStream ($dockerBase + @('exec', '-T', 'wordpress', 'tar', '-C', '/var/www/html', '--exclude=wp-content/plugins/netchat', '--exclude=wp-content/debug.log', '-czf', '-', 'wp-content', 'wp-config.php')) (Join-Path $backupPath 'wordpress-files.tar.gz')
$manifest = Get-ChildItem -LiteralPath $backupPath -File | ForEach-Object {
    [pscustomobject]@{ Name = $_.Name; Bytes = $_.Length; SHA256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash }
}
$manifest | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $backupPath 'manifest.json') -Encoding utf8
Write-Output "Backup created: $backupPath"
Write-Output 'NetChat source is a separate bind mount; preserve its Git revision and local changes separately.'
