#!/bin/bash

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PYTHON="${PYTHON:-/usr/bin/python3}"
export PYINSTALLER_CONFIG_DIR="${PYINSTALLER_CONFIG_DIR:-$ROOT/build/pyinstaller-cache}"
"$PYTHON" -m PyInstaller build_macos.spec --clean --noconfirm
"$PYTHON" scripts/verify_version.py --binary "$ROOT/dist/hsx_device_bridge/hsx_device_bridge"

echo "Built: $ROOT/dist/hsx_device_bridge"
du -sh "$ROOT/dist/hsx_device_bridge"
