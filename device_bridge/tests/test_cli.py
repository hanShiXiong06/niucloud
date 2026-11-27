import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import Mock, patch

from hsx_device_bridge import __version__
from hsx_device_bridge.__main__ import main

spec = importlib.util.spec_from_file_location("cli_version_check", Path(__file__).parents[1] / "scripts/verify_version.py")
version_check = importlib.util.module_from_spec(spec)
spec.loader.exec_module(version_check)


class WindowedCliTest(unittest.TestCase):
    def test_version_file_works_without_stdout(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "version.txt"
            with patch("sys.argv", ["bridge", "version", "--output", str(output)]), patch("sys.stdout", None):
                main()
            self.assertEqual(output.read_text(encoding="utf-8"), __version__)

    def test_self_check_file_keeps_failure_details_without_stdout(self):
        for ready in (True, False):
            with tempfile.TemporaryDirectory() as directory:
                output = Path(directory) / "check.json"
                with patch("sys.argv", ["bridge", "self-check", "--output", str(output)]), \
                        patch("sys.stdout", None), patch("hsx_device_bridge.__main__.runtime_dependency_diagnostics", return_value={"ready": ready}):
                    if ready:
                        main()
                    else:
                        with self.assertRaises(SystemExit) as error:
                            main()
                        self.assertEqual(error.exception.code, 3)
                self.assertEqual(json.loads(output.read_text(encoding="utf-8"))["code"], 0 if ready else 1)

    def test_windows_version_verifier_reads_file_not_stdout(self):
        def run(command, **kwargs):
            self.assertEqual(command[1:3], ["version", "--output"])
            Path(command[3]).write_text(__version__, encoding="utf-8")
            return Mock(returncode=0)

        with patch.object(version_check.sys, "platform", "win32"), patch.object(version_check.subprocess, "run", side_effect=run):
            self.assertEqual(version_check.verify_version("bridge.exe"), __version__)

    def test_old_windowed_binary_without_file_output_is_rejected(self):
        with patch.object(version_check.sys, "platform", "win32"), patch.object(version_check.subprocess, "run", return_value=Mock(returncode=0)):
            with self.assertRaisesRegex(ValueError, "Rebuild"):
                version_check.verify_version("old.exe")


if __name__ == "__main__":
    unittest.main()
