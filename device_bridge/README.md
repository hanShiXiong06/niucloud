# hsx_device_bridge

`hsx_recycle` 的本地 USB 读取内核。它只负责读取和标准化硬件事实，不创建回收订单、不修改 ERP 数据，也不自行猜测站点分类。

- iPhone：沿用信任电脑后的读取。
- macOS Apple Silicon 0.2.0：新增三星 MTP 基础读取，已用 Galaxy S21 5G 验证。不是全品牌安卓支持。
- Windows：仍为 iPhone 读取，本次没有构建或发布支持安卓的 Windows 包。

## 本地开发

```bash
cd device_bridge
PYTHONPATH=src python3 -m hsx_device_bridge scan
PYTHONPATH=src python3 -m hsx_device_bridge read --raw
PYTHONPATH=src python3 -m hsx_device_bridge devices
PYTHONPATH=src python3 -m hsx_device_bridge serve --port 17890
```

浏览器接口：

- `GET http://127.0.0.1:17890/v1/health`
- `GET http://127.0.0.1:17890/v1/scan`
- `GET http://127.0.0.1:17890/v1/devices`

默认仅允许 `localhost` 和 `127.0.0.1` 页面跨域读取。线上站点通过环境变量加入白名单：

```bash
HSX_DEVICE_BRIDGE_ORIGINS=https://erp.example.com
```

这里填写的是打开管理后台时浏览器地址栏中的来源（协议、域名及非默认端口），
不是部署桥接服务的云服务器地址。例如正式后台为 `https://gl.hsxbk.top/site/...`，
白名单必须包含 `https://gl.hsxbk.top`。桥接服务始终安装在连接手机的操作员电脑上，
网页访问的 `127.0.0.1` 也是这台操作员电脑，而不是云服务器。

## 安装与首次使用

安装包名称：

```text
dist/hsx_device_bridge-0.2.0-macos-arm64.pkg
dist/installer/hsx_device_bridge-0.2.0-windows-x64-setup.exe
```

1. 双击安装包完成安装。当前测试包尚未使用 Apple Developer ID 签名；若系统拦截，请到“系统设置 → 隐私与安全性”中确认仍要打开。
2. iPhone 解锁后选择“信任此电脑”；三星解锁后选择“文件传输”，允许手机弹出的访问提示。读取不使用 ADB。
3. 安装器会注册 `LaunchAgent`，桥接服务会自动启动，不需要再打开桌面应用。
4. 浏览器访问 `http://127.0.0.1:17890/v1/health`，确认 `version: 0.2.0`。Mac 包应有 `capabilities.android_mtp: true`；`device_count` 表示连接数量，不代表质检信息全部可读。
5. 正式 HTTPS 站点首次读取时，浏览器可能询问是否允许访问本地网络，请选择允许。
6. 回到回收签收页面，点击“读取本地设备”；需要持续识别时可开启“自动检测”。

Windows 安装包默认安装到当前用户目录，不需要管理员权限。安装结束后会立即启动，并注册为登录自动启动。Windows 读取 iPhone 依赖苹果设备驱动；客户电脑已有爱思助手时通常可以直接复用，若健康检查提示驱动不可用，请先在爱思助手中执行驱动修复。

本地日志：

```text
/tmp/hsx_device_bridge.log
/tmp/hsx_device_bridge.error.log
Windows: %LOCALAPPDATA%\HSX Device Bridge\logs\bridge.log
```

打包时维护允许访问的站点域名。此次本地 Mac 包沿用本站域名 `https://gl.hsxbk.top`，并允许本机开发页面。其他站点需重新设置白名单：

```bash
bash scripts/package_macos.sh 0.2.0 "https://你的后台域名"
./scripts/build_windows.ps1 -AllowedOrigins "https://你的后台域名"
```

## 数据边界

输出契约为 `hsx.device.snapshot.v1`：

- `identity`：SN、IMEI、IMEI2、UDID、ECID
- `hardware`：ProductType、ModelNumber、HardwareModel、地区码
- `display`：设备名、颜色、容量
- `system`：iOS、Build、激活状态
- `battery`：健康度、循环、电量、温度、电压

分类匹配由 `hsx_recycle` 使用 `hardware.product_type` 等稳定键解析到本站叶子节点；没有唯一匹配时必须由用户确认，禁止默认取搜索结果第一条。

## Android MTP 只读验证

0.2.0 已把三星 MTP 读取接入 `/v1/scan`、`/v1/devices` 和 Mac 安装包；读机协议封装在 `mtp_protocol.py`，独立探测工具复用同一实现。

签收填写规则：型号使用 `SM-G9910` 等稳定硬件标识匹配叶子节点；没有匹配时沿用人工选择与型号绑定。USB 序列号按同一 USB 端口关联后填入 SN，MTP UUID 单独保存，不混作 IMEI。前端保留原始读取档案并提示核对 USB SN。USB SN 是否可用于保修查询需由查询服务另行确认。

手机充电电量不回填健康度，固件字符串不回填 Android 版本，未取得的容量、颜色、IMEI、健康度和循环次数保持空白。扫描不读取用户文件，读机在限时子进程中执行，忙碌或超时明确报错，不会自动关闭 ADB 或抢占其他软件。

独立协议诊断：

1. 手机解锁，USB 用途选择“文件传输”，不是“USB 网络共享”；不需要开启 USB 调试。
2. 测试电脑需要 macOS/Linux 的 `libmtp` 动态库。Python 使用标准库 `ctypes` 调用它，不依赖 ADB。
3. 从项目根目录运行（`0x04e8` 仅测试三星设备；不指定则检查所有 MTP 设备）：

```bash
python3 device_bridge/scripts/probe_android_mtp.py \
  --library /absolute/path/to/libmtp.dylib \
  --vendor-id 0x04e8 --timeout 25
```

- 只读取制造商、MTP 型号码、序列号、设备友好名称、原始设备版本和当前电量。
- 加 `--extended` 可继续读取 MTP 可见存储容量、剩余空间和协议能力列表；能力列表输出到标准错误，JSON 结果输出到标准输出。
- 存储的 `exposed_capacity_bytes` / `exposed_capacity_gib` 是手机开放给 MTP 的文件系统容量，不能作为标称容量；`used_bytes` 仅由总空间减剩余空间计算，没有枚举文件。
- 不读取或枚举照片、联系人等用户文件，不写手机，不创建订单，不触发打印。
- `device_version_raw` 不等于 Android 版本；当前电量不等于电池健康度。
- `serial_number` 是 MTP 协议返回的设备标识，可能与 USB 序列号不同，未核对前不能作为工厂 SN 或 IMEI 回填。
- IMEI、颜色、标称容量、电池健康度、循环次数等未读取项明确列在 `not_read_fields`，不能据此自动填零或推算。
- `devices: []` 表示没有可读取的 MTP 设备，不代表读机成功。确认数据线、解锁状态和 USB 用途，并检查是否被其他程序占用。
- 超时会终止本次探测子进程。程序不会自动停止 ADB、抢占其他程序或修改手机 USB 模式。
- Windows 仍需独立验证原生 WPD 读取；此脚本不代表 Windows 安装包已经支持 Android。

真机结果见 [三星 S21 只读探测记录](docs/2026-09-21-samsung-s21-mtp.md)。

```bash
cd device_bridge
PYTHONPATH=src python3 -m unittest discover -s tests -v
```

## macOS 打包与版本校验

开发机需要 libmtp 和 libusb；客户端无需安装 Homebrew 或 Python。构建会把动态库及许可证打进包内。可通过环境变量指定开发机的库路径，许可证应位于该库所属安装目录的 `COPYING`。

```bash
bash scripts/build_macos.sh
bash scripts/package_macos.sh 0.2.0 "https://erp.example.com"
```

安装包会把桥接器安装为 `LaunchAgent`。用户只需安装一次，登录系统后自动运行，不需要再手动打开桌面程序。正式分发前仍需使用 Apple Developer ID 对二进制和安装包签名、公证。

版本唯一来源是 `src/hsx_device_bridge/__init__.py`。构建后和生成安装包前都执行二进制 `--version`，与源码和指定包版本不一致立即失败；不能给旧程序换一个新版本的文件名后发布。安装器更新后重新注册服务，启动失败会报错，不再静默忽略。

本机安装需系统管理员授权。生成安装包、独立程序读机成功，不等于运行中的 17890 服务已更新；最终以该端口的版本和 `/v1/devices` 实测为准。

## GitHub 打包

工作流 `Build Device Bridge Installers` 支持手动运行；推送与源码版本一致的 `device-bridge-v*` 标签时，会分别在 macOS ARM64 和 Windows x64 构建。两端构建成功后，Release 发布安装包和 SHA256 校验文件。客户使用 SaaS 平台维护的网盘地址，不需要访问 GitHub。
