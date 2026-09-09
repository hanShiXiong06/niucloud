# phone_shop 上新订阅、批量通知与商品列表修复

日期：2026-09-07 开始，2026-09-08 完成本轮校验。范围：本次订阅/批次通知/list 布局，不代表仓库其他未提交工作都已完成。

## 结论与边界

原订阅有保存条件和商品变化匹配，但不是完整、可核验的微信触达闭环：保存偏好不等于微信同意，队列入队也不等于微信发送成功。本次已补授权、微信回执、批次入口、逐客户结果和重复发送保护。

- 本次业务实现都在 phone_shop 插件内，没有新增公共框架改动。
- 不新增表、字段，不需要执行新的 SQL；复用已有商品订阅表、订阅命中表和导入导出任务表。
- 不回填历史数据。批次发送仅支持本次更新之后创建、保存了商品清单的导入任务。
- 未连接线上数据库，未导入真实业务数据，未给真实客户发送通知，未部署或执行带发布脚本的全量打包。
- 已做代码/模板编译和隔离测试；真实微信模板、授权额度、收信、通知跳转及手机视觉仍需按文末清单验收。

## 用户与管理员的操作

客户：进入分类页或 list → 可关闭的“上新提醒”提示 → 点击订阅 → 微信同意 → 保存本站订阅偏好。

- 自动提示每天最多一次（按设备本地缓存、站点、会员区分），关闭后仍有“上新提醒”入口。
- 未登录先登录，保留原入口参数。原有强制登录设置继续生效。
- 微信要求订阅授权由用户点击等合法动作触发，不能在刚进入页面时无条件拉起微信授权窗。
- 一次性订阅不是永久的每日群发权限；额度以微信实际回执为准。提供“再次授权”及“取消订阅”。
- H5 不伪装微信小程序授权；新增微信提醒入口只在微信小程序显示。[uni-app 订阅消息说明](https://uniapp.dcloud.net.cn/api/other/requestSubscribeMessage.html)

管理员入口：商城 → 商品列表 → 批量导入与导出 → 任务记录 → 对应导入批次的“上新通知”。

1. 完成导入，上架准备销售的设备。
2. 点击“上新通知”，查看本批当前可售、已同意本批提醒的客户、已排除商品数量。
3. 确认发送。后台队列每次处理 5 位客户并续投递，一人一批至多一条被微信明确受理的通知。
4. 抽屉中查看进度、微信回执及每位客户的处理原因，支持分页。
5. 失败/未发送项可显式重试；成功与结果待核实项不重发。
6. 消费进程中断时，可检查并恢复；数据库命名锁阻止接管仍在运行的任务。恢复时复用回执、重新核算统计，不重复发已受理项。

导入时选择“默认下架”的批次不会被误发。没有可售商品、没有已同意客户、微信配置缺失、队列关闭、没有权限时均提示具体原因。

通知点击进入本批商品列表。后端限定当前站点、当前仍可售的本批商品；不会只凭前端参数放行其他站点数据。模板价格字段使用本批商品标价起价，说明中提示实际售价以商品页为准。

## 两种订阅的关系

| 订阅选择 | 行为 |
| --- | --- |
| 只订阅分类/筛选 | 保留符合条件商品的上新/调价提醒 |
| 开启“上新提醒” | 优先接收管理员发送的整批通知，保留原筛选偏好，但不叠加逐台通知 |
| 取消“上新提醒” | 恢复原分类/筛选订阅；微信发送仍需有效授权额度 |

批次提醒不把只订阅某分类的人擅自加入全站通知名单。原分类/筛选入口补了再次授权按钮。

## 数据与重复发送保护

复用：
- `phone_shop_goods_subscription.rule_json`：规则与本站小程序/模板的同意记录；同意元数据不参与规则去重，也不允许客户端直接写入。
- `phone_shop_goods_transfer_task.result_json`：本批实际导入商品 ID、关联通知任务。
- 同一任务表的 `arrival_notice` 任务：接收者快照、游标、统计结果。
- `phone_shop_goods_subscription_match`：逐客户回执；批次回执使用 goods_id=0，指纹含任务 ID 和会员 ID，不与真实商品命中冲突。

通知状态语义：

| 值 | 含义 | 重试规则 |
| --- | --- | --- |
| 0 | 已认领，等待明确回执 | 中断后按待核实处理 |
| 1 | 微信明确受理 | 不重发；不代表已读 |
| 2 | 明确失败 | 管理员可重试 |
| 3 | 网络/写回中断，结果待核实 | 不自动重发 |
| 4 | 未发送，例如取消、无身份、无可售商品 | 条件修复后可显式重试 |

- 父导入任务行锁防止重复点击创建多个通知任务。
- 消费使用 MySQL 命名锁，不在网络请求期间锁业务表；同库同站同任务互斥。
- 单商品订阅使用会员行的短事务认领，防止父分类/子分类重叠重复触达。
- 微信请求复用框架小程序实例和授权 token，仅在此次克隆实例关闭传输层自动重试，避免网络不确定时 SDK 自行重复 POST。
- 日志不写小程序密钥、access_token 或 openid。
- 收到 43101 等微信错误时显示具体原因，不再把入队当成成功。

## list 布局处理

用户尚未补充“只有左边”对应单行图文还是双列瀑布流的截图，本次对两处风险同时处理：

- 单行图文：封面增加固定宽度外层；右侧内容明确 flex 收缩和 min-width，避免小程序自定义组件宿主挤走内容。
- 双列：两个独立循环中的同名作用域插槽改为同一个双层循环，显式保留左右列宽；无效的估算高度不再让列分配失衡。
- 已用 uni-app 的微信小程序模板编译器生成 WXML，确认包含左右列循环与各项插槽；未以此替代真机视觉验收。

## 更新步骤

1. 先备份将替换的插件文件和当前可用前端构建产物。
2. 更新下面列出的 phone_shop PHP 文件及插件前端源文件。不要只上传新增文件，路由、事件和原服务文件也要一并更新。
3. 通过现有插件更新/菜单重置流程加载新菜单，给相关角色授予“上新通知”权限。未授权员工不能群发；超级管理员可用。
4. 在本站通知设置中，配置并开启“商品上新与调价订阅提醒”的小程序渠道，使用本站小程序合法可用的模板。填好小程序配置或第三方授权；本功能不替客户申请微信模板。
5. 按现有发布流程重新构建后台、H5、微信小程序。订阅授权须在微信小程序测试，H5 用于商品浏览/布局回归。
6. 重启现有队列消费者，使其加载新增任务类和最新服务；检查队列开启且正常消费。
7. 用更新后新导入的小批次验收，确认后再向其他已同意客户发送。

本次没有新增公共框架文件需要替换。仓库中其他任务遗留的改动不属于本说明；不要把整个未提交目录不加区分地覆盖线上。

### PHP 文件（插件根目录）

- [app/service/core/goods/CoreGoodsNoticeService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/core/goods/CoreGoodsNoticeService.php)
- [app/service/core/goods/CoreGoodsArrivalService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/core/goods/CoreGoodsArrivalService.php)
- [app/job/goods/GoodsArrivalNotice.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/job/goods/GoodsArrivalNotice.php)
- [app/service/admin/goods/GoodsArrivalService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/admin/goods/GoodsArrivalService.php)
- [app/service/api/goods/GoodsSubscriptionService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/api/goods/GoodsSubscriptionService.php)
- [app/api/controller/goods/GoodsSubscription.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/api/controller/goods/GoodsSubscription.php)
- [app/api/route/route.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/api/route/route.php)
- [app/service/core/goods/CoreGoodsSubscriptionMatchService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/core/goods/CoreGoodsSubscriptionMatchService.php)
- [app/service/admin/goods/GoodsTransferService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/admin/goods/GoodsTransferService.php)
- [app/service/admin/goods/GoodsService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/admin/goods/GoodsService.php)
- [app/service/admin/goods/VirtualGoodsService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/admin/goods/VirtualGoodsService.php)
- [app/adminapi/controller/goods/GoodsTransfer.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/adminapi/controller/goods/GoodsTransfer.php)
- [app/adminapi/route/route.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/adminapi/route/route.php)
- [app/dict/menu/site.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/dict/menu/site.php)
- [app/event.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/event.php)
- [app/api/controller/goods/Goods.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/api/controller/goods/Goods.php)
- [app/service/api/goods/GoodsService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/api/goods/GoodsService.php)

### 移动端运行源码

- [api/goods.ts](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/api/goods.ts)
- [hooks/useGoodsSubscriptionNotice.ts](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/hooks/useGoodsSubscriptionNotice.ts)
- [components/GoodsArrivalSubscription.vue](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/components/GoodsArrivalSubscription.vue)
- [components/PhoneGoodsWaterfall.vue](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/components/PhoneGoodsWaterfall.vue)
- [components/goods-filter/GoodsCategoryFilterPopup.vue](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/components/goods-filter/GoodsCategoryFilterPopup.vue)
- [components/goods-filter/GoodsMoreFilterPopup.vue](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/components/goods-filter/GoodsMoreFilterPopup.vue)
- [pages/goods/category.vue](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/pages/goods/category.vue)
- [pages/goods/list.vue](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/pages/goods/list.vue)

以上 8 个文件已同步到插件发布副本：`/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/uni-app/` 下相同相对路径。

### 后台运行源码

- [api/goods.ts](/Users/a123/Documents/1-work/niucloud/niucloud/admin/src/addon/phone_shop/api/goods.ts)
- [views/goods/components/goods-transfer-dialog.vue](/Users/a123/Documents/1-work/niucloud/niucloud/admin/src/addon/phone_shop/views/goods/components/goods-transfer-dialog.vue)
- [views/goods/components/goods-arrival-notice.vue](/Users/a123/Documents/1-work/niucloud/niucloud/admin/src/addon/phone_shop/views/goods/components/goods-arrival-notice.vue)

以上 3 个文件已同步到插件发布副本：`/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/admin/` 下相同相对路径。

## 测试记录

| 检查 | 结果 | 范围 |
| --- | --- | --- |
| PHP 语法 | 18 个相关文件通过 | 新旧路由、事件、服务、任务及原监听器 |
| 后端隔离测试 | 38 项通过 | 内存模型、假微信客户端、模拟队列/锁分支 |
| 前端编译/隔离/发布副本 | 34 项通过 | H5/微信 SFC、TS 转译、微信 WXML 编译、授权手势、布局结构、11 份发布副本 |
| 插件边界回归 | 40 项通过 | 登录/支付原修复仍保持插件化 |
| 商品门禁回归 | 39 项通过 | 登录开关、异步请求、离页/返回、入口参数 |
| 登录返回回归 | 41 项通过 | H5/小程序/App 的原插件登录返回行为 |
| 差异空白检查 | 通过 | git diff --check |

核心测试：[后端隔离测试](/Users/a123/Documents/1-work/niucloud/niucloud/tests/phone_shop_arrival_notice_smoke.php)、[前端测试](/Users/a123/Documents/1-work/niucloud/niucloud/tests/phone_shop_arrival_frontend_smoke.cjs)。

本轮旧门禁/登录测试初次执行有失败：测试仍按登录插件化前的同步时序和参数全集做断言，且缺少新增批次参数的 mock。已更新测试夹具：等待登录回跳检查、校验并剔除内部回跳标记后比较业务参数、补批次参数、仅规范 CRLF/LF；没有为通过旧测试修改登录业务代码。

隔离测试不证明真实 MySQL 并发、真实队列重启、微信额度或最终送达。本次也没有做整个项目的生产打包。

## 需要人工验收的清单

- [ ] 配置关闭时无上新授权入口；配置不完整时后台明确提示。
- [ ] 微信小程序进入分类和 list 可见提示；关闭后不反复弹；点击才拉起微信授权。
- [ ] 拒绝不加入批次名单；同意后名单增加；取消后排队任务不再通知该客户。
- [ ] 再次授权可用；微信额度不足显示 43101，不显示发送成功。
- [ ] 新批次混合上架、下架、无库存、锁定、已售设备，预览只计可售商品。
- [ ] 重复点发送只复用一个任务；同一客户本批只收到一条，原分类订阅不叠加逐台通知。
- [ ] 通知结果可分页查每个客户；失败可重试、成功/待核实不重发。
- [ ] 点微信通知进入本站本批商品列表；批次中后来下架/售出的商品不再显示。
- [ ] 普通无权限员工不能群发；跨站批次/结果查询被拒绝。
- [ ] 测试环境模拟队列进程中断再恢复，核验无重复消息、无重复统计。
- [ ] 手机单行图文右侧标题、价格和操作可见；双列左右均有商品；切换筛选及追加分页正常。
- [ ] 强制登录、返回首页、购买/转发、支付金额原有行为无回归。
