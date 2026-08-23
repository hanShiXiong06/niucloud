from __future__ import annotations

import asyncio
import inspect
import sys
import threading
import types
from datetime import datetime, timezone
from typing import Any

from .normalizer import json_safe, number, storage_label

_thread_local = threading.local()


def _install_frozen_runtime_stubs() -> None:
    """Avoid bundling IPython, which pymobiledevice3 only needs for its debug shell."""
    if not getattr(sys, "frozen", False) or "IPython" in sys.modules:
        return
    module = types.ModuleType("IPython")

    def start_ipython(*args: Any, **kwargs: Any) -> None:
        raise RuntimeError("hsx_device_bridge does not provide an interactive IPython shell")

    module.start_ipython = start_ipython  # type: ignore[attr-defined]
    sys.modules["IPython"] = module


_install_frozen_runtime_stubs()


class BridgeReadError(RuntimeError):
    def __init__(self, code: str, message: str):
        super().__init__(message)
        self.code = code


def _loop() -> asyncio.AbstractEventLoop:
    loop = getattr(_thread_local, "loop", None)
    if loop is None or loop.is_closed():
        loop = asyncio.new_event_loop()
        _thread_local.loop = loop
    return loop


def _resolve(value: Any) -> Any:
    if not inspect.isawaitable(value):
        return value
    try:
        asyncio.get_running_loop()
    except RuntimeError:
        return _loop().run_until_complete(value)

    result: list[Any] = []
    failure: list[BaseException] = []

    def run() -> None:
        try:
            result.append(asyncio.run(value))
        except BaseException as exc:  # pragma: no cover - defensive thread handoff
            failure.append(exc)

    worker = threading.Thread(target=run, daemon=True)
    worker.start()
    worker.join()
    if failure:
        raise failure[0]
    return result[0]


def _battery_snapshot(lockdown: Any) -> dict[str, Any]:
    try:
        from pymobiledevice3.services.diagnostics import DiagnosticsService

        diagnostics = DiagnosticsService(lockdown)
        battery = _resolve(diagnostics.get_battery()) or {}
        design = number(battery.get("DesignCapacity")) or 0
        nominal = number(battery.get("NominalChargeCapacity")) or 0
        reported_health = number(battery.get("MaxCapacity"))
        calculated_health = round(nominal / design * 100, 1) if design > 0 and nominal > 0 else None
        snapshot = {
            "health_percent": reported_health,
            "calculated_health_percent": calculated_health,
            "cycle_count": number(battery.get("CycleCount")),
            "level_percent": number(battery.get("CurrentCapacity")),
            "charging": bool(battery.get("IsCharging", False)),
            "temperature_c": round((number(battery.get("Temperature")) or 0) / 100, 1)
            if number(battery.get("Temperature")) else None,
            "voltage_mv": number(battery.get("Voltage")),
            "serial_number": battery.get("Serial", ""),
            "design_capacity_mah": design or None,
            "nominal_capacity_mah": nominal or None,
        }
        close = getattr(diagnostics, "close", None)
        if callable(close):
            _resolve(close())
        return {key: value for key, value in snapshot.items() if value not in (None, "")}
    except Exception:
        return {}


def scan_device_ids() -> list[str]:
    try:
        from pymobiledevice3.usbmux import list_devices

        devices = _resolve(list_devices()) or []
        by_serial: dict[str, Any] = {}
        for device in devices:
            serial = str(getattr(device, "serial", ""))
            if not serial:
                continue
            current = by_serial.get(serial)
            connection = str(getattr(device, "connection_type", ""))
            current_connection = str(getattr(current, "connection_type", "")) if current else ""
            if current is None or (connection.upper() == "USB" and current_connection.upper() != "USB"):
                by_serial[serial] = device
        return list(by_serial.keys())
    except ModuleNotFoundError as exc:
        missing_module = exc.name or str(exc)
        raise BridgeReadError(
            "DEPENDENCY_MISSING",
            f"扫描 iPhone 失败：设备桥安装包缺少运行组件 {missing_module}，请覆盖安装最新版",
        ) from exc
    except Exception as exc:
        raise BridgeReadError("USB_SCAN_FAILED", f"扫描 iPhone 失败：{exc}") from exc


def read_device(device_id: str | None = None, include_raw: bool = False) -> dict[str, Any]:
    try:
        from pymobiledevice3.lockdown import create_using_usbmux
        from pymobiledevice3.exceptions import NoDeviceConnectedError
    except ImportError as exc:
        raise BridgeReadError("DEPENDENCY_MISSING", "缺少 pymobiledevice3 运行依赖") from exc

    try:
        lockdown = _resolve(create_using_usbmux(serial=device_id)) if device_id else _resolve(create_using_usbmux())
    except NoDeviceConnectedError as exc:
        raise BridgeReadError("NO_DEVICE", "未检测到 iPhone，请连接数据线并解锁手机") from exc
    except Exception as exc:
        message = str(exc).strip() or exc.__class__.__name__
        code = "DEVICE_NOT_TRUSTED" if "pair" in message.lower() or "trust" in message.lower() else "CONNECT_FAILED"
        raise BridgeReadError(code, f"连接 iPhone 失败：{message}") from exc

    try:
        values = _resolve(lockdown.all_values) if inspect.isawaitable(lockdown.all_values) else lockdown.all_values
        values = values or {}
        product_type = str(values.get("ProductType", ""))
        model_number = str(values.get("ModelNumber", ""))
        region_code = str(values.get("RegionInfo", ""))
        disk_values: dict[str, Any] = {}
        try:
            disk_values = _resolve(lockdown.get_value(domain="com.apple.disk_usage.factory")) or {}
        except Exception:
            pass
        total_bytes = values.get("TotalDiskCapacity", 0) or disk_values.get("TotalDiskCapacity", 0)
        color_code = str(values.get("DeviceColor", ""))
        color_name = "" if color_code.isdigit() else color_code
        snapshot: dict[str, Any] = {
            "schema_version": "hsx.device.snapshot.v1",
            "captured_at": datetime.now(timezone.utc).isoformat(),
            "source": "usb",
            "platform": "ios",
            "identity": {
                "udid": values.get("UniqueDeviceID", device_id or ""),
                "serial_number": values.get("SerialNumber", ""),
                "imei": values.get("InternationalMobileEquipmentIdentity", ""),
                "imei2": values.get("InternationalMobileEquipmentIdentity2", ""),
                "ecid": values.get("UniqueChipID", ""),
            },
            "hardware": {
                "product_type": product_type,
                "model_number": model_number,
                "model_number_full": f"{model_number}{region_code}" if region_code else model_number,
                "hardware_model": values.get("HardwareModel", ""),
                "hardware_platform": values.get("HardwarePlatform", ""),
                "device_class": values.get("DeviceClass", ""),
                "region_code": region_code,
                "color_code": color_code,
            },
            "display": {
                "device_name": values.get("DeviceName", ""),
                "model_hint": product_type,
                "color": color_name,
                "color_index": color_code,
                "capacity": storage_label(total_bytes),
                "capacity_bytes": number(total_bytes),
            },
            "system": {
                "version": values.get("ProductVersion", ""),
                "build_version": values.get("BuildVersion", ""),
                "activation_state": values.get("ActivationState", ""),
                "password_protected": bool(values.get("PasswordProtected", False)),
            },
            "battery": _battery_snapshot(lockdown),
        }
        if include_raw:
            snapshot["raw"] = json_safe(values)
        return json_safe(snapshot)
    except BridgeReadError:
        raise
    except Exception as exc:
        raise BridgeReadError("READ_FAILED", f"读取 iPhone 信息失败：{exc}") from exc
    finally:
        close = getattr(lockdown, "close", None)
        if callable(close):
            try:
                _resolve(close())
            except Exception:
                pass


def read_devices(include_raw: bool = False) -> list[dict[str, Any]]:
    ids = scan_device_ids()
    return [read_device(device_id, include_raw=include_raw) for device_id in ids]
