<script lang="ts">export default { name: 'HsxTreeTablePicker', inheritAttrs: false }</script>

<script setup lang="ts">
import { computed, nextTick, ref, useSlots, watch } from 'vue'
import type { TreeInstance } from 'element-plus'
import HsxButton from '../HsxButton/index.vue'
import HsxDialog from '../HsxDialog/index.vue'
import HsxPagination from '../HsxPagination/index.vue'
import HsxSearchInput from '../HsxSearchInput/index.vue'
import HsxTable from '../HsxTable/index.vue'
import ProForm from '../ProForm/index.vue'
import type { AnyRecord, HsxTableColumn, ProFormField } from '../../types'
import { deepClone, removeEmptyValues } from '../../utils'
import type {
    HsxTreeTablePickerKey,
    HsxTreeTablePickerRequest,
    HsxTreeTablePickerRequestParams,
    HsxTreeTablePickerResponseAdapter,
    HsxTreeTablePickerTab
} from './types'

type PickerValue = AnyRecord[] | AnyRecord | null

const props = withDefaults(defineProps<{
    visible: boolean
    modelValue?: PickerValue
    title?: string
    width?: string | number
    bodyHeight?: string
    fullscreen?: boolean
    showFullscreen?: boolean
    multiple?: boolean
    rowKey?: string | ((row: AnyRecord) => HsxTreeTablePickerKey)
    columns: HsxTableColumn[]
    request?: HsxTreeTablePickerRequest
    responseAdapter?: HsxTreeTablePickerResponseAdapter
    data?: AnyRecord[]
    autoLoad?: boolean
    querySchema?: ProFormField[]
    initialQuery?: AnyRecord
    searchColumns?: number
    searchLabelWidth?: string | number
    searchText?: string
    pageSize?: number
    pageSizes?: number[]
    paginationLayout?: string
    treeData?: AnyRecord[]
    treeNodeKey?: string
    treeProps?: AnyRecord
    treeKeywordPlaceholder?: string
    treeDefaultExpandAll?: boolean
    treeCheckStrictly?: boolean
    treeLazy?: boolean
    treeLoad?: (node: AnyRecord, resolve: (data: AnyRecord[]) => void) => void
    treeFilterNode?: (keyword: string, data: AnyRecord, node: AnyRecord) => boolean
    treeParamKey?: string
    tabs?: HsxTreeTablePickerTab[]
    activeTab?: HsxTreeTablePickerKey
    tabParamKey?: string
    selectedText?: string
    emptyText?: string
    confirmText?: string
    cancelText?: string
    confirmLoading?: boolean
    closeOnClickModal?: boolean
    rowClickSelect?: boolean
    selectable?: (row: AnyRecord, index: number) => boolean
    beforeConfirm?: (value: PickerValue, rows: AnyRecord[]) => boolean | Promise<boolean>
}>(), {
    modelValue: null,
    title: '选择数据',
    width: 'min(94vw, 1480px)',
    bodyHeight: 'min(72vh, 820px)',
    fullscreen: false,
    showFullscreen: true,
    multiple: true,
    rowKey: 'id',
    request: undefined,
    responseAdapter: undefined,
    data: () => [],
    autoLoad: true,
    querySchema: () => [],
    initialQuery: () => ({}),
    searchColumns: 3,
    searchLabelWidth: 0,
    searchText: '搜索',
    pageSize: 10,
    pageSizes: () => [10, 20, 50, 100],
    paginationLayout: 'total, sizes, prev, pager, next, jumper',
    treeData: () => [],
    treeNodeKey: 'id',
    treeProps: () => ({ label: 'label', children: 'children' }),
    treeKeywordPlaceholder: '搜索组织、分类或分组',
    treeDefaultExpandAll: true,
    treeCheckStrictly: true,
    treeLazy: false,
    treeParamKey: 'tree_id',
    tabs: () => [],
    activeTab: '',
    tabParamKey: 'tab',
    selectedText: '已选择',
    emptyText: '暂无可选数据',
    confirmText: '确定',
    cancelText: '取消',
    confirmLoading: false,
    closeOnClickModal: false,
    rowClickSelect: false
})

const emit = defineEmits<{
    (event: 'update:visible', value: boolean): void
    (event: 'update:modelValue', value: PickerValue): void
    (event: 'update:activeTab', value: HsxTreeTablePickerKey): void
    (event: 'confirm', value: PickerValue, rows: AnyRecord[]): void
    (event: 'cancel'): void
    (event: 'search', query: AnyRecord): void
    (event: 'reset', query: AnyRecord): void
    (event: 'load', rows: AnyRecord[], total: number): void
    (event: 'error', error: unknown): void
    (event: 'tree-change', key: HsxTreeTablePickerKey | undefined, data?: AnyRecord): void
    (event: 'tab-change', value: HsxTreeTablePickerKey): void
    (event: 'selection-change', rows: AnyRecord[]): void
}>()

const treeRef = ref<TreeInstance>()
const tableRef = ref<InstanceType<typeof HsxTable>>()
const query = ref<AnyRecord>(deepClone(props.initialQuery))
const treeKeyword = ref('')
const selectedTreeKey = ref<HsxTreeTablePickerKey>()
const selectedTreeNode = ref<AnyRecord>()
const currentTab = ref<HsxTreeTablePickerKey>(props.activeTab || props.tabs[0]?.value || '')
const rows = ref<AnyRecord[]>([])
const total = ref(0)
const page = ref(1)
const limit = ref(props.pageSize)
const loading = ref(false)
const selectionMap = ref(new Map<HsxTreeTablePickerKey, AnyRecord>())
const slots = useSlots()
let requestVersion = 0
let syncingSelection = false

const showTree = computed(() => props.treeData.length > 0 || props.treeLazy || Boolean(props.treeLoad) || Boolean(slots.tree))
const selectedRows = computed(() => Array.from(selectionMap.value.values()))
const selectedSingleKey = computed(() => selectedRows.value[0] ? rowKeyOf(selectedRows.value[0]) : undefined)
const resolvedColumns = computed<HsxTableColumn[]>(() => {
    const base = props.columns.filter((column) => column.type !== 'selection' && column.prop !== '__hsx_picker_single')
    return props.multiple
        ? [{ type: 'selection', width: 48, fixed: 'left' }, ...base]
        : [{ prop: '__hsx_picker_single', label: '', width: 48, fixed: 'left', slot: 'picker-single' }, ...base]
})

function rowKeyOf(row: AnyRecord): HsxTreeTablePickerKey {
    return typeof props.rowKey === 'function' ? props.rowKey(row) : row[props.rowKey]
}

function normalizeSelection(value: PickerValue): AnyRecord[] {
    if (Array.isArray(value)) return deepClone(value)
    return value ? [deepClone(value)] : []
}

function syncFromModel(value: PickerValue) {
    const next = new Map<HsxTreeTablePickerKey, AnyRecord>()
    normalizeSelection(value).forEach((row) => next.set(rowKeyOf(row), row))
    selectionMap.value = next
}

function defaultResponseAdapter(response: any) {
    const payload = response?.data?.data ?? response?.data ?? response ?? {}
    if (Array.isArray(payload)) return { list: payload, total: payload.length }
    const list = payload.list ?? payload.rows ?? payload.items ?? []
    return { list: Array.isArray(list) ? list : [], total: Number(payload.total ?? payload.count ?? list.length) || 0 }
}

function requestParams(): HsxTreeTablePickerRequestParams {
    const params: HsxTreeTablePickerRequestParams = { page: page.value, limit: limit.value, ...removeEmptyValues(deepClone(query.value)) }
    if (selectedTreeKey.value !== undefined) params[props.treeParamKey] = selectedTreeKey.value
    if (selectedTreeNode.value) params.treeNode = deepClone(selectedTreeNode.value)
    if (currentTab.value !== '' && currentTab.value !== undefined) params[props.tabParamKey] = currentTab.value
    params.treeKey = selectedTreeKey.value
    params.tab = currentTab.value
    return params
}

async function load() {
    const version = ++requestVersion
    loading.value = true
    try {
        const response = props.request ? await props.request(requestParams()) : props.data
        if (version !== requestVersion) return
        const result = (props.responseAdapter || defaultResponseAdapter)(response)
        rows.value = deepClone(result.list || [])
        total.value = Number(result.total) || 0
        await restoreCurrentPageSelection()
        emit('load', deepClone(rows.value), total.value)
    } catch (error) {
        if (version === requestVersion) {
            rows.value = []
            total.value = 0
            emit('error', error)
        }
    } finally {
        if (version === requestVersion) loading.value = false
    }
}

async function restoreCurrentPageSelection() {
    if (!props.multiple) return
    await nextTick()
    syncingSelection = true
    tableRef.value?.clearSelection?.()
    rows.value.forEach((row) => {
        if (selectionMap.value.has(rowKeyOf(row))) tableRef.value?.toggleRowSelection?.(row, true)
    })
    await nextTick()
    syncingSelection = false
}

function updateSelection(next: Map<HsxTreeTablePickerKey, AnyRecord>) {
    selectionMap.value = next
    emit('selection-change', deepClone(Array.from(next.values())))
}

function handleSelectionChange(selected: AnyRecord[]) {
    if (!props.multiple || syncingSelection) return
    const next = new Map(selectionMap.value)
    rows.value.forEach((row) => next.delete(rowKeyOf(row)))
    selected.forEach((row) => next.set(rowKeyOf(row), deepClone(row)))
    updateSelection(next)
}

function setSingle(row: AnyRecord) {
    updateSelection(new Map([[rowKeyOf(row), deepClone(row)]]))
}

function handleRowClick(row: AnyRecord) {
    if (!props.multiple) return setSingle(row)
    if (!props.rowClickSelect) return
    const selected = selectionMap.value.has(rowKeyOf(row))
    tableRef.value?.toggleRowSelection?.(row, !selected)
}

function handleTreeFilter(keyword: string, data: AnyRecord, node: AnyRecord) {
    if (props.treeFilterNode) return props.treeFilterNode(keyword, data, node)
    if (!keyword) return true
    const labelKey = String(props.treeProps.label || 'label')
    return String(data[labelKey] ?? '').toLowerCase().includes(keyword.toLowerCase())
}

function handleTreeNodeClick(data: AnyRecord) {
    selectedTreeNode.value = data
    selectedTreeKey.value = data[props.treeNodeKey]
    page.value = 1
    emit('tree-change', selectedTreeKey.value, deepClone(data))
    void load()
}

async function search() {
    page.value = 1
    emit('search', removeEmptyValues(deepClone(query.value)))
    await load()
}

async function reset() {
    query.value = deepClone(props.initialQuery)
    page.value = 1
    emit('reset', deepClone(query.value))
    await load()
}

async function handlePageChange(value: { page: number, limit: number }) {
    page.value = value.page
    limit.value = value.limit
    await load()
}

async function handleTabChange(value: HsxTreeTablePickerKey) {
    currentTab.value = value
    page.value = 1
    emit('update:activeTab', value)
    emit('tab-change', value)
    await load()
}

function clearSelection() { updateSelection(new Map()); tableRef.value?.clearSelection?.() }
function close() { emit('update:visible', false) }
function cancel() { emit('cancel'); close() }

async function confirm() {
    const output: PickerValue = props.multiple ? deepClone(selectedRows.value) : deepClone(selectedRows.value[0] || null)
    if (props.beforeConfirm && !(await props.beforeConfirm(output, deepClone(selectedRows.value)))) return
    emit('update:modelValue', output)
    emit('confirm', output, deepClone(selectedRows.value))
    close()
}

async function open() { emit('update:visible', true); await nextTick(); if (props.autoLoad) await load() }

watch(treeKeyword, (value) => treeRef.value?.filter(value))
watch(() => props.activeTab, (value) => { if (value !== undefined && value !== currentTab.value) currentTab.value = value })
watch(() => props.modelValue, (value) => { if (props.visible) syncFromModel(value) }, { deep: true })
watch(() => props.visible, async (visible) => {
    if (!visible) return
    syncFromModel(props.modelValue)
    query.value = deepClone(props.initialQuery)
    page.value = 1
    limit.value = props.pageSize
    await nextTick()
    if (props.autoLoad) await load()
}, { immediate: true })
watch(() => props.data, () => { if (props.visible && !props.request) void load() }, { deep: true })

defineExpose({ open, close, reload: load, search, reset, confirm, getSelectedRows: () => deepClone(selectedRows.value), clearSelection, treeRef, tableRef })
</script>

<template>
    <HsxDialog
        :model-value="visible"
        v-bind="$attrs"
        class="hsx-tree-table-picker"
        :title="title"
        :width="width"
        :fullscreen="fullscreen"
        :show-fullscreen="showFullscreen"
        :close-on-click-modal="closeOnClickModal"
        body-max-height="none"
        :show-footer="true"
        @update:model-value="emit('update:visible', $event)"
    >
        <el-tabs v-if="tabs.length" class="hsx-tree-table-picker__tabs" :model-value="currentTab" @update:model-value="handleTabChange">
            <el-tab-pane v-for="tab in tabs" :key="tab.value" :label="tab.label" :name="tab.value" :disabled="tab.disabled" />
        </el-tabs>

        <div class="hsx-tree-table-picker__layout" :class="{ 'hsx-tree-table-picker__layout--without-tree': !showTree }" :style="{ height: bodyHeight }">
            <aside v-if="showTree" class="hsx-tree-table-picker__tree-pane">
                <slot name="tree-header">
                    <HsxSearchInput v-model="treeKeyword" :placeholder="treeKeywordPlaceholder" :show-button="false" auto-search />
                </slot>
                <div class="hsx-tree-table-picker__tree-scroll">
                    <slot name="tree" :keyword="treeKeyword" :select="handleTreeNodeClick">
                        <el-tree
                            ref="treeRef"
                            :data="treeData"
                            :node-key="treeNodeKey"
                            :props="treeProps"
                            :default-expand-all="treeDefaultExpandAll"
                            :check-strictly="treeCheckStrictly"
                            :lazy="treeLazy"
                            :load="treeLoad"
                            :filter-node-method="handleTreeFilter"
                            highlight-current
                            @node-click="handleTreeNodeClick"
                        >
                            <template #default="scope"><slot name="tree-node" v-bind="scope"><span class="hsx-tree-table-picker__tree-label">{{ scope.data[treeProps.label || 'label'] }}</span></slot></template>
                        </el-tree>
                    </slot>
                </div>
            </aside>

            <main class="hsx-tree-table-picker__main">
                <div class="hsx-tree-table-picker__search" @keyup.enter="search">
                    <slot name="search" :model="query" :search="search" :reset="reset">
                        <ProForm v-if="querySchema.length" v-model="query" class="hsx-tree-table-picker__search-form" :schema="querySchema" :columns="searchColumns" :label-width="searchLabelWidth" />
                        <div class="hsx-tree-table-picker__search-actions">
                            <HsxButton type="primary" :loading="loading" @click="search">{{ searchText }}</HsxButton>
                            <el-button v-if="querySchema.length" :disabled="loading" @click="reset">重置</el-button>
                            <slot name="search-actions" :model="query" :search="search" :reset="reset" />
                        </div>
                    </slot>
                </div>

                <slot name="toolbar" :rows="rows" :selected-rows="selectedRows" :reload="load" />
                <div class="hsx-tree-table-picker__table">
                    <HsxTable
                        ref="tableRef"
                        :columns="resolvedColumns"
                        :data="rows"
                        :loading="loading"
                        :row-key="rowKey"
                        :empty-text="emptyText"
                        :selectable="selectable"
                        height="100%"
                        highlight-current-row
                        @selection-change="handleSelectionChange"
                        @row-click="handleRowClick"
                    >
                        <template #picker-single="{ row }"><el-radio :model-value="selectedSingleKey" :label="rowKeyOf(row)" @change="setSingle(row)"><span /></el-radio></template>
                        <template v-for="(_, name) in $slots" #[name]="slotProps"><slot :name="name" v-bind="slotProps || {}" /></template>
                    </HsxTable>
                </div>
                <div class="hsx-tree-table-picker__pagination">
                    <HsxPagination
                        :current-page="page"
                        :page-size="limit"
                        :total="total"
                        :page-sizes="pageSizes"
                        :layout="paginationLayout"
                        @change="handlePageChange"
                    />
                </div>
            </main>
        </div>

        <template #footer>
            <div class="hsx-tree-table-picker__footer">
                <div class="hsx-tree-table-picker__selected">
                    <slot name="selected" :rows="selectedRows" :count="selectedRows.length">
                        {{ selectedText }} <strong>{{ selectedRows.length }}</strong> 项
                        <el-button v-if="selectedRows.length" link type="primary" @click="clearSelection">清空</el-button>
                    </slot>
                </div>
                <div class="hsx-tree-table-picker__footer-actions">
                    <el-button @click="cancel">{{ cancelText }}</el-button>
                    <HsxButton type="primary" :loading="confirmLoading" @click="confirm">{{ confirmText }}</HsxButton>
                </div>
            </div>
        </template>
    </HsxDialog>
</template>

<style>
.hsx-tree-table-picker .el-dialog__body { padding: 0 24px 18px; }
.hsx-tree-table-picker .el-dialog__footer { padding: 14px 24px 18px; border-top: 1px solid var(--hsx-border-color, var(--el-border-color-lighter)); }
</style>

<style scoped>
.hsx-tree-table-picker__tabs { margin-bottom: 16px; }
.hsx-tree-table-picker__tabs :deep(.el-tabs__header) { margin-bottom: 0; }
.hsx-tree-table-picker__layout { display: grid; min-height: 480px; grid-template-columns: minmax(250px, 30%) minmax(0, 1fr); overflow: hidden; border: 1px solid var(--hsx-border-color, var(--el-border-color-lighter)); border-radius: 14px; background: var(--hsx-bg-surface, var(--el-bg-color)); }
.hsx-tree-table-picker__layout--without-tree { grid-template-columns: minmax(0, 1fr); }
.hsx-tree-table-picker__tree-pane { display: flex; min-width: 0; flex-direction: column; gap: 14px; padding: 16px; overflow: hidden; border-right: 1px solid var(--hsx-border-color, var(--el-border-color-lighter)); background: var(--hsx-bg-muted, var(--el-fill-color-extra-light)); }
.hsx-tree-table-picker__tree-scroll { min-height: 0; flex: 1; overflow: auto; }
.hsx-tree-table-picker__tree-scroll :deep(.el-tree) { color: var(--hsx-text-primary, var(--el-text-color-primary)); background: transparent; }
.hsx-tree-table-picker__tree-label { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hsx-tree-table-picker__main { display: flex; min-width: 0; min-height: 0; flex-direction: column; padding: 16px; }
.hsx-tree-table-picker__search { display: flex; flex: none; align-items: flex-start; gap: 10px; margin-bottom: 14px; }
.hsx-tree-table-picker__search > :deep(.hsx-tree-table-picker__search-form) { min-width: 0; flex: 1; }
.hsx-tree-table-picker__search-form :deep(.el-form-item) { margin-bottom: 0; }
.hsx-tree-table-picker__search-form :deep(.el-row) { row-gap: 10px; }
.hsx-tree-table-picker__search-actions { display: flex; flex: none; align-items: center; gap: 8px; }
.hsx-tree-table-picker__table { min-height: 0; flex: 1; overflow: hidden; }
.hsx-tree-table-picker__pagination { display: flex; flex: none; justify-content: flex-end; padding-top: 14px; }
.hsx-tree-table-picker__footer { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 16px; }
.hsx-tree-table-picker__selected { color: var(--hsx-text-secondary, var(--el-text-color-secondary)); font-size: 13px; }
.hsx-tree-table-picker__selected strong { color: var(--el-color-primary); font-size: 16px; }
.hsx-tree-table-picker__footer-actions { display: flex; align-items: center; gap: 10px; }
@media (max-width: 900px) {
    .hsx-tree-table-picker__layout { height: auto !important; min-height: 560px; grid-template-columns: 1fr; grid-template-rows: minmax(180px, 32%) minmax(360px, 1fr); overflow: auto; }
    .hsx-tree-table-picker__tree-pane { border-right: 0; border-bottom: 1px solid var(--hsx-border-color, var(--el-border-color-lighter)); }
    .hsx-tree-table-picker__search { flex-direction: column; }
    .hsx-tree-table-picker__search-form { width: 100%; }
}
</style>
