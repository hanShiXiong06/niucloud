# hsx_recycle 0.0.2 可控经营看板实施路径

## 目标

把回收业务看板从“写死统计”升级为“可解释、可配置、可下钻”的经营看板。

本版本不计算真实利润，因为当前插件只有回收链路，没有销售出库链路。0.0.2 只统计当前能从真实业务表闭环得到的数据，例如订单量、设备量、待处理、超时、打款金额、库存回收成本、转化率、退货率。

## 设计原则

- 用户体验优先：老板、员工、财务看到数字时，必须知道这个数字是什么意思。
- 数据可追溯：每个看板数字都要能下钻到真实订单或设备。
- 口径可审计：统计接口和列表接口使用同一个 `filter_key`，避免看板显示 4 单，点进去不是 4 单。
- 配置不甩锅：后台只暴露“显示/隐藏、排序、角色可见、阈值”这类容易理解的配置，不提供自定义 SQL 或复杂公式编辑器。
- 不改框架：本阶段所有改动限定在 `addon/hsx_recycle` 内。

## 0.0.2 第一阶段交付

| 步骤 | 解决问题 | 文件 | 状态 |
| --- | --- | --- | --- |
| 1 | 明确开发路径、口径、验证方式，避免黑盒 | `docs/recycle-dashboard-0.0.2-roadmap.md` | 已完成 |
| 2 | 建立指标字典：每个指标包含名称、单位、说明、统计口径、下钻目标 | `app/dict/dashboard/RecycleDashboardMetricDict.php` | 已完成 |
| 3 | 建立过滤字典：前端只传 `filter_key`，后端负责真实 SQL 条件 | `app/dict/dashboard/RecycleDashboardFilterDict.php` | 已完成 |
| 4 | 建立统一过滤服务：统计和订单列表共用同一套条件 | `app/service/admin/dashboard/RecycleDashboardFilterService.php` | 已完成 |
| 5 | 建立看板统计服务：返回老板能直接读懂的卡片、待办、口径、下钻参数 | `app/service/admin/dashboard/RecycleDashboardMetricService.php` | 已完成 |
| 6 | 接入后台接口：看板概览、指标字典、过滤字典 | `app/adminapi/controller/dashboard/RecycleDashboard.php`、`app/adminapi/route/route.php`、`admin/api/stats.ts` | 已完成 |
| 7 | 订单列表支持 `filter_key` 和 `view_mode=device_expand` | `app/adminapi/controller/order/RecycleOrder.php`、`app/service/admin/order/RecycleOrderService.php` | 已完成 |
| 8 | 补充 0.0.2 SQL，保证升级用户可安装新增配置表 | `sql/update_0.0.2.sql`、`sql/install.sql`、`sql/uninstall.sql` | 已完成 |
| 9 | PHP 语法验证 | 新增/修改的 PHP 文件 | 已完成 |
| 10 | 后台经营看板页面接入：展示核心指标、待办提醒、点击下钻 | `admin/views/stats/dashboard.vue`、`admin/api/stats.ts` | 已完成 |
| 11 | 订单列表承接看板下钻：展示下钻提示，合并路由 `filter_key` 查询 | `admin/views/recycle_order/list.vue`、`admin/hooks/useRecycleOrderQuery.ts` | 已完成 |
| 12 | 移除老运营概览经营数字，避免与新经营看板两套口径冲突；老区域改为团队工作统计 | `admin/views/stats/dashboard.vue`、`admin/views/stats/hooks/useStatsData.ts` | 已完成 |
| 13 | 修正概述页布局：经营看板、趋势图、团队统计、会员统计拆成稳定区块，避免栅格错位 | `admin/views/stats/dashboard.vue` | 已完成 |
| 14 | 补充时间口径：指标返回 `scope_label`，区分“所选时间”“当前状态”“当前库存” | `app/dict/dashboard/RecycleDashboardMetricDict.php`、`app/service/admin/dashboard/RecycleDashboardMetricService.php` | 已完成 |
| 15 | 增加经营趋势接口和图表：按所选时间逐日展示新增订单、新增设备、打款金额、报价确认率、退货率 | `app/adminapi/controller/dashboard/RecycleDashboard.php`、`app/adminapi/route/route.php`、`admin/views/stats/hooks/useCharts.ts` | 已完成 |
| 16 | 将概述页拆分为业务看板、财务看板、用户看板，按场景展示指标和图表 | `admin/views/stats/dashboard.vue` | 已完成 |
| 17 | 将业务/财务/用户看板纳入首页组件权限，普通员工可通过默认可见、角色、指定员工控制展示 | `app/service/admin/dashboard/RecycleDashboardWidgetService.php`、`admin/views/stats/dashboard_config.vue` | 已完成 |
| 18 | 拆分看板前端组件：Tab、摘要、指标卡、指标网格、待办面板、空状态独立维护，降低页面黑盒复杂度 | `admin/views/stats/components/dashboard/*`、`admin/views/stats/types/dashboard.ts`、`admin/views/stats/dashboard.vue` | 已完成 |

## 0.0.2 设备级打款实施路径

| 步骤 | 解决问题 | 文件 | 状态 |
| --- | --- | --- | --- |
| 1 | 明确打款模式默认值，旧商户升级后仍按订单打款，避免线上流程突变 | `app/service/core/order/OrderSubmitConfigService.php` | 已完成 |
| 2 | 后台配置页增加“按订单打款 / 按设备打款”，切换时弹出影响说明，保存后才生效 | `admin/views/order_config/submit.vue`、`admin/api/order_config.ts` | 已完成 |
| 3 | 设备表增加当前打款状态，记录最近一次打款金额、时间、操作人和批次号 | `sql/install.sql`、`sql/update_0.0.2.sql` | 已完成 |
| 4 | 新增设备打款记录表，保留每次打款的设备、金额、凭证、操作人和批次号 | `sql/install.sql`、`sql/update_0.0.2.sql`、`app/model/order/RecycleDevicePayment.php` | 已完成 |
| 5 | 建立设备级打款服务：校验模式、订单、设备状态、重复打款，并同步订单部分/全部打款状态 | `app/service/admin/order/RecycleDevicePaymentService.php` | 已完成 |
| 6 | 后端新增设备打款接口和日志接口；按设备模式下旧整单打款接口会明确拒绝 | `app/adminapi/controller/order/RecycleOrder.php`、`app/adminapi/route/route.php` | 已完成 |
| 7 | 订单列表和订单详情数据返回打款模式、设备打款字段和设备打款汇总 | `app/service/admin/order/RecycleOrderService.php` | 已完成 |
| 8 | 打款弹窗根据模式切换交互：订单模式直接确认，设备模式必须选择一台或多台设备 | `admin/views/recycle_order/components/PaymentMethodDialog.vue` | 已完成 |
| 9 | 订单列表展开设备时展示“未打款 / 已打款”，方便财务对账 | `admin/views/recycle_order/components/RecycleOrderDesktopTable.vue`、`RecycleOrderMobileCards.vue` | 已完成 |
| 10 | 同步插件包后台文件，保证打包安装与当前运行后台一致 | `niucloud/addon/hsx_recycle/admin/*` | 已完成 |
| 11 | PHP 语法检查、前端 Vite 构建、空白检查 | PHP 文件、`admin` 前端 | 已完成 |

### 设备级打款业务规则

- 默认模式是 `按订单打款`，升级到 0.0.2 后不会改变旧商户的原有打款流程。
- 切换到 `按设备打款` 后，订单不会因为一次确认打款就直接完成；财务需要选择具体设备打款。
- 只有 `已回收`、未打款、金额大于 0 的设备允许被选择打款。
- 一个订单可以分多次打款。部分设备已打款时，订单 `pay_status` 会变为 `部分打款`，设备列表会显示每台设备的打款状态。
- 所有应打款设备都完成后，订单自动变为 `已完成`，并触发订单打款完成后的通知/奖励事件。
- 旧整单打款接口在设备模式下会返回明确错误：`当前为按设备打款模式，请在打款弹窗中选择需要打款的设备`，避免误操作整单完成。

## 0.0.2 订单流转模式实施路径

| 步骤 | 解决问题 | 文件 | 状态 |
| --- | --- | --- | --- |
| 1 | 将“打款模式”升级为“订单流转模式”，配置只影响新订单，避免历史订单因配置切换被误伤 | `app/service/core/order/OrderSubmitConfigService.php`、`admin/views/order_config/submit.vue` | 已完成 |
| 2 | 新订单创建时写入订单自己的 `flow_mode`，形成订单快照，历史订单默认整单流转 | `app/service/core/recycle_order/CoreRecycleOrderService.php`、`sql/install.sql`、`sql/update_0.0.2.sql` | 已完成 |
| 3 | 设备表增加报价确认状态，支持后续“部分设备先确认、先打款” | `sql/install.sql`、`sql/update_0.0.2.sql`、`app/model/order/RecycleDevice.php` | 已完成 |
| 4 | 建立订单流转模式服务：统一计算流转模式、设备摘要、可确认、可打款和禁用原因，避免前端猜业务规则 | `app/service/admin/order/RecycleOrderFlowModeService.php` | 已完成 |
| 5 | 订单列表和详情返回 `flow_mode_name`、`flow_summary`、`available_actions` 和设备级 `can_pay/pay_disabled_reason` | `app/service/admin/order/RecycleOrderService.php` | 已完成 |
| 6 | 设备打款按订单自己的 `flow_mode` 判断，不再按全局配置误伤历史订单 | `app/service/admin/order/RecycleDevicePaymentService.php`、`app/adminapi/controller/order/RecycleOrder.php` | 已完成 |
| 7 | 后台配置页改为“整单流转 / 按设备流转”，切换时明确提示“保存后只影响新订单” | `admin/views/order_config/submit.vue`、`admin/api/order_config.ts` | 已完成 |
| 8 | 订单列表展示流转模式、设备进度摘要和设备确认状态；打款弹窗展示不可打款原因 | `admin/views/recycle_order/components/RecycleOrderDesktopTable.vue`、`PaymentMethodDialog.vue`、`admin/hooks/useRecycleOrderActions.ts` | 已完成 |
| 9 | 增加后台设备确认接口，为后续客户前台“确认这几台报价”提供后端能力 | `app/adminapi/controller/order/RecycleOrder.php`、`app/adminapi/route/route.php` | 已完成 |

### 订单流转模式业务规则

- `整单流转`：新订单需要全部设备完成质检后统一确认、统一打款，适合批量结算商家。
- `按设备流转`：新订单下设备可独立质检、确认、打款；订单只展示整体进度，全部设备闭环后才完成。
- 配置页切换模式后必须点击“保存设置”才生效。
- 配置只影响新订单；每个订单创建时写入自己的 `flow_mode`，历史订单仍按创建时的模式流转。
- 前端列表和打款弹窗不再自己推断业务条件，统一使用后端返回的 `can_pay`、`pay_disabled_reason`、`flow_summary`。
- 设备不能打款时必须显示原因，例如：`待客户确认`、`待质检或待定价`、`金额为0，不可打款`、`已打款`、`设备已退回`。

### 订单流转模式当前边界

- 已提供后台设备确认接口，但客户前台“确认这几台报价”的完整交互还需要在用户端页面继续接入。
- `update_0.0.2.sql` 是标准升级脚本，按框架升级链路执行一次；如果手工重复执行，已有字段可能触发重复列错误。

## 第一批指标口径

| 指标 | 含义 | 下钻 |
| --- | --- | --- |
| 新增订单 | 所选时间内创建的有效回收订单数量 | 订单列表：`filter_key=today_created_orders` |
| 新增设备 | 所选时间内创建订单涉及的设备数量 | 订单列表设备展开：`filter_key=today_created_devices&view_mode=device_expand` |
| 打款金额 | 所选时间内已打款订单下设备最终价合计 | 订单列表：`filter_key=paid_today` |
| 待质检 | 当前已签收/质检中但未完成质检的订单和设备 | 订单列表：`filter_key=pending_check` |
| 质检超时 | 当前签收超过阈值仍未完成质检的订单和设备 | 订单列表：`filter_key=check_timeout` |
| 待打款 | 当前用户已确认/流程进入待打款但未打款的订单和金额 | 订单列表：`filter_key=pending_pay` |
| 库存回收成本 | 当前已回收但未销售闭环内设备最终价合计 | 订单列表设备展开：`filter_key=inventory_devices&view_mode=device_expand` |
| 报价确认率 | 所选时间内报价订单中，进入待打款或完成的占比 | 订单列表：分子 `filter_key=quote_confirmed`，分母 `filter_key=quote_decided` |
| 退货率 | 所选时间内形成最终结果的设备中，已退回设备占比 | 订单列表设备展开：`filter_key=returned_devices&view_mode=device_expand` |

## 下钻返回约定

看板接口返回的每个数据项必须包含：

- `key`：指标唯一标识
- `title`：用户看到的名称
- `value`：数值
- `unit`：单位
- `description`：一句话解释
- `caliber`：统计口径
- `drilldown`：下钻目标、`filter_key`、可选 `view_mode`
- `scope_label`：时间口径，例如“所选时间”“当前状态”“当前库存”

示例：

```json
{
  "key": "check_timeout",
  "title": "质检超时",
  "value": 4,
  "unit": "单",
  "description": "签收超过24小时仍未完成质检的订单",
  "caliber": "订单状态为已签收或质检中，且签收时间早于当前时间24小时",
  "drilldown": {
    "target": "order_list",
    "filter_key": "check_timeout"
  }
}
```

## 验证记录

| 时间 | 验证内容 | 结果 |
| --- | --- | --- |
| 2026-05-24 | 路径确认：后端插件为 `niucloud/addon/hsx_recycle`，后台前端为 `web/addon/recycle`，小程序为 `site-uniapp/src/addon/recycle` | 通过 |
| 2026-05-24 | PHP 语法检查：指标字典、过滤字典、过滤服务、指标服务、看板控制器、路由、订单控制器、订单服务 | 通过 |
| 2026-05-24 | 管理端 `npm run build`：Vite 编译通过；最后发布到 `niucloud/public/admin` 时因本地文件权限 `EPERM unlink index.html` 失败 | 编译通过，发布未完成 |
| 2026-05-24 | 管理端 `npx vite build`：只做前端编译，不执行发布脚本 | 通过 |
| 2026-05-24 | 设备级打款：PHP 语法检查、`git diff --check`、管理端 `npx vite build` | 通过 |
| 2026-05-24 | 订单流转模式：核心 PHP 文件语法检查、`git diff --check`、管理端 `npx vite build` | 通过 |

## 当前接口

- `GET /adminapi/recycle/dashboard/overview`：经营看板概览，返回卡片、口径、阈值、下钻参数。
- `GET /adminapi/recycle/dashboard/trend`：经营趋势，按所选时间逐日返回新增订单、新增设备、打款金额、报价确认率、退货率；最多展示 31 天。
- `GET /adminapi/recycle/dashboard/metrics`：指标字典，前端可用于解释说明。
- `GET /adminapi/recycle/dashboard/filters`：下钻过滤字典，前端可用于展示“点击后会看什么”。
- `GET /adminapi/recycle/recycle_order/lists?filter_key=check_timeout&view_mode=device_expand`：订单列表按看板口径下钻。
- `POST /adminapi/recycle/recycle_order/{id}/device_payment_confirm`：按设备确认打款，需要传 `device_ids` 和 `payment_info`。
- `GET /adminapi/recycle/recycle_order/{id}/device_payment_logs`：设备打款记录。
- `POST /adminapi/recycle/recycle_order/{id}/device_confirm`：按设备确认报价，需要传 `device_ids`，用于按设备流转模式。
- 当 `view_mode=device_expand` 时，订单列表中的设备关系也会按同一个 `filter_key` 过滤，避免展开设备和看板设备数对不上。
- 订单列表的 `status_counts` 也会叠加同一个 `filter_key`，避免下钻后顶部状态数字和当前列表口径不一致。
- 后台页面顶部已接入“经营看板”卡片。卡片点击后会跳转订单列表，并带上 `filter_key`、`view_mode`、时间范围和标题。
- 后台页面已接入“经营趋势”图。库存回收成本属于当前库存快照，不做历史回放；未来如需历史库存成本，需要新增库存快照或出入库流水。
- 老“运营概览”中的今日订单、今日质检、今日打款、今日退货已撤掉，经营数字统一由顶部经营看板展示；下方保留团队/员工工作统计。

## 当前边界

- 0.0.2 已预留异常规则配置表，但第一阶段接口仍使用默认阈值参数，避免在没有配置页面时让用户困惑。
- 0.0.2 已预留打印变量配置表，后续可逐步替换打印模板中的硬编码变量。
- 本阶段没有计算利润，只计算回收成本和待打款金额。
