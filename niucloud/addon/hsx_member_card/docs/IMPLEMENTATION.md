# 会员服务卡插件实施说明（v0.0.1）

本插件面向单门店贴膜会员卡场景，核心目标是“店员一次操作完成，财务自动留痕”。

## 已实现主链路

1. 卡种：售价、有限/不限次、立即/首次使用生效、永久/固定时长有效。
2. 客户：按姓名、手机号、会员号检索；支持现场快速创建会员。
3. 开卡：一次提交生成本地开卡单、会员卡、卡权益及 ERP 应收。
4. 收款：支持现场收款和 ERP 挂账；现场收款必须选择有效资金账户，可上传凭证。
5. 核销：完整手机号或后四位检索，必须人工核对购卡姓名，每次固定扣减 1 次。
6. 冲正：恢复次数和确认收入，原核销记录不删除。
7. 退款：整卡退款支持现场出款或生成 ERP 应付；所有资金动作保留原单和审计。
8. 绩效：记录开卡人、收款人、核销人、冲正人、退款付款人，使用业务级幂等事件对接绩效插件。

## ERP 公共契约

会员卡插件不直接引用 ERP 类或读写 ERP 财务表，通过以下事件契约接入：

- `ErpPartyResolveRequested`：会员解析/创建 ERP 往来主体。
- `ErpCapitalAccountOptionsRequested`：读取启用中的资金账户选项。
- `ErpFinanceSettlementRequested`：确认本插件归属的应收或应付。
- `ErpFinanceFactVoidRequested`：未结算财务事实作废，不删除原记录。

## 数据安全

- 所有业务表必须带 `site_id`。
- 开卡、核销、退款使用站点级 `request_id` 唯一索引。
- 核销使用数据库行锁，防止并发重复扣次。
- 财务契约验证 `origin_plugin=hsx_member_card`，插件不能处理其他业务的账。
- 敏感操作必须填写原因，采用冲正/作废方式保留审计链。

## 源码位置

- 插件发布源：`niucloud/addon/hsx_member_card`
- PC 开发源：`admin/src/addon/hsx_member_card`
- 移动管理端发布源：`niucloud/addon/hsx_member_card/site-uniapp`
- 移动管理端开发源：`site-uniapp/src/addon/hsx_member_card`（不会安装到面向顾客的 `uni-app`）
- 产品技术规划：`docs/贴膜会员卡插件_产品与技术实施规划_v0.0.1.md`

## 验收命令

```bash
php addon/hsx_member_card/tests/foundation_smoke.php
php addon/hsx_erp/tests/member_card_bridge_contract_smoke.php
php addon/hsx_erp/tests/finance_fact_listener_smoke.php

# 连接开发数据库，测试全流程并最终回滚
HSX_MEMBER_CARD_INTEGRATION=1 php addon/hsx_member_card/tests/full_flow_integration.php
```

前端分别执行：

```bash
cd admin && npm run build
cd site-uniapp && npm run build:h5
```

## v0.0.1 明确边界

- 单站点使用，不处理跨门店共享卡。
- 不接线上商城售卡和微信支付。
- 不做复杂授权人、验证码或实体卡介质。
- 核销对象固定为贴膜服务，一次核销 1 次。
