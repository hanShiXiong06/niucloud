# 货源入库 · `DeviceAssetPriceCompleted` 事件 payload 契约（给中台/ERP 侧）

> 用途：中台拍照定价完成时派发的全局事件 `DeviceAssetPriceCompleted`，phone_shop 商城要监听它、把"已定价·可售"的设备落成"待上架货源"，人工加工成商城商品。
> 现状：该事件已存在（hsx_recycle 在听），现 payload 只有 `device_id / erp_asset_id / sale_price`，**不够建品**。本契约请中台**扩充 payload 字段**。
> 原则：一台设备一条事件；商城按 `erp_asset_id` 幂等（重复发不重复建）。即使后续商城人工建品，原料也必须随事件过来。

## 事件结构（建议）

```php
event('DeviceAssetPriceCompleted', [
    'event_name' => 'device_asset.price.completed.v1', // 版本标识，商城按此校验
    'device_id'  => 12345,            // 回收设备ID（已有）
    'payload'    => [
        // —— 关联与幂等（必填）——
        'erp_asset_id'    => 67890,   // ERP设备资产唯一ID（关联键+幂等键，已有）
        'site_id'         => 1,       // 站点id

        // —— 三种价格（必填 sale_price，其余可空）——
        'sale_price'      => 3200.00, // 销售价（散客零售价，已有）
        'peer_price'      => 3000.00, // 同行价（B端同行价，落商城会员价-同行等级）
        'cost_price'      => 2800.00, // 成本价（落 sku.cost_price）

        // —— 设备基础信息（用于建品，能给多少给多少）——
        'model_name'      => 'iPhone 13',   // 型号
        'brand_name'      => 'Apple',       // 品牌（可空，人工可补）
        'memory'          => '128G',        // 内存
        'color'           => '午夜色',       // 颜色（可空）
        'condition_grade' => '99新',        // 成色（可空，人工可补）
        'imei'            => '35xxxxx',      // IMEI（落 sku_no，仅搜索展示）

        // —— 图片 & 质检（中台已拍摄/已质检）——
        'images'          => ['https://.../1.jpg', '...'], // 设备图片URL数组
        'qc_info'         => [ /* 质检结构化数据或摘要文本 */ ],

        // —— 时间 ——
        'occurred_at'     => 1718000000,
    ],
]);
```

## 商城侧消费方式（phone_shop，本插件负责）
1. 监听 `DeviceAssetPriceCompleted`，仅处理 `event_name == 'device_asset.price.completed.v1'`。
2. 按 `erp_asset_id` 幂等 upsert 到暂存表 `phone_shop_device_intake`（待上架货源）。
3. 后台"待上架货源"列表 → 人工映射商城三级分类、确认品牌/内存/成色、确认三种价、勾图片 → 生成 goods+sku（一台一商品，stock=1，is_unique=1，erp_asset_id 关联）上架。

## 字段优先级
- **必须**：`erp_asset_id`、`sale_price`、`site_id`、`images`（没图没法卖）。
- **强烈建议**：`model_name`、`memory`、`condition_grade`、`qc_info`、`cost_price`、`peer_price`（缺了人工要补，慢）。
- **可空**：`brand_name`、`color`、`imei`（人工补）。

## 后续（暂不做，留口）
- 改价回流：商城调价 → 发事件通知中台（`payment`/`pricing.updated`，契约后议）。
- AI 商品描述/卖点：未来接入，独立于本契约。

---

## ★ 链路现状核对（2026-06，读码确认）

**触发点**：`hsx_device_asset`（数据中台）`app/service/admin/DeviceAssetService.php::price()` 定价完成后 `event('DeviceAssetPriceCompleted', $completedEvent)`。**链路是通的**：phone_shop 已注册监听器、能收到。

**中台当前实发的 payload（不够用）**：
```
$completedEvent = [
  'event_name' => 'device_asset.price.completed.v1',
  'site_id'    => ...,   // ← 顶层！不在 payload 内
  'asset_id'   => ...,
  'device_id'  => ...,   // 顶层
  'payload' => [ 'erp_asset_id', 'sale_price', 'peer_price', 'min_price', 'remark' ],
];
```

**已修（phone_shop 侧）**：入库服务原读 `payload['site_id']` 取不到 → 改为优先取顶层 `event['site_id']`，否则 site_id=0 会导致后台列表看不到货源。

**中台需补的 payload 字段（建货源必需）**——括号是中台取数位置：
| 字段 | 中台能否直接给 | 取数位置 |
|---|---|---|
| `cost_price` 成本价 | ✅ 容易 | 已有 `$asset->recycle_final_price`（orderData 里就有，加进 payload 即可）|
| `model_name` 型号 | ✅ 容易 | `device_asset_item.model` |
| `imei` | ✅ 容易 | `device_asset_item.imei` |
| `images` 图片 | ✅ 容易 | 查 `device_asset_media`（该资产的 image 媒体）|
| `memory` 内存 | ⚠️ 需取回收/检测侧 | 经 `device_id` 关联回收设备/检测数据 |
| `color` 颜色 | ⚠️ 同上 | 同上 |
| `condition_grade` 成色 | ⚠️ 同上 | 同上 |
| `brand_name` 品牌 | ⚠️ 同上 | 同上 |
| `qc_info` 质检 | ⚠️ 同上 | 同上 |

**另需确认**：`erp_asset_id` 来自 `$asset->ext_json['erp_asset_id']`，若未写则为 0 → phone_shop 会跳过入库（幂等键缺失）。中台要保证它已落值。

**结论**：链路通，phone_shop 侧已就绪并修好 site_id；**只差中台在 `$completedEvent['payload']` 里补上述字段**（前 4 个中台手里就有、几行代码；后 5 个要从 device_id 关联的回收/检测侧带过来）。补完即全程打通。
