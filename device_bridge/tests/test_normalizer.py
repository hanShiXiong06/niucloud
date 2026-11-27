import unittest

from hsx_device_bridge.normalizer import json_safe, number, storage_label


class NormalizerTest(unittest.TestCase):
    def test_storage_uses_commercial_capacity(self):
        self.assertEqual(storage_label(119 * 1024 ** 3), "128GB")
        self.assertEqual(storage_label(477 * 1024 ** 3), "512GB")

    def test_number_accepts_device_units(self):
        self.assertEqual(number("98.5%"), 98.5)
        self.assertEqual(number("312次"), 312)

    def test_json_safe_converts_bytes(self):
        self.assertEqual(json_safe({"value": b"ABC\x00"}), {"value": "ABC"})


if __name__ == "__main__":
    unittest.main()
