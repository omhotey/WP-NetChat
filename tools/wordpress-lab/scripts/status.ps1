#requires -Version 7.0
. (Join-Path $PSScriptRoot 'common.ps1')
Invoke-LabCompose @('ps', '-a')
Invoke-LabCompose @('run', '--rm', '--no-deps', 'wpcli', 'core', 'version')
Invoke-LabCompose @('run', '--rm', '--no-deps', 'wpcli', 'plugin', 'get', 'netchat', '--fields=name,status,version', '--format=json')
