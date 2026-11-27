# 绩效与经营报告插件安装说明

## 新安装

1. 执行 `sql/install.sql`，创建经营报告、事实账本、指标目录、员工日汇总和异常表。
2. 安装或升级插件，使框架注册 `hsx_performance_report_tick` 定时任务。
3. 在“经营分析 -> 员工产出”确认业务事实开始进入；需要企业微信报告时再进入“经营报告”配置。
4. 在企业微信插件中开启“经营报告通知”，并为接收人绑定企业微信成员 UserID。
5. 管理小程序发布前确认包含以下页面：
   - `addon/hsx_performance/pages/output/list`
   - `addon/hsx_performance/pages/output/detail`
   - `addon/hsx_performance/pages/report/list`
   - `addon/hsx_performance/pages/report/detail`

经营报告中的“已进入通知队列”表示企业微信插件已接收消息；实际送达结果和接口错误请在企业微信插件的消息日志中查看。未配置接收人或未绑定 UserID 的报告可在 PC 报告列表中补推，不会重复创建消息。

## 跨插件契约

- 指标采集：`HsxBusinessReportMetricsRequested`
- 报告生成：`HsxBusinessReportGenerated`
- 员工工作事实：`HsxPerformanceFactRecorded`

ERP、回收和未来业务插件只返回自己拥有的指标；企业微信只消费报告事件，不查询业务表。

## 从 1.0.0 升级

1. 先备份 `performance_fact` 和 `performance_report`。
2. 执行 `sql/update_1.1.0.sql`。
3. 更新插件代码并刷新菜单、管理端和管理小程序。
4. 升级 SQL 会自动规范已知旧指标并回填历史日汇总；如升级期间仍有业务写入，可在“员工产出 -> 数据工具”中再执行一次“重建当前周期汇总”。
5. 运行“数据对账”，确认事实分组数与汇总分组数一致。

`update_1.1.0.sql` 会给 `performance_fact` 增加以下字段：

- 契约：`event_name`、`event_version`、`payload_hash`
- 指标：`metric_name`、`fact_scope`、`fact_type`、`direction`
- 冲红：`reversal_of_event_id`
- 扩展值：`duration_seconds`、`quality_score`、`unit`
- 追溯：`dimensions_json`、`source_route_json`
- 日期：`business_date`、`received_at`、`update_at`

并新增：

- `performance_metric`
- `performance_employee_daily`
- `performance_anomaly`

## 调度口径

- 日报：每天配置时间生成昨天数据。
- 周报：每周一生成上周一至周日数据。
- 月报：每月 1 日生成上个自然月数据。
- 唯一键：`site_id + report_type + period_key`，重复调度不会重复生成。
- 补偿策略：队列晚于配置时间执行仍会补生成；通知失败由企业微信消息队列继续重试。
