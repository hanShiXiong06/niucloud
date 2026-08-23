# 公共组件迁移优先级

## 维护规则

- `niucloud/addon/hsx_components/admin` 是管理端组件维护源，发布或安装时同步到 `admin/src/addon/hsx_components`。
- 公共组件只负责稳定交互、视觉和数据契约，不直接请求 ERP、商城、回收或会员卡接口。
- 业务插件通过 Props、Events、Slots 和适配函数消费公共组件，不把业务枚举写回公共层。
- 新组件至少满足两个业务模块可复用，并提供独立示例和明确空态、加载态、错误态、禁用态。

## P0：优先迁移

### 页面骨架

统一使用 `HsxPage` 组织标题、说明、工具栏、正文宽度和响应式留白。先覆盖新页面，旧页面按业务迭代逐页迁移，禁止全仓机械替换。

### 弹窗

新增、编辑、确认类弹窗优先使用 `HsxDialog`。业务内容保留在插件内，通过默认插槽和 Footer 插槽组合，避免再次复制标题栏、正文滚动和底部按钮 CSS。

### 凭证上传

ERP、会员卡、回收和商城中的收款、付款、退款凭证逐步改用 `HsxVoucherUpload`。业务层只传标题、提示、数量和上传结果，不重复封装 `upload-image`。

### 反馈与危险操作

统一使用 `useFeedback` 和 `HsxActionBar`，禁止业务页面直接拼接另一套 MessageBox 文案和异常处理。

## P1：下一批公共能力

1. `HsxMoneyInput`：金额精度、元/分转换、负数规则、范围校验和格式化。
2. `HsxStatusTag`：状态字典、语义颜色、禁用/作废/异常状态及 Tooltip。
3. `HsxSelectTable`：远程分页、跨页选择、搜索、回显和已选摘要。
4. `HsxEditableTable`：行内编辑、单元格校验、变更集和批量保存。
5. `HsxImagePreviewGroup`：图片预览、旋转、下载权限和质检证据入口。

## 保留在业务插件的组件

- ERP 往来主体、仓库库位、设备目录型号等带业务接口和权限的选择器。
- 回收质检、回收定价、设备确认等领域表单。
- 商城商品、会员权益、渠道映射等领域组件。

这些组件应组合 `HsxDialog`、`HsxCascader`、`HsxTable`、`HsxVoucherUpload` 等公共内核，但不应直接搬进公共库。

## 当前迁移线索

- 凭证上传重复点：`hsx_erp/components/ErpFinanceVoucherUpload.vue`、`hsx_member_card/components/MemberCardVoucherUpload.vue` 及多个收付款页面。
- 弹窗重复点：ERP 财务、回收订单、商城订单与会员卡开卡页面。
- 实体选择重复点：ERP 往来主体、回收会员、商城会员和供应商选择场景。

迁移时一次只处理一个完整业务链，并保留原组件名作为薄适配层，避免插件升级或历史页面同时失效。
