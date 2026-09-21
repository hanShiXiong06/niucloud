param(
    [string]$Version = "",
    [string]$AllowedOrigins = ""
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

$SourceVersion = (python scripts/verify_version.py).Trim()
if ($LASTEXITCODE -ne 0) { throw "Unable to read bridge source version" }
if (-not $Version) { $Version = $SourceVersion }
if ($Version -ne $SourceVersion) { throw "Requested version differs from source $SourceVersion" }

python -m PyInstaller build_windows.spec --clean --noconfirm
if ($LASTEXITCODE -ne 0) { throw "PyInstaller failed" }

$BridgeExe = "dist\hsx_device_bridge\hsx_device_bridge.exe"
if (-not (Test-Path $BridgeExe)) {
    throw "Windows bridge executable was not generated: $BridgeExe"
}
python scripts/verify_version.py --binary $BridgeExe --expected $Version
if ($LASTEXITCODE -ne 0) { throw "Executable version check failed" }
$SelfCheck = Start-Process -FilePath $BridgeExe -ArgumentList "self-check" -Wait -PassThru
if ($SelfCheck.ExitCode -ne 0) {
    throw "Windows bridge dependency self-check failed with exit code $($SelfCheck.ExitCode)"
}

$Config = @{
    allowed_origins = $AllowedOrigins
} | ConvertTo-Json -Compress
Set-Content -Path "dist\hsx_device_bridge\bridge-config.json" -Value $Config -Encoding UTF8

$Candidates = @(
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles}\Inno Setup 6\ISCC.exe"
)
$Iscc = $Candidates | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
if (-not $Iscc) {
    throw "Inno Setup 6 was not found"
}

New-Item -ItemType Directory -Force -Path "dist\installer" | Out-Null
& $Iscc "/DMyAppVersion=$Version" "packaging\windows\installer.iss"
if ($LASTEXITCODE -ne 0) { throw "Installer packaging failed" }

$Installer = "dist\installer\hsx_device_bridge-$Version-windows-x64-setup.exe"
if (-not (Test-Path $Installer)) {
    throw "Windows installer was not generated: $Installer"
}
Write-Host "Built: $Installer"
