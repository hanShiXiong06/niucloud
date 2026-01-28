# 回收订单流程引擎使用文档

## 概述

订单流程引擎是一个配置驱动的订单状态管理系统，通过统一的流程编排实现订单状态转换、权限控制和业务处理。

## 核心组件

### 1. 流程配置字典
- **RecycleOrderApiFlowDict**: API端（用户端）流程配置
- **RecycleOrderAdminFlowDict**: Admin端（管理端）流程配置

### 2. 核心服务
- **CoreRecycleOrderFlowService**: 流程引擎核心服务

### 3. 业务处理器
所有处理器位于 `handler/` 目录：
- **SignHandler**: 签收订单
- **CancelHandler**: 取消订单
- **CloseHandler**: 关闭订单
- **StartCheckHandler**: 开始质检
- **CompleteCheckHandler**: 完成质检
- **AddDeviceHandler**: 添加设备
- **SetPriceHandler**: 设置价格
- **AdjustPriceHandler**: 调整价格
- **ForceConfirmHandler**: 强制确认
- **ConfirmReceiptHandler**: 用户确认收货
- **ConfirmPriceHandler**: 用户确认价格
- **NegotiateHandler**: 议价
- **PaymentHandler**: 打款

## 使用示例

### 管理员操作示例

```php
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderFlowService;

$flowService = new CoreRecycleOrderFlowService();

// 1. 签收订单
$result = $flowService->execute(
    $orderId,
    'sign',
    [
        'devices' => [
            [
                'imei' => '123456789',
                'model' => 'iPhone 13',
                'initial_price' => 3000,
                'category_id' => 1
            ]
        ],
        'remark' => '设备完好'
    ],
    CoreRecycleOrderFlowService::FLOW_TYPE_ADMIN,
    [
        'operator_id' => $adminId,
        'site_id' => $siteId
    ]
);

// 2. 开始质检
$result = $flowService->execute(
    $orderId,
    'start_check',
    ['remark' => '开始质检'],
    CoreRecycleOrderFlowService::FLOW_TYPE_ADMIN,
    ['operator_id' => $adminId, 'site_id' => $siteId]
);

// 3. 完成质检
$result = $flowService->execute(
    $orderId,
    'complete_check',
    ['remark' => '质检完成'],
    CoreRecycleOrderFlowService::FLOW_TYPE_ADMIN,
    ['operator_id' => $adminId, 'site_id' => $siteId]
);

// 4. 设置价格
$result = $flowService->execute(
    $orderId,
    'set_price',
    [
        'devices' => [
            ['device_id' => 1, 'final_price' => 2800, 'remark' => '成色良好'],
            ['device_id' => 2, 'final_price' => 2500, 'remark' => '有轻微划痕']
        ]
    ],
    CoreRecycleOrderFlowService::FLOW_TYPE_ADMIN,
    ['operator_id' => $adminId, 'site_id' => $siteId]
);

// 5. 打款
$result = $flowService->execute(
    $orderId,
    'payment',
    [
        'payment_info' => [
            'method' => 'alipay',
            'transaction_no' => '202601260001'
        ],
        'remark' => '已打款'
    ],
    CoreRecycleOrderFlowService::FLOW_TYPE_ADMIN,
    ['operator_id' => $adminId, 'site_id' => $siteId]
);
```

### 用户操作示例

```php
// 1. 用户取消订单
$result = $flowService->execute(
    $orderId,
    'cancel',
    ['reason' => '不想卖了'],
    CoreRecycleOrderFlowService::FLOW_TYPE_API,
    ['operator_id' => $memberId, 'site_id' => $siteId]
);

// 2. 用户确认收货
$result = $flowService->execute(
    $orderId,
    'confirm_receipt',
    ['remark' => '已收到快递'],
    CoreRecycleOrderFlowService::FLOW_TYPE_API,
    ['operator_id' => $memberId, 'site_id' => $siteId]
);

// 3. 用户议价
$result = $flowService->execute(
    $orderId,
    'negotiate',
    [
        'expected_price' => 3200,
        'reason' => '设备成色很好，希望提高价格'
    ],
    CoreRecycleOrderFlowService::FLOW_TYPE_API,
    ['operator_id' => $memberId, 'site_id' => $siteId]
);

// 4. 用户确认价格
$result = $flowService->execute(
    $orderId,
    'confirm_price',
    ['remark' => '同意该价格'],
    CoreRecycleOrderFlowService::FLOW_TYPE_API,
    ['operator_id' => $memberId, 'site_id' => $siteId]
);
```

## 订单流程图

```
待签收(1) 
  ├─ [Admin] sign → 已签收(2)
  ├─ [Admin/User] cancel → 已取消(9)
  └─ [Admin] close → 已关闭(8)

已签收(2)
  ├─ [Admin] start_check → 质检中(3)
  ├─ [Admin] add_device → 已签收(2)
  ├─ [Admin] cancel → 已取消(9)
  └─ [User] confirm_receipt → 质检中(3)

质检中(3)
  ├─ [Admin] complete_check → 已质检(4)
  └─ [Admin] cancel → 已取消(9)

已质检(4)
  ├─ [Admin] set_price → 待确认(5)
  └─ [Admin] cancel → 已取消(9)

待确认(5)
  ├─ [User] confirm_price → 待打款(6)
  ├─ [User] negotiate → 待确认(5)
  ├─ [Admin] adjust_price → 待确认(5)
  ├─ [Admin] force_confirm → 待打款(6)
  └─ [Admin] cancel → 已取消(9)

待打款(6)
  ├─ [Admin] payment → 已完成(7)
  └─ [Admin] cancel → 已取消(9)

已完成(7) - 终态
已关闭(8) - 终态
已取消(9) - 终态
```

## 扩展指南

### 添加新操作

1. **在配置文件中添加操作定义**
```php
// RecycleOrderAdminFlowDict.php
'new_action' => [
    'to_status' => RecycleOrderDict::ORDER_STATUS_XXX,
    'handler' => 'NewActionHandler',
    'validate' => ['checkSomething'],
    'event_after' => 'orderNewActionAfter',
    'require_data' => ['field1', 'field2'],
    'description' => '新操作说明'
]
```

2. **创建处理器类**
```php
// handler/NewActionHandler.php
namespace addon\recycle\app\service\core\recycle_order\handler;

class NewActionHandler extends BaseFlowHandler
{
    public function handle(array $order, array $data, array $context): array
    {
        // 实现业务逻辑
        return $this->success('操作成功', $result);
    }
}
```

3. **添加事件方法（可选）**
```php
// CoreRecycleOrderEventService.php
public static function orderNewActionAfter($data) {
    NewActionAfter::dispatch(['data' => $data]);
    return true;
}
```

### 添加验证规则

在 CoreRecycleOrderFlowService 中添加验证方法：

```php
private function checkSomething(array $order, array $data): void
{
    if (/* 验证条件 */) {
        throw new CommonException('验证失败');
    }
}
```

## 注意事项

1. **事务处理**: 流程引擎自动处理事务，处理器中无需手动开启事务
2. **状态转换**: 由流程引擎统一调用 CoreRecycleOrderStatusService
3. **事件触发**: 由流程引擎统一调用 CoreRecycleOrderEventService
4. **权限控制**: 通过配置文件的 actions 字段控制
5. **数据验证**: 通过 require_data 和 validate 字段配置

## 优势

✅ **配置驱动**: 流程规则清晰可见，易于维护  
✅ **权限分离**: API和Admin权限完全分离  
✅ **易于扩展**: 添加新功能只需配置+处理器  
✅ **代码复用**: 处理器可复用现有服务  
✅ **事件集成**: 完美集成现有事件系统  
✅ **统一编排**: 流程引擎统一管理所有操作
