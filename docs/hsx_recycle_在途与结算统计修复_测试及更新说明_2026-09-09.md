# 回收在途与结算统计修复

日期：2026-09-09

范围：移动管理端回收统计页，以及它共用的回收看板、待办和任务查询服务。

## 1. 结论与边界

本地代码已修复，新增本次回归测试共 76 项通过：后端 59 项，移动端 17 项。另重新运行上一项移动菜单回归，59 项通过。

- 没有新增表、字段或数据库升级 SQL。
- 没有修改框架文件，运行代码均位于回收插件目录。
- 没有连接或修改开发/生产业务数据库；后端测试使用 SQLite 内存库执行真实 ThinkORM 查询。
- 没有删除、回填或改写历史订单、设备、付款流水和历史汇总。
- 没有上传服务器、发布小程序或进行生产环境核账。
- 移动端完成 SFC/模板编译、TypeScript 转译及真实页面脚本的请求模拟测试；不是完整小程序构建或真机验收。

## 2. 根因

### 在途负数

原先除签收等少量环节外，主要读取 `recycle_stat_current` 的历史增减累计值。状态埋点可能遗漏或重复，跨插件处理、删除等路径也未必成对维护计数，因此累计值可能为负或虚高。

现在在途直接查询有效订单、设备、退回单的当前状态，不依赖旧累计值；不是在前端把负数截成零。旧计数表仍保留，不自动重算。已有手动重算入口也改用同一查询口径，但本次更新不需要执行它。

### 脏状态混入和多套口径

过去看板、台账、责任分布和任务列表分别判断状态，导致：

- 未签收、已删除、已取消订单下的残留设备，甚至无有效父订单的设备混入待办。
- 设备标记为“退回”后，即使退回已经完成，仍永久占用异常处理数量。
- 重复或过期的退回关联放大数量。
- 部分付款被当成已经结束，或者待打款统计整单所有设备、整笔报价。
- “全部任务”可能因曾经负责旧环节而混入其他人员当前负责的任务。

上述在途入口现统一使用 `CoreRecycleWorkloadService`，个人任务继续叠加当前环节的责任人与权限限制。

### 金额与刷新

- 原先部分付款统计按订单付款时间和设备当前报价推算。设备后续调价会改变历史付款统计，分次结算也容易漏算或重复。
- 现在已结算金额、资金概览和金额趋势统一读取设备实际结算流水。包含折账，因此明确标注“已结算”，不等于纯现金支出。
- 待打款金额只合计有效待付款设备的未结清差额。例如最终价 4700、已结算 4600，剩余为 100。
- 原先选择历史时间可能把含“当前待办”的 overview 缓存一天；现在 overview 实时查询，趋势最多缓存 60 秒，并切换了缓存版本键。
- 移动端快速切换日期不再吞掉新请求；旧响应不能覆盖新日期。从任务页返回重新查询，失败明确显示重试，不残留上一次数字或冒充零业务。

## 3. 当前在途口径

共同约束：仅本站数据，设备必须对应本站现存且未删除、未取消的有效订单；未分配人员也计入全站在途。

| 环节 | 计数单位 | 条件概要 |
| --- | --- | --- |
| 待取货 | 单 | 待签收订单，配送方式为物流车 |
| 待签收 | 单 | 待签收订单，排除物流车待取货 |
| 质检 | 台 | 有效在途订单下待质检或质检中的未付款设备；排除未签收、已终结及转退回/代卖设备 |
| 定价 | 台 | 有效在途订单下已质检、已定价或重新定价的未付款设备 |
| 报价确认 | 台 | 有效在途订单下待确认，确认状态仍待确认，且未付款的设备 |
| 打款 | 台 | 已确认回收、未付款或部分付款，最终价大于已结算金额；已完成订单的真实部分付款补差仍计入 |
| 异常处理 | 台 | 当前关联的有效退回单仍待处理/退回中，且对应设备退回流程未结束；同一设备不因重复关联多计 |

补充说明：

- 在途是当前状态，不受今日、昨日、近 7 天等时间筛选影响。时间筛选作用于业务发生量及结算趋势等期间数据。
- “全站在途”不等于“我的任务”。后者只显示有权限且当前环节分配给自己的记录，页面已直接提示这一差别。
- 已结清、无待付差额、无有效父订单，以及“有付款时间却没有金额”等矛盾记录，不当作可直接处理的未付款记录。
- 原回收订单关闭后仍可能有未完成退回任务，这类有效退回仍保留，不会因原订单关闭就遗漏。

## 4. 历史数据处理原则

本次消除的是旧累计数、无效关系和终结状态对当前在途的污染，不是清库。

如果一条旧测试订单的状态、关系都与正常在途一致，系统不能仅凭“时间早”判断它是垃圾数据，因此仍会计入。此类记录需另行明确业务标记或关闭范围，不能自动删除或随意排除。

真实历史结算流水不会因原订单后来删除而从金额中消失。没有结算流水的旧订单不再用当前报价推算已付款金额，也不会自动补账；“未记录结算”不代表现实中一定没有付款。

已结算卡片下钻按当期实际流水查找关联订单/设备；订单列表仍遵守原有可见范围，不显示已删除订单。因此已删除订单的历史资金金额可能保留在总额中，但不会重新恢复到普通订单列表。

本次没有重建 `recycle_stat_daily`。`stat/board` 为其他消费者保留的旧 `today` / `trend` 字段也没有改成历史数据修复工程；本次移动统计页使用的在途来自 `stages`，经营趋势来自 `dashboard/trend`。

## 5. 手动更新文件清单

### 后端：9 个 PHP 文件，必须配套更新

以下全部属于 `niucloud/addon/hsx_recycle`，没有框架文件。

| 文件 | 用途 |
| --- | --- |
| [CoreRecycleWorkloadService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/service/core/stat/CoreRecycleWorkloadService.php) | 新文件；统一在途查询、有效关联校验、待付差额 |
| [CoreRecycleStatService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/service/core/stat/CoreRecycleStatService.php) | 看板改读当前业务状态，返回单位和口径说明 |
| [RecycleStageDict.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/dict/stat/RecycleStageDict.php) | 部分付款仍属于打款环节 |
| [RecycleDashboardMetricDict.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/dict/dashboard/RecycleDashboardMetricDict.php) | 更新指标含义和示例口径，区分结算额与现金 |
| [RecycleDashboardFilterDict.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/dict/dashboard/RecycleDashboardFilterDict.php) | 结算明细筛选的名称与说明 |
| [RecycleDashboardFilterService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/service/admin/dashboard/RecycleDashboardFilterService.php) | 待办与订单/设备下钻共用口径，结算下钻按流水日期 |
| [RecycleDashboardMetricService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/service/admin/dashboard/RecycleDashboardMetricService.php) | 台账、责任分布、实际结算及金额趋势 |
| [TaskService.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/service/admin/stat/TaskService.php) | 个人任务及分配计数与在途一致，匹配当前环节责任 |
| [RecycleDashboard.php](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_recycle/app/adminapi/controller/dashboard/RecycleDashboard.php) | 当前快照不使用过期缓存；趋势缓存最长 60 秒 |

### 移动管理端：1 个页面，需要重新构建发布

[site-uniapp/src/addon/hsx_recycle/pages/stats/index.vue](/Users/a123/Documents/1-work/niucloud/niucloud/site-uniapp/src/addon/hsx_recycle/pages/stats/index.vue)

包含单位、统计说明、结算说明、加载失败重试、返回刷新及快速切换日期修复。

先完整更新后端，尤其不要漏传新增的 `CoreRecycleWorkloadService.php`；再用现有发布流程构建、发布移动管理端。只替换 Vue 源文件不会改变线上已编译的小程序。

仅更新后端就能修复主要统计计算；移动端配套发布后，新的说明、名称与刷新交互才生效。无需执行 SQL、清空汇总表或处理历史数据。若运行环境使用常驻 PHP 或不自动检查时间戳的 OPcache，按现有发布流程重载对应服务。

本清单不包含上一项菜单修复的 `app/dict/adminapp/app.php`、`AppsService.php`；它们是另一个已完成需求，不要误认为本次新增修改。

## 6. 已执行测试

### 后端：59 项通过

[tests/recycle_workload_stats_smoke.php](/Users/a123/Documents/1-work/niucloud/niucloud/tests/recycle_workload_stats_smoke.php)

使用项目自带 ThinkORM、SQLite `:memory:`，自定义表前缀 `ut_`；只替换与查询无关的模型展示属性及请求上下文依赖。未加载业务 `.env`，不使用 MySQL 业务库。

覆盖：

- 七个环节的真实 SQL 查询，本站/跨站/空站点隔离。
- 旧汇总表注入负数、虚高值后，当前看板仍返回正确数量，读取过程不改旧表。
- 未签收、删除、取消、终结、孤儿、跨站关联、已付款残留、转代卖/退回设备。
- 正常旧订单不按日期被误删出在途。
- 报价待确认、已接受、已拒绝，以及部分付款、已结清、零差额、超付、矛盾付款时间。
- 有效退回、完成/取消/删除退回、过期关联、原订单关联不符、重复关联去重。
- 台账、订单下钻、设备明细、责任分布、个人任务和分配计数一致。
- 同单部分设备质检、部分设备可付款，不能把整单所有设备当作待打款。
- 实际结算的日期、跨日分次、跨站、折账、异常负金额，以及缺少流水的旧付款标志。
- 设备后续调价不改变历史实际结算，删除订单不抹去历史真实资金。
- 历史环节责任不混入当前个人任务，关键字和权限范围保持有效。
- 补款结清、退回完成、报价处理后重新查询，立即退出相应待办。

### 移动端：17 项通过

[tests/recycle_stats_mobile_smoke.cjs](/Users/a123/Documents/1-work/niucloud/niucloud/tests/recycle_stats_mobile_smoke.cjs)

- 实际页面的 SFC 解析、模板编译、TypeScript 转译。
- 三个统计请求加载、日期切换发起新请求、旧请求晚到不覆盖。
- 请求失败清空旧数字、明确重试、重试恢复。
- 负数、空计数、空环节列表不能被当成正确零值。
- 返回页面重新查询。
- 在途与资金说明弹窗。

另外：本次 9 个后端运行文件及 PHP 测试文件通过语法检查，`git diff --check` 通过。上一项菜单测试另有 59 项通过，不计入本次 76 项。

本机重跑方式（在项目根目录）：

```sh
/Users/a123/Library/PhpWebStudy/app/static-php-8.1.34/bin/php tests/recycle_workload_stats_smoke.php
/Users/a123/Library/PhpWebStudy/env/node/bin/node tests/recycle_stats_mobile_smoke.cjs
```

## 7. 发布后人工验收建议（本次尚未执行）

1. 打开移动统计，确认各环节数量非负、待取货/待签收为“单”，其他为“台”。
2. 切换今日/昨日/近 7 天：当前在途应保持当前值，期间业务量正常变化。
3. 处理一台质检或报价设备后返回统计，检查对应环节减少、下一环节增加。
4. 完成一笔退回后重新查询，确认该设备退出异常处理。
5. 测试最终价 4700、已付 4600：待付显示 100；补付后退出打款在途。
6. 验证一单多台设备分别质检、确认、付款，不能整单一起进入或离开待办。
7. 用两个不同责任人查看“我的任务”，核对全站计数与个人范围说明。
8. 临时断网、恢复重试、快速切换时间，确认没有旧数字和新日期错配。

人工验收只应在测试环境使用测试订单；生产环境仅核对现有记录，不为验证数字随意打款、退回或删除业务。
