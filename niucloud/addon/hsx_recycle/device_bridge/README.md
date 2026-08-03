# hsx_device_bridge

`hsx_recycle` 的本地 iPhone USB 读取内核。它只负责读取和标准化硬件事实，不创建回收订单、不修改 ERP 数据，也不自行猜测站点分类。

## 本地开发

```bash
cd niucloud/addon/hsx_recycle/device_bridge
PYTHONPATH=src python3 -m hsx_device_bridge scan
PYTHONPATH=src python3 -m hsx_device_bridge read --raw
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

## 安装与首次使用

当前提供 macOS Apple 芯片版和 Windows x64 版：

```text
dist/hsx_device_bridge-0.1.0-macos-arm64.pkg
dist/installer/hsx_device_bridge-0.1.1-windows-x64-setup.exe
```

1. 双击安装包完成安装。当前测试包尚未使用 Apple Developer ID 签名；若系统拦截，请到“系统设置 → 隐私与安全性”中确认仍要打开。
2. 使用数据线连接 iPhone，解锁手机并选择“信任此电脑”。
3. 安装器会注册 `LaunchAgent`，桥接服务会自动启动，不需要再打开桌面应用。
4. 浏览器访问 `http://127.0.0.1:17890/v1/health`，返回 `code: 0` 表示服务正常。
5. 回到回收签收页面，点击“读取本地设备”；需要持续识别时可开启“自动检测”。

Windows 安装包默认安装到当前用户目录，不需要管理员权限。安装结束后会立即启动，并注册为登录自动启动。Windows 读取 iPhone 依赖苹果设备驱动；客户电脑已有爱思助手时通常可以直接复用，若健康检查提示驱动不可用，请先在爱思助手中执行驱动修复。

本地日志：

```text
/tmp/hsx_device_bridge.log
/tmp/hsx_device_bridge.error.log
Windows: %LOCALAPPDATA%\HSX Device Bridge\logs\bridge.log
```

现成测试包只允许 `localhost` 和 `127.0.0.1` 页面访问。部署正式站点前，必须使用实际站点域名重新打包：

```bash
bash scripts/package_macos.sh 0.1.0 "https://你的后台域名"
./scripts/build_windows.ps1 -Version 0.1.1 -AllowedOrigins "https://你的后台域名"
```

## 数据边界

输出契约为 `hsx.device.snapshot.v1`：

- `identity`：SN、IMEI、IMEI2、UDID、ECID
- `hardware`：ProductType、ModelNumber、HardwareModel、地区码
- `display`：设备名、颜色、容量
- `system`：iOS、Build、激活状态
- `battery`：健康度、循环、电量、温度、电压

分类匹配由 `hsx_recycle` 使用 `hardware.product_type` 等稳定键解析到本站叶子节点；没有唯一匹配时必须由用户确认，禁止默认取搜索结果第一条。

## macOS 打包

```bash
bash scripts/build_macos.sh
bash scripts/package_macos.sh 0.1.0 "https://erp.example.com"
```

安装包会把桥接器安装为 `LaunchAgent`。用户只需安装一次，登录系统后自动运行，不需要再手动打开桌面程序。正式分发前仍需使用 Apple Developer ID 对二进制和安装包签名、公证。
