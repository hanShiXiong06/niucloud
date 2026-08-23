<script lang="ts">export default { name: 'HsxTable', inheritAttrs: false }</script>
<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import type { TableInstance } from 'element-plus'
import HsxInput from '../HsxInput/index.vue'
import HsxSelect from '../HsxSelect/index.vue'
import CellRenderer from './CellRenderer'
import type { AnyRecord, HsxTableColumn, HsxTableSelectionMode, HsxTreeOptions } from '../../types'
import { buildTree, createTreeParentMap, flattenTree, getPathValue, treeDescendants } from '../../utils'

const props = withDefaults(defineProps<{
    columns: HsxTableColumn<any>[]
    data?: AnyRecord[]
    loading?: boolean
    rowKey?: string | ((row: AnyRecord) => string | number)
    border?: boolean
    stripe?: boolean
    emptyText?: string
    showOverflowTooltip?: boolean
    autoTree?: boolean
    treeOptions?: HsxTreeOptions
    selectionMode?: HsxTableSelectionMode
    selectable?: (row: AnyRecord, index: number) => boolean
}>(), {
    data: () => [], loading: false, rowKey: 'id', border: false, stripe: false, emptyText: '暂无数据',
    showOverflowTooltip: true, autoTree: false, treeOptions: () => ({}), selectionMode: 'independent'
})
const emit = defineEmits<{
    (event: 'selection-change', rows: AnyRecord[]): void
    (event: 'select', rows: AnyRecord[], row: AnyRecord): void
    (event: 'select-all', rows: AnyRecord[]): void
    (event: 'row-click', row: AnyRecord, column: any, nativeEvent: Event): void
    (event: 'sort-change', sort: any): void
    (event: 'expand-change', row: AnyRecord, expanded: boolean | AnyRecord[]): void
}>()
const tableRef = ref<TableInstance>()
const childrenKey = computed(() => props.treeOptions.childrenKey || 'children')
const actualData = computed(() => props.autoTree ? buildTree(props.data, props.treeOptions) : props.data)
const actualTreeProps = computed(() => ({ children: childrenKey.value, hasChildren: 'hasChildren' }))
const actualRowKey = computed(() => {
    const rowKey = props.rowKey
    return typeof rowKey === 'function' ? (row: AnyRecord) => String(rowKey(row)) : rowKey
})
const parentMap = computed(() => typeof props.rowKey === 'string' ? createTreeParentMap(actualData.value, props.rowKey, childrenKey.value) : new Map())
let selectionEmitVersion = 0

function rowKeyOf(row: AnyRecord) { return typeof props.rowKey === 'function' ? props.rowKey(row) : row[props.rowKey] }
function childrenOf(row: AnyRecord): AnyRecord[] { return Array.isArray(row[childrenKey.value]) ? row[childrenKey.value] : [] }
function isSelectable(row: AnyRecord, index: number) {
    if (props.selectionMode === 'leaf' && childrenOf(row).length) return false
    return props.selectable ? props.selectable(row, index) : true
}
function columnBindings(column: HsxTableColumn<any>) {
    const { search: _search, hideInTable: _hide, slot: _slot, headerSlot: _header, editable: _editable, editComponent: _editComponent, editProps: _editProps, options: _options, formatter: _formatter, render: _render, hideInSetting: _hideSetting, columnSetting: _columnSetting, ...bindings } = column
    if (!column.type && bindings.showOverflowTooltip === undefined) bindings.showOverflowTooltip = props.showOverflowTooltip
    if (column.type === 'selection') bindings.selectable = isSelectable
    return bindings
}
function displayValue(row: AnyRecord, column: HsxTableColumn<any>, index: number) {
    const value = column.prop ? getPathValue(row, String(column.prop)) : undefined
    if (column.formatter) return column.formatter(row, value, index)
    if (column.options) return column.options.find((option) => option.value === value)?.label ?? value ?? '-'
    return value ?? '-'
}
function updateRowValue(row: AnyRecord, prop: string | undefined, value: any) {
    if (!prop) return
    const keys = prop.split('.'); const last = keys.pop(); if (!last) return
    const parent = keys.reduce((current: AnyRecord, key) => { if (!current[key]) current[key] = {}; return current[key] }, row)
    parent[last] = value
}
function selectedRows() { return (tableRef.value?.getSelectionRows?.() || []) as AnyRecord[] }
function scheduleSelectionChange() {
    const version = ++selectionEmitVersion
    void nextTick(() => { if (version === selectionEmitVersion) emit('selection-change', selectedRows()) })
}
function handleSelectionChange() { scheduleSelectionChange() }
function toggleRows(rows: AnyRecord[], selected: boolean) { rows.forEach((item) => tableRef.value?.toggleRowSelection(item, selected)) }
function handleSelect(selection: AnyRecord[], row: AnyRecord) {
    const selected = selection.some((item) => rowKeyOf(item) === rowKeyOf(row))
    if (props.selectionMode === 'children' || props.selectionMode === 'cascade') toggleRows(treeDescendants(row, childrenKey.value), selected)
    if (props.selectionMode === 'cascade' && typeof props.rowKey === 'string') {
        const keys = new Set(selectedRows().map(rowKeyOf))
        treeDescendants(row, childrenKey.value).forEach((item) => selected ? keys.add(rowKeyOf(item)) : keys.delete(rowKeyOf(item)))
        let parent = parentMap.value.get(rowKeyOf(row))
        while (parent) {
            const checked = childrenOf(parent).every((child) => keys.has(rowKeyOf(child)))
            tableRef.value?.toggleRowSelection(parent, checked)
            checked ? keys.add(rowKeyOf(parent)) : keys.delete(rowKeyOf(parent))
            parent = parentMap.value.get(rowKeyOf(parent))
        }
    }
    void nextTick(() => emit('select', selectedRows(), row))
}
function handleSelectAll(selection: AnyRecord[]) {
    if (props.selectionMode !== 'independent') {
        const selecting = selection.length > 0
        tableRef.value?.clearSelection()
        if (selecting) toggleRows(flattenTree(actualData.value, childrenKey.value).filter(isSelectable), true)
    }
    void nextTick(() => emit('select-all', selectedRows()))
}
function handleRowClick(row: AnyRecord, column: any, nativeEvent: Event) { emit('row-click', row, column, nativeEvent) }
function handleExpandChange(row: AnyRecord, expanded: boolean | AnyRecord[]) { emit('expand-change', row, expanded) }
function clearSelection() { tableRef.value?.clearSelection() }
function toggleRowSelection(row: AnyRecord, selected?: boolean) {
    if (selected === undefined) {
        const toggle = tableRef.value?.toggleRowSelection as ((row: AnyRecord) => void) | undefined
        toggle?.(row)
        return
    }
    tableRef.value?.toggleRowSelection(row, selected)
}
defineExpose({ tableRef, data: actualData, clearSelection, getSelectionRows: selectedRows, toggleRowSelection, toggleAllSelection: () => tableRef.value?.toggleAllSelection() })
</script>

<template>
    <el-table ref="tableRef" v-loading="loading" v-bind="$attrs" :data="actualData" :row-key="actualRowKey" :border="border" :stripe="stripe" :empty-text="emptyText" :tree-props="actualTreeProps" @selection-change="handleSelectionChange" @select="handleSelect" @select-all="handleSelectAll" @row-click="handleRowClick" @sort-change="emit('sort-change', $event)" @expand-change="handleExpandChange">
        <template v-for="column in columns" :key="column.prop || column.type || column.label">
            <el-table-column v-if="column.type && column.type !== 'expand'" v-bind="columnBindings(column)" />
            <el-table-column v-else-if="column.type === 'expand'" v-bind="columnBindings(column)"><template #default="scope"><slot name="expand" v-bind="scope" /></template></el-table-column>
            <el-table-column v-else v-bind="columnBindings(column)">
                <template v-if="column.headerSlot" #header="scope"><slot :name="column.headerSlot" v-bind="scope" :column="column" /></template>
                <template #default="scope">
                    <slot v-if="column.slot || $slots[`cell-${String(column.prop)}`]" :name="column.slot || `cell-${String(column.prop)}`" v-bind="scope" :column="column" :value="column.prop ? getPathValue(scope.row, String(column.prop)) : undefined" />
                    <CellRenderer v-else-if="column.render" :renderer="column.render" :context="{ row: scope.row, column, value: column.prop ? getPathValue(scope.row, String(column.prop)) : undefined, index: scope.$index }" />
                    <HsxInput v-else-if="column.editable && (!column.editComponent || column.editComponent === 'input')" v-bind="column.editProps" :model-value="column.prop ? getPathValue(scope.row, String(column.prop)) : ''" @update:model-value="updateRowValue(scope.row, String(column.prop), $event)" />
                    <el-input-number v-else-if="column.editable && column.editComponent === 'input-number'" v-bind="column.editProps" :model-value="column.prop ? getPathValue(scope.row, String(column.prop)) : undefined" @update:model-value="updateRowValue(scope.row, String(column.prop), $event)" />
                    <HsxSelect v-else-if="column.editable && column.editComponent === 'select'" v-bind="column.editProps" :model-value="column.prop ? getPathValue(scope.row, String(column.prop)) : undefined" :options="column.options || []" @update:model-value="updateRowValue(scope.row, String(column.prop), $event)" />
                    <span v-else>{{ displayValue(scope.row, column, scope.$index) }}</span>
                </template>
            </el-table-column>
        </template>
        <template #empty><slot name="empty">{{ emptyText }}</slot></template>
    </el-table>
</template>
