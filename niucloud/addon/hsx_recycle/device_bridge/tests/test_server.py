import unittest
from unittest.mock import patch

from hsx_device_bridge.server import _origin_allowed


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
