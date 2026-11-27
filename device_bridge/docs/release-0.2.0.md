# HSX Device Bridge 0.2.0

- macOS Apple Silicon: iPhone + Samsung MTP basic device information (Galaxy S21 5G tested locally).
- Windows x64: iPhone only. Android/WPD support is not included in this release.
- Fixed package/runtime version mismatch. All installers verify the executable version before packaging.
- Mac Samsung reading requires an unlocked phone in USB file-transfer mode. No ADB calls are used.
- Samsung USB serial may populate SN after operator confirmation; IMEI, health, color, SKU capacity and Android version are not guessed.
- Installation starts the bridge automatically. Verify `http://127.0.0.1:17890/v1/health` after upgrade.
- This release is not Apple-notarized or Windows-code-signed. Install only after verifying the source and SHA256 checksum.
- Allowed production origin: `https://gl.hsxbk.top`; local development origins are also allowed. Other domains must be explicitly configured, not wildcarded.

Download `.pkg` for an Apple-chip Mac, or `-windows-x64-setup.exe` for Windows.
CI package success does not replace testing with a physical phone on the target computer.
