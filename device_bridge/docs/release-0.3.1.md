# HSX Device Bridge 0.3.1

## Fix

Meizu 16th metadata was read successfully, but macOS reassigned its temporary USB address after MTP released the connection. The 0.3.0 post-read guard incorrectly reported a changed device and discarded the snapshot.

- Verify the physical USB location, vendor/product and nonempty USB serial instead of requiring an unchanged temporary address.
- Resolve the current address before reading; wait briefly for re-enumeration after reading, within the batch deadline.
- Keep protection against a different serial, different physical port, ambiguous matches and missing identity. Never infer identity from model name or MTP UUID.
- Use a stable, hashed `device_id` for scan/read responses so automatic detection does not continuously re-read the same phone.
- Treat empty IORegistry output as no USB devices, not malformed data.

## Checks

- 50 Python tests passed, including the reproduced address change, true device replacement, temporary disappearance, scan/read ID consistency and timeout bounds.
- Source runtime: five consecutive read-only passes on the connected Meizu 16th succeeded. Each scan/read/scan cycle took about 0.5 seconds; model, USB serial and current battery level were available, with no batch warnings and a stable device ID.
- Packaged runtime: three scan/read/scan HTTP cycles succeeded on the same phone, at 0.55-0.57 seconds per cycle. The local browser Origin was accepted and scan/read IDs stayed identical across all cycles. The temporary service was stopped after testing.
- The local Mac PKG is built: `hsx_device_bridge-0.3.1-macos-arm64.pkg`, SHA256 `ffb84c63418226714c8362cfc57fedef623493449b20b1231ade50e7e1b45867`. It has not been uploaded or installed by this task; the existing service still reports 0.3.0.
- IMEI, health, color, retail capacity and Android version remain unread; no guessed values were introduced.
- No database, framework or frontend changes are required for this patch over 0.3.0. Windows Android support is unchanged.
- Installation, running-service verification and catalog association in the user's business UI are separate acceptance steps. No business record is created by these checks.
