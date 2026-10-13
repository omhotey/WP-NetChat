#requires -Version 7.0
. (Join-Path $PSScriptRoot 'common.ps1')
Invoke-LabCompose @('down')
Write-Output 'Stopped. Database, WordPress files and captured email remain in named volumes.'
