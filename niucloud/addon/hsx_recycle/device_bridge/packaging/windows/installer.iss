#ifndef MyAppVersion
  #define MyAppVersion "0.1.1"
#endif

#define MyAppName "HSX Device Bridge"
#define MyAppPublisher "HSX"
#define MyAppExeName "hsx_device_bridge.exe"

[Setup]
AppId={{C7878238-D27D-4D2F-BBD8-E3712890923A}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={localappdata}\Programs\HSX Device Bridge
DefaultGroupName=HSX Device Bridge
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
OutputDir=..\..\dist\installer
OutputBaseFilename=hsx_device_bridge-{#MyAppVersion}-windows-x64-setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
CloseApplications=yes
RestartApplications=no
UninstallDisplayIcon={app}\{#MyAppExeName}

[Files]
Source: "..\..\dist\hsx_device_bridge\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs restartreplace

[Registry]
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "HSXDeviceBridge"; ValueData: """{app}\{#MyAppExeName}"" serve"; Flags: uninsdeletevalue

[Icons]
Name: "{group}\检查设备桥状态"; Filename: "http://127.0.0.1:17890/v1/health"
Name: "{group}\启动设备桥"; Filename: "{app}\{#MyAppExeName}"; Parameters: "serve"
Name: "{group}\卸载设备桥"; Filename: "{uninstallexe}"

[Run]
Filename: "{app}\{#MyAppExeName}"; Parameters: "serve"; Description: "启动设备桥"; Flags: nowait postinstall skipifsilent runhidden

[UninstallRun]
Filename: "{cmd}"; Parameters: "/C taskkill /F /IM {#MyAppExeName}"; Flags: runhidden skipifdoesntexist
