param([string]$Bundle,[string]$ExpectedBootstrap)
$ErrorActionPreference='Stop'
$Bundle=[IO.Path]::GetFullPath($Bundle).TrimEnd('\','/')
$principal=[Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if(-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){
 $root64=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($Bundle))
 # The elevated window closes on exit, so show any refusal before it disappears.
 $command='try{$r=[Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('''+$root64+'''));$b=[IO.File]::ReadAllBytes((Join-Path $r ''Update-Preflight.ps1''));$h=([BitConverter]::ToString([Security.Cryptography.SHA256]::Create().ComputeHash($b))).Replace(''-'','''').ToLowerInvariant();if($h -ne '''+$ExpectedBootstrap+'''){throw ''Bootstrap checksum changed''};&([ScriptBlock]::Create([Text.Encoding]::UTF8.GetString($b))) $r '''+$ExpectedBootstrap+'''}catch{Write-Host (''Update refused: ''+$_.Exception.Message);Read-Host ''Press Enter to close''|Out-Null;exit 1}'
 $encoded=[Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($command))
 $p=Start-Process -FilePath (Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe') -ArgumentList @('-NoLogo','-NoProfile','-EncodedCommand',$encoded) -Verb RunAs -Wait -PassThru
 exit $p.ExitCode
}
function Check-Source($path){$item=Get-Item -LiteralPath $path -Force;if($item.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Linked update content'}}
Check-Source $Bundle
$manifestBytes=[IO.File]::ReadAllBytes((Join-Path $Bundle 'SHA256SUMS.json'))
if($manifestBytes.Length -gt 131072){throw 'Manifest too large'}
$manifest=[Text.Encoding]::UTF8.GetString($manifestBytes)|ConvertFrom-Json
$properties=@($manifest.PSObject.Properties)
if($properties.Count -lt 5 -or $properties.Count -gt 512){throw 'Invalid file list'}
$expected=@{};$seen=@{}
foreach($property in $properties){
 $relative=$property.Name
 if($relative.Length -gt 200 -or $relative -notmatch '^[A-Za-z0-9_ ./-]+$' -or @($relative.Split('/')|Where-Object {$_ -eq '' -or $_ -eq '.' -or $_ -eq '..'}).Count -gt 0 -or $property.Value -notmatch '^[a-f0-9]{64}$'){throw 'Unsafe manifest path'}
 $key=$relative.ToLowerInvariant();if($seen.ContainsKey($key)){throw 'Colliding file names'};$seen[$key]=$true
 if($relative -notmatch '^(www|maintenance)/' -and $relative -notin @('Update.php','Update-Doyel.bat','Update-Preflight.ps1','README.txt','package-policy.json')){throw 'Unexpected root content'}
 if($relative -match '^(www|maintenance)/' -and $relative -notmatch '\.(php|js|css|png|gif|jpg|bat)$'){throw 'Runtime payload forbidden'}
 $path=Join-Path $Bundle $relative;Check-Source $path
 if((Get-Item -LiteralPath $path).Length -gt 16777216 -or (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant() -ne $property.Value){throw 'Update checksum mismatch'}
 $expected[$relative]=$property.Value
}
$actual=@(Get-ChildItem -LiteralPath $Bundle -Force -Recurse)
foreach($item in $actual){Check-Source $item.FullName;if(-not $item.PSIsContainer){$relative=$item.FullName.Substring($Bundle.Length+1).Replace('\','/');if($relative -ne 'SHA256SUMS.json' -and -not $expected.ContainsKey($relative)){throw 'Unexpected update file'}}}
$stage=Join-Path $env:ProgramData ('DoyelUpdate-'+[Guid]::NewGuid().ToString('N'))
$acl=[Security.AccessControl.DirectorySecurity]::new();$acl.SetAccessRuleProtection($true,$false)
foreach($sid in @('S-1-5-18','S-1-5-32-544')){$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new($sid),'FullControl','ContainerInherit,ObjectInherit','None','Allow'))}
if(Test-Path -LiteralPath $stage){throw 'New protected stage required'}
[IO.Directory]::CreateDirectory($stage,$acl)|Out-Null
$actualAcl=Get-Acl -LiteralPath $stage;if(-not $actualAcl.AreAccessRulesProtected -or @($actualAcl.Access).Count -ne 2){throw 'Staging ACL mismatch'}
foreach($relative in $expected.Keys){$source=Join-Path $Bundle $relative;$target=Join-Path $stage $relative;$parent=Split-Path -Parent $target;if(-not(Test-Path -LiteralPath $parent)){New-Item -ItemType Directory -Path $parent -Force|Out-Null};$reader=[IO.File]::OpenRead($source);$writer=[IO.File]::Open($target,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None);try{$reader.CopyTo($writer);$writer.Flush($true)}finally{$reader.Dispose();$writer.Dispose()};if((Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expected[$relative]){throw 'Staged checksum mismatch'}}
[IO.File]::WriteAllBytes((Join-Path $stage 'SHA256SUMS.json'),$manifestBytes)
$php=Join-Path $env:ProgramFiles 'Doyel\php\php.exe';$ini=Join-Path $env:ProgramData 'Doyel\config\php.ini'
Write-Host ('Verified owner-only update copy: '+$stage)
& $php '-c' $ini (Join-Path $stage 'Update.php')
exit $LASTEXITCODE
