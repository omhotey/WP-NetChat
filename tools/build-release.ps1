#requires -Version 7.0
param()
$ErrorActionPreference='Stop'
$python=Get-Command python3,python -ErrorAction SilentlyContinue | Select-Object -First 1
if(!$python){throw 'Python 3 is required to reproduce the reviewed ZIP.'}
& $python.Source (Join-Path $PSScriptRoot 'build-release.py')
if($LASTEXITCODE -ne 0){throw 'Release verification/build failed.'}
