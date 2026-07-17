# 企业微信协同插件手动安装

## 接入模式

当前 MVP 使用“每个客户企业创建一个企业微信自建应用”的方式接入，需要真实企业微信组织、CorpID、应用 AgentId 和 Secret，并配置应用可见范围。它不是群机器人。

单个企业内部使用时，先按自建应用接入即可；未来如果要把本插件作为 SaaS 提供给大量不同企业统一授权，再升级为企业微信第三方服务商套件。业务插件发出的 `HsxBusinessTaskAssigned` 事件协议不需要因此修改。

1. 执行 `sql/install.sql`，将 `{{prefix}}` 替换为当前数据库表前缀。
2. 在插件管理中安装或刷新 `hsx_wecom`，确保菜单和每分钟消息补偿任务已注册。
3. 在“企业微信 / 协同配置”填写企业 ID、自建应用 AgentId、Secret，并选择网页、小程序或双入口。
4. 点击“测试连接”，成功后进入“员工绑定”，填写企业微信通讯录中的成员账号 UserID。
5. 在回收“我的任务”中分配任务；未绑定员工不会阻断业务，只会在消息日志中显示“已跳过”。

企业微信需要创建自建应用，并将需要接收任务的员工加入应用可见范围。插件不依赖群机器人，也不会在企业微信不可用时阻断回收业务。

## 任务打开方式

- `网页 + 小程序`：一张企业微信模板卡片同时提供两个入口，移动端优先打开后台管理小程序，电脑端可打开网页管理后台。
- `仅小程序`：只提供后台管理小程序入口。
- `仅网页`：只提供网页管理后台入口，兼容未关联小程序的企业。

网页管理端地址填写管理后台域名，例如 `https://example.com`，不要追加 `/site` 或 `/adminapp`。后台管理小程序只配置 AppID，不在本插件重复保存 AppSecret。小程序必须先在企业微信后台与当前自建应用完成关联，否则企业微信会拒绝小程序跳转消息。

业务插件通过 `HsxBusinessTaskAssigned` 事件提供与渠道无关的目标快照：

```json
{
  "target": {
    "plugin": "hsx_recycle",
    "route_key": "hsx_recycle.task.list",
    "params": { "stage": "check", "keyword": "123" },
    "web_path": "site/stat/task?stage=check&keyword=123",
    "miniapp_path": "addon/hsx_recycle/pages/task/index?stage=check&keyword=123"
  }
}
```

企业微信插件负责根据站点配置生成网页 URL 和小程序 `pagepath`，业务插件不保存 CorpID、AgentId、Secret 等企业微信参数。

回收任务入口可在 PC 端企业微信配置中切换：`订单详情（兼容）` 或 `我的待办列表`。待办列表模式要求线上管理小程序已经发布 `addon/hsx_recycle/pages/task/index`；旧包应先使用订单详情模式。

`source_plugin` 表示任务从哪个业务产生，`target.plugin` 表示应到哪个插件处理，两者可以不同。例如回收确认后由 ERP 接管付款时，来源仍是 `hsx_recycle`，目标必须是 `hsx_erp.payable.list`，网页进入 `/site/hsx_erp/payable`，小程序进入 `addon/hsx_erp/pages/payable/list`。企业微信插件会校验目标插件、路由键和小程序路径，拒绝发送互相矛盾的跳转目标。

## 新增数据表

- `wecom_staff_binding`：系统员工 UID 与企业微信成员 UserID 的站点级绑定。
- `wecom_message_log`：消息发件箱、发送结果、失败原因和重试状态。

字段明细以 `sql/install.sql` 为准。该 SQL 可独立手动执行，不依赖回收或 ERP 插件的数据表。

从 `1.0.0` 升级到 `1.0.1` 不新增数据库字段：网页地址、小程序 AppID 和打开方式保存在站点系统配置 JSON 中，原有 `admin_base_url` 会兼容读取为网页管理端地址。

## MVP 边界

- 已实现：任务到人、转交留痕、企业微信应用模板卡片、网页/小程序双入口、失败重试、员工绑定、消息日志。
- 已接入回收主流程：下单待签收、签收待质检、质检待定价、定价待确认、客户确认后的待打款或异常处理。
- 已接入 ERP 财务处理入口：回收待打款任务在 ERP 接管时直接进入对应来源单的 ERP 应付款列表。
- 暂未实现：外部联系人 SCRM、客户会话存档、统一客户画像、ERP 拍照任务分配、ERP 原生应收任务分配。这些能力后续继续复用相同业务事件协议扩展。
