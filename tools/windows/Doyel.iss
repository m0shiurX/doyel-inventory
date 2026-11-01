; First-install candidate. Requires a reviewed current-schema SQL export.
; Build the external payload with build-bundle.php before compiling this file.
#ifndef AppVersion
  #define AppVersion "1.0.0"
#endif

[Setup]
AppId=DoyelOffline
AppName=Doyel
AppVersion={#AppVersion}
DefaultDirName={autopf}\Doyel
DisableDirPage=yes
PrivilegesRequired=admin
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
MinVersion=10.0
OutputDir=output
OutputBaseFilename=DoyelSetup-{#AppVersion}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
UninstallDisplayName=Doyel (retains business data)
SetupLogging=yes

[Files]
Source: "payload\apache\*"; DestDir: "{app}\apache"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\mysql\*"; DestDir: "{app}\mysql"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\www\*"; DestDir: "{app}\www"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\installer\manage.php"; DestDir: "{app}\installer"; Flags: ignoreversion
Source: "payload\installer\launch.php"; DestDir: "{app}\installer"; Flags: ignoreversion
Source: "payload\prerequisites\vc_redist.x64.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall

[Icons]
Name: "{autodesktop}\Doyel"; Filename: "{app}\php\php-win.exe"; Parameters: "-n ""{app}\installer\launch.php"""; WorkingDir: "{app}"
Name: "{autoprograms}\Doyel"; Filename: "{app}\php\php-win.exe"; Parameters: "-n ""{app}\installer\launch.php"""; WorkingDir: "{app}"

[Code]
var
  DatabasePage: TInputFileWizardPage;

function InitializeSetup: Boolean;
begin
  Result := False;
  if DirExists(ExpandConstant('{commonappdata}\Doyel')) or
     FileExists(ExpandConstant('{autopf}\Doyel\installer\manage.php')) then
  begin
    MsgBox('Doyel files or retained data already exist. This installer supports first installation only. Use the migration/repair procedure; do not delete business data.', mbError, MB_OK);
    Exit;
  end;
  Result := True;
end;

procedure InitializeWizard;
begin
  DatabasePage := CreateInputFilePage(wpSelectDir, 'Prepared Doyel database',
    'Select a verified MySQL 8.4 export of the current Doyel schema.',
    'This is an owner-run import. Legacy databases must be migrated and verified first. Keep the original backup. SQL must target the selected database without CREATE DATABASE or USE statements.');
  DatabasePage.Add('Prepared SQL export:', 'SQL files|*.sql', '.sql');
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = DatabasePage.ID then
  begin
    Result := FileExists(DatabasePage.Values[0]);
    if not Result then MsgBox('Select the prepared SQL export.', mbError, MB_OK);
  end;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  Code: Integer;
begin
  Result := '';
  if not FileExists(DatabasePage.Values[0]) then
  begin
    Result := 'A prepared database export is required. Silent database provisioning is not supported.';
    Exit;
  end;
  ExtractTemporaryFile('vc_redist.x64.exe');
  if not Exec(ExpandConstant('{tmp}\vc_redist.x64.exe'), '/install /quiet /norestart',
    '', SW_HIDE, ewWaitUntilTerminated, Code) then
    Result := 'Could not start the Visual C++ runtime installer.'
  else if (Code = 3010) or (Code = 1641) then
  begin
    NeedsRestart := True;
    Result := 'Restart Windows to complete the Visual C++ runtime installation, then run Doyel Setup again.';
  end
  else if (Code <> 0) and (Code <> 1638) then
    Result := 'Visual C++ runtime installation failed. Exit code: ' + IntToStr(Code);
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  Code: Integer;
begin
  if CurStep = ssPostInstall then
  begin
    if not Exec(ExpandConstant('{app}\php\php.exe'),
      '-n "' + ExpandConstant('{app}\installer\manage.php') + '" install "' +
      ExpandConstant('{app}') + '" "' + DatabasePage.Values[0] + '"',
      ExpandConstant('{app}'), SW_HIDE, ewWaitUntilTerminated, Code) then
      RaiseException('Could not start Doyel configuration.');
    if Code <> 0 then
      RaiseException('Doyel configuration failed. See ProgramData\Doyel\setup.log if created. Private data is preserved; inspect it before retrying.');
  end;
end;

function InitializeUninstall: Boolean;
var
  Code: Integer;
begin
  Result := Exec(ExpandConstant('{app}\php\php.exe'),
    '-n "' + ExpandConstant('{app}\installer\manage.php') + '" uninstall "' +
    ExpandConstant('{app}') + '"', ExpandConstant('{app}'), SW_HIDE,
    ewWaitUntilTerminated, Code);
  if Result then Result := Code = 0;
  if not Result then
    MsgBox('Could not safely remove Doyel services. Uninstall stopped; binaries and data must remain available.', mbError, MB_OK);
end;
