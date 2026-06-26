<template>
    <el-drawer v-model="rowDrawer.visible" size="80%" :with-header="false" destroy-on-close class="qs-scope quote-spider-drawer">
        <div class="drawer-shell">
            <div class="drawer-head">
                <div>
                    <div class="drawer-title">{{ selectedItemName || '价格管理' }}</div>
                    <div class="panel-subtitle">
                        {{ rowDrawer.item.brand || '-' }} · {{ rowDrawer.item.tab || '-' }} · {{ rowDrawer.item.parent_name || selectedCategoryName || '-' }}
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
                        </el-form>

                        <div class="rows-table-wrap">
                            <el-table
                                v-loading="rowLoading"
                                :data="rowMatrixRows"
                                border
                                size="large"
                                height="100%"
                                row-key="id"
                                :span-method="rowMatrixSpanMethod"
                                class="excel-matrix-table row-price-matrix"
                                @selection-change="handleRowSelectionChange"
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
                        <el-table-column
                            v-for="column in rowMatrixPriceColumns"
                            :key="column.key"
                            :label="column.label"
                            :min-width="column.minWidth"
                            :align="column.align"
                            show-overflow-tooltip
                        >
                            <template #default="{ row }">
                                <span
                                    :class="[
                                        column.isRemark ? 'excel-remark-cell' : 'excel-price-cell',
                                        getPriceTrend(row, column) === 'up' ? 'price-up' : getPriceTrend(row, column) === 'down' ? 'price-down' : ''
                                    ]"
                                >
                                    {{ getRowMatrixCell(row, column) }}
                                    <i v-if="getPriceTrend(row, column) === 'up'" class="trend-arrow">▲</i>
                                    <i v-else-if="getPriceTrend(row, column) === 'down'" class="trend-arrow">▼</i>
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column label="排序" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.source.sort" :min="0" :controls="false" class="sort-input" @change="saveRow(row.source, true)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="跟随" width="80">
                            <template #default="{ row }">
                                <el-switch v-model="row.source.follow_source" :active-value="1" :inactive-value="0" @change="saveRow(row.source, true)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="显示" width="72">
                            <template #default="{ row }">
                                <el-switch v-model="row.source.is_show" :active-value="1" :inactive-value="0" @change="saveRow(row.source, true)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="热门" width="72">
                            <template #default="{ row }">
                                <el-switch v-model="row.source.is_hot" :active-value="1" :inactive-value="0" @change="saveRow(row.source, true)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="168" fixed="right">
                            <template #default="{ row }">
                                <el-button link type="primary" @click.stop="openPriceHistory(row)">趋势</el-button>
                                <el-button link type="primary" @click.stop="openEditDialog('row', row.source)">编辑</el-button>
                                <el-button link type="danger" @click.stop="deleteRow(row)">删除</el-button>
                            </template>
                                </el-table-column>
                            </el-table>
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
import { ArrowDown } from '@element-plus/icons-vue'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

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
    rowMatrixRows,
    rowMatrixPriceColumns,
    rowMatrixSpanMethod,
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
    handleRowSelectionChange,
    batchRowCommand,
    getDisplayBrand,
    getRowMatrixCell,
    getPriceTrend,
    openPriceHistory,
    saveRow,
    deleteRow,
    handleExcelChange,
    downloadExcelTemplate,
    handleExcelSourceChange,
    confirmExcelImport,
    saveDrawerItem
} = useQuoteSpider()
</script>
