# -*- mode: python ; coding: utf-8 -*-

import os
from pathlib import Path

mtp = Path(os.environ.get("HSX_DEVICE_BRIDGE_LIBMTP", "/opt/homebrew/opt/libmtp/lib/libmtp.9.dylib")).resolve()
usb = Path(os.environ.get("HSX_DEVICE_BRIDGE_LIBUSB", "/opt/homebrew/opt/libusb/lib/libusb-1.0.0.dylib")).resolve()
mtp_license = mtp.parent.parent / "COPYING"
usb_license = usb.parent.parent / "COPYING"
for required in (mtp, usb, mtp_license, usb_license):
    if not required.is_file():
        raise SystemExit("Missing MTP build dependency or license: %s" % required)

a = Analysis(
    ["bridge_entry.py"],
    pathex=["src"],
    binaries=[(str(mtp), "native"), (str(usb), "native")],
    datas=[(str(mtp_license), "licenses/libmtp"), (str(usb_license), "licenses/libusb"),
           ("packaging/THIRD_PARTY_NOTICES.txt", "licenses")],
    hiddenimports=[
        "pymobiledevice3",
        "pymobiledevice3.lockdown",
        "pymobiledevice3.usbmux",
        "pymobiledevice3.services.diagnostics",
    ],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[
        "PyQt5",
        "PyQt6",
        "PySide2",
        "PySide6",
        "flask",
        "adbutils",
        "tkinter",
        "matplotlib",
        "numpy",
        "pandas",
        "IPython",
        "PIL",
        "jedi",
        "parso",
        "prompt_toolkit",
        "wcwidth",
    ],
    noarchive=False,
)

pyz = PYZ(a.pure)

exe = EXE(
    pyz,
    a.scripts,
    [],
    exclude_binaries=True,
    name="hsx_device_bridge",
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=False,
    console=True,
    target_arch="arm64",
)

coll = COLLECT(
    exe,
    a.binaries,
    a.datas,
    strip=False,
    upx=False,
    name="hsx_device_bridge",
)
