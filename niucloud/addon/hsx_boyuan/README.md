# 博远 ERP 对接插件

## PC：查询一张单的业务往来

「博远对接 → 业务往来」默认列出最近采购单。切到「付款凭证」按原凭证查看，一笔付款只占一行，点明细看各台设备分摊。也可按串号、本站单号、博远单号查设备。「按时间查接口」默认列最近实际调用，不必先选日期；展开即可对照入参、出参和时间。阅读只取本地保存的数据，不重发请求。菜单按岗位权限展示，不需授予编辑连接的权限。

「回收 → 日常业务 → 业务操作日志」集中展示回收、ERP 和博远的操作，可按系统筛选；单据有关联时可看原回收单。在 ERP 工作台点击「ERP 操作记录」，就在当前页展开 ERP 的列表；博远业务往来的「操作记录」仅显示博远动作。跨系统摘要包含来源、操作、经办、时间、结果及可核对的编号，不把接口原文、凭据或收款资料复制进数据库。后台代为执行标作系统，不冒用制单人。接口返回、付款登记和原采购核销不是同一件事。

部署顺序（在应用的 niucloud 目录）：先执行 `php addon/hsx_recycle/scripts/upgrade_business_audit.php --check`，核对后用 `--apply` 给既有回收日志表加字段和索引；发布三个插件对应的 PC 构建，执行 `php think menu:refresh --addon=hsx_recycle` 并为需要查看的岗位授予「业务操作日志」权限。重新加载驻留队列以启用新监听器。历史凭证和已留存的接口日志可以立即浏览；不会从旧数据补造当时未记录的操作。

本站结清、博远登记付款、原采购核销是三件事。旧单未留日志不代表没发过；查不到核销明细显示「待核对」，不应据此再次付款。安装过博远插件的站点若尚未出现「业务往来」菜单，可执行 `php think menu:refresh --addon=hsx_boyuan` 注册入口，无须卸载。博远的单据列表本身不改表；跨系统操作日志依赖上一步的回收日志表升级。

## 交付边界

**1.2.2 本机验收：** 从期初商品、供应商、仓库和库位开始，同一串号经过 ERP 采购入库、同行挂账销售出库，再分别登记收付。博远测试模式下，销售事实进入「博远对接 → 销售出库核对」。只有经办人确认后才创建博远批发单，再用原单独立查询核对结果。详细操作、复验命令和未开放的业务见 [入库到出库操作与验收](docs/WHOLESALE.md)。

**首次启用 ERP：** 从「ERP → 期初建账 → 从博远同步基础资料」进入，不需要先创建采购单。1.2.1 把下载博远档案和确认建立本站主档分为两步。只要本站还有未结清或状态异常的应收、应付，就不能建档。范围、权限和验收见 [期初建账使用说明](docs/OPENING.md)。不导入余额、实物库存或历史流水。

本插件连接已经确认的 ERP 业务事实，不代替博远的库存和记账服务。模拟、测试、正式共用事件接收、校验、任务、回执核验、异常补偿与手机工作台。

1.2.0 按博远《开放平台API开发文档 v1.5.4》增加采购入库半自动链路：ERP 入库后生成待核对任务，从博远已有资料选择关联，保存草稿不发送，明确提交后异步创建，再只读核验采购明细与入库串号。提供 PC 与手机核对页；后续自动模式复用同一链路，只作用于新捕获且关联齐全的任务。

2026-11-18 补充 v1.5.3 基础 API 后，增加独立的“本批次当前在库”异步只读核验及六环节状态汇总，并在 UAT 验证。重复请求复用任务，分段显示进度；证据过期后需重新查询。不新增表字段，不重建采购、不调整库存或款项。仍缺的财务契约见 [补充确认单](docs/CLOSURE_CONTRACT_GAPS.txt)，不能把采购或在库成功宣称为财务全闭环。

2026-11-18 进一步增加严格限定 UAT 的付款账务登记：只消费本站财务已确认付款，人工核对已有测试账户后冻结并异步单次登记，结果回写原采购页。已用原采购221707的1.00元测试付款生成博远付款单221709；没有再次扣本地账务。PC和手机分别提供紧凑核对弹层与显式实验开关。

本轮只在独立 UAT 联调，不切换生产，不调用统一支付、真实退款或银行转账。**付款单号、金额等回执已核对一致，这笔付款登记即为经办已办：无需再点确认，不发待办提醒。** 若结果不明或回执不一致，财务核查原单，不能重复登记。博远内部如何把付款核销到某笔采购，不属于经办人的步骤；没有分配明细时，不展示“原采购已核销”。分次付款按各笔自己的回执判断，一笔已登记不等于整张采购已付清。销售和退货未作本轮真实验收。详细操作及更新范围见 [采购入库 Beta 说明](docs/PURCHASE_BETA.txt)。

基础资料配置、同步方向、UAT结果及现存边界见 [基础资料同步](docs/BASIC_DATA.md)。

1.1.1升级手机工作台：资料归属标识、四类关联管理、手机号候选选择、批量处理已有任务、自动更新逐笔结果、提醒状态及未保存修改保护。当前批量入口是已有任务的人工继续/核验，不更改原有事件接收策略，也不等于已实现全站旧数据停用或新的博远单据接口。

详细参数与回执见 [连接适配契约](docs/CONTRACT.md)，本轮检查证据与验收步骤见 [本地隔离验收记录](docs/LOCAL_ACCEPTANCE.md)。

## 三种模式

| 模式 | 行为 | 完成状态 |
| --- | --- | --- |
| mock 模拟 | 后台异步使用持久化模拟服务，支持成功、拒绝、响应丢失、部分成功和异常回执 | simulated 模拟完成，不代表博远成功 |
| test 测试 | 向授权测试账套发送实际请求，按真实回执逐行核验 | succeeded 已同步，仅属于测试账套 |
| production 正式 | 向已确认正式账套发送实际请求 | succeeded 已同步，属于生产业务 |

环境独立保存连接身份、授权和映射，任务固定原环境及连接。切换不复制或重放任务。暂停不删除队列，也不能撤回已发送请求。安装后默认不接收、不投递。

关闭财务同步会保留并停止尚未发出的财务任务，仍允许人工发起只读核验；已发出的请求无法通过开关撤回。

## 业务范围

开放平台 1.2.0 Beta 采购入库支持买断设备、一机一行、单串号、同一任务一个博远仓库，`cash_term=0`，不自动新建供应商。另有默认关闭、精确 UAT 限定的付款登记实验，不在正式环境开放，不等于指定核销或资金转账。以下完整领域划分仍是原模拟/桥接契约及后续路线，不能当作这些开放平台接口已经完成真实验收。

- 第一期：采购入库、采购退货、零售销售及退货、批发销售及退货。
- 第二期独立开关：每机应付、每销售单应收、查询当前余额、单笔和合并收付款。多个独立真实转账仍分别登记。只记账，不调用银行转账。
- 往来单位固定对应优先，客户按手机号匹配及获准新建；供应商默认只匹配，缺档人工对应。不按同名合并。
- 历史机器没有本连接的新采购事实时，转明确的人工待办，不把旧机器自动纳入新流程。
- 代卖、折账、历史导入或数据清空不做。标品、多数量商品不冒充单台串号设备。
- 已生效原单撤销、特殊退款账目、跨多张博远原单的复杂退货保留人工核对，不自动删单或反向造库存。
- 原业务单已自动记资金或资金效果不明确时阻断重复记款，要求核对关联，不默认再收付一次。
- 商城退款成功不代表收到实物；仅在实物收回入口生成对应回库事实。

## 主要组成

- `app/gateway`：真实 HTTPS 连接和隔离模拟服务。
- `app/service`：配置、任务、映射、结果关联、通知和权限。
- `app/adminapi`：站点员工接口，区分查看、连接管理、对应资料及异常处理权限。
- `site-uniapp`：独立手机管理端交付镜像。实际开发源码在仓库 `site-uniapp/src/addon/hsx_boyuan`。
- `tests`：规则测试、真实 ERP 服务的隔离数据库测试、可交互验收和浏览器检查。

## 安装与数据库

依赖已有 `hsx_erp`。通过 NiuCloud 正常插件安装流程执行 `sql/install.sql`、菜单和计划任务安装，不手工向正式库试跑 SQL。

现有业务表：任务 `hsx_boyuan_task`、资料对应 `hsx_boyuan_mapping`、外部单及行关联 `hsx_boyuan_binding`、隔离模拟记录 `hsx_boyuan_mock_record`。均带实际系统表前缀。连接配置及运行心跳复用现有 `sys_config`。1.2.0 的操作及错误日志统一使用框架 `Log`，不再读写或新建 `hsx_boyuan_log`；既有历史日志表和记录不删除。业务审计继续走 PHP Log，不建数据库日志表。

### 两个插件联调：看 ERP 原单，以及博远实际收到请求后的返回

1. PC 后台 → 博远对接 → 连接设置 → **接口调试**，检查站点、环境和账套，点「开启 15 分钟」。仅有连接管理权限的员工可开启，不用暂停业务，不要打开全局 APP_DEBUG。
2. 排查连接可点「重新核验账套」（只读）；排查已有采购或销售可在原单核对。不要为了产生一条日志重复提交建单、出库或付款。只打开已缓存的资料、没有触发接口时，不会有 HTTP 请求日志。
3. 「如何查看这次日志」里可复制本站本轮专属命令，在服务端项目的 `niucloud` 目录执行。日志前缀是 `hsx_boyuan trace`；默认后台在 `runtime/adminapi/log/YYYYMM/DD.log`，队列在 `runtime/log/YYYYMM/DD.log`。若自定义了框架日志通道或多机运行，查实际的日志目录和对应工作节点；通道需允许 info。调试不会新建日志表。
4. 先用原单号（`business_no`）或任务 ID 找 `trace_id`：采购、销售按任务贯穿请求及结果，基础资料按同步任务贯穿多页。一次 HTTP 调用的准备入参在 `stage=request`，返回在同一 `call_id` 的 `stage=result`，附接口、HTTP 状态和耗时。ERP 交给插件的事件在 `stage=local`：`event_id` 对应原 ERP 事件，`event_input` 是交接的数据样本，`listener_result` 是监听器返回。再看 `stage=task` 的业务状态及外部单号，才能判断回执是否被本地采纳。关闭后的后续业务仍可按相同任务 `trace_id` 找常规日志。
5. `blocked_before_send` / `delivery=not_sent` 表示本次准备或网络前置检查被拦截。`no_confirmed_response` 或只有 request 没有 result **不能**判定博远没建单。`response_received` 仅表示收到了 HTTP 回应；再看博远 `return_code`、业务单号及本地核验结果。

只记录开启后的脱敏参数样本：显示分类/仓库/制单人编号、单号、金额等业务字段；隐藏密钥、签名、手机号（含助记码）、账户隐私，串号仅留末四位。自由文本只取已知错误提示，长明细每个数组最多 5 项并标注 `_omitted`。这些限制只作用于日志副本。停止后可继续用原命令检索；不会补录历史，也不会改变库存、收付款或自动重试策略。

**1.3.0 结构变更**：十类档案使用十张 `hsx_boyuan_md_*` 专表，分页任务仍用 `hsx_boyuan_reference_sync`；新安装为 15 张插件表。升级时逐条核对档案并保留旧总表副本，不动采购/销售/收付款单及关联。必须运行插件升级，不能只覆盖后端文件。PC 入口：博远对接 → 基础资料，依赖 `hsx_components` 0.1.1 的桌面目录组件和重编的管理端资源。权限由管理员按需授予。详见 [桌面资料目录与升级](docs/PC-CATALOG.md)。

1.1.1不新增表或字段。`app/upgrade/v111/Upgrade.php` 仅刷新权限菜单，新增“批量处理同步任务”“查看批次结果”和“按手机号匹配人员”权限，仍需管理员按需授权；批量处理员工需同时有批次结果查看权。后端与手机源码镜像需一起更新并重新编译，不改核心框架。

手机端把 `site-uniapp` 内源码同步到移动管理端对应插件目录，并把该目录的 `pages.json` 合并进应用 `subPackages`。当前仓库已注册页面 `/addon/hsx_boyuan/pages/workbench/index`。发布需重新编译手机管理端，不应只上传后端后就声称页面已经上线。

`app/dict/adminapp` 注册手机入口；`app/dict/menu/site.php` 注册权限节点及 PC 采购核对页。异常员工需获相应权限。API 未挂原始请求体审计中间件，防止凭据被记入通用日志；插件通过框架 Log 记录必要脱敏操作编号。

## ERP 与业务插件的必要更新

本次没有修改 `core/`、框架 `app/` 或 `vendor/`。以下均为业务插件中的通用事实扩展，不包含博远专用字段或分支：

| 文件 | 原因 |
| --- | --- |
| ERP `ErpIntegrationDocumentService` | 在业务事务内冻结整单及设备行快照，并记录原单变更 |
| ERP `ErpPurchaseService`、`ErpSaleService` | 确认采购和销售后保存通用出站事实 |
| ERP `ErpPurchaseExtensionService`、PC/手机采购详情 | 1.2.0 通用可选事件提供采购同步入口；ERP 不硬编码博远路由 |
| ERP `ErpPurchaseReturnService`、`ErpSaleReturnService` | 确认实物退货后保存原单和行关系 |
| ERP `ErpExternalSaleRecordedService` | 商城销售记入 ERP 后保存统一销售事实 |
| ERP `ErpFinanceService` | 结算事件补全采购行、销售单及资金方式引用 |
| ERP `ErpPhysicalReturnReceived` 与 `app/event.php` | 接收经过原业务校验的实物收回事实 |
| 商城 `CoreOrderDeviceReturnService` | 实际收回设备时通知 ERP，绝不在仅退款时通知回库 |

上述 ERP 更新必须与插件一同交付，否则不能保证自动接收完整业务。已有发件箱定时补偿负责投递通用事件；博远计划任务每分钟异步处理一次。任务投递与远端写入不放在采购或销售的数据库事务中。

## 本地验证

在 `niucloud` 后端目录的上一级仓库根目录运行：

```bash
php -d error_reporting=22527 niucloud/addon/hsx_boyuan/tests/unit.php
php niucloud/addon/hsx_boyuan/tests/purchase-gateway.php
php niucloud/addon/hsx_boyuan/tests/purchase-sync.php
php -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,dns_get_record,gethostbyname,gethostbynamel niucloud/addon/hsx_boyuan/tests/purchase-integration.php --isolated-local-mysql
php -d error_reporting=22527 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,stream_socket_client niucloud/addon/hsx_boyuan/tests/integration.php --isolated-local-mysql
php -d error_reporting=22527 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,stream_socket_client niucloud/addon/hsx_boyuan/tests/references.php --isolated-local-mysql
php -d error_reporting=22527 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,stream_socket_client niucloud/addon/hsx_boyuan/tests/ux.php --isolated-local-mysql
```

数据库测试只允许 localhost MySQL，创建随机 `hsx_boyuan_test_` 库，只复制必要表结构，不复制客户数据，正常及断言失败都会清理该测试库。启动中断仍需按准确库名核对清理，不能批量删除其他数据库。测试读取本机项目配置，不输出数据库密码。

本地可交互验收：

```bash
export HSX_BOYUAN_ACCEPTANCE_STATE="$(php -d error_reporting=22527 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,stream_socket_client niucloud/addon/hsx_boyuan/tests/acceptance/setup.php --isolated-local-mysql)"
export BOYUAN_PHP="$(command -v php)"
node niucloud/addon/hsx_boyuan/tests/acceptance/server.mjs
```

打开 `http://127.0.0.1:5199/`。界面使用实际手机业务页面及真实插件控制器、任务和数据库；仅将原生 UI 基础控件适配为浏览器控件。它不是生产鉴权或小程序真机验收的替代。内置测试采购使用真实 ERP 服务，后台独立进程异步处理。所有 HTTP 外调函数禁用，仅监听本机。演示界面不要填真实授权。

UAT档案验收为单独受限模式：`HSX_BOYUAN_ACCEPTANCE_UAT_READONLY=1`。只接受用户确认的UAT地址、`openapi_v153`协议、业务接收关闭和投递暂停。禁止创建模拟业务单据；仅允许白名单基础资料读取。不要把这种隔离验收代理当作正式登录认证。结束后可使用 `tests/acceptance/cleanup.php --drop-isolated-database --state-file=私有状态文件` 清理对应的随机测试库，不能批量删库。

浏览器回归使用 Playwright 和本机 Chrome，`BOYUAN_PLAYWRIGHT_MODULE` 可指向已有 Playwright 模块，未设置时从环境模块查找：

```bash
node niucloud/addon/hsx_boyuan/tests/ui-smoke.mjs
```

## 真实联调与启用

1. 博远确认实际路径、Bearer 授权方式或提供所需签名协议、目标账套、六种业务类型、金额与税口径、保存审核生效步骤及查单规则。
2. 在独立 SaaS 测试站点安装，填测试连接，保持暂停。先保存再执行只读账套核验。档案读取需回传授权实际账套 `data.account_id`，不能仅凭 HTTP 200 开放写入。
3. 对应测试商品、仓库、往来单位、业务员和收付方式；启用时重新输入目标账套确认。分别完成六类单据、超时、重复、部分成功与人工补偿联调。
4. 财务单独开启和验收，重点核对自然往来、资金副作用、合并分配、余额版本及退货后款项，不能重复造账。
5. 正式站点使用另一份授权、连接及映射，登记真实测试验收记录编号，再确认目标账套启用。只接收启用后的新流程业务，不迁移旧任务。

不能用模拟通过、网页可打开、只读核验通过或“有一个单号”代替真实生产验收。真实消息通知还需验证企业微信插件、员工身份及实际收到消息，队列接收不等于送达。

## 暂停与回退

先暂停外部投递，再处理在途请求和结果未知任务。不得因接口超时重复录采购、销售或付款。修复明确无副作用的拒绝使用带前驱引用的新操作编号；结果未知或部分成功只查询核验。

停用、卸载不删除任务、映射或审计记录；卸载仅移除计划任务。已在博远生效的业务需按原单退货或双方确认的人工冲销流程处理，回退代码不等于撤销远端单据。未获得授权，不向正式账套试写。
