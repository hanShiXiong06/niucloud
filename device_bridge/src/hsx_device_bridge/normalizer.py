from __future__ import annotations

from datetime import date, datetime
from typing import Any


def json_safe(value: Any) -> Any:
    if isinstance(value, bytes):
        return value.rstrip(b"\x00").decode("utf-8", errors="replace")
    if isinstance(value, (datetime, date)):
        return value.isoformat()
    if isinstance(value, dict):
        return {str(key): json_safe(item) for key, item in value.items()}
    if isinstance(value, (list, tuple, set)):
        return [json_safe(item) for item in value]
    if value is None or isinstance(value, (str, int, float, bool)):
        return value
    return str(value)


def storage_label(size_bytes: Any) -> str:
    try:
        size = int(size_bytes or 0)
    except (TypeError, ValueError):
        return ""
    if size <= 0:
        return ""
    actual_gb = size / (1024 ** 3)
    for nominal in (16, 32, 64, 128, 256, 512, 1024, 2048):
        if actual_gb <= nominal * 1.1:
            return f"{nominal}GB" if nominal < 1024 else f"{nominal // 1024}TB"
    return f"{actual_gb:.0f}GB"


def number(value: Any) -> float | int | None:
    if value in (None, ""):
        return None
    if isinstance(value, (int, float)):
        return value
    text = str(value).strip().replace("%", "").replace("次", "")
    try:
        parsed = float(text)
    except ValueError:
        return None
    return int(parsed) if parsed.is_integer() else parsed
