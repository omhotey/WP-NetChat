#requires -Version 7.0
param([Parameter(ValueFromRemainingArguments = $true)][string[]] $WpArguments)
. (Join-Path $PSScriptRoot 'common.ps1')
Invoke-LabCompose (@('run', '--rm', '--no-deps', 'wpcli') + $WpArguments)
