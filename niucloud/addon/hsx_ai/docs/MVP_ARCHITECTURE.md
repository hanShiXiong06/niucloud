# hsx_ai MVP 技术架构

> 本文记录当前 MVP 已落地边界。后续统一会话、智能体编排、结构化需求、消息协议与安全治理，见 [AI 业务中台架构](./AI_BUSINESS_PLATFORM_ARCHITECTURE.md)。

## 目标

`hsx_ai` 为回收、ERP、会员卡和用户端提供可选的 AI 能力。业务插件保留业务事实、权限校验和最终写入权；AI 插件只负责模型接入、场景路由、结构化输出和调用审计。

卸载或停用 `hsx_ai` 后，其他业务插件必须继续运行。

## MVP 能力

- 多个 OpenAI Compatible 模型通道；
- 连接测试与 `/v1/models` 模型同步；
- 多个业务场景与场景级模型、提示词、输出格式；
- 后台完整响应与流式在线测试；
- `HsxAiExecuteRequested` 同步事件契约；
- `HsxAiCapabilityRequested` 能力发现契约；
- 站点隔离、请求幂等、敏感信息基础脱敏和调用日志；
- OpenAI Compatible 流式扩展契约，区分推理增量与最终正文。
- 商城用户端选机助手、受控商品事实和商品卡片；
- AI 工具白名单、场景/身份/权限过滤契约；
- 按站点、按业务插件显式授权的通信锁；
- 可选的短语音识别与回复朗读服务；
- 模型、业务接入、语音、在线测试、日志五个独立后台菜单及接口权限。

第一期不实现知识库、异步任务和自动写业务。商城助手只开放只读商品查询。

## 业务工具与权限

业务插件通过 `HsxAiToolRegistryRequested` 注册工具，通过 `HsxAiToolExecuteRequested` 执行工具。工具必须声明：

- 唯一 `key`、允许的 `scenes` 和来源插件；
- `auth`：`public`、`member` 或 `admin`；
- 后台工具所需的 `permissions`；
- JSON `input_schema` 和是否 `read_only`。

`AiToolService` 先按场景、用户类型和权限过滤，再校验参数，最后才派发执行事件。`site_id`、会员/管理员 ID 和权限由服务端注入，模型无权提交或覆盖。业务监听器仍须按当前请求再次校验站点和数据归属。

商城第一项工具是 `phone_shop.goods.search`。它仅返回当前站点已上架、有库存的商品和当前会员可见价。用户端首期使用同一工具的业务上下文投影，以减少一次模型工具规划调用并改善首字速度。

## 业务接入锁

业务插件通过 `HsxAiIntegrationRegistryRequested` 声明自己的名称、场景和可开放能力。声明只表示“支持接入”，不会自动授权。站点管理员必须在“AI 能力中心 → 业务接入”显式开启，AI 才允许双方通信。

锁关闭后会同时阻止：

- AI 向业务插件请求上下文；
- AI 调用该插件注册的工具；
- 业务插件通过 `HsxAiExecuteRequested` 主动调用模型；
- 商城等业务插件直接消费 AI 事件。

业务插件可通过 `HsxAiIntegrationAccessRequested` 反向检查授权。AI 未安装、全局停用、业务插件未注册或接入开关关闭时，结果都视为未授权。开关按站点保存在牛云配置中心，不新增业务表，也不影响插件自身流程。

## 用户端边界

- `GET /api/ai/assistant/capability` 是可选能力探测；插件未安装、AI 停用、商城接入锁关闭或场景停用时，商城入口静默隐藏。
- `POST /api/ai/assistant/stream` 在 H5 与微信小程序使用流式响应；App 首期自动回退完整响应。
- 问题先经商城业务域判断。与本站商品选择、价格、库存、配置或购买无关时，在调用模型前直接拒绝。
- 当前开放给访客的是公开商品查询。未来订单工具必须要求已登录会员，并只能读取当前 `member_id` 的订单。
- 每个会员或访客 IP 每分钟最多 12 次请求，避免公开页面失控消耗模型额度。

## 语音

语音能力属于 AI 插件，不属于商城。商城只调用统一 `AiSpeechService`，不感知具体服务商：

- 当前提供百度智能云和腾讯云两套 Provider，站点选择其中一套生效；
- 百度同时兼容新版 `bce-v3` API Key 直连和旧 API Key/Secret Key 换取 Access Token，腾讯使用 SecretId/SecretKey 和 TC3-HMAC-SHA256 签名；
- 腾讯短语音识别调用 `SentenceRecognition`，在线合成调用 `TextToVoice`；
- 所有密钥仅存服务端，音频由本站服务端转发；
- 后台“测试并试听”执行真实 TTS 请求并返回音频和耗时，可在保存前验证凭据、服务权限与网络；
- 微信小程序录制 16kHz 单声道音频，识别完成后写入输入框，由用户确认后发送；回复朗读由用户主动触发。
- 语音接口按当前会员或访客 IP 限制为每分钟 20 次，避免公开入口消耗第三方额度。

## 完整响应与流式响应

- 业务插件通过 `HsxAiExecuteRequested` 使用完整响应。模型结束并通过 JSON 校验后，业务层才允许消费结果。
- 后台测试和未来对话界面可使用 `POST /adminapi/ai/stream`。接口通过 SSE 依次发送 `meta`、`reasoning`、`content`、`done` 或 `error`。
- `reasoning` 仅用于交互展示，不会混入最终 `content`，也不能作为业务事实。
- 流式调用同样执行站点隔离、场景路由、脱敏、幂等和审计；`done` 中返回首字耗时、总耗时和 Token 用量。
- 浏览器使用 `fetch` 读取 SSE，以便继续携带牛云后台的 Token 和站点请求头。

## 同步调用契约

业务插件调用：

```php
$responses = (array)event('HsxAiExecuteRequested', [
    'site_id' => $siteId,
    'request_id' => 'hsx_recycle:device:100:pricing:v1',
    'scene_key' => 'hsx_recycle.device_pricing',
    'prompt' => '请根据已经提供的业务事实给出定价建议。',
    'operator' => [
        'id' => $uid,
        'name' => $username,
    ],
    'source' => [
        'plugin' => 'hsx_recycle',
        'type' => 'recycle_device',
        'id' => '100',
    ],
]);
```

成功监听结果：

```json
{
  "consumer": "hsx_ai",
  "status": "processed",
  "result": {
    "request_id": "hsx_recycle:device:100:pricing:v1",
    "scene_key": "hsx_recycle.device_pricing",
    "provider_id": "yunwu",
    "model": "configured-model",
    "content": "模型原始文本",
    "data": null,
    "finish_reason": "stop",
    "usage": {
      "prompt_tokens": 100,
      "completion_tokens": 50,
      "total_tokens": 150
    },
    "latency_ms": 1200,
    "status": "success"
  }
}
```

业务插件必须检查 `consumer=hsx_ai`、`status=processed`。未安装插件时事件结果为空，业务流程不得因此失败。

同一个 `site_id + request_id` 只会真实调用模型一次。重复请求返回 `result.status=duplicate`；当“保存问答内容”关闭时，重复结果不会再次返回模型正文，业务插件应复用自己已保存的首次结果，不能据此再次生成。

## JSON 场景

场景选择 `JSON` 输出后，插件会向兼容接口传递：

```json
{"response_format":{"type":"json_object"}}
```

并对模型返回内容执行 JSON 解析。解析失败时调用记为失败，不把不合格文本交给业务继续执行。

## 数据与安全

- API Key 通过 `CoreConfigService` 保存，后台查询仅返回 `******`。
- 调用日志默认不保存 Prompt 和模型正文，只保存哈希、长度、模型、Token、耗时和错误。
- “发送前脱敏”默认开启，覆盖手机号、身份证和常见账户号码。
- `site_id + request_id` 唯一；相同请求 ID 携带不同内容会被拒绝。
- 并发提交相同请求时，由数据库唯一索引兜底，后到请求不会重复调用模型。
- AI 不能直接读写其他插件数据表。
- 后续工具调用必须由业务插件提供事件契约，并在业务服务内再次校验站点、权限和幂等。

## 后续阶段

1. 回收设备定价建议场景。
2. 异步 AI 任务、Inbox/Outbox 和队列重试。
3. ERP 只读查询工具与财务解释。
4. 需要人工确认的业务操作工具。
5. 知识库、图片理解和管理端助手。
