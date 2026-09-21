#!/bin/bash

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PYTHON="${PYTHON:-/usr/bin/python3}"
VERSION="$("$PYTHON" "$ROOT/scripts/verify_version.py" \
    --binary "$ROOT/dist/hsx_device_bridge/hsx_device_bridge" --expected "${1:-}")"
ALLOWED_ORIGINS="${2:-http://localhost,http://127.0.0.1}"
PKG_ROOT="$ROOT/build/pkgroot"
SCRIPTS_ROOT="$ROOT/build/pkgscripts"
OUTPUT="$ROOT/dist/hsx_device_bridge-${VERSION}-macos-arm64.pkg"

rm -rf "$PKG_ROOT" "$SCRIPTS_ROOT"
mkdir -p "$PKG_ROOT/Library/Application Support/hsx_device_bridge"
mkdir -p "$PKG_ROOT/Library/LaunchAgents" "$SCRIPTS_ROOT"

cp -R "$ROOT/dist/hsx_device_bridge" "$PKG_ROOT/Library/Application Support/hsx_device_bridge/"
cp "$ROOT/packaging/macos/com.hsx.device-bridge.plist" "$PKG_ROOT/Library/LaunchAgents/"
/usr/libexec/PlistBuddy -c "Set :EnvironmentVariables:HSX_DEVICE_BRIDGE_ORIGINS $ALLOWED_ORIGINS" \
    "$PKG_ROOT/Library/LaunchAgents/com.hsx.device-bridge.plist"
cp "$ROOT/packaging/macos/postinstall" "$SCRIPTS_ROOT/postinstall"
chmod 755 "$SCRIPTS_ROOT/postinstall"
COMPONENTS="$ROOT/build/pkg-components.plist"
pkgbuild --analyze --root "$PKG_ROOT" "$COMPONENTS"
"$PYTHON" "$ROOT/scripts/macos_components.py" "$COMPONENTS"

pkgbuild \
    --root "$PKG_ROOT" \
    --scripts "$SCRIPTS_ROOT" \
    --component-plist "$COMPONENTS" \
    --identifier "com.hsx.device-bridge" \
    --version "$VERSION" \
    --install-location "/" \
    "$OUTPUT"

echo "Packaged: $OUTPUT"
du -sh "$OUTPUT"
