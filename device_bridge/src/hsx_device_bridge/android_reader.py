from __future__ import annotations

import ctypes.util
import hashlib
import json
import os
from pathlib import Path
import plistlib
import subprocess
import sys
import threading
import time

from .ios_reader import BridgeReadError
from .mtp_protocol import RESULT_MARKER, probe
from .mtp_snapshot import normalize_snapshot
from . import windows_mtp_reader

_read_lock = threading.Lock()
DISCOVERY_TIMEOUT = 3
DEVICE_TIMEOUT = 8
BATCH_TIMEOUT = 20


def usb_identity(device: dict) -> tuple | None:
    if not device.get("location_id") or not device.get("usb_serial_number"):
        return None
    return (device["vendor_id"], device["product_id"], device["location_id"], device["usb_serial_number"])


def device_id(port: dict, usb_devices: list[dict] | None = None) -> str:
    matches = usb_matches(port, usb_devices or [])
    identity = usb_identity(matches[0]) if len(matches) == 1 else None
    if identity:
        digest = hashlib.sha256(json.dumps(identity).encode("utf-8")).hexdigest()[:32]
        return "mtp:usb:" + digest
    return "mtp:%s:%s:%s:%s" % tuple(port[key] for key in (
        "vendor_id", "product_id", "bus_location", "device_number",
    ))


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
    if not payload.strip():
        return result
    for node in _walk(plistlib.loads(payload)):
        vendor = node.get("idVendor")
        if vendor is None or vendor == 0x05AC or node.get("bDeviceClass") == 9:
            continue
        serial = str(node.get("USB Serial Number", "")).strip()
        bus = int(node.get("locationID", 0)) >> 24
        address = int(node.get("USB Address", -1))
        # IORegistry may omit interface children even for a working MTP phone.
        # This is an identity inventory only; libmtp decides which devices speak MTP.
        result.append({
            "vendor_id": vendor, "product_id": int(node.get("idProduct", 0)),
            "bus_location": bus, "device_number": address,
            "location_id": int(node.get("locationID", 0)),
            "usb_serial_number": serial,
            "name": str(node.get("USB Product Name", "")).strip(),
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


def worker(library: str, discover_only: bool = False, target: dict | None = None) -> None:
    try:
        report = probe(library, discover_only=discover_only, target=target)
    except (OSError, AttributeError, RuntimeError):
        report = {"error": "MTP_WORKER_FAILED"}
    print(RESULT_MARKER + json.dumps(report), flush=True)


def run_worker(library: str, discover_only: bool = False, target: dict | None = None,
               timeout: float = DEVICE_TIMEOUT) -> dict:
    command = [sys.executable]
    if not getattr(sys, "frozen", False):
        command += ["-m", "hsx_device_bridge"]
    command += ["_mtp-worker", "--library", library]
    if discover_only:
        command += ["--discover-only"]
    if target is not None:
        command += ["--target", json.dumps(target)]
    try:
        process = subprocess.run(command, capture_output=True, text=True, errors="replace", timeout=timeout)
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


def usb_matches(port: dict, usb_devices: list[dict]) -> list[dict]:
    return [item for item in usb_devices if (
        "%#06x" % item["vendor_id"] == port.get("vendor_id")
        and "%#06x" % item["product_id"] == port.get("product_id")
        and item["bus_location"] == port.get("bus_location")
        and item["device_number"] == port.get("device_number")
    )]


def usb_port(device: dict) -> dict:
    return {
        "vendor_id": "%#06x" % device["vendor_id"],
        "product_id": "%#06x" % device["product_id"],
        "bus_location": device["bus_location"], "device_number": device["device_number"],
    }


def confirm_usb_identity(expected: dict, deadline: float | None = None) -> dict:
    identity = usb_identity(expected)
    for delay in (0, 0.1, 0.2, 0.4):
        if deadline is not None and time.monotonic() + delay >= deadline:
            raise BridgeReadError("MTP_BATCH_TIMEOUT", "本次读取达到时限，请再次读取尚未完成的设备")
        if delay:
            time.sleep(delay)
        try:
            devices = scan_usb_devices()
        except BridgeReadError:
            continue
        # macOS can reassign the temporary USB address when MTP releases a phone.
        # Only a stable physical port AND the same nonempty USB serial allow that.
        if identity:
            matches = [item for item in devices if (
                item["vendor_id"], item["product_id"], item.get("location_id")
            ) == identity[:3]]
        else:
            matches = usb_matches(usb_port(expected), devices)
        if not matches:
            continue
        if len(matches) == 1 and matches[0]["usb_serial_number"] == expected["usb_serial_number"]:
            return matches[0]
        break
    raise BridgeReadError("MTP_CONNECTION_CHANGED", "无法确认仍是同一台设备，请保持连接后重新读取")


def normalize_device(metadata: dict, usb_devices: list[dict], include_raw: bool = False) -> dict:
    matches = usb_matches(metadata["usb"], usb_devices)
    # Never use the MTP UUID as a factory SN or match multiple phones by model alone.
    serial = matches[0]["usb_serial_number"] if len(matches) == 1 else ""
    return normalize_snapshot(metadata, device_id(metadata["usb"], usb_devices), serial, include_raw)


def capabilities() -> dict:
    if sys.platform == "win32":
        available = bool(windows_mtp_reader.helper_path())
        backend, status = "windows_wpd", "preview"
    else:
        available = bool(library_path())
        backend, status = "macos_libmtp", "available"
    return {
        "android_mtp": available,
        "android_mtp_scope": "generic" if available else "",
        "android_mtp_backend": backend if available else "",
        "android_mtp_status": status if available else "unavailable",
    }


def scan_device_ids() -> list[str]:
    if sys.platform == "win32":
        return windows_mtp_reader.scan_device_ids()
    if sys.platform != "darwin":
        return []
    library = library_path()
    if not library:
        raise BridgeReadError("MTP_UNAVAILABLE", "当前安装包不包含 Mac 安卓读取组件，请更新设备桥")
    if not _read_lock.acquire(blocking=False):
        raise BridgeReadError("MTP_BUSY", "正在读取安卓设备，请稍后重试")
    try:
        report = run_worker(library, discover_only=True, timeout=DISCOVERY_TIMEOUT)
        ports = report.get("detected", [])
        usb_devices = scan_usb_devices() if ports else []
        return [device_id(port, usb_devices) for port in ports]
    finally:
        _read_lock.release()


def read_result(include_raw: bool = False) -> dict:
    if sys.platform == "win32":
        return windows_mtp_reader.read_result(include_raw)
    result = {"data": [], "warnings": []}
    if sys.platform != "darwin":
        return result
    library = library_path()
    if not library:
        raise BridgeReadError("MTP_UNAVAILABLE", "当前安装包不包含 Mac 安卓读取组件，请更新设备桥")
    if not _read_lock.acquire(blocking=False):
        raise BridgeReadError("MTP_BUSY", "正在读取安卓设备，请稍后重试")
    try:
        deadline = time.monotonic() + BATCH_TIMEOUT
        report = run_worker(library, discover_only=True, timeout=DISCOVERY_TIMEOUT)
        ports = report.get("detected", [])
        if not ports:
            return result
        try:
            usb_devices = scan_usb_devices()
        except BridgeReadError as exc:
            usb_devices = []
            result["warnings"].append({"code": exc.code, "message": str(exc), "device_id": ""})
        for port in ports:
            matches = usb_matches(port, usb_devices)
            name = matches[0]["name"] if len(matches) == 1 else "MTP 设备"
            try:
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    raise BridgeReadError("MTP_BATCH_TIMEOUT", "本次读取达到时限，请再次读取尚未完成的设备")
                bound = confirm_usb_identity(matches[0], deadline) if len(matches) == 1 else None
                target = usb_port(bound) if bound else port
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    raise BridgeReadError("MTP_BATCH_TIMEOUT", "本次读取达到时限，请再次读取尚未完成的设备")
                report = run_worker(library, target=target, timeout=min(DEVICE_TIMEOUT, remaining))
                devices = report.get("devices", [])
                if report.get("errors"):
                    raise BridgeReadError("MTP_OPEN_FAILED", "文件传输未获允许、被其他读机工具占用或设备暂不兼容；请解锁、允许访问并关闭其他工具后重试")
                if len(devices) != 1 or devices[0].get("usb") != target:
                    raise BridgeReadError("MTP_NO_DEVICE", "设备已断开或连接发生变化，请重新读取")
                if bound:
                    confirm_usb_identity(bound, deadline)
                result["data"].append(normalize_device(devices[0], [bound] if bound else [], include_raw))
            except BridgeReadError as exc:
                result["warnings"].append({
                    "code": exc.code, "message": "%s：%s" % (name or "MTP 设备", exc),
                    "device_id": device_id(port, usb_devices),
                })
        return result
    finally:
        _read_lock.release()


def read_devices(include_raw: bool = False) -> list[dict]:
    result = read_result(include_raw)
    if not result["data"] and result["warnings"]:
        first = result["warnings"][0]
        raise BridgeReadError(first["code"], first["message"])
    return result["data"]
