import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import Mock, patch

from hsx_device_bridge import __version__, android_reader, device_reader
from hsx_device_bridge import windows_mtp_reader as reader
from hsx_device_bridge.ios_reader import BridgeReadError
from hsx_device_bridge.mtp_snapshot import NOT_READ_FIELDS, normalize_snapshot


def candidate(serial="SN-1", vendor="04E8", product="6860", unique=True, connection="100"):
    instance = "USB\\VID_%s&PID_%s\\%s" % (vendor, product, serial)
    return {
        "pnp_id": "\\\\?\\usb#" + instance,
        "usb_instance_id": instance,
        "connection_token": instance + "|" + connection + "|" + connection,
        "usb_serial_number": serial if unique else "",
        "usb_serial_unique": unique,
    }


def metadata(target=None, model="SM-G9910", battery=75):
    return {
        "usb": target or candidate(), "model_code": model, "serial_number": "MTP-UUID-NOT-SN",
        "manufacturer": "Samsung", "friendly_name": "我的手机", "battery_level_percent": battery,
        "device_version_raw": "FIRMWARE-NOT-ANDROID-VERSION", "protocol": "MTP: 1.0",
        "protocol_extensions": [],
    }


def native_report(**fields):
    return {"schema_version": 1, "version": __version__, **fields}


class WindowsHelperTest(unittest.TestCase):
    def setUp(self):
        helper = patch.object(reader, "helper_path", return_value="C:/bridge/native/hsx_wpd_reader.exe")
        helper.start()
        self.addCleanup(helper.stop)

    def test_helper_uses_utf8_and_no_shell_or_console(self):
        payload = native_report(devices=[metadata()])
        with patch.object(reader.subprocess, "run", return_value=Mock(returncode=0, stdout=json.dumps(payload))) as run:
            self.assertEqual(reader.run_helper("--read", "pnp with & spaces", "token"), payload)
        self.assertEqual(run.call_args.args[0], ["C:/bridge/native/hsx_wpd_reader.exe", "--read", "pnp with & spaces", "token"])
        self.assertEqual(run.call_args.kwargs["encoding"], "utf-8")
        self.assertEqual(run.call_args.kwargs["creationflags"], getattr(subprocess, "CREATE_NO_WINDOW", 0))
        self.assertNotIn("shell", run.call_args.kwargs)
        self.assertEqual(run.call_args.kwargs["timeout"], reader.DEVICE_TIMEOUT)

    def test_missing_helper_does_not_fall_back_to_adb(self):
        with patch.object(reader, "helper_path", return_value=""), patch.object(reader.subprocess, "run") as run:
            with self.assertRaises(BridgeReadError) as error:
                reader.run_helper("--scan")
        self.assertEqual(error.exception.code, "MTP_UNAVAILABLE")
        run.assert_not_called()

    def test_timeout_and_process_failures_are_actionable(self):
        for failure, code in (
            (subprocess.TimeoutExpired("helper", 8), "MTP_TIMEOUT"),
            (OSError("bad executable"), "MTP_WORKER_FAILED"),
            (UnicodeError("bad encoding"), "MTP_INVALID_RESULT"),
        ):
            with self.subTest(code=code), patch.object(reader.subprocess, "run", side_effect=failure):
                with self.assertRaises(BridgeReadError) as error:
                    reader.run_helper("--scan")
                self.assertEqual(error.exception.code, code)

    def test_native_error_codes_survive_nonzero_exit(self):
        for code in reader.ERROR_MESSAGES:
            with self.subTest(code=code), patch.object(reader.subprocess, "run", return_value=Mock(
                returncode=2, stdout=json.dumps(native_report(error=code, hresult="0x80070005")),
            )):
                with self.assertRaises(BridgeReadError) as error:
                    reader.run_helper("--scan")
                self.assertEqual(error.exception.code, code)
                self.assertTrue(str(error.exception))

    def test_broken_or_old_helper_results_are_not_success(self):
        for output, code, expected in (
            ("broken", 0, "MTP_INVALID_RESULT"), ("[]", 0, "MTP_INVALID_RESULT"),
            (json.dumps(native_report()), 2, "MTP_WORKER_FAILED"),
            (json.dumps({"schema_version": 1, "version": "0.1.0"}), 0, "MTP_VERSION_MISMATCH"),
            (json.dumps({"version": __version__}), 0, "MTP_VERSION_MISMATCH"),
        ):
            with self.subTest(output=output), patch.object(reader.subprocess, "run", return_value=Mock(returncode=code, stdout=output)):
                with self.assertRaises(BridgeReadError) as error:
                    reader.run_helper("--scan")
                self.assertEqual(error.exception.code, expected)

    def test_diagnostics_does_not_confuse_no_phone_with_missing_wpd(self):
        with patch.object(reader, "run_helper", return_value=native_report(ready=True, backend="windows_wpd")):
            self.assertTrue(reader.diagnostics()["ready"])
        with patch.object(reader, "run_helper", side_effect=BridgeReadError("WPD_UNAVAILABLE", "missing")):
            report = reader.diagnostics()
            self.assertFalse(report["ready"])
            self.assertEqual(report["code"], "WPD_UNAVAILABLE")


class WindowsMtpReaderTest(unittest.TestCase):
    def test_no_devices_is_empty_without_attempting_a_read(self):
        with patch.object(reader, "run_helper", return_value=native_report(detected=[])) as run:
            self.assertEqual(reader.read_result(), {"data": [], "warnings": []})
        run.assert_called_once_with("--scan", timeout=reader.DISCOVERY_TIMEOUT)

    def test_samsung_iqoo_meizu_and_unlisted_brands_use_same_snapshot_contract(self):
        for vendor, product, model in (("04E8", "6860", "SM-G9910"), ("2D95", "6013", "V1955A"),
                                        ("2A45", "2008", "16th"), ("F123", "0001", "NEW-MODEL")):
            target = candidate(vendor=vendor, product=product)
            with self.subTest(model=model), patch.object(reader, "run_helper", side_effect=[
                native_report(detected=[target]), native_report(devices=[metadata(target, model)]),
            ]) as run:
                result = reader.read_result(include_raw=True)
            self.assertEqual(result["warnings"], [])
            device = result["data"][0]
            self.assertEqual(device["schema_version"], "hsx.device.snapshot.v1")
            self.assertEqual(device["hardware"]["product_type"], model)
            self.assertEqual(device["identity"]["serial_number"], "SN-1")
            self.assertEqual(device["identity"]["mtp_serial_number"], "MTP-UUID-NOT-SN")
            self.assertEqual(device["identity"]["serial_number_source"], "usb_descriptor")
            self.assertFalse(device["identity"]["serial_number_verified"])
            self.assertEqual(device["platform"], "mtp")
            self.assertEqual(device["raw"]["usb"], target)
            self.assertEqual(run.call_args.args, ("--read", target["pnp_id"], target["connection_token"]))
            self.assertLessEqual(run.call_args.kwargs["timeout"], reader.DEVICE_TIMEOUT)

    def test_mtp_uuid_and_windows_generated_instance_never_become_sn(self):
        target = candidate("6&WINDOWS-PORT-ID&0000", unique=False)
        for offered_serial in ("", "UNTRUSTED-SERIAL"):
            target["usb_serial_number"] = offered_serial
            with patch.object(reader, "run_helper", side_effect=[
                native_report(detected=[target]), native_report(devices=[metadata(target)]),
            ]):
                device = reader.read_result()["data"][0]
            self.assertEqual(device["identity"]["serial_number"], "")
            self.assertNotIn("raw", device)
            self.assertTrue(device["warnings"])

    def test_hot_swap_or_reconnect_cannot_mix_phone_metadata_and_sn(self):
        target = candidate()
        for replacement in (candidate("SN-2"), candidate(connection="200"), candidate(vendor="2A45")):
            with patch.object(reader, "run_helper", side_effect=[
                native_report(detected=[target]), native_report(devices=[metadata(replacement)]),
            ]):
                result = reader.read_result()
            self.assertEqual(result["data"], [])
            self.assertEqual(result["warnings"][0]["code"], "MTP_CONNECTION_CHANGED")

    def test_same_model_on_multiple_phones_keeps_each_exact_identity(self):
        targets = [candidate("FIRST"), candidate("SECOND")]
        with patch.object(reader, "run_helper", side_effect=[native_report(detected=targets)] + [
            native_report(devices=[metadata(target)]) for target in targets
        ]):
            result = reader.read_result()
        self.assertEqual([row["identity"]["serial_number"] for row in result["data"]], ["FIRST", "SECOND"])
        self.assertEqual(len({row["device_id"] for row in result["data"]}), 2)

    def test_single_timeout_preserves_next_phone_and_releases_lock(self):
        first, second = candidate(), candidate("SECOND")
        with patch.object(reader, "run_helper", side_effect=[native_report(detected=[first, second]),
            BridgeReadError("MTP_TIMEOUT", "timed out"), native_report(devices=[metadata(second)]),
            native_report(detected=[]),
        ]):
            result = reader.read_result()
            self.assertEqual(reader.scan_device_ids(), [])
        self.assertEqual(result["data"][0]["identity"]["serial_number"], "SECOND")
        self.assertEqual(result["warnings"][0]["device_id"], reader.device_id(first))

    def test_read_batch_does_not_exceed_budget(self):
        with patch.object(reader.time, "monotonic", side_effect=[0, reader.BATCH_TIMEOUT + 1]), \
                patch.object(reader, "run_helper", return_value=native_report(detected=[candidate()])) as run:
            result = reader.read_result()
        self.assertEqual(result["warnings"][0]["code"], "MTP_BATCH_TIMEOUT")
        self.assertEqual(run.call_count, 1)

    def test_concurrent_scan_or_read_is_rejected_without_touching_phone(self):
        reader._read_lock.acquire()
        try:
            for operation in (reader.scan_device_ids, reader.read_result):
                with self.assertRaises(BridgeReadError) as error:
                    operation()
                self.assertEqual(error.exception.code, "MTP_BUSY")
        finally:
            reader._read_lock.release()

    def test_malformed_discovery_releases_lock(self):
        for detected in (None, {}, [None], [{}], [candidate()] * 65):
            with patch.object(reader, "run_helper", return_value=native_report(detected=detected)):
                with self.assertRaises(BridgeReadError):
                    reader.read_result()
            self.assertFalse(reader._read_lock.locked())

    def test_incomplete_native_read_is_reported_not_assumed_successful(self):
        for devices in (None, [], [None], [{"usb": None}], [metadata(model="")], [metadata(), metadata()]):
            with patch.object(reader, "run_helper", side_effect=[
                native_report(detected=[candidate()]), native_report(devices=devices),
            ]):
                result = reader.read_result()
            self.assertEqual(result["data"], [])
            self.assertEqual(len(result["warnings"]), 1)

    def test_id_is_stable_across_reconnect_and_duplicate_interfaces_are_not_reread(self):
        target = candidate()
        self.assertEqual(reader.device_id(target), reader.device_id(candidate(connection="200")))
        other_interface = {**target, "pnp_id": "second WPD interface"}
        with patch.object(reader, "run_helper", return_value=native_report(detected=[target, other_interface])):
            self.assertEqual(reader.scan_device_ids(), [reader.device_id(target)])

    def test_no_serial_reconnect_gets_new_id_so_auto_detect_does_not_skip_next_phone(self):
        before = candidate("WINDOWS-PORT-ID", unique=False, connection="100")
        after = candidate("WINDOWS-PORT-ID", unique=False, connection="200")
        self.assertNotEqual(reader.device_id(before), reader.device_id(after))

    def test_battery_and_other_unavailable_fields_are_never_invented(self):
        for battery in (0, 75, 100, None, 101, -1, True, "80", float("nan")):
            device = normalize_snapshot(metadata(battery=battery), "id")
            expected = battery if type(battery) is int and 0 <= battery <= 100 else None
            self.assertEqual(device["battery"]["level_percent"], expected)
            self.assertNotIn("health_percent", device["battery"])
            self.assertEqual(device["system"]["version"], "")
            self.assertEqual(device["identity"]["imei"], "")
            self.assertEqual(device["display"]["capacity"], "")
            self.assertEqual(device["display"]["color"], "")
            self.assertEqual(device["not_read_fields"], NOT_READ_FIELDS)


class WindowsRoutingTest(unittest.TestCase):
    def test_windows_dispatches_to_wpd_not_libmtp(self):
        with patch.object(android_reader.sys, "platform", "win32"), \
                patch.object(reader, "scan_device_ids", return_value=["windows-phone"]) as scan, \
                patch.object(reader, "read_result", return_value={"data": [], "warnings": []}) as read, \
                patch.object(android_reader, "run_worker") as mac:
            self.assertEqual(android_reader.scan_device_ids(), ["windows-phone"])
            self.assertEqual(android_reader.read_result(True), {"data": [], "warnings": []})
            scan.assert_called_once_with()
            read.assert_called_once_with(True)
            mac.assert_not_called()

    def test_capability_is_only_advertised_if_helper_is_bundled(self):
        for executable, expected in (("", False), ("native/helper.exe", True)):
            with patch.object(android_reader.sys, "platform", "win32"), patch.object(reader, "helper_path", return_value=executable):
                caps = device_reader.capabilities()
                self.assertEqual(caps["android_mtp"], expected)
                self.assertEqual(caps["android_mtp_backend"], "windows_wpd" if expected else "")
                self.assertTrue(caps["ios"])

    def test_frozen_helper_is_resolved_from_bundle_not_working_directory(self):
        with tempfile.TemporaryDirectory() as directory, patch.object(reader.sys, "platform", "win32"), \
                patch.object(reader.sys, "frozen", True, create=True), patch.object(reader.sys, "_MEIPASS", directory, create=True):
            self.assertEqual(reader.helper_path(), "")
            path = Path(directory) / "native" / "hsx_wpd_reader.exe"
            path.parent.mkdir()
            path.touch()
            self.assertEqual(reader.helper_path(), str(path))

    def test_mac_and_windows_shared_fields_match(self):
        info = metadata()
        info["usb"] = {"vendor_id": "0x04e8", "product_id": "0x6860", "bus_location": 1, "device_number": 2}
        usb = {"vendor_id": 0x04E8, "product_id": 0x6860, "bus_location": 1, "device_number": 2, "usb_serial_number": "SN-1"}
        mac = android_reader.normalize_device(info, [usb])
        windows = normalize_snapshot(metadata(), "windows-id", "SN-1")
        for key in ("identity", "hardware", "display", "system", "battery", "not_read_fields"):
            self.assertEqual(mac[key], windows[key])


if __name__ == "__main__":
    unittest.main()
