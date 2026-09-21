from __future__ import annotations

import argparse
import json

from . import __version__
from .android_reader import worker
from .device_reader import read_result, response_payload, scan_result
from .ios_reader import BridgeReadError, read_device
from .server import serve
from .windows_support import runtime_dependency_diagnostics


def main() -> None:
    parser = argparse.ArgumentParser(prog="hsx-device-bridge")
    parser.add_argument("--version", action="version", version=__version__)
    subparsers = parser.add_subparsers(dest="command")

    subparsers.add_parser("scan", help="列出当前连接的受支持设备")
    subparsers.add_parser("devices", help="读取所有受支持设备的基础信息")
    worker_parser = subparsers.add_parser("_mtp-worker", help=argparse.SUPPRESS)
    worker_parser.add_argument("--library", required=True)
    worker_parser.add_argument("--discover-only", action="store_true")
    worker_parser.add_argument("--target", type=json.loads)
    subparsers.add_parser("self-check", help="检查安装包的 Windows 运行依赖")
    read_parser = subparsers.add_parser("read", help="读取一台 iPhone 的标准快照")
    read_parser.add_argument("--device-id", default="")
    read_parser.add_argument("--raw", action="store_true")
    serve_parser = subparsers.add_parser("serve", help="启动本地浏览器桥接服务")
    serve_parser.add_argument("--host", default="127.0.0.1")
    serve_parser.add_argument("--port", type=int, default=17890)

    args = parser.parse_args()
    command = args.command or "serve"
    try:
        if command in ("scan", "devices"):
            result = scan_result() if command == "scan" else read_result()
            payload = response_payload(result)
            print(json.dumps(payload, ensure_ascii=False))
            if payload["code"] != 0:
                raise SystemExit(2)
        elif command == "_mtp-worker":
            worker(args.library, discover_only=args.discover_only, target=args.target)
        elif command == "self-check":
            diagnostics = runtime_dependency_diagnostics()
            print(json.dumps({"code": 0 if diagnostics["ready"] else 1, "data": diagnostics}, ensure_ascii=False))
            if not diagnostics["ready"]:
                raise SystemExit(3)
        elif command == "read":
            snapshot = read_device(args.device_id or None, include_raw=args.raw)
            print(json.dumps({"code": 0, "data": snapshot}, ensure_ascii=False, indent=2))
        else:
            serve(args.host, args.port)
    except BridgeReadError as exc:
        print(json.dumps({"code": exc.code, "message": str(exc), "data": []}, ensure_ascii=False))
        raise SystemExit(2) from exc


if __name__ == "__main__":
    main()
