# Windows 安卓读取内测

## 当前状态

- 源码版本：0.4.0，Windows 安卓读取标记 `preview`。
- 已完成：Python 适配、标准快照、多设备隔离、超时与身份校验、Windows 原生 WPD 读取代码、打包检查脚本。
- 已在 Mac 验证：Python 自动化测试、可移植 C++ 身份解析与 JSON 编码测试。测试中的 Windows 设备返回值是模拟数据，不是真机读取结果。
- 未完成：Windows SDK 原生程序编译、打包后运行、真实手机及安装升级验收。本次未推送代码、未触发 GitHub Actions、未发布 EXE。
- 没有数据库变更，没有框架变更，不修改运行中的 0.3.1 Mac 服务。

## 用户操作

发布并验收后，客户只需安装新版设备桥一次。使用时：

1. 用可传数据的数据线连接手机，解锁手机。
2. 在 USB 用途中选择“文件传输”，允许手机弹出的访问提示，不用开启开发者模式或 USB 调试。
3. 在签收页点击“读取本地设备”。没有自动匹配的型号，手动关联现有型号；关联由回收业务处理，桥不创建分类。

Windows 使用系统 WPD/MTP 驱动，不使用 libusb，不替换客户现有驱动。标准 Windows 通常自带；系统精简版、Windows N 版或损坏的驱动可能需要补齐。不能承诺所有品牌和系统版本都可直接读取。

## 能读到什么

| 字段 | 处理规则 |
| --- | --- |
| 制造商、型号 | 读取 WPD 根设备属性，不把客户自定义设备名当型号 |
| 设备名称 | 保留手机返回的友好名称，用于辅助核对 |
| USB SN | 沿同一个 WPD 设备的 PnP 父链定位物理 USB 节点，仅在 `CM_DEVCAP_UNIQUEID` 为真时采用 USB 实例序列；仍需人工核对 |
| MTP 序列号 | 单独保留为 `mtp_serial_number`，不能兜底填入 SN 或 IMEI |
| 当前电量 | 仅接收 0 到 100，缺失不填零，不作为健康度 |
| 固件字符串 | 放在 `firmware_raw`，不当作 Android 系统版本 |
| IMEI、颜色、标称容量、健康度、循环次数 | 当前未读取，保持空白 |

Windows WPD 不提供本桥所需的 Android 扩展声明，快照 `platform` 为 `mtp`，不冒充已经验证了 Android 类型。签收端通过 `source: usb_mtp` 使用现有映射。

## 读取安全

- 原生程序用 `GENERIC_READ` 打开指定设备，只调用根 `DEVICE` 的 `GetValues`；不枚举照片、文件或联系人，不写手机。
- 型号相同不等于同一台设备。以完整 PnP ID、物理 USB 实例和连接时间标记绑定一次读取，前后验证；不能确认身份则明确失败。
- Windows 生成的无序列号位置标识不作为 SN，不能用 WPD/MTP UUID 替代。
- 有唯一 USB SN 时设备 ID 跨重新连接保持稳定；没有 USB SN 时把连接标记加入临时 ID，避免同一端口换了一台手机后自动检测错误地跳过。
- 扫描上限 3 秒，单台读取上限 8 秒，安卓批次总预算 20 秒。子进程超时会被终止，已读成功的其他设备保留，失败设备可重试。
- 本地服务仍只绑定回环地址并校验站点 Origin，不添加远程读机入口。
- HTTP `/v1/diagnostics` 的 `android_driver` 与苹果 `driver` 分开。苹果驱动未安装不代表安卓通道失败。
- `capabilities.android_mtp: true` 只表示对应适配组件在包中，不保证当前手机已经获准读取。

## 打包方法

开发机需要 Windows x64、Visual Studio 2022 C++ 工具及 Windows SDK、Python 和 Inno Setup 6；GitHub Windows 构建环境需具备同样能力。客户机器不需要这些开发工具。

```powershell
cd device_bridge
python -m pip install . pyinstaller
./scripts/build_windows.ps1 -AllowedOrigins "https://你的后台域名"
```

流程依次执行：

1. 从唯一源码读取版本。
2. 用 MSVC 编译 WPD 辅助程序，静态链接 C++ 运行库；运行原生逻辑测试、COM 自检和扫描检查。
3. 用 PyInstaller 将原生程序打入 `native/`，不依赖客户工作目录或 PATH。
4. 对打包后的 EXE 做文件式版本校验、依赖自检、临时端口 HTTP 启动和 WPD 调用检查。
5. 通过后才由 Inno Setup 输出 `dist/installer/hsx_device_bridge-0.4.0-windows-x64-setup.exe`。

`verify_windows_package.py` 不依赖控制台输出，避免无窗口 EXE 校验不到版本。任何检查失败则停止，不生成一个看起来成功的安装包。

后续经用户允许，可先手动运行现有 `Build Device Bridge Installers` 工作流下载 Artifact 做内测，暂不推版本标签或自动对客户发布。远程编译通过不等于手机兼容性验收通过。

## 首轮实机验收

| 场景 | 预期 |
| --- | --- |
| 旧版覆盖安装 | 原服务被替换，健康接口显示 0.4.0、`windows_wpd` 与 `preview` |
| 重启/重新登录 | 桥自动运行，客户无需双击启动 |
| 没有连接手机 | 空列表，无虚假读取成功 |
| 三星 S21、iQOO、魅族分别连接 | 型号核对正确，USB SN 与实机人工核对，未提供字段保持空白 |
| 锁屏/仅充电/未允许访问 | 清楚提示允许文件传输或未发现设备，不猜型号 |
| 同型号两台手机 | 各自型号与 SN 不串台 |
| 读取时拔出/换机 | 拒绝混合旧型号和新 SN，重新读取可恢复 |
| 一台读取超时，另一台成功 | 保留成功结果，失败项可重试 |
| iPhone 与安卓混插 | 两条链路独立，苹果仍按信任电脑后的原流程读取 |
| 浏览器型号绑定 | 选择已有型号时不创建分类；再次连接沿用业务别名映射 |

## 官方接口依据

- [WPD 驱动概览](https://learn.microsoft.com/en-us/windows-hardware/drivers/portable/wpd-drivers-overview)
- [WPD 设备属性](https://learn.microsoft.com/en-us/windows/win32/wpd_sdk/device-properties)
- [以只读权限打开设备](https://learn.microsoft.com/en-us/windows/win32/api/portabledeviceapi/nf-portabledeviceapi-iportabledevice-open)
- [枚举与释放设备 ID](https://learn.microsoft.com/en-us/windows/win32/api/portabledeviceapi/nf-portabledeviceapi-iportabledevicemanager-getdevices)
