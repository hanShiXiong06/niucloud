# hsx_device_bridge

`hsx_recycle` 的本地 USB 读取内核。它只负责读取和标准化硬件事实，不创建回收订单、不修改 ERP 数据，也不自行猜测站点分类。

- iPhone：沿用信任电脑后的读取。
- macOS Apple Silicon：沿用 0.3.1 的通用 MTP 发现与魅族连接校验修复，不再限制品牌或要求 USB 名称包含 Android。不代表所有品牌、型号都已实测或能读到相同字段。
- Windows：0.4.0 内测版加入原生 WPD/MTP 读取，复用 Windows 便携设备驱动；iPhone 仍走原链路。GitHub Windows 环境已通过原生编译、组件自检和打包后 HTTP 检查，真实手机及安装升级验收仍待完成。

Windows 开发与验收见 [Windows 安卓读取内测说明](docs/windows-mtp-preview.md)。0.4.0 的双平台安装包已在 [GitHub Actions](https://github.com/hanShiXiong06/niucloud/actions/runs/35631020579) 构建成功，未发布正式 Release，不能以远程构建结果替代真机验收。

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
- `GET http://127.0.0.1:17890/v1/diagnostics`
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
dist/github-v0.4.0/hsx_device_bridge-0.4.0-macos-arm64.pkg
dist/github-v0.4.0/hsx_device_bridge-0.4.0-windows-x64-setup.exe
```

1. 双击安装包完成安装。当前测试包尚未使用 Apple Developer ID 签名；若系统拦截，请到“系统设置 → 隐私与安全性”中确认仍要打开。
2. iPhone 解锁后选择“信任此电脑”；安卓解锁后选择“文件传输”，允许手机弹出的访问提示。读取不使用 ADB，也不需要开启 USB 调试。
3. Mac 安装器会注册 `LaunchAgent`，Windows 会注册登录启动，桥接服务会自动运行，不需要再打开桌面应用。
4. 浏览器访问 `http://127.0.0.1:17890/v1/health`，确认 `version: 0.4.0`。Mac 包应有 `capabilities.android_mtp: true` 和 `android_mtp_scope: generic`；Windows 应有 `android_mtp_backend: windows_wpd` 和 `android_mtp_status: preview`。`device_count` 表示发现数量，不代表质检信息全部可读。
5. 正式 HTTPS 站点首次读取时，浏览器可能询问是否允许访问本地网络，请选择允许。
6. 回到回收签收页面，点击“读取本地设备”；需要持续识别时可开启“自动检测”。

Windows 安装包默认安装到当前用户目录，不需要管理员权限。安装结束后会立即启动，并注册为登录自动启动。Windows 读取 iPhone 依赖苹果设备驱动；客户电脑已有爱思助手时通常可以直接复用，若健康检查提示驱动不可用，请先在爱思助手中执行驱动修复。

本地日志：

```text
/tmp/hsx_device_bridge.log
/tmp/hsx_device_bridge.error.log
Windows: %LOCALAPPDATA%\HSX Device Bridge\logs\bridge.log
```

打包时维护允许访问的站点域名。此次双平台包包含本站域名 `https://gl.hsxbk.top`，并允许本机开发页面。不同域名的后台需重新设置白名单：

```bash
bash scripts/package_macos.sh 0.4.0 "https://你的后台域名"
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

0.3.0 将 `/v1/scan`、`/v1/devices` 改为通用 MTP 发现与逐设备读取。读机协议封装在 `mtp_protocol.py`，独立探测工具复用同一实现。

扫描只发现 MTP 候选，不靠品牌白名单，也不把普通 USB 键盘当手机。每台候选在独立限时子进程读取，单台超时不会丢弃其他已成功的设备。设备没有声明 `android.com` 协议扩展时，`platform` 为 `mtp`，不冒充已确认的安卓手机。

接口新增 `device_id`、`warnings`、`partial`：部分成功返回 HTTP 200 和成功快照，失败设备写入 `warnings`；全部失败返回 HTTP 422；没有设备时返回空列表。前端自动检测只标记成功设备，失败设备保留重试机会。

0.3.1 使用厂商、产品、物理 USB 端口和非空 USB 序列号确认同一设备，允许读取后临时 USB 地址重新分配；无法取得稳定身份时不放宽校验。`device_id` 使用该组合的摘要，不因正常地址变化重复触发自动读机。同型号、另一物理端口或另一序列号不能替代原设备。

签收填写规则：型号使用 `SM-G9910` 等稳定硬件标识匹配叶子节点；没有匹配时沿用人工选择与型号绑定。USB 序列号按同一 USB 端口关联后填入 SN，MTP UUID 单独保存，不混作 IMEI。前端保留原始读取档案并提示核对 USB SN。USB SN 是否可用于保修查询需由查询服务另行确认。

手机充电电量不回填健康度，固件字符串不回填 Android 版本，未取得的容量、颜色、IMEI、健康度和循环次数保持空白。扫描不读取用户文件，读机在限时子进程中执行，忙碌或超时明确报错，不会自动关闭 ADB 或抢占其他软件。

0.4.0 将两端的快照标准化放在 `mtp_snapshot.py`；Mac 的连接、重试及 USB 身份确认逻辑保持不变。Windows 的 `windows_mtp_reader.py` 调用安装包内的 `native/hsx_wpd_reader.exe`，只打开明确的 WPD 设备根对象读取基础属性，不读取用户文件。原生代码只在 Windows 构建机编译，客户无需安装 Python、编译器或 ADB。

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
- 此独立诊断脚本仅针对 libmtp；Windows 改用原生 WPD 读取，虽已通过独立编译和打包自检，仍须完成真机验收，不能用 Mac 的读机结果代替。

真机结果见 [三星 S21 只读探测记录](docs/2026-09-21-samsung-s21-mtp.md)。

```bash
cd device_bridge
PYTHONPATH=src python3 -m unittest discover -s tests -v
```

## macOS 打包与版本校验

开发机需要 libmtp 和 libusb；客户端无需安装 Homebrew 或 Python。构建会把动态库及许可证打进包内。可通过环境变量指定开发机的库路径，许可证应位于该库所属安装目录的 `COPYING`。

```bash
bash scripts/build_macos.sh
bash scripts/package_macos.sh 0.4.0 "https://erp.example.com"
```

安装包会把桥接器安装为 `LaunchAgent`。用户只需安装一次，登录系统后自动运行，不需要再手动打开桌面程序。正式分发前仍需使用 Apple Developer ID 对二进制和安装包签名、公证。

版本唯一来源是 `src/hsx_device_bridge/__init__.py`。构建后和生成安装包前都执行二进制 `--version`，与源码和指定包版本不一致立即失败；不能给旧程序换一个新版本的文件名后发布。安装器更新后重新注册服务，启动失败会报错，不再静默忽略。

本机安装需系统管理员授权。生成安装包、独立程序读机成功，不等于运行中的 17890 服务已更新；最终以该端口的版本和 `/v1/devices` 实测为准。

## GitHub 打包

工作流 `Build Device Bridge Installers` 在 `feature/hsx_erp-pc-mobile-alignment` 分支的 `device_bridge/` 或工作流文件变更推送后自动构建 macOS ARM64 和 Windows x64 安装包，Artifact 保留 30 天，不自动发布 Release。工作流还保留手动运行入口；GitHub 网页是否显示该入口取决于默认分支的工作流版本。

仅推送与源码版本一致的 `device-bridge-v*` 标签时，才会在两端构建成功后发布 Release 安装包和 SHA256 校验文件。本次未推送版本标签。客户使用 SaaS 平台维护的网盘地址，不需要访问 GitHub。
