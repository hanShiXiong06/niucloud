param([string]$Version)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
if ($Version -notmatch '^\d+\.\d+\.\d+$') { throw "Invalid bridge version" }

$VsWhere = "${env:ProgramFiles(x86)}\Microsoft Visual Studio\Installer\vswhere.exe"
if (-not (Test-Path $VsWhere)) { throw "Visual Studio C++ build tools are required on the build machine" }
$VsPath = & $VsWhere -latest -products * -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath
if ($LASTEXITCODE -ne 0 -or -not $VsPath) { throw "Visual Studio C++ build tools were not found" }
& (Join-Path $VsPath "Common7\Tools\Launch-VsDevShell.ps1") -Arch amd64 -HostArch amd64 -SkipAutomaticLocation
if (-not (Get-Command cl.exe -ErrorAction SilentlyContinue)) { throw "MSVC compiler was not found" }

$Build = Join-Path $Root "build\native"
New-Item -ItemType Directory -Force -Path $Build | Out-Null
Set-Content -Path (Join-Path $Build "bridge_version.h") -Value "#define HSX_BRIDGE_VERSION `"$Version`"" -Encoding ascii
Push-Location $Build
try {
    $Common = @('/nologo', '/std:c++17', '/EHsc', '/MT', '/O2', '/W4', '/utf-8', '/DUNICODE', '/D_UNICODE', '/D_WIN32_WINNT=0x0A00')
    & cl.exe @Common (Join-Path $Root "native\windows\test_wpd_contract.cpp") '/Fe:wpd_contract_test.exe'
    if ($LASTEXITCODE -ne 0) { throw "Native contract tests failed to compile" }
    & .\wpd_contract_test.exe
    if ($LASTEXITCODE -ne 0) { throw "Native contract tests failed" }
    & cl.exe @Common '/I.' (Join-Path $Root "native\windows\wpd_reader.cpp") '/Fe:hsx_wpd_reader.exe' '/link' 'ole32.lib' 'PortableDeviceGUIDs.lib' 'Cfgmgr32.lib'
    if ($LASTEXITCODE -ne 0) { throw "Native WPD reader failed to compile" }
    $Check = & .\hsx_wpd_reader.exe --self-check
    if ($LASTEXITCODE -ne 0) { throw "WPD native self-check failed: $Check" }
    $Report = $Check | ConvertFrom-Json
    if (-not $Report.ready -or $Report.version -ne $Version) { throw "Native reader version or COM check failed" }
    $Scan = & .\hsx_wpd_reader.exe --scan
    if ($LASTEXITCODE -ne 0) { throw "WPD scan smoke test failed: $Scan" }
    $ScanReport = $Scan | ConvertFrom-Json
    if ($ScanReport.version -ne $Version -or $null -eq $ScanReport.detected) { throw "WPD scan returned invalid JSON" }
} finally {
    Pop-Location
}
