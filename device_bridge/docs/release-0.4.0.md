# HSX Device Bridge 0.4.0

Windows 安卓读取内测版本。2026-09-22 已完成 GitHub 双平台构建，产出 Windows x64 EXE 和 Mac ARM64 PKG，未发布正式 Release。

构建记录：[GitHub Actions #8](https://github.com/hanShiXiong06/niucloud/actions/runs/35631020579)，源码提交 `9400642f92c77acad78cc7ae6e9aad4ee628ae6d`。

- Windows 增加原生 WPD/MTP 只读适配，手机解锁并选择文件传输后读取基础设备属性，不用开启 USB 调试。
- 保留现有 iPhone 和 Mac 通道，两端 MTP 共用标准快照格式。
- 多台设备分别读取，超时不丢弃其他成功结果；读取前后校验 USB 身份，避免串机。
- USB SN 与 MTP 标识分开；不虚构 IMEI、颜色、容量或电池健康度。
- 安装包增加 WPD 组件自检、版本一致性和打包后 HTTP 冒烟检查。

双平台各 81 项 Python 测试通过。Windows 的原生编译、WPD 自检和打包后 HTTP 冒烟检查通过；Mac 包内二进制版本核对为 0.4.0。

Windows 实机兼容性仍需验证，不承诺全品牌通用。建议先对少量设备内测，通过后再向客户推广。安装包可上传网盘，客户无需访问 GitHub。两端包均仅包含 `https://gl.hsxbk.top` 及本机开发来源白名单，其他后台域名需要另行配置。

详细边界及验收表见 [Windows 安卓读取内测说明](windows-mtp-preview.md)。
