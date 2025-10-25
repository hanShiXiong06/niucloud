# 回收系统通知功能说明

本文档详细说明了回收系统的通知功能实现方式及使用方法。

## 通知功能组件

系统通知功能由以下几个部分组成：

1. **通知监听器**
   - `OrderSign.php`: 订单签收通知
   - `OrderAdd.php`: 回收订单下单通知
   - `OrderAgree.php`: 订单同意通知
   - `OrderPay.php`: 订单打款通知

2. **事件Job**
   - `OrderSignAfter.php`: 订单签收后事件处理
   - `OrderAddAfter.php`: 订单下单后事件处理
   - `OrderAgreeAfter.php`: 订单同意后事件处理
   - `OrderPayAfter.php`: 订单打款后事件处理

3. **事件服务类**
   - `CoreOrderEventService.php`: 处理各类订单事件

4. **通知服务**
   - `CoreRecycleOrderNotifyService.php`: 封装了通知发送的具体实现

## 触发点集成

通知功能已在以下业务流程中集成：

### 在RecycleOrderService中：

1. **订单创建时**
   - 在`add`方法中，调用`orderAddNotify`发送下单通知

2. **订单签收时**
   - 在`sign`方法中，调用`orderSignNotify`发送签收通知

3. **价格确认时**
   - 在`confirmPrice`方法中，调用`orderAgreeNotify`发送同意通知

4. **订单打款时**
   - 在`payment`方法中，调用`orderPayNotify`发送打款通知

### 在RecycleDeviceService中：

1. **设备价格确认时**
   - 在`confirmPrice`方法中，当订单下所有设备都完成价格确认时，触发`orderAgreeNotify`

2. **设备回收时**
   - 在`recycle`方法中，当订单下所有设备都完成回收时，触发`orderPayNotify`

## 通知内容配置

通知内容在以下配置文件中定义：

- `app/dict/notice/notice.php`: 通知基本配置
- `app/dict/notice/weapp.php`: 微信小程序通知配置
- `app/dict/notice/wechat.php`: 微信公众号通知配置
- `app/dict/notice/sms.php`: 短信通知配置

## 通知流程

1. 业务层触发通知（如订单创建、签收等）
2. 调用`CoreRecycleOrderNotifyService`中的对应方法
3. 事件服务`CoreOrderEventService`触发相应事件
4. 事件任务`Job`异步处理事件
5. 通知监听器处理并发送通知

## 如何扩展

如需添加新的通知类型：

1. 在`app/dict/notice/`目录下的配置文件中添加新的通知定义
2. 创建对应的通知监听器
3. 添加对应的事件Job
4. 在`CoreOrderEventService`中添加对应的事件处理方法
5. 在`CoreRecycleOrderNotifyService`中添加对应的通知方法
6. 在业务层适当位置调用通知方法

## 使用示例

```php
// 在业务逻辑中调用通知
$notifyService = new CoreRecycleOrderNotifyService();

// 订单下单通知
$notifyService->orderAddNotify(['order_id' => $orderId]);

// 订单签收通知
$notifyService->orderSignNotify(['order_id' => $orderId]);

// 订单同意通知
$notifyService->orderAgreeNotify(['order_id' => $orderId]);

// 订单打款通知
$notifyService->orderPayNotify(['order_id' => $orderId]);
``` 