# ERP 自动任务责任人升级

## 升级方式

已安装站点执行 `sql/update_0.0.2.sql`，或执行项目的 `ErpSchema::migrate()`。新安装站点已包含在 `sql/install.sql`。

## 新增字段

`erp_asset`、`erp_payable`、`erp_receivable` 都增加以下责任人快照字段：

- `task_stage_key`
- `task_assignee_uid`
- `task_assignee_name`
- `task_assigner_uid`
- `task_assigner_name`
- `task_assigned_at`

并分别增加按站点、环节、责任人查询的索引。

## 分配规则

管理员在 ERP「业务规则 - 自动任务默认负责人」设置应付、应收、拍照、商城定价和资料上架负责人。系统优先使用默认负责人；未设置时选择首位具备对应动作权限的岗位员工；仅在没有岗位员工时由站点管理员兜底。

保存设置会补齐当前尚未分配的在途任务，不覆盖已有责任人。企业微信 UserID 仍在企业微信插件中绑定。
