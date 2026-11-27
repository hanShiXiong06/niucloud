# UniApp 公共组件

移动端公共层基于 Vue 3、UniApp 与项目已经声明的 uview-plus 构建。

```ts
import {
    HsxAdaptivePage,
    HsxButton,
    HsxCascader,
    HsxChart,
    HsxChartCard,
    HsxFilterDrawer,
    HsxEntityPicker,
    HsxProductList,
    HsxDetail,
    HsxFilterToolbar,
    HsxForm,
    HsxSchemaForm,
    HsxGrid,
    HsxIcon,
    HsxMediaCard,
    HsxProgress,
    HsxResponsiveGrid,
    HsxSplitPane,
    HsxStack,
    HsxText,
    HsxTitle,
    HsxPageList,
    HsxPageHeader,
    HsxPopup,
    HsxSearchBar,
    HsxSelect,
    HsxSwipeActions,
    HsxThemeProvider,
    HsxUpload,
    createMobileLineChart,
    useAdaptiveLayout,
    usePaging,
    useRequest
} from '@/addon/hsx_components'
```

第一版提供：

- `HsxButton`：防连点、异步操作自动 loading。
- `HsxSelect`：可检索、可清空的统一移动端选择弹层，避免原生 Picker 被父弹窗遮挡。
- `HsxCascader`：全宽逐级钻取、本地/异步数据和编辑回显；长机型名称不再挤进多列滚轮。
- `HsxPopup`：底部/中间/侧边弹层和真正的移动端全屏模式；统一底部安全区，`bodyScroll=false` 可承载 `z-paging`，宽屏默认按内容收紧高度。
- `HsxForm/HsxSchemaForm`：同一内核的移动端 JSON Schema 表单与强类型 TSX 入口；手机默认标签上置，宽屏自动左置。
- `HsxPageList`：基于 `z-paging@2.8.7` 的下拉刷新、触底分页、空状态和错误重试。
- `HsxPageHeader/HsxSearchBar`：统一安全区、小程序胶囊避让、自定义插槽和关键词检索；手机与宽屏自动切换搜索布局。
- `HsxFilterToolbar/HsxFilterDrawer`：统一商品端横向快捷筛选与管理端高级筛选；手机底部弹层、iPad/折叠屏右侧抽屉。
- `HsxUpload`：多文件、上传进度、后端返回适配和失败重试。
- `HsxIcon`：统一 uview-plus 图标、框架字体图标和图片图标。
- `HsxChart`、`HsxChartCard`：统一 uCharts 多端渲染、尺寸、空态、Loading、交互和明暗主题。
- `HsxAdaptivePage`、`HsxResponsiveGrid`、`HsxSplitPane`：统一手机、横屏、折叠屏展开和分屏布局。
- `HsxCheckbox`、`HsxSwitch`：响应式字号、图标尺寸、间距与分栏，并支持业务确认协议。
- `HsxThemeProvider`：统一 light/dark 语义色和随窗口变化的排版、控件、卡片令牌。
- `HsxCard`、`HsxMediaCard`、`HsxEmpty`：统一普通卡片、带图片加载失败兜底的图文商品卡片与空状态。
- `HsxSwipeActions`、`HsxActionBar`：统一左滑快捷操作与安全区底部操作栏。
- `HsxEntityPicker`：管理员、用户、主理人、门店等远程分页选择；手机底部弹层，宽屏右侧抽屉。
- `HsxProductList`：基于共享分页桥接直接组合 `z-paging/HsxMediaCard/uview swipe`，统一商品分页、图文卡片和左滑动作；小程序不经过多层插槽转发。
- `HsxComponentCatalog`：把全部组件按类别集中展示，支持关键词检索与精确选择；业务演示页监听 `select` 后跳到对应组件区块。
- `HsxDetail`：Schema 阅读态、金额、状态、图片、链接、复制与脱敏，按窗口宽度自动分栏。
- `HsxText`、`HsxTitle`、`HsxGrid`、`HsxStack`、`HsxList`、`HsxOverflow`：排版与布局。
- `HsxProgress`、`HsxMotion`：统一进度语义与克制动效。
- `HsxBlockRenderer`：白名单低代码布局，禁止执行任意字符串脚本。
- `useAdaptiveLayout`、`useRequest`、`usePaging`、`useStorage`、`useCountDown`、`useToast`、`useModal`、`useFeedback`、`useHaptics`。

折叠屏适配按“当前 App 窗口”而不是设备型号判断。默认宽度等级为：`compact < 600px`、
`medium 600～839px`、`expanded 840～1199px`、`large 1200～1599px` 和
`extra-large >= 1600px`。页面布局使用逻辑 `px + flex/grid`，不要在大屏上继续等比例放大所有 `rpx`。

移动端图表内核来自 Apache 2.0 开源的 `qiun-data-charts/uCharts`，源码与许可证保留在
`vendor/qiun-data-charts`。业务插件只调用 `HsxChart` 或图表配置助手，不再复制第三方组件。

`HsxPageList` 由 `z-paging` 管理分页生命周期，业务仍只需要实现
`request({ page, limit, ...query })`。独立逻辑场景仍可使用 `usePaging`。

项目依赖必须固定为 `z-paging@2.8.7`。`2.8.8` 的 npm 发布包缺少内部刷新组件，当前
工程会构建失败，因此在上游发布修复前不要自动升级。

为保证微信小程序兼容，移动端组件采用明确的属性白名单，不使用 Web 端的整包 `v-bind`
透传。新增 Schema 属性时，应先在 H5 和微信小程序各执行一次真实构建。

`HsxForm` 已与平台端统一支持动态显示、禁用、必填、动态 Props/Options、字段权限、
依赖驱动的异步选项和嵌套字段路径。移动端业务自定义字段优先使用 `slot`，保证小程序编译稳定。

后台与 UniApp 已启用 Vue TSX 编译链。Template 与 TSX 只能调用同一组件实现，不能复制两套组件。
完整 UI/UX 和交互规范见 `docs/DESIGN_SYSTEM.md`，代码组织见 `docs/TSX_GUIDE.md`。
