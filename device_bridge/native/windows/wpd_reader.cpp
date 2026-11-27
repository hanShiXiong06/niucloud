#define NOMINMAX
#include <windows.h>
#include <PortableDeviceApi.h>
#include <PortableDevice.h>
#include <PortableDeviceTypes.h>
#include <initguid.h>
#include <devpkey.h>
#include <cfgmgr32.h>
#include <wrl/client.h>

#include <algorithm>
#include <cwchar>
#include <iostream>
#include <memory>
#include <sstream>
#include <vector>
#include "wpd_contract.h"
#include "bridge_version.h"

using Microsoft::WRL::ComPtr;

namespace {

constexpr DWORD kMaxDevices = 64;
constexpr ULONG kMaxPropertyBytes = 65536;

struct ReadError {
    const char* code;
    HRESULT hr;
};

void require(HRESULT hr, const char* code) {
    if (FAILED(hr)) throw ReadError{hr == E_ACCESSDENIED ? "MTP_ACCESS_DENIED" : code, hr};
}

struct ComApartment {
    ComApartment() { require(CoInitializeEx(nullptr, COINIT_MULTITHREADED), "WPD_UNAVAILABLE"); }
    ~ComApartment() { CoUninitialize(); }
};

struct TaskMemFree {
    void operator()(wchar_t* value) const { CoTaskMemFree(value); }
};

std::string utf8(const std::wstring& value) {
    if (value.empty()) return "";
    if (value.size() > kMaxPropertyBytes) throw ReadError{"MTP_READ_FAILED", E_INVALIDARG};
    const int size = WideCharToMultiByte(CP_UTF8, WC_ERR_INVALID_CHARS, value.data(),
                                       static_cast<int>(value.size()), nullptr, 0, nullptr, nullptr);
    if (!size) throw ReadError{"MTP_READ_FAILED", E_INVALIDARG};
    std::string result(size, '\0');
    if (WideCharToMultiByte(CP_UTF8, WC_ERR_INVALID_CHARS, value.data(), static_cast<int>(value.size()),
                            result.data(), size, nullptr, nullptr) != size) {
        throw ReadError{"MTP_READ_FAILED", E_INVALIDARG};
    }
    return result;
}

std::string json(const std::wstring& value) { return hsx::json_string(utf8(value)); }

std::string envelope(const std::string& fields) {
    return "{\"schema_version\":1,\"version\":" + hsx::json_string(HSX_BRIDGE_VERSION) + "," + fields + "}";
}

std::wstring instance_id(DEVINST node) {
    ULONG length = 0;
    if (CM_Get_Device_ID_Size(&length, node, 0) != CR_SUCCESS || length > kMaxPropertyBytes) return L"";
    std::vector<wchar_t> buffer(length + 1, L'\0');
    if (CM_Get_Device_IDW(node, buffer.data(), length + 1, 0) != CR_SUCCESS) return L"";
    return buffer.data();
}

std::optional<DEVINST> locate(const std::wstring& pnp) {
    DEVINST node = 0;
    if (CM_Locate_DevNodeW(&node, const_cast<wchar_t*>(pnp.c_str()), CM_LOCATE_DEVNODE_NORMAL) == CR_SUCCESS) return node;
    // WPD may return an interface symbolic link instead of a devnode instance ID.
    DEVPROPTYPE type = 0;
    ULONG bytes = 0;
    if (CM_Get_Device_Interface_PropertyW(pnp.c_str(), &DEVPKEY_Device_InstanceId, &type,
                                          nullptr, &bytes, 0) != CR_BUFFER_SMALL
        || !bytes || bytes > kMaxPropertyBytes) return std::nullopt;
    std::vector<wchar_t> buffer(bytes / sizeof(wchar_t) + 1, L'\0');
    if (CM_Get_Device_Interface_PropertyW(pnp.c_str(), &DEVPKEY_Device_InstanceId, &type,
                                          reinterpret_cast<PBYTE>(buffer.data()), &bytes, 0) != CR_SUCCESS
        || type != DEVPROP_TYPE_STRING) return std::nullopt;
    if (CM_Locate_DevNodeW(&node, buffer.data(), CM_LOCATE_DEVNODE_NORMAL) != CR_SUCCESS) return std::nullopt;
    return node;
}

template<typename T>
T node_property(DEVINST node, const DEVPROPKEY& key, DEVPROPTYPE expected) {
    T value{};
    DEVPROPTYPE type = 0;
    ULONG size = sizeof(value);
    if (CM_Get_DevNode_PropertyW(node, &key, &type, reinterpret_cast<PBYTE>(&value), &size, 0) != CR_SUCCESS
        || type != expected || size != sizeof(value)) return T{};
    return value;
}

unsigned long long arrival(DEVINST node) {
    auto value = node_property<FILETIME>(node, DEVPKEY_Device_LastArrivalDate, DEVPROP_TYPE_FILETIME);
    return (static_cast<unsigned long long>(value.dwHighDateTime) << 32) | value.dwLowDateTime;
}

struct Candidate {
    std::wstring pnp, usb_instance, serial, token;
    bool unique_serial = false;
    bool connection_known = false;
    std::string encode() const {
        return "{\"pnp_id\":" + json(pnp) + ",\"usb_instance_id\":" + json(usb_instance)
            + ",\"usb_serial_number\":" + json(serial) + ",\"connection_token\":" + json(token)
            + ",\"usb_serial_unique\":" + (unique_serial ? "true" : "false") + "}";
    }
};

std::optional<Candidate> usb_candidate(const std::wstring& pnp) {
    auto located = locate(pnp);
    if (!located) return std::nullopt;
    const auto interface_arrival = arrival(*located);
    DEVINST node = *located;
    for (int depth = 0; depth < 16; ++depth) {
        const auto instance = instance_id(node);
        const auto usb = hsx::parse_usb_instance(instance);
        if (usb) {
            if (usb->vendor == 0x05AC) return std::nullopt;  // iPhone stays on usbmux/lockdown.
            ULONG capabilities = 0, type = 0, size = sizeof(capabilities);
            const bool unique = CM_Get_DevNode_Registry_PropertyW(node, CM_DRP_CAPABILITIES, &type,
                &capabilities, &size, 0) == CR_SUCCESS && type == REG_DWORD
                && (capabilities & CM_DEVCAP_UNIQUEID) != 0;
            const auto usb_arrival = arrival(node);
            Candidate result;
            result.pnp = pnp;
            result.usb_instance = instance;
            result.unique_serial = unique;
            result.serial = unique ? usb->serial : L"";
            result.token = instance + L"|" + std::to_wstring(usb_arrival) + L"|" + std::to_wstring(interface_arrival);
            result.connection_known = unique || usb_arrival != 0 || interface_arrival != 0;
            return result;
        }
        DEVINST parent = 0;
        if (CM_Get_Parent(&parent, node, 0) != CR_SUCCESS) break;
        node = parent;
    }
    return std::nullopt;
}

std::vector<std::wstring> devices(IPortableDeviceManager* manager) {
    for (int attempt = 0; attempt < 3; ++attempt) {
        require(manager->RefreshDeviceList(), "WPD_UNAVAILABLE");
        DWORD count = 0;
        require(manager->GetDevices(nullptr, &count), "WPD_UNAVAILABLE");
        if (count > kMaxDevices) throw ReadError{"MTP_TOO_MANY_DEVICES", E_FAIL};
        if (!count) return {};
        struct Names {
            std::vector<LPWSTR> values;
            explicit Names(DWORD size) : values(size, nullptr) {}
            ~Names() { for (auto value : values) CoTaskMemFree(value); }
        } names(count);
        const auto hr = manager->GetDevices(names.values.data(), &count);
        require(hr, "WPD_UNAVAILABLE");
        if (hr == S_FALSE) continue;
        if (count > names.values.size()) throw ReadError{"MTP_READ_FAILED", E_FAIL};
        std::vector<std::wstring> result;
        for (DWORD i = 0; i < count; ++i) if (names.values[i]) result.emplace_back(names.values[i]);
        return result;
    }
    throw ReadError{"MTP_CONNECTION_CHANGED", E_FAIL};
}

std::wstring string_property(IPortableDeviceValues* values, REFPROPERTYKEY key) {
    LPWSTR raw = nullptr;
    const auto hr = values->GetStringValue(key, &raw);
    std::unique_ptr<wchar_t, TaskMemFree> owner(raw);
    if (FAILED(hr) || !raw) return L"";
    const auto length = wcsnlen(raw, kMaxPropertyBytes + 1);
    if (length > kMaxPropertyBytes) throw ReadError{"MTP_READ_FAILED", E_INVALIDARG};
    return std::wstring(raw, length);
}

Candidate require_connection(IPortableDeviceManager* manager, const std::wstring& pnp, const std::wstring& token) {
    const auto current = devices(manager);
    if (std::find(current.begin(), current.end(), pnp) == current.end()) throw ReadError{"MTP_NO_DEVICE", E_FAIL};
    const auto candidate = usb_candidate(pnp);
    if (!candidate || !candidate->connection_known || candidate->token != token) {
        throw ReadError{"MTP_CONNECTION_CHANGED", E_FAIL};
    }
    return *candidate;
}

std::string read_device(IPortableDeviceManager* manager, const std::wstring& pnp, const std::wstring& token) {
    const auto before = require_connection(manager, pnp, token);
    ComPtr<IPortableDeviceValues> client;
    require(CoCreateInstance(CLSID_PortableDeviceValues, nullptr, CLSCTX_INPROC_SERVER,
                             IID_PPV_ARGS(client.GetAddressOf())), "WPD_UNAVAILABLE");
    require(client->SetStringValue(WPD_CLIENT_NAME, L"HSX Device Bridge"), "WPD_UNAVAILABLE");
    require(client->SetUnsignedIntegerValue(WPD_CLIENT_DESIRED_ACCESS, GENERIC_READ), "WPD_UNAVAILABLE");
    require(client->SetUnsignedIntegerValue(WPD_CLIENT_SECURITY_QUALITY_OF_SERVICE, SECURITY_IMPERSONATION), "WPD_UNAVAILABLE");
    ComPtr<IPortableDevice> device;
    require(CoCreateInstance(CLSID_PortableDeviceFTM, nullptr, CLSCTX_INPROC_SERVER,
                             IID_PPV_ARGS(device.GetAddressOf())), "WPD_UNAVAILABLE");
    require(device->Open(pnp.c_str(), client.Get()), "MTP_OPEN_FAILED");
    ComPtr<IPortableDeviceContent> content;
    ComPtr<IPortableDeviceProperties> properties;
    ComPtr<IPortableDeviceKeyCollection> keys;
    ComPtr<IPortableDeviceValues> values;
    require(device->Content(content.GetAddressOf()), "MTP_READ_FAILED");
    require(content->Properties(properties.GetAddressOf()), "MTP_READ_FAILED");
    require(CoCreateInstance(CLSID_PortableDeviceKeyCollection, nullptr, CLSCTX_INPROC_SERVER,
                             IID_PPV_ARGS(keys.GetAddressOf())), "WPD_UNAVAILABLE");
    // Only the root DEVICE properties are requested. Never enumerate or read user files.
    for (const auto& key : {WPD_DEVICE_MANUFACTURER, WPD_DEVICE_MODEL, WPD_DEVICE_SERIAL_NUMBER,
                           WPD_DEVICE_FRIENDLY_NAME, WPD_DEVICE_FIRMWARE_VERSION,
                           WPD_DEVICE_POWER_LEVEL, WPD_DEVICE_PROTOCOL}) {
        require(keys->Add(key), "MTP_READ_FAILED");
    }
    require(properties->GetValues(WPD_DEVICE_OBJECT_ID, keys.Get(), values.GetAddressOf()), "MTP_READ_FAILED");
    const auto protocol = string_property(values.Get(), WPD_DEVICE_PROTOCOL);
    if (!hsx::is_mtp(protocol)) throw ReadError{"MTP_UNSUPPORTED", E_FAIL};
    const auto model = string_property(values.Get(), WPD_DEVICE_MODEL);
    if (model.empty()) throw ReadError{"MTP_READ_FAILED", E_FAIL};
    DWORD battery = 0;
    const bool battery_available = SUCCEEDED(values->GetUnsignedIntegerValue(WPD_DEVICE_POWER_LEVEL, &battery)) && battery <= 100;
    const std::string metadata = "\"usb\":" + before.encode()
        + ",\"manufacturer\":" + json(string_property(values.Get(), WPD_DEVICE_MANUFACTURER))
        + ",\"model_code\":" + json(model)
        + ",\"serial_number\":" + json(string_property(values.Get(), WPD_DEVICE_SERIAL_NUMBER))
        + ",\"friendly_name\":" + json(string_property(values.Get(), WPD_DEVICE_FRIENDLY_NAME))
        + ",\"device_version_raw\":" + json(string_property(values.Get(), WPD_DEVICE_FIRMWARE_VERSION))
        + ",\"battery_level_percent\":" + (battery_available ? std::to_string(battery) : "null")
        + ",\"protocol\":" + json(protocol) + ",\"protocol_extensions\":[]";
    device->Close();
    const auto after = require_connection(manager, pnp, token);
    if (before.encode() != after.encode()) throw ReadError{"MTP_CONNECTION_CHANGED", E_FAIL};
    return envelope("\"devices\":[{" + metadata + "}]");
}

std::string execute(int argc, wchar_t** argv) {
    if (argc < 2 || argc > 4) throw ReadError{"INVALID_ARGUMENT", E_INVALIDARG};
    ComPtr<IPortableDeviceManager> manager;
    require(CoCreateInstance(CLSID_PortableDeviceManager, nullptr, CLSCTX_INPROC_SERVER,
                             IID_PPV_ARGS(manager.GetAddressOf())), "WPD_UNAVAILABLE");
    const std::wstring command = argv[1];
    if (command == L"--self-check" && argc == 2) {
        // Verify all COM classes used by a read, without needing a connected phone.
        ComPtr<IPortableDevice> device;
        ComPtr<IPortableDeviceValues> values;
        ComPtr<IPortableDeviceKeyCollection> keys;
        require(CoCreateInstance(CLSID_PortableDeviceFTM, nullptr, CLSCTX_INPROC_SERVER,
                                 IID_PPV_ARGS(device.GetAddressOf())), "WPD_UNAVAILABLE");
        require(CoCreateInstance(CLSID_PortableDeviceValues, nullptr, CLSCTX_INPROC_SERVER,
                                 IID_PPV_ARGS(values.GetAddressOf())), "WPD_UNAVAILABLE");
        require(CoCreateInstance(CLSID_PortableDeviceKeyCollection, nullptr, CLSCTX_INPROC_SERVER,
                                 IID_PPV_ARGS(keys.GetAddressOf())), "WPD_UNAVAILABLE");
        devices(manager.Get());
        return envelope("\"ready\":true,\"backend\":\"windows_wpd\"");
    }
    if (command == L"--scan" && argc == 2) {
        std::string result = "\"detected\":[";
        bool first = true;
        for (const auto& pnp : devices(manager.Get())) {
            const auto candidate = usb_candidate(pnp);
            if (!candidate) continue;
            if (!first) result += ',';
            first = false;
            result += candidate->encode();
        }
        return envelope(result + ']');
    }
    if (command == L"--read" && argc == 4) return read_device(manager.Get(), argv[2], argv[3]);
    throw ReadError{"INVALID_ARGUMENT", E_INVALIDARG};
}

}  // namespace

int wmain(int argc, wchar_t** argv) {
    try {
        ComApartment apartment;
        std::cout << execute(argc, argv) << std::endl;
        return 0;
    } catch (const ReadError& error) {
        std::ostringstream hr;
        hr << "0x" << std::hex << static_cast<unsigned long>(error.hr);
        std::cout << envelope("\"error\":" + hsx::json_string(error.code) + ",\"hresult\":" + hsx::json_string(hr.str())) << std::endl;
    } catch (...) {
        std::cout << envelope("\"error\":\"MTP_READ_FAILED\"") << std::endl;
    }
    return 2;
}
