import ctypes as C
import unittest
from unittest.mock import Mock, patch


from hsx_device_bridge import mtp_protocol as probe_module


class MtpProbeTest(unittest.TestCase):
    def make_library(self, values=None, battery=(0, 100, 72)):
        lib = Mock()
        buffers = []
        for field, getter in probe_module.STRING_FIELDS.items():
            text = (values or {}).get(field)
            buffer = C.create_string_buffer(text.encode("utf-8")) if text is not None else None
            buffers.append(buffer)
            getattr(lib, getter).return_value = C.addressof(buffer) if buffer is not None else None

        def get_battery(device, maximum, current):
            C.cast(maximum, C.POINTER(C.c_uint8))[0] = battery[1]
            C.cast(current, C.POINTER(C.c_uint8))[0] = battery[2]
            return battery[0]

        lib.LIBMTP_Get_Batterylevel.side_effect = get_battery
        lib.buffers = buffers
        return lib

    def test_metadata_preserves_model_and_does_not_invent_sensitive_fields(self):
        lib = self.make_library({
            "manufacturer": "SAMSUNG", "model_code": "SM-G9910",
            "serial_number": "TEST-SN", "friendly_name": "My phone",
            "device_version_raw": "1.0",
        })
        result = probe_module.read_metadata(lib, 1)
        self.assertEqual(result["model_code"], "SM-G9910")
        self.assertEqual(result["serial_number"], "TEST-SN")
        self.assertEqual(result["device_version_raw"], "1.0")
        self.assertEqual(result["battery_level_percent"], 72.0)
        for field in result["not_read_fields"]:
            self.assertNotIn(field, result)
        self.assertEqual(lib.LIBMTP_FreeMemory.call_count, 5)

    def test_missing_metadata_is_empty_not_a_guess_from_friendly_name(self):
        lib = self.make_library({"friendly_name": "Galaxy S21"}, battery=(-1, 100, 0))
        result = probe_module.read_metadata(lib, 1)
        self.assertEqual(result["model_code"], "")
        self.assertEqual(result["serial_number"], "")
        self.assertIsNone(result["battery_level_percent"])
        self.assertEqual(lib.LIBMTP_FreeMemory.call_count, 1)

    def test_battery_zero_is_valid_but_bad_ranges_are_not(self):
        for battery, expected in [((0, 100, 0), 0.0), ((0, 0, 0), None), ((0, 100, 101), None)]:
            with self.subTest(battery=battery):
                result = probe_module.read_metadata(self.make_library(battery=battery), 1)
                self.assertEqual(result["battery_level_percent"], expected)

    def test_probe_filters_vendor_and_releases_device_and_discovery_memory(self):
        lib = self.make_library({"model_code": "SM-G9910"})
        raw_devices = (probe_module.RawDevice * 2)()
        raw_devices[0].device_entry.vendor_id = 0x04E8
        raw_devices[0].device_entry.product_id = 0x6860
        raw_devices[1].device_entry.vendor_id = 0x1234

        def detect(devices, count):
            C.cast(devices, C.POINTER(C.POINTER(probe_module.RawDevice)))[0] = C.cast(
                raw_devices, C.POINTER(probe_module.RawDevice)
            )
            C.cast(count, C.POINTER(C.c_int))[0] = 2
            return 0

        lib.LIBMTP_Detect_Raw_Devices.side_effect = detect
        lib.LIBMTP_Open_Raw_Device_Uncached.return_value = 99
        with patch.object(probe_module, "load_library", return_value=lib):
            result = probe_module.probe("test-library", 0x04E8)
        self.assertEqual(len(result["devices"]), 1)
        self.assertEqual(result["errors"], [])
        lib.LIBMTP_Open_Raw_Device_Uncached.assert_called_once()
        lib.LIBMTP_Release_Device.assert_called_once_with(99)
        self.assertEqual(lib.LIBMTP_FreeMemory.call_count, 2)

    def test_no_device_is_not_an_invented_successful_read(self):
        lib = self.make_library()
        lib.LIBMTP_Detect_Raw_Devices.return_value = 5
        with patch.object(probe_module, "load_library", return_value=lib):
            result = probe_module.probe("test-library", 0x04E8)
        self.assertEqual(result["devices"], [])
        lib.LIBMTP_Open_Raw_Device_Uncached.assert_not_called()

    def test_storage_reports_exposed_filesystem_not_nominal_sku(self):
        lib = Mock()
        lib.LIBMTP_Get_Storage.return_value = 0
        storage = probe_module.Storage()
        storage.id = 0x10001
        storage.description = b"Internal storage"
        storage.max_capacity = 108 * 1024 ** 3
        storage.free_bytes = 89 * 1024 ** 3
        device = probe_module.DeviceHeader()
        device.storage = C.pointer(storage)
        result = probe_module.read_storage(lib, C.byref(device))
        self.assertEqual(result["storage"][0]["exposed_capacity_gib"], 108.0)
        self.assertEqual(result["storage"][0]["free_gib"], 89.0)
        self.assertEqual(result["storage"][0]["used_bytes"], 19 * 1024 ** 3)
        self.assertNotIn("sku_capacity", result["storage"][0])

    def test_unknown_storage_capacity_is_not_zero_or_overflow(self):
        lib = Mock()
        lib.LIBMTP_Get_Storage.return_value = 0
        storage = probe_module.Storage()
        storage.max_capacity = storage.free_bytes = 2 ** 64 - 1
        device = probe_module.DeviceHeader()
        device.storage = C.pointer(storage)
        result = probe_module.read_storage(lib, C.byref(device))["storage"][0]
        self.assertIsNone(result["exposed_capacity_bytes"])
        self.assertIsNone(result["free_bytes"])
        self.assertIsNone(result["used_bytes"])

    def test_storage_failure_does_not_dereference_device(self):
        lib = Mock()
        lib.LIBMTP_Get_Storage.return_value = -1
        self.assertEqual(probe_module.read_storage(lib, None), {"storage_status": "unavailable", "storage": []})


if __name__ == "__main__":
    unittest.main()
