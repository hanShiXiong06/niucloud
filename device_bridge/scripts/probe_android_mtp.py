"""Read-only MTP diagnostic, using the same protocol reader as the bridge."""

import argparse
import ctypes.util
import json
from pathlib import Path
import subprocess
import sys
import time

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "src"))
from hsx_device_bridge.mtp_protocol import RESULT_MARKER, probe

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--library", default=ctypes.util.find_library("mtp"))
    parser.add_argument("--vendor-id", type=lambda value: int(value, 0))
    parser.add_argument("--timeout", type=int, default=30)
    parser.add_argument("--extended", action="store_true", help="Read exposed storage capacity and report MTP capabilities")
    parser.add_argument("--worker", action="store_true", help=argparse.SUPPRESS)
    args = parser.parse_args()
    if not args.library:
        parser.error("libmtp is unavailable; specify --library /absolute/path/to/libmtp")
    if args.timeout <= 0:
        parser.error("--timeout must be positive")
    if args.worker:
        try:
            result = probe(args.library, args.vendor_id, args.extended)
        except (OSError, AttributeError, RuntimeError) as error:
            result = {"error": str(error)}
        print(RESULT_MARKER + json.dumps(result), flush=True)
        return 0

    command = [sys.executable, __file__, "--worker", "--library", args.library]
    if args.vendor_id is not None:
        command.extend(["--vendor-id", str(args.vendor_id)])
    if args.extended:
        command.append("--extended")
    start = time.monotonic()
    # A disconnected or busy device must not leave a native USB call hanging.
    try:
        process = subprocess.run(command, capture_output=True, text=True, errors="replace", timeout=args.timeout)
    except subprocess.TimeoutExpired:
        print(json.dumps({"error": "MTP_TIMEOUT", "timeout_seconds": args.timeout}))
        return 2
    before, marker, after = process.stdout.partition(RESULT_MARKER)
    if not marker or process.returncode != 0:
        print(process.stdout + process.stderr, file=sys.stderr)
        print(json.dumps({"error": "MTP_WORKER_FAILED", "exit_code": process.returncode}))
        return 2
    try:
        report, end = json.JSONDecoder().raw_decode(after)
    except (ValueError, TypeError):
        print(json.dumps({"error": "MTP_INVALID_RESULT"}))
        return 2
    diagnostics = before + after[end:] + process.stderr
    if diagnostics.strip():
        print(diagnostics.strip(), file=sys.stderr)
    report["elapsed_seconds"] = round(time.monotonic() - start, 3)
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 2 if report.get("error") or report.get("errors") else 0


if __name__ == "__main__":
    sys.exit(main())
