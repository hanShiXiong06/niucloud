from __future__ import annotations

import argparse
import json

from .ios_reader import BridgeReadError, read_device, scan_device_ids
from .server import serve


def main() -> None:
    parser = argparse.ArgumentParser(prog="hsx-device-bridge")
    subparsers = parser.add_subparsers(dest="command")

    subparsers.add_parser("scan", help="列出当前 USB 连接的 iPhone")
    read_parser = subparsers.add_parser("read", help="读取一台 iPhone 的标准快照")
    read_parser.add_argument("--device-id", default="")
    read_parser.add_argument("--raw", action="store_true")
    serve_parser = subparsers.add_parser("serve", help="启动本地浏览器桥接服务")
    serve_parser.add_argument("--host", default="127.0.0.1")
    serve_parser.add_argument("--port", type=int, default=17890)

    args = parser.parse_args()
    command = args.command or "serve"
    try:
        if command == "scan":
            ids = scan_device_ids()
            print(json.dumps({"code": 0, "count": len(ids), "data": ids}, ensure_ascii=False))
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
