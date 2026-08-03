# -*- mode: python ; coding: utf-8 -*-

a = Analysis(
    ["bridge_entry.py"],
    pathex=["src"],
    binaries=[],
    datas=[],
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
