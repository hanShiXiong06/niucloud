# 易速快递能力设计

## 目标

把易速快递从零散接口调用收拢成回收插件内的独立快递能力，后台可配置、可诊断、可直接操作，移动端后续只调用回收插件自己的快递接口，不直接感知易速。

## 配置

配置存储在 `sys_config`：

| 配置项 | key | 说明 |
| --- | --- | --- |
| 第三方能力配置 | `recycle_third_party_config` | `express_order.yisu` 保存易速基础配置和接口路径 |

`express_order.yisu` 当前字段：

| 字段 | 说明 |
| --- | --- |
| `base_url` | 易速接口域名 |
| `appid` | 易速 AppID |
| `app_secret` | 易速签名密钥 |
| `version` | 接口版本，默认 `V1.0` |
| `timeout` | 请求超时时间 |
| `api_paths.quote` | 预估运费 `/openApi/getPrice` |
| `api_paths.create` | 运单下单 `/openApi/doOrder` |
| `api_paths.cancel` | 取消/拦截 `/openApi/doCancel` |
| `api_paths.modify` | 运单修改 `/openApi/doModify` |
| `api_paths.detail` | 运单详情 `/openApi/getOrderDetail` |
| `api_paths.waybillPdf` | 面单 PDF `/openApi/getWaybillPdf` |
| `api_paths.fund` | 资金信息 `/openApi/fund` |

## 接口映射

| 后台动作 | 易速接口 | 后端方法 | 管理端路由 |
| --- | --- | --- | --- |
| 预估运费 | `POST /openApi/getPrice` | `ExpressOrderService::getQuote` | `POST recycle/express_order/quote` |
| 运单下单 | `POST /openApi/doOrder` | `ExpressOrderService::createOrder` | `POST recycle/express_order/create` |
| 取消/拦截 | `POST /openApi/doCancel` | `ExpressOrderService::cancelOrInterceptOrder` | `POST recycle/express_order/cancel` |
| 运单修改 | `POST /openApi/doModify` | `ExpressOrderService::modifyOrder` | `POST recycle/express_order/modify` |
| 运单详情 | `POST /openApi/getOrderDetail` | `ExpressOrderService::getOrderDetail` | `GET recycle/express_order/detail` |
| 面单 PDF | `POST /openApi/getWaybillPdf` | `ExpressOrderService::getWaybillPdf` | `POST recycle/express_order/waybill_pdf` |
| 资金信息 | `POST /openApi/fund` | `ExpressOrderService::getFund` | `GET recycle/express_order/balance` |
| 推送回调 | 商户回调地址 | `ExpressController::yisuPush` | `POST recycle/express/yisu_push` |

## 后端分层

| 层级 | 文件 | 责任 |
| --- | --- | --- |
| Provider | `app/service/core/third_party/provider/express_order/ProviderYisu.php` | 只处理易速签名、路径映射、参数转换、响应标准化 |
| Core service | `app/service/core/ExpressOrderService.php` | 提供统一快递操作方法，并写入快递记录 |
| Order bridge | `app/service/core/express/RecycleExpressService.php` | 把回收订单和快递订单绑定 |
| Admin controller | `app/adminapi/controller/express/ExpressOrder.php` | 管理端操作入口 |
| API callback | `app/api/controller/express/ExpressController.php` | 移动端查询和易速推送回调 |
| UX | `admin/src/addon/recycle/views/third_party/config.vue` | 服务能力中心，配置、统计、调试 |

## 回调处理

易速推送地址：

```text
POST /api/recycle/express/yisu_push?site_id={site_id}
```

处理规则：

| `pushType` | 含义 | 当前处理 |
| --- | --- | --- |
| `1` | 状态推送 | 更新 `express_order_record.order_status`，同步回收订单快递状态 |
| `2` | 计费推送 | 更新实际费用、实际重量、费用差异 |
| `3` | 快递员推送 | 保存到 `api_response.last_push` |
| `4` | 运单变更 | 更新新运单号 |

接口会尽快返回 `SUCCESS`，处理失败只写日志，不让易速长时间等待。

## 测试重点

| 模块 | 测试点 |
| --- | --- |
| 配置保存 | AppSecret 是否脱敏保存；接口路径修改后是否生效；恢复默认是否只重置表单 |
| 资金查询 | 调用后是否进入 API 日志；服务能力中心今日统计是否增加 |
| 运单详情 | 使用平台订单号、运单号、商户订单号三种方式是否都可查询 |
| 取消/拦截 | `genre=1` 取消、`genre=3` 拦截是否按易速限制返回明确错误 |
| 运单修改 | 只改包裹数、只改预约时间、同时修改三种场景 |
| 面单 PDF | URL 类型是否能打开；Base64 类型是否能在结果中看到 |
| 回收订单下单 | 回收订单发快递后是否写入 `express_no`、`delivery_order_id`、`delivery_data` |
| 推送回调 | 状态、计费、新运单号推送是否更新快递记录和回收订单 |
| 异常体验 | 配置缺失、签名错误、接口超时、易速业务错误是否能在页面和 API 日志里看到 |

