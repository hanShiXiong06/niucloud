# HSX 组件库路线图

## 收录边界

一个能力进入公共组件库前，需要同时满足：至少两个业务模块会复用、不包含具体业务接口、
可以通过 Props/Events/Slots 说明清楚、平台端或移动端能够独立演示。只服务手机交易领域的
能力放到业务组件层，底层组合公共组件。

## v0.1.0 已完成

- 两端独立公开出口、Schema 类型、Hooks、工具函数。
- 管理端 CRUD 主链：查询、表格、分页、新增、编辑、查看。
- 拖拽和全屏弹窗。
- 平台与移动端 `HsxCascader`：多级、本地/异步加载、远程搜索、编辑回显。
- 平台端搜索框、分页、自定义列、文件导入和本地/后端导出。
- 移动端表单、弹层、基于 `z-paging` 的分页列表、上传、卡片与空状态。
- 平台后台可交互开发文档与用户移动端预览。
- 后台、用户端 H5、微信小程序构建准入。
- 双端组件入参、出参、事件、插槽和 Hooks 调用手册。
- 双端 Schema 动态显示/禁用/必填、字段权限、动态属性、异步选项和嵌套路径。
- 平台 Schema 业务组件注册器，以及双端 v-model 防循环自动化测试。
- 平台 `HsxDateRange`、`HsxUpload`、`QueryForm`，以及移动端 `HsxUpload`。
- 双端 `HsxDetail`、`HsxEntityPicker`、`HsxProductList` 与配置驱动 `HsxActionBar` 通用业务组合层。
- 管理端 `HsxPage` 页面基线、商业化弹窗规格和 `HsxVoucherUpload` 业务凭证上传预设。
- 管理端 `core/forms/data/visual` 分层公开入口，业务插件可以按能力域引入。

## 下一批：通用基础层

### P0：直接提升开发速度

1. `HsxUpload` 增强：拖拽排序、暂停/取消以及分片上传适配器。
2. `QueryForm` 增强：可选 URL 查询参数同步。
3. `HsxDetail` 后续增强：质检图片标注、字段级审计说明和打印布局。
4. `EditableTable`：行内编辑、单元格校验、批量保存和变更集输出。
5. `SelectTable`：弹窗表格选择、远程分页、已选回显和跨页勾选。

### P1：复杂数据与统一体验

1. `AsyncTree` / `TreeSelect`：懒加载、远程搜索、权限树和半选策略。
2. `TransferTable`：大量数据穿梭、分页和已选缓存。
3. `MoneyInput`：分/元转换、精度、千分位和范围校验。
4. `StatusTag`：统一状态语义、颜色和业务字典适配器。
5. `ImagePreviewGroup`：多图、旋转、下载权限和质检标注入口。
6. `VirtualList`：大货盘、大型号库和长列表性能保护。

### P2：工程能力

- 扩大组件级单元测试覆盖，补充 H5/微信小程序自动交互与双端快照。
- 独立 Playground、示例代码复制、版本变更标识和废弃 API 提示。
- Schema 注册器继续增加字段适配器和废弃 API 提示。

## 业务组件层

业务组件建议放在手机供应链主应用的 `components/domain` 中：

- `DeviceModelCascader`：组合 `HsxCascader`，绑定品牌/系列/型号接口及热门型号搜索。
- `DeviceSkuSelector`：颜色、容量、版本、网络制式等 SKU 组合。
- `ImeiSnInput`：IMEI/SN 格式、扫码、重复校验和批量录入。
- `InspectionReportCard`：质检项、异常级别、图片证据和争议入口。
- `SellerTrustCard`：描述准确率、发货退货率、售后解决时长。
- `PricingPanel`：卖家最低到手价、主理人定价、佣金和预估利润。
- `CommissionContribution`：货主、主理人、销售渠道、平台贡献分佣展示。
- `OrderStatusTimeline`：出价、成交、发货、签收、售后、结算节点。
- `SettlementRiskCard`：T+1、80% 预付款、欠款、保证金和可提现余额。

业务组件可以有业务名称和接口，但仍应把选择、弹窗、表格、上传、金额等基础交互交给
公共组件，避免形成第二套 UI 基建。
