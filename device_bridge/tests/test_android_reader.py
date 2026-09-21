import importlib.util
import json
from pathlib import Path
import plistlib
import subprocess
import unittest
from unittest.mock import Mock, patch

from hsx_device_bridge import __version__, android_reader as reader, device_reader
from hsx_device_bridge.ios_reader import BridgeReadError


def usb_node(serial="USB-SN", address=1, mtp=True):
    return {
        "USB Product Name": "SAMSUNG_Android", "USB Serial Number": serial,
        "idVendor": 0x04E8, "idProduct": 0x6860, "locationID": 0x100000,
        "USB Address": address,
        "IORegistryEntryChildren": [{"IORegistryEntryName": "MTP" if mtp else "Charging"}],
    }


def metadata(address=1):
    return {
        "usb": {"vendor_id": "0x04e8", "product_id": "0x6860", "bus_location": 0, "device_number": address},
        "model_code": "SM-G9910", "serial_number": "MTP-UUID-NOT-SN",
        "friendly_name": "My phone", "device_version_raw": "FIRMWARE", "battery_level_percent": 100,
    }


class AndroidReaderTest(unittest.TestCase):
    def test_usb_scan_uses_interface_and_excludes_other_devices(self):
        apple = dict(usb_node(), idVendor=0x05AC)
        result = reader.parse_usb_devices(plistlib.dumps([usb_node(), apple, usb_node("SECOND", 2, False)]))
        self.assertEqual(len(result), 2)
        self.assertTrue(result[0]["mtp_available"])
        self.assertFalse(result[1]["mtp_available"])
        self.assertNotEqual(result[0]["id"], result[1]["id"])

    def test_device_normalization_matches_usb_port_not_model(self):
        usb = reader.parse_usb_devices(plistlib.dumps([usb_node(), usb_node("SECOND", 2)]))
        device = reader.normalize_device(metadata(2), usb)
        self.assertEqual(device["identity"]["serial_number"], "SECOND")
        self.assertFalse(device["identity"]["serial_number_verified"])
        self.assertEqual(device["identity"]["mtp_serial_number"], "MTP-UUID-NOT-SN")
        self.assertEqual(device["hardware"]["product_type"], "SM-G9910")
        self.assertEqual(device["battery"], {"level_percent": 100})
        self.assertEqual(device["identity"]["imei"], "")
        self.assertEqual(device["system"]["version"], "")
        self.assertEqual(device["display"]["capacity"], "")
        self.assertEqual(device["display"]["color"], "")
        self.assertNotIn("raw", device)

    def test_missing_or_ambiguous_usb_serial_is_never_replaced_with_mtp_uuid(self):
        usb = reader.parse_usb_devices(plistlib.dumps([usb_node()]))
        for devices in ([], usb + usb):
            self.assertEqual(reader.normalize_device(metadata(), devices)["identity"]["serial_number"], "")

    def test_worker_handles_native_library_output_and_timeout(self):
        output = "libmtp diagnostic\n" + reader.RESULT_MARKER + json.dumps({"devices": []}) + "\n"
        with patch.object(reader.subprocess, "run", return_value=Mock(returncode=0, stdout=output)) as run:
            self.assertEqual(reader.run_worker("library"), {"devices": []})
            self.assertEqual(run.call_args.kwargs["timeout"], 10)
        with patch.object(reader.subprocess, "run", side_effect=subprocess.TimeoutExpired("mtp", 10)):
            with self.assertRaises(BridgeReadError) as error:
                reader.run_worker("library")
            self.assertEqual(error.exception.code, "MTP_TIMEOUT")

    def test_worker_rejects_failed_or_invalid_result(self):
        for code, output in ((1, ""), (0, reader.RESULT_MARKER + "broken"), (0, reader.RESULT_MARKER + "[]")):
            with patch.object(reader.subprocess, "run", return_value=Mock(returncode=code, stdout=output)):
                with self.assertRaises(BridgeReadError):
                    reader.run_worker("library")

    def test_read_releases_lock_after_device_error(self):
        usb = reader.parse_usb_devices(plistlib.dumps([usb_node()]))
        with patch.object(reader, "scan_usb_devices", return_value=usb), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", side_effect=[{"errors": [{}]}, {"devices": [metadata()]}]):
            with self.assertRaises(BridgeReadError):
                reader.read_devices()
            self.assertEqual(len(reader.read_devices()), 1)

    def test_file_transfer_is_required_before_opening_device(self):
        usb = reader.parse_usb_devices(plistlib.dumps([usb_node(mtp=False)]))
        with patch.object(reader, "scan_usb_devices", return_value=usb), \
                patch.object(reader, "library_path", return_value="library"), patch.object(reader, "run_worker") as worker:
            with self.assertRaises(BridgeReadError) as error:
                reader.read_devices()
            self.assertEqual(error.exception.code, "MTP_MODE_REQUIRED")
            worker.assert_not_called()

    def test_windows_does_not_advertise_android(self):
        with patch.object(reader.sys, "platform", "win32"):
            self.assertEqual(reader.scan_usb_devices(), [])
            self.assertFalse(device_reader.capabilities()["android_mtp"])

    def test_facade_preserves_iphone_and_adds_android(self):
        with patch.object(device_reader.ios_reader, "read_devices", return_value=[{"platform": "ios"}]), \
                patch.object(reader, "read_devices", return_value=[{"platform": "android"}]):
            self.assertEqual([item["platform"] for item in device_reader.read_devices()], ["ios", "android"])


spec = importlib.util.spec_from_file_location("verify_version", Path(__file__).parents[1] / "scripts/verify_version.py")
version_check = importlib.util.module_from_spec(spec)
spec.loader.exec_module(version_check)


class PackageVersionTest(unittest.TestCase):
    def test_stale_binary_cannot_be_relabelled(self):
        with patch.object(version_check.subprocess, "run", return_value=Mock(returncode=0, stdout="0.1.1\n")):
            with self.assertRaisesRegex(ValueError, "Rebuild"):
                version_check.verify_version("binary")

    def test_requested_label_must_match_source(self):
        with self.assertRaisesRegex(ValueError, "source"):
            version_check.verify_version("binary", "0.1.2")

    def test_matching_source_binary_and_package_pass(self):
        with patch.object(version_check.subprocess, "run", return_value=Mock(returncode=0, stdout=__version__ + "\n")):
            self.assertEqual(version_check.verify_version("binary", __version__), __version__)


if __name__ == "__main__":
    unittest.main()
