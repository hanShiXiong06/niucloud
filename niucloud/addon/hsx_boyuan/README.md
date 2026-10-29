# 博远 ERP 对接插件

## 交付边界

本插件连接已经确认的 ERP 业务事实，不代替博远的库存和记账服务。模拟、测试、正式共用事件接收、校验、任务、回执核验、异常补偿与手机工作台。

当前已提供本地实现和隔离验收环境。**没有取得博远实际接口文档、测试授权或正式授权，因此没有完成博远真实联调或生产验收。** 当前 HTTP 适配器实现的是双方待确认的 1.0 建议契约，不宣称博远现有接口天然支持该格式。实际协议如有差异，仅在插件网关适配，不让页面猜返回值。

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

新增五张插件表：任务 `hsx_boyuan_task`、资料对应 `hsx_boyuan_mapping`、外部单及行关联 `hsx_boyuan_binding`、操作记录 `hsx_boyuan_log`、隔离模拟记录 `hsx_boyuan_mock_record`。均带实际系统表前缀。连接配置及运行心跳复用现有 `sys_config`。**不新增核心框架表或改写历史业务。**

手机端把 `site-uniapp` 内源码同步到移动管理端对应插件目录，并把该目录的 `pages.json` 合并进应用 `subPackages`。当前仓库已注册页面 `/addon/hsx_boyuan/pages/workbench/index`。发布需重新编译手机管理端，不应只上传后端后就声称页面已经上线。

`app/dict/adminapp` 注册手机入口；`app/dict/menu/site.php` 注册权限节点，PC 不新增一套桌面业务页面。异常员工需获相应权限。API 未挂原始请求体审计中间件，防止凭据被记入通用日志；插件自身记录脱敏操作审计。

## ERP 与业务插件的必要更新

本次没有修改 `core/`、框架 `app/` 或 `vendor/`。以下均为业务插件中的通用事实扩展，不包含博远专用字段或分支：

| 文件 | 原因 |
| --- | --- |
| ERP `ErpIntegrationDocumentService` | 在业务事务内冻结整单及设备行快照，并记录原单变更 |
| ERP `ErpPurchaseService`、`ErpSaleService` | 确认采购和销售后保存通用出站事实 |
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
php -d error_reporting=22527 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,stream_socket_client niucloud/addon/hsx_boyuan/tests/integration.php --isolated-local-mysql
```

数据库测试只允许 localhost MySQL，创建随机 `hsx_boyuan_test_` 库，只复制必要表结构，不复制客户数据，正常及断言失败都会清理该测试库。启动中断仍需按准确库名核对清理，不能批量删除其他数据库。测试读取本机项目配置，不输出数据库密码。

本地可交互验收：

```bash
export HSX_BOYUAN_ACCEPTANCE_STATE="$(php -d error_reporting=22527 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,stream_socket_client niucloud/addon/hsx_boyuan/tests/acceptance/setup.php --isolated-local-mysql)"
export BOYUAN_PHP="$(command -v php)"
node niucloud/addon/hsx_boyuan/tests/acceptance/server.mjs
```

打开 `http://127.0.0.1:5199/`。界面使用实际手机业务页面及真实插件控制器、任务和数据库；仅将原生 UI 基础控件适配为浏览器控件。它不是生产鉴权或小程序真机验收的替代。内置测试采购使用真实 ERP 服务，后台独立进程异步处理。所有 HTTP 外调函数禁用，仅监听本机。演示界面不要填真实授权。

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
