"""Keep package labels tied to the source and the executable actually shipped."""

import argparse
from pathlib import Path
import subprocess
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "src"))
from hsx_device_bridge import __version__


def verify_version(binary, expected=None):
    expected = expected or __version__
    if expected != __version__:
        raise ValueError("Requested package version %s differs from source %s" % (expected, __version__))
    process = subprocess.run([str(binary), "--version"], capture_output=True, text=True, timeout=10)
    actual = process.stdout.strip()
    if process.returncode or actual != expected:
        raise ValueError("Executable version %r differs from %s. Rebuild before packaging." % (actual, expected))
    return actual


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--binary")
    parser.add_argument("--expected")
    args = parser.parse_args()
    try:
        print(verify_version(args.binary, args.expected) if args.binary else __version__)
    except (OSError, ValueError, subprocess.SubprocessError) as exc:
        parser.exit(1, "%s\n" % exc)
