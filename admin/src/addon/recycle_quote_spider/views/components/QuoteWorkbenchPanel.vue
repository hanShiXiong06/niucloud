<template>
    <div class="quote-workbench">
        <div v-loading="summaryLoading" class="workbench-summary">
            <div class="summary-item">
                <span class="summary-label">报价单</span>
                <strong>{{ workbenchSummary.item_count }}</strong>
                <span class="summary-note">来自 {{ workbenchSummary.enabled_source_count }} 个启用数据源</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">结构化型号</span>
                <strong>{{ workbenchSummary.row_count }}</strong>
                <span class="summary-note">前台显示 {{ workbenchSummary.visible_row_count }} 条</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">历史覆盖</span>
                <strong>{{ coveragePercent }}%</strong>
                <span class="summary-note">{{ workbenchSummary.history_row_count }} 个型号已有快照</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">最新价格</span>
                <strong class="summary-date">{{ workbenchSummary.latest_record_date || '暂无快照' }}</strong>
                <span class="summary-note">累计 {{ workbenchSummary.snapshot_day_count }} 个快照日</span>
            </div>
        </div>

        <div class="workbench-toolbar">
            <el-input
                v-model="itemQuery.keyword"
                clearable
                class="workbench-search"
                placeholder="搜索型号、品牌、报价单"
                :prefix-icon="Search"
                @keyup.enter="searchManage"
                @clear="searchManage"
            />
            <el-select v-model="selectedSourceId" clearable placeholder="全部数据源" class="toolbar-select" @change="handleSourceFilterChange">
                <el-option v-for="source in sourceOptions" :key="source.id" :label="source.source_name" :value="source.id" />
            </el-select>
            <el-tree-select
                v-model="selectedCategoryId"
                clearable
                check-strictly
                node-key="id"
                :data="categoryOptions"
                :props="{ label: 'name', value: 'id', children: 'children' }"
                placeholder="全部分类"
                class="toolbar-select"
                @change="handleCategoryFilterChange"
            />
            <el-select v-model="itemQuery.brand" clearable filterable placeholder="全部品牌" class="toolbar-select" @change="handleItemSearch">
                <el-option v-for="brand in filterOptions.brands" :key="brand" :label="brand" :value="brand" />
            </el-select>
            <el-popover placement="bottom-end" trigger="click" width="300">
                <template #reference>
                    <el-button>更多筛选<el-icon class="ml-[4px]"><Filter /></el-icon></el-button>
                </template>
                <div class="advanced-filter">
                    <label>报价类型</label>
                    <el-select v-model="itemQuery.quote_type" clearable placeholder="全部" @change="handleItemSearch">
                        <el-option v-for="type in filterOptions.quote_types" :key="type" :label="type" :value="type" />
                    </el-select>
                    <label>数据形态</label>
                    <el-select v-model="itemQuery.is_image_quote" clearable placeholder="全部" @change="handleItemSearch">
                        <el-option label="结构化报价" :value="0" />
                        <el-option label="图片报价" :value="1" />
                    </el-select>
                    <label>前台状态</label>
                    <el-select v-model="itemQuery.is_show" clearable placeholder="全部" @change="handleItemSearch">
                        <el-option label="显示中" :value="1" />
                        <el-option label="已隐藏" :value="0" />
                    </el-select>
                    <el-button class="w-full" @click="resetFilters">重置全部筛选</el-button>
                </div>
            </el-popover>
            <span class="toolbar-spacer" />
            <el-button :icon="Refresh" @click="refreshManage">刷新</el-button>
            <el-button type="primary" :disabled="!selectedCategoryId" @click="openCreateItem">新增报价单</el-button>
        </div>

        <el-table
            v-loading="itemLoading"
            :data="itemTable.data"
            row-key="id"
            class="workbench-table"
            @selection-change="itemSelection = $event"
        >
            <template #empty>
                <el-empty v-if="!itemLoading" description="没有找到报价单，可以切换数据源或导入 Excel" />
            </template>
            <el-table-column type="selection" width="44" />
            <el-table-column label="报价单" min-width="300">
                <template #default="{ row }">
                    <div class="quote-item-cell">
                        <el-image v-if="resolveQuoteItemIcon(row)" class="quote-item-icon" :src="imageUrl(resolveQuoteItemIcon(row))" fit="cover" />
                        <div class="quote-item-copy">
                            <div class="name-main">{{ row.name }}</div>
                            <div class="name-sub">
                                {{ row.source_name || '未归属数据源' }}
                                <template v-if="row.category_name"> · {{ row.category_name }}</template>
                                <template v-if="row.brand"> · {{ row.brand }}</template>
                            </div>
                        </div>
                    </div>
                </template>
            </el-table-column>
            <el-table-column label="当前数据" min-width="160">
                <template #default="{ row }">
                    <div class="metric-main">{{ row.row_count || 0 }} 个型号</div>
                    <div class="name-sub">更新于 {{ formatTime(row.latest_price_at || row.update_at) }}</div>
                </template>
            </el-table-column>
            <el-table-column label="历史价格" min-width="190">
                <template #default="{ row }">
                    <div v-if="row.row_count" class="history-coverage">
                        <div class="metric-main">{{ row.history_row_count || 0 }} / {{ row.row_count }} 个型号</div>
                        <el-progress :percentage="itemCoverage(row)" :show-text="false" :stroke-width="5" />
                        <div class="name-sub">最新快照 {{ row.latest_record_date || '尚未生成' }}</div>
                    </div>
                    <span v-else class="muted">图片报价无结构化走势</span>
                </template>
            </el-table-column>
            <el-table-column label="状态" width="150">
                <template #default="{ row }">
                    <div class="status-control">
                        <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0" @change="saveItem(row, true)" />
                        <span>{{ row.is_show ? '前台显示' : '已隐藏' }}</span>
                    </div>
                    <div class="status-tags">
                        <el-tag v-if="row.is_hot" size="small" type="danger">热门</el-tag>
                        <el-tag v-if="row.follow_source" size="small" type="success">自动跟随</el-tag>
                        <el-tag v-if="row.is_image_quote" size="small" type="info">图片</el-tag>
                    </div>
                </template>
            </el-table-column>
            <el-table-column label="操作" width="190" fixed="right">
                <template #default="{ row }">
                    <el-button link type="primary" @click="openRowDrawer(row)">查看报价</el-button>
                    <el-dropdown @command="handleItemCommand($event, row)">
                        <el-button link>更多<el-icon class="ml-[2px]"><ArrowDown /></el-icon></el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="edit">编辑基础信息</el-dropdown-item>
                                <el-dropdown-item command="excel">Excel 覆盖</el-dropdown-item>
                                <el-dropdown-item command="hot">{{ row.is_hot ? '取消热门' : '设为热门' }}</el-dropdown-item>
                                <el-dropdown-item command="follow">{{ row.follow_source ? '改为人工维护' : '跟随数据源' }}</el-dropdown-item>
                                <el-dropdown-item command="delete" divided>删除报价单</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </template>
            </el-table-column>
        </el-table>

        <div class="pagination-wrap">
            <el-pagination
                v-model:current-page="itemTable.page"
                v-model:page-size="itemTable.limit"
                :total="itemTable.total"
                :page-sizes="[20, 50, 100]"
                layout="total, sizes, prev, pager, next"
                @size-change="handleItemSizeChange"
                @current-change="loadItem"
            />
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { ArrowDown, Filter, Refresh, Search } from '@element-plus/icons-vue'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const {
    summaryLoading,
    workbenchSummary,
    selectedSourceId,
    selectedCategoryId,
    sourceOptions,
    categoryOptions,
    filterOptions,
    itemQuery,
    itemLoading,
    itemTable,
    itemSelection,
    handleSourceFilterChange,
    handleCategoryFilterChange,
    handleItemSearch,
    handleItemSizeChange,
    searchManage,
    resetFilters,
    refreshManage,
    loadItem,
    openCreateItem,
    openEditDialog,
    openRowDrawer,
    prepareExcelImport,
    saveItem,
    deleteItem,
    resolveQuoteItemIcon,
    imageUrl,
    formatTime
} = useQuoteSpider()

const coveragePercent = computed(() => {
    if (!workbenchSummary.row_count) return 0
    return Math.round((Number(workbenchSummary.history_row_count || 0) / Number(workbenchSummary.row_count)) * 100)
})

const itemCoverage = (row: any) => {
    if (!Number(row.row_count)) return 0
    return Math.round((Number(row.history_row_count || 0) / Number(row.row_count)) * 100)
}

const handleItemCommand = async (command: string, row: any) => {
    if (command === 'edit') return openEditDialog('item', row)
    if (command === 'excel') return prepareExcelImport(row)
    if (command === 'delete') return deleteItem(row)
    if (command === 'hot') row.is_hot = row.is_hot ? 0 : 1
    if (command === 'follow') row.follow_source = row.follow_source ? 0 : 1
    await saveItem(row, true)
}
</script>
