from __future__ import annotations

import socket
import subprocess
import sys
from typing import Any


def _apple_service_state() -> str:
    if sys.platform != "win32":
        return "not_applicable"
    creation_flags = getattr(subprocess, "CREATE_NO_WINDOW", 0)
    try:
        result = subprocess.run(
            ["sc.exe", "query", "Apple Mobile Device Service"],
            capture_output=True,
            text=True,
            timeout=3,
            creationflags=creation_flags,
            check=False,
        )
    except (OSError, subprocess.SubprocessError):
        return "unknown"
    output = f"{result.stdout}\n{result.stderr}".upper()
    if "RUNNING" in output:
        return "running"
    if result.returncode == 0:
        return "stopped"
    return "missing"


def _usbmux_reachable() -> bool:
    if sys.platform != "win32":
        return False
    try:
        with socket.create_connection(("127.0.0.1", 27015), timeout=0.35):
            return True
    except OSError:
        return False


def apple_driver_diagnostics() -> dict[str, Any]:
    if sys.platform != "win32":
        return {
            "platform": sys.platform,
            "required": False,
            "ready": True,
            "service_state": "not_applicable",
            "message": "",
        }

    service_state = _apple_service_state()
    usbmux_reachable = _usbmux_reachable()
    ready = service_state == "running" or usbmux_reachable
    if ready:
        message = "苹果设备驱动服务正常"
    elif service_state == "stopped":
        message = "苹果设备驱动已安装但未启动，请打开爱思助手或重启电脑后再试"
    else:
        message = "未检测到苹果设备驱动，请先安装爱思助手并使用其驱动修复功能"
    return {
        "platform": "windows",
        "required": True,
        "ready": ready,
        "service_state": service_state,
        "usbmux_reachable": usbmux_reachable,
        "message": message,
    }
