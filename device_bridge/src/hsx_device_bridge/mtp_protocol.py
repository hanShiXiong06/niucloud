"""Read device metadata over MTP, without ADB or access to user files.

Requires libmtp on macOS/Linux. This diagnostic does not register devices in ERP.
"""

import ctypes as C


RESULT_MARKER = "HSX_MTP_RESULT="


class DeviceEntry(C.Structure):
    _fields_ = [
        ("vendor", C.c_char_p),
        ("vendor_id", C.c_uint16),
        ("product", C.c_char_p),
        ("product_id", C.c_uint16),
        ("device_flags", C.c_uint32),
    ]


class RawDevice(C.Structure):
    _fields_ = [
        ("device_entry", DeviceEntry),
        ("bus_location", C.c_uint32),
        ("devnum", C.c_uint8),
    ]


class Storage(C.Structure):
    pass


Storage._fields_ = [
    ("id", C.c_uint32),
    ("storage_type", C.c_uint16),
    ("filesystem_type", C.c_uint16),
    ("access_capability", C.c_uint16),
    ("max_capacity", C.c_uint64),
    ("free_bytes", C.c_uint64),
    ("free_objects", C.c_uint64),
    ("description", C.c_char_p),
    ("volume_identifier", C.c_char_p),
    ("next", C.POINTER(Storage)),
    ("previous", C.POINTER(Storage)),
]


class DeviceHeader(C.Structure):
    # Prefix of the public LIBMTP_mtpdevice_t, not its private PTP parameters.
    _fields_ = [
        ("object_bitsize", C.c_uint8),
        ("params", C.c_void_p),
        ("usbinfo", C.c_void_p),
        ("storage", C.POINTER(Storage)),
    ]


STRING_FIELDS = {
    "manufacturer": "LIBMTP_Get_Manufacturername",
    "model_code": "LIBMTP_Get_Modelname",
    "serial_number": "LIBMTP_Get_Serialnumber",
    "friendly_name": "LIBMTP_Get_Friendlyname",
    "device_version_raw": "LIBMTP_Get_Deviceversion",
}


def load_library(path):
    lib = C.CDLL(path)
    signatures = {
        "LIBMTP_Init": ([], None),
        "LIBMTP_Detect_Raw_Devices": ([C.POINTER(C.POINTER(RawDevice)), C.POINTER(C.c_int)], C.c_int),
        "LIBMTP_Open_Raw_Device_Uncached": ([C.POINTER(RawDevice)], C.c_void_p),
        "LIBMTP_Release_Device": ([C.c_void_p], None),
        "LIBMTP_FreeMemory": ([C.c_void_p], None),
        "LIBMTP_Get_Batterylevel": ([C.c_void_p, C.POINTER(C.c_uint8), C.POINTER(C.c_uint8)], C.c_int),
        "LIBMTP_Get_Storage": ([C.c_void_p, C.c_int], C.c_int),
        "LIBMTP_Dump_Device_Info": ([C.c_void_p], None),
        **{name: ([C.c_void_p], C.c_void_p) for name in STRING_FIELDS.values()},
    }
    for name, (args, result) in signatures.items():
        fn = getattr(lib, name)
        fn.argtypes = args
        fn.restype = result
    return lib


def read_string(lib, device, getter):
    pointer = getattr(lib, getter)(device)
    if not pointer:
        return ""
    try:
        return C.string_at(pointer).decode("utf-8", errors="replace").strip()
    finally:
        lib.LIBMTP_FreeMemory(pointer)


def read_metadata(lib, device):
    result = {key: read_string(lib, device, getter) for key, getter in STRING_FIELDS.items()}
    maximum, current = C.c_uint8(), C.c_uint8()
    status = lib.LIBMTP_Get_Batterylevel(device, C.byref(maximum), C.byref(current))
    result["battery_level_percent"] = (
        round(current.value * 100 / maximum.value, 1)
        if status == 0 and 0 <= current.value <= maximum.value and maximum.value > 0
        else None
    )
    # MTP firmware and usable storage are not Android version or retail SKU capacity.
    result["not_read_fields"] = [
        "imei", "imei2", "android_version", "color", "sku_capacity",
        "battery_health_percent", "battery_cycle_count",
    ]
    return result


def read_storage(lib, device):
    if lib.LIBMTP_Get_Storage(device, 0) != 0:
        return {"storage_status": "unavailable", "storage": []}
    pointer = C.cast(device, C.POINTER(DeviceHeader)).contents.storage
    result, seen = [], set()
    while pointer:
        address = C.addressof(pointer.contents)
        if address in seen or len(seen) >= 64:
            raise RuntimeError("Invalid MTP storage list")
        seen.add(address)
        value = pointer.contents
        capacity = value.max_capacity if value.max_capacity < 2 ** 64 - 1 else None
        free = value.free_bytes if value.free_bytes < 2 ** 64 - 1 else None
        used = capacity - free if capacity is not None and free is not None and free <= capacity else None
        result.append({
            "storage_id": "0x%08x" % value.id,
            "description": (value.description or b"").decode("utf-8", errors="replace"),
            "storage_type": value.storage_type,
            "filesystem_type": value.filesystem_type,
            "access_capability": value.access_capability,
            "exposed_capacity_bytes": capacity,
            "free_bytes": free,
            "used_bytes": used,
            "exposed_capacity_gib": round(capacity / 1024 ** 3, 2) if capacity is not None else None,
            "free_gib": round(free / 1024 ** 3, 2) if free is not None else None,
        })
        pointer = value.next
    return {"storage_status": "ok", "storage": result}


def probe(library_path, vendor_id=None, extended=False):
    lib = load_library(library_path)
    lib.LIBMTP_Init()
    raw_devices = C.POINTER(RawDevice)()
    count = C.c_int()
    report = {"transport": "mtp", "read_only": True, "adb_used": False, "devices": [], "errors": []}
    status = lib.LIBMTP_Detect_Raw_Devices(C.byref(raw_devices), C.byref(count))
    try:
        if status == 5:  # LIBMTP_ERROR_NO_DEVICE_ATTACHED
            return report
        if status != 0:
            raise RuntimeError("MTP discovery failed (libmtp code %s)" % status)
        for index in range(count.value):
            raw = raw_devices[index]
            if vendor_id is not None and raw.device_entry.vendor_id != vendor_id:
                continue
            usb = {
                "vendor_id": "0x%04x" % raw.device_entry.vendor_id,
                "product_id": "0x%04x" % raw.device_entry.product_id,
                "bus_location": raw.bus_location,
                "device_number": raw.devnum,
            }
            # Uncached mode avoids enumerating the user's files and media.
            device = lib.LIBMTP_Open_Raw_Device_Uncached(C.byref(raw))
            if not device:
                report["errors"].append({"usb": usb, "error": "MTP_OPEN_FAILED"})
                continue
            try:
                metadata = {"usb": usb, **read_metadata(lib, device)}
                if extended:
                    metadata.update(read_storage(lib, device))
                    # Reports protocol capabilities, never enumerates actual objects.
                    lib.LIBMTP_Dump_Device_Info(device)
                report["devices"].append(metadata)
            finally:
                lib.LIBMTP_Release_Device(device)
    finally:
        if raw_devices:
            lib.LIBMTP_FreeMemory(raw_devices)
    return report
