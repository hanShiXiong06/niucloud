from __future__ import annotations

import json
import os
import sys
from pathlib import Path
from typing import Any


def executable_dir() -> Path:
    if getattr(sys, "frozen", False):
        return Path(sys.executable).resolve().parent
    return Path(__file__).resolve().parents[2]


def read_runtime_config() -> dict[str, Any]:
    path = executable_dir() / "bridge-config.json"
    if not path.is_file():
        return {}
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
        return value if isinstance(value, dict) else {}
    except (OSError, ValueError):
        return {}


def log_dir() -> Path:
    if sys.platform == "win32":
        root = Path(os.getenv("LOCALAPPDATA") or Path.home())
        return root / "HSX Device Bridge" / "logs"
    return Path(os.getenv("TMPDIR") or "/tmp")
