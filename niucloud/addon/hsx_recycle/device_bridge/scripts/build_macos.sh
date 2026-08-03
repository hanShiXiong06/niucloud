#!/bin/bash

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

/usr/bin/python3 -m PyInstaller build_macos.spec --clean --noconfirm

echo "Built: $ROOT/dist/hsx_device_bridge"
du -sh "$ROOT/dist/hsx_device_bridge"
