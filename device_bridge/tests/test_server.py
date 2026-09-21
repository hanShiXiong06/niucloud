import unittest
from unittest.mock import Mock, patch

from hsx_device_bridge.server import _origin_allowed, BridgeHandler


class DeviceResponseTest(unittest.TestCase):
    def test_windows_android_diagnostics_are_separate_from_apple_driver(self):
        handler = self.handler("/v1/diagnostics")
        with patch("hsx_device_bridge.server.apple_driver_diagnostics", return_value={"ready": False}), \
                patch("hsx_device_bridge.server.android_driver_diagnostics", return_value={"ready": True, "backend": "windows_wpd"}):
            handler.do_GET()
        status, body = handler._send.call_args.args
        self.assertEqual(status, 200)
        self.assertFalse(body["data"]["driver"]["ready"])
        self.assertTrue(body["data"]["android_driver"]["ready"])

    def handler(self, path, origin="http://localhost:5175"):
        handler = object.__new__(BridgeHandler)
        handler.path = path
        handler.headers = {"Origin": origin}
        handler._send = Mock()
        return handler

    def test_partial_result_returns_successful_devices_with_warnings(self):
        result = {"data": [{"device_id": "phone-1"}], "warnings": [
            {"code": "MTP_TIMEOUT", "message": "phone-2 timed out", "device_id": "phone-2"},
        ]}
        handler = self.handler("/v1/devices?include_raw=1")
        with patch("hsx_device_bridge.server.read_result", return_value=result) as read:
            handler.do_GET()
        status, body = handler._send.call_args.args
        self.assertEqual(status, 200)
        self.assertEqual(body["data"], result["data"])
        self.assertEqual(body["warnings"], result["warnings"])
        self.assertTrue(body["partial"])
        read.assert_called_once_with(include_raw=True)

    def test_no_successes_returns_actionable_failure(self):
        handler = self.handler("/v1/devices")
        with patch("hsx_device_bridge.server.read_result", return_value={
            "data": [], "warnings": [{"code": "MTP_OPEN_FAILED", "message": "unlock phone"}],
        }):
            handler.do_GET()
        status, body = handler._send.call_args.args
        self.assertEqual(status, 422)
        self.assertEqual(body["code"], "MTP_OPEN_FAILED")
        self.assertEqual(body["message"], "unlock phone")

    def test_health_keeps_other_devices_when_one_transport_cannot_scan(self):
        handler = self.handler("/v1/health")
        with patch("hsx_device_bridge.server.scan_result", return_value={
            "data": ["iphone"], "warnings": [{"code": "MTP_BUSY", "message": "reading"}],
        }), patch("hsx_device_bridge.server.apple_driver_diagnostics", return_value={"ready": True}):
            handler.do_GET()
        status, body = handler._send.call_args.args
        self.assertEqual(status, 200)
        self.assertEqual(body["data"]["device_count"], 1)
        self.assertEqual(body["data"]["scan_error"], "reading")

    def test_denied_origin_cannot_trigger_usb_access(self):
        handler = self.handler("/v1/devices", "https://untrusted.example")
        with patch("hsx_device_bridge.server.read_result") as read:
            handler.do_GET()
        self.assertEqual(handler._send.call_args.args[0], 403)
        read.assert_not_called()


class OriginPolicyTest(unittest.TestCase):
    def test_configured_production_origin_is_allowed(self):
        with patch.dict(
            "hsx_device_bridge.server.os.environ",
            {"HSX_DEVICE_BRIDGE_ORIGINS": "https://gl.hsxbk.top"},
            clear=True,
        ):
            self.assertTrue(_origin_allowed("https://gl.hsxbk.top"))
            self.assertFalse(_origin_allowed("https://example.com"))

    def test_local_development_origin_with_port_is_allowed(self):
        with patch.dict("hsx_device_bridge.server.os.environ", {}, clear=True):
            self.assertTrue(_origin_allowed("http://localhost:5175"))
            self.assertTrue(_origin_allowed("http://127.0.0.1:5175"))


if __name__ == "__main__":
    unittest.main()
