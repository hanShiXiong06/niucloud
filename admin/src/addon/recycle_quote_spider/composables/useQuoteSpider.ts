import { computed, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { UploadFile } from 'element-plus'
import * as XLSX from 'xlsx'
import {
    addQuoteCategory,
    addQuoteItem,
    addQuoteRow,
    addQuoteSource,
    batchConfirmQuoteExcel,
    confirmQuoteExcel,
    createDefaultQuoteSource,
    deleteQuoteCategory,
    deleteQuoteItem,
    deleteQuoteRow,
    editQuoteCategory,
    editQuoteItem,
    editQuoteRow,
    editQuoteSource,
    getQuoteRowPriceHistory,
    getQuoteCategoryTree,
    getQuoteItemFilterOptions,
    getQuoteItemList,
    getQuoteImportTaskList,
    getQuoteRowList,
    getQuoteSourceAll,
    getQuoteSourceList,
    getQuoteSyncLogList,
    getQuoteWorkbenchSummary,
    previewQuoteExcel,
    syncQuoteSource,
    uploadQuoteExcel
} from '@/addon/recycle_quote_spider/api/quote'

export type EditType = 'category' | 'item' | 'row'

/**
 * 回收报价插件后台 —— 单例数据中枢
 * 所有 tab / 抽屉 / 弹窗共享同一份状态与方法，组件只负责展示。
 */

const activeTab = ref('workbench')
const accessMode = ref<'sync' | 'excel'>('sync')
const sourceLoading = ref(false)
const categoryLoading = ref(false)
const itemLoading = ref(false)
const rowLoading = ref(false)
const logLoading = ref(false)
const importLogLoading = ref(false)
const summaryLoading = ref(false)
const syncingId = ref(0)
const selectedSourceId = ref<number | ''>('')
const selectedCategoryId = ref<number | ''>('')
const selectedItemId = ref(0)
const selectedItemName = ref('')
const sourceOptions = ref<any[]>([])
const categoryOptions = ref<any[]>([])
const categorySelection = ref<any[]>([])
const itemSelection = ref<any[]>([])
const rowSelection = ref<any[]>([])
const excelSourceId = ref<number | ''>('')
const excelFileName = ref('')
const excelPreview = ref<any>({})
const excelTaskId = ref(0)
const excelImporting = ref(false)
const logRefreshTimer = ref<number | null>(null)
// 按品牌批量导入(一张多工作表的表 → 分流到分类下多个报价项)
const excelSheets = ref<any[]>([])
const batchMode = ref(false)
const batchImporting = ref(false)
const batchCategoryItems = ref<any[]>([])
const batchTargets = ref<any[]>([])

const sourceTable = reactive({ data: [] as any[], page: 1, limit: 10, total: 0 })
const categoryTable = reactive({ data: [] as any[], total: 0 })
const itemTable = reactive({ data: [] as any[], page: 1, limit: 20, total: 0 })
const rowTable = reactive({ data: [] as any[], page: 1, limit: 20, total: 0 })
const logTable = reactive({ data: [] as any[] })
const importLogTable = reactive({ data: [] as any[] })
const workbenchSummary = reactive({
    source_count: 0,
    enabled_source_count: 0,
    item_count: 0,
    row_count: 0,
    visible_row_count: 0,
    history_row_count: 0,
    snapshot_day_count: 0,
    latest_record_date: '',
    latest_price_at: 0,
    latest_sync_at: 0
})
const sourceQuery = reactive({ keyword: '', status: '' as number | '' })
const categoryQuery = reactive({ keyword: '' })
const itemQuery = reactive({
    keyword: '',
    is_show: '' as number | '',
    is_hot: '' as number | '',
    follow_source: '' as number | '',
    has_update: '' as number | '',
    is_image_quote: '' as number | '',
    brand: '',
    tab: '',
    quote_type: '',
    order_by: '',
    create_at_start: '' as number | '',
    create_at_end: '' as number | ''
})
const itemDate = ref<string | null>(null)
const rowQuery = reactive({
    keyword: '',
    item_id: '',
    is_hot: '' as number | '',
    create_at_start: '' as number | '',
    create_at_end: '' as number | '',
    page: 1,
    limit: 20
})
const rowDate = ref<string | null>(null)
const filterOptions = reactive({
    brands: [] as string[],
    tabs: [] as string[],
    quote_types: [] as string[]
})
const excelImportForm = reactive({
    source_id: '' as number | '',
    category_id: '' as number | '',
    item_id: '' as number | '',
    item_name: '',
    notice_text: '',
    brand: '',
    tab: ''
})

const sourceDialog = reactive({
    visible: false,
    curlText: '',
    requestConfigText: '',
    form: emptySourceForm()
})

const editDialog = reactive({
    visible: false,
    mode: 'edit' as 'create' | 'edit',
    type: 'category' as EditType,
    keepOpen: false,
    submitting: false,
    form: {} as Record<string, any>,
    columnsText: ''
})

const rowDrawer = reactive({
    visible: false,
    activeTab: 'rows',
    item: {} as Record<string, any>
})

const adjustDialog = reactive({
    visible: false,
    submitting: false,
    form: {
        adjust_type: 1,
        adjust_value: 0,
        adjust_ratio: 1
    }
})

const historyDialog = reactive({
    visible: false,
    loading: false,
    days: 30,
    row: {} as Record<string, any>,
    columns: [] as string[],
    points: [] as Array<{ date: string; prices: any[] }>
})

const followSourceOptions = [
    { label: '跟随爬虫', value: 1 },
    { label: '人工维护', value: 0 }
]

const quoteModeOptions = [
    { label: '结构化报价', value: 0 },
    { label: '图片报价', value: 1 }
]

const adjustTypeOptions = [
    { label: '固定调整', value: 1 },
    { label: '比例调整', value: 2 }
]

function emptySourceForm(): Record<string, any> {
    return {
        id: 0,
        source_key: '',
        source_name: '',
        provider: '',
        base_url: '',
        list_path: '',
        detail_path: '',
        request_config: {},
        sync_enabled: 0,
        sync_interval: 86400,
        timeout: 15,
        rate_limit: 300,
        retry_times: 1,
        status: 1
    }
}

/* ------------------------------ 纯函数 / 工具 ------------------------------ */

const formatTime = (value: number | string) => {
    if (!value) return '-'
    const numeric = Number(value)
    const date = Number.isFinite(numeric)
        ? new Date(numeric < 1_000_000_000_000 ? numeric * 1000 : numeric)
        : new Date(String(value).replace(/-/g, '/'))
    if (Number.isNaN(date.getTime())) return String(value)
    const pad = (part: number) => String(part).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

const syncIntervalText = (seconds: number) => {
    const value = Number(seconds || 0)
    if (value >= 86400) return `每 ${Math.round(value / 86400)} 天`
    if (value >= 3600) return `每 ${Math.round(value / 3600)} 小时`
    return `每 ${Math.max(1, Math.round(value / 60))} 分钟`
}

const sourceSyncText = (row: any) => {
    if (row.sync_enabled !== 1) return '手动同步可用'
    const last = Number(row.last_sync_at || 0)
    const next = last ? `，下次最早 ${formatTime(last + Number(row.sync_interval || 86400))}` : '，等待首次自动同步'
    return `${syncIntervalText(Number(row.sync_interval || 86400))}${next}`
}

const formatPrices = (columns: any[] = [], prices: any[] = []) => {
    if (!Array.isArray(prices)) return ''
    return prices.map((price, index) => (columns?.[index] ? `${columns[index]}:${price}` : price)).join(' / ')
}

const isRemarkColumn = (label: string) => /备注|说明|描述|note|remark/i.test(label)

const isPriceColumn = (label: string) =>
    /价|充新|准新|靓机|小花|大花|微瑕|内爆|外爆|开机|不开机|废板|屏好|屏坏|成色|回收|报价/u.test(String(label))

const formatMoney = (value: any) => {
    if (value === '' || value === null || value === undefined) return '-'
    return value
}

const normalizePriceArray = (value: any) => (Array.isArray(value) ? [...value] : [])

const normalizeManualPrices = (value: any, minLength = 0) => {
    const list = normalizePriceArray(value)
    while (list.length < minLength) list.push('')
    return list.map(item => (item === null || item === undefined ? '' : item))
}

const parseColumnsText = (value: string) =>
    value.split(/[,，\n]/).map(item => item.trim()).filter(Boolean)

const parseJsonObject = (value: string) => {
    if (!value.trim()) return {} as Record<string, any>
    const parsed = JSON.parse(value)
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
}

const ignoredCurlHeaders = [
    'host',
    'connection',
    'content-length',
    'accept-encoding',
    'sec-fetch-site',
    'sec-fetch-mode',
    'sec-fetch-dest',
    'priority'
]

const parseCookieHeader = (value: string) => {
    const cookies: Record<string, string> = {}
    value.split(';').forEach(item => {
        const splitIndex = item.indexOf('=')
        if (splitIndex <= 0) return
        const key = item.slice(0, splitIndex).trim()
        const cookieValue = item.slice(splitIndex + 1).trim()
        if (key) cookies[key] = cookieValue
    })
    return cookies
}

const tokenizeCurl = (value: string) => {
    const tokens: string[] = []
    let current = ''
    let quote = ''
    let escaped = false
    for (const char of value) {
        if (escaped) {
            current += char
            escaped = false
            continue
        }
        if (char === '\\') {
            escaped = true
            continue
        }
        if (quote) {
            if (char === quote) {
                quote = ''
            } else {
                current += char
            }
            continue
        }
        if (char === '\'' || char === '"') {
            quote = char
            continue
        }
        if (/\s/.test(char)) {
            if (current) {
                tokens.push(current)
                current = ''
            }
            continue
        }
        current += char
    }
    if (current) tokens.push(current)
    return tokens
}

const applyCurlHeader = (
    header: string,
    result: { headers: Record<string, string>; cookies: Record<string, string>; user_agent: string }
) => {
    const splitIndex = header.indexOf(':')
    if (splitIndex <= 0) return
    const name = header.slice(0, splitIndex).trim()
    const value = header.slice(splitIndex + 1).trim()
    if (!name) return
    const lowerName = name.toLowerCase()
    if (ignoredCurlHeaders.includes(lowerName)) return
    if (lowerName === 'cookie') {
        Object.assign(result.cookies, parseCookieHeader(value))
        return
    }
    if (lowerName === 'user-agent') {
        result.user_agent = value
        return
    }
    result.headers[name] = value
}

const parseCurlCommand = (value: string) => {
    const tokens = tokenizeCurl(value.replace(/\\\r?\n/g, ' '))
    const result = {
        url: '',
        headers: {} as Record<string, string>,
        cookies: {} as Record<string, string>,
        user_agent: ''
    }
    for (let index = 0; index < tokens.length; index++) {
        const token = tokens[index]
        if (token === 'curl') continue
        if ((token === '-H' || token === '--header') && tokens[index + 1]) {
            applyCurlHeader(tokens[++index], result)
            continue
        }
        if ((token === '-b' || token === '--cookie' || token === '--cookie-jar') && tokens[index + 1]) {
            Object.assign(result.cookies, parseCookieHeader(tokens[++index]))
            continue
        }
        if ((token === '-A' || token === '--user-agent') && tokens[index + 1]) {
            result.user_agent = tokens[++index]
            continue
        }
        if (token.startsWith('http://') || token.startsWith('https://')) {
            result.url = token
        }
    }
    return result
}

const inferDetailPath = (listPath: string) => {
    if (listPath.includes('/newimage')) return listPath.replace('/newimage', '/bj')
    if (listPath.includes('/list')) return listPath.replace('/list', '/detail')
    return listPath
}

const inferQuoteSourcePaths = (path: string) => {
    const result = { list_path: path, detail_path: inferDetailPath(path), is_detail: false }
    if (/\/getstores\d*$/i.test(path)) {
        result.detail_path = path.replace(/\/getstores\d*$/i, '/bj4')
        return result
    }
    if (/\/bj\d*$/i.test(path)) {
        result.list_path = path.replace(/\/bj\d*$/i, '/getstores1')
        result.detail_path = path
        result.is_detail = true
    }
    return result
}

const normalizeSourceKey = (value: string) => {
    const key = value
        .toLowerCase()
        .replace(/^https?:\/\//, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
    return key || `source_${Date.now()}`
}

const findCategoryById = (list: any[], id: number | string): any => {
    if (id === '') return null
    for (const item of list) {
        if (item.id === id) return item
        const child = findCategoryById(item.children || [], id)
        if (child) return child
    }
    return null
}

const collectCategoryIds = (category: any): number[] => {
    const ids = [Number(category.id)]
    ;(category.children || []).forEach((child: any) => {
        ids.push(...collectCategoryIds(child))
    })
    return ids.filter(Boolean)
}

const flattenCategories = (list: any[]): any[] => {
    const result: any[] = []
    list.forEach(item => {
        result.push(item)
        result.push(...flattenCategories(item.children || []))
    })
    return result
}

const resolveQuoteItemIcon = (item: any) => item.icon || item.image || item.timage || item.bimage || ''

const imageUrl = (value: string) => {
    const url = String(value || '').trim()
    if (!url) return ''
    if (/^(https?:)?\/\//.test(url) || url.startsWith('data:')) return url
    return url.startsWith('/') ? url : `/${url}`
}

const isValidCapacityValue = (value: any, groupName = '') => {
    const text = String(value ?? '').trim()
    if (!text) return false
    if (groupName && text === groupName) return false
    if (/分组|系列/.test(text)) return false
    return true
}

const getCapacityName = (row: any) => {
    const raw = row?.raw_data && typeof row.raw_data === 'object' ? row.raw_data : {}
    const candidates = [
        raw['内存'],
        raw['容量'],
        raw['规格'],
        raw['存储'],
        row.capacity_name,
        row.capacity,
        raw.capacity_name,
        raw.capacity,
        raw.memory,
        raw.storage,
        raw.rom
    ]
    const groupName = String(row.group_name || row.tab || '').trim()
    const value = candidates.find(item => isValidCapacityValue(item, groupName))
    return value === undefined ? '' : String(value).trim()
}

const getDisplayBrand = (row: any) => {
    const brand = String(row.brand || '').trim()
    if (!brand || brand === row.group_name || brand === row.tab || /分组|系列/.test(brand)) return ''
    return brand
}

const buildSpanMap = (rows: any[], keyGetter: (row: any) => string) => {
    const spans: Record<number, number> = {}
    let start = 0
    while (start < rows.length) {
        const key = keyGetter(rows[start])
        let end = start + 1
        while (end < rows.length && keyGetter(rows[end]) === key) end++
        spans[start] = end - start
        for (let index = start + 1; index < end; index++) spans[index] = 0
        start = end
    }
    return spans
}

/* ------------------------------ 计算属性 ------------------------------ */

const selectedSource = computed(() => sourceOptions.value.find(item => item.id === selectedSourceId.value) || null)

const selectedCategoryName = computed(() => {
    const current = findCategoryById(categoryOptions.value, selectedCategoryId.value)
    return current?.name || ''
})

const selectedCategoryScopeIds = computed(() => {
    const current = findCategoryById(categoryOptions.value, selectedCategoryId.value)
    return current ? collectCategoryIds(current) : []
})

const editDialogTitle = computed(() => {
    const titleMap: Record<EditType, string> = {
        category: editDialog.mode === 'create' ? '新增分类' : '编辑分类',
        item: editDialog.mode === 'create' ? '新增报价项' : '编辑报价项',
        row: editDialog.mode === 'create' ? '新增行价格' : '编辑行价格'
    }
    return titleMap[editDialog.type]
})

const rowEditModeTip = computed(() =>
    editDialog.form.follow_source === 1
        ? '同步后继续使用第三方源价格，可叠加下方调价规则。'
        : '后续同步不主动覆盖本行最终价，可在价格明细里直接维护每一列人工价格。'
)

const rowPriceTable = computed(() => {
    const columns =
        editDialog.mode === 'create' && editDialog.type === 'row'
            ? parseColumnsText(editDialog.columnsText)
            : normalizePriceArray(editDialog.form.columns)
    const sourcePrices = normalizePriceArray(editDialog.form.source_prices)
    const finalPrices = normalizePriceArray(editDialog.form.final_prices)
    const manualPrices = normalizePriceArray(editDialog.form.manual_prices)
    const length = Math.max(columns.length, sourcePrices.length, finalPrices.length, manualPrices.length)
    return Array.from({ length }, (_, index) => ({
        column: columns[index] || `价格${index + 1}`,
        source_price: sourcePrices[index] ?? '',
        final_price: finalPrices[index] ?? '',
        manual_price: manualPrices[index] ?? ''
    }))
})

const buildMatrixPriceColumns = (labels: string[], hasRemark: boolean) => {
    const normalized = [...labels]
    if (hasRemark && !normalized.some(label => isRemarkColumn(label))) normalized.push('备注')
    return normalized.map((label, index) => ({
        key: `${index}-${label}`,
        label,
        index,
        isRemark: isRemarkColumn(label),
        align: isRemarkColumn(label) ? 'left' : 'right',
        minWidth: isRemarkColumn(label) ? 180 : Math.max(110, Math.min(180, label.length * 16 + 44))
    }))
}

const buildMatrixPriceColumnGroups = (columns: any[]) => {
    const parsed = columns.map(column => {
        const match = String(column.label || '').match(/^(.+?)\s+([^\s]+)$/u)
        return {
            column,
            groupLabel: match?.[1]?.trim() || '',
            childLabel: match?.[2]?.trim() || column.label
        }
    })
    const groupCounts = parsed.reduce<Record<string, number>>((result, item) => {
        if (item.groupLabel) result[item.groupLabel] = (result[item.groupLabel] || 0) + 1
        return result
    }, {})
    const result: Array<{ key: string; label: string; grouped: boolean; children: any[] }> = []
    const groupMap = new Map<string, (typeof result)[number]>()
    parsed.forEach(item => {
        if (item.groupLabel && groupCounts[item.groupLabel] > 1) {
            let group = groupMap.get(item.groupLabel)
            if (!group) {
                group = { key: `group-${item.groupLabel}`, label: item.groupLabel, grouped: true, children: [] }
                groupMap.set(item.groupLabel, group)
                result.push(group)
            }
            group.children.push({ ...item.column, displayLabel: item.childLabel })
        } else {
            result.push({
                key: `column-${item.column.key}`,
                label: item.column.label,
                grouped: false,
                children: [{ ...item.column, displayLabel: item.column.label }]
            })
        }
    })
    return result
}

/** 完整表头相同的型号共用一张表；表头签名不同则另起一张表。 */
const rowMatrixSections = computed(() => {
    const buckets = new Map<string, any[]>()
    rowTable.data.forEach(row => {
        const labels = normalizePriceArray(row.columns).map((column: any, index: number) =>
            String(column || `价格${index + 1}`).trim()
        )
        const signature = JSON.stringify(labels)
        if (!buckets.has(signature)) buckets.set(signature, [])
        buckets.get(signature)!.push(row)
    })

    return Array.from(buckets.entries()).map(([signature, sourceRows], sectionIndex) => {
        const labels = JSON.parse(signature) as string[]
        // 这里只保留矩阵结构需要的字段。不要展开 source，否则任意价格输入都会
        // 让 computed 订阅 final_prices 等所有字段，进而重建全部分表。
        const rows = sourceRows.map((source, rowIndex) => ({
            id: source.id,
            source,
            rowIndex,
            brand: source.brand,
            tab: source.tab,
            group_name: source.tab || source.parent_name || '未分组',
            model_name: source.model_name || source.name || '-',
            capacity_name: getCapacityName(source),
            modelSpanKey: `${source.tab || ''}__${source.model_name || ''}`,
            groupSpanKey: source.tab || '未分组'
        }))
        const modelNames = Array.from(new Set(rows.map(row => row.model_name).filter(Boolean)))
        const columns = buildMatrixPriceColumns(labels, sourceRows.some(row => String(row.remark || '').trim()))
        const spanMaps = {
            group: buildSpanMap(rows, row => row.groupSpanKey),
            model: buildSpanMap(rows, row => row.modelSpanKey)
        }
        return {
            key: `section-${sectionIndex}`,
            title: modelNames.slice(0, 3).join('、') + (modelNames.length > 3 ? ' 等' : ''),
            rowCount: rows.length,
            modelCount: modelNames.length,
            rows,
            columnGroups: buildMatrixPriceColumnGroups(columns),
            spanMaps,
            spanMethod: ({ rowIndex, columnIndex }: { rowIndex: number; columnIndex: number }) => {
                if (columnIndex === 1) {
                    const rowspan = spanMaps.group[rowIndex] ?? 1
                    return { rowspan, colspan: rowspan === 0 ? 0 : 1 }
                }
                if (columnIndex === 2) {
                    const rowspan = spanMaps.model[rowIndex] ?? 1
                    return { rowspan, colspan: rowspan === 0 ? 0 : 1 }
                }
                return { rowspan: 1, colspan: 1 }
            }
        }
    })
})

const getRowMatrixCell = (row: any, column: any) => {
    const source = row.source || row
    if (column.isRemark && column.label === '备注') return source.remark || '-'
    const prices = Array.isArray(source.final_prices) ? source.final_prices : []
    const matchedIndex = Number(column.index)
    const value = matchedIndex > -1 ? prices[matchedIndex] : ''
    if ((value === '' || value === null || value === undefined) && column.isRemark) return source.remark || '-'
    return formatMoney(value)
}

// 今天 vs 上一次：'up' 涨(红) / 'down' 跌(绿) / '' 不变
const getPriceTrend = (row: any, column: any): '' | 'up' | 'down' => {
    if (!column || column.isRemark) return ''
    const source = row.source || row
    const idx = Number(column.index)
    if (idx < 0) return ''
    const cur = Number((Array.isArray(source.final_prices) ? source.final_prices : [])[idx])
    const prev = Number((Array.isArray(source.prev_final_prices) ? source.prev_final_prices : [])[idx])
    if (!Number.isFinite(cur) || !Number.isFinite(prev) || !prev || cur === prev) return ''
    return cur > prev ? 'up' : 'down'
}

const openPriceHistory = (row: any) => {
    historyDialog.row = row.source || row
    historyDialog.days = 30
    historyDialog.visible = true
    loadPriceHistory()
}

const loadPriceHistory = async () => {
    const id = Number(historyDialog.row?.id || 0)
    if (!id) return
    historyDialog.loading = true
    try {
        const res: any = await getQuoteRowPriceHistory(id, historyDialog.days)
        historyDialog.columns = res.data?.columns || []
        historyDialog.points = res.data?.points || []
    } finally {
        historyDialog.loading = false
    }
}

const setHistoryDays = (days: number) => {
    historyDialog.days = days
    loadPriceHistory()
}

const excelPreviewTable = computed(() => {
    const headers = Array.isArray(excelPreview.value.headers) ? excelPreview.value.headers : []
    const rows = Array.isArray(excelPreview.value.rows) ? excelPreview.value.rows : []
    const mapping: any = excelPreview.value.suggested_mapping || {}
    const columns = headers.map((label: string, index: number) => {
        const key = `col_${index}`
        // 以后端 suggested_mapping 为准（唯一真源），识别不到再回退本地正则
        const mappedField = Array.isArray(mapping) ? mapping[index] : mapping[index] ?? mapping[String(index)]
        const isPrice =
            typeof mappedField === 'string' && mappedField ? mappedField.startsWith('price:') : isPriceColumn(label)
        return {
            key,
            label,
            isPrice,
            minWidth: isPrice
                ? Math.max(110, Math.min(180, String(label).length * 16 + 44))
                : Math.max(120, Math.min(220, String(label).length * 16 + 56))
        }
    })
    return {
        columns,
        rows: rows.map((row: any) => {
            const data = row.data || {}
            const tableRow: Record<string, any> = { row_number: row.row_number }
            headers.forEach((header: string, index: number) => {
                tableRow[`col_${index}`] = data[header]
            })
            return tableRow
        })
    }
})

const excelPriceColumnCount = computed(() => excelPreviewTable.value.columns.filter(column => column.isPrice).length)

const batchAdjustRowsPreview = computed(() => rowSelection.value.slice(0, 6))

const batchAdjustTip = computed(() =>
    adjustDialog.form.adjust_type === 1 ? '适合给选中的型号统一加钱或扣钱。' : '适合按百分比统一上浮或下调。'
)

const batchAdjustPreviewText = computed(() => {
    if (adjustDialog.form.adjust_type === 1) {
        const value = Number(adjustDialog.form.adjust_value || 0)
        if (value === 0) return '当前固定调整为 0，保存后价格不发生变化。'
        return value > 0 ? `所有选中行在源价基础上加 ${value}` : `所有选中行在源价基础上扣 ${Math.abs(value)}`
    }
    const ratio = Number(adjustDialog.form.adjust_ratio || 1)
    if (ratio === 1) return '当前比例为 1，保存后价格不发生变化。'
    if (ratio > 1) return `所有选中行按源价上浮 ${Math.round((ratio - 1) * 10000) / 100}%`
    return `所有选中行按源价下调 ${Math.round((1 - ratio) * 10000) / 100}%`
})

/* ------------------------------ 数据加载 ------------------------------ */

const loadSources = async (page = sourceTable.page) => {
    sourceLoading.value = true
    sourceTable.page = page
    try {
        const res: any = await getQuoteSourceList({
            page: sourceTable.page,
            limit: sourceTable.limit,
            ...sourceQuery
        })
        sourceTable.data = res.data.data || []
        sourceTable.total = res.data.total || 0
        const all: any = await getQuoteSourceAll()
        sourceOptions.value = all.data || []
    } finally {
        sourceLoading.value = false
    }
}

const loadCategory = async () => {
    categoryLoading.value = true
    try {
        const res: any = await getQuoteCategoryTree({
            source_id: selectedSourceId.value,
            keyword: categoryQuery.keyword
        })
        categoryTable.data = res.data || []
        categoryTable.total = flattenCategories(categoryTable.data).length
    } finally {
        categoryLoading.value = false
    }
}

const loadCategoryOptions = async () => {
    const res: any = await getQuoteCategoryTree({ source_id: selectedSourceId.value })
    categoryOptions.value = res.data || []
}

const loadFilterOptions = async () => {
    const res: any = await getQuoteItemFilterOptions({
        source_id: selectedSourceId.value,
        category_id: selectedCategoryId.value,
        category_ids: selectedCategoryScopeIds.value.join(',')
    })
    filterOptions.brands = res.data.brands || []
    filterOptions.tabs = res.data.tabs || []
    filterOptions.quote_types = res.data.quote_types || []
}

const loadWorkbenchSummary = async () => {
    summaryLoading.value = true
    try {
        const res: any = await getQuoteWorkbenchSummary({
            source_id: selectedSourceId.value,
            category_id: selectedCategoryId.value,
            category_ids: selectedCategoryScopeIds.value.join(',')
        })
        Object.assign(workbenchSummary, res.data || {})
    } finally {
        summaryLoading.value = false
    }
}

const loadItem = async () => {
    itemLoading.value = true
    try {
        const res: any = await getQuoteItemList({
            source_id: selectedSourceId.value,
            category_id: selectedCategoryId.value,
            category_ids: selectedCategoryScopeIds.value.join(','),
            ...itemQuery,
            page: itemTable.page,
            limit: itemTable.limit
        })
        itemTable.data = res.data.data || []
        itemTable.total = res.data.total || 0
    } finally {
        itemLoading.value = false
    }
}

const loadRows = async () => {
    if (!selectedItemId.value) {
        rowTable.data = []
        rowTable.total = 0
        return
    }
    rowLoading.value = true
    try {
        rowQuery.item_id = String(selectedItemId.value)
        rowQuery.page = rowTable.page
        rowQuery.limit = rowTable.limit
        const res: any = await getQuoteRowList({
            ...rowQuery,
            source_id: selectedSourceId.value,
            brand: itemQuery.brand,
            tab: itemQuery.tab,
            follow_source: itemQuery.follow_source,
            has_update: itemQuery.has_update
        })
        rowTable.data = res.data.data || []
        rowTable.total = res.data.total || 0
    } finally {
        rowLoading.value = false
    }
}

const loadLogs = async () => {
    logLoading.value = true
    try {
        const res: any = await getQuoteSyncLogList({ source_id: selectedSourceId.value, page: 1, limit: 20 })
        logTable.data = res.data.data || []
    } finally {
        logLoading.value = false
    }
}

const loadImportLogs = async () => {
    importLogLoading.value = true
    try {
        const res: any = await getQuoteImportTaskList({ source_id: selectedSourceId.value, page: 1, limit: 20 })
        importLogTable.data = res.data.data || []
    } finally {
        importLogLoading.value = false
    }
}

const refreshManage = () => {
    itemTable.page = 1
    rowTable.page = 1
    loadCategory()
    loadCategoryOptions()
    loadFilterOptions()
    loadItem()
    loadRows()
    loadWorkbenchSummary()
}

const searchManage = () => {
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    refreshManage()
}

const resetFilters = () => {
    categoryQuery.keyword = ''
    selectedCategoryId.value = ''
    selectedItemId.value = 0
    selectedItemName.value = ''
    Object.assign(itemQuery, {
        keyword: '',
        is_show: '',
        is_hot: '',
        follow_source: '',
        has_update: '',
        is_image_quote: '',
        brand: '',
        tab: '',
        quote_type: '',
        order_by: '',
        create_at_start: '',
        create_at_end: ''
    })
    itemDate.value = null
    rowQuery.keyword = ''
    refreshManage()
}

/* ------------------------------ 选择 / 切换 ------------------------------ */

const handleSourceFilterChange = () => {
    if (!selectedSourceId.value) selectedSourceId.value = ''
    selectedCategoryId.value = ''
    selectedItemId.value = 0
    selectedItemName.value = ''
    refreshManage()
}

// 报价源切换：在“数据管理”里用醒目的切换器调用
const switchSource = (id: number | '') => {
    selectedSourceId.value = id
    handleSourceFilterChange()
}

const handleSourceSizeChange = () => {
    sourceTable.page = 1
    loadSources()
}

const handleCategoryFilterChange = () => {
    if (!selectedCategoryId.value) selectedCategoryId.value = ''
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    loadCategory()
    loadFilterOptions()
    loadItem()
    loadRows()
    loadWorkbenchSummary()
}

const openSourceData = (source: any) => {
    selectedSourceId.value = source.id
    activeTab.value = 'workbench'
    handleSourceFilterChange()
}

const selectCategory = (row: any) => {
    if (!row) return
    selectedCategoryId.value = row.id
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    loadFilterOptions()
    loadItem()
    loadRows()
    loadWorkbenchSummary()
}

const clearCategorySelection = () => {
    selectedCategoryId.value = ''
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    loadFilterOptions()
    loadItem()
    loadRows()
    loadWorkbenchSummary()
}

const selectItem = (row: any) => {
    selectedItemId.value = row.id
    selectedItemName.value = row.name
    rowTable.page = 1
    loadRows()
}

const openRowDrawer = (row: any) => {
    selectedItemId.value = row.id
    selectedItemName.value = row.name
    rowDrawer.item = { ...row }
    rowDrawer.activeTab = 'rows'
    rowDrawer.visible = true
    rowSelection.value = []
    rowTable.page = 1
    loadRows()
}

const handleCategorySearch = () => loadCategory()

const handleItemSearch = () => {
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    loadItem()
    loadRows()
}

const handleItemSizeChange = () => {
    itemTable.page = 1
    loadItem()
}

// el-date-picker（type=date, value-format="x"）返回当天 0 点的毫秒时间戳；按“指定某一天”换算成当天起止秒
const dayBounds = (ms: any): [number | '', number | ''] => {
    if (!ms) return ['', '']
    const start = Math.floor(Number(ms) / 1000)
    if (!Number.isFinite(start)) return ['', '']
    return [start, start + 86399]
}

const applyItemDate = (ms: string | null) => {
    itemDate.value = ms
    const [start, end] = dayBounds(ms)
    itemQuery.create_at_start = start
    itemQuery.create_at_end = end
    handleItemSearch()
}

const applyRowDate = (ms: string | null) => {
    rowDate.value = ms
    const [start, end] = dayBounds(ms)
    rowQuery.create_at_start = start
    rowQuery.create_at_end = end
    handleRowSearch()
}

// el-date-picker disabled-date：禁用未来日期（时间未到）
const disableFutureDate = (date: Date) => date.getTime() > Date.now()

const handleRowSearch = () => {
    rowTable.page = 1
    loadRows()
}

const handleRowSizeChange = () => {
    rowTable.page = 1
    loadRows()
}

const handleRowSelectionChange = (rows: any[]) => {
    rowSelection.value = rows.map(row => row.source || row)
}

const handleTabChange = () => {
    if (activeTab.value === 'workbench') refreshManage()
    if (activeTab.value === 'records') {
        loadLogs()
        loadImportLogs()
    }
    if (activeTab.value === 'settings') {
        loadSources()
        loadCategory()
    }
}

/* ------------------------------ 报价源弹窗 ------------------------------ */

const openSourceDialog = (row: any = null) => {
    Object.assign(sourceDialog.form, row ? { ...emptySourceForm(), ...row } : emptySourceForm())
    sourceDialog.curlText = ''
    sourceDialog.requestConfigText = JSON.stringify(sourceDialog.form.request_config || {}, null, 2)
    sourceDialog.visible = true
}

const parseSourceCurl = () => {
    try {
        const parsed = parseCurlCommand(sourceDialog.curlText)
        if (!parsed.url) {
            ElMessage.error('没有识别到 curl 里的请求地址')
            return
        }
        const url = new URL(parsed.url)
        const inferredPaths = inferQuoteSourcePaths(url.pathname)
        sourceDialog.form.base_url = `${url.protocol}//${url.host}`
        sourceDialog.form.list_path = inferredPaths.list_path
        if (!sourceDialog.form.detail_path) sourceDialog.form.detail_path = inferredPaths.detail_path

        const config = parseJsonObject(sourceDialog.requestConfigText)
        const query = { ...(config.query || {}) }
        if (inferredPaths.is_detail) delete query.id
        url.searchParams.forEach((value, key) => {
            if (inferredPaths.is_detail && key === 'id') return
            query[key] = value
        })
        const headers = { ...(config.headers || {}), ...parsed.headers }
        const cookies = { ...(config.cookies || {}), ...parsed.cookies }
        delete headers.cookie
        delete headers.Cookie

        const requestConfig: Record<string, any> = { ...config, query, headers, cookies }
        if (parsed.user_agent) requestConfig.user_agent = parsed.user_agent
        sourceDialog.form.request_config = requestConfig
        sourceDialog.requestConfigText = JSON.stringify(requestConfig, null, 2)

        if (!sourceDialog.form.provider) sourceDialog.form.provider = url.hostname.split('.')[0] || 'saas'
        if (!sourceDialog.form.source_key)
            sourceDialog.form.source_key = normalizeSourceKey(sourceDialog.form.provider || url.hostname)
        if (!sourceDialog.form.source_name) sourceDialog.form.source_name = `${url.hostname} 报价源`
        ElMessage.success(
            inferredPaths.is_detail
                ? '已识别为详情接口，并自动推断列表接口，请确认后保存'
                : '已解析 curl，请确认接口和权限信息后保存'
        )
    } catch (error: any) {
        ElMessage.error(error?.message || 'curl 解析失败')
    }
}

const saveSource = async () => {
    if (!String(sourceDialog.form.source_name || '').trim()) {
        ElMessage.error('请填写报价源名称')
        return
    }
    try {
        sourceDialog.form.request_config = sourceDialog.requestConfigText
            ? JSON.parse(sourceDialog.requestConfigText)
            : {}
    } catch (e) {
        ElMessage.error('请求配置不是有效JSON')
        return
    }
    if (sourceDialog.form.id) {
        await editQuoteSource(sourceDialog.form.id, sourceDialog.form)
    } else {
        await addQuoteSource(sourceDialog.form)
    }
    sourceDialog.visible = false
    ElMessage.success('保存成功')
    loadSources()
}

const createDefaultSource = async () => {
    await createDefaultQuoteSource()
    ElMessage.success('默认报价源已创建')
    loadSources()
}

const syncSource = async (row: any) => {
    syncingId.value = row.id
    try {
        const res: any = await syncQuoteSource(row.id)
        const data = res.data || {}
        selectedSourceId.value = row.id
        activeTab.value = 'records'
        const message = data.message || '同步任务已创建，请在同步日志中查看进度'
        if (data.async) {
            ElMessage.success(message)
        } else if (data.queue_enabled === false) {
            ElMessage.warning(message)
        } else {
            ElMessage.info(message)
        }
        startLogRefresh()
        loadSources()
    } finally {
        syncingId.value = 0
    }
}

const startLogRefresh = () => {
    stopLogRefresh()
    loadLogs()
    logRefreshTimer.value = window.setInterval(() => {
        loadLogs()
        loadSources()
    }, 3000)
    window.setTimeout(stopLogRefresh, 60000)
}

const stopLogRefresh = () => {
    if (logRefreshTimer.value !== null) {
        window.clearInterval(logRefreshTimer.value)
        logRefreshTimer.value = null
    }
}

/* ------------------------------ 内联保存 ------------------------------ */

const saveCategory = (row: any, notify = false) =>
    editQuoteCategory(row.id, {
        name: row.name,
        is_show: row.is_show,
        is_hot: row.is_hot,
        sort: row.sort
    }).then(() => {
        if (notify) ElMessage.success('分类已保存')
    })

const saveItem = (row: any, notify = false) =>
    editQuoteItem(row.id, {
        name: row.name,
        brand: row.brand,
        tab: row.tab,
        keywords: row.keywords,
        quote_type: row.quote_type,
        is_image_quote: row.is_image_quote,
        image: row.image,
        timage: row.timage,
        bimage: row.bimage,
        icon: row.icon,
        notice_text: row.notice_text,
        sort: row.sort,
        is_show: row.is_show,
        is_hot: row.is_hot,
        follow_source: row.follow_source
    }).then(() => {
        if (notify) ElMessage.success('报价项已保存')
    })

const saveDrawerItem = async () => {
    await saveItem(rowDrawer.item)
    const current = itemTable.data.find(item => item.id === rowDrawer.item.id)
    if (current) Object.assign(current, rowDrawer.item)
    ElMessage.success('报价项已保存')
}

const saveRow = (row: any, notify = false) =>
    editQuoteRow(row.id, {
        model_name: row.model_name,
        brand: row.brand,
        tab: row.tab,
        remark: row.remark,
        manual_prices: normalizeManualPrices(row.manual_prices),
        is_show: row.is_show,
        is_hot: row.is_hot,
        sort: row.sort,
        follow_source: row.follow_source,
        adjust_type: row.adjust_type,
        adjust_value: row.adjust_value,
        adjust_ratio: row.adjust_ratio,
        round_mode: row.round_mode
    }).then(() => {
        if (notify) ElMessage.success('行价格已保存')
        loadRows()
    })

/** 价格矩阵轻量编辑：整行转为人工维护并直接落库，不刷新列表以避免输入焦点跳动。 */
const saveInlineRowPrices = async (row: any) => {
    const source = row.source || row
    const finalPrices = normalizeManualPrices(source.final_prices, normalizePriceArray(source.columns).length)
    await editQuoteRow(source.id, {
        manual_prices: finalPrices,
        follow_source: 0,
        adjust_type: 0,
        adjust_value: 0,
        adjust_ratio: 1,
        round_mode: source.round_mode || 'round'
    })
    source.follow_source = 0
    source.adjust_type = 0
    source.adjust_value = 0
    source.adjust_ratio = 1
}

/* ------------------------------ 新增 / 编辑弹窗 ------------------------------ */

const openEditDialog = (type: EditType, row: any) => {
    editDialog.mode = 'edit'
    editDialog.type = type
    editDialog.keepOpen = false
    editDialog.form = { ...row }
    editDialog.columnsText = ''
    if (type === 'row') {
        editDialog.form.columns = normalizePriceArray(row.columns)
        editDialog.form.source_prices = normalizePriceArray(row.source_prices)
        editDialog.form.final_prices = normalizePriceArray(row.final_prices)
        editDialog.form.manual_prices = normalizeManualPrices(
            row.manual_prices,
            editDialog.form.columns.length || editDialog.form.source_prices.length || editDialog.form.final_prices.length
        )
        editDialog.form.follow_source = Number(row.follow_source ?? 1)
        editDialog.form.adjust_type = Number(row.adjust_type ?? 0)
        editDialog.form.adjust_value = Number(row.adjust_value ?? 0)
        editDialog.form.adjust_ratio = Number(row.adjust_ratio ?? 1)
    }
    editDialog.visible = true
}

// 新增分类：父级显式传入，默认 0（一级）。不再依赖当前选中分类，避免“加了一级又被强制变子类”。
const openCreateCategory = (parentId: number | '' = 0) => {
    editDialog.mode = 'create'
    editDialog.type = 'category'
    editDialog.keepOpen = true
    editDialog.columnsText = ''
    editDialog.form = {
        source_id: selectedSourceId.value || '',
        parent_id: Number(parentId) || 0,
        parent_name: parentId ? findCategoryById(categoryOptions.value, Number(parentId))?.name || '' : '',
        name: '',
        sort: 0,
        is_show: 1,
        is_hot: 0
    }
    editDialog.visible = true
}

const openCreateItem = () => {
    editDialog.mode = 'create'
    editDialog.type = 'item'
    editDialog.keepOpen = false
    editDialog.columnsText = ''
    editDialog.form = {
        source_id: selectedSourceId.value || '',
        category_id: selectedCategoryId.value || '',
        name: '',
        brand: '',
        tab: '',
        keywords: '',
        quote_type: 'manual',
        is_image_quote: 0,
        image: '',
        timage: '',
        bimage: '',
        icon: '',
        notice_text: '',
        is_show: 1,
        is_hot: 0,
        follow_source: 0,
        columns: []
    }
    editDialog.visible = true
}

const openCreateRow = () => {
    editDialog.mode = 'create'
    editDialog.type = 'row'
    editDialog.keepOpen = false
    const currentItem = itemTable.data.find(item => item.id === selectedItemId.value) || {}
    editDialog.form = {
        source_id: selectedSourceId.value || currentItem.source_id || '',
        item_id: selectedItemId.value,
        model_name: '',
        brand: currentItem.brand || '',
        tab: currentItem.tab || '',
        remark: '',
        follow_source: 0,
        columns: normalizePriceArray(currentItem.columns),
        source_prices: [],
        manual_prices: normalizeManualPrices([], normalizePriceArray(currentItem.columns).length),
        final_prices: [],
        is_show: 1,
        is_hot: 0,
        sort: 0,
        adjust_type: 0,
        adjust_value: 0,
        adjust_ratio: 1
    }
    editDialog.columnsText = normalizePriceArray(currentItem.columns).join(',')
    editDialog.visible = true
}

const handleRowModeChange = (value: number) => {
    if (value !== 0) return
    const manualPrices = normalizeManualPrices(editDialog.form.manual_prices, rowPriceTable.value.length)
    const finalPrices = normalizePriceArray(editDialog.form.final_prices)
    const sourcePrices = normalizePriceArray(editDialog.form.source_prices)
    editDialog.form.manual_prices = manualPrices.map((price, index) => {
        if (price !== '') return price
        return finalPrices[index] ?? sourcePrices[index] ?? ''
    })
}

// continueAfter=true：保存后清空名称、保留父级，连续新增（用于一级/子分类批量录入）
const submitEdit = async (continueAfter = false) => {
    if (editDialog.submitting) return
    editDialog.submitting = true
    try {
        if (editDialog.mode === 'create') {
            if (editDialog.type === 'category') {
                if (!String(editDialog.form.name || '').trim()) {
                    ElMessage.error('请输入分类名称')
                    return
                }
                await addQuoteCategory(editDialog.form)
                await Promise.all([loadCategory(), loadCategoryOptions()])
            }
            if (editDialog.type === 'item') {
                await addQuoteItem(editDialog.form)
                loadItem()
                loadFilterOptions()
            }
            if (editDialog.type === 'row') {
                const columns = parseColumnsText(editDialog.columnsText)
                await addQuoteRow({
                    ...editDialog.form,
                    columns,
                    manual_prices: normalizeManualPrices(editDialog.form.manual_prices, columns.length)
                })
                loadRows()
            }
            ElMessage.success('新增成功')
            if (continueAfter && editDialog.type === 'category') {
                editDialog.form.name = ''
                editDialog.form.sort = 0
            } else {
                editDialog.visible = false
            }
            return
        }

        if (editDialog.type === 'category') {
            await saveCategory(editDialog.form)
            loadCategory()
            loadCategoryOptions()
        }
        if (editDialog.type === 'item') {
            await saveItem(editDialog.form)
            loadItem()
        }
        if (editDialog.type === 'row') {
            await saveRow(editDialog.form)
        }
        editDialog.visible = false
        ElMessage.success('保存成功')
    } finally {
        editDialog.submitting = false
    }
}

/* ------------------------------ 删除 ------------------------------ */

const deleteCategory = async (row: any) => {
    await ElMessageBox.confirm(`确定删除分类「${row.name}」吗？`, '删除确认', {
        type: 'warning',
        confirmButtonText: '删除',
        cancelButtonText: '取消'
    })
    await deleteQuoteCategory(row.id)
    ElMessage.success('分类已删除')
    if (selectedCategoryId.value === row.id) clearCategorySelection()
    loadCategory()
    loadCategoryOptions()
}

const deleteItem = async (row: any) => {
    await ElMessageBox.confirm(`确定删除报价项「${row.name}」吗？该报价项下的行价格会一并删除。`, '删除确认', {
        type: 'warning',
        confirmButtonText: '删除',
        cancelButtonText: '取消'
    })
    await deleteQuoteItem(row.id)
    ElMessage.success('报价项已删除')
    if (selectedItemId.value === row.id) {
        selectedItemId.value = 0
        selectedItemName.value = ''
        rowTable.data = []
        rowTable.total = 0
    }
    loadItem()
    loadFilterOptions()
}

const deleteRow = async (row: any) => {
    const source = row.source || row
    await ElMessageBox.confirm(`确定删除行价格「${source.model_name || source.name || ''}」吗？`, '删除确认', {
        type: 'warning',
        confirmButtonText: '删除',
        cancelButtonText: '取消'
    })
    await deleteQuoteRow(source.id)
    ElMessage.success('行价格已删除')
    loadRows()
}

/* ------------------------------ 批量操作 ------------------------------ */

const batchCategoryCommand = async (command: string) => {
    if (command === 'delete') {
        await ElMessageBox.confirm(`确定删除选中的 ${categorySelection.value.length} 个分类吗？仅会删除没有子分类、没有报价项的分类。`, '批量删除', {
            type: 'warning',
            confirmButtonText: '删除',
            cancelButtonText: '取消'
        })
        const results = await Promise.allSettled(categorySelection.value.map(row => deleteQuoteCategory(row.id)))
        const failed = results.filter(r => r.status === 'rejected').length
        if (failed) ElMessage.warning(`完成，其中 ${failed} 个未能删除（含子分类或报价项）`)
        else ElMessage.success('批量删除完成')
        loadCategory()
        loadCategoryOptions()
        return
    }
    const payloadMap: Record<string, Record<string, any>> = {
        show: { is_show: 1 },
        hide: { is_show: 0 },
        hot: { is_hot: 1 },
        unhot: { is_hot: 0 }
    }
    await Promise.all(categorySelection.value.map(row => editQuoteCategory(row.id, payloadMap[command])))
    ElMessage.success('批量操作完成')
    loadCategory()
}

const batchItemCommand = async (command: string) => {
    if (command === 'delete') {
        await ElMessageBox.confirm(`确定删除选中的 ${itemSelection.value.length} 个报价项吗？它们的行价格会一并删除。`, '批量删除', {
            type: 'warning',
            confirmButtonText: '删除',
            cancelButtonText: '取消'
        })
        await Promise.all(itemSelection.value.map(row => deleteQuoteItem(row.id)))
        ElMessage.success('批量删除完成')
        loadItem()
        loadFilterOptions()
        return
    }
    const payloadMap: Record<string, Record<string, any>> = {
        show: { is_show: 1 },
        hide: { is_show: 0 },
        hot: { is_hot: 1 },
        unhot: { is_hot: 0 },
        follow: { follow_source: 1 },
        unfollow: { follow_source: 0 }
    }
    await Promise.all(itemSelection.value.map(row => editQuoteItem(row.id, payloadMap[command])))
    ElMessage.success('批量操作完成')
    loadItem()
}

const batchRowCommand = async (command: string) => {
    if (command === 'delete') {
        await ElMessageBox.confirm(`确定删除选中的 ${rowSelection.value.length} 行价格吗？`, '批量删除', {
            type: 'warning',
            confirmButtonText: '删除',
            cancelButtonText: '取消'
        })
        await Promise.all(rowSelection.value.map(row => deleteQuoteRow(row.id)))
        ElMessage.success('批量删除完成')
        loadRows()
        return
    }
    if (command === 'fixed' || command === 'ratio') {
        adjustDialog.form.adjust_type = command === 'fixed' ? 1 : 2
        adjustDialog.form.adjust_value = 0
        adjustDialog.form.adjust_ratio = 1
        adjustDialog.visible = true
        return
    }
    const payloadMap: Record<string, Record<string, any>> = {
        show: { is_show: 1 },
        hide: { is_show: 0 },
        hot: { is_hot: 1 },
        unhot: { is_hot: 0 },
        follow: { follow_source: 1 },
        unfollow: { follow_source: 0 },
        clear: { adjust_type: 0, adjust_value: 0, adjust_ratio: 1 }
    }
    await Promise.all(rowSelection.value.map(row => editQuoteRow(row.id, payloadMap[command])))
    ElMessage.success('批量操作完成')
    loadRows()
}

const handleBatchAdjustTypeChange = (value: number) => {
    if (value === 1) adjustDialog.form.adjust_ratio = 1
    else adjustDialog.form.adjust_value = 0
}

const submitBatchAdjust = async () => {
    if (adjustDialog.submitting) return
    adjustDialog.submitting = true
    try {
        const payload = {
            adjust_type: adjustDialog.form.adjust_type,
            adjust_value: adjustDialog.form.adjust_value,
            adjust_ratio: adjustDialog.form.adjust_ratio
        }
        await Promise.all(rowSelection.value.map(row => editQuoteRow(row.id, payload)))
        adjustDialog.visible = false
        ElMessage.success('批量调价完成')
        loadRows()
    } finally {
        adjustDialog.submitting = false
    }
}

/* ------------------------------ Excel 导入 ------------------------------ */

const prepareExcelImport = (row: any) => {
    if (row?.id) {
        selectedItemId.value = row.id
        selectedItemName.value = row.name
        rowDrawer.item = { ...row }
    }
    excelImportForm.source_id = Number(row?.source_id || selectedSourceId.value || '')
    excelImportForm.category_id = Number(row?.category_id || selectedCategoryId.value || '')
    excelImportForm.item_id = Number(row?.id || selectedItemId.value || '')
    excelImportForm.item_name = row?.name || selectedItemName.value || ''
    excelImportForm.notice_text = row?.notice_text || ''
    excelImportForm.brand = row?.brand || ''
    excelImportForm.tab = row?.tab || ''
    if (row?.id) {
        rowDrawer.visible = true
        rowDrawer.activeTab = 'import'
    } else {
        activeTab.value = 'access'
        accessMode.value = 'excel'
    }
}

const handleExcelChange = async (file: UploadFile) => {
    if (!file.raw) return
    excelFileName.value = file.name
    const formData = new FormData()
    formData.append('file', file.raw)
    if (excelSourceId.value) formData.append('source_id', String(excelSourceId.value))
    const uploadRes: any = await uploadQuoteExcel(formData)
    excelTaskId.value = Number(uploadRes.data.task_id || 0)
    excelSheets.value = Array.isArray(uploadRes.data.sheets) ? uploadRes.data.sheets : []
    const previewRes: any = await previewQuoteExcel({ task_id: uploadRes.data.task_id, preview_count: 20 })
    excelPreview.value = previewRes.data
    const drawerItem = rowDrawer.visible ? rowDrawer.item : {}
    excelImportForm.source_id = Number(
        excelImportForm.source_id || excelSourceId.value || selectedSourceId.value || drawerItem.source_id || ''
    )
    excelImportForm.category_id = Number(excelImportForm.category_id || selectedCategoryId.value || drawerItem.category_id || '')
    excelImportForm.item_id = excelImportForm.item_id || selectedItemId.value || drawerItem.id || ''
    excelImportForm.item_name =
        excelImportForm.item_name || selectedItemName.value || drawerItem.name || file.name.replace(/\.(xls|xlsx)$/i, '')
    excelImportForm.notice_text = excelImportForm.notice_text || previewRes.data?.notice_text || ''
    // 多工作表 → 默认进入「按品牌批量分流」模式
    batchMode.value = excelSheets.value.length > 1
    if (batchMode.value) {
        await loadBatchCategoryItems()
        buildBatchTargets()
    }
    if (rowDrawer.visible) rowDrawer.activeTab = 'import'
    else {
        activeTab.value = 'access'
        accessMode.value = 'excel'
    }
}

/* ----------------------- 按品牌批量导入 ----------------------- */

const normalizeSeriesName = (name: any) =>
    String(name || '')
        .toLowerCase()
        .replace(/\s|系列|系|\//g, '')

const loadBatchCategoryItems = async () => {
    if (!excelImportForm.category_id) {
        batchCategoryItems.value = []
        return
    }
    const res: any = await getQuoteItemList({
        source_id: excelImportForm.source_id || undefined,
        category_id: excelImportForm.category_id,
        page: 1,
        limit: 100
    })
    batchCategoryItems.value = res.data?.data || []
}

const matchSheetItem = (sheetName: string) => {
    const key = normalizeSeriesName(sheetName)
    if (!key) return 0
    let hit = batchCategoryItems.value.find(it => {
        const n = normalizeSeriesName(it.name)
        const t = normalizeSeriesName(it.tab)
        return n === key || t === key
    })
    if (!hit) {
        hit = batchCategoryItems.value.find(it => {
            const n = normalizeSeriesName(it.name)
            const t = normalizeSeriesName(it.tab)
            return (n && (n.includes(key) || key.includes(n))) || (t && (t.includes(key) || key.includes(t)))
        })
    }
    return hit ? Number(hit.id) : 0
}

const buildBatchTargets = () => {
    batchTargets.value = excelSheets.value.map((s: any) => {
        const sheetName = s.name || s.sheet_name || ''
        return {
            sheet_name: sheetName,
            row_count: s.row_count || 0,
            enabled: true,
            item_id: matchSheetItem(sheetName),
            item_name: sheetName
        }
    })
}

const handleBatchCategoryChange = async () => {
    await loadBatchCategoryItems()
    batchTargets.value.forEach(t => {
        t.item_id = matchSheetItem(t.sheet_name)
    })
}

const batchSelectedCount = computed(() => batchTargets.value.filter(t => t.enabled).length)

const batchConfirmExcelImport = async () => {
    if (!excelTaskId.value) {
        ElMessage.error('请先上传Excel')
        return
    }
    if (!excelImportForm.source_id) {
        ElMessage.error('请选择报价源')
        return
    }
    if (!excelImportForm.category_id) {
        ElMessage.error('请选择导入到的分类')
        return
    }
    const sheets = batchTargets.value
        .filter(t => t.enabled)
        .map(t => ({
            sheet_name: t.sheet_name,
            item_id: t.item_id || 0,
            item_name: t.item_name || t.sheet_name
        }))
    if (!sheets.length) {
        ElMessage.error('请至少勾选一个工作表')
        return
    }
    batchImporting.value = true
    try {
        const res: any = await batchConfirmQuoteExcel({
            task_id: excelTaskId.value,
            source_id: excelImportForm.source_id,
            category_id: excelImportForm.category_id,
            notice_text: excelImportForm.notice_text,
            brand: excelImportForm.brand,
            mode: 'replace',
            sheets
        })
        const data = res.data || {}
        const failed = (data.sheets || []).filter((s: any) => !s.success)
        if (failed.length) {
            ElMessage.warning(
                `已导入 ${data.sheet_count || 0} 个工作表 / ${data.total_rows || 0} 行；${failed.length} 个失败：` +
                    failed.map((f: any) => `${f.sheet_name}(${f.message})`).join('、')
            )
        } else {
            ElMessage.success(`批量导入完成，共 ${data.sheet_count || 0} 个工作表 / ${data.total_rows || 0} 行`)
        }
        activeTab.value = 'workbench'
        selectedSourceId.value = excelImportForm.source_id
        selectedCategoryId.value = excelImportForm.category_id
        refreshManage()
    } finally {
        batchImporting.value = false
    }
}

const resetExcel = () => {
    excelFileName.value = ''
    excelPreview.value = {}
    excelTaskId.value = 0
    excelSheets.value = []
    batchMode.value = false
    batchTargets.value = []
    batchCategoryItems.value = []
}

const downloadExcelTemplate = () => {
    const rows = [
        ['报价提示', '温馨提示：报价仅供参考，最终价格以质检结果为准\n请确认设备型号、容量、成色与功能状态后再下单'],
        ['分组', '型号', '品牌', '内存', '靓机', '小花', '内爆', '备注'],
        ['17系列', 'iPhone 17', '苹果', '128GB', 5200, 5000, 4300, '正常回收报价'],
        ['17系列', 'iPhone 17 Pro', '苹果', '256GB', 6500, 6200, 5400, ''],
        ['17系列', 'iPhone 17 Pro Max', '苹果', '256GB', 7200, 6900, 6100, ''],
        ['Mate系列', 'Mate 60 Pro', '华为', '12+256GB', 4100, 3900, 3300, '']
    ]
    const tips = [
        ['字段', '是否必填', '说明'],
        ['报价提示', '选填', '固定写在第一行：第一列写“报价提示”，第二列写提示内容；单元格内换行会保存为 \\n。'],
        ['分组/系列', '选填', '用于把 17、17 Pro、17 Pro Max 等型号归到同一个系列，后台表格会按它跨行展示。'],
        ['型号', '必填', '每一行会导入为一个行价格；没有型号的行会被跳过。'],
        ['品牌', '选填', '为空时使用导入表单里填写的品牌。'],
        ['内存', '选填', '会写入行价格原始数据，用于后台 Excel 表格展示。'],
        ['价格列', '至少一列', '列名可以是靓机、小花、内爆、外爆、开机、不开机等；系统会识别为价格列。'],
        ['备注', '选填', '导入到行价格备注。'],
        ['覆盖报价项', '-', '会清空所选报价项原有行价格，再用 Excel 重新生成。Excel 里有的新型号会新增，Excel 里没有的旧型号会被删除。'],
        ['新建报价项', '-', '不选择覆盖报价项时，会在所选分类下新建一个报价项，并把 Excel 行写入这个报价项。']
    ]
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet(rows), '报价导入模板')
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet(tips), '填写说明')
    XLSX.writeFile(workbook, `回收报价导入模板_${new Date().toISOString().slice(0, 10)}.xlsx`)
}

const handleExcelSourceChange = async () => {
    selectedSourceId.value = excelImportForm.source_id
    excelImportForm.category_id = ''
    excelImportForm.item_id = ''
    await loadCategoryOptions()
    await loadItem()
}

const confirmExcelImport = async () => {
    if (!excelTaskId.value) {
        ElMessage.error('请先上传Excel')
        return
    }
    if (!excelImportForm.category_id) {
        ElMessage.error('请选择导入到的分类')
        return
    }
    if (!excelImportForm.item_id && !String(excelImportForm.item_name || '').trim()) {
        ElMessage.error('请选择要覆盖的报价项，或填写新报价项名称')
        return
    }
    excelImporting.value = true
    try {
        const res: any = await confirmQuoteExcel({
            task_id: excelTaskId.value,
            source_id: excelImportForm.source_id,
            category_id: excelImportForm.category_id,
            item_id: excelImportForm.item_id,
            item_name: excelImportForm.item_name,
            notice_text: excelImportForm.notice_text,
            brand: excelImportForm.brand,
            tab: excelImportForm.tab,
            mapping: excelPreview.value.suggested_mapping || [],
            mode: 'replace'
        })
        ElMessage.success(`导入完成，已写入 ${res.data.rows || 0} 行`)
        activeTab.value = 'workbench'
        selectedSourceId.value = excelImportForm.source_id
        selectedCategoryId.value = excelImportForm.category_id
        selectedItemId.value = Number(res.data.item_id || 0)
        selectedItemName.value = excelImportForm.item_name
        refreshManage()
        if (rowDrawer.visible) {
            rowDrawer.activeTab = 'rows'
            loadRows()
        }
    } finally {
        excelImporting.value = false
    }
}

const resetSourceSearch = () => {
    sourceQuery.keyword = ''
    sourceQuery.status = ''
    loadSources(1)
}

/* ------------------------------ 导出 ------------------------------ */

export function useQuoteSpider() {
    return {
        // state
        activeTab,
        accessMode,
        sourceLoading,
        categoryLoading,
        itemLoading,
        rowLoading,
        logLoading,
        importLogLoading,
        summaryLoading,
        syncingId,
        selectedSourceId,
        selectedCategoryId,
        selectedItemId,
        selectedItemName,
        sourceOptions,
        categoryOptions,
        categorySelection,
        itemSelection,
        rowSelection,
        excelSourceId,
        excelFileName,
        excelPreview,
        excelTaskId,
        excelImporting,
        sourceTable,
        categoryTable,
        itemTable,
        rowTable,
        logTable,
        importLogTable,
        workbenchSummary,
        sourceQuery,
        categoryQuery,
        itemQuery,
        rowQuery,
        itemDate,
        rowDate,
        applyItemDate,
        applyRowDate,
        disableFutureDate,
        filterOptions,
        excelImportForm,
        sourceDialog,
        editDialog,
        rowDrawer,
        adjustDialog,
        historyDialog,
        // constants
        followSourceOptions,
        quoteModeOptions,
        adjustTypeOptions,
        // computed
        selectedSource,
        selectedCategoryName,
        selectedCategoryScopeIds,
        editDialogTitle,
        rowEditModeTip,
        rowPriceTable,
        rowMatrixSections,
        getRowMatrixCell,
        getPriceTrend,
        openPriceHistory,
        loadPriceHistory,
        setHistoryDays,
        excelPreviewTable,
        excelPriceColumnCount,
        batchAdjustRowsPreview,
        batchAdjustTip,
        batchAdjustPreviewText,
        // helpers
        formatTime,
        syncIntervalText,
        sourceSyncText,
        formatPrices,
        formatMoney,
        isRemarkColumn,
        isPriceColumn,
        normalizePriceArray,
        normalizeManualPrices,
        resolveQuoteItemIcon,
        imageUrl,
        getDisplayBrand,
        getCapacityName,
        // loaders
        loadSources,
        loadCategory,
        loadCategoryOptions,
        loadFilterOptions,
        loadItem,
        loadRows,
        loadLogs,
        loadImportLogs,
        loadWorkbenchSummary,
        refreshManage,
        searchManage,
        resetFilters,
        // selection / switching
        handleSourceFilterChange,
        switchSource,
        handleSourceSizeChange,
        handleCategoryFilterChange,
        openSourceData,
        selectCategory,
        clearCategorySelection,
        selectItem,
        openRowDrawer,
        handleCategorySearch,
        handleItemSearch,
        handleItemSizeChange,
        handleRowSearch,
        handleRowSizeChange,
        handleRowSelectionChange,
        handleTabChange,
        // source dialog
        openSourceDialog,
        parseSourceCurl,
        saveSource,
        createDefaultSource,
        syncSource,
        startLogRefresh,
        stopLogRefresh,
        resetSourceSearch,
        // inline save
        saveCategory,
        saveItem,
        saveDrawerItem,
        saveRow,
        saveInlineRowPrices,
        // edit dialog
        openEditDialog,
        openCreateCategory,
        openCreateItem,
        openCreateRow,
        handleRowModeChange,
        submitEdit,
        // delete
        deleteCategory,
        deleteItem,
        deleteRow,
        // batch
        batchCategoryCommand,
        batchItemCommand,
        batchRowCommand,
        handleBatchAdjustTypeChange,
        submitBatchAdjust,
        // excel
        prepareExcelImport,
        handleExcelChange,
        resetExcel,
        downloadExcelTemplate,
        handleExcelSourceChange,
        confirmExcelImport,
        // excel 批量分流
        excelSheets,
        batchMode,
        batchImporting,
        batchTargets,
        batchCategoryItems,
        batchSelectedCount,
        handleBatchCategoryChange,
        batchConfirmExcelImport
    }
}
