# 管理端组件

## 引入方式

业务插件推荐从分层入口按需引入，避免仅使用表单时同时加载图表、AI 渲染和商品卡片：

```ts
import { HsxPage, HsxDialog, HsxActionBar } from '@/addon/hsx_components/core'
import { HsxVoucherUpload, HsxCascader } from '@/addon/hsx_components/forms'
import { ProTable, HsxDetail } from '@/addon/hsx_components/data'
```

旧页面仍可从聚合入口引入，现有调用保持兼容：

```ts
import {
    HsxCascader,
    HsxEntityPicker,
    HsxProductList,
    HsxDetail,
    HsxActionBar,
    HsxChart,
    HsxChartCard,
    HsxColumnSetting,
    HsxDatePicker,
    HsxDateRange,
    HsxDialog,
    HsxExport,
    HsxImport,
    HsxIcon,
    HsxGrid,
    HsxList,
    HsxMarkdownRenderer,
    HsxMotion,
    HsxOverflow,
    HsxPagination,
    HsxPage,
    HsxProgress,
    HsxSearchInput,
    HsxUpload,
    HsxVoucherUpload,
    HsxStack,
    HsxStatCard,
    HsxText,
    HsxTitle,
    ProDialogForm,
    ProForm,
    ProTable,
    QueryForm,
    useCrudPage,
    useFeedback,
    useAutoFollowScroll
} from '@/addon/hsx_components'
```

## 页面与弹窗基线

公共组件的收录边界和业务迁移顺序见插件目录 `docs/MIGRATION_PRIORITY.md`。

新页面优先使用 `HsxPage`，统一页面留白、标题层级、工具栏、小屏和 150% 系统缩放适配：

```vue
<HsxPage title="库存中心" subtitle="查看设备库存、状态与经营数据" content-width="wide">
    <template #extra>
        <HsxActionBar :actions="pageActions" />
    </template>
    <ProTable :columns="columns" :request="getPage" />
</HsxPage>
```

业务弹窗统一使用 `HsxDialog`。`size` 提供 `sm/md/lg/xl` 四档，支持副标题、局部加载、
主操作禁用和全屏切换：

```vue
<HsxDialog
    v-model="visible"
    title="确认收款"
    subtitle="确认后将写入资金流水"
    size="md"
    show-footer
    :body-loading="detailLoading"
    :confirm-loading="saving"
    :confirm-disabled="!canSubmit"
    @confirm="submit"
>
    <HsxVoucherUpload v-model="form.voucher" />
</HsxDialog>
```

如需全局注册，可在应用入口使用：

```ts
import { HsxComponents } from '@/addon/hsx_components'

app.use(HsxComponents, {
    permissionChecker: (permission) => true,
    formComponents: { ImeiInput, DeviceSkuSelector }
})
```

## Schema 示例

```ts
const formSchema = [
    { prop: 'name', label: '名称', component: 'input', required: true },
    { prop: 'mode', label: '模式', component: 'select', options: modeOptions },
    {
        prop: 'managerId',
        label: '主理人',
        component: 'select',
        optionsDependencies: ['mode'],
        optionsLoader: ({ dependencies }) => getManagers(dependencies.mode)
    },
    { prop: 'internalMargin', label: '内部毛利', component: 'input-number', permission: 'pricing.internal' }
]

const columns = [
    { type: 'index', label: '序号', width: 70 },
    { prop: 'name', label: '名称', search: true },
    { prop: 'status', label: '状态', search: { component: 'select', options: statusOptions } },
    { prop: 'actions', label: '操作', slot: 'actions', fixed: 'right' }
]
```

`ProTable` 的 `request` 接收 `{ page, limit, ...查询条件 }`。默认兼容 NiuCloud 常见的
`data.data + data.total`、`data.list + data.total`、`list + total` 三种返回结构，也可以通过
`responseAdapter` 自定义。

`HsxDialog` 默认支持拖拽，并提供全屏切换。全屏状态下自动关闭拖拽，退出全屏后恢复。

`HsxCascader` 支持本地树、逐级异步加载、远程搜索和编辑回显。品牌、系列、型号等接口
应由业务插件再次封装，不进入公共组件。

`ProTable` 默认组合 `HsxPagination` 和 `HsxColumnSetting`；列设置支持显示隐藏、左右固定、
调整顺序和 `storageKey` 本地记忆。`HsxImport` 与 `HsxExport` 可以独立使用，也可以放进
`toolbar` 插槽。

`ProForm` 与 `ProDialogForm` 支持动态 `visible/disabled/required/props/options/tip`、字段权限、
依赖驱动的异步选项，以及通过 `components + componentKey` 注册业务字段组件。

`HsxDatePicker` 同时处理单时间和区间；区间默认把起止值补齐为 `00:00:00` 与
`23:59:59`。`HsxDateRange` 保留为兼容入口。`HsxUpload` 通过 `uploader` 适配各业务上传接口，
并支持独立设置预览宽、高、圆角与圆形头像。`HsxIcon` 统一 Element、框架字体图标、图片和
业务图标组件。所有组件样式使用语义色变量并跟随 NiuCloud 暗黑模式。

`HsxChart` 统一 ECharts 生命周期、ResizeObserver、暗黑模式和空状态；简单图表使用
`createLineChartOption/createBarChartOption/createDonutChartOption/createSparklineOption`。
轻提示、重通知和危险确认统一从 `useFeedback` 调用，页面不直接依赖 Element Plus 消息 API。

排版、响应式网格、列表状态、溢出、进度和动效统一使用 `HsxText/HsxTitle/HsxGrid/HsxStack/
HsxList/HsxOverflow/HsxProgress/HsxMotion`。组件支持 Template 和 TSX 调用；`HsxStack` 本身以 TSX
实现，持续验证真实 TSX 构建链。完整规则见 `docs/DESIGN_SYSTEM.md` 与 `docs/TSX_GUIDE.md`。

业务页面优先从通用组合层开始：人员、管理员、主理人和门店选择使用 `HsxEntityPicker`；
商品货盘使用 `HsxProductList/HsxProductCard`；详情阅读态使用 `HsxDetail`；顶部或底部动作使用
`HsxActionBar`。这些组件内部继续复用树表选择器、响应式网格、按钮、状态标签等基础组件，
业务只提供请求、字段映射和动作配置。

流式会话、日志尾随等持续追加内容的页面使用 `useAutoFollowScroll`。它会合并高频滚动、
仅在用户停留于底部附近时自动跟随，用户向上阅读后不会被强制拉回。Markdown 输出统一使用
`HsxMarkdownRenderer`，默认关闭原始 HTML，并限制链接协议，业务页面不再重复维护解析与代码高亮。
复制到独立项目时需要同时安装 `markdown-it`、`highlight.js` 及 `@types/markdown-it`。

## 自动化验证

在 `admin` 目录执行：

```bash
npm run test:components
```

测试覆盖深度数据比较、Schema 条件和依赖解析、双端 v-model 防循环、日期跨度、移动端
按钮布局、`z-paging` 请求协议和移动上传结果适配。
