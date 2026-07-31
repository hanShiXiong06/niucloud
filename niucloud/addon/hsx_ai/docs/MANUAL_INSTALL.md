# hsx_ai 手动安装说明

## 插件信息

- 插件标识：`hsx_ai`
- 当前版本：`0.3.0`
- 管理接口前缀：`/adminapi/ai`
- 用户接口前缀：`/api/ai/assistant`

## 数据库

新安装执行：

```text
niucloud/addon/hsx_ai/sql/install.sql
```

当前版本仅新增：

```text
{{prefix}}ai_call_log
```

该表记录站点、请求 ID、业务场景、来源插件、操作人、模型通道、模型、Token、耗时和错误。API Key 不写入该表。

卸载 SQL：

```text
niucloud/addon/hsx_ai/sql/uninstall.sql
```

## 默认模型通道

首次打开配置页会生成未启用的云雾 API 模板：

```text
Provider ID: yunwu
Driver: openai_compatible
Base URL: https://yunwu.ai
Models: /v1/models
Chat: /v1/chat/completions
```

进入“AI 能力中心 → 模型与场景”填写 API Key，依次执行：

1. 测试连接；
2. 同步模型；
3. 选择通道默认模型；
4. 启用 AI；
5. 保存；
6. 进入独立的“在线测试”菜单发送消息。

启用商城助手需要同时满足：

1. AI 总开关已启用；
2. “业务接入”中开启“二手机商城”；
3. “业务场景”中 `phone_shop.customer_assistant` 已启用。

业务接入默认关闭。关闭后服务端不再向商城派发查询或工具事件，商城悬浮入口也会自动隐藏，但商城自身不受影响。该开关保存在牛云站点配置中，不需要执行 SQL。

插件安装器会通过 `package/uni-app-pages.php` 自动注册用户端页面 `addon/hsx_ai/pages/chat/index`；项目手动维护页面清单时，还需把该页加入 `uni-app/src/pages.json` 的 `addon/hsx_ai` 分包。

语音功能在独立的“语音服务”菜单中启用，目前支持二选一：

- 百度智能云：新版 `bce-v3/...` API Key 直接鉴权，不需要 Secret Key；旧语音应用 API Key 则同时填写 Secret Key，由系统换取 Access Token；
- 腾讯云：填写访问管理中的 SecretId、SecretKey，选择地域、识别引擎和发音人。

两种服务商都可以分别开关语音转文字和回复朗读。页面“测试并试听”会真实请求当前服务商的语音合成接口，成功后直接播放测试音频，同时显示请求耗时。测试不要求先保存，但需要云账号已开通对应语音服务、密钥有权限且部署服务器能访问服务商 443 端口。不开启语音不会影响文字助手。

在线测试默认使用流式响应，页面会分别显示首字耗时和总耗时。`deepseek-r1` 等推理模型在正文前会先生成推理内容，总耗时通常明显高于普通对话模型；流式输出改善等待体验，但不会缩短模型自身推理时间。

## 网络故障排查

通道默认使用 IPv4、HTTP/1.1、30 秒连接超时，兼容部分服务器 IPv6 或 HTTP/2 握手不稳定的问题。首次连接在请求尚未发出时失败，会自动切换 IP/HTTP 协议安全重试一次；TLS 已完成后不会自动重试，避免对话重复计费。出现 `SSL connection timeout` 时：

1. 保持 IP 协议为“IPv4”、HTTP 协议为“HTTP/1.1”；
2. 将连接超时提高到 60 秒后重新测试；
3. 在实际部署服务器执行 `curl -v https://yunwu.ai/v1/models`；
4. 若仍无法到达 HTTP 401，检查服务器 443 出口、防火墙、代理和服务商跨境网络策略。

插件错误信息会附带 `curl` 错误码、目标 IP、DNS、TCP、TLS 和总耗时。到达 HTTP 401 说明网络正常，只需继续核对 API Key。

## 手动部署检查

- 后端目录：`niucloud/addon/hsx_ai`
- 插件内 PC 源码：`niucloud/addon/hsx_ai/admin`
- 插件内用户端源码：`niucloud/addon/hsx_ai/uni-app`
- 当前项目 PC 源码：`admin/src/addon/hsx_ai`
- 插件事件：`niucloud/addon/hsx_ai/app/event.php`
- 菜单：`niucloud/addon/hsx_ai/app/dict/menu/site.php`
- 路由：`niucloud/addon/hsx_ai/app/adminapi/route/route.php`
- 用户路由：`niucloud/addon/hsx_ai/app/api/route/route.php`

安装菜单后，“AI 能力中心”下有五个独立权限页面：

1. 模型与场景；
2. 业务接入；
3. 语音服务；
4. 在线测试；
5. 调用日志。

页面不使用页内 Tab。每个页面的读取、保存或执行接口均在 `site.php` 中独立声明，角色可以只获得语音配置、只看日志或只做在线测试。

已安装旧菜单数据的环境，需要重新同步插件菜单并重新勾选角色权限。反向代理应允许流式响应，并保留后端返回的 `X-Accel-Buffering: no`；若代理强制缓存，页面仍会在结束时一次性显示。

插件目录中的 `admin` 与当前项目的 `admin/src/addon/hsx_ai` 保持一致。手动部署时若框架未自动复制插件前端资源，需要将插件内 `admin` 目录同步到项目的 `admin/src/addon/hsx_ai`。

## 开发验证

基础与配置契约：

```bash
php niucloud/addon/hsx_ai/tests/foundation_smoke.php
php niucloud/addon/hsx_ai/tests/config_contract_smoke.php
php niucloud/addon/hsx_ai/tests/mall_assistant_contract_smoke.php
php niucloud/addon/hsx_ai/tests/speech_provider_contract_smoke.php
```

OpenAI Compatible Provider 本地契约：

```bash
php -S 127.0.0.1:18991 niucloud/addon/hsx_ai/tests/mock_provider_router.php
php niucloud/addon/hsx_ai/tests/provider_contract_smoke.php http://127.0.0.1:18991
```
