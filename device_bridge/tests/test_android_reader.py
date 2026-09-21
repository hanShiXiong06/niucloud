import importlib.util
import json
from pathlib import Path
import plistlib
import subprocess
import unittest
from unittest.mock import Mock, patch

from hsx_device_bridge import __version__, android_reader as reader, device_reader
from hsx_device_bridge.ios_reader import BridgeReadError


def usb_node(serial="USB-SN", address=1, vendor=0x04E8, product=0x6860, name="SAMSUNG_Android", location=0x100000):
    return {
        "USB Product Name": name, "USB Serial Number": serial,
        "idVendor": vendor, "idProduct": product, "locationID": location,
        "USB Address": address,
        "IORegistryEntryChildren": [],
    }


def metadata(address=1):
    return {
        "usb": {"vendor_id": "0x04e8", "product_id": "0x6860", "bus_location": 0, "device_number": address},
        "model_code": "SM-G9910", "serial_number": "MTP-UUID-NOT-SN",
        "friendly_name": "My phone", "device_version_raw": "FIRMWARE", "battery_level_percent": 100,
        "protocol_extensions": ["android.com"],
    }


class AndroidReaderTest(unittest.TestCase):
    def setUp(self):
        platform = patch.object(reader.sys, "platform", "darwin")
        platform.start()
        self.addCleanup(platform.stop)
        sleep = patch.object(reader.time, "sleep")
        sleep.start()
        self.addCleanup(sleep.stop)

    def test_usb_inventory_accepts_iqoo_meizu_and_unknown_brand_without_interfaces(self):
        nodes = [usb_node(), usb_node(vendor=0x05AC), dict(usb_node(), bDeviceClass=9),
                 usb_node(vendor=0x2D95, product=0x6013, name="vivo iQOO"),
                 usb_node(vendor=0x2A45, product=0x2008, name="16th"),
                 usb_node(vendor=0xF123, product=1, name="New brand")]
        result = reader.parse_usb_devices(plistlib.dumps(nodes))
        self.assertEqual([item["vendor_id"] for item in result], [0x04E8, 0x2D95, 0x2A45, 0xF123])

    def test_scan_uses_protocol_discovery_not_usb_product_names(self):
        port = metadata()["usb"]
        with patch.object(reader.sys, "platform", "darwin"), \
                patch.object(reader, "scan_usb_devices", return_value=[]), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", return_value={"detected": [port]}) as worker:
            self.assertEqual(reader.scan_device_ids(), [reader.device_id(port)])
            worker.assert_called_once_with("library", discover_only=True, timeout=reader.DISCOVERY_TIMEOUT)

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
        self.assertEqual(device["platform"], "android")
        self.assertEqual(device["device_id"], reader.device_id(metadata(2)["usb"], usb))

    def test_non_android_mtp_is_not_mislabeled_as_android(self):
        info = {**metadata(), "protocol_extensions": ["sony.net/WMFU"]}
        device = reader.normalize_device(info, [])
        self.assertEqual(device["platform"], "mtp")
        self.assertGreater(len(device["warnings"]), 1)

    def test_missing_or_ambiguous_usb_serial_is_never_replaced_with_mtp_uuid(self):
        usb = reader.parse_usb_devices(plistlib.dumps([usb_node()]))
        for devices in ([], usb + usb):
            self.assertEqual(reader.normalize_device(metadata(), devices)["identity"]["serial_number"], "")

    def test_worker_handles_native_library_output_and_timeout(self):
        output = "libmtp diagnostic\n" + reader.RESULT_MARKER + json.dumps({"devices": []}) + "\n"
        with patch.object(reader.subprocess, "run", return_value=Mock(returncode=0, stdout=output)) as run:
            self.assertEqual(reader.run_worker("library"), {"devices": []})
            self.assertEqual(run.call_args.kwargs["timeout"], reader.DEVICE_TIMEOUT)
        with patch.object(reader.subprocess, "run", side_effect=subprocess.TimeoutExpired("mtp", 10)):
            with self.assertRaises(BridgeReadError) as error:
                reader.run_worker("library")
            self.assertEqual(error.exception.code, "MTP_TIMEOUT")

    def test_worker_rejects_failed_or_invalid_result(self):
        for code, output in ((1, ""), (0, reader.RESULT_MARKER + "broken"), (0, reader.RESULT_MARKER + "[]")):
            with patch.object(reader.subprocess, "run", return_value=Mock(returncode=code, stdout=output)):
                with self.assertRaises(BridgeReadError):
                    reader.run_worker("library")

    def test_read_releases_lock_after_device_error_and_does_not_require_interface_nodes(self):
        usb = reader.parse_usb_devices(plistlib.dumps([usb_node()]))
        with patch.object(reader, "scan_usb_devices", return_value=usb), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", side_effect=[
                    {"detected": [metadata()["usb"]]}, {"errors": [{}]},
                    {"detected": [metadata()["usb"]]}, {"devices": [metadata()]},
                ]):
            with self.assertRaises(BridgeReadError):
                reader.read_devices()
            self.assertEqual(len(reader.read_devices()), 1)

    def test_timed_out_phone_does_not_hide_another_successful_phone(self):
        usb = reader.parse_usb_devices(plistlib.dumps([usb_node(), usb_node("SECOND", 2, location=0x200000)]))
        with patch.object(reader, "scan_usb_devices", return_value=usb), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", side_effect=[
                    {"detected": [metadata()["usb"], metadata(2)["usb"]]},
                    BridgeReadError("MTP_TIMEOUT", "timed out"), {"devices": [metadata(2)]},
                ]):
            result = reader.read_result()
        self.assertEqual(result["data"][0]["identity"]["serial_number"], "SECOND")
        self.assertEqual(result["warnings"][0]["code"], "MTP_TIMEOUT")
        self.assertEqual(result["warnings"][0]["device_id"], reader.device_id(metadata()["usb"], usb))

    def test_disconnected_target_cannot_be_replaced_by_another_phone(self):
        with patch.object(reader, "scan_usb_devices", return_value=[]), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", side_effect=[
                    {"detected": [metadata()["usb"]]}, {"devices": [metadata(2)]},
                ]):
            result = reader.read_result()
        self.assertEqual(result["data"], [])
        self.assertEqual(result["warnings"][0]["code"], "MTP_NO_DEVICE")

    def test_hot_swap_cannot_combine_one_phones_metadata_with_another_sn(self):
        before = reader.parse_usb_devices(plistlib.dumps([usb_node("FIRST")]))
        after = reader.parse_usb_devices(plistlib.dumps([usb_node("REPLACED")]))
        with patch.object(reader, "scan_usb_devices", side_effect=[before, before, after]), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", side_effect=[
                    {"detected": [metadata()["usb"]]}, {"devices": [metadata()]},
                ]):
            result = reader.read_result()
        self.assertEqual(result["data"], [])
        self.assertEqual(result["warnings"][0]["code"], "MTP_CONNECTION_CHANGED")

    def test_meizu_address_change_after_read_keeps_verified_usb_binding(self):
        before = reader.parse_usb_devices(plistlib.dumps([usb_node()]))
        after = reader.parse_usb_devices(plistlib.dumps([usb_node(address=2)]))
        with patch.object(reader, "scan_usb_devices", side_effect=[before, before, after]), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", side_effect=[
                    {"detected": [metadata()["usb"]]}, {"devices": [metadata()]},
                ]):
            result = reader.read_result()
        self.assertEqual(result["warnings"], [])
        self.assertEqual(result["data"][0]["identity"]["serial_number"], "USB-SN")
        self.assertEqual(result["data"][0]["device_id"], reader.device_id(metadata(2)["usb"], after))

    def test_address_change_before_read_targets_current_address_not_stale_one(self):
        before = reader.parse_usb_devices(plistlib.dumps([usb_node()]))
        after = reader.parse_usb_devices(plistlib.dumps([usb_node(address=2)]))
        with patch.object(reader, "scan_usb_devices", side_effect=[before, after, after]), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", side_effect=[
                    {"detected": [metadata()["usb"]]}, {"devices": [metadata(2)]},
                ]) as worker:
            result = reader.read_result()
        self.assertEqual(result["warnings"], [])
        self.assertEqual(worker.call_args.kwargs["target"], metadata(2)["usb"])
        self.assertEqual(result["data"][0]["identity"]["serial_number"], "USB-SN")

    def test_scan_ids_do_not_change_when_temporary_usb_address_changes(self):
        before = reader.parse_usb_devices(plistlib.dumps([usb_node()]))
        after = reader.parse_usb_devices(plistlib.dumps([usb_node(address=2)]))
        with patch.object(reader, "scan_usb_devices", side_effect=[before, after]), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", side_effect=[
                    {"detected": [metadata()["usb"]]}, {"detected": [metadata(2)["usb"]]},
                ]):
            self.assertEqual(reader.scan_device_ids(), reader.scan_device_ids())

    def test_same_model_or_same_serial_on_another_physical_port_is_not_a_match(self):
        before = reader.parse_usb_devices(plistlib.dumps([usb_node()]))[0]
        other_port = reader.parse_usb_devices(plistlib.dumps([usb_node(address=2, location=0x200000)]))
        other_serial = reader.parse_usb_devices(plistlib.dumps([usb_node("OTHER", address=2)]))
        for current in (other_port, other_serial, other_port + other_serial):
            with patch.object(reader, "scan_usb_devices", return_value=current):
                with self.assertRaises(BridgeReadError) as error:
                    reader.confirm_usb_identity(before)
                self.assertEqual(error.exception.code, "MTP_CONNECTION_CHANGED")

    def test_reenumeration_waits_briefly_for_the_same_usb_identity(self):
        before = reader.parse_usb_devices(plistlib.dumps([usb_node()]))[0]
        after = reader.parse_usb_devices(plistlib.dumps([usb_node(address=2)]))
        with patch.object(reader, "scan_usb_devices", side_effect=[[], BridgeReadError("USB_SCAN_FAILED", "retry"), after]):
            self.assertEqual(reader.confirm_usb_identity(before)["device_number"], 2)
        with patch.object(reader.time, "monotonic", return_value=20), \
                patch.object(reader, "scan_usb_devices") as scan:
            with self.assertRaises(BridgeReadError) as error:
                reader.confirm_usb_identity(before, deadline=19)
            self.assertEqual(error.exception.code, "MTP_BATCH_TIMEOUT")
            scan.assert_not_called()

    def test_address_change_without_usb_serial_is_not_assumed_to_be_same_phone(self):
        before = reader.parse_usb_devices(plistlib.dumps([usb_node(serial="")]))[0]
        after = reader.parse_usb_devices(plistlib.dumps([usb_node(serial="", address=2)]))
        with patch.object(reader, "scan_usb_devices", return_value=after):
            with self.assertRaises(BridgeReadError):
                reader.confirm_usb_identity(before)

    def test_empty_ioreg_output_is_a_valid_no_devices_result(self):
        self.assertEqual(reader.parse_usb_devices(b""), [])

    def test_empty_detection_does_not_try_to_read_keyboards_or_charging_devices(self):
        with patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader, "run_worker", return_value={"detected": []}) as worker:
            self.assertEqual(reader.read_result(), {"data": [], "warnings": []})
            self.assertEqual(worker.call_count, 1)

    def test_batch_time_limit_and_busy_lock_have_recoverable_errors(self):
        reader._read_lock.acquire()
        try:
            with patch.object(reader, "library_path", return_value="library"):
                with self.assertRaises(BridgeReadError) as error:
                    reader.read_result()
                self.assertEqual(error.exception.code, "MTP_BUSY")
        finally:
            reader._read_lock.release()
        with patch.object(reader, "scan_usb_devices", return_value=[]), \
                patch.object(reader, "library_path", return_value="library"), \
                patch.object(reader.time, "monotonic", side_effect=[0, reader.BATCH_TIMEOUT + 1]), \
                patch.object(reader, "run_worker", return_value={"detected": [metadata()["usb"]]}) as worker:
            result = reader.read_result()
            self.assertEqual(result["warnings"][0]["code"], "MTP_BATCH_TIMEOUT")
            self.assertEqual(worker.call_count, 1)

    def test_windows_does_not_advertise_android(self):
        with patch.object(reader.sys, "platform", "win32"):
            self.assertEqual(reader.scan_usb_devices(), [])
            self.assertFalse(device_reader.capabilities()["android_mtp"])

    def test_facade_preserves_iphone_and_adds_android(self):
        with patch.object(device_reader.ios_reader, "scan_device_ids", return_value=["iphone"]), \
                patch.object(device_reader.ios_reader, "read_device", return_value={"platform": "ios"}), \
                patch.object(reader, "read_result", return_value={"data": [{"platform": "android"}], "warnings": []}):
            self.assertEqual([item["platform"] for item in device_reader.read_devices()], ["ios", "android"])

    def test_facade_keeps_iphone_when_android_fails_and_reports_partial_result(self):
        with patch.object(device_reader.ios_reader, "scan_device_ids", return_value=["iphone"]), \
                patch.object(device_reader.ios_reader, "read_device", return_value={"platform": "ios"}), \
                patch.object(reader, "read_result", side_effect=BridgeReadError("MTP_TIMEOUT", "timed out")):
            result = device_reader.response_payload(device_reader.read_result())
        self.assertEqual(result["code"], 0)
        self.assertTrue(result["partial"])
        self.assertEqual(result["data"][0]["device_id"], "iphone")
        self.assertEqual(result["warnings"][0]["transport"], "mtp")

    def test_facade_keeps_android_when_apple_scan_fails(self):
        with patch.object(device_reader.ios_reader, "scan_device_ids", side_effect=BridgeReadError("USB_SCAN_FAILED", "no driver")), \
                patch.object(reader, "read_result", return_value={"data": [{"platform": "android"}], "warnings": []}), \
                patch.object(reader, "scan_device_ids", return_value=["mtp:test"]):
            result = device_reader.response_payload(device_reader.read_result())
            scan = device_reader.response_payload(device_reader.scan_result())
        self.assertEqual(result["count"], 1)
        self.assertTrue(result["partial"])
        self.assertEqual(scan["data"], ["mtp:test"])

    def test_no_successes_is_an_error_not_a_successful_empty_list(self):
        result = device_reader.response_payload({"data": [], "warnings": [{"code": "MTP_BUSY", "message": "busy"}]})
        self.assertEqual(result["code"], "MTP_BUSY")
        self.assertFalse(result["partial"])


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
