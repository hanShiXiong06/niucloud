# 打印机管理 API 接口文档

## 基础路径
```
/recycle/printer
```

## 接口列表

### 1. 打印机品牌管理

#### 1.1 获取打印机品牌列表
- **接口**: `GET /recycle/printer/brand_list`
- **说明**: 获取支持的打印机品牌列表
- **参数**: 无
- **返回**: 
```json
{
  "code": 0,
  "msg": "success",
  "data": [
    {
      "brand": "xpyun",
      "name": "芯烨云打印机",
      "desc": "支持小票打印和标签打印",
      "logo": "https://www.xpyun.net/img/logo.png",
      "support": ["ticket", "label"]
    }
  ]
}
```

---

### 2. 打印机管理（CRUD）

#### 2.1 获取打印机列表
- **接口**: `GET /recycle/printer/lists`
- **说明**: 获取当前用户的打印机列表（支持分页）
- **参数**:
  - `page` (int, 可选): 页码，默认1
  - `limit` (int, 可选): 每页数量，默认10
- **返回**:
```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "list": [...],
    "count": 10,
    "page": 1,
    "limit": 10
  }
}
```

#### 2.2 获取打印机详情
- **接口**: `GET /recycle/printer/:id`
- **说明**: 根据ID获取打印机详情
- **参数**: 
  - `id` (int, 路径参数): 打印机ID
- **返回**:
```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "printer_id": 1,
    "printer_name": "办公室打印机",
    "brand": "xpyun",
    "type": "label",
    "status": 1,
    "sn": "xxx",
    "user_name": "xxx",
    "create_time": 1234567890,
    "update_time": 1234567890
  }
}
```

#### 2.3 添加打印机
- **接口**: `POST /recycle/printer`
- **说明**: 添加新打印机（会自动注册到芯烨云）
- **参数**:
  - `printer_name` (string, 必填): 打印机名称
  - `sn` (string, 必填): 打印机序列号
  - `user_name` (string, 必填): 芯烨云用户名
  - `user_key` (string, 必填): 芯烨云密钥
  - `brand` (string, 可选): 品牌，默认"xpyun"
  - `type` (string, 可选): 类型，默认"label"（标签打印机）
- **返回**:
```json
{
  "code": 0,
  "msg": "添加成功",
  "data": true
}
```

#### 2.4 更新打印机信息
- **接口**: `PUT /recycle/printer/:id`
- **说明**: 更新打印机信息（会同步更新芯烨云）
- **参数**:
  - `id` (int, 路径参数): 打印机ID
  - `printer_name` (string, 必填): 打印机名称
  - `sn` (string, 必填): 打印机序列号
  - `user_name` (string, 必填): 芯烨云用户名
  - `user_key` (string, 必填): 芯烨云密钥
  - `brand` (string, 可选): 品牌
  - `type` (string, 可选): 类型
- **返回**:
```json
{
  "code": 0,
  "msg": "更新成功"
}
```

#### 2.5 删除打印机
- **接口**: `DELETE /recycle/printer/:id`
- **说明**: 删除打印机（会同步从芯烨云删除）
- **参数**:
  - `id` (int, 路径参数): 打印机ID
- **返回**:
```json
{
  "code": 0,
  "msg": "删除成功"
}
```

#### 2.6 切换打印机状态
- **接口**: `POST /recycle/printer/user/toggle/:id`
- **说明**: 切换打印机激活/停用状态
- **参数**:
  - `id` (int, 路径参数): 打印机ID
  - `status` (int, Body参数): 状态值，0-停用，1-激活
- **返回**:
```json
{
  "code": 0,
  "msg": "打印机已激活"
}
```

#### 2.7 查询打印机状态
- **接口**: `GET /recycle/printer/status/:id`
- **说明**: 查询打印机的在线状态（从芯烨云查询）
- **参数**:
  - `id` (int, 路径参数): 打印机ID
- **返回**:
```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "success": true,
    "status": 1,
    "status_text": "在线正常",
    "message": "查询成功"
  }
}
```
- **状态值说明**:
  - `0`: 离线
  - `1`: 在线正常
  - `2`: 在线不正常（可能缺纸）

---

### 3. 用户打印机绑定

#### 3.1 获取当前用户绑定的打印机
- **接口**: `GET /recycle/printer/user`
- **说明**: 获取当前用户绑定的打印机（激活状态的）
- **参数**: 无
- **返回**:
```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "printer_id": 1,
    "printer_name": "办公室打印机",
    ...
  }
}
```

#### 3.2 绑定打印机
- **接口**: `POST /recycle/printer/bind`
- **说明**: 绑定打印机到当前用户
- **参数**:
  - `printer_name` (string, 必填): 打印机名称
  - `sn` (string, 必填): 打印机序列号
  - `user_name` (string, 必填): 芯烨云用户名
  - `user_key` (string, 必填): 芯烨云密钥
  - `brand` (string, 可选): 品牌，默认"xpyun"
- **返回**:
```json
{
  "code": 0,
  "msg": "绑定成功",
  "data": {
    "printer_id": 1
  }
}
```

#### 3.3 解绑打印机
- **接口**: `POST /recycle/printer/unbind`
- **说明**: 解绑当前用户的打印机
- **参数**: 无
- **返回**:
```json
{
  "code": 0,
  "msg": "解绑成功"
}
```

---

### 4. 打印功能

#### 4.1 测试打印机
- **接口**: `POST /recycle/printer/test`
- **说明**: 测试打印机连接和打印功能
- **参数**:
  - `sn` (string, 必填): 打印机序列号
  - `user_name` (string, 必填): 芯烨云用户名
  - `user_key` (string, 必填): 芯烨云密钥
  - `content` (string, 可选): 测试打印内容
- **返回**:
```json
{
  "code": 0,
  "msg": "测试打印成功",
  "data": {
    "code": 0,
    "message": "测试打印成功"
  }
}
```

#### 4.2 打印标签
- **接口**: `POST /recycle/printer/print_label`
- **说明**: 打印设备标签（使用默认模板）
- **参数**:
  - `order_id` (string, 可选): 订单号
  - `brand` (string, 可选): 品牌
  - `model` (string, 可选): 型号
  - `color` (string, 可选): 颜色
  - `memory` (string, 可选): 内存
  - `imei` (string, 可选): IMEI号
  - `check_result` (string, 可选): 质检结果，默认"质检通过"
  - `copies` (int, 可选): 打印份数，默认1
- **返回**:
```json
{
  "code": 0,
  "msg": "打印成功",
  "data": {
    "code": 0,
    "message": "打印成功"
  }
}
```

#### 4.3 打印设备标签
- **接口**: `POST /recycle/printer/print_device_label/:id`
- **说明**: 根据设备ID打印标签（使用模板）
- **参数**:
  - `id` (int, 路径参数): 设备ID
- **返回**:
```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "success": true,
    "message": "标签打印成功"
  }
}
```

---

## 错误响应格式

```json
{
  "code": -1,
  "msg": "错误信息"
}
```

## 状态码说明

- `0`: 成功
- `-1`: 失败（具体错误信息在msg中）

## 注意事项

1. 所有接口都需要登录认证（Token）
2. 所有接口都需要权限验证（Role）
3. 所有操作都会记录日志（AdminLog）
4. 添加、修改、删除打印机会同步更新芯烨云
5. 打印前会自动检查打印机状态（离线或异常时会阻止打印）

