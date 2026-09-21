# HSX Device Bridge 0.3.0

- macOS Apple Silicon: generic MTP discovery replaces the Samsung-only USB filter. Missing IORegistry interface children no longer reject working phones.
- No ADB, USB debugging, file enumeration, phone writes or business-data writes. Metadata reads use libmtp's uncached device API.
- USB serial is matched by vendor, product, bus and USB address. It remains unverified; MTP UUIDs never substitute for factory SN or IMEI.
- Devices are read in isolated, time-bounded workers. A failure no longer discards another phone's successful result, including iPhone results.
- Additive API fields: device snapshots carry `device_id`; responses carry `warnings` and `partial`. An all-failed read returns HTTP 422; a partial read returns successful devices with HTTP 200.
- The admin device-entry component displays partial failures and retries failed phones during automatic detection. Help distinguishes Samsung-only older packages from generic MTP packages.
- Windows remains iPhone-only. No promise of all-brand/all-model compatibility or of IMEI, health, color, storage SKU and OS-version availability.

## Verification Boundary

- Prior read-only protocol probes succeeded with Samsung S21, vivo iQOO and Meizu 16th. These are individual devices, not certification for all models in those brands.
- The new package must be independently verified against connected hardware; installing it and checking the running service are separate checkpoints.
- No database schema changes or framework-file changes are required. Both the bridge and the addon frontend should be updated for partial-error visibility and installation guidance.

Local checks on 2026-09-21:

- 43 Python tests passed, including mixed-device failures, hot-swap identity protection, unknown vendors, field accuracy and HTTP origin checks.
- Frontend transport tests passed: partial failures retry, successful phones deduplicate, reconnects retry, and stopped detection does not insert delayed results.
- Addon source/release copies match; Vue components compile. Isolated browser checks passed at desktop and 390px widths, including old/new bridge capability guidance.
- The 0.3.0 Mac binary and PKG were built. Temporary HTTP service checks passed for health, scan and devices, with no device connected. This is not a successful phone read.
- The installed service still reports 0.2.0. No installation, Git push, GitHub build or release was performed for 0.3.0.
- PKG SHA256: `da1de17f1426e469318695b9301aa723e33ba7ed39cefb5ac753defb6e264cba`.

## Dependency

Uses the public [libmtp API](https://github.com/libmtp/libmtp/blob/master/src/libmtp.h.in). The Mac package includes libmtp/libusb and their existing license notices.
