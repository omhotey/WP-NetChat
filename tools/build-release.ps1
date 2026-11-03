#requires -Version 7.0
param([switch] $VerifyOnly, [string] $PythonPath = '')
$ErrorActionPreference='Stop'
if ($PythonPath) {
    if (-not (Test-Path -LiteralPath $PythonPath -PathType Leaf)) { throw 'PythonPath must point to an existing Python 3 executable.' }
    $pythonExecutable = (Get-Item -LiteralPath $PythonPath).FullName
} else {
    $python = Get-Command python3,python -ErrorAction SilentlyContinue | Where-Object { $_.Source -notmatch '[\\/]Microsoft[\\/]WindowsApps[\\/]python' } | Select-Object -First 1
    if (!$python) { throw 'Python 3 is required. Pass -PythonPath with an installed interpreter.' }
    $pythonExecutable = $python.Source
}
$buildArguments = @((Join-Path $PSScriptRoot 'build-release.py'))
if ($VerifyOnly) { $buildArguments += '--verify-only' }
& $pythonExecutable @buildArguments
if ($LASTEXITCODE -ne 0) { throw 'Release verification/build failed.' }
