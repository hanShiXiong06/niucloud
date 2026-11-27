<template>
    <el-drawer v-model="rowDrawer.visible" size="80%" :with-header="false" destroy-on-close class="qs-scope quote-spider-drawer">
        <div class="drawer-shell">
            <div class="drawer-head">
                <div>
                    <div class="drawer-title">{{ selectedItemName || '价格管理' }}</div>
                    <div class="panel-subtitle drawer-meta-line">
                        <span v-for="meta in drawerMetaItems" :key="meta">{{ meta }}</span>
                    </div>
                </div>
                <div class="drawer-actions">
                    <el-button @click="prepareExcelImport(rowDrawer.item)">Excel覆盖</el-button>
                    <el-button type="primary" @click="openEditDialog('item', rowDrawer.item)">编辑报价项</el-button>
                </div>
            </div>

            <el-tabs v-model="rowDrawer.activeTab" class="drawer-tabs">
                <!-- 行价格 -->
                <el-tab-pane label="行价格" name="rows">
                    <div class="rows-pane">
                        <el-alert
                            class="inline-edit-alert"
                            type="info"
                            :closable="false"
                            title="价格可直接修改，输入后自动保存。首次手改会把该型号切换为人工维护，后续数据源同步不会覆盖人工价格。"
                        />
                        <el-form :inline="true" :model="rowQuery" class="row-filter-form">
                            <el-form-item label="型号">
                                <el-input v-model="rowQuery.keyword" clearable placeholder="型号、备注" class="w-[200px]" @keyup.enter="handleRowSearch" />
                            </el-form-item>
                            <el-form-item label="热门">
                                <el-select v-model="rowQuery.is_hot" clearable placeholder="全部" class="w-[100px]" @change="handleRowSearch">
                                    <el-option label="热门" :value="1" />
                                    <el-option label="非热门" :value="0" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="报价日期">
                                <el-date-picker
                                    :model-value="rowDate"
                                    type="date"
                                    value-format="x"
                                    placeholder="指定某一天"
                                    class="!w-[150px]"
                                    :disabled-date="disableFutureDate"
                                    @update:model-value="applyRowDate"
                                />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="handleRowSearch">查询</el-button>
                                <el-button :disabled="!selectedItemId" @click="openCreateRow">新增行价格</el-button>
                                <el-dropdown :disabled="!rowSelection.length" @command="batchRowCommand">
                                    <el-button>批量操作<el-icon class="ml-[4px]"><ArrowDown /></el-icon></el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="show">显示</el-dropdown-item>
                                            <el-dropdown-item command="hide">隐藏</el-dropdown-item>
                                            <el-dropdown-item command="hot">设为热门</el-dropdown-item>
                                            <el-dropdown-item command="unhot">取消热门</el-dropdown-item>
                                            <el-dropdown-item command="follow" divided>跟随爬虫</el-dropdown-item>
                                            <el-dropdown-item command="unfollow">取消跟随</el-dropdown-item>
                                            <el-dropdown-item command="fixed" divided>固定调整</el-dropdown-item>
                                            <el-dropdown-item command="ratio">比例调整</el-dropdown-item>
                                            <el-dropdown-item command="clear">清除调价</el-dropdown-item>
                                            <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <span class="panel-subtitle ml-[6px]">共 {{ rowTable.total }} 行 · 已选 {{ rowSelection.length }}</span>
                            </el-form-item>
                            <el-form-item class="matrix-zoom-form-item">
                                <div class="matrix-zoom-control">
                                    <el-tooltip content="缩小表格" placement="top">
                                        <el-button :icon="ZoomOut" circle :disabled="matrixZoom <= 75" aria-label="缩小表格" @click="changeMatrixZoom(-5)" />
                                    </el-tooltip>
                                    <button type="button" class="matrix-zoom-value" title="恢复默认缩放" @click="setMatrixZoom(85)">{{ matrixZoom }}%</button>
                                    <el-tooltip content="放大表格" placement="top">
                                        <el-button :icon="ZoomIn" circle :disabled="matrixZoom >= 100" aria-label="放大表格" @click="changeMatrixZoom(5)" />
                                    </el-tooltip>
                                </div>
                            </el-form-item>
                        </el-form>

                        <div v-loading="rowLoading" class="rows-table-wrap">
                            <div class="price-sections-zoom" :style="matrixZoomStyle">
                            <section v-for="section in rowMatrixSections" :key="section.key" class="price-section">
                                <div class="price-section-head">
                                    <div>
                                        <strong>{{ section.title || '未命名型号' }}</strong>
                                        <span>{{ section.modelCount }} 个型号 · {{ section.rowCount }} 行</span>
                                    </div>
                                    <el-tag size="small" type="info">独立表头</el-tag>
                                </div>
                            <el-table
                                :data="section.rows"
                                border
                                size="large"
                                row-key="id"
                                :span-method="section.spanMethod"
                                class="excel-matrix-table row-price-matrix"
                                @selection-change="handleSectionSelectionChange(section.key, $event)"
                            >
                                <template #empty>
                                    <el-empty v-if="!rowLoading" description="该报价项还没有行价格，点“新增行价格”或用 Excel 覆盖导入" /> </template>
                        <el-table-column type="selection" width="44" />
                        <el-table-column prop="group_name" label="分组/系列" width="136" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div class="matrix-group-cell">{{ row.group_name || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="model_name" label="型号" min-width="220" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div class="name-main">{{ row.model_name || '-' }}</div>
                                <div v-if="getDisplayBrand(row)" class="name-sub">{{ getDisplayBrand(row) }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="capacity_name" label="内存/规格" width="120" show-overflow-tooltip>
                            <template #default="{ row }">{{ row.capacity_name || '-' }}</template>
                        </el-table-column>
                        <template v-for="group in section.columnGroups" :key="group.key">
                            <el-table-column v-if="group.grouped" :label="group.label" align="center">
                                <el-table-column
                                    v-for="column in group.children"
                                    :key="column.key"
                                    :label="column.displayLabel"
                                    :min-width="column.minWidth"
                                    :align="column.align"
                                >
                                    <template #default="{ row }">
                                        <QuoteInlinePriceCell
                                            :value="column.isRemark ? getRowMatrixCell(row, column) : getInlinePriceValue(row, column)"
                                            :label="`${row.model_name} ${column.label}`"
                                            :editable="getInlinePriceIndex(row, column) >= 0"
                                            :is-remark="column.isRemark"
                                            :save-state="rowSaveState[row.id]"
                                            :trend="getPriceTrend(row, column)"
                                            @update="handleInlinePriceInput(row, column, $event)"
                                            @flush="flushInlinePrice(row)"
                                        />
                                    </template>
                                </el-table-column>
                            </el-table-column>
                            <el-table-column
                                v-else
                                :label="group.label"
                                :min-width="group.children[0].minWidth"
                                :align="group.children[0].align"
                            >
                                <template #default="{ row }">
                                    <QuoteInlinePriceCell
                                        :value="group.children[0].isRemark ? getRowMatrixCell(row, group.children[0]) : getInlinePriceValue(row, group.children[0])"
                                        :label="`${row.model_name} ${group.children[0].label}`"
                                        :editable="getInlinePriceIndex(row, group.children[0]) >= 0"
                                        :is-remark="group.children[0].isRemark"
                                        :save-state="rowSaveState[row.id]"
                                        :trend="getPriceTrend(row, group.children[0])"
                                        @update="handleInlinePriceInput(row, group.children[0], $event)"
                                        @flush="flushInlinePrice(row)"
                                    />
                                </template>
                            </el-table-column>
                        </template>
                        <el-table-column label="排序" width="100">
                            <template #default="{ row }">
                                <input
                                    class="row-sort-native"
                                    type="number"
                                    min="0"
                                    :value="row.source.sort"
                                    :aria-label="`${row.model_name} 排序`"
                                    @change="handleRowSortChange(row.source, $event)"
                                />
                            </template>
                        </el-table-column>
                        <el-table-column label="跟随" width="80">
                            <template #default="{ row }">
                                <button
                                    type="button"
                                    class="row-native-switch"
                                    :class="{ 'is-on': Number(row.source.follow_source) === 1 }"
                                    role="switch"
                                    :aria-checked="Number(row.source.follow_source) === 1"
                                    :aria-label="`${row.model_name} 跟随数据源`"
                                    @click="toggleRowField(row.source, 'follow_source')"
                                ><span /></button>
                            </template>
                        </el-table-column>
                        <el-table-column label="显示" width="72">
                            <template #default="{ row }">
                                <button
                                    type="button"
                                    class="row-native-switch"
                                    :class="{ 'is-on': Number(row.source.is_show) === 1 }"
                                    role="switch"
                                    :aria-checked="Number(row.source.is_show) === 1"
                                    :aria-label="`${row.model_name} 前台显示`"
                                    @click="toggleRowField(row.source, 'is_show')"
                                ><span /></button>
                            </template>
                        </el-table-column>
                        <el-table-column label="热门" width="72">
                            <template #default="{ row }">
                                <button
                                    type="button"
                                    class="row-native-switch"
                                    :class="{ 'is-on': Number(row.source.is_hot) === 1 }"
                                    role="switch"
                                    :aria-checked="Number(row.source.is_hot) === 1"
                                    :aria-label="`${row.model_name} 热门`"
                                    @click="toggleRowField(row.source, 'is_hot')"
                                ><span /></button>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="168" fixed="right">
                            <template #default="{ row }">
                                <div class="row-native-actions">
                                    <button type="button" @click.stop="openPriceHistory(row)">7/30天走势</button>
                                    <button type="button" @click.stop="openEditDialog('row', row.source)">编辑</button>
                                    <button type="button" class="is-danger" @click.stop="deleteRow(row)">删除</button>
                                </div>
                            </template>
                                </el-table-column>
                            </el-table>
                            </section>
                            <el-empty v-if="!rowLoading && !rowMatrixSections.length" description="该报价项还没有行价格，点“新增行价格”或用 Excel 覆盖导入" />
                            </div>
                        </div>
                    </div>
                </el-tab-pane>

                <!-- 导入覆盖 -->
                <el-tab-pane label="导入覆盖" name="import">
                    <div class="drawer-import">
                        <el-alert
                            type="warning"
                            :closable="false"
                            title="当前是覆盖已有报价项：确认后会清空这个报价项下已有行价格，再按 Excel 重新生成。Excel 里新增的型号会作为新行价格写入；Excel 里没有的旧型号会被移除。"
                        />
                        <div class="excel-pane mt-[14px]">
                            <el-upload :auto-upload="false" :show-file-list="false" accept=".xls,.xlsx" :on-change="handleExcelChange">
                                <el-button type="primary">选择Excel</el-button>
                            </el-upload>
                            <el-button @click="downloadExcelTemplate">下载模板</el-button>
                            <span class="muted">{{ excelFileName || '选择后会先预览，不会立即入库' }}</span>
                        </div>
                        <el-form :inline="true" :model="excelImportForm" class="mt-[14px]">
                            <el-form-item label="报价源">
                                <el-select v-model="excelImportForm.source_id" filterable placeholder="选择报价源" class="w-[220px]" @change="handleExcelSourceChange">
                                    <el-option v-for="item in sourceOptions" :key="item.id" :label="item.source_name" :value="item.id" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="分类">
                                <el-tree-select
                                    v-model="excelImportForm.category_id"
                                    check-strictly
                                    node-key="id"
                                    :data="categoryOptions"
                                    :props="{ label: 'name', value: 'id', children: 'children' }"
                                    placeholder="选择分类"
                                    class="w-[220px]"
                                />
                            </el-form-item>
                            <el-form-item label="覆盖报价项">
                                <el-select v-model="excelImportForm.item_id" filterable placeholder="当前报价项" class="w-[240px]">
                                    <el-option v-for="item in itemTable.data" :key="item.id" :label="item.name" :value="item.id" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="报价提示">
                                <el-input
                                    v-model="excelImportForm.notice_text"
                                    type="textarea"
                                    :rows="2"
                                    class="excel-notice-input"
                                    placeholder="默认使用系统提示；Excel 中“报价提示”列有内容时会自动读取，换行会保存为 \n"
                                />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" :loading="excelImporting" :disabled="!excelTaskId" @click="confirmExcelImport">确认覆盖</el-button>
                            </el-form-item>
                        </el-form>
                        <el-table
                            v-if="excelPreviewTable.rows.length"
                            :data="excelPreviewTable.rows"
                            border
                            size="large"
                            max-height="360"
                            class="excel-matrix-table"
                        >
                            <el-table-column prop="row_number" label="行号" width="76" fixed />
                            <el-table-column
                                v-for="column in excelPreviewTable.columns"
                                :key="column.key"
                                :prop="column.key"
                                :label="column.label"
                                :min-width="column.minWidth"
                                show-overflow-tooltip
                            >
                                <template #default="{ row }">
                                    <span :class="column.isPrice ? 'excel-price-cell' : 'excel-plain-cell'">
                                        {{ row[column.key] || '-' }}
                                    </span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </el-tab-pane>

                <!-- 基础信息 -->
                <el-tab-pane label="基础信息" name="info">
                    <div class="item-info-layout">
                        <section class="row-edit-section">
                            <div class="section-title">报价项信息</div>
                            <el-descriptions :column="2" border>
                                <el-descriptions-item label="名称">{{ rowDrawer.item.name || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="品牌">{{ rowDrawer.item.brand || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="分组">{{ rowDrawer.item.tab || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="类型">{{ rowDrawer.item.is_image_quote ? '图片报价' : '结构化报价' }}</el-descriptions-item>
                                <el-descriptions-item label="排序">{{ rowDrawer.item.sort ?? 0 }}</el-descriptions-item>
                                <el-descriptions-item label="关键词">{{ rowDrawer.item.keywords || '-' }}</el-descriptions-item>
                            </el-descriptions>
                            <div class="mt-[14px]">
                                <div class="image-setting-title mb-[8px]">报价详情提示</div>
                                <el-input
                                    v-model="rowDrawer.item.notice_text"
                                    type="textarea"
                                    :rows="4"
                                    placeholder="温馨提示：报价仅供参考，最终价格以质检结果为准"
                                />
                                <div class="form-tip">移动端报价详情 notice-card 会展示这段文案；多行文案按换行展示。</div>
                            </div>
                        </section>
                        <section class="row-edit-section">
                            <div class="section-title">展示图片</div>
                            <div class="image-setting-grid">
                                <div class="image-setting-card">
                                    <div class="image-setting-title">导航图标</div>
                                    <upload-image v-model="rowDrawer.item.icon" :limit="1" />
                                    <div class="form-tip">用于低代码图文导航、报价项列表缩略图。你手动替换后会优先展示这个图标。</div>
                                </div>
                                <div class="image-setting-card">
                                    <div class="image-setting-title">报价图片</div>
                                    <upload-image v-model="rowDrawer.item.image" :limit="1" />
                                    <div class="form-tip">图片报价会使用这张图；结构化报价也可以保留为辅助资料。</div>
                                </div>
                            </div>
                            <div class="mt-[12px]">
                                <el-button type="primary" @click="saveDrawerItem">保存基础信息</el-button>
                            </div>
                        </section>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </div>
    </el-drawer>
</template>

<script lang="ts" setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ArrowDown, ZoomIn, ZoomOut } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'
import QuoteInlinePriceCell from './QuoteInlinePriceCell.vue'

const {
    rowDrawer,
    selectedItemName,
    selectedCategoryName,
    selectedItemId,
    rowQuery,
    rowDate,
    applyRowDate,
    disableFutureDate,
    rowLoading,
    rowTable,
    rowSelection,
    rowMatrixSections,
    sourceOptions,
    categoryOptions,
    itemTable,
    excelFileName,
    excelImportForm,
    excelImporting,
    excelTaskId,
    excelPreviewTable,
    prepareExcelImport,
    openEditDialog,
    openCreateRow,
    handleRowSearch,
    batchRowCommand,
    getDisplayBrand,
    getRowMatrixCell,
    getPriceTrend,
    normalizePriceArray,
    formatTime,
    openPriceHistory,
    saveRow,
    saveInlineRowPrices,
    deleteRow,
    handleExcelChange,
    downloadExcelTemplate,
    handleExcelSourceChange,
    confirmExcelImport,
    saveDrawerItem
} = useQuoteSpider()

type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error'
const rowSaveState = reactive<Record<number, SaveState>>({})
const rowSaveTimers = new Map<number, number>()
const savedPriceSnapshots = new Map<number, any[]>()
const rowVersions = new Map<number, number>()
const sectionSelections = reactive<Record<string, any[]>>({})
const storedMatrixZoom = Number(window.localStorage.getItem('recycle_quote_spider_matrix_zoom') || 85)
const matrixZoom = ref(Math.min(100, Math.max(75, Number.isFinite(storedMatrixZoom) ? storedMatrixZoom : 85)))
const matrixZoomStyle = computed(() => ({
    zoom: matrixZoom.value / 100,
    width: `${10000 / matrixZoom.value}%`
}))

const drawerMetaItems = computed(() => {
    const item = rowDrawer.item || {}
    const values = [item.source_name, item.category_name || selectedCategoryName.value, item.brand, item.tab]
        .map(value => String(value || '').trim())
        .filter((value, index, list) => value && list.indexOf(value) === index)
    const priceUpdatedAt = Number(item.latest_price_at || item.update_at || 0)
    if (priceUpdatedAt > 0) values.push(`价格更新于 ${formatTime(priceUpdatedAt)}`)
    if (String(item.latest_record_date || '').trim()) values.push(`最新快照 ${item.latest_record_date}`)
    return values.length ? values : ['暂无有效报价时间']
})

const setMatrixZoom = (value: number) => {
    matrixZoom.value = Math.min(100, Math.max(75, value))
    window.localStorage.setItem('recycle_quote_spider_matrix_zoom', String(matrixZoom.value))
}
const changeMatrixZoom = (step: number) => setMatrixZoom(matrixZoom.value + step)

type RowToggleField = 'follow_source' | 'is_show' | 'is_hot'
const toggleRowField = (source: any, field: RowToggleField) => {
    source[field] = Number(source[field]) === 1 ? 0 : 1
    saveRow(source, true)
}

const handleRowSortChange = (source: any, event: Event) => {
    const input = event.currentTarget as HTMLInputElement
    source.sort = Math.max(0, Number.parseInt(input.value || '0', 10) || 0)
    saveRow(source, true)
}

const handleSectionSelectionChange = (sectionKey: string, rows: any[]) => {
    sectionSelections[sectionKey] = rows.map(row => row.source || row)
    rowSelection.value = Object.values(sectionSelections).flat()
}

watch(
    () => rowMatrixSections.value.map(section => section.key).join(','),
    () => {
        Object.keys(sectionSelections).forEach(key => delete sectionSelections[key])
        rowSelection.value = []
    }
)

const getInlinePriceIndex = (row: any, column: any) => {
    if (column?.isRemark) return -1
    const source = row.source || row
    const index = Number(column?.index)
    return index >= 0 && index < (Array.isArray(source.columns) ? source.columns.length : 0) ? index : -1
}

const getInlinePriceValue = (row: any, column: any) => {
    const source = row.source || row
    const index = getInlinePriceIndex(row, column)
    return index < 0 ? '' : ((Array.isArray(source.final_prices) ? source.final_prices : [])[index] ?? '')
}

const handleInlinePriceInput = (row: any, column: any, value: string) => {
    const source = row.source || row
    const rowId = Number(source.id)
    const index = getInlinePriceIndex(row, column)
    if (index < 0) return
    if (!savedPriceSnapshots.has(rowId)) savedPriceSnapshots.set(rowId, normalizePriceArray(source.final_prices))

    const nextPrices = normalizePriceArray(source.final_prices)
    while (nextPrices.length < normalizePriceArray(source.columns).length) nextPrices.push('')
    const trimmed = String(value ?? '').trim()
    nextPrices[index] = trimmed === '' ? '' : trimmed
    source.final_prices = nextPrices
    source.manual_prices = [...nextPrices]
    source.follow_source = 0
    source.adjust_type = 0
    source.adjust_value = 0
    source.adjust_ratio = 1
    rowSaveState[rowId] = 'dirty'
    rowVersions.set(rowId, (rowVersions.get(rowId) || 0) + 1)
    scheduleInlinePriceSave(row)
}

const scheduleInlinePriceSave = (row: any) => {
    const rowId = Number((row.source || row).id)
    const current = rowSaveTimers.get(rowId)
    if (current) window.clearTimeout(current)
    rowSaveTimers.set(rowId, window.setTimeout(() => persistInlinePrice(row), 600))
}

const flushInlinePrice = (row: any) => {
    const rowId = Number((row.source || row).id)
    if (rowSaveState[rowId] !== 'dirty') return
    const current = rowSaveTimers.get(rowId)
    if (current) window.clearTimeout(current)
    rowSaveTimers.delete(rowId)
    persistInlinePrice(row)
}

const persistInlinePrice = async (row: any) => {
    const source = row.source || row
    const rowId = Number(source.id)
    const version = rowVersions.get(rowId) || 0
    const submittedPrices = normalizePriceArray(source.final_prices)
    rowSaveState[rowId] = 'saving'
    try {
        await saveInlineRowPrices(source)
        savedPriceSnapshots.set(rowId, submittedPrices)
        if ((rowVersions.get(rowId) || 0) === version) {
            rowSaveState[rowId] = 'saved'
            window.setTimeout(() => {
                if (rowSaveState[rowId] === 'saved') rowSaveState[rowId] = 'idle'
            }, 1200)
        }
    } catch (error) {
        if ((rowVersions.get(rowId) || 0) === version) {
            const snapshot = savedPriceSnapshots.get(rowId) || []
            source.final_prices = [...snapshot]
            source.manual_prices = [...snapshot]
            rowSaveState[rowId] = 'error'
            ElMessage.error('价格保存失败，已恢复到上一次保存结果')
        }
    }
}

onBeforeUnmount(() => {
    rowSaveTimers.forEach(timer => window.clearTimeout(timer))
    rowSaveTimers.clear()
})
</script>
