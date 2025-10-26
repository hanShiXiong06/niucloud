# 校园跑腿商品配置说明

## 配置方式

在商品管理后台，为跑腿商品添加 `errand_config` 字段（可以存储在 `goods_content` 或新增字段中）。

## 配置格式（JSON）

```json
{
  "routes": [
    {
      "id": 1,
      "name": "东门快递站 → 1号宿舍楼",
      "start": "东门快递站",
      "end": "1号宿舍楼",
      "prices": {
        "small": {
          "name": "小件",
          "price": 2.00,
          "desc": "长宽高30cm以内，重量5kg以内"
        },
        "medium": {
          "name": "中件",
          "price": 3.00,
          "desc": "长宽高50cm以内，重量10kg以内"
        },
        "large": {
          "name": "大件",
          "price": 5.00,
          "desc": "长宽高80cm以内，重量20kg以内"
        }
      }
    },
    {
      "id": 2,
      "name": "东门快递站 → 2号宿舍楼",
      "start": "东门快递站",
      "end": "2号宿舍楼",
      "prices": {
        "small": {
          "name": "小件",
          "price": 2.00,
          "desc": "长宽高30cm以内，重量5kg以内"
        },
        "medium": {
          "name": "中件",
          "price": 3.00,
          "desc": "长宽高50cm以内，重量10kg以内"
        },
        "large": {
          "name": "大件",
          "price": 5.00,
          "desc": "长宽高80cm以内，重量20kg以内"
        }
      }
    },
    {
      "id": 3,
      "name": "西门快递站 → 1号宿舍楼",
      "start": "西门快递站",
      "end": "1号宿舍楼",
      "prices": {
        "small": {
          "name": "小件",
          "price": 3.00,
          "desc": "长宽高30cm以内，重量5kg以内"
        },
        "medium": {
          "name": "中件",
          "price": 4.00,
          "desc": "长宽高50cm以内，重量10kg以内"
        },
        "large": {
          "name": "大件",
          "price": 6.00,
          "desc": "长宽高80cm以内，重量20kg以内"
        }
      }
    }
  ]
}
```

## 配置说明

### 路线对象 (Route)

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| `id` | number | 路线唯一ID | `1` |
| `name` | string | 路线名称 | `"东门快递站 → 1号宿舍楼"` |
| `start` | string | 起点名称 | `"东门快递站"` |
| `end` | string | 终点名称 | `"1号宿舍楼"` |
| `prices` | object | 价格配置对象 | 见下方 |

### 价格对象 (Price)

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| `name` | string | 包裹大小名称 | `"小件"` |
| `price` | number | 价格（元） | `2.00` |
| `desc` | string | 描述说明 | `"长宽高30cm以内，重量5kg以内"` |

### 包裹大小类型 (Size Keys)

- `small`: 小件
- `medium`: 中件
- `large`: 大件

可根据实际需求扩展更多类型。

## 在后台商品管理中配置

### 方法 1: 直接在数据库添加字段

```sql
-- 为商品表添加跑腿配置字段
ALTER TABLE `ns_home_service_goods` 
ADD COLUMN `errand_config` TEXT NULL COMMENT '跑腿配置（JSON格式）' AFTER `goods_content`;
```

### 方法 2: 存储在商品详情中

将上述 JSON 配置存储在商品的 `goods_content` 字段中，使用特殊标识：

```html
<!-- 在商品详情富文本编辑器中添加 -->
<div style="display: none;" data-errand-config='{"routes":[...]}'></div>
```

## 订单数据存储格式

跑腿订单的详细信息会以 JSON 格式存储在 `member_message` 字段中：

```json
{
  "type": "errand",
  "contact_name": "张三",
  "contact_mobile": "13800138000",
  "items": [
    {
      "route_id": 1,
      "route_name": "东门快递站 → 1号宿舍楼",
      "package_size": "小件",
      "pickup_code": "SF123456789",
      "price": 2.00
    },
    {
      "route_id": 2,
      "route_name": "东门快递站 → 2号宿舍楼",
      "package_size": "中件",
      "pickup_code": "YTO987654321",
      "price": 3.00
    }
  ],
  "total_price": 5.00
}
```

## 前端展示说明

- 用户进入跑腿商品详情页后，会自动识别 `errand_config` 字段
- 如果存在配置，则显示跑腿订单表单
- 否则，显示原有的服务预约表单

## 骑手端展示

骑手端需要解析 `member_message` 字段，格式化展示：

```
📦 包裹1：东门快递站 → 1号宿舍楼 | 小件 | ¥2.00
   取件码：SF123456789

📦 包裹2：东门快递站 → 2号宿舍楼 | 中件 | ¥3.00
   取件码：YTO987654321

收件人：张三
联系电话：13800138000
总计：¥5.00
```

## 后续优化建议

1. **后台可视化配置界面**：提供图形化界面，管理员可以轻松添加/编辑路线和价格
2. **路线智能推荐**：根据历史订单数据，推荐热门路线
3. **动态定价**：根据时间段、距离等因素动态调整价格
4. **批量导入**：支持Excel批量导入路线配置

