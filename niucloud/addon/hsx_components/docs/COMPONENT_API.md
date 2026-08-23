# HSX 通用组件调用手册

版本：`hsx_components 0.1.0`

本文档面向其他业务插件开发者，统一说明平台管理后台和用户移动端的组件、
Hooks、指令及工具函数。Web 端及移动端的 Hooks、类型、配置助手统一从公开入口导入。

微信小程序使用旧版 UniApp 编译链时，Template 中出现的 Vue SFC 必须显式导入对应组件入口，
编译器才能生成 `usingComponents`。因此移动端的 `components/*/index.vue` 也属于受支持的公开入口；
不要再向下引用组件内部实现文件。

设计和代码约束同时参见 [`DESIGN_SYSTEM.md`](./DESIGN_SYSTEM.md) 与
[`TSX_GUIDE.md`](./TSX_GUIDE.md)。

## 1. 双端入口

| 使用端 | 工程目录 | 插件源码目录 | 业务插件引入路径 |
| --- | --- | --- | --- |
| Web 管理后台 | `admin` | `admin/src/addon/hsx_components` | `@/addon/hsx_components` |
| 用户移动端 | `uni-app` | `uni-app/src/addon/hsx_components` | `@/addon/hsx_components` |

同一个引入路径只在各自工程内解析，不会互相串端。

```ts
// Web、Hooks、类型和配置助手：从聚合公开入口引入
import { ProTable, useCrudPage } from '@/addon/hsx_components'

// UniApp Template + 微信小程序：显式引入 SFC，确保生成 usingComponents
import HsxButton from '@/addon/hsx_components/components/HsxButton/index.vue'
import HsxSchemaForm from '@/addon/hsx_components/components/HsxSchemaForm/index.vue'
```

## 2. Web 管理后台

管理端公开入口按职责拆分：

| 入口 | 内容 |
| --- | --- |
| `@/addon/hsx_components/core` | 页面、标题、按钮、弹窗、布局和反馈 |
| `@/addon/hsx_components/forms` | 表单、选择器、日期、上传和凭证上传 |
| `@/addon/hsx_components/data` | 表格、详情、分页、时间线和导入导出 |
| `@/addon/hsx_components/visual` | 图表、统计卡、Markdown、AI 块和商品展示 |

聚合入口 `@/addon/hsx_components` 保持兼容，新增业务优先使用分层入口。

### 2.0 页面、弹窗与业务凭证

`HsxPage` 是管理端页面基线，统一标题、说明、操作区、内容宽度、页面留白、局部加载和
1366/150% 缩放适配。常用参数为 `title/subtitle/eyebrow/loading/surface/padding/contentWidth`，
插槽包括 `header/prefix/extra/toolbar/default`。

`HsxDialog` 提供 `sm/md/lg/xl` 四档商业弹窗宽度，支持 `subtitle/bodyLoading/confirmLoading/
confirmDisabled/footerAlign`。已有 `width` 调用继续有效，业务弹窗不得再复制 Element Plus
内部结构和响应式覆盖。

`HsxVoucherUpload` 是牛云 `upload-image` 的业务凭证预设，使用字符串 `v-model`，统一上传数量、
缩略图尺寸、选填/必填标识、说明和只读状态。通用二进制上传仍使用 `HsxUpload`。

### 2.1 HsxButton

用途：防重复点击、异步操作自动 Loading、统一权限判断。

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `action` | `(event: MouseEvent) => any \| Promise<any>` | - | 点击任务。返回 Promise 时可自动管理 Loading |
| `autoLoading` | `boolean` | `true` | 是否跟踪 `action` 返回的 Promise |
| `debounce` | `number` | `500` | 两次有效点击的最小间隔，单位毫秒 |
| `loading` | `boolean` | `false` | 外部控制 Loading |
| `disabled` | `boolean` | `false` | 是否禁用 |
| `permission` | `string \| string[]` | - | 所需权限标识 |
| `permissionChecker` | `(permission) => boolean` | - | 当前按钮独立的权限判断器 |
| `hideWithoutPermission` | `boolean` | `true` | 无权限时隐藏；设为 `false` 时禁用 |

其他 Element Plus Button 属性通过组件透传。

#### 出参 Events

| 事件 | 参数 | 说明 |
| --- | --- | --- |
| `click` | `MouseEvent` | 通过防重复检查后的点击事件 |
| `error` | `unknown` | `action` 执行失败 |

#### Slots

| 名称 | 说明 |
| --- | --- |
| `default` | 按钮内容 |

> `@click="asyncFn"` 的返回值无法被 Vue Emit 获取，因此需要自动 Loading 时必须使用
> `:action="asyncFn"`。

```vue
<HsxButton type="primary" :action="saveDevice" permission="device:save">保存</HsxButton>
```

### 2.2 HsxInput

用途：数字清洗、限制小数位、失焦去空格、回车搜索。

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `string \| number \| null` | `''` | 输入值 |
| `numeric` | `boolean` | `false` | 是否只允许数字 |
| `allowNegative` | `boolean` | `false` | 数字模式是否允许负数 |
| `decimalPlaces` | `number` | `2` | 最大小数位 |
| `trimOnBlur` | `boolean` | `true` | 失焦时是否移除首尾空格 |
| `enterSearch` | `boolean` | `true` | 回车时是否触发 `search` |

其他 Element Plus Input 属性和插槽透传。

#### 出参 Events

| 事件 | 参数 | 说明 |
| --- | --- | --- |
| `update:modelValue` | `string \| number` | 双向绑定值 |
| `input` | `string \| number` | 输入时触发 |
| `change` | `string \| number` | 失焦完成清洗后触发 |
| `blur` | `FocusEvent` | 原生失焦事件 |
| `search` | `string \| number` | 按回车触发 |

### 2.3 HsxSelect

用途：远程搜索、分页加载、选项缓存、编辑回显。

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | 基础值或基础值数组 | - | 当前选择值 |
| `options` | `SelectOption[]` | `[]` | 本地选项 |
| `fetchOptions` | `(keyword, page, limit) => Promise<RemoteResult>` | - | 远程分页加载器 |
| `resolveValues` | `(values[]) => Promise<SelectOption[]>` | - | 根据已有值补齐回显选项 |
| `pageSize` | `number` | `20` | 每次远程加载数量 |
| `labelKey` | `string` | `label` | 选项显示字段 |
| `valueKey` | `string` | `value` | 选项值字段 |
| `autoLoad` | `boolean` | `true` | 首次展开是否自动请求 |
| `loading` | `boolean` | `false` | 外部异步选项加载状态；会与内部远程 Loading 合并 |

```ts
interface SelectOption {
    label: string
    value: string | number | boolean
    disabled?: boolean
    [key: string]: any
}

type RemoteResult = SelectOption[] | {
    list: SelectOption[]
    total?: number
    hasMore?: boolean
}
```

#### 出参 Events

| 事件 | 参数 | 说明 |
| --- | --- | --- |
| `update:modelValue` | `any` | 双向绑定值 |
| `change` | `any` | 选择变化 |
| `load` | `SelectOption[]` | 每次合并缓存后的完整选项 |

#### 暴露方法

| 名称 | 入参 | 返回值 | 说明 |
| --- | --- | --- | --- |
| `reload` | - | `Promise<void>` | 从第一页重新加载 |
| `loadMore` | - | `Promise<void>` | 加载下一页 |
| `options` | - | `ComputedRef<SelectOption[]>` | 当前缓存选项 |

### 2.4 HsxCascader

用途：把 Element Plus Cascader 提升为可复用的数据组件，支持任意层级、逐级异步加载、
远程搜索、搜索缓存和编辑回显。公共组件不包含品牌、系列、型号等业务字段。

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `string \| number \| Array \| null` | `null` | 当前值；默认输出完整值路径 |
| `options` | `HsxCascaderOption[]` | `[]` | 本地树形数据 |
| `fetchOptions` | `(parent, context) => Promise<Option[]>` | - | 展开节点时异步加载下一级；传入后自动开启 lazy |
| `searchOptions` | `(keyword) => Promise<Option[] \| Option[][]>` | - | 远程搜索；可返回树或完整路径数组 |
| `resolvePaths` | `(value) => Promise<Option[] \| Option[][]>` | - | 根据已有值补齐编辑回显路径 |
| `beforeFilter` | `(keyword) => boolean \| Promise<boolean>` | - | 搜索前置校验 |
| `filterMethod` | `(node, keyword) => boolean` | 路径文本匹配 | 搜索结果过滤方法 |
| `cascaderProps` | `Record<string, any>` | `{}` | 透传 Element Plus Cascader Props |
| `labelKey/valueKey` | `string` | `label/value` | 自定义字段映射 |
| `childrenKey/leafKey` | `string` | `children/leaf` | 子级和末级字段映射 |
| `emitPath` | `boolean` | `true` | 是否输出完整值路径 |
| `multiple` | `boolean` | `false` | 多选 |
| `checkStrictly` | `boolean` | `false` | 父子节点是否取消关联 |
| `filterable/clearable` | `boolean` | `true` | 搜索与清空 |
| `showAllLevels` | `boolean` | `false` | 输入框是否显示完整文本路径 |
| `cacheSearch` | `boolean` | `true` | 是否缓存相同关键词结果 |

`fetchOptions` 的 `context` 为 `{ level, keyword, path }`。`searchOptions` 返回
`Option[][]` 时，每一项代表一条完整路径，组件会自动合并为树。

#### 出参 Events 与暴露方法

| 名称 | 参数/说明 |
| --- | --- |
| `change` | `(value, selectedPaths)`，值和已选完整节点路径 |
| `load` | `(options, parent)`，逐级加载完成 |
| `search` | `(keyword, options)`，远程搜索完成 |
| `error` | `(error, stage)`，阶段为 load/search/resolve |
| `reload()` | 重置本地状态并重新回显 |
| `clearSearchCache()` | 清除远程搜索缓存 |
| `resolveSelectedPaths(force?)` | 主动补齐编辑回显路径 |
| `getCheckedNodes()` | 访问 Element Plus 已选节点 |

```vue
<HsxCascader
    v-model="modelPath"
    :fetch-options="loadModelChildren"
    :search-options="searchModelPaths"
    :resolve-paths="resolveModelPaths"
    placeholder="搜索或逐级选择型号"
/>
```

手机型号属于业务组件。建议业务插件再封装 `DeviceModelCascader`，在内部绑定型号接口、
字段映射和业务权限，对外仍使用统一的 `v-model/change` 协议。

### 2.5 HsxSearchInput

用途：统一关键词输入、回车搜索、按钮搜索、清空搜索和输入防抖搜索。

| 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `string \| number` | `''` | 关键词 |
| `autoSearch` | `boolean` | `false` | 输入停止后自动搜索 |
| `debounce` | `number` | `400` | 自动搜索等待毫秒数 |
| `searchOnClear` | `boolean` | `true` | 清空后是否查询全部 |
| `loading/disabled/clearable` | `boolean` | 常规值 | 交互状态 |
| `buttonText` | `string` | `搜索` | 按钮文字 |

出参：`update:modelValue(value)`、`search(value, trigger)`、`clear()`。`trigger` 为
`enter`、`button`、`clear` 或 `debounce`。

### 2.6 HsxPagination

用途：统一分页参数、布局和事件，已作为 `ProTable` 的默认分页器。

主要入参：`currentPage`、`pageSize`、`total`、`pageSizes`、`layout`、`background`、
`small`、`disabled`、`hideOnSinglePage`。支持 `v-model:current-page` 和
`v-model:page-size`。

出参：`current-change(page)`、`size-change(limit)`、`change({ page, limit })`。

### 2.7 HsxColumnSetting

用途：表格列的显示隐藏、左右固定、上下排序和用户偏好记忆。

| 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `HsxColumnSettingItem[]` | 必填 | 列设置 |
| `storageKey` | `string` | `''` | 非空时保存至浏览器本地 |
| `allowFixed` | `boolean` | `true` | 允许固定左右侧 |
| `minVisible` | `number` | `1` | 至少保留的可见列数 |

```ts
interface HsxColumnSettingItem {
    key: string
    label: string
    visible?: boolean
    fixed?: false | 'left' | 'right'
    required?: boolean
    disabled?: boolean
}
```

出参：`update:modelValue(items)`、`change(items)`、`reset(items)`；暴露 `reset()`。

### 2.8 HsxImport

用途：统一 Excel/CSV 文件选择、格式和大小校验、模板下载及异步导入状态。

主要入参：`importer(file, context)`、`accept`、`maxSizeMb`、`templateUrl`、`tip`、
`closeOnSuccess`、`permission`。默认接受 `.xlsx,.xls,.csv`，默认上限 `20MB`。

出参：`change(file)`、`submit(file, context)`、`success(result, file)`、
`error(error, file)`、`open`、`close`。暴露 `open()`、`close()`、`clear()`、`submit()`。

> 公共组件只负责文件和状态；字段映射、重复 IMEI、错误行报告等由业务导入接口返回。

### 2.9 HsxExport

用途：统一本地 CSV/JSON 导出与后端 Excel 大数据导出。

主要入参：`data`、`columns`、`filename`、`format`、`query`、`exporter(context)`、
`permission`。不传 `exporter` 时在浏览器生成 CSV/JSON；传入时可返回 `Blob`、
`ArrayBuffer`、下载 URL 或 `{ blob/url, filename }`。

出参：`start(context)`、`success(result, context)`、`error(error, context)`；暴露
`export()`。大数据量和 `.xlsx` 应使用后端任务导出，避免浏览器卡死。

### 2.10 HsxDialog

用途：统一弹窗，支持拖拽和全屏。

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `boolean` | 必填 | 显示状态 |
| `title` | `string` | `''` | 标题 |
| `width` | `string \| number` | `720px` | 普通模式宽度 |
| `fullscreen` | `boolean` | `false` | 全屏状态，可使用 `v-model:fullscreen` |
| `showFullscreen` | `boolean` | `true` | 是否显示全屏按钮 |
| `draggable` | `boolean` | `true` | 普通模式是否允许拖拽 |
| `bodyMaxHeight` | `string` | `65vh` | 内容区最大高度 |
| `closeOnClickModal` | `boolean` | `false` | 点击遮罩是否关闭 |
| `destroyOnClose` | `boolean` | `true` | 关闭时销毁内容 |
| `appendToBody` | `boolean` | `true` | 是否挂载到 Body |
| `showFooter` | `boolean` | `false` | 是否显示默认底部区域 |
| `confirmText` | `string` | `确定` | 默认确认文案 |
| `cancelText` | `string` | `取消` | 默认取消文案 |
| `confirmLoading` | `boolean` | `false` | 确认按钮 Loading |

#### 出参 Events

| 事件 | 参数 | 说明 |
| --- | --- | --- |
| `update:modelValue` | `boolean` | 显示状态变化 |
| `update:fullscreen` | `boolean` | 全屏状态变化 |
| `open` | - | 开始打开 |
| `close` | - | 开始关闭 |
| `closed` | - | 关闭动画完成 |
| `confirm` | - | 点击默认确认按钮；不会自动关闭 |
| `cancel` | - | 点击默认取消按钮；会关闭 |

#### Slots

| 名称 | 插槽参数 | 说明 |
| --- | --- | --- |
| `default` | - | 弹窗内容 |
| `header` | `{ title, fullscreen }` | 自定义标题 |
| `footer` | `{ close, confirm }` | 自定义底部 |

#### 暴露方法

| 名称 | 说明 |
| --- | --- |
| `close()` | 关闭弹窗 |
| `toggleFullscreen()` | 切换全屏 |
| `fullscreen` | 当前内部全屏 Ref |

全屏状态下会自动关闭拖拽，退出全屏后恢复。

### 2.11 ProForm

用途：根据 Schema 生成表单。

#### 组件入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `Record<string, any>` | `{}` | 表单数据 |
| `schema` | `ProFormField[]` | 必填 | 字段配置 |
| `rules` | `FormRules` | `{}` | 额外校验规则，同名时覆盖字段规则 |
| `columns` | `number` | `2` | 一行默认列数 |
| `gutter` | `number` | `20` | 栅格间距 |
| `labelWidth` | `string \| number` | `100` | 标签宽度 |
| `disabled` | `boolean` | `false` | 整表禁用 |
| `readonly` | `boolean` | `false` | 阅读模式 |
| `inline` | `boolean` | `false` | 行内表单 |
| `showMessage` | `boolean` | `true` | 显示校验信息 |
| `validateOnRuleChange` | `boolean` | `false` | 联动规则变化时不立刻报错；设为 `true` 恢复 Element Plus 默认行为 |
| `components` | `Record<string, Component>` | `{}` | 业务插件注册的自定义字段组件 |
| `permissionChecker` | `(permission) => boolean` | 全局配置 | 当前表单的字段权限判断器 |

#### 字段 Schema 入参

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `prop` | `string` | 必填 | 字段名，支持 `a.b` 路径 |
| `label` | `string` | - | 标签 |
| `component` | 组件类型或 Vue Component | `input` | 支持 `input`、`select`、`cascader`、日期、开关、单选、多选、插槽等控件 |
| `placeholder` | `string` | 自动生成 | 占位文案 |
| `defaultValue` | `any` | - | 字段无值时使用的默认值 |
| `span` | `number` | `24 / columns` | 栅格宽度 |
| `labelWidth` | `string \| number` | - | 当前字段标签宽度 |
| `props` | `Record<string, any> \| (model) => Record<string, any>` | - | 静态或随表单变化的底层控件属性 |
| `options` | `SelectOption[] \| (model) => SelectOption[]` | - | 静态或动态选择项 |
| `optionsLoader` | `(context) => Promise<SelectOption[]>` | - | 异步加载选择项 |
| `optionsDependencies` | `string[]` | `[]` | 指定字段变化后重新执行 `optionsLoader` |
| `rules` | `FormItemRule \| FormItemRule[]` | - | 校验规则 |
| `visible` | `boolean \| (model) => boolean` | `true` | 显示联动 |
| `disabled` | `boolean \| (model) => boolean` | `false` | 禁用联动 |
| `required` | `boolean \| (model) => boolean` | `false` | 必填联动；隐藏字段不会参与校验 |
| `requiredMessage` | `string` | 自动 | 动态必填提示 |
| `permission` | `string \| string[]` | - | 字段权限；无权限时不渲染也不校验 |
| `componentKey` | `string` | - | 从 `components` 注册表读取业务组件 |
| `slot` | `string` | - | 自定义字段插槽名 |
| `tip` | `string \| (model) => string` | - | 静态或动态字段提示 |
| `change` | `(value, model) => void` | - | 当前字段变化回调 |

支持的内置类型：`input`、`textarea`、`input-number`、`select`、`cascader`、`date`、
`datetime`、`date-range`、`datetime-range`、`upload`、`switch`、`radio`、`checkbox`、`slot`。

在 `ProForm` 中使用级联时，把异步方法放进字段 `props`，公共表单会交给
`HsxCascader` 执行，不需要另写模板：

```ts
const schema: ProFormField[] = [{
    prop: 'modelPath',
    label: '手机型号',
    component: 'cascader',
    props: {
        fetchOptions: loadModelChildren,
        searchOptions: searchModelPaths,
        resolvePaths: resolveModelPaths
    }
}]
```

#### 动态联动、异步选项与字段权限

```ts
const schema: ProFormField[] = [
    {
        prop: 'tradeMode',
        label: '销售方式',
        component: 'radio',
        defaultValue: 'consign',
        options: [
            { label: '平台代卖', value: 'consign' },
            { label: '主理人买断', value: 'buyout' }
        ]
    },
    {
        prop: 'minimumPrice',
        label: '最低到手价',
        component: 'input-number',
        visible: model => model.tradeMode === 'consign',
        required: model => model.tradeMode === 'consign',
        props: model => ({ min: Number(model.sellerPrice || 0) })
    },
    {
        prop: 'managerId',
        label: '主理人',
        component: 'select',
        optionsDependencies: ['tradeMode', 'region.code'],
        optionsLoader: ({ dependencies }) => getManagers(dependencies)
    },
    {
        prop: 'internalMargin',
        label: '内部毛利',
        component: 'input-number',
        permission: 'pricing.internal'
    }
]
```

`optionsLoader` 的 `context` 为 `{ model, field, dependencies }`。仅
`optionsDependencies` 声明的值变化时才重新请求，避免表单任意输入都触发接口。

#### 注册业务字段组件

```vue
<ProForm
    v-model="form"
    :schema="schema"
    :components="{ ImeiInput }"
    :permission-checker="userStore.hasPermission"
/>
```

也可以在应用入口统一注册，所有业务表单直接使用 `componentKey`：

```ts
app.use(HsxComponents, {
    permissionChecker: userStore.hasPermission,
    formComponents: { ImeiInput, DeviceSkuSelector }
})
```

```ts
const schema = [{ prop: 'imei', label: 'IMEI', componentKey: 'ImeiInput' }]
```

业务组件会收到 `modelValue`、`field`、`model`、`disabled`，并通过
`update:modelValue` 回传。简单定制仍优先使用字段插槽。

#### 出参 Events

| 事件 | 参数 | 说明 |
| --- | --- | --- |
| `update:modelValue` | `model` | 完整表单数据 |
| `change` | `prop, value, model` | 任意字段变化 |
| `options-load` | `prop, options` | 异步选项加载成功 |
| `options-error` | `prop, error` | 异步选项加载失败 |

#### Slots

字段配置 `slot: 'imei'` 后：

```vue
<template #imei="{ field, model, value, setValue }">
    <imei-input :model-value="value" @update:model-value="setValue" />
</template>
```

#### 暴露方法

| 名称 | 入参 | 返回值 | 说明 |
| --- | --- | --- | --- |
| `validate()` | - | `Promise<boolean>` | 整表校验 |
| `validateField(props)` | 字段名/数组 | Promise | 指定字段校验 |
| `resetFields(value?)` | 新初始值 | Promise | 重置并清除校验 |
| `clearValidate(props?)` | 字段名/数组 | void | 清除校验 |
| `getValues<T>()` | - | `T` | 获取深拷贝数据 |
| `setValues(values)` | 对象 | void | 批量设置，键支持路径 |
| `reloadOptions(prop?)` | 可选字段名 | Promise | 主动重载一个或全部异步选项 |
| `formRef` | - | `FormInstance` | Element Plus 表单实例 |

### 2.12 ProTable

用途：列配置、查询表单、请求、分页、行内编辑、插槽统一管理。

#### 组件入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `columns` | `ProTableColumn[]` | 必填 | 列配置 |
| `request` | `(params) => Promise<any>` | - | 分页请求；不传时使用 `data` |
| `data` | `Record<string, any>[]` | `[]` | 本地数据 |
| `loading` | `boolean` | `false` | 外部 Loading |
| `responseAdapter` | `(response) => { list, total }` | 默认适配器 | 自定义返回值转换 |
| `initialSearch` | `Record<string, any>` | `{}` | 初始查询条件 |
| `searchSchema` | `ProFormField[]` | 自动生成 | 完整覆盖自动查询 Schema |
| `searchColumns` | `number` | `4` | 查询区列数 |
| `autoLoad` | `boolean` | `true` | 挂载后自动请求 |
| `rowKey` | `string \| (row) => string` | `id` | 行键 |
| `showSearch` | `boolean` | `true` | 显示查询区 |
| `showToolbar` | `boolean` | `true` | 显示工具栏 |
| `showPagination` | `boolean` | `true` | 显示分页 |
| `showColumnSetting` | `boolean` | `true` | 显示自定义列入口 |
| `columnStorageKey` | `string` | `''` | 用户列偏好的本地存储键 |
| `pageSize` | `number` | `20` | 默认每页条数 |
| `pageSizes` | `number[]` | `[10,20,50,100]` | 每页条数选项 |
| `paginationLayout` | `string` | Element Plus 常用布局 | 分页布局 |
| `border` | `boolean` | `false` | 表格边框 |
| `stripe` | `boolean` | `true` | 斑马纹 |
| `emptyText` | `string` | `暂无数据` | 空状态文案 |

其他 Element Plus Table 属性透传。

#### 请求入参

```ts
{
    page: 1,
    limit: 20,
    ...searchModel,
    order_by?: string,
    sort?: 'asc' | 'desc' | ''
}
```

默认返回适配器识别：

```ts
response.data.data + response.data.total
response.data.list + response.data.total
response.list + response.total
response.rows + response.count
```

无法满足时传入 `responseAdapter`，最终必须返回：

```ts
{ list: Row[], total: number }
```

#### 列 Schema 入参

| 名称 | 类型 | 说明 |
| --- | --- | --- |
| `prop` | `string` | 字段名，支持路径 |
| `label` | `string` | 列名 |
| `type` | `selection \| index \| expand` | 特殊列 |
| `width/minWidth/fixed/align` | Element Plus 类型 | 表格列布局 |
| `sortable` | `boolean \| custom` | 排序 |
| `hideInTable` | `boolean` | 只搜索、不显示列 |
| `search` | `boolean \| ProTableSearchConfig` | 自动生成查询字段 |
| `slot` | `string` | 自定义内容插槽 |
| `headerSlot` | `string` | 自定义表头插槽 |
| `editable` | `boolean` | 开启行内编辑 |
| `editComponent` | `input \| input-number \| select` | 编辑控件 |
| `editProps` | `Record<string, any>` | 编辑控件属性 |
| `options` | `SelectOption[]` | 值到文字的映射，同时供编辑选择 |
| `formatter` | `(row, value, index) => any` | 显示格式化 |
| `hideInSetting` | `boolean` | 不进入自定义列并保持固定显示状态 |
| `columnSetting` | `{ visible, fixed, required, disabled }` | 自定义列初始规则 |

#### 出参 Events

| 事件 | 参数 | 说明 |
| --- | --- | --- |
| `load` | `rows, total` | 加载完成 |
| `error` | `unknown` | 请求失败 |
| `search` | `params` | 每次发起查询前 |
| `reset` | - | 重置查询 |
| `selection-change` | `rows` | 多选变化 |
| `row-click` | `row, column, event` | 行点击 |
| `sort-change` | `sort` | 排序变化 |
| `column-change` | `columns` | 显示、固定或顺序变化 |

#### Slots

| 名称 | 插槽参数 | 说明 |
| --- | --- | --- |
| `toolbar` | `{ load, rows }` | 左侧工具栏 |
| `toolbar-right` | `{ load, rows }` | 右侧工具栏 |
| `search-actions` | `{ search, load, reset }` | 查询按钮后追加操作 |
| `cell-{prop}` 或列 `slot` | `{ row, column, $index, value }` | 自定义单元格 |
| `expand` | Element Plus Scope | 展开行 |
| `empty` | - | 空状态 |

#### 暴露数据和方法

`tableRef`、`rows`、`loading`、`total`、`pagination`、`searchModel`、`columnSettings`、`load()`、
`reload()`、`resetSearch()`、`clearSelection()`。

### 2.13 ProDialogForm

用途：新增、编辑、查看共用同一个弹窗表单。

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `boolean` | 必填 | 弹窗显示状态 |
| `mode` | `create \| edit \| view` | `create` | 模式 |
| `title` | `string` | 自动 | 固定标题 |
| `modeText` | `{ create, edit, view }` | 中文默认值 | 模式标题 |
| `formData` | `Record<string, any>` | `{}` | 表单数据，可使用 `v-model:form-data` |
| `schema` | `ProFormField[]` | 必填 | 表单 Schema |
| `columns` | `number` | `2` | 表单列数 |
| `width` | `string \| number` | `760px` | 弹窗宽度 |
| `fullscreen` | `boolean` | `false` | 初始全屏 |
| `submit` | `(data, mode) => Promise<any> \| any` | - | 保存任务，可自动 Loading |
| `beforeSubmit` | `(data, mode) => data \| Promise<data>` | - | 提交前转换 |
| `closeOnSuccess` | `boolean` | `true` | 成功后关闭 |
| `components` | `Record<string, Component>` | `{}` | 传给内部 ProForm 的业务组件注册表 |
| `permissionChecker` | `(permission) => boolean` | 全局配置 | 传给内部 ProForm 的字段权限判断器 |

#### 出参 Events

| 事件 | 参数 | 说明 |
| --- | --- | --- |
| `update:modelValue` | `boolean` | 显示状态 |
| `update:formData` | `model` | 表单变化 |
| `submit` | `data, mode` | 校验通过后、执行 `submit` 属性前触发 |
| `success` | `result, data` | `submit` 成功 |
| `error` | `unknown` | 保存失败；组件不会抛到外层 |
| `options-load` | `prop, options` | 内部异步选项加载成功 |
| `options-error` | `prop, error` | 内部异步选项加载失败 |

字段插槽会继续传给内部 `ProForm`。暴露 `formRef`、`submit()`、`loading`。

### 2.14 HsxDatePicker

用途：统一单个日期、单个时间、日期区间和时间区间。区间默认面向查询场景，把开始值补齐
`00:00:00`，结束值补齐 `23:59:59`，避免漏掉结束日期当天的数据。

| 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | 单值、`[start, end]` 或 `null` | `null` | 当前值 |
| `type` | `date \| datetime \| daterange \| datetimerange` | `date` | 选择类型 |
| `normalizeRangeBoundary` | `boolean` | `true` | 区间是否补齐全天边界 |
| `startTime/endTime` | `string` | `00:00:00/23:59:59` | 自定义区间边界 |
| `valueFormat` | `string` | 随类型生成 | Element Plus 输出格式 |
| `maxSpanDays` | `number` | `0` | 最大跨度，0 不限制 |
| `disableFuture` | `boolean` | `false` | 禁选未来日期 |
| `shortcuts` | `HsxDateRangeShortcut[]` | 常用区间 | 自定义快捷区间 |

出参：`update:modelValue(value)`、`change(value)`、`raw-change(raw, normalized)`、
`exceed(value, maxSpanDays)`。

```vue
<HsxDatePicker v-model="createdAt" type="datetime" />
<HsxDatePicker v-model="range" type="daterange" />
<!-- range: ['2026-08-01 00:00:00', '2026-08-06 23:59:59'] -->
```

### 2.15 HsxDateRange

用途：`HsxDatePicker` 的兼容区间入口。已有业务无需迁移；新业务优先使用
`HsxDatePicker`。`ProForm` 的 `date/datetime/date-range/datetime-range` 已全部接入统一组件。

| 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `[start, end] \| null` | `null` | 日期范围 |
| `type` | `daterange \| datetimerange` | `datetimerange` | 日期类型 |
| `valueFormat` | `string` | 随类型生成 | 默认输出标准日期字符串 |
| `maxSpanDays` | `number` | `0` | 最大跨度；0 不限制 |
| `disableFuture` | `boolean` | `false` | 禁选未来日期 |
| `disabledDate` | `(date) => boolean` | - | 额外禁选规则 |
| `shortcuts` | `HsxDateRangeShortcut[]` | 常用区间 | 自定义快捷区间 |
| `showDefaultShortcuts` | `boolean` | `true` | 今天、近 7/30/90 天 |
| `normalizeRangeBoundary` | `boolean` | `true` | 输出全天起止边界 |

出参：`update:modelValue(value)`、`change(value)`、`exceed(value, maxSpanDays)`。
其余 Element Plus DatePicker 属性通过组件透传。

### 2.16 HsxUpload

用途：统一图片/视频/文件上传的大小、数量、进度、预览和失败状态；公共组件不写死
上传地址，业务插件传入 `uploader`。

| 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `HsxUploadItem[]` | `[]` | 文件列表 |
| `uploader` | `(file, context) => Promise<result>` | 必填 | 业务上传函数 |
| `resultAdapter` | `(result, file) => HsxUploadItem` | 默认识别 url | 自定义后端返回转换 |
| `accept` | `string` | `image/*` | 文件类型 |
| `maxSizeMb` | `number` | `10` | 单文件上限 |
| `limit` | `number` | `9` | 数量上限 |
| `multiple/disabled/drag/autoUpload` | `boolean` | 常规值 | 上传交互 |
| `listType` | `text \| picture \| picture-card` | `picture-card` | 列表样式 |
| `previewSize` | `string \| number` | `96` | 预览宽高的统一尺寸 |
| `previewWidth/previewHeight` | `string \| number` | - | 分别覆盖预览宽高 |
| `previewRadius` | `string \| number` | `10` | 方形预览圆角 |
| `shape` | `square \| circle` | `square` | 方形或圆形头像预览 |

`context` 为 `{ uid, name, size, type, onProgress }`。默认结果适配器识别字符串、
`result.url`、`result.data.url` 和 `result.data.data.url`。

出参：`change(files)`、`success(item, result)`、`error(error, file)`、
`remove(item)`、`preview(item)`、`progress(percent, file)`、`exceed(files)`。
暴露 `submit()`、`clearFiles()`、`fileList`、`uploadRef`。

```vue
<HsxUpload v-model="photos" :uploader="uploadDevicePhoto" :limit="9" :preview-size="88" />
```

### 2.17 HsxIcon

用途：统一图标调用协议。`name="element Search"` 使用 Element 图标；
`name="iconfont iconshangpin"` 使用框架字体图标；也可传 `component` 或 `src`。

主要入参：`name`、`component`、`src`、`size = 16`、`color = currentColor`、`spin`、
`clickable`、`label`。出参：`click(event)`。

### 2.18 QueryForm

用途：用 `ProForm` Schema 生成统一查询区，内置首行折叠、查询、重置和回车搜索。

主要入参：`modelValue`、`schema`、`initialValues`、`columns = 4`、
`collapseCount = 4`、`defaultCollapsed = true`、`loading`、`validateBeforeSearch`。

出参：`search(cleanModel)`、`reset(model)`、`collapse-change(collapsed)` 和
`update:modelValue`。插槽 `actions` 可追加导出等动作；字段插槽原样传给 `ProForm`。
暴露 `search()`、`reset()`、`toggleCollapsed()`、`formRef`、`model`、`collapsed`。

### 2.19 HsxDrawer

用途：统一侧边/上下抽屉，并完整保留 Element Plus 的属性、事件和插槽扩展能力。

| 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `boolean` | 必填 | 使用 `v-model` 控制显示 |
| `title` | `string` | `''` | 默认标题 |
| `size` | `string \| number` | `420px` | 宽度或高度 |
| `direction` | `rtl \| ltr \| ttb \| btt` | `rtl` | 打开方向 |
| `beforeClose` | `(done) => void` | - | 关闭前拦截 |
| `showFooter` | `boolean` | `false` | 显示默认底部动作 |
| `confirmLoading` | `boolean` | `false` | 确认按钮 Loading |

其他 Element Plus Drawer 属性通过 `$attrs` 透传。事件包含 `open/opened/close/closed`、
`open-auto-focus/close-auto-focus`、`confirm/cancel`。`default` 插槽收到
`{ close, confirm }`，`header` 收到原始 Scope 和 `close`，`footer` 收到
`{ close, confirm }`。暴露 `close()`、`confirm()`。

```vue
<HsxDrawer v-model="visible" title="设备详情" size="520px" show-footer @confirm="save">
    <template #default="{ close }">...</template>
    <template #footer="{ close, confirm }">...</template>
</HsxDrawer>
```

### 2.20 HsxCheckbox / HsxSwitch

`HsxCheckbox` 支持单个布尔选择和 options 组选项；组模式支持 checkbox/button、横向/纵向、
`min/max` 和 `option` 插槽。入参为 `modelValue/options/label/trueValue/falseValue/type/direction/min/max`，
出参为 `update:modelValue`、`change`。

`HsxSwitch` 为需要调用接口的状态开关提供统一事务流程：

```text
用户点击 → beforeChange → confirm → action(自动 Loading) → 更新 v-model
```

| 入参 | 类型 | 说明 |
| --- | --- | --- |
| `beforeChange` | `(next, current) => boolean \| Promise<boolean>` | 前置校验，返回 false 中止 |
| `confirm` | `(next, current) => boolean \| Promise<boolean>` | 业务确认，返回 false 中止 |
| `action` | `(next, current) => void \| boolean \| Promise` | 保存任务，返回 false 不更新值 |
| `debounce` | `number` | 防连续切换，默认 300ms |

出参为 `update:modelValue`、`change`、`error`；Element Plus Switch 属性和插槽继续透传。
两者均已接入 `ProForm` 的 `checkbox`、`switch` Schema 类型。

### 2.21 HsxTable

用途：提供稳定的基础表格协议。`ProTable` 在其上增加查询、请求、分页和列设置；业务页面不要
重复维护另一套表格内核。

| 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `columns` | `HsxTableColumn[]` | 必填 | 配置列、插槽列、渲染函数、编辑列、展开列 |
| `data` | `Record<string, any>[]` | `[]` | 本地数据 |
| `autoTree` | `boolean` | `false` | 将扁平父子数据转为树 |
| `treeOptions` | `HsxTreeOptions` | 常规字段 | 配置 id/parent/children/root 字段 |
| `selectionMode` | `independent \| children \| cascade \| leaf` | `independent` | 多选联动方式 |
| `selectable` | `(row, index) => boolean` | - | 自定义是否可选 |
| `rowKey` | `string \| function` | `id` | 行键 |

事件：`selection-change`、`select`、`select-all`、`row-click`、`sort-change`、
`expand-change`。支持 `cell-{prop}`/列 `slot`、`headerSlot`、`expand`、`empty` 插槽；
暴露 `clearSelection/getSelectionRows/toggleRowSelection/toggleAllSelection/tableRef`。

```vue
<!-- 父子级：扁平数据自动组树，父子双向联动 -->
<HsxTable :columns="columns" :data="rows" auto-tree selection-mode="cascade" default-expand-all />

<!-- 订单—设备：不要让基础组件理解订单，用 expand 插槽组合第二张表 -->
<HsxTable :columns="orderColumns" :data="orders">
    <template #expand="{ row }">
        <HsxTable :columns="deviceColumns" :data="row.devices" />
    </template>
</HsxTable>
```

列的业务状态、订单动作和接口调用留在业务插槽/Hooks 中。详细取舍见
[`TABLE_GUIDE.md`](./TABLE_GUIDE.md)。

### 2.22 HsxTag / HsxTimeline

`HsxTag` 只统一状态语义：`neutral/primary/info/success/warning/danger`，支持
`effect/size/round/closable/dot`，并透传点击、关闭事件。

`HsxTimeline` 接收 `HsxTimelineItem[]`，每项可配置 `actor/title/time/tag/tone/content/details`；
支持 `reverse/compact/showContent` 和 `dot/header/content` 插槽。组件只负责时间线骨架，
“审核、定价、售后”等文案和详情仍由业务提供。

### 2.23 createHsxCache

平台端与移动端都从公开入口导出同名 API，用来代替业务里散落的 Storage 调用。

| 创建参数 | 说明 |
| --- | --- |
| `namespace` | 必填建议；按插件/业务域隔离键 |
| `version` | 版本变化时旧缓存自动失效 |
| `defaultTtl` | 默认有效毫秒数，`0` 表示不过期 |
| `storage` | Web 支持 `local/session/memory`；移动端使用 UniApp Storage 并自动回退内存 |

返回方法：`get(key, fallback)`、`set(key, value, { ttl })`、`has`、`remove`、`clear`、
`keys`、`remember(key, loader, { ttl, force })`。`remember` 会合并同一个 key 的并发请求，
避免列表或多组件同时请求相同字典/详情。

```ts
const cache = createHsxCache({ namespace: 'hsx_trade', version: '1', defaultTtl: 60_000 })
const device = await cache.remember(`device:${id}`, () => getDevice(id))
```

缓存不能存登录凭证、支付口令等敏感信息，也不能替代 Pinia 的实时页面状态。

### 2.24 HsxChart / HsxChartCard / HsxStatCard

`HsxChart` 是 ECharts 的框架适配层，负责初始化、`ResizeObserver`、明暗主题、Loading、空态与销毁。
业务传入标准 `EChartsOption`，不要在页面直接调用 `echarts.init()`。

| 组件 | 主要入参 | 出参 / 暴露能力 | 说明 |
| --- | --- | --- | --- |
| `HsxChart` | `option/height/minHeight/loading/empty/renderer/autoResize` | `ready/click/finished`；`render/resize/dispose/getDataURL/dispatchAction` | 任意图表底座 |
| `HsxChartCard` | `title/subtitle/option/height/trend/loading/empty` | `chart-click`；`extra/footer` 插槽 | 驾驶舱大图表卡片 |
| `HsxStatCard` | `title/value/unit/trend/tone/icon/chartOption` | `value/icon/footer` 插槽 | 指标卡与迷你图表 |

组件库同时导出四个纯配置助手：`createLineChartOption`、`createBarChartOption`、
`createDonutChartOption`、`createSparklineOption`。简单业务优先使用助手；复杂业务仍可传完整 ECharts 配置。

```ts
const turnoverOption = createLineChartOption({
    labels: ['周一', '周二', '周三'],
    series: [{ name: '成交额', data: [12, 18, 26] }],
    area: true,
    unit: '万'
})
```

### 2.25 HsxBadge / HsxNoticeBubble

`HsxBadge` 统一数字、99+ 与红点提醒；`HsxNoticeBubble` 统一就地说明气泡，支持
`reference/title/default/actions` 插槽及 `v-model`。气泡只放简短信息和轻量动作，复杂表单使用 Dialog 或 Drawer。

### 2.26 HsxGrid / HsxGridItem

`HsxGrid` 负责页面级 1～5 列和多行布局，`HsxGridItem` 负责单张卡片的跨列与视觉表面。
普通业务不再单独编写 `grid-template-columns`、渐变背景或背景图 CSS。

| 组件 | 主要入参 | 说明 |
| --- | --- | --- |
| `HsxGrid` | `columns/gap/columnGap/rowGap/minItemWidth/dense/equalHeight` | `columns` 可传数字或 `xs/sm/md/lg/xl` 对象；卡片超出一行后自动形成多行 |
| `HsxGrid` | `background/backgroundColor/gradient/backgroundImage/overlay/padding/radius/border/shadow` | 容器级纯色、渐变、背景图、遮罩与盒模型 |
| `HsxGridItem` | `span/rowSpan/full/order/alignSelf` | 单项响应式跨列、跨满整行、跨行和排序 |
| `HsxGridItem` | 与 Grid 相同的视觉表面参数 | 每张卡片可独立使用纯色、渐变或背景图 |

```vue
<HsxGrid
    :columns="{ xs: 1, sm: 2, md: 3, lg: 4, xl: 5 }"
    :gap="16"
    gradient="linear-gradient(135deg, #f8fbff, #eef4ff)"
    :padding="18"
    :radius="18"
>
    <HsxGridItem
        v-for="item in cards"
        :key="item.id"
        :span="item.important ? { xs: 1, lg: 2 } : 1"
        shadow
    >
        {{ item.title }}
    </HsxGridItem>
    <HsxGridItem full>跨满整行的公告或汇总</HsxGridItem>
</HsxGrid>
```

### 2.27 HsxTreeTablePicker

平台端通用树表选择弹窗。它不绑定组织或人员接口，业务只提供树数据、查询 Schema、表格列和
分页请求函数；弹窗布局、加载、分页、跨页选择、暗黑模式、窄屏降级与确认流程由组件处理。

| 类型 | 名称 | 说明 |
| --- | --- | --- |
| Props | `visible/modelValue/multiple/rowKey` | `visible` 控制弹窗；`modelValue` 为选中行，单选返回对象，多选返回数组 |
| Props | `treeData/treeNodeKey/treeProps/treeLazy/treeLoad/treeParamKey` | 左侧树及同步/异步加载；所选节点以 `treeParamKey` 放入请求参数 |
| Props | `columns/request/responseAdapter/data` | 右侧表格列与分页数据；无请求时也可传静态 `data` |
| Props | `querySchema/initialQuery/searchColumns` | 搜索区复用 `ProForm` Schema，不重复编写查询表单 |
| Props | `tabs/activeTab/tabParamKey` | 可选顶部业务范围切换，如“组织架构 / 本部门” |
| Events | `confirm/selection-change/tree-change/tab-change/load/error` | 业务选择结果与加载生命周期 |
| Slots | `tree-header/tree/tree-node/search/search-actions/toolbar/selected` | 所有大区块均可替换；表格单元格插槽继续透传 |
| Expose | `open/close/reload/search/reset/confirm/getSelectedRows/clearSelection` | 命令式控制能力 |

```vue
<HsxTreeTablePicker
    v-model:visible="visible"
    v-model="selectedUsers"
    v-model:active-tab="activeTab"
    title="选择整改责任人"
    :tabs="[{ label: '组织架构', value: 'organization' }, { label: '本部门', value: 'department' }]"
    :tree-data="organizationTree"
    :query-schema="[
        { prop: 'account', component: 'input', placeholder: '请输入账号' },
        { prop: 'name', component: 'input', placeholder: '请输入姓名' }
    ]"
    :columns="userColumns"
    :request="getUserList"
    multiple
    @confirm="saveSelection"
/>
```

请求函数收到 `page/limit`、查询字段、`tree_id`、`treeNode` 与 `tab`。如后端字段名不同，使用
`treeParamKey/tabParamKey` 修改；如返回结构不是 `{ list, total }`，通过 `responseAdapter` 适配。

## 3. Web 管理后台 Hooks

### 3.1 useDialog

```ts
const {
    visible, mode, payload, readonly,
    open, close, create, edit, view
} = useDialog<Row>()
```

| 方法 | 入参 | 说明 |
| --- | --- | --- |
| `open(mode, data?)` | 模式、数据 | 打开 |
| `create(data?)` | 可选初始值 | 新增模式 |
| `edit(data)` | 行数据 | 编辑模式 |
| `view(data)` | 行数据 | 查看模式 |
| `close()` | - | 关闭 |

### 3.2 useForm

```ts
const { formRef, model, setValues, reset, validate } = useForm(() => ({ name: '' }))
```

- `setValues(values)`：合并数据。
- `reset(values?)`：恢复初始值，可附加覆盖值，并清除校验。
- `validate()`：返回 `Promise<boolean>`。

### 3.3 useLoading

返回 `loading`、`startLoading()`、`stopLoading()`、`withLoading(promiseOrFactory)`。
内部使用计数器，并发任务不会提前关闭 Loading。

### 3.4 useTablePage

入参：

```ts
{
    request,
    initialSearch?,
    pageSize?,
    responseAdapter?,
    immediate?: true
}
```

出参：`loading`、`rows`、`total`、`pagination`、`search`、`load()`、`reload(resetPage?)`、
`resetSearch()`。

### 3.5 useCrudPage

在 `useTablePage` 基础上增加 `create`、`update`、`remove` 请求和 `dialog` 状态。

```ts
const crud = useCrudPage({
    request: getDevicePage,
    create: createDevice,
    update: updateDevice,
    remove: deleteDevice
})
```

出参额外包含 `dialog`、`submit(data, mode?)`、`remove(row)`。

### 3.6 useChart

管理 ECharts 实例、响应式尺寸、主题监听、Loading 与页面卸载。`HsxChart` 已经内置该 Hook；
只有需要直接操控复杂实例的业务组件才单独调用。

### 3.7 useFeedback

| 方法 | 适用场景 | 返回值 |
| --- | --- | --- |
| `light/message/success/warning/error/info` | 保存结果、短提示 | Element Message 实例 |
| `notice/notification/noticeSuccess/noticeWarning/noticeError` | 需要阅读的行情、任务和系统通知 | Element Notification 实例 |
| `confirm(options)` | 删除、清退、驳回等需要决策的动作 | `Promise<boolean>` |
| `alert(message, title?, type?)` | 必须阅读后关闭的信息 | `Promise<void>` |

业务不直接导入 `ElMessage/ElNotification/ElMessageBox`，未来替换 UI 库时只修改适配层。

## 4. Web 指令与工具

### 4.1 指令

| 指令 | 入参 | 说明 |
| --- | --- | --- |
| `v-hsx-permission` | `string \| string[]` | 无权限时移除元素 |
| `v-hsx-copy` | `string \| () => string` | 点击复制 |
| `v-hsx-debounce` | 函数或 `{ handler, delay }` | 点击防抖 |

全局注册：

```ts
app.use(HsxComponents, {
    permissionChecker: (permission) => userStore.hasPermission(permission)
})
```

### 4.2 工具函数

| 名称 | 入参 | 返回值/说明 |
| --- | --- | --- |
| `debounce(fn, delay?)` | 函数、毫秒 | 防抖函数 |
| `throttle(fn, delay?)` | 函数、毫秒 | 节流函数 |
| `formatDate(value, withTime?)` | 日期值 | 格式化字符串 |
| `formatMoney(value, digits?)` | 金额 | 千分位字符串 |
| `genRules(label, trigger?)` | 标签、触发方式 | 必填校验规则 |
| `transformData(source)` | 对象 | Range 拆分和空值清理 |
| `deepClone(value)` | 任意值 | 深拷贝 |
| `getPathValue(source, path)` | 对象、路径 | 路径取值 |
| `setPathValue(target, path, value)` | 对象、路径、值 | 路径赋值 |
| `clearObject(target, keepKeys?)` | 对象、保留键 | 原对象清空 |
| `removeEmptyValues(source)` | 对象 | 删除空字符串、null、undefined |

## 5. 用户移动端组件

以下组件存在于 `uni-app`。移动端为兼容微信小程序，
不支持 Web 端那样的整包属性透传，Props 必须以文档白名单为准。

### 5.1 HsxButton

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `action` | `(event) => any \| Promise<any>` | - | 异步点击任务 |
| `autoLoading` | `boolean` | `true` | 跟踪 Promise |
| `debounce` | `number` | `500` | 防连点间隔 |
| `loading` | `boolean` | `false` | 外部 Loading |
| `disabled` | `boolean` | `false` | 禁用 |
| `type` | `string` | `default` | uview-plus 按钮类型 |
| `size` | `string` | `normal` | 尺寸 |
| `shape` | `string` | `square` | 形状 |
| `color` | `string` | `''` | 自定义颜色 |
| `plain` | `boolean` | `false` | 镂空 |
| `hairline` | `boolean` | `true` | 细边框 |
| `block` | `boolean` | `false` | 是否占满一行；默认由外层 `view` 保持内容宽度 |
| `haptic` | `none \| light \| medium \| heavy` | `none` | 显式触感反馈；微信开发者工具自动跳过，避免模拟器整屏抖动 |

出参：`click(event)`、`error(error)`；默认插槽为按钮内容。

### 5.2 HsxCascader

用途：移动端全宽逐级联动选择，支持任意层级、本地树、逐级异步加载、搜索和编辑回显。
逐级钻取让长机型名称保留完整可读宽度，并避免多列滚轮在窄屏中截断文字。

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `(string \| number)[]` | `[]` | 完整值路径 |
| `options` | `MobileCascaderOption[]` | `[]` | 本地树 |
| `fetchOptions` | `(parent, context) => Promise<Option[]>` | - | 异步加载下一级 |
| `resolvePath` | `(value[]) => Promise<Option[]>` | - | 编辑回显完整节点路径 |
| `labelKey/valueKey` | `string` | `label/value` | 字段映射 |
| `childrenKey/leafKey` | `string` | `children/leaf` | 子级与末级字段映射 |
| `title/placeholder` | `string` | `请选择` | 弹层标题与占位文本 |
| `separator` | `string` | ` / ` | 回显路径分隔符 |
| `maxLevel` | `number` | `8` | 最大层级保护 |
| `clearable/disabled` | `boolean` | `true/false` | 清空与禁用 |
| `showAllLevels` | `boolean` | `false` | 触发区显示完整路径；默认只显示末级名称 |
| `searchable` | `boolean` | `true` | 搜索当前层级 |
| `zIndex` | `string \| number` | `10240` | 选择弹层层级 |

出参：`update:modelValue(value[])`、`change(value[], path)`、`load(options, parent)`、
`open`、`close`、`error(error, stage)`。

暴露：`open()`、`close()`、`clear()`、`reload()`、`displayPath`、`currentOptions`、`loading`。

### 5.2.1 HsxSelect

统一单选弹层。入参：`modelValue/options/title/placeholder/disabled/clearable/searchable/searchPlaceholder/zIndex`；
出参：`update:modelValue`、`change(value, option)`、`open`、`close`。H5 会把弹层挂到页面根节点，
可安全用于 `HsxPopup` 内部，不再使用容易被父弹层裁剪的原生 Picker。

`HsxForm` 已支持 `component: 'cascader'`，其 `props.fetchOptions/resolvePath` 会自动传给组件。

### 5.2.2 HsxSearchBar / HsxPageHeader

`HsxSearchBar` 基于 uview-plus 输入框统一关键词检索、清空、回车、显式搜索按钮、输入防抖、
筛选入口与条件数量角标。它既可单独用于列表，也可交给 `HsxPageHeader` 自动布局。

| HsxSearchBar 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `string \| number` | `''` | 关键词 |
| `placeholder/maxlength` | `string / number` | `搜索关键词 / 80` | 输入约束 |
| `autoSearch/debounce` | `boolean / number` | `false / 400` | 停止输入后自动检索 |
| `searchOnClear` | `boolean` | `true` | 清空后查询全部 |
| `showFilter/filterCount` | `boolean / number` | `false / 0` | 筛选按钮及生效条件数 |
| `showAction/actionText` | `boolean / string` | `false / 搜索` | 是否显示显式搜索按钮 |
| `size` | `small \| normal \| large` | `normal` | 高度规格 |
| `variant` | `filled \| outline \| glass` | `filled` | 视觉样式 |

出参：`update:modelValue(value)`、`input(value)`、`search(value, trigger)`、`clear`、`filter`。
`trigger` 为 `enter/button/icon/clear/debounce`。插槽：`leading/trailing/filter/action`。

`HsxPageHeader` 负责状态栏安全区、小程序右上胶囊避让、返回/首页回退、固定或吸顶布局，
并在手机标题页把搜索放到第二行，在 iPad、折叠屏展开态自动收进头部中间区域。

主要入参：`title/subtitle/showBack/fixed/sticky/fill/safeAreaTop/background/color/zIndex`；
检索入参：`v-model/searchable/searchMode/searchPlaceholder/autoSearch/searchDebounce/showFilter/filterCount`。
`searchMode` 可设为 `auto/inline/below`。`fixed + fill` 时用 `bottomHeight` 声明自定义 bottom
插槽高度，组件会生成正确占位。

出参：`search(value, trigger)`、`clear`、`filter`、`back`。插槽：
`left/title/center/right/bottom/search-leading/search-trailing`。

### 5.2.3 HsxFilterToolbar / HsxFilterDrawer

`HsxFilterToolbar` 用于商品货盘顶部的横向胶囊筛选。每项结构为：

```ts
interface MobileFilterToolbarItem {
    label: string
    value: string | number
    icon?: string
    count?: number
    active?: boolean
    arrow?: boolean
    disabled?: boolean
    subscribedText?: string
}
```

入参：`items/modelValue/selection/dense/showScrollbar/haptic`。`selection` 默认为 `none`，此时
组件只触发 `select(item, index)`，由业务打开分类、成色或更多弹层；设为 `single/multiple`
时同时支持 `v-model`。插槽：`item/after`。

`HsxFilterDrawer` 使用 `HsxPopup + HsxSchemaForm` 组合高级筛选。手机从底部弹出，窗口达到
`adaptiveAt` 后自动切换右侧抽屉；业务只维护同一份筛选 Schema。

| 入参 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `visible` | `boolean` | 必填 | 使用 `v-model:visible` |
| `modelValue` | `Record<string, any>` | `{}` | 已应用筛选，使用默认 `v-model` |
| `schema` | `MobileFormField[]` | `[]` | 与移动表单完全相同的 JSON Schema |
| `title/description` | `string` | `高级筛选/组合条件精确定位结果` | 抽屉头部 |
| `resetValue` | `Record<string, any>` | `{}` | 重置目标值 |
| `clean` | `boolean` | `true` | 确认时删除空字符串、空数组、false 和空对象 |
| `validate` | `boolean` | `false` | 确认前执行 Schema 校验 |
| `closeOnReset` | `boolean` | `false` | 重置后是否关闭 |
| `adaptiveAt/adaptiveWidth` | `number / number` | `600 / 460` | 右侧抽屉断点和宽度 |

出参：`update:visible`、`update:modelValue`、`confirm(model)`、`reset(model)`、
`change(prop, value, model)`。插槽：`before/form/default/after/footer`；复杂业务字段可通过 `form`
插槽接管表单区域，避免小程序动态插槽编译差异。
暴露：`open/close/confirm/reset/draft/activeCount/formRef`。

辅助函数 `countActiveMobileFilters(model, ignoredKeys?)` 按字段统计有效条件；
`compactMobileFilters(model)` 生成列表接口请求参数，并保留数字 `0` 等合法值。

```vue
<HsxPageHeader
    v-model="keyword"
    title="回收订单"
    searchable
    show-filter
    :filter-count="countActiveMobileFilters(filters)"
    @search="reload"
    @filter="filterVisible = true"
/>

<HsxFilterDrawer
    v-model:visible="filterVisible"
    v-model="filters"
    :schema="filterSchema"
    @confirm="reload"
/>
```

### 5.3 HsxPopup

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `boolean` | 必填 | 显示状态 |
| `mode` | `center \| top \| right \| bottom \| left \| fullscreen` | `bottom` | 弹出方向 |
| `title` | `string` | `''` | 标题 |
| `height` | `string \| number` | `auto` | 内容高度 |
| `width` | `string \| number` | `auto` | 内容宽度 |
| `round` | `string \| number` | `16` | 圆角 |
| `closeable` | `boolean` | `true` | 显示关闭按钮 |
| `closeOnClickOverlay` | `boolean` | `true` | 点击遮罩关闭 |
| `safeAreaInsetBottom` | `boolean` | `true` | 底部安全区 |
| `adaptive/adaptiveAt` | `none \| dialog \| side / number` | `none / 600` | 宽屏切换为居中对话框或侧栏 |
| `adaptiveWidth` | `number` | `560` | 宽屏弹层宽度（px） |
| `adaptiveHeight` | `string \| number` | `auto` | 宽屏弹层高度；默认按内容收紧，避免 iPad 大块留白 |
| `zIndex` | `string \| number` | `10075` | 内容层级；遮罩自动使用前一层级 |
| `bodyPadding` | `string` | `28rpx 32rpx` | 内容区内边距 |
| `bodyScroll` | `boolean` | `true` | 内容区是否由弹层滚动；内部使用 `HsxPageList/z-paging` 时设为 `false`，避免双滚动和列表高度丢失 |
| `haptic` | `boolean` | `false` | 打开/确认时是否触发触感；普通弹窗保持关闭，避免与入口按钮重复反馈 |

出参：`update:modelValue(boolean)`、`open`、`close`、`confirm`。

插槽：`header({ title, close })`、`default`、`footer`。暴露 `close()`。

底部安全区由 `HsxPopup` 统一计算并加入 footer；组合组件中的 `HsxActionBar` 不应再次开启
`safeArea`，避免底部重复留白或按钮被系统 Home 指示条遮住。

### 5.4 HsxForm

#### 组件入参

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `modelValue` | `Record<string, any>` | `{}` | 数据 |
| `schema` | `MobileFormField[]` | 必填 | 字段配置 |
| `labelPosition` | `auto \| left \| top` | `auto` | 手机自动上置、宽屏自动左置 |
| `labelWidth` | `string \| number \| 响应式映射` | `0/96/112` | 标签宽度 |
| `disabled` | `boolean` | `false` | 整表禁用 |
| `borderBottom` | `boolean` | `true` | 字段分隔线 |
| `permissionChecker` | `(permission) => boolean` | - | 字段权限判断器 |

#### MobileFormField

| 名称 | 类型 | 说明 |
| --- | --- | --- |
| `prop` | `string` | 字段名，支持 `seller.region.code` 路径 |
| `label` | `string` | 标签 |
| `component` | `input \| textarea \| number \| select \| cascader \| date \| upload \| switch \| radio \| slot` | 控件 |
| `placeholder` | `string` | 占位文本 |
| `defaultValue` | `any` | 默认值 |
| `options` | `MobileOption[] \| (model) => MobileOption[]` | 静态或动态选择项 |
| `optionsLoader` | `(context) => Promise<MobileOption[]>` | 异步加载选择项 |
| `optionsDependencies` | `string[]` | 触发异步选项重载的字段路径 |
| `props` | `Record<string, any> \| (model) => Record<string, any>` | 静态或动态白名单控件属性 |
| `rules` | 对象或数组 | uview-plus 校验规则 |
| `visible` | `boolean \| (model) => boolean` | 显示联动 |
| `disabled` | `boolean \| (model) => boolean` | 禁用联动 |
| `required` | `boolean \| (model) => boolean` | 必填联动 |
| `requiredMessage` | `string` | 必填提示 |
| `permission` | `string \| string[]` | 字段权限 |
| `slot` | `string` | 字段插槽 |
| `tip` | `string \| (model) => string` | 静态或动态提示 |
| `change` | `(value, model) => void` | 字段回调 |

当前 `props` 白名单：

- Input：`type`、`maxlength`、`clearable`、`inputAlign`。
- Textarea：`maxlength`、`count`。
- Switch：`activeValue`、`inactiveValue`、`size`。
- RadioGroup：`placement`。
- Select：`title`、`clearable`、`searchable`、`zIndex`。
- Cascader：`fetchOptions`、`resolvePath`、字段映射、`title`、`separator`、`maxLevel`、`clearable`、`showAllLevels`、`searchable`、`zIndex`。
- Upload：`uploader`、`resultAdapter`、`accept`、`maxCount`、`maxSize`、`multiple`、`capture`。

出参：`update:modelValue(model)`、`change(prop, value, model)`、
`options-load(prop, options)`、`options-error(prop, error)`。

暴露：`validate()`、`resetFields(value?)`、`getValues()`、`reloadOptions(prop?)`、`formRef`、`model`。

同一 Schema 可通过 `HsxSchemaForm` 在 TSX 中调用，并可用 `defineMobileFormSchema()` 保留字段模型类型。

### 5.5 HsxPageList

用途：基于 `z-paging` 2.8.7 统一下拉刷新、触底分页、空状态、错误重试和请求完成状态。

#### 入参 Props

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `request` | `(params) => Promise<any>` | 必填 | 分页请求 |
| `query` | `Record<string, any>` | `{}` | 查询条件，深度变化后自动刷新 |
| `adapter` | `(response) => { list, total }` | 默认适配器 | 返回转换 |
| `pageSize` | `number` | `20` | 每页数量 |
| `height` | `string` | `100%` | z-paging 高度 |
| `rowKey` | `string \| (row) => string \| number` | `id` | 行键 |
| `autoLoad` | `boolean` | `true` | 挂载后加载 |
| `refresherEnabled` | `boolean` | `true` | 下拉刷新 |
| `emptyText` | `string` | `暂无数据` | 空状态文本 |
| `fixed` | `boolean` | `false` | 是否铺满屏幕；普通容器默认关闭 |
| `safeAreaInsetBottom` | `boolean` | `false` | 底部安全区适配 |
| `columns` | `number \| 响应式映射` | `1` | 内置 item 模式的列数；完全接管 default 插槽时无效 |
| `gap` | `number \| 响应式映射` | `10/12/16` | 列表卡片间距，单位 px |

请求入参为 `{ page, limit, ...query }`。默认适配规则与 Web 表格相同，最终需要 `{ list, total }`。

#### 出参 Events

| 事件 | 参数 | 说明 |
| --- | --- | --- |
| `load` | 当前完整列表 | 刷新或加载更多完成 |
| `error` | `unknown` | 请求失败 |

#### Slots

| 名称 | 参数 | 说明 |
| --- | --- | --- |
| `header` | `{ refresh }` | z-paging 顶部固定区 |
| `default` | `{ list, refresh, loadMore }` | 完全接管列表 |
| `item` | `{ item, index }` | 单项渲染 |
| `empty` | `{ isLoadFailed, reload }` | 自定义空态/失败态 |
| `footer` | `{ list, total, reload }` | z-paging 底部固定区 |

暴露 `pagingRef`、`list`、`total`、`loading`、`error`、`reload()`、`refresh()`、`loadMore()`。

### 5.6 HsxUpload

用途：移动端图片/视频/文件上传，基于 `u-upload`，但把请求、进度、后端返回适配和
失败重试统一起来。

主要入参：`modelValue`、`uploader(file, context)`、`resultAdapter`、`accept`、
`capture`、`maxCount = 9`、`maxSize = 10MB`、`multiple`、`disabled`、`deletable`。

`context` 为 `{ uid, index, onProgress }`。出参：`change`、`success`、`error`、
`remove`、`progress`、`oversize`。暴露 `retry(index)`、`clear()`、`fileList`。

`HsxForm` 已支持 `component: 'upload'`，把 `uploader` 放入字段 `props` 即可。

### 5.7 HsxCard

入参：

| 名称 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `title` | `string` | `''` | 标题 |
| `subtitle` | `string` | `''` | 副标题 |
| `status` | `string` | `''` | 右上状态 |
| `padding` | `string` | 响应式主题令牌 | 内边距 |
| `clickable` | `boolean` | `false` | 点击态 |

出参：`click`。插槽：`header`、`default`、`footer`。

### 5.8 HsxEmpty

入参：`text = '暂无数据'`、`mode = 'data'`、`marginTop = 120`。默认插槽可替换空状态内容。

### 5.8.1 HsxMediaCard / HsxSwipeActions / HsxActionBar

- `HsxMediaCard`：图文列表卡片，入参 `image/title/subtitle/description/price/status/imageSize/clickable`，
  支持 `image/status/meta/actions` 插槽，图片失败或缺失时使用统一语义图标占位。
- `HsxSwipeActions`：对 uview-plus 左滑操作统一封装，入参 `items/rowKey/actions/disabled/autoClose/threshold/duration`，
  默认提供编辑、删除语义；输出 `action(action, item, index)`，默认插槽得到 `{ item, index }`。
- `HsxActionBar`：底部操作区，入参 `fixed/safeArea/bordered/elevated/gap/zIndex`，提供 `left/default/right` 插槽。

### 5.9 HsxCheckbox / HsxSwitch

移动端 `HsxCheckbox` 基于 uview-plus 复选组，支持 `options/placement/min/max/disabled`，
并提供响应式 `size/iconSize/labelSize/columns/gap/rowGap/itemMinWidth`。这些属性可传固定值，
也可传 `{ compact, medium, expanded, large, extra-large }`。默认标签字号从手机 14px 调整到
宽屏 16px，并由组件直接传给 uview，避免其内联固定字号无法被外部 CSS 覆盖。

通过 `update:modelValue/change/limit-exceed` 输出结果。`HsxSwitch` 的 `size` 同样支持响应式映射，
并与平台端保持 `action/confirm/loading` 协议；确认过程使用统一 `useModal`，成功后才更新值。
二者已接入移动端 `HsxForm`。

### 5.10 HsxTag / HsxTimeline

移动端使用与平台一致的六种 tone 语义。`HsxTimeline` 同样读取
`actor/title/time/tag/content/details`，尺寸读取响应式主题令牌，并通过主题变量适配明暗模式。
业务端可以直接共用时间线 DTO，不需要共用平台 DOM 组件。

### 5.11 移动端缓存

`createHsxCache` 的 `get/set/remember/remove/clear/keys` 与平台同名，底层使用
`uni.getStorageSync/setStorageSync`，失败时降级为内存缓存。跨端业务可复用缓存策略，
但实例需要分别在各端创建。

### 5.12 HsxChart / HsxChartCard（移动端）

移动端图表以 Apache 2.0 开源的 `qiun-data-charts/uCharts` 为渲染内核，`HsxChart`
负责固定高度、自动空态、Loading、错误态、Canvas ID、多端属性白名单和明暗主题配置。
业务页面不要再复制 `qiun-data-charts` 源码，也不要重复编写图表容器状态。

| 组件 | 主要入参 | 出参 | 说明 |
| --- | --- | --- | --- |
| `HsxChart` | `type/chartData/opts/mode/height/loading/empty/error/fallback` | `select/complete/error/touch-*` | 通用跨端图表底座；Canvas 未完成时自动提供数据降级视图 |
| `HsxChartCard` | `title/subtitle/trend` + `HsxChart` 数据参数 | `select`；`extra/empty/footer` 插槽 | 驾驶舱图表卡片 |

公开配置助手：`createMobileChartOptions`、`createMobileColumnChart`、
`createMobileLineChart`、`createMobileRingChart`、`hasMobileChartData`。助手只生成数据与配置，
不会请求业务接口。

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { HsxChartCard, createMobileLineChart } from '@/addon/hsx_components'

const chart = computed(() => createMobileLineChart(
    ['周一', '周二', '周三'],
    [{ name: '成交设备', data: [18, 26, 31] }]
))
</script>

<template>
    <HsxChartCard
        title="成交趋势"
        :type="chart.type"
        :chart-data="chart.chartData"
        :opts="chart.opts"
    />
</template>
```

Canvas 不识别 CSS 变量，因此暗黑页面必须传 `mode="dark"` 或使用组件库的配置助手生成
暗色轴线、网格和图例颜色。移动端图表容器必须有明确高度，`HsxChart` 默认已处理。
微信开发者工具会把 Canvas 2D 降级为兼容 Canvas；若模拟器或低端设备仍未完成绘制，组件底层的
`HsxChartFallback` 会继续显示同一份数据，避免驾驶舱出现整块空白。确认真机 Canvas 正常后可传
`fallback="false"` 关闭该层。

### 5.12.1 HsxDiyRenderer（复用 NiuCloud 低代码组件）

系统现有 `app/components/diy` 组件及 `addon/components/diy/group` 聚合 Renderer 继续作为第一层
框架能力使用。`HsxDiyRenderer` 只统一数据协议和降级路径，不复制这些组件，也不把它们硬编码进
可复用组件库。

- 传 `schema`：使用 HSX 白名单低代码块，适合跨项目页面。
- 提供 `system` 插槽：直接挂载 NiuCloud 的 `DiyGroup`，可复用 RubikCube、GraphicNav、ImageAds、
  Text、Notice 及表单类 DIY 组件。
- `normalizeHsxDiyPageData(data)`：补齐 `componentIsShow/margin/global/value`，过滤无效组件。

```vue
<script setup lang="ts">
import DiyGroup from '@/addon/components/diy/group/index.vue'
import HsxDiyRenderer from '@/addon/hsx_components/components/HsxDiyRenderer/index.vue'
</script>

<template>
    <HsxDiyRenderer :data="pageDiyData" :pull-down-refresh-count="pullDownRefreshCount">
        <template #system="{ data, pullDownRefreshCount }">
            <DiyGroup :data="data" :pull-down-refresh-count="pullDownRefreshCount" />
        </template>
    </HsxDiyRenderer>
</template>
```

这样当前项目可以直接使用系统 DIY；把组件库带到其他项目时，只需不提供 `system` 插槽，构建不会
因为缺少 NiuCloud 目录而失败。

### 5.13 折叠屏与宽屏自适应

移动端公共层只判断 App 当前可用窗口，不判断品牌、机型或物理屏幕。折叠、展开、横屏和
系统分屏都会更新同一份布局状态。默认断点为：`compact < 600px`、`medium 600～839px`、
`expanded 840～1199px`、`large 1200～1599px`、`extra-large >= 1600px`。

| 能力 | 主要入参 | 出参/插槽 | 说明 |
| --- | --- | --- | --- |
| `useAdaptiveLayout` | `initialWidth/initialHeight/*Breakpoint/listen` | 窗口尺寸、尺寸等级、安全区、横竖屏、`refresh/start/stop` | 统一窗口状态 |
| `HsxAdaptivePage` | `maxWidth/gutter/minHeight/safeAreaTop/safeAreaBottom` | 默认作用域插槽：`layout/widthClass/isWide` | 页面最大宽度、留白和安全区 |
| `HsxResponsiveGrid` | `columns/gap/rowGap/viewportWidth` | 默认插槽 | 按尺寸等级改变列数；断点状态通过 `useAdaptiveLayout` 获取 |
| `HsxSplitPane` | `v-model/splitAt/primaryWidth/gap/hasSecondary/preserve` | `primary/secondary/empty`；`mode-change/pane-change` | 窄屏单页、宽屏列表详情双栏 |

`HsxPopup` 新增 `adaptive="dialog | side"`。窄屏保持原始底部/全屏模式，达到
`adaptiveAt` 后自动改为居中对话框或右侧面板；`adaptiveWidth` 控制宽屏宽度，
`adaptiveHeight` 默认 `auto`，因此手机可使用固定高底部弹层，iPad/折叠屏会按内容收紧。

响应式入参既可以传固定值，也可以传尺寸映射。未配置更大等级时会向前继承最近配置：

```vue
<script setup lang="ts">
import {
    HsxAdaptivePage,
    HsxResponsiveGrid,
    HsxSplitPane
} from '@/addon/hsx_components'
</script>

<template>
    <HsxAdaptivePage :max-width="1280">
        <HsxResponsiveGrid :columns="{ compact: 1, medium: 2, expanded: 3 }">
            <DeviceCard v-for="item in devices" :key="item.id" :item="item" />
        </HsxResponsiveGrid>

        <HsxSplitPane v-model="activePane" :has-secondary="Boolean(selected)">
            <template #primary="{ showSecondary }">
                <DeviceList @select="item => { selected = item; showSecondary() }" />
            </template>
            <template #secondary="{ showPrimary }">
                <DeviceDetail :item="selected" @back="showPrimary" />
            </template>
        </HsxSplitPane>
    </HsxAdaptivePage>
</template>
```

业务页不需要监听 `resize`，也不要通过 `screenWidth` 判断设备类型。需要查看折叠屏效果时，
直接调整浏览器/模拟器窗口宽度，组件演示页会实时显示当前尺寸等级并切换单页或双栏。

## 5.14 通用业务组合组件

这一层只组合公共组件，不内置具体业务接口。管理员、普通用户、主理人、门店、供应商和商品
通过 `fieldMap`、`request`、Schema 与插槽适配，因此能够跨插件复用。

| 组件 | Web 主要入参 | UniApp 主要入参 | 出参/插槽 |
| --- | --- | --- | --- |
| `HsxEntityPicker` | `modelValue/visible/request/data/treeData/columns/querySchema/multiple/fieldMap` | `modelValue/visible/request/items/requestParams/fieldMap/multiple/max` | `clear/confirm/selection-change/update:modelValue`、`trigger/item/tree/search` |
| `HsxProductList` | `items/fieldMap/actions/layout/columns/loading`；约定读取行数据的 `statusTone` | `request/items/requestParams/fieldMap/columns/swipeActions/cardProps/cardPropsResolver/swipeAutoClose/swipeThreshold/swipeDuration` | `item-click/action/load/error`、完整商品卡 `item` 插槽 |
| `HsxProductCard` | `product/fieldMap/actions/layout/statusTone` | 移动端由 `HsxProductList` 组合已有 `HsxMediaCard` | `click/action/image-error`、`image/status/content/price/meta/actions` |
| `HsxDetail` | `data/schema/columns/direction/border/permissionChecker` | `data/schema/columns/labelPosition/bordered/divided` | `copy/click`、字段插槽 `item-${prop}` |
| `HsxActionBar` | `actions/context/maxVisible/permission/confirm/sticky` | `actions/context/maxVisible/fixed/safeArea` | `action/error`、`left/right/action-${key}/more` |
| `HsxComponentCatalog` | 由平台组件文档菜单承载 | `items/visible/fixed/activeKey/height/adaptiveAt/adaptiveWidth` | `select/update:visible`、`trigger` |

移动端 `HsxProductList` 默认使用 `fieldMap + cardProps/cardPropsResolver` 生成稳定商品卡；需要完全自定义图片、
状态、价格或操作区域时使用 `#item="{ item, index }"` 接管整张卡片。小程序列表循环中不再逐层
转发 `image/status/meta` 等命名插槽，避免微信组件运行时把同名插槽重复注册后整卡不渲染。
默认卡片同时直接进入 uview-plus 的滑动项，不再由 `HsxSwipeActions` 二次转发默认插槽；这样既保留
编辑、下架等左滑动作，也规避微信小程序多层自定义组件插槽偶发丢失的问题。

Web 端商品行可提供 `statusTone: neutral/primary/info/success/warning/danger`，列表会将其传给状态标签；
复杂规则仍可直接使用 `HsxProductCard.statusTone` 函数。`HsxTag` 的 `primary` 为组件自定义语义色，
不会再向 Element Plus 透传非法的空 `type`。

`HsxComponentCatalog` 的 `items` 使用 `key/label/group/description/icon/badge/target`。组件只负责分类、搜索、
弹层和选择反馈；页面在 `select` 事件中根据 `target` 调用 `uni.pageScrollTo`，因此目录不耦合具体页面结构。
`HsxEntityPicker` 的“清空”和“确定”按钮均由 `HsxActionBar.actions` 内部生成：清空只重置本次草稿选择并
触发 `clear/selection-change`，确定才写回 `modelValue`、触发 `confirm` 并关闭弹层。不要再把按钮放入
操作栏默认插槽，以避免微信小程序跨组件插槽中的点击事件丢失。

`HsxEntityPicker` 的 Web 版本直接复用 `HsxTreeTablePicker`，所以继续支持左树、右表、远程分页、
跨页选择与自定义列；移动版本直接复用 `HsxPopup/HsxSearchBar/HsxPageList`，手机显示底部弹层，
iPad、横屏和折叠屏展开态自动切换右侧面板。

```vue
<HsxEntityPicker
    v-model="selectedManagers"
    v-model:visible="visible"
    :tree-data="organizationTree"
    :request="getManagerPage"
    multiple
/>

<HsxProductList
    :items="products"
    :columns="{ xs: 1, sm: 2, md: 3, lg: 4, xl: 5 }"
    :actions="productActions"
    @item-click="openDetail"
/>

<HsxDetail :data="detail" :schema="detailSchema" :columns="3" />
<HsxActionBar :actions="actions" :context="detail" />
```

动作项使用 `key/label/icon/type/permission/visible/disabled/confirm/action/props`。Web 端的
危险动作可声明 `confirm`，组件会统一弹出确认框；移动端需要更复杂的确认时，业务在 `action`
中调用 `useFeedback().confirm()`，避免组件擅自决定业务风险文案。

## 6. 移动端共用 Hooks

### 6.1 useRequest

```ts
const { loading, data, error, execute } = useRequest(request, {
    initialData?, onSuccess?, onError?
})
```

`execute(...args)` 原样调用请求并返回 Promise。Loading 使用并发计数。

### 6.2 usePaging

入参：`request`、`search?`、`pageSize?`、`adapter?`。

出参：

| 名称 | 类型 | 说明 |
| --- | --- | --- |
| `list` | `ShallowRef<T[]>` | 当前累计数据 |
| `total` | `Ref<number>` | 总数 |
| `page` | `{ current, limit }` | 下一页状态 |
| `search` | `Reactive<S>` | 查询条件 |
| `loading` | `Ref<boolean>` | 请求中 |
| `refreshing` | `Ref<boolean>` | 下拉刷新中 |
| `finished` | `ComputedRef<boolean>` | 是否全部加载 |
| `error` | `Ref<unknown>` | 最近错误 |
| `refresh()` | Promise | 从第一页替换数据 |
| `loadMore()` | Promise | 加载下一页 |
| `reset()` | void | 清空状态，不自动请求 |

### 6.3 其他 Hooks

| Hook | 入参 | 出参 |
| --- | --- | --- |
| `useStorage<T>(key, defaultValue)` | 键、默认值 | 自动同步本地缓存的 `Ref<T>` |
| `useCountDown(seconds?)` | 默认秒数 | `seconds`、`running`、`text`、`start()`、`stop()` |
| `useToast()` | - | `show(title, icon?, duration?)`、`success()`、`error()` |
| `useModal()` | - | `confirm(content, title?) => Promise<boolean>` |
| `useFeedback()` | 文案或重通知对象 | 与 Web 同语义的 `light/notice/confirm/alert`；重通知映射系统 Modal |

## 7. 排版、布局、反馈与 TSX

| 组件 | 主要入参 | 出参/插槽 | 说明 |
| --- | --- | --- | --- |
| `HsxText` | `text/tone/size/weight/lines/ellipsis/tooltip/maxWidth` | 默认插槽 | Web 溢出自动启用 Tooltip |
| `HsxTitle` | `title/subtitle/eyebrow/level/size/divider` | `prefix/default/subtitle/extra` | 页面、区块、卡片标题层级 |
| `HsxGrid` | `columns/gap/rowGap/minItemWidth/align` + 背景视觉参数 | 默认作用域插槽 | 1～5 列、多行响应式网格 |
| `HsxGridItem` | `span/rowSpan/full/order` + 背景视觉参数 | 默认插槽 | 跨列、整行与独立卡片表面 |
| `HsxStack` | `direction/align/justify/gap/wrap/tag` | 默认插槽 | TSX 实现的强类型 Flex 布局 |
| `HsxList` | `items/itemKey/loading/emptyText/divided/hoverable/clickable` | `default/action/empty` | 骨架、空态、焦点和点击态 |
| `HsxOverflow` | `direction/maxHeight/maxWidth/fade/scrollbar` | 默认插槽 | 横纵溢出和键盘滚动 |
| `HsxProgress` | `percentage/label/description/tone/autoTone/showValue` | - | 语义进度和自动阈值颜色 |
| `HsxMotion` | `show/appear/preset/duration/delay/tag` | 默认插槽 | 自动尊重减少动画设置 |

`HsxDatePicker` 默认宽度为 date 180px、datetime 220px、range 360px。使用 `width` 自定义，
只有明确需要填满容器时才传 `fullWidth`。

所有公开组件都可在 Template 和 TSX 中调用并共享同一份类型。后台与 UniApp 均已启用 Vue JSX
编译插件。`HsxStack` 本体使用 TSX 实现，用于持续验证真实编译链。

移动端新增：

- `useHaptics()`：`trigger/selection/success/warning/error/long`，不支持的端及微信开发者工具安全返回 `false`，真机能力不受影响。
- `useModal()`：`confirm/alert/danger`；`useActionSheet().show()` 返回索引或 `null`。
- `HsxBlockRenderer`：仅渲染 title/text/progress/grid/stack/card；自定义扩展使用具名 slot 节点。
- `HsxDiyRenderer`：在跨项目白名单 Schema 与 NiuCloud 系统 DIY Renderer 之间提供稳定适配层。

震动不是默认装饰。普通选择使用 light，警告使用 medium，危险确认使用 heavy，被动刷新不震动。

## 8. 业务插件完整示例

### 8.1 Web 货盘列表

```vue
<script setup lang="ts">
import { ProTable, ProDialogForm, type ProTableColumn } from '@/addon/hsx_components'

const columns: ProTableColumn[] = [
    { prop: 'model_name', label: '机型', search: true },
    { prop: 'grade', label: '成色', search: true },
    { prop: 'seller_price', label: '卖家到手价' },
    { prop: 'operation', label: '操作', slot: 'operation' }
]
</script>

<template>
    <ProTable :columns="columns" :request="getDevicePage">
        <template #operation="{ row }">
            <el-button link @click="openPricing(row)">定价</el-button>
        </template>
    </ProTable>
</template>
```

## 9. 兼容性与变更规则

1. Web 管理后台基于 Vue 3.2 与 Element Plus 2.7。
2. 用户移动端基于 UniApp、Vue 3 和 uview-plus 3。
3. 移动端新增属性必须同时通过 H5 和微信小程序真实页面构建。
4. 删除或改名 Props、Events、Slots 属于破坏性变更，必须提升主版本。
5. 新增可选入参属于兼容变更，提升次版本。
6. 业务插件应固定依赖的最低 `hsx_components` 版本，并只使用本文档公开 API。
