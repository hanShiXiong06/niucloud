import sys
import unittest
from unittest.mock import patch

from hsx_device_bridge.windows_support import apple_driver_diagnostics, runtime_dependency_diagnostics


class WindowsSupportTest(unittest.TestCase):
    def test_wpd_dependency_is_checked_without_requiring_apple_hardware(self):
        with patch("hsx_device_bridge.windows_support.sys.platform", "win32"), \
                patch("hsx_device_bridge.windows_support.importlib.import_module"), \
                patch("hsx_device_bridge.windows_support.mtp_diagnostics", return_value={"ready": True}) as mtp:
            self.assertTrue(runtime_dependency_diagnostics()["ready"])
            mtp.assert_called_once_with()

    def test_missing_native_helper_fails_package_self_check(self):
        with patch("hsx_device_bridge.windows_support.sys.platform", "win32"), \
                patch("hsx_device_bridge.windows_support.importlib.import_module"), \
                patch("hsx_device_bridge.windows_support.mtp_diagnostics", return_value={"ready": False, "code": "MTP_UNAVAILABLE"}):
            result = runtime_dependency_diagnostics()
            self.assertFalse(result["ready"])
            self.assertEqual(result["android_mtp"]["code"], "MTP_UNAVAILABLE")

    def test_runtime_dependencies_are_not_required_off_windows(self) -> None:
        result = runtime_dependency_diagnostics()
        if sys.platform != "win32":
            self.assertTrue(result["ready"])
            self.assertEqual(result["missing_modules"], [])

    def test_non_windows_does_not_require_apple_driver(self):
        with patch("hsx_device_bridge.windows_support.sys.platform", "darwin"):
            result = apple_driver_diagnostics()
        self.assertTrue(result["ready"])
        self.assertFalse(result["required"])

    def test_running_windows_service_is_ready(self):
        with (
            patch("hsx_device_bridge.windows_support.sys.platform", "win32"),
            patch("hsx_device_bridge.windows_support._apple_service_state", return_value="running"),
            patch("hsx_device_bridge.windows_support._usbmux_reachable", return_value=True),
        ):
            result = apple_driver_diagnostics()
        self.assertTrue(result["ready"])
        self.assertEqual(result["service_state"], "running")


if __name__ == "__main__":
    unittest.main()
