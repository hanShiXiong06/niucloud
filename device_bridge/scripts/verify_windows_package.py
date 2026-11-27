"""Windows CI checks the frozen executable, not just the development Python code."""

import argparse
import json
from pathlib import Path
import socket
import subprocess
import sys
import tempfile
import time
from urllib.error import URLError
from urllib.request import ProxyHandler, build_opener

from verify_version import verify_version


def verify_package(binary: Path) -> None:
    if sys.platform != "win32":
        raise RuntimeError("This smoke test must run on Windows")
    version = verify_version(binary)
    with tempfile.TemporaryDirectory() as directory:
        output = Path(directory) / "self-check.json"
        subprocess.run([str(binary), "self-check", "--output", str(output)], timeout=30, check=True)
        report = json.loads(output.read_text(encoding="utf-8"))
        if report.get("code") != 0 or report["data"]["android_mtp"].get("ready") is not True:
            raise RuntimeError("The packaged WPD helper or Python dependencies are unavailable")

    with socket.socket() as reserved:
        reserved.bind(("127.0.0.1", 0))
        port = reserved.getsockname()[1]
    process = subprocess.Popen([str(binary), "serve", "--port", str(port)])
    opener = build_opener(ProxyHandler({}))
    deadline = time.monotonic() + 30
    try:
        while True:
            if process.poll() is not None:
                raise RuntimeError("Packaged server exited before becoming ready")
            try:
                with opener.open("http://127.0.0.1:%d/v1/health" % port, timeout=12) as response:
                    health = json.load(response)["data"]
                break
            except (URLError, TimeoutError, OSError):
                if time.monotonic() >= deadline:
                    raise RuntimeError("Packaged server did not become ready within 30 seconds")
                time.sleep(0.3)
        if health["version"] != version or health["capabilities"].get("android_mtp_backend") != "windows_wpd":
            raise RuntimeError("Packaged HTTP server version or capabilities do not match")
        with opener.open("http://127.0.0.1:%d/v1/diagnostics" % port, timeout=12) as response:
            diagnostics = json.load(response)["data"]
        if not diagnostics["android_driver"]["ready"]:
            raise RuntimeError("Packaged server could not start the native WPD helper")
    finally:
        if process.poll() is None:
            process.terminate()
        try:
            process.wait(timeout=10)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait(timeout=5)
    print("Windows package smoke tests passed; phone hardware compatibility is NOT verified")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--binary", type=Path, required=True)
    args = parser.parse_args()
    verify_package(args.binary.resolve())
