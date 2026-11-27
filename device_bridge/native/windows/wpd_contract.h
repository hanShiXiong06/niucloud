#pragma once

#include <optional>
#include <string>

namespace hsx {

inline std::string json_string(const std::string& value) {
    const char* hex = "0123456789abcdef";
    std::string result = "\"";
    for (unsigned char ch : value) {
        if (ch == '"' || ch == '\\') {
            result += '\\';
            result += static_cast<char>(ch);
        } else if (ch < 0x20) {
            result += "\\u00";
            result += hex[ch >> 4];
            result += hex[ch & 15];
        } else {
            result += static_cast<char>(ch);
        }
    }
    return result + '"';
}

inline std::wstring ascii_upper(std::wstring value) {
    for (auto& ch : value) {
        if (ch >= L'a' && ch <= L'z') ch -= L'a' - L'A';
    }
    return value;
}

struct UsbInstance {
    unsigned int vendor;
    unsigned int product;
    std::wstring serial;
};

inline std::optional<unsigned int> hex_word(const std::wstring& text) {
    if (text.size() != 4) return std::nullopt;
    unsigned int value = 0;
    for (wchar_t ch : ascii_upper(text)) {
        if (ch >= L'0' && ch <= L'9') value = value * 16 + ch - L'0';
        else if (ch >= L'A' && ch <= L'F') value = value * 16 + ch - L'A' + 10;
        else return std::nullopt;
    }
    return value;
}

inline std::optional<UsbInstance> parse_usb_instance(const std::wstring& instance) {
    // A composite interface ID is not the physical USB device's serial number.
    const auto upper = ascii_upper(instance);
    if (upper.rfind(L"USB\\VID_", 0) != 0) return std::nullopt;
    const auto separator = upper.find(L'\\', 4);
    if (separator == std::wstring::npos || separator + 1 == upper.size()) return std::nullopt;
    const auto hardware = upper.substr(4, separator - 4);
    if (hardware.find(L"&MI_") != std::wstring::npos) return std::nullopt;
    const auto pid = hardware.find(L"&PID_");
    if (pid != 8 || (hardware.size() > 17 && hardware[17] != L'&')) return std::nullopt;
    auto vendor = hex_word(hardware.substr(4, 4));
    auto product = hex_word(hardware.substr(13, 4));
    if (!vendor || !product) return std::nullopt;
    const auto serial = instance.substr(separator + 1);
    if (serial.find(L'\\') != std::wstring::npos) return std::nullopt;
    return UsbInstance{*vendor, *product, serial};
}

inline bool is_mtp(const std::wstring& protocol) {
    const auto upper = ascii_upper(protocol);
    return upper == L"MTP" || upper.rfind(L"MTP:", 0) == 0 || upper.rfind(L"MTP ", 0) == 0
        || upper.find(L"MEDIA TRANSFER PROTOCOL") != std::wstring::npos;
}

}  // namespace hsx
