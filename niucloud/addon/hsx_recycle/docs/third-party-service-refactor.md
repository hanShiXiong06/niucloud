# 第三方能力拆分与迁移方案

## 目标

`hsx_recycle` 只保留回收业务编排，不再直接理解供应商的 key、header、签名、产品编码和响应结构。快递、物流查询、查机等能力均通过强类型契约调用；插件间用事件完成供应商注册和业务结果通知。

同步请求不能完全事件化。报价、下单、取消和查机必须有明确返回值与异常契约，因此采用“接口 + 注册中心”；事件只承担热插拔注册和成功后的领域通知。

## 能力边界

1. 快递下单：报价、产品列表、下单、改约、取消/拦截、面单、账户余额。
2. 物流查询：轨迹查询、订阅回调、状态归一化。与快递下单分开，可单独使用。
3. 查机：服务目录、供应商路由、查询订单、结果归一化、计费及用户端展示。目标承载插件为 `hsx_phone_query`。
4. 地址：寄件人/收件人统一 DTO 和地址簿；地址智能解析属于独立能力。

## 快递内部结构

```text
Controller / 回收业务
        |
兼容门面 ExpressOrderService
        |
ExpressGatewayService
        |
ExpressProviderRegistry <--- HsxExpressProviderRegistry
        |
ExpressProviderInterface
        +-- YisuExpressProvider
        +-- KuaidiBirdExpressProvider（未来）
        +-- Kuaidi100ExpressProvider（未来）
        +-- JdOfficialExpressProvider（未来）
        +-- SfOfficialExpressProvider（未来）
```

每个 Provider 独占以下内容：

- 凭证字段、header、签名算法、endpoint、超时；
- 供应商产品编码与平台快递产品的转换；
- 请求参数组装；
- 原始响应到统一响应的转换；
- 回调验签和状态转换。

业务层只传统一寄件地址、收件地址、包裹、业务来源和幂等单号。

## 事件契约

- `HsxExpressProviderRegistry`：安装的插件贡献 Provider 类，不执行业务请求。
- `HsxExpressDomainEvent`：统一领域事件出口。
  - `express.shipment.created`
  - `express.shipment.cancelled`
  - `express.shipment.status_changed`
  - 后续增加 `express.shipment.fee_confirmed`、`express.shipment.failed`

事件统一包含 `event_id`、`event_name`、`schema_version`、`site_id`、`occurred_at` 和 `payload`。消费者必须用 `event_id` 幂等。

## 配置和产品

供应商配置由各 Provider 声明 schema，配置中心只保存：是否启用、当前服务商、凭证和连接参数。禁止在 Provider 源码写死密钥。

产品统一字段：

```text
provider / provider_product_code / carrier_code / service_code
product_name / billing_mode / capabilities / enabled / raw_meta
```

产品目录与站点启用配置统一存放在 `sys_config`：键为
`recycle_express_product_catalog`。实际产品规模只有几十至几百条，按服务商使用
紧凑行结构内联保存，不再人为拆成大量配置片；上一版分片数据仍可读取，保存
一次后自动迁移并清理旧片。Excel 导入任务按任务 ID 分键保存，支持队列异步
处理、重复产品跳过、进度查询和失败重试。

新安装不再创建 `yisu_product_config`；生产环境已有旧表不执行 DROP，代码停止
读写后可由运维在确认无回滚需求时自行清理。报价、下单、后台配置统一读取
`ExpressProductCatalogService`，新增服务商不再新增产品配置表。

## 日志与一致性

- 第三方 API 日志统一屏蔽密钥、token、签名，手机号和完整地址脱敏。
- 业务流水、供应商 API 成本、用户实际支付是三类数据，不混成一张账。
- `third_order_no` 是下单幂等键；重复请求先返回已有有效运单。
- 外部 HTTP 调用不放在数据库事务中。第三方成功后必须先保存本地运单，再更新回收订单。
- 当前事件直接派发；需要跨插件可靠消费时接入 ERP 已有 Outbox，不重复实现队列。

## 查机迁移

`hsx_phone_query` 已是独立插件，应作为查机主能力。3023、快查分别实现查机 Provider。回收插件保留兼容门面：安装查机插件时通过事件请求；未安装时可在过渡期回退旧实现。迁移期间不修改回收插件生产表。

查机付费链路拆成：商品/价格 -> 用户订单 -> 支付 -> 查询任务 -> 供应商调用 -> 结果 -> 退款/补偿。供应商调用成本日志和用户付款流水分别记录。

当前回收侧过渡实现已经按三层收口：

```text
应用层：后台配置 / 测试按钮 / 用户查询接口
                    |
业务层：CoreDeviceQueryService
        渠道路由、缓存、失败切换、计费、日志、结果标准化
                    |
服务商适配层：DeviceQueryProviderInterface
        +-- PathQueryProvider（3023：路径接口 + Header key）
        +-- GkdtQueryProvider（爱查：服务 ID + AppID/Secret MD5 签名）
```

业务代码只使用 `service_code`、标准查询号码和标准结果，不读取 AppID、Secret、
API Key，不判断供应商成功码。每个供应商适配器负责自己的 URL、参数名、Header、
签名、成功码和原始返回结构。

### 3023 协议边界

- 基础地址：`https://api.3023data.com`，具体服务使用可配置路径；
- API Key 默认放在 Header `key`；
- 查询字段由接口映射决定，序列号接口可使用 `sn`，IMEI 接口可使用 `imei`；
- 成功码为 `0`，`data/cost/balance` 转换为统一 Provider 返回；
- 400/401/402/403 属于请求或账户错误，不自动换渠道；410/502/503 可按映射配置重试。

### 爱查协议边界

- 地址：`https://api-srv.gkdt.com/inquiry/async`；
- 每次请求包含 `appid`、查询号码 `code`、服务 ID `key`、`style`、`time` 和 `sign`；
- 签名：过滤空值、参数 ASCII 升序、拼接查询串，再追加 `&secret=...` 后取 MD5；
- 成功码为 `200`，不按 3023 的 `0` 误判；
- 10101=苹果保修、10104=激活锁、10109=苹果 IMEI2；服务 ID 保存在可维护映射中，
  不散落在 Controller 或 Vue 页面。

### 新增供应商约束

新增 3023、爱查之外的服务商，只能新增 Provider 并注册到
`DeviceQueryProviderRegistry`。不允许在 `CoreDeviceQueryService` 增加供应商分支，
也不允许由前端拼第三方 URL。上线前至少验证：鉴权组装、`sn/imei/code` 参数、
成功/失败码、空数据、超时、日志脱敏和缓存命中。

## 分阶段实施

1. 已完成第一阶段骨架：快递契约、注册中心、亿速适配器、领域事件、幂等保护、日志脱敏；现行路由和数据库不变。无人消费且没有真实业务数据的旧安果专用控制器、服务和 SDK 已删除。
2. 第二阶段：配置中心改为 Provider 元数据驱动，清理两套配置和两套字典，但保留旧配置读取迁移器。
3. 第三阶段：接入第二个测试 Provider，验证无需修改业务服务即可切换。
4. 已完成物流查询拆分：`ExpressQueryProviderInterface` + Registry + Gateway，用户端和后台均不再直连 curl。
5. 已完成地址解析拆分：独立 Provider/Registry/Gateway，本地地区匹配仍留在业务服务。
6. 已完成回收插件内查机 Provider 的事件注册化：移除核心服务中的 provider switch；后续 `hsx_phone_query` 可通过同一注册事件接管。
7. 第七阶段：查机主能力迁移到 `hsx_phone_query`，回收侧切换为兼容事件桥。
8. 第八阶段：用户端付费、ERP 财务留痕、供应商成本和退款闭环。

## 明确不做

- 不修改牛云核心框架。
- 第一阶段不修改生产回收插件 SQL。
- 不同时重写回收、快递、查机三个链路。
- 不在未配置的情况下自动切换供应商，避免账单和服务质量不可控。
