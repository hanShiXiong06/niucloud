from __future__ import annotations

import json
import logging
import os
import errno
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

from . import __version__
from .ios_reader import BridgeReadError, read_devices, scan_device_ids
from .runtime import log_dir, read_runtime_config
from .windows_support import apple_driver_diagnostics


def _allowed_origins() -> set[str]:
    configured = os.getenv("HSX_DEVICE_BRIDGE_ORIGINS", "")
    if not configured:
        configured = str(read_runtime_config().get("allowed_origins", ""))
    values = {item.strip().rstrip("/") for item in configured.split(",") if item.strip()}
    values.update({"http://localhost", "http://127.0.0.1"})
    return values


def _origin_allowed(origin: str) -> bool:
    if not origin:
        return True
    origin = origin.rstrip("/")
    allowed = _allowed_origins()
    if origin in allowed:
        return True
    return origin.startswith("http://localhost:") or origin.startswith("http://127.0.0.1:")


class BridgeHandler(BaseHTTPRequestHandler):
    server_version = "hsx-device-bridge"

    def log_message(self, fmt: str, *args: object) -> None:
        return

    def _send(self, status: int, payload: dict) -> None:
        body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        self.send_response(status)
        origin = self.headers.get("Origin", "")
        if origin and _origin_allowed(origin):
            self.send_header("Access-Control-Allow-Origin", origin)
            self.send_header("Vary", "Origin")
            # Chromium 会对公网 HTTPS 页面访问 127.0.0.1 发起本地网络预检。
            # 仅对已进入白名单的来源授权，避免任意网站读取本机设备信息。
            self.send_header("Access-Control-Allow-Private-Network", "true")
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self) -> None:  # noqa: N802
        origin = self.headers.get("Origin", "")
        if not _origin_allowed(origin):
            self._send(403, {"code": 403, "message": "当前站点未获准访问设备桥接服务"})
            return
        self.send_response(204)
        if origin:
            self.send_header("Access-Control-Allow-Origin", origin)
            self.send_header("Vary", "Origin")
            self.send_header("Access-Control-Allow-Private-Network", "true")
        self.send_header("Access-Control-Allow-Methods", "GET, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.send_header("Access-Control-Max-Age", "600")
        self.end_headers()

    def do_GET(self) -> None:  # noqa: N802
        origin = self.headers.get("Origin", "")
        if not _origin_allowed(origin):
            self._send(403, {"code": 403, "message": "当前站点未获准访问设备桥接服务"})
            return

        request = urlparse(self.path)
        if request.path == "/v1/health":
            driver = apple_driver_diagnostics()
            try:
                device_count = len(scan_device_ids())
                scan_error = ""
            except BridgeReadError as exc:
                device_count = 0
                scan_error = str(exc)
            self._send(200, {
                "code": 0,
                "data": {
                    "service": "hsx_device_bridge",
                    "version": __version__,
                    "device_count": device_count,
                    "scan_error": scan_error,
                    "driver": driver,
                },
            })
            return
        if request.path == "/v1/diagnostics":
            self._send(200, {
                "code": 0,
                "data": {
                    "service": "hsx_device_bridge",
                    "version": __version__,
                    "driver": apple_driver_diagnostics(),
                },
            })
            return
        if request.path == "/v1/scan":
            try:
                device_ids = scan_device_ids()
                self._send(200, {"code": 0, "count": len(device_ids), "data": device_ids})
            except BridgeReadError as exc:
                self._send(422, {"code": exc.code, "message": str(exc), "data": []})
            return
        if request.path == "/v1/devices":
            include_raw = parse_qs(request.query).get("include_raw", ["0"])[0] == "1"
            try:
                devices = read_devices(include_raw=include_raw)
                self._send(200, {"code": 0, "count": len(devices), "data": devices})
            except BridgeReadError as exc:
                self._send(422, {"code": exc.code, "message": str(exc), "data": []})
            return
        self._send(404, {"code": 404, "message": "接口不存在"})


def serve(host: str = "127.0.0.1", port: int = 17890) -> None:
    logs = log_dir()
    logs.mkdir(parents=True, exist_ok=True)
    logging.basicConfig(
        filename=logs / "bridge.log",
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
    )
    try:
        server = ThreadingHTTPServer((host, port), BridgeHandler)
    except OSError as exc:
        if exc.errno in (errno.EADDRINUSE, 10048):
            return
        raise
    logging.info("hsx_device_bridge %s listening on http://%s:%s", __version__, host, port)
    print(f"hsx_device_bridge {__version__} listening on http://{host}:{port}", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
