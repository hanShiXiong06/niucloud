from __future__ import annotations

import hashlib
import json
from pathlib import Path
import subprocess
import sys
import threading
import time

from . import __version__
from .ios_reader import BridgeReadError
from .mtp_snapshot import normalize_snapshot

DISCOVERY_TIMEOUT = 3
DEVICE_TIMEOUT = 8
BATCH_TIMEOUT = 20
MAX_DEVICES = 64
_read_lock = threading.Lock()

ERROR_MESSAGES = {
    "WPD_UNAVAILABLE": "Windows 便携设备组件不可用，请检查系统更新；Windows N 版需安装媒体功能包",
    "MTP_OPEN_FAILED": "文件传输未获允许或设备被占用，请解锁手机、允许访问并关闭其他读机工具后重试",
    "MTP_ACCESS_DENIED": "手机未允许读取，请解锁并选择文件传输、允许访问后重试",
    "MTP_READ_FAILED": "无法读取设备属性，请确认文件传输连接正常后重试",
    "MTP_CONNECTION_CHANGED": "读取期间 USB 连接已变化，请保持连接后重新读取",
    "MTP_NO_DEVICE": "设备已断开，请重新连接手机并选择文件传输",
    "MTP_UNSUPPORTED": "设备未提供 MTP 文件传输协议，请在手机 USB 设置中选择文件传输",
    "MTP_TOO_MANY_DEVICES": "连接设备过多，请分批连接后读取",
}


def helper_path() -> str:
    if sys.platform != "win32":
        return ""
    if getattr(sys, "frozen", False):
        path = Path(getattr(sys, "_MEIPASS", "")) / "native" / "hsx_wpd_reader.exe"
    else:
        path = Path(__file__).resolve().parents[2] / "build" / "native" / "hsx_wpd_reader.exe"
    return str(path) if path.is_file() else ""


def run_helper(*args: str, timeout: float = DEVICE_TIMEOUT) -> dict:
    executable = helper_path()
    if not executable:
        raise BridgeReadError("MTP_UNAVAILABLE", "当前安装包缺少 Windows 安卓读取组件，请安装新版设备桥")
    try:
        process = subprocess.run(
            [executable, *args], capture_output=True, text=True, encoding="utf-8", errors="strict",
            timeout=timeout, creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
        )
    except subprocess.TimeoutExpired as exc:
        raise BridgeReadError("MTP_TIMEOUT", "读取超时，请解锁手机并选择文件传输，关闭其他读机工具后重试") from exc
    except OSError as exc:
        raise BridgeReadError("MTP_WORKER_FAILED", "Windows 读取程序无法启动，请重新安装设备桥") from exc
    except UnicodeError as exc:
        raise BridgeReadError("MTP_INVALID_RESULT", "设备读取结果编码异常，请重新安装设备桥") from exc
    try:
        report = json.loads(process.stdout)
    except ValueError as exc:
        raise BridgeReadError("MTP_INVALID_RESULT", "设备读取结果不完整，请重试或重新安装设备桥") from exc
    if not isinstance(report, dict):
        raise BridgeReadError("MTP_INVALID_RESULT", "设备读取结果不完整，请重新安装设备桥")
    if report.get("error"):
        code = report["error"]
        message = ERROR_MESSAGES.get(code, "Windows 安卓读取组件异常，请重新安装设备桥")
        raise BridgeReadError(code if code in ERROR_MESSAGES else "MTP_WORKER_FAILED", message)
    if process.returncode != 0:
        raise BridgeReadError("MTP_WORKER_FAILED", "Windows 安卓读取组件异常，请重新安装设备桥")
    if report.get("schema_version") != 1 or report.get("version") != __version__:
        raise BridgeReadError("MTP_VERSION_MISMATCH", "Windows 读取组件版本不一致，请重新安装设备桥")
    return report


def _identity(candidate: dict) -> tuple:
    keys = ("pnp_id", "usb_instance_id", "connection_token", "usb_serial_number")
    if not isinstance(candidate, dict) or any(not isinstance(candidate.get(key), str) for key in keys):
        raise BridgeReadError("MTP_INVALID_RESULT", "设备连接标识不完整，请重新读取")
    if not candidate["pnp_id"] or not candidate["usb_instance_id"] or not candidate["connection_token"]:
        raise BridgeReadError("MTP_INVALID_RESULT", "设备连接标识不完整，请重新读取")
    if not isinstance(candidate.get("usb_serial_unique"), bool):
        raise BridgeReadError("MTP_INVALID_RESULT", "设备序列号来源不明确，请重新读取")
    return tuple(candidate[key] for key in keys) + (candidate["usb_serial_unique"],)


def device_id(candidate: dict) -> str:
    _identity(candidate)
    identity = candidate["usb_instance_id"].casefold()
    # Without a USB serial Windows can reuse a port ID for the next phone.
    if not candidate["usb_serial_unique"] or not candidate["usb_serial_number"]:
        identity += "|" + candidate["connection_token"]
    digest = hashlib.sha256(identity.encode("utf-8")).hexdigest()[:32]
    return "mtp:wpd:" + digest


def _scan(timeout: float = DISCOVERY_TIMEOUT) -> list[dict]:
    report = run_helper("--scan", timeout=timeout)
    candidates = report.get("detected")
    if not isinstance(candidates, list) or len(candidates) > MAX_DEVICES:
        raise BridgeReadError("MTP_INVALID_RESULT", "设备列表不完整，请重新读取")
    result, seen = [], set()
    for candidate in candidates:
        _identity(candidate)
        identifier = device_id(candidate)
        if identifier not in seen:
            seen.add(identifier)
            result.append(candidate)
    return result


def scan_device_ids() -> list[str]:
    if not _read_lock.acquire(blocking=False):
        raise BridgeReadError("MTP_BUSY", "正在读取安卓设备，请稍后重试")
    try:
        return [device_id(item) for item in _scan()]
    finally:
        _read_lock.release()


def read_result(include_raw: bool = False) -> dict:
    if not _read_lock.acquire(blocking=False):
        raise BridgeReadError("MTP_BUSY", "正在读取安卓设备，请稍后重试")
    result = {"data": [], "warnings": []}
    try:
        deadline = time.monotonic() + BATCH_TIMEOUT
        for target in _scan():
            identifier = device_id(target)
            try:
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    raise BridgeReadError("MTP_BATCH_TIMEOUT", "本次读取达到时限，请再次读取尚未完成的设备")
                # Bind the subprocess to the exact connection discovered, never just a model name.
                report = run_helper("--read", target["pnp_id"], target["connection_token"],
                                    timeout=min(DEVICE_TIMEOUT, remaining))
                devices = report.get("devices")
                if not isinstance(devices, list) or len(devices) != 1 or not isinstance(devices[0], dict):
                    raise BridgeReadError("MTP_INVALID_RESULT", "设备读取结果不完整，请重新读取")
                metadata = devices[0]
                if _identity(metadata.get("usb")) != _identity(target):
                    raise BridgeReadError("MTP_CONNECTION_CHANGED", ERROR_MESSAGES["MTP_CONNECTION_CHANGED"])
                if not isinstance(metadata.get("model_code"), str) or not metadata["model_code"].strip():
                    raise BridgeReadError("MTP_READ_FAILED", "设备未提供型号，请确认已解锁并允许文件传输")
                serial = target["usb_serial_number"] if target["usb_serial_unique"] else ""
                result["data"].append(normalize_snapshot(metadata, identifier, serial, include_raw))
            except BridgeReadError as exc:
                result["warnings"].append({"code": exc.code, "message": str(exc), "device_id": identifier})
        return result
    finally:
        _read_lock.release()


def diagnostics() -> dict:
    try:
        report = run_helper("--self-check", timeout=DISCOVERY_TIMEOUT)
        if report.get("ready") is not True or report.get("backend") != "windows_wpd":
            raise BridgeReadError("WPD_UNAVAILABLE", ERROR_MESSAGES["WPD_UNAVAILABLE"])
        return {"ready": True, "backend": "windows_wpd", "status": "preview", "message": "Windows WPD 组件可用，真机兼容性仍需验证"}
    except BridgeReadError as exc:
        return {"ready": False, "backend": "windows_wpd", "status": "preview", "code": exc.code, "message": str(exc)}
