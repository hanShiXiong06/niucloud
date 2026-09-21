#include "wpd_contract.h"
#include <iostream>
#include <stdexcept>

void check(bool result) {
    if (!result) throw std::runtime_error("WPD contract assertion failed");
}

int main() {
    for (const auto* id : {L"USB\\VID_04E8&PID_6860\\S21-SN", L"USB\\VID_2D95&PID_6013\\IQOO-SN",
                           L"USB\\VID_2A45&PID_2008\\MEIZU-SN", L"usb\\vid_f123&pid_0001\\Other"}) {
        auto usb = hsx::parse_usb_instance(id);
        check(usb.has_value() && !usb->serial.empty());
    }
    check(hsx::parse_usb_instance(L"USB\\VID_04E8&PID_6860\\S21-SN")->vendor == 0x04E8);
    check(hsx::parse_usb_instance(L"USB\\VID_04E8&PID_6860&REV_0400\\S21-SN")->product == 0x6860);
    for (const auto* id : {L"USB\\VID_04E8&PID_6860&MI_00\\INTERFACE", L"SWD\\WPDBUSENUM\\UUID",
                           L"USB\\VID_XXXX&PID_6860\\SERIAL", L"USB\\VID_04E8&PID_6860\\",
                           L"USB\\VID_04E8&PID_68600\\SERIAL", L"USB\\VID_04E8&PID_6860\\A\\B",
                           L"USB\\VID_04E8&PID_6\\A", L""}) {
        check(!hsx::parse_usb_instance(id));
    }
    check(hsx::is_mtp(L"MTP") && hsx::is_mtp(L"MTP: 1.0") && hsx::is_mtp(L"MTP 1.0"));
    check(hsx::is_mtp(L"Media Transfer Protocol") && !hsx::is_mtp(L"PTP") && !hsx::is_mtp(L""));
    check(hsx::json_string("a\"b\\c\n\t\r") == "\"a\\\"b\\\\c\\u000a\\u0009\\u000d\"");
    check(hsx::json_string(std::string("a\0b", 3)) == "\"a\\u0000b\"");
    check(hsx::json_string("\xe6\x89\x8b\xe6\x9c\xba") == "\"\xe6\x89\x8b\xe6\x9c\xba\"");
    std::cout << "WPD contract tests passed" << std::endl;
}
