from . import android_reader, ios_reader


def scan_device_ids():
    return ios_reader.scan_device_ids() + [item["id"] for item in android_reader.scan_usb_devices()]


def read_devices(include_raw=False):
    return ios_reader.read_devices(include_raw=include_raw) + android_reader.read_devices(include_raw=include_raw)


def capabilities():
    android_mtp = bool(android_reader.library_path())
    return {"ios": True, "android_mtp": android_mtp, "android_brands": ["samsung"] if android_mtp else []}
