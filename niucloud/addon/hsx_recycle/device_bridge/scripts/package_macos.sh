#!/bin/bash

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-0.1.0}"
ALLOWED_ORIGINS="${2:-http://localhost,http://127.0.0.1}"
PKG_ROOT="$ROOT/build/pkgroot"
SCRIPTS_ROOT="$ROOT/build/pkgscripts"
OUTPUT="$ROOT/dist/hsx_device_bridge-${VERSION}-macos-arm64.pkg"

if [ ! -x "$ROOT/dist/hsx_device_bridge/hsx_device_bridge" ]; then
    echo "Missing bridge binary. Run scripts/build_macos.sh first."
    exit 1
fi

rm -rf "$PKG_ROOT" "$SCRIPTS_ROOT"
mkdir -p "$PKG_ROOT/Library/Application Support/hsx_device_bridge"
mkdir -p "$PKG_ROOT/Library/LaunchAgents" "$SCRIPTS_ROOT"

cp -R "$ROOT/dist/hsx_device_bridge" "$PKG_ROOT/Library/Application Support/hsx_device_bridge/"
cp "$ROOT/packaging/macos/com.hsx.device-bridge.plist" "$PKG_ROOT/Library/LaunchAgents/"
/usr/libexec/PlistBuddy -c "Set :EnvironmentVariables:HSX_DEVICE_BRIDGE_ORIGINS $ALLOWED_ORIGINS" \
    "$PKG_ROOT/Library/LaunchAgents/com.hsx.device-bridge.plist"
cp "$ROOT/packaging/macos/postinstall" "$SCRIPTS_ROOT/postinstall"
chmod 755 "$SCRIPTS_ROOT/postinstall"

pkgbuild \
    --root "$PKG_ROOT" \
    --scripts "$SCRIPTS_ROOT" \
    --identifier "com.hsx.device-bridge" \
    --version "$VERSION" \
    --install-location "/" \
    "$OUTPUT"

echo "Packaged: $OUTPUT"
du -sh "$OUTPUT"
