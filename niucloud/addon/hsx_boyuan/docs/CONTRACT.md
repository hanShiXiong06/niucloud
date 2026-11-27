# 连接适配契约 1.0

本文件是原模拟器/自定义桥接服务的内部测试契约，不要求博远按本文件开发。实际开放平台基础资料已经按其 v1.5.3 文档适配，见 `BASIC_DATA.md`；新的单据接口以博远后续正式文档为准。

本文件描述插件已经实现的连接协议，不代表博远现有 API 已经采用该协议。联调前需确认差异，不能填一个未知地址就开启生产。凭据不放入示例或仓库。

## 外部调用

| 用途 | 默认相对路径 | 请求 |
| --- | --- | --- |
| 基础档案及账套身份 | GET /reference-data | resource、keyword、cursor、limit |
| 往来单位查找及获准建档 | POST /parties/resolve | 统一写入外层，operation_type=party_resolve |
| 六类业务 | POST /documents | purchase_inbound、purchase_return、retail_sale、retail_return、wholesale_sale、wholesale_return |
| 原操作查单 | GET /operations/{operation_id} | 原操作编号，返回原结果及当前状态 |
| 往来关联 | POST /financial-items/ensure | financial_item_ensure，不重复创建自然往来 |
| 当前往来余额 | POST /financial-items/search | financial_item_type、source_financial_item_ids，无业务操作编号 |
| 收付记账及分配 | POST /settlements | settlement_register，不发起转账 |

相对路径可在连接 profile.paths 配置。当前鉴权是 HTTPS Bearer Token，`X-Account-Id` 为已授权目标账套；写入附 `Idempotency-Key`，每次网络尝试有不同 `X-Request-Id`。鉴权必须在服务端绑定账套，不接受客户端任意切换。

统一写入外层：schema_version=1.0、operation_id、operation_type、business_time、business_date、supersedes_operation_id 可空、data。金额为两位十进制字符串，串号与ID为字符串，设备一机一行数量为整数1。

基础档案返回 `code=OK`、data.account_id、items、has_more、next_cursor。item 至少含 id、code、name、enabled；商品含 unit_code。分页游标原样传递。账套只读核验严格比对 account_id。测试与正式连接资料完全隔离。

## 回执门槛

写入与查单外层：request_id、code、message、data。data 必须包含与请求一致的 operation_id 和 operation_type，以及 operation_status。状态取 accepted、processing、effective、rejected、partial、unknown；effective 同时要求 code=OK。

六类单据返回 documents 数组，逐张包含 external_document_id、external_document_no、source_document_id、document_type、document_status、inventory_applied、funds_effect 和 lines。每条 lines 包含 source_line_id、device_ref、external_line_id、serial、warehouse_id、quantity、line_amount、inventory_effect、inventory_applied。逐行身份、金额、仓库、串号及实际库存方向全部一致，才完成；保存草稿、漏行或部分成功不完成。

财务关联返回 items：source_financial_item_id、external_financial_item_id、external_financial_item_no、external_party_id、financial_item_type、original_amount、current_amount、settled_amount、balance_amount、version、status、binding_mode、created。每机应付与整单应收保持不同粒度。版本为正整数。

结算先读取当前余额与版本，再传 allocations。每项包含原账目来源及外部编号、原单及行、amount、expected_balance_amount、expected_version。收付总额必须等于分配合计。返回 settlement.funds_recorded=true、allocations_applied=true，以及每项 source_financial_item_id、external_financial_item_id、external_allocation_id、applied_amount、settled_amount、balance_amount、status、version。回执不得暗示银行已执行转账。

## 异常与安全

- 网络超时、5xx、无效回执先按原操作查单，不能换编号重建。明确查不到时自动恢复只允许原操作编号和冻结报文；人工“查询”按钮仅查询，不触发补发。
- rejected 只有所有错误都明确 side_effect=none，且没有已创建对象时，才允许修复并引用 supersedes_operation_id。不同内容不得复用原操作编号。
- partial 保留实际返回的单及行，不能整单重试。人工记录保存说明及外部单号，状态仍是待核验，不自动变成成功。
- OPERATION_NOT_FOUND 必须是授权账套中的确定查无记录，不能把鉴权失败、临时异常或仍在途伪装成未找到。
- 博远业务单自动记入资金时须明确 funds_effect；已有资金或资金效果未知，本插件阻断重复登记并转人工核对。
- 真实连接禁止 HTTP、IP 地址、内网解析、重定向及携带凭据的 URL。TLS 验证开启，DNS 结果校验并固定连接地址。授权加密保存，普通接口和日志不返回密钥。

## 单据请求字段

六种库存业务共用外层，仅 `operation_type` 和原单引用不同。以下字段名称是本插件实际发送的名称，博远可按此实现，或提供等价接口后由网关转换。

| 字段 | 类型 | 约束及用途 |
| --- | --- | --- |
| schema_version | string | 固定 1.0 |
| operation_id | string | 1至128字符，无空白；账套内幂等，原内容原编号 |
| operation_type | string | 本文列出的六种库存类型之一 |
| business_time | string | ISO 8601，含时区；当前业务时区 Asia/Shanghai |
| business_date | string | YYYY-MM-DD，与上述业务时区一致 |
| supersedes_operation_id | string/null | 仅明确未产生副作用的拒绝修复时填写 |
| data.source_document_id | string | 来源单据稳定编号；不可只用可变展示单号匹配 |
| data.source_document_no | string | 展示和人工检索单号 |
| data.batch_ref | string | 本次确认的设备行集合身份；追加设备不是覆盖旧批次 |
| data.counterparty | object | source_party_ref、external_party_id、name、role；role 为 supplier/customer |
| data.operator | object | operator_ref、name；来源操作人用于追溯 |
| data.currency | string | 当前固定 CNY |
| data.total_amount | string | 非负两位小数，等于明细 line_amount 合计 |
| data.purchase_source | string | 仅采购入库：ordinary_customer/wholesale_supplier |
| data.salesperson | object | 销售及销售退货：external_staff_id、name |
| data.payment_method_code | string | 销售及销售退货的博远收银方式编码；不意味着已登记资金 |
| data.original_document | object | 退货必填：source_document_id、external_document_id、operation_type |
| data.reason | string | 退货原因，仅实物退货触发库存变化 |
| data.lines | array | 1至100行；一机一行，禁止同设备重复 |

每条 `lines`：

| 字段 | 类型 | 约束及用途 |
| --- | --- | --- |
| source_line_id、device_ref | string | 稳定的来源行及设备身份 |
| external_product_id、product_code | string | 已对应的博远商品ID与编码 |
| product_name | string | 来源型号名称，供核对 |
| attributes | array | key、label、value_code、value_text；规格/颜色不能仅有数值代码 |
| imei、sn | string/null | 至少一项有值，保留前导零，不能转数字 |
| warehouse_id | string | 博远仓库ID |
| location_id | string/null | 博远要求库位时必须先对应 |
| quantity | integer | 固定1 |
| unit_code | string | 博远真实单位编码，不能凭本地名称猜测 |
| unit_price、line_amount | string | 两位小数；单台设备两者相等 |
| external_inbound_document_id、external_inbound_line_id | string | 销售必填，引用已核验采购入库 |
| original_source_line_id、original_external_document_id、original_external_line_id | string | 退货必填，引用真正的原单和原行 |

库存写入成功示例，其他类型只改变业务方向及原单字段：

```json
{
  "request_id": "EXAMPLE_REQUEST_1",
  "code": "OK",
  "message": "已审核并完成库存处理",
  "data": {
    "operation_id": "EXAMPLE_OPERATION_1",
    "operation_type": "purchase_inbound",
    "operation_status": "effective",
    "documents": [{
      "external_document_id": "BY_DOC_1",
      "external_document_no": "CG202610280001",
      "source_document_id": "purchase:example",
      "document_type": "purchase_inbound",
      "document_status": "effective",
      "inventory_applied": true,
      "funds_effect": "none",
      "lines": [{
        "source_line_id": "purchase_line:example",
        "device_ref": "asset:example",
        "external_line_id": "BY_LINE_1",
        "serial": "EXAMPLE_SERIAL_1",
        "warehouse_id": "BY_WAREHOUSE_1",
        "quantity": 1,
        "line_amount": "2000.00",
        "inventory_effect": "in",
        "inventory_applied": true
      }]
    }],
    "errors": []
  }
}
```

本例不是真实博远单据。`funds_effect` 为 `none` 才允许后续独立收付款登记；自动记款或未知资金效果需另行明确，不能重复登记。

## 往来和结算请求字段

`party_resolve` 的 data 包含 source_party_ref、role、mode、name、mobile。客户为 match_or_create：先用已保存对应，再按手机号唯一匹配，不存在且允许时新建；无手机号按稳定来源编号新建，不按同名合并。供应商为 match_only，找不到转人工。回传 match_status=matched/created，party 包含 id、code、name、role、enabled。多候选不能任选一条作为成功。

`financial_item_ensure` 的 data.items 每项包含：

- source_financial_item_id、source_financial_item_no：原账稳定编号和展示编号。
- financial_item_type=payable/receivable、business_kind=purchase/sale、party_role、external_party_id。
- source_document_id、source_line_id、device_ref：应付按机器且后两项有值；销售应收按整单且后两项为 null。
- currency=CNY、amount：原始金额。
- source_documents：external_document_id、external_line_id，引用已生效业务单，不重复创建该单自然产生的往来。
- components：source_line_id、device_ref、amount，合计必须等于原账金额。

余额查询返回与上文财务关联相同的 items。每项 current_amount=settled_amount+balance_amount，version 必须为正整数；返回错账、漏项、重复项或错误代码都不得继续记款。

`settlement_register` 的 data：

| 字段 | 类型 | 约束及用途 |
| --- | --- | --- |
| source_settlement_id、source_settlement_no | string | 来源资金登记的稳定编号和展示编号 |
| action | string | 当前固定 register |
| external_settlement_id | null | 新登记；撤销及冲销不在本版自动处理范围 |
| direction | string | payment/receipt，分别为付款/收款 |
| registration_mode | string | single/merged，按分配条目数量标识 |
| external_party_id、party_role | string | 同一往来单位；不做客户供应商互抵 |
| capital_account_id、method_code | string | 博远资金账户和收付方式 |
| currency、amount | string | CNY，正金额，等于分配合计 |
| transaction_time、transaction_reference | string/null | 已发生款项的时间、外部凭证编号，不执行银行转账 |
| operator | object | operator_ref、name |
| voucher_refs | array | 当前为空，未实现附件上传 |
| allocations | array | 1至100项，每项 source_financial_item_id、external_financial_item_id、source_document_id、source_line_id、amount、expected_balance_amount、expected_version |
| remark | string | 登记说明 |

合并收款/付款保持每笔原账独立，只产生一笔资金登记并逐笔分配；多次真实转账不得虚构成一次。博远需原子校验余额及版本，余额或版本冲突应拒绝整次分配并声明是否存在副作用。成功回执逐项核对原账和外部账编号、分配编号、已应用金额、结后余额及递增版本，不能只返回一笔总额。

## 博远需确认

1. 上述七组接口路径及权限、鉴权机制、实际账套核验和测试环境。
2. 六类单据保存、审核及实际库存生效是否一步完成；异步时必须提供原操作查单。
3. 商品、仓库、业务员、往来单位和收付方式的真实编码规则；不存在或多候选时的明确错误。
4. 金额是否含税、是否含运费及舍入规则。本版只同步来源单据金额，未擅自添加税率或费用分摊。
5. 原单自动生成应收应付/资金的规则；关联现有自然往来，不能再造一笔同额往来。
6. 幂等保留期限、查询一致性、部分生效的逐行结果及人工冲销办法。

若实际服务不是上述契约，需要先完成网关适配及隔离回归，再开通实际写入。拿到真实返回结构前，不将建议契约称为博远官方接口。
