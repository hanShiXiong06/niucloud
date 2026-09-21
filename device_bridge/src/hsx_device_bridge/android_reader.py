from __future__ import annotations

import ctypes.util
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import plistlib
import subprocess
import sys
import threading

from .ios_reader import BridgeReadError
from .mtp_protocol import RESULT_MARKER, probe

_read_lock = threading.Lock()
SAMSUNG_VENDOR = 0x04E8


def library_path() -> str:
    if sys.platform != "darwin":
        return ""
    bundled = Path(getattr(sys, "_MEIPASS", "")) / "native" / "libmtp.9.dylib"
    if getattr(sys, "frozen", False):
        return str(bundled) if bundled.is_file() else ""
    return os.getenv("HSX_DEVICE_BRIDGE_LIBMTP", "") or ctypes.util.find_library("mtp") or ""


def _walk(nodes):
    for node in nodes:
        yield node
        yield from _walk(node.get("IORegistryEntryChildren", []))


def parse_usb_devices(payload: bytes) -> list[dict]:
    result = []
    for node in _walk(plistlib.loads(payload)):
        name = str(node.get("USB Product Name", ""))
        if node.get("idVendor") != SAMSUNG_VENDOR or "android" not in name.lower():
            continue
        serial = str(node.get("USB Serial Number", "")).strip()
        bus = int(node.get("locationID", 0)) >> 24
        address = int(node.get("USB Address", -1))
        interfaces = list(_walk(node.get("IORegistryEntryChildren", [])))
        has_mtp = any(
            child.get("IORegistryEntryName") == "MTP" or child.get("bInterfaceClass") == 6
            for child in interfaces
        )
        result.append({
            "vendor_id": SAMSUNG_VENDOR, "product_id": int(node.get("idProduct", 0)),
            "bus_location": bus, "device_number": address,
            "usb_serial_number": serial, "mtp_available": has_mtp,
            "id": "mtp:samsung:%s:%s:%s" % (bus, address, serial),
        })
    return result


def scan_usb_devices() -> list[dict]:
    if sys.platform != "darwin":
        return []
    try:
        process = subprocess.run(
            ["/usr/sbin/ioreg", "-a", "-r", "-c", "IOUSBHostDevice"],
            capture_output=True, timeout=3, check=True,
        )
        return parse_usb_devices(process.stdout)
    except (OSError, ValueError, plistlib.InvalidFileException, subprocess.SubprocessError) as exc:
        raise BridgeReadError("USB_SCAN_FAILED", "查询安卓 USB 连接失败，请重试") from exc


def worker(library: str) -> None:
    try:
        report = probe(library, SAMSUNG_VENDOR)
    except (OSError, AttributeError, RuntimeError):
        report = {"error": "MTP_WORKER_FAILED"}
    print(RESULT_MARKER + json.dumps(report), flush=True)


def run_worker(library: str) -> dict:
    command = [sys.executable]
    if not getattr(sys, "frozen", False):
        command += ["-m", "hsx_device_bridge"]
    command += ["_mtp-worker", "--library", library]
    try:
        process = subprocess.run(command, capture_output=True, text=True, errors="replace", timeout=10)
    except subprocess.TimeoutExpired as exc:
        raise BridgeReadError("MTP_TIMEOUT", "读取安卓设备超时，请解锁手机并选择文件传输，关闭其他读机工具后重试") from exc
    except OSError as exc:
        raise BridgeReadError("MTP_WORKER_FAILED", "无法启动安卓读取程序，请重新安装设备桥") from exc
    _, marker, data = process.stdout.partition(RESULT_MARKER)
    if process.returncode or not marker:
        raise BridgeReadError("MTP_WORKER_FAILED", "安卓读取程序异常，请重试或重新安装设备桥")
    try:
        report, _ = json.JSONDecoder().raw_decode(data)
    except ValueError as exc:
        raise BridgeReadError("MTP_INVALID_RESULT", "安卓读取结果不完整，请重新连接手机") from exc
    if not isinstance(report, dict) or report.get("error"):
        raise BridgeReadError("MTP_WORKER_FAILED", "安卓读取组件暂不可用，请重新安装设备桥")
    return report


def normalize_device(metadata: dict, usb_devices: list[dict], include_raw: bool = False) -> dict:
    port = metadata.get("usb", {})
    matches = [item for item in usb_devices if (
        "%#06x" % item["vendor_id"] == port.get("vendor_id")
        and "%#06x" % item["product_id"] == port.get("product_id")
        and item["bus_location"] == port.get("bus_location")
        and item["device_number"] == port.get("device_number")
    )]
    # Never use the MTP UUID as a factory SN or match multiple phones by model alone.
    serial = matches[0]["usb_serial_number"] if len(matches) == 1 else ""
    model = str(metadata.get("model_code", "")).strip()
    snapshot = {
        "schema_version": "hsx.device.snapshot.v1",
        "captured_at": datetime.now(timezone.utc).isoformat(),
        "source": "usb_mtp", "platform": "android",
        "identity": {
            "imei": "", "imei2": "", "serial_number": serial,
            "serial_number_source": "usb_descriptor" if serial else "",
            "serial_number_verified": False,
            "usb_serial_number": serial, "mtp_serial_number": metadata.get("serial_number", ""),
        },
        "hardware": {"product_type": model, "model_number": model, "manufacturer": metadata.get("manufacturer", "")},
        "display": {"device_name": metadata.get("friendly_name") or model, "model_hint": model, "color": "", "capacity": ""},
        "system": {"version": "", "firmware_raw": metadata.get("device_version_raw", "")},
        "battery": {"level_percent": metadata.get("battery_level_percent")},
        "not_read_fields": metadata.get("not_read_fields", []),
        "warnings": ["SN 来自 USB 序列号，请与手机机身核对；保修查询是否支持需单独确认。"],
    }
    if include_raw:
        snapshot["raw"] = metadata
    return snapshot


def read_devices(include_raw: bool = False) -> list[dict]:
    usb_devices = scan_usb_devices()
    if not usb_devices:
        return []
    library = library_path()
    if not library:
        raise BridgeReadError("MTP_UNAVAILABLE", "当前安装包不包含 Mac 安卓读取组件，请更新设备桥")
    if any(not item["mtp_available"] for item in usb_devices):
        raise BridgeReadError("MTP_MODE_REQUIRED", "已连接三星手机，请解锁并将 USB 用途切换为文件传输")
    if not _read_lock.acquire(blocking=False):
        raise BridgeReadError("MTP_BUSY", "正在读取安卓设备，请稍后重试")
    try:
        report = run_worker(library)
        if report.get("errors"):
            raise BridgeReadError("MTP_OPEN_FAILED", "安卓手机被占用或尚未允许文件传输，请解锁、允许连接，并关闭其他读机工具后重试")
        if not report.get("devices"):
            raise BridgeReadError("MTP_NO_DEVICE", "手机已断开或文件传输未就绪，请重新连接后重试")
        return [normalize_device(item, usb_devices, include_raw) for item in report["devices"]]
    finally:
        _read_lock.release()
