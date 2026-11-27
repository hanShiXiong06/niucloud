# 本店 ERP 商品在商城改价被误拦截

## 原因

商城列表改价和 ERP 回传两个入口仍以 `is_proxy=1` 判断代理商品。该字段已经废弃，本店商品可能保留此标记，而当前库存对账按 `source` 判断来源，因此出现本店 ERP 商品被提示“代理商品不能改写来源站点 ERP”。

本次统一为商城已有的来源规则：商品必须属于当前站点，且 `source` 为空、`0`、`1` 或当前站点 ID，才视为本店商品。不批量修改旧标记，也不通过取消校验来放行跨站商品。

## 修复后的行为

- 本店 ERP 商品允许在商城列表修改基准售价，同时回写 ERP 的 `estimate_sale_price` 和按本站规则计算的 `retail_price`。
- ERP 发起改价时也使用同一判断，避免商城保存放行后又被 ERP 回传拦截。
- 外站来源商品即使 `is_proxy=0`，也不能回写来源站点 ERP。
- 保留事务、站点、设备串号、在库状态和营销活动校验。同步失败时，商城价格、ERP 价格和流水一起回滚。
- 资料完善入口及主子站资料跟随同步采用相同来源判断，不因废弃标记隐藏本店工单或漏掉已关联的子站商品。
- 不改变库存、ERP 成本或已有订单；本次不新增价格同步到子站的规则。

## 更新文件

以下文件均在 `niucloud/addon/phone_shop/` 下，须配套更新：

1. `app/support/GoodsSource.php`（新增，必须一起上传）
2. `app/service/core/goods/CoreGoodsPriceWriteService.php`
3. `app/listener/erp/ErpSalesPricing.php`
4. `app/service/core/goods/CoreGoodsMaterialService.php`
5. `app/service/admin/intake/DeviceIntakeService.php`

没有新增表、字段或升级 SQL，没有框架文件或前端文件修改，不需要因本修复重新构建前端。上述资料完善服务含同批未发布功能，应按对应插件版本整体更新，不能覆盖站点上其他较新的业务变更。

本仓库忽略 `niucloud/addon/phone_shop/`，只提交管理端代码不会交付这些文件。本次仅修改本地源码，未提交或部署生产环境。

## 验证

- `php tests/erp_mall_tier_pricing_smoke.php`：55 项，真实规则和监听器配合内存数据库替身，包含旧代理标记复现、基准价、重复保存、身份隔离和回滚。
- `HSX_REFERENCE_TEST_MYSQL_SOCKET=... php niucloud/addon/phone_shop/tests/erp_price_mysql.php`：40 项，真实列表改价服务、双向监听器、ORM、事务和 ERP 流水；配置及外部事件消费者使用替身。
- `HSX_REFERENCE_TEST_MYSQL_SOCKET=... php niucloud/addon/phone_shop/tests/erp_material_mysql.php`：28 项，资料状态及主子站同步，包括本店保留旧代理标记、外站没有代理标记的情况。
- `HSX_REFERENCE_TEST_MYSQL_SOCKET=... php niucloud/addon/phone_shop/tests/agent_reference_sync_mysql.php`：70 项，主子站参考资料同步回归。

MySQL 测试只允许 `/private/tmp/hsx-phone-ref-mysql.*` 中的独立临时实例，自动创建和删除测试库，不读取或修改业务数据库。

上线后用一件本店测试商品从列表修改基准价，核对 ERP 基准价、普通售价和改价流水；再确认子站副本不能改主站 ERP。本地测试没有核对本次报错商品的实际数据库记录，仍需商品 ID 或编辑页地址确认其实际关联。
