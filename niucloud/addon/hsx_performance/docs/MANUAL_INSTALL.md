# 绩效与经营报告插件安装说明

## 新安装

1. 执行 `sql/install.sql`，创建 `performance_report` 和 `performance_fact`。
2. 安装或升级插件，使框架注册 `hsx_performance_report_tick` 定时任务。
3. 在“经营分析 -> 经营报告”中启用报告、选择周期、推送时间和老板接收人。
4. 在企业微信插件中开启“经营报告通知”，并为接收人绑定企业微信成员 UserID。
5. 管理小程序发布前确认包含以下页面：
   - `addon/hsx_performance/pages/report/list`
   - `addon/hsx_performance/pages/report/detail`

经营报告中的“已进入通知队列”表示企业微信插件已接收消息；实际送达结果和接口错误请在企业微信插件的消息日志中查看。未配置接收人或未绑定 UserID 的报告可在 PC 报告列表中补推，不会重复创建消息。

## 跨插件契约

- 指标采集：`HsxBusinessReportMetricsRequested`
- 报告生成：`HsxBusinessReportGenerated`
- 员工工作事实：`HsxPerformanceFactRecorded`

ERP、回收和未来业务插件只返回自己拥有的指标；企业微信只消费报告事件，不查询业务表。

## 调度口径

- 日报：每天配置时间生成昨天数据。
- 周报：每周一生成上周一至周日数据。
- 月报：每月 1 日生成上个自然月数据。
- 唯一键：`site_id + report_type + period_key`，重复调度不会重复生成。
- 补偿策略：队列晚于配置时间执行仍会补生成；通知失败由企业微信消息队列继续重试。
