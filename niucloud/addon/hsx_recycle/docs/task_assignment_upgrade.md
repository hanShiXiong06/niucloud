# 回收任务指定人员升级

现有站点升级到 `0.0.12` 时执行 `sql/update_0.0.12.sql`。全新安装不需要额外执行，字段已经写入 `sql/install.sql`。

## recycle_task_claim 新增字段

| 字段 | 类型 | 用途 |
| --- | --- | --- |
| `assigner_uid` | int | 最近分配人 UID |
| `assigner_name` | varchar(50) | 最近分配人名称快照 |
| `assignment_mode` | varchar(20) | `claim` 认领、`assign` 指定、`transfer` 转交 |
| `assigned_at` | int | 最近分配时间 |

## 新增表

`recycle_task_assignment_log` 保存每次认领、指定和转交的前后责任人、操作人及事件唯一标识。

企业微信插件未安装或未配置时，任务分配仍然正常落库。`HsxBusinessTaskAssigned` 事件没有监听器不会影响回收业务。
