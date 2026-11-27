# ERP 上架与商城资料核对

## 操作流程

1. ERP 选好商城分类、设备图片和价格后推送。满足原有库存、交易校验即可上架，不用等待运营重录质检信息。
2. 商城自动带入可明确识别的容量、颜色、成色等级、电池健康度、保修到期日，保留原有质检报告和图片。
3. 新交接商品显示“待完善”。运营在商品列表点击“完善资料”，核对自动填入的数据；数据正确可直接点击“核对完成”。暂存显示“完善中”，完成显示“已完善”。
4. 货源列表仍可按资料进度筛选，查看处理人、时间和记录。商品售卖状态与资料进度独立，完善资料不改变价格、库存或财务。

## 主子站规则

- 仅启用主子站关系的站点参与跟随。ERP 主站建品在属性、图片、质检信息写完后再铺货，不发送中间状态。
- 分类和参数模板使用子站自己的对应 ID。缺分类是否自动建立，沿用主子站基础资料同步设置。
- 子站商品列表展示“主站 · 待完善/完善中/已完善”，不复制运营工单，不开放主站货源编辑权限。
- 主站运营保存资料后，更新已有子站代理商品的展示属性；不覆盖子站售价、库存和上下架状态。已售商品不会因此恢复可售。
- 单个子站首次铺货失败不会阻断其他子站或主站建品。ERP 返回警告，商城货源列表可查看上架时的失败原因；修复原因后使用既有商品同步功能重试。
- 主站资料保存后若子站同步失败，界面明确提示主站已保存、子站尚未完成；可在原因修复后重新保存资料重试。状态标签表示主站核对进度，不是每个子站的同步回执。

## 数据边界

- 只读取明确的质检字段及选项名称。不会把选项索引当颜色，不以电池循环次数替代健康度，不依据瑕疵猜等级。
- 空的 ERP 属性允许用质检值补齐；已有明确值及本次人工覆盖优先。冲突、未知和无法识别的数据保留待核对，不自动编造。
- 容量仅在当前分类规格中唯一匹配时统一，例如 `256GB` 对应 `256G`。不会自行创建规格、等级、参数模板。
- 参数模板不自动猜选。通用参数仍在原资料弹窗维护，已配置选项继续使用商城字典。
- 重复 ERP 推送不会重建商品、覆盖人工修正或清空已经完成的核对记录。
- 商品列表按页批量读取资料状态，只提取 JSON 中的任务信息，不逐条读取完整质检快照。

## 部署

本次没有新增表、字段或 SQL，没有框架文件修改，不进行历史商品批量回填。沿用当前插件已有的货源表、商品字段和主子站关系结构。

后端文件，位于 `niucloud/addon/phone_shop/`：

- `app/support/InspectionGoodsAttributes.php`（新增）
- `app/support/GoodsSource.php`（新增，统一识别本店与外站来源，不依赖废弃的代理标记）
- `app/support/IntakeMaterialAttributes.php`
- `app/support/IntakeMaterialTask.php`
- `app/service/core/intake/CoreListingMappingService.php`
- `app/service/core/goods/CoreGoodsMaterialService.php`（新增）
- `app/service/core/agent/GoodsDistributionService.php`
- `app/service/admin/intake/DeviceIntakeService.php`
- `app/service/admin/goods/GoodsService.php`
- `app/listener/erp/ErpPublishListing.php`

前端以下三个文件的源码与插件发布镜像已同步：

- `views/goods/list.vue`
- `views/intake/list.vue`
- `views/intake/components/IntakeBuildDialog.vue`

源码根目录为 `admin/src/addon/phone_shop/`，镜像为 `niucloud/addon/phone_shop/admin/`。线上需按现有发布流程更新管理端产物及上述后端文件。本次未做完整构建或线上部署。

注意：本仓库的 `niucloud/addon/phone_shop/` 被 Git 忽略。普通 `git status` 或仅提交前端不能覆盖这部分后端，需要按插件交付方式一起发布。之前主子站基础资料同步功能的升级 SQL 不属于本次新增内容，也不能被本说明代替。

## 验收

- `php niucloud/addon/phone_shop/tests/inspection_goods_mapping_smoke.php`：21 项质检映射与状态规则。
- `php tests/erp_mall_basic_listing_smoke.php`：76 项建品、安全校验、重复事件与资料流转。
- `php tests/phone_shop_intake_material_smoke.php`：35 项字典、参数及站点范围校验（同时执行上述 76 项）。
- `HSX_REFERENCE_TEST_MYSQL_SOCKET=... php niucloud/addon/phone_shop/tests/erp_material_mysql.php`：28 项真实 ORM、事务、JSON、子站传播校验。只允许隔离临时 MySQL，自动创建和删除测试库；通用建品入口和请求上下文使用替身。
- `node tests/phone_shop_intake_build_ui_smoke.cjs`：前端定向编译、镜像一致性与 24 组交互校验。
- `node tests/phone_shop_material_browser.cjs`：真实组件、模拟接口的保存/完成/重开/记录与桌面、390px 窄屏验收。可通过 `PLAYWRIGHT_MODULE`、`CHROME_PATH` 指定本地运行依赖。

仍需在部署环境以测试设备验证 ERP 页面推送、主子站列表和运营账号权限；本地自动化通过不代表已经更新生产站点。
