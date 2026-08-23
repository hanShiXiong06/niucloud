<script lang="ts">
export default { name: 'ProTable', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import HsxButton from '../HsxButton/index.vue'
import HsxColumnSetting from '../HsxColumnSetting/index.vue'
import HsxPagination from '../HsxPagination/index.vue'
import HsxTable from '../HsxTable/index.vue'
import ProForm from '../ProForm/index.vue'
import type {
    AnyRecord,
    HsxColumnSettingItem,
    ProFormField,
    ProTableColumn,
    TableRequest,
    TableResponseAdapter
} from '../../types'
import { deepClone, removeEmptyValues } from '../../utils'
import { defaultTableResponseAdapter } from '../../hooks/useTablePage'

const props = withDefaults(
    defineProps<{
        columns: ProTableColumn<any>[]
        request?: TableRequest<any>
        data?: AnyRecord[]
        loading?: boolean
        responseAdapter?: TableResponseAdapter<any>
        initialSearch?: AnyRecord
        searchSchema?: ProFormField[]
        searchColumns?: number
        autoLoad?: boolean
        rowKey?: string | ((row: AnyRecord) => string)
        showSearch?: boolean
        showToolbar?: boolean
        showPagination?: boolean
        showColumnSetting?: boolean
        columnStorageKey?: string
        pageSize?: number
        pageSizes?: number[]
        paginationLayout?: string
        border?: boolean
        stripe?: boolean
        emptyText?: string
    }>(),
    {
        data: () => [],
        initialSearch: () => ({}),
        searchColumns: 4,
        autoLoad: true,
        rowKey: 'id',
        showSearch: true,
        showToolbar: true,
        showPagination: true,
        showColumnSetting: true,
        columnStorageKey: '',
        pageSize: 20,
        pageSizes: () => [10, 20, 50, 100],
        paginationLayout: 'total, sizes, prev, pager, next, jumper',
        border: false,
        stripe: true,
        emptyText: '暂无数据'
    }
)

const emit = defineEmits<{
    (event: 'load', rows: AnyRecord[], total: number): void
    (event: 'error', error: unknown): void
    (event: 'search', params: AnyRecord): void
    (event: 'reset'): void
    (event: 'selection-change', rows: AnyRecord[]): void
    (event: 'row-click', row: AnyRecord, column: any, nativeEvent: Event): void
    (event: 'sort-change', sort: any): void
    (event: 'column-change', columns: HsxColumnSettingItem[]): void
}>()

const tableRef = ref<InstanceType<typeof HsxTable>>()
const innerRows = ref<AnyRecord[]>([])
const innerLoading = ref(false)
const total = ref(0)
const pagination = reactive({ page: 1, limit: props.pageSize })
const searchModel = ref<AnyRecord>(deepClone(props.initialSearch))
const sortModel = ref<AnyRecord>({})
const columnSettings = ref<HsxColumnSettingItem[]>([])

const actualRows = computed(() => (props.request ? innerRows.value : props.data))
const actualLoading = computed(() => props.loading || innerLoading.value)
const tableColumns = computed(() => {
    const columnMap = new Map(props.columns.map((column, index) => [columnKey(column, index), column]))
    const configured = columnSettings.value
        .filter((item) => item.visible !== false)
        .map((item) => {
            const column = columnMap.get(item.key)
            if (!column) return undefined
            const normalized: ProTableColumn<any> = { ...column, fixed: item.fixed || undefined }
            if (normalized.type === 'index' && !normalized.index) normalized.index = indexMethod
            return normalized
        })
        .filter(Boolean) as ProTableColumn<any>[]
    const fixedColumns = props.columns.filter((column) => column.hideInSetting && !column.hideInTable).map((column) => {
        if (column.type === 'index' && !column.index) return { ...column, index: indexMethod }
        return column
    })
    return [...configured, ...fixedColumns]
})
const searchFields = computed<ProFormField[]>(() => {
    if (props.searchSchema) return props.searchSchema
    return props.columns.reduce((fields, column) => {
        if (!column.search || !column.prop) return fields
        const config = column.search === true ? {} : column.search
        fields.push({
            prop: config.prop || String(column.prop),
            label: config.label || column.label,
            component: config.component || 'input',
            span: config.span,
            placeholder: config.placeholder,
            defaultValue: config.defaultValue,
            options: config.options || column.options,
            props: config.props,
            visible: config.visible,
            disabled: config.disabled,
            slot: config.slot,
            tip: config.tip,
            change: config.change
        })
        return fields
    }, [] as ProFormField[])
})

watch(
    () => props.data,
    (rows) => {
        if (!props.request) total.value = rows.length
    },
    { immediate: true, deep: true }
)

watch(
    () => props.columns,
    (columns) => syncColumnSettings(columns),
    { immediate: true, deep: true }
)

function columnKey(column: ProTableColumn<any>, index: number) {
    return String(column.prop || (column.type ? `__type_${column.type}_${index}` : `__column_${index}`))
}

function syncColumnSettings(columns: ProTableColumn<any>[]) {
    const currentMap = new Map(columnSettings.value.map((item) => [item.key, item]))
    const sourceMap = new Map(columns.map((column, index) => [columnKey(column, index), { column, index }]))
    const next = columnSettings.value
        .filter((item) => sourceMap.has(item.key))
        .map((item) => {
            const source = sourceMap.get(item.key)!.column
            return {
                ...item,
                label: source.label || item.label,
                required: source.columnSetting?.required,
                disabled: source.columnSetting?.disabled
            }
        })
    columns.forEach((column, index) => {
        if (column.hideInSetting) return
        const key = columnKey(column, index)
        if (currentMap.has(key)) return
        next.push({
            key,
            label: column.label || String(column.prop || column.type || `列${index + 1}`),
            visible: column.columnSetting?.visible ?? !column.hideInTable,
            fixed: column.columnSetting?.fixed ?? (column.fixed === true ? 'left' : column.fixed || false),
            required: column.columnSetting?.required,
            disabled: column.columnSetting?.disabled
        })
    })
    columnSettings.value = next
}

function handleColumnChange(value: HsxColumnSettingItem[]) {
    emit('column-change', deepClone(value))
}

function buildParams(): AnyRecord {
    return {
        ...removeEmptyValues(searchModel.value),
        ...sortModel.value,
        page: pagination.page,
        limit: pagination.limit
    }
}

async function load(resetPage = false) {
    if (resetPage) pagination.page = 1
    const params = buildParams()
    emit('search', params)
    if (!props.request) {
        total.value = props.data.length
        emit('load', props.data, total.value)
        return { list: props.data, total: total.value }
    }

    innerLoading.value = true
    try {
        const response = await props.request(params as any)
        const result = (props.responseAdapter || defaultTableResponseAdapter<AnyRecord>)(response)
        innerRows.value = result.list
        total.value = result.total
        emit('load', result.list, result.total)
        return result
    } catch (error) {
        emit('error', error)
        throw error
    } finally {
        innerLoading.value = false
    }
}

function resetSearch() {
    searchModel.value = deepClone(props.initialSearch)
    sortModel.value = {}
    pagination.page = 1
    emit('reset')
    return load()
}

function handlePageChange(page: number) {
    pagination.page = page
    void load()
}

function handleSizeChange(limit: number) {
    pagination.limit = limit
    pagination.page = 1
    void load()
}

function handleSortChange(sort: any) {
    sortModel.value = sort?.prop
        ? { order_by: sort.prop, sort: sort.order === 'ascending' ? 'asc' : sort.order === 'descending' ? 'desc' : '' }
        : {}
    emit('sort-change', sort)
    void load(true)
}

function indexMethod(index: number) {
    return (pagination.page - 1) * pagination.limit + index + 1
}

function clearSelection() {
    tableRef.value?.clearSelection()
}

onMounted(() => {
    if (props.autoLoad) void load()
})

defineExpose({
    tableRef,
    rows: actualRows,
    loading: actualLoading,
    total,
    pagination,
    searchModel,
    columnSettings,
    load,
    reload: load,
    resetSearch,
    clearSelection
})
</script>

<template>
    <section class="hsx-pro-table">
        <div v-if="showSearch && searchFields.length" class="hsx-pro-table__search">
            <ProForm v-model="searchModel" :schema="searchFields" :columns="searchColumns" label-position="top">
                <template v-for="(_, name) in $slots" #[name]="slotProps">
                    <slot :name="name" v-bind="slotProps || {}" />
                </template>
            </ProForm>
            <div class="hsx-pro-table__search-actions">
                <HsxButton type="primary" @click="load(true)">查询</HsxButton>
                <el-button @click="resetSearch">重置</el-button>
                <slot name="search-actions" :search="searchModel" :load="load" :reset="resetSearch" />
            </div>
        </div>

        <div v-if="showToolbar || showColumnSetting || $slots.toolbar || $slots['toolbar-right']" class="hsx-pro-table__toolbar">
            <div class="hsx-pro-table__toolbar-left">
                <slot name="toolbar" :load="load" :rows="actualRows" />
            </div>
            <div class="hsx-pro-table__toolbar-right">
                <HsxColumnSetting
                    v-if="showColumnSetting"
                    v-model="columnSettings"
                    :storage-key="columnStorageKey"
                    @change="handleColumnChange"
                />
                <slot name="toolbar-right" :load="load" :rows="actualRows" />
            </div>
        </div>

        <HsxTable
            ref="tableRef"
            v-bind="$attrs"
            :data="actualRows"
            :columns="tableColumns"
            :loading="actualLoading"
            :row-key="rowKey"
            :border="border"
            :stripe="stripe"
            :empty-text="emptyText"
            @selection-change="(rows: AnyRecord[]) => emit('selection-change', rows)"
            @row-click="(row: AnyRecord, column: any, event: Event) => emit('row-click', row, column, event)"
            @sort-change="handleSortChange"
        >
            <template v-for="(_, name) in $slots" #[name]="slotProps"><slot :name="name" v-bind="slotProps || {}" /></template>
        </HsxTable>

        <div v-if="showPagination" class="hsx-pro-table__pagination">
            <HsxPagination
                :current-page="pagination.page"
                :page-size="pagination.limit"
                :total="total"
                :page-sizes="pageSizes"
                :layout="paginationLayout"
                @current-change="handlePageChange"
                @size-change="handleSizeChange"
            />
        </div>
    </section>
</template>

<style scoped>
.hsx-pro-table {
    width: 100%;
}

.hsx-pro-table__search {
    margin-bottom: 16px;
    padding: 16px 16px 4px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: var(--el-border-radius-base);
    background: var(--el-fill-color-blank);
}

.hsx-pro-table__search-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin: -4px 0 12px;
}

.hsx-pro-table__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 40px;
    margin-bottom: 12px;
}

.hsx-pro-table__toolbar-left,
.hsx-pro-table__toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.hsx-pro-table__pagination {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}
</style>
