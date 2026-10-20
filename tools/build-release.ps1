#requires -Version 7.0
param([string] $OutputDirectory = (Join-Path $PSScriptRoot '../dist/release-1.2.1'), [ValidateSet('netchat','WP-NetChat-main')][string] $PluginDirectoryName='netchat')
$ErrorActionPreference='Stop'
$projectRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$outputRoot=[IO.Path]::GetFullPath($OutputDirectory)
$approved=[IO.Path]::GetFullPath((Join-Path $projectRoot 'dist/release-1.2.1'))
if($outputRoot -ne $approved){throw 'Output must be dist/release-1.2.1; existing dist artifacts are protected.'}
$header=Get-Content -LiteralPath (Join-Path $projectRoot 'netchat.php') -Raw
if($header -notmatch 'Version:\s+([0-9]+\.[0-9]+\.[0-9]+)'){throw 'Plugin version missing.'}
$version=$Matches[1]
$readme=Get-Content -LiteralPath (Join-Path $projectRoot 'readme.txt') -Raw
if($readme -notmatch "(?m)^Stable tag: $([regex]::Escape($version))\s*$"){throw 'Plugin/readme version mismatch.'}
$paths=@('netchat.php','uninstall.php','readme.txt','LICENSE','includes/service-presets.json')
$paths+=Get-ChildItem -LiteralPath (Join-Path $projectRoot 'includes') -File -Filter '*.php' | ForEach-Object {'includes/'+$_.Name}
foreach($folder in @('assets','languages')){
 $paths+=Get-ChildItem -LiteralPath (Join-Path $projectRoot $folder) -File -Recurse | Where-Object {$_.Extension -in @('.php','.css','.js','.svg','.mo','.po','.pot')} | ForEach-Object {[IO.Path]::GetRelativePath($projectRoot,$_.FullName).Replace('\','/')}
}
$paths=@($paths | Sort-Object -Unique)
$files=@(foreach($path in $paths){$source=Join-Path $projectRoot $path;if(!(Test-Path -LiteralPath $source -PathType Leaf)){throw "Missing: $path"};if((Get-Item -LiteralPath $source).Attributes -band [IO.FileAttributes]::ReparsePoint){throw "Reparse source: $path"};[ordered]@{path="$PluginDirectoryName/$path";size=(Get-Item -LiteralPath $source).Length;sha256=(Get-FileHash -LiteralPath $source).Hash.ToLowerInvariant()}})
New-Item -ItemType Directory -Force -Path $outputRoot | Out-Null
$name=if($PluginDirectoryName -eq 'netchat'){'netchat-wp.zip'}else{'netchat-upgrade-WP-NetChat-main.zip'}
$zipPath=Join-Path $outputRoot $name
$temp=Join-Path $outputRoot ([guid]::NewGuid().ToString()+'.tmp')
$manifestPath=Join-Path $outputRoot ($name.Replace('.zip','.manifest.json'))
$manifestTemp=Join-Path $outputRoot ([guid]::NewGuid().ToString()+'.tmp')
try{
 $zip=[IO.Compression.ZipArchive]::new([IO.File]::Open($temp,[IO.FileMode]::CreateNew),[IO.Compression.ZipArchiveMode]::Create)
 try{foreach($path in $paths){$entry=$zip.CreateEntry("$PluginDirectoryName/$path",[IO.Compression.CompressionLevel]::Optimal);$entry.LastWriteTime=[DateTimeOffset]::Parse("2026-10-20T00:00:00Z");$inputStream=[IO.File]::OpenRead((Join-Path $projectRoot $path));try{$entryStream=$entry.Open();try{$inputStream.CopyTo($entryStream)}finally{$entryStream.Dispose()}}finally{$inputStream.Dispose()}}}finally{$zip.Dispose()}
 $read=[IO.Compression.ZipFile]::OpenRead($temp)
 try{
  if($read.Entries.Count -ne $files.Count){throw 'ZIP count mismatch'}
  foreach($file in $files){$entry=$read.GetEntry($file.path);if(!$entry -or $entry.Length -ne $file.size){throw "ZIP entry mismatch: $($file.path)"};$stream=$entry.Open();try{$hasher=[Security.Cryptography.SHA256]::Create();try{$hash=[BitConverter]::ToString($hasher.ComputeHash($stream)).Replace('-','').ToLowerInvariant()}finally{$hasher.Dispose()}}finally{$stream.Dispose()};if($hash -ne $file.sha256){throw "ZIP hash mismatch: $($file.path)"}}
 }finally{$read.Dispose()}
 $manifest=[ordered]@{version=$version;archive=$name;plugin_root=$PluginDirectoryName;sha256=(Get-FileHash -LiteralPath $temp).Hash.ToLowerInvariant();files=$files}
 [IO.File]::WriteAllText($manifestTemp,($manifest | ConvertTo-Json -Depth 5))
 # Same-directory replacement is atomic for each file; a consumer must verify the manifest archive hash.
 [IO.File]::Move($temp,$zipPath,$true)
 [IO.File]::Move($manifestTemp,$manifestPath,$true)
 Write-Output "Built $zipPath; $($files.Count) verified entries; SHA256 $($manifest.sha256)"
}finally{foreach($ownTemp in @($temp,$manifestTemp)){if(Test-Path -LiteralPath $ownTemp){Remove-Item -LiteralPath $ownTemp}}}
