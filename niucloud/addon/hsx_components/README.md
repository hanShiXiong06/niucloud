# HSX 框架组件库

这是一个 NiuCloud `app` 类型的框架基础应用，也是业务插件与 NiuCloud 底层之间的公共层。
源码放在 `niucloud/addon/hsx_components`，版本统一由 `info.json` 管理，当前开发版本为 `0.1.0`。

## 三层关系

1. NiuCloud：登录、权限、菜单、请求、应用安装等底层能力。
2. hsx_components：Schema、公共组件、Hooks、指令、工具与开发文档。
3. 业务插件：手机货盘、交易、质检、资金、信用等具体业务。

业务插件只能从公开出口引用组件库：

```ts
import { HsxDialog, ProForm, ProTable, useCrudPage } from '@/addon/hsx_components'
```

## 当前能力

平台管理端：

- `HsxButton`、`HsxCheckbox`、`HsxSwitch`、`HsxInput`、`HsxSearchInput`、`HsxSelect`、`HsxCascader`
- `HsxDialog`、`HsxDrawer`、`HsxTable`、`HsxTag`、`HsxTimeline`
- `HsxPagination`、`HsxColumnSetting`、`HsxDatePicker`、`HsxDateRange`、`HsxUpload`、`HsxIcon`
- `HsxImport`、`HsxExport`，以及统一明暗主题变量
- `HsxChart`、`HsxChartCard`、`HsxStatCard`、`HsxBadge`、`HsxNoticeBubble`、白名单 `HsxBlockRenderer`
- `HsxText`、`HsxTitle`、`HsxGrid`、`HsxStack`、`HsxList`、`HsxOverflow`、`HsxProgress`、`HsxMotion`
- `HsxPage`、`HsxSearchPanel`、`HsxNotice`、`HsxFold`：页面骨架、可记忆排列的搜索区、分级提示与保留状态的折叠区
- `HsxEntityPicker`、`HsxProductCard`、`HsxProductList`、`HsxDetail`、`HsxActionBar` 通用业务组合层
- `ProForm`、`ProTable`、`ProDialogForm`、`QueryForm`
- `useCrudPage`、`useTablePage`、`useForm`、`useDialog`、`useLoading`、`useChart`、`useFeedback`
- `useSearchLayout`：搜索表单左右/上下排列的浏览器持久偏好
- `v-hsx-permission`、`v-hsx-copy`、`v-hsx-debounce`
- `createHsxCache`：命名空间、TTL、版本失效与并发请求合并

用户移动端：

- `HsxButton`、`HsxCheckbox`、`HsxSwitch`、`HsxTag`、`HsxTimeline`
- `HsxCascader`、`HsxUpload`、`HsxIcon`、`HsxPopup`、`HsxForm`、`HsxPageList`
- `HsxChart`、`HsxChartCard` 与折线/柱状/环形图配置助手（uCharts 多端内核）
- `HsxThemeProvider` 提供移动端明暗主题语义变量
- 与平台同协议的排版、网格、列表、溢出、进度、动效组件，以及白名单 `HsxBlockRenderer`
- `HsxCard`、`HsxEmpty`
- `HsxEntityPicker`、`HsxProductList`、`HsxDetail`，以及支持动作 Schema 的 `HsxActionBar`
- `useRequest`、`usePaging`、`useStorage`、`useCountDown`、`useToast`、`useModal`、`useFeedback`
- 与平台同协议的 `createHsxCache`

## 文档与演示

- 平台后台：`开发工具 → 组件开发中心 → 组件总览/基础组件/图表与反馈/Schema 组件/业务组件/移动端组件/API 手册`
- 站点后台：`组件开发中心`（由 `app/dict/menu/site.php` 注册）
- 用户移动端：`/addon/hsx_components/pages/demo/index`
- 完整 API：`docs/COMPONENT_API.md`
- UI/UX 设计规范：`docs/DESIGN_SYSTEM.md`
- Template/TSX/低代码边界：`docs/TSX_GUIDE.md`
- 表格父子级、主从表及业务边界：`docs/TABLE_GUIDE.md`
- 第三方开源许可证：`THIRD_PARTY_NOTICES.md`
- [回收与 ERP 交互统一及测试报告](docs/回收与ERP交互统一_2026-09-07.md)：适配范围、组件调用方式、数据库回归、部署边界与未覆盖项

后台文档页面会读取已安装应用版本，因此 `info.json` 升级并同步到数据库后，页面版本会自动更新。
演示数据均为本地模拟数据，不会修改业务数据。

## 准入规则

1. 公共组件不能反向依赖手机货盘、交易、质检等业务插件。
2. 业务插件从 `@/addon/hsx_components` 或其公开的 `/core`、`/forms`、`/types` 等出口导入，避免绕过公共层复制组件实现。
3. 平台端新增能力必须通过独立类型检查和生产构建。
4. 移动端新增能力必须通过 H5 与微信小程序真实构建。
5. 小程序端不使用无边界的整包属性透传，新增属性必须进入明确白名单。
6. `HsxPageList` 使用固定版本 `z-paging@2.8.7`；升级前必须通过 H5 与微信小程序构建。

后续清单见 `docs/ROADMAP.md`。
