<template>
    <el-drawer
        :model-value="modelValue"
        title="设备经营台账"
        size="92%"
        destroy-on-close
        class="erp-profit-report-drawer"
        @close="close"
        @open="handleOpen"
    >
        <div class="profit-report">
            <div class="report-head">
                <div>
                    <div class="report-head__title">{{ activePreset?.name || '设备经营台账' }}</div>
                    <div class="report-head__desc">{{ activePreset?.description || '从采购、库存到销售，以设备为单位统一查看经营事实。' }}</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                    <el-button :icon="Setting" @click="columnDialog = true">字段设置</el-button>
                    <el-button type="primary" :icon="Download" :loading="exporting" @click="exportExcel">导出当前结果</el-button>
                </div>
            </div>

            <div class="filter-panel">
                <div class="preset-row">
                    <span class="preset-row__label">台账视图</span>
                    <el-radio-group v-model="activePresetKey" @change="changePreset">
                        <el-radio-button v-for="item in meta.presets" :key="item.key" :label="item.key">{{ item.name }}</el-radio-button>
                    </el-radio-group>
                </div>
                <div class="period-row">
                    <el-radio-group v-model="quickPeriod" @change="applyQuickPeriod">
                        <el-radio-button v-for="item in periodOptions" :key="item.value" :label="item.value">
                            {{ item.label }}
                        </el-radio-button>
                    </el-radio-group>
                    <div v-if="!allTime" class="period-row__picker-wrap">
                        <el-date-picker
                            v-model="filters.dateRange"
                            type="daterange"
                            value-format="YYYY-MM-DD"
                            start-placeholder="开始日期"
                            end-placeholder="结束日期"
                            class="period-row__picker"
                            @change="onDateRangeChange"
                        />
                    </div>
                </div>
                <el-form :inline="true" class="mt-4 !mb-0" @submit.prevent>
                    <el-form-item v-if="datasetScope === 'assets'" label="业务方向">
                        <el-radio-group v-model="filters.party_scope" @change="onPartyScopeChange">
                            <el-radio-button label="supplier">采购 / 回收来源</el-radio-button>
                            <el-radio-button label="customer">销售客户</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item v-if="datasetScope === 'assets' && !allTime" label="时间口径">
                        <el-select v-model="filters.time_dimension" class="!w-[140px]">
                            <el-option label="入库时间" value="stock_in" />
                            <el-option label="采购时间" value="purchase" />
                            <el-option label="销售时间" value="sale" />
                            <el-option label="最后变动" value="update" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="datasetScope === 'sales'" label="交易口径">
                        <el-radio-group v-model="filters.trade_scope" @change="handleSearch">
                            <el-radio-button label="effective">真实成交</el-radio-button>
                            <el-radio-button label="invalid">已退/作废</el-radio-button>
                            <el-radio-button label="all">全部留痕</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item v-if="datasetScope === 'sales'" label="盈亏">
                        <el-select v-model="filters.profit_state" clearable class="!w-[130px]" placeholder="全部盈亏">
                            <el-option label="盈利设备" value="profit" />
                            <el-option label="亏损设备" value="loss" />
                            <el-option label="持平设备" value="zero" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="关键词">
                        <el-input
                            v-model.trim="filters.keyword"
                            clearable
                            class="!w-[260px]"
                            placeholder="型号 / IMEI / 客户 / 销售单"
                            @keyup.enter="handleSearch"
                        />
                    </el-form-item>
                    <el-form-item label="商品目录">
                        <ErpCatalogProductSelect
                            v-model="filters.catalog_product_id"
                            placeholder="全部目录型号"
                            class="!w-[260px]"
                        />
                    </el-form-item>
                    <el-form-item :label="activePartyScope === 'customer' ? '购买客户' : '供货来源'">
                        <ErpPartySelect
                            v-model="filters.party_id"
                            v-model:party-name="filters.party_name"
                            :party-type="activePartyScope === 'customer' ? 'customer' : 'supplier'"
                            :allow-create="false"
                            :placeholder="activePartyScope === 'customer' ? '选择购买客户' : '选择回收客户/供货商'"
                            class="!w-[220px]"
                        />
                    </el-form-item>
                    <el-form-item label="业务来源">
                        <el-select v-model="filters.source_plugin" clearable class="!w-[150px]" placeholder="全部来源">
                            <el-option v-for="item in sourceOptions" :key="item.value" :label="item.label" :value="item.value" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="activePartyScope === 'customer'" label="销售渠道">
                        <el-select v-model="filters.sale_channel_key" clearable filterable class="!w-[170px]" placeholder="全部渠道">
                            <el-option v-for="item in saleChannelOptions" :key="item.key" :label="item.name" :value="item.key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="activePartyScope === 'customer' ? '销售人员' : '采购人员'">
                        <el-select v-model="filters.staff_uid" clearable filterable class="!w-[150px]" placeholder="全部人员">
                            <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                        <el-button @click="resetFilters">重置</el-button>
                    </el-form-item>
                </el-form>
            </div>

            <div class="summary-grid">
                <div v-for="item in summaryCards" :key="item.key" class="summary-card" :class="item.className">
                    <div class="summary-card__label">{{ item.label }}</div>
                    <div class="summary-card__value">{{ item.value }}<span v-if="item.unit">{{ item.unit }}</span></div>
                    <div class="summary-card__hint">{{ item.hint }}</div>
                </div>
            </div>

            <div class="table-panel">
                <div class="table-panel__head">
                    <div>
                        <span class="font-medium text-gray-800">{{ activePreset?.name || '经营明细' }}</span>
                        <span class="ml-2 text-xs text-gray-400">共 {{ table.total }} 条，长内容悬停可查看完整信息</span>
                    </div>
                    <div class="profit-legend">
                        <span><i class="profit-dot profit-dot--green"></i>盈利</span>
                        <span><i class="profit-dot profit-dot--red"></i>亏损</span>
                        <span><i class="profit-dot profit-dot--gray"></i>持平</span>
                    </div>
                </div>
                <el-table
                    v-loading="table.loading"
                    :data="table.data"
                    size="large"
                    :row-class-name="rowClassName"
                    empty-text="当前条件下没有销售明细"
                >
                    <el-table-column
                        v-for="column in visibleColumns"
                        :key="column.key"
                        :label="column.label"
                        :width="column.width"
                        :fixed="column.fixed || undefined"
                        :align="['money','quantity','integer'].includes(column.format) ? 'right' : 'left'"
                        show-overflow-tooltip
                    >
                        <template #default="{ row }">
                            <span v-if="column.format === 'money'" class="profit-value" :class="column.key === 'profit' ? `profit-value--${row.profit_state}` : ''">{{ money(columnValue(row, column.key)) }}</span>
                            <span v-else-if="column.format === 'quantity'">{{ quantityText(columnValue(row, column.key)) }}</span>
                            <el-tag v-else-if="column.format === 'state'" :type="row.effective_trade ? profitTagType(row.profit_state) : 'info'" :effect="row.effective_trade ? 'light' : 'plain'">{{ columnValue(row, column.key) }}</el-tag>
                            <span v-else>{{ displayValue(row, column) }}</span>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="mt-4 flex justify-end">
                    <el-pagination
                        v-model:current-page="table.page"
                        v-model:page-size="table.limit"
                        layout="total, sizes, prev, pager, next, jumper"
                        :page-sizes="[20, 50, 100]"
                        :total="table.total"
                        @size-change="loadList"
                        @current-change="loadList"
                    />
                </div>
            </div>
        </div>

        <el-dialog v-model="columnDialog" title="台账字段设置" width="720px" append-to-body destroy-on-close>
            <el-alert title="型号、串号和业务状态属于设备台账核心字段，始终保留；其他字段可自由控制显示、导出和顺序。" type="info" :closable="false" show-icon />
            <div class="column-config-list">
                <div v-for="(item, index) in viewColumns" :key="item.key" class="column-config-row">
                    <div class="column-config-row__sort">
                        <el-button text :icon="ArrowUp" :disabled="index === 0" @click="moveColumn(index, -1)" />
                        <el-button text :icon="ArrowDown" :disabled="index === viewColumns.length - 1" @click="moveColumn(index, 1)" />
                    </div>
                    <div class="column-config-row__name"><span>{{ columnMeta(item.key)?.label }}</span><small>{{ columnMeta(item.key)?.group }}</small></div>
                    <el-checkbox v-model="item.visible" :true-label="1" :false-label="0" :disabled="columnMeta(item.key)?.required === 1">列表显示</el-checkbox>
                    <el-checkbox v-model="item.export" :true-label="1" :false-label="0">参与导出</el-checkbox>
                    <el-input-number v-model="item.width" :min="70" :max="500" :step="10" controls-position="right" class="!w-[120px]" />
                </div>
            </div>
            <template #footer><el-button @click="resetPresetColumns">恢复当前视图默认</el-button><el-button @click="columnDialog = false">取消</el-button><el-button type="primary" :loading="savingView" @click="saveView">保存字段方案</el-button></template>
        </el-dialog>
    </el-drawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ArrowDown, ArrowUp, Download, Refresh, Search, Setting } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { exportErpSaleProfitReport, getErpSaleProfitReport, getErpSaleProfitReportMeta, getErpStaffOptions, saveErpSaleProfitReportView } from '@/addon/hsx_erp/api/erp'
import { getErpSaleChannelOptions } from '@/addon/hsx_erp/api/config'
import ErpCatalogProductSelect from '@/addon/hsx_erp/components/ErpCatalogProductSelect.vue'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import { exportErpConfiguredTable } from '@/addon/hsx_erp/hooks/useErpTableExport'

const props = withDefaults(defineProps<{ modelValue: boolean; initialPreset?: string }>(), { initialPreset: 'sales_profit' })
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()

const periodOptions = [
    { label: '全部时间', value: 'all' },
    { label: '本月', value: 'month' },
    { label: '上月', value: 'last_month' },
    { label: '近30天', value: '30_days' },
    { label: '自定义', value: 'custom' }
]
const quickPeriod = ref(props.initialPreset === 'inventory' ? 'all' : 'month')
const staffOptions = ref<any[]>([])
const saleChannelOptions = ref<any[]>([])
const exporting = ref(false)
const savingView = ref(false)
const columnDialog = ref(false)
const activePresetKey = ref(props.initialPreset)
const meta = reactive<any>({ columns: [], presets: [], view: { preset: 'sales_profit', columns: [] } })
const viewColumns = ref<any[]>([])
const filters = reactive<any>({
    item_type: 'device',
    trade_scope: 'effective',
    profit_state: '',
    keyword: '',
    catalog_product_id: '',
    party_scope: 'supplier',
    party_id: null,
    party_name: '',
    source_plugin: '',
    sale_channel_key: '',
    staff_uid: '',
    time_dimension: 'stock_in',
    dateRange: monthRange()
})
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 20, total: 0 })
const summary = reactive<any>(emptySummary())
const activePreset = computed(() => meta.presets.find((item: any) => item.key === activePresetKey.value))
const datasetScope = computed(() => activePreset.value?.filters?.dataset_scope || 'sales')
const allTime = computed(() => quickPeriod.value === 'all')
const activePartyScope = computed(() => datasetScope.value === 'sales' ? 'customer' : filters.party_scope)
const sourceOptions = computed(() => activePartyScope.value === 'customer'
    ? [{ label: 'ERP 销售', value: 'erp' }, { label: '商城订单', value: 'phone_shop' }]
    : [{ label: 'ERP 采购 / 期初', value: 'erp' }, { label: '回收插件', value: 'hsx_recycle' }, { label: '商城补录', value: 'phone_shop' }])
const visibleColumns = computed(() => viewColumns.value
    .filter((item: any) => Number(item.visible) === 1)
    .map((item: any) => ({ ...(columnMeta(item.key) || {}), ...item })))

const profitRate = computed(() => {
    const amount = Number(summary.amount || 0)
    return amount > 0 ? `${(Number(summary.profit || 0) / amount * 100).toFixed(2)}%` : '0.00%'
})
const summaryCards = computed(() => datasetScope.value === 'assets' ? [
    { key: 'quantity', label: '设备数量', value: quantityText(summary.quantity), unit: '台', hint: activePreset.value?.name || '当前资产口径', className: 'summary-card--blue' },
    { key: 'cost', label: '成本占用', value: money(summary.cost), hint: '按设备当前总成本汇总', className: '' },
    { key: 'amount', label: '销售价/估值', value: money(summary.amount), hint: '优先零售价，其次预估售价', className: '' },
    { key: 'profit', label: '已售毛利', value: money(summary.profit), hint: '所选资产中已售设备毛利', className: Number(summary.profit || 0) >= 0 ? 'summary-card--green' : 'summary-card--red' }
] : [
    { key: 'quantity', label: '有效销售设备', value: quantityText(summary.quantity), unit: '台', hint: `盈利 ${quantityText(summary.profit_quantity)} 台 · 亏损 ${quantityText(summary.loss_quantity)} 台`, className: 'summary-card--blue' },
    { key: 'amount', label: '销售净额', value: money(summary.amount), hint: '补差、退款后实际经营口径', className: '' },
    { key: 'cost', label: '有效成本', value: money(summary.cost), hint: '与当前有效成交对应', className: '' },
    { key: 'profit', label: '销售毛利', value: money(summary.profit), hint: `毛利率 ${profitRate.value}`, className: Number(summary.profit || 0) >= 0 ? 'summary-card--green' : 'summary-card--red' }
])

function emptySummary() {
    return { quantity: 0, amount: 0, cost: 0, profit: 0, profit_quantity: 0, loss_quantity: 0, zero_quantity: 0 }
}

async function handleOpen() {
    await loadMeta()
    const tasks: Promise<any>[] = []
    if (!staffOptions.value.length) tasks.push(getErpStaffOptions().then((res: any) => { staffOptions.value = res?.data?.users || [] }))
    if (!saleChannelOptions.value.length) tasks.push(getErpSaleChannelOptions().then((res: any) => {
        saleChannelOptions.value = (Array.isArray(res?.data) ? res.data : []).filter((item: any) => Number(item.enabled ?? 1) === 1)
    }))
    if (tasks.length) await Promise.all(tasks)
    await loadList()
}

async function loadMeta() {
    const res: any = await getErpSaleProfitReportMeta()
    Object.assign(meta, res?.data || {})
    const requested = meta.presets.some((item: any) => item.key === props.initialPreset) ? props.initialPreset : meta.view?.preset
    activePresetKey.value = requested || 'sales_profit'
    syncPresetFilters()
    applyViewColumns(true)
}

function applyViewColumns(useSaved = true) {
    const preset = activePreset.value
    const saved = useSaved ? (meta.view?.views?.[activePresetKey.value] || (meta.view?.preset === activePresetKey.value ? meta.view?.columns : [])) : []
    const keys = saved?.length ? saved.map((item: any) => item.key) : (preset?.columns || [])
    const rows = saved?.length ? saved : keys.map((key: string) => ({ key, visible: 1, export: 1, width: columnMeta(key)?.width || 120, fixed: '' }))
    const missing = meta.columns.filter((item: any) => !rows.some((row: any) => row.key === item.key))
    viewColumns.value = [...rows, ...missing.map((item: any) => ({ key: item.key, visible: item.required === 1 ? 1 : 0, export: item.required === 1 ? 1 : 0, width: item.width, fixed: '' }))]
}

function changePreset() {
    syncPresetFilters()
    applyViewColumns(true)
    handleSearch()
}

function syncPresetFilters() {
    const defaults = activePreset.value?.filters || {}
    filters.trade_scope = defaults.trade_scope || 'effective'
    filters.profit_state = defaults.profit_state || ''
    filters.time_dimension = defaults.time_dimension || (datasetScope.value === 'sales' ? 'sale' : 'stock_in')
    filters.party_scope = datasetScope.value === 'sales' ? 'customer' : 'supplier'
    filters.party_id = null
    filters.party_name = ''
    filters.source_plugin = ''
    filters.sale_channel_key = ''
    filters.staff_uid = ''
    quickPeriod.value = Number(defaults.ignore_time || 0) === 1 ? 'all' : 'month'
    if (quickPeriod.value !== 'all') filters.dateRange = monthRange()
}

function close() {
    emit('update:modelValue', false)
}

function applyQuickPeriod(value: string | number | boolean) {
    const period = String(value)
    if (period === 'all') {
        handleSearch()
        return
    }
    if (period === 'month') filters.dateRange = monthRange()
    if (period === 'last_month') filters.dateRange = lastMonthRange()
    if (period === '30_days') filters.dateRange = recentDaysRange(30)
    if (period !== 'custom') handleSearch()
}

function onDateRangeChange() {
    quickPeriod.value = 'custom'
    handleSearch()
}

function handleSearch() {
    table.page = 1
    loadList()
}

function resetFilters() {
    quickPeriod.value = Number(activePreset.value?.filters?.ignore_time || 0) === 1 ? 'all' : 'month'
    filters.trade_scope = 'effective'
    filters.profit_state = ''
    filters.keyword = ''
    filters.catalog_product_id = ''
    filters.party_scope = datasetScope.value === 'sales' ? 'customer' : 'supplier'
    filters.party_id = null
    filters.party_name = ''
    filters.source_plugin = ''
    filters.sale_channel_key = ''
    filters.staff_uid = ''
    filters.time_dimension = activePreset.value?.filters?.time_dimension || (datasetScope.value === 'sales' ? 'sale' : 'stock_in')
    filters.dateRange = monthRange()
    handleSearch()
}

function onPartyScopeChange() {
    filters.party_id = null
    filters.party_name = ''
    filters.source_plugin = ''
    filters.sale_channel_key = ''
    filters.staff_uid = ''
    filters.time_dimension = filters.party_scope === 'customer' ? 'sale' : 'stock_in'
}

function columnMeta(key: string) {
    return meta.columns.find((item: any) => item.key === key)
}

function moveColumn(index: number, offset: number) {
    const target = index + offset
    if (target < 0 || target >= viewColumns.value.length) return
    const rows = [...viewColumns.value]
    ;[rows[index], rows[target]] = [rows[target], rows[index]]
    viewColumns.value = rows
}

function resetPresetColumns() {
    applyViewColumns(false)
}

async function saveView() {
    savingView.value = true
    try {
        const res: any = await saveErpSaleProfitReportView({ preset: activePresetKey.value, columns: viewColumns.value })
        meta.view = res?.data || { preset: activePresetKey.value, columns: viewColumns.value }
        columnDialog.value = false
        ElMessage.success('字段方案已保存，仅影响当前站点')
        await loadList()
    } finally {
        savingView.value = false
    }
}

async function loadList() {
    table.loading = true
    try {
        const res: any = await getErpSaleProfitReport({
            ...requestParams(),
            page: table.page,
            limit: table.limit
        })
        table.data = res?.data?.data || []
        table.total = Number(res?.data?.total || 0)
        Object.assign(summary, emptySummary(), res?.data?.summary || {})
    } finally {
        table.loading = false
    }
}

async function exportExcel() {
    exporting.value = true
    try {
        const res: any = await exportErpSaleProfitReport(requestParams())
        const payload = res?.data || {}
        const rows = Array.isArray(payload.rows) ? payload.rows : []
        const total = payload.summary || emptySummary()
        const [start, end] = normalizedRange()
        const rangeLabel = allTime.value ? '全部时间' : `${start} 至 ${end}`
        const fileRange = allTime.value ? '全部时间' : `${start}_${end}`
        const columns = Array.isArray(payload.columns) && payload.columns.length ? payload.columns : visibleColumns.value
        exportErpConfiguredTable({
            title: activePreset.value?.name || '设备经营台账',
            sheetName: '设备经营台账',
            fileName: `ERP设备经营台账_${fileRange}.xlsx`,
            columns,
            metaRows: [['制表日期', formatDateTime(payload.generated_at || Math.floor(Date.now() / 1000)), '', '查询日期', rangeLabel]],
            rows: rows.map((row: any) => columns.map((column: any) => exportValue(row, column))),
            summaryRows: [[
                '总计', `${quantityText(total.quantity)} 台`, `销售/估值 ${money(total.amount)}`,
                `成本 ${money(total.cost)}`, `利润 ${money(total.profit)}`,
                `盈利 ${quantityText(total.profit_quantity)} 台 / 亏损 ${quantityText(total.loss_quantity)} 台`
            ]]
        })
        ElMessage.success(`已导出 ${rows.length} 条设备台账`)
    } finally {
        exporting.value = false
    }
}

function requestParams() {
    const [start_date, end_date] = normalizedRange()
    return {
        preset: activePresetKey.value,
        dataset_scope: datasetScope.value,
        item_type: filters.item_type,
        trade_scope: filters.trade_scope,
        profit_state: filters.profit_state,
        keyword: filters.keyword,
        catalog_product_id: filters.catalog_product_id,
        party_scope: activePartyScope.value,
        party_id: filters.party_id,
        source_plugin: filters.source_plugin,
        sale_channel_key: activePartyScope.value === 'customer' ? filters.sale_channel_key : '',
        staff_uid: filters.staff_uid,
        time_dimension: datasetScope.value === 'sales' ? 'sale' : filters.time_dimension,
        ignore_time: allTime.value ? 1 : 0,
        start_date,
        end_date,
        columns: viewColumns.value.filter((item: any) => Number(item.export) === 1).map((item: any) => item.key).join(',')
    }
}

function normalizedRange(): [string, string] {
    const range = Array.isArray(filters.dateRange) ? filters.dateRange : monthRange()
    return [String(range[0] || monthRange()[0]), String(range[1] || today())]
}

function monthRange(): [string, string] {
    const now = new Date()
    return [formatDate(new Date(now.getFullYear(), now.getMonth(), 1)), formatDate(now)]
}

function lastMonthRange(): [string, string] {
    const now = new Date()
    return [formatDate(new Date(now.getFullYear(), now.getMonth() - 1, 1)), formatDate(new Date(now.getFullYear(), now.getMonth(), 0))]
}

function recentDaysRange(days: number): [string, string] {
    const now = new Date()
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - days + 1, 0, 0, 0)
    return [formatDate(start), formatDate(now)]
}

function today() { return formatDate(new Date()) }
function formatDate(date: Date) { return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` }

function dateText(value: number | string) {
    if (!value) return '-'
    const date = new Date(Number(value) * 1000)
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function columnValue(row: any, key: string) {
    const values: Record<string, any> = {
        purchase_date: row.purchase_at,
        stock_in_date: row.stock_in_at,
        sale_date: row.sale_at,
        serial_no: row.serial_no || row.imei || row.sn || row.asset_no,
        sale_amount: row.report_amount,
        total_cost: row.report_cost,
        profit: row.report_profit,
        customer_name: row.party_name || row.member_name,
        business_state: row.business_state || row.trade_state_name,
        supplier_name: row.supplier_name,
        salesman_name: row.salesman_name || row.operator_name,
        sale_channel: row.sale_channel || row.origin_name
    }
    return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : row[key]
}

function displayValue(row: any, column: any) {
    const value = columnValue(row, column.key)
    if (column.format === 'date') return dateText(value)
    if (column.format === 'integer') return Number(value || 0)
    if (column.format === 'listing_status') return listingStatusName(String(value || ''))
    return value === '' || value === null || value === undefined ? '-' : String(value)
}

function exportValue(row: any, column: any) {
    const value = columnValue(row, column.key)
    if (column.format === 'date') return value ? dateText(value) : ''
    if (column.format === 'money' || column.format === 'quantity' || column.format === 'integer') return Number(value || 0)
    if (column.format === 'listing_status') return listingStatusName(String(value || ''))
    return value === null || value === undefined ? '' : String(value)
}

function listingStatusName(value: string) {
    const names: Record<string, string> = {
        none: '无需上架', need_photo: '待拍照', need_price: '待销售定价', need_material: '待完善资料',
        ready: '待上架', pending_shop: '待商城完善', listed: '已上架'
    }
    return names[value] || value || '-'
}

function formatDateTime(value: number | string) {
    if (!value) return '-'
    const date = new Date(Number(value) * 1000)
    return `${dateText(value)} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

function pad(value: number) {
    return String(value).padStart(2, '0')
}

function money(value: any) {
    const amount = Number(value || 0)
    return `${amount < 0 ? '-' : ''}¥${Math.abs(amount).toFixed(2)}`
}

function quantityText(value: any) {
    const quantity = Number(value || 0)
    return Number.isInteger(quantity) ? String(quantity) : quantity.toFixed(3).replace(/0+$/, '').replace(/\.$/, '')
}

function staffName(item: any) {
    return item?.real_name || item?.username || item?.nickname || `员工${item?.uid || ''}`
}

function profitTagType(state: string) {
    if (state === 'profit') return 'success'
    if (state === 'loss') return 'danger'
    return 'info'
}

function rowClassName({ row }: { row: any }) {
    if (!row.effective_trade) return 'profit-row--invalid'
    if (row.profit_state === 'loss') return 'profit-row--loss'
    return ''
}
</script>

<style scoped>
.profit-report { min-width: 1060px; padding: 0 2px 24px; }
.report-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 16px; }
.report-head__title { color: #111827; font-size: 18px; font-weight: 650; }
.report-head__desc { margin-top: 6px; color: #64748b; font-size: 13px; }
.filter-panel, .table-panel { border: 1px solid #e7ebf2; border-radius: 12px; background: #fff; }
.filter-panel { padding: 16px 18px 2px; }
.preset-row { display: flex; align-items: center; gap: 14px; padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px solid #eef1f6; }
.preset-row__label { flex: none; color: #475569; font-size: 13px; font-weight: 600; }
.period-row { display: flex; align-items: center; gap: 12px; }
.period-row__picker-wrap { flex: 0 0 280px; width: 280px; max-width: 280px; }
.period-row__picker { width: 100% !important; }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 14px 0; }
.summary-card { position: relative; overflow: hidden; min-height: 112px; padding: 17px 18px; border: 1px solid #e7ebf2; border-radius: 12px; background: linear-gradient(145deg, #fff, #fbfcfe); }
.summary-card::after { position: absolute; right: -18px; bottom: -24px; width: 82px; height: 82px; border-radius: 50%; background: rgba(59, 130, 246, .05); content: ''; }
.summary-card__label { color: #64748b; font-size: 13px; }
.summary-card__value { margin-top: 7px; color: #172033; font-size: 25px; font-weight: 700; line-height: 1.15; }
.summary-card__value span { margin-left: 5px; font-size: 13px; font-weight: 500; }
.summary-card__hint { margin-top: 8px; color: #94a3b8; font-size: 12px; }
.summary-card--blue { border-color: #dbe8ff; background: linear-gradient(145deg, #f8fbff, #eef5ff); }
.summary-card--green .summary-card__value { color: #16a34a; }
.summary-card--red .summary-card__value { color: #dc2626; }
.table-panel { padding: 0 16px 16px; }
.table-panel__head { display: flex; align-items: center; justify-content: space-between; height: 54px; }
.profit-legend { display: flex; gap: 16px; color: #64748b; font-size: 12px; }
.profit-dot { display: inline-block; width: 7px; height: 7px; margin-right: 5px; border-radius: 50%; }
.profit-dot--green { background: #22c55e; }
.profit-dot--red { background: #ef4444; }
.profit-dot--gray { background: #94a3b8; }
.profit-value { font-weight: 650; }
.profit-value--profit { color: #16a34a; }
.profit-value--loss { color: #dc2626; }
.profit-value--zero { color: #64748b; }
:deep(.profit-row--invalid) { color: #94a3b8; background: #fafafa; }
:deep(.profit-row--invalid .profit-value) { color: #94a3b8; text-decoration: line-through; }
:deep(.profit-row--loss td:first-child) { box-shadow: inset 3px 0 0 #ef4444; }
.column-config-list { max-height: 520px; overflow-y: auto; margin-top: 16px; border: 1px solid #e7ebf2; border-radius: 10px; }
.column-config-row { display: grid; grid-template-columns: 72px minmax(150px, 1fr) 94px 94px 130px; align-items: center; min-height: 54px; padding: 0 14px; border-bottom: 1px solid #eef1f6; }
.column-config-row:last-child { border-bottom: 0; }
.column-config-row__sort { display: flex; }
.column-config-row__sort :deep(.el-button + .el-button) { margin-left: 0; }
.column-config-row__name { display: flex; flex-direction: column; color: #334155; font-size: 13px; font-weight: 600; }
.column-config-row__name small { margin-top: 2px; color: #94a3b8; font-size: 11px; font-weight: 400; }
</style>
