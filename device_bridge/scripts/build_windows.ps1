param(
    [string]$Version = "0.1.3",
    [string]$AllowedOrigins = ""
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

python -m PyInstaller build_windows.spec --clean --noconfirm

$BridgeExe = "dist\hsx_device_bridge\hsx_device_bridge.exe"
if (-not (Test-Path $BridgeExe)) {
    throw "Windows bridge executable was not generated: $BridgeExe"
}
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

$Installer = "dist\installer\hsx_device_bridge-$Version-windows-x64-setup.exe"
if (-not (Test-Path $Installer)) {
    throw "Windows installer was not generated: $Installer"
}
Write-Host "Built: $Installer"
