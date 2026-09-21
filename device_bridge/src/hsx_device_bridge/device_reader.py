from . import android_reader, ios_reader
from .ios_reader import BridgeReadError


def _warning(error, transport, device_id=""):
    return {"code": error.code, "message": str(error), "transport": transport, "device_id": device_id}


def scan_result():
    result = {"data": [], "warnings": []}
    for transport, reader in (("ios", ios_reader), ("mtp", android_reader)):
        try:
            result["data"].extend(reader.scan_device_ids())
        except BridgeReadError as exc:
            result["warnings"].append(_warning(exc, transport))
    return result


def scan_device_ids():
    return scan_result()["data"]


def read_result(include_raw=False):
    result = {"data": [], "warnings": []}
    try:
        ids = ios_reader.scan_device_ids()
    except BridgeReadError as exc:
        ids = []
        result["warnings"].append(_warning(exc, "ios"))
    for identifier in ids:
        try:
            device = ios_reader.read_device(identifier, include_raw=include_raw)
            device["device_id"] = identifier
            result["data"].append(device)
        except BridgeReadError as exc:
            result["warnings"].append(_warning(exc, "ios", identifier))
    try:
        android = android_reader.read_result(include_raw=include_raw)
        result["data"].extend(android["data"])
        result["warnings"].extend({**item, "transport": "mtp"} for item in android["warnings"])
    except BridgeReadError as exc:
        result["warnings"].append(_warning(exc, "mtp"))
    return result


def response_payload(result):
    devices, warnings = result["data"], result["warnings"]
    failed = not devices and bool(warnings)
    return {
        "code": warnings[0]["code"] if failed else 0,
        "message": "；".join(item["message"] for item in warnings) if failed else "",
        "count": len(devices), "data": devices, "warnings": warnings,
        "partial": bool(devices and warnings),
    }


def read_devices(include_raw=False):
    result = read_result(include_raw)
    if not result["data"] and result["warnings"]:
        first = result["warnings"][0]
        raise BridgeReadError(first["code"], first["message"])
    return result["data"]


def capabilities():
    return {"ios": True, **android_reader.capabilities(), "partial_results": True}
