from __future__ import annotations

from datetime import datetime, timezone


NOT_READ_FIELDS = [
    "imei", "imei2", "android_version", "color", "sku_capacity",
    "battery_health_percent", "battery_cycle_count",
]


def normalize_snapshot(metadata: dict, identifier: str, usb_serial: str = "", include_raw: bool = False) -> dict:
    """Normalize MTP facts; USB identity is verified by the platform adapter."""
    model = str(metadata.get("model_code", "")).strip()
    battery = metadata.get("battery_level_percent")
    if isinstance(battery, bool) or not isinstance(battery, (int, float)) or not 0 <= battery <= 100:
        battery = None
    snapshot = {
        "schema_version": "hsx.device.snapshot.v1",
        "captured_at": datetime.now(timezone.utc).isoformat(),
        "source": "usb_mtp",
        "device_id": identifier,
        "platform": "android" if "android.com" in metadata.get("protocol_extensions", []) else "mtp",
        "identity": {
            "imei": "", "imei2": "", "serial_number": usb_serial,
            "serial_number_source": "usb_descriptor" if usb_serial else "",
            "serial_number_verified": False,
            "usb_serial_number": usb_serial, "mtp_serial_number": metadata.get("serial_number", ""),
        },
        "hardware": {"product_type": model, "model_number": model, "manufacturer": metadata.get("manufacturer", "")},
        "display": {"device_name": metadata.get("friendly_name") or model, "model_hint": model, "color": "", "capacity": ""},
        "system": {"version": "", "firmware_raw": metadata.get("device_version_raw", "")},
        "battery": {"level_percent": battery},
        "not_read_fields": list(dict.fromkeys(NOT_READ_FIELDS + metadata.get("not_read_fields", []))),
        "warnings": ["SN 来自 USB 序列号，请与手机机身核对；保修查询是否支持需单独确认。"],
    }
    if not usb_serial:
        snapshot["warnings"] = ["未取得唯一 USB 序列号，请手动填写 IMEI/SN；不会使用 MTP UUID 代替。"]
    if snapshot["platform"] != "android":
        snapshot["warnings"].append("未取得 Android 协议扩展声明，请核对设备类型和型号。")
    if include_raw:
        snapshot["raw"] = metadata
    return snapshot
