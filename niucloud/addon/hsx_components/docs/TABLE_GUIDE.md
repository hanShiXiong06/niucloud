# HsxTable 使用与边界指南

版本：`0.1.0`

## 为什么不直接复制业务订单表

参考的 `RecycleOrderDesktopTable.vue` 有可借鉴的交互：订单展开后显示设备子表、主操作与
次操作分层、设备选择按订单隔离。但它同时接入回收接口、用户 Store、状态枚举、图片规则、
打印、打款和几十个回调，属于一个完整业务页面，不适合作为公共组件。

组件库只抽取稳定能力：

1. `HsxTable`：列配置、插槽、展开、树形、选择联动、行内编辑。
2. `ProTable`：在 HsxTable 上组合查询、接口请求、分页、导入导出和列设置。
3. 业务 Hooks：决定“当前主操作是什么”“哪些动作有权限”“点击后调用哪个接口”。
4. 业务插槽：订单信息、设备状态、金额、物流和动作区的具体展示。

这样升级表格不会碰业务规则，修改交易规则也不会污染组件库。

## 四种父子选择模式

| 模式 | 行为 | 适用场景 |
| --- | --- | --- |
| `independent` | 每行独立选择 | 普通列表 |
| `children` | 选父级时同步全部子级 | 批量处理某组数据 |
| `cascade` | 父级联动子级，子级全选后反选父级 | 计划—项目、货盘—设备 |
| `leaf` | 只有末级节点可选 | 只能操作实际设备，目录不可操作 |

## 两类父子结构

树形层级使用 `auto-tree`：

```vue
<HsxTable
    :columns="columns"
    :data="flatRows"
    auto-tree
    :tree-options="{ idKey: 'id', parentKey: 'parent_id' }"
    selection-mode="cascade"
    default-expand-all
/>
```

订单包含设备属于主从表，不要硬转成树：

```vue
<HsxTable :columns="orderColumns" :data="orders" @expand-change="loadDevices">
    <template #expand="{ row }">
        <HsxTable
            :columns="deviceColumns"
            :data="row.devices || []"
            @selection-change="rows => updateOrderSelection(row.id, rows)"
        >
            <template #status="{ row: device }">
                <DeviceStatusTag :device="device" />
            </template>
        </HsxTable>
    </template>
</HsxTable>
```

## 何时不要封装

- 只有一个页面使用的复杂单元格，直接放在业务插槽。
- 会频繁改变的审批/交易按钮规则，放在业务 Hook。
- 需要访问业务 API、Pinia Store 或权限枚举的内容，不能进入 HsxTable。
- 当两个页面只有视觉相似、数据和行为不同，不要为了“看起来复用”制造万能组件。

公共层应稳定、克制；业务层应明确、灵活。

## VXE 开源能力的定位

`vxe-table` 本体使用 MIT 许可证，官方当前 V4 要求 Vue 3.2+。开源版可用于：可编辑表格、
数据校验、树表、虚拟滚动、导入导出、数据代理、列拖拽和复杂 Grid。官方功能表同时明确把
“区域选取、单元格复制粘贴、查找替换、全键盘 Excel 操作”标为企业版；本项目不引入这些
企业插件，也不通过私有实现仿制其收费能力。

因此后续采用双内核而不是整体替换：

1. 普通 CRUD、订单列表和中小数据量继续使用 `HsxTable/ProTable`，保持 Element Plus 风格与低成本。
2. 大数据、密集编辑、强校验、虚拟滚动场景使用独立 `HsxExcelTable` 适配器，底层只依赖
   `vxe-table` 与必要的 MIT 开源包。
3. 业务只能依赖 HSX 适配器，不直接散落 `vxe-*` 标签，未来升级或替换内核时不影响业务页面。
4. 在适配器通过暗黑主题、类型检查、构建和真实业务压测前，不把 VXE 注册成全局默认表格。

参考：[`vxe-table` 官方仓库与开源/企业功能边界](https://github.com/x-extends/vxe-table)。
