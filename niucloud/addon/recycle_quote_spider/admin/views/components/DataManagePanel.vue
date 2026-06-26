<template>
    <div>
        <!-- 报价源切换器 -->
        <div class="source-switcher">
            <span class="source-switcher-label">报价源</span>
            <span class="source-chip" :class="{ 'is-active': selectedSourceId === '' }" @click="switchSource('')">全部</span>
            <span
                v-for="item in sourceOptions"
                :key="item.id"
                class="source-chip"
                :class="{ 'is-active': selectedSourceId === item.id }"
                @click="switchSource(item.id)"
            >
                <span class="dot" :class="{ off: item.status !== 1 }" />
                {{ item.source_name }}
            </span>
            <span class="source-switcher-spacer" />
            <el-button text type="primary" :loading="syncBtnLoading" :disabled="!selectedSourceId" @click="syncCurrent">同步当前源</el-button>
        </div>

        <!-- 筛选 -->
        <el-card class="box-card !border-none mb-[10px] table-search-wrap" shadow="never">
            <el-form :inline="true" :model="itemQuery" class="filter-form">
                <el-form-item label="报价项">
                    <el-input v-model="itemQuery.keyword" clearable placeholder="型号、报价项、品牌、关键词" class="w-[240px]" @keyup.enter="searchManage" />
                </el-form-item>
                <el-form-item label="品牌">
                    <el-select v-model="itemQuery.brand" clearable filterable placeholder="全部" class="w-[140px]" @change="handleItemSearch">
                        <el-option v-for="brand in filterOptions.brands" :key="brand" :label="brand" :value="brand" />
                    </el-select>
                </el-form-item>
                <el-form-item label="分组">
                    <el-select v-model="itemQuery.tab" clearable filterable placeholder="全部" class="w-[140px]" @change="handleItemSearch">
                        <el-option v-for="tab in filterOptions.tabs" :key="tab" :label="tab" :value="tab" />
                    </el-select>
                </el-form-item>
                <template v-if="showMore">
                    <el-form-item label="报价类型">
                        <el-select v-model="itemQuery.quote_type" clearable filterable placeholder="全部" class="w-[140px]" @change="handleItemSearch">
                            <el-option v-for="type in filterOptions.quote_types" :key="type" :label="type" :value="type" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="显示">
                        <el-select v-model="itemQuery.is_show" clearable placeholder="全部" class="w-[110px]" @change="handleItemSearch">
                            <el-option label="显示" :value="1" />
                            <el-option label="隐藏" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="热门">
                        <el-select v-model="itemQuery.is_hot" clearable placeholder="全部" class="w-[110px]" @change="handleItemSearch">
                            <el-option label="热门" :value="1" />
                            <el-option label="非热门" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="跟随">
                        <el-select v-model="itemQuery.follow_source" clearable placeholder="全部" class="w-[110px]" @change="handleItemSearch">
                            <el-option label="跟随" :value="1" />
                            <el-option label="不跟随" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="类型">
                        <el-select v-model="itemQuery.is_image_quote" clearable placeholder="全部" class="w-[120px]" @change="handleItemSearch">
                            <el-option label="结构化" :value="0" />
                            <el-option label="图片报价" :value="1" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="更新">
                        <el-select v-model="itemQuery.has_update" clearable placeholder="全部" class="w-[110px]" @change="handleItemSearch">
                            <el-option label="有更新" :value="1" />
                            <el-option label="无更新" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="创建日期">
                        <el-date-picker
                            :model-value="itemDate"
                            type="date"
                            value-format="x"
                            placeholder="选择日期"
                            class="!w-[160px]"
                            :disabled-date="disableFutureDate"
                            @update:model-value="applyItemDate"
                        />
                    </el-form-item>
                    <el-form-item label="排序">
                        <el-select v-model="itemQuery.order_by" placeholder="默认" class="w-[130px]" @change="handleItemSearch">
                            <el-option label="默认排序" value="" />
                            <el-option label="浏览量↓" value="view" />
                            <el-option label="最新创建" value="new" />
                            <el-option label="最早创建" value="old" />
                        </el-select>
                    </el-form-item>
                </template>
                <el-form-item>
                    <el-button type="primary" @click="searchManage">查询</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                    <el-button link type="primary" @click="showMore = !showMore">{{ showMore ? '收起筛选' : '更多筛选' }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <div class="manage-layout">
            <!-- 分类树 -->
            <aside class="category-nav-panel panel">
                <div class="panel-actions">
                    <div class="flex gap-2">
                        <el-button type="primary" @click="openCreateCategory(0)">新增一级分类</el-button>
                        <el-button :disabled="!selectedCategoryId" @click="clearCategorySelection">全部分类</el-button>
                    </div>
                    <el-dropdown :disabled="!categorySelection.length" @command="batchCategoryCommand">
                        <el-button>批量操作<el-icon class="ml-[4px]"><ArrowDown /></el-icon></el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="show">显示</el-dropdown-item>
                                <el-dropdown-item command="hide">隐藏</el-dropdown-item>
                                <el-dropdown-item command="hot">设为热门</el-dropdown-item>
                                <el-dropdown-item command="unhot">取消热门</el-dropdown-item>
                                <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
                <el-table
                    v-loading="categoryLoading"
                    :data="categoryTable.data"
                    border
                    size="large"
                    height="640"
                    row-key="id"
                    default-expand-all
                    :tree-props="{ children: 'children' }"
                    highlight-current-row
                    @selection-change="categorySelection = $event"
                    @row-click="selectCategory"
                >
                    <template #empty>
                        <el-empty v-if="!categoryLoading" description="还没有分类，点“新增一级分类”创建" />
                    </template>
                    <el-table-column type="selection" width="40" />
                    <el-table-column prop="name" label="分类" min-width="150" show-overflow-tooltip class-name="category-name-column">
                        <template #default="{ row }">
                            <div class="category-name-text">
                                <div class="name-main">{{ row.name }}</div>
                                <div class="name-sub">层级 {{ row.level }} · 排序 {{ row.sort }}</div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="排序" width="96">
                        <template #default="{ row }">
                            <el-input-number v-model="row.sort" :min="0" :controls="false" class="sort-input" @click.stop @change="saveCategory(row, true)" />
                        </template>
                    </el-table-column>
                    <el-table-column label="控制" width="110">
                        <template #default="{ row }">
                            <div class="switch-line">
                                <span>显示</span>
                                <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0" @click.stop @change="saveCategory(row, true)" />
                            </div>
                            <div class="switch-line">
                                <span>热门</span>
                                <el-switch v-model="row.is_hot" :active-value="1" :inactive-value="0" @click.stop @change="saveCategory(row, true)" />
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{ row }">
                            <div class="category-row-actions">
                                <el-button link type="primary" @click.stop="openCreateCategory(row.id)">+ 子分类</el-button>
                                <el-button link type="primary" @click.stop="openEditDialog('category', row)">编辑</el-button>
                                <el-button link type="danger" @click.stop="deleteCategory(row)">删除</el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </aside>

            <!-- 报价项 -->
            <section class="item-main-panel panel">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">报价项</div>
                        <div class="panel-subtitle">共 {{ itemTable.total }} 个{{ selectedCategoryName ? ' · ' + selectedCategoryName : ' · 全部分类' }}</div>
                    </div>
                    <div class="flex gap-2">
                        <el-button type="primary" :disabled="!selectedCategoryId" @click="openCreateItem">
                            新增报价项
                        </el-button>
                        <el-dropdown :disabled="!itemSelection.length" @command="batchItemCommand">
                            <el-button>批量操作<el-icon class="ml-[4px]"><ArrowDown /></el-icon></el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="show">显示</el-dropdown-item>
                                    <el-dropdown-item command="hide">隐藏</el-dropdown-item>
                                    <el-dropdown-item command="hot">设为热门</el-dropdown-item>
                                    <el-dropdown-item command="unhot">取消热门</el-dropdown-item>
                                    <el-dropdown-item command="follow">跟随爬虫</el-dropdown-item>
                                    <el-dropdown-item command="unfollow">取消跟随</el-dropdown-item>
                                    <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                </div>
                <el-table
                    v-loading="itemLoading"
                    :data="itemTable.data"
                    border
                    size="large"
                    height="600"
                    row-key="id"
                    highlight-current-row
                    @selection-change="itemSelection = $event"
                    @row-click="selectItem"
                >
                    <template #empty>
                        <el-empty v-if="!itemLoading" :description="selectedCategoryId ? '该分类下还没有报价项' : '先在左侧选择一个分类，或新增报价项'" />
                    </template>
                    <el-table-column type="selection" width="40" />
                    <el-table-column prop="name" label="报价项" min-width="220" show-overflow-tooltip>
                        <template #default="{ row }">
                            <div class="quote-item-cell">
                                <el-image v-if="resolveQuoteItemIcon(row)" class="quote-item-icon" :src="imageUrl(resolveQuoteItemIcon(row))" fit="cover" />
                                <div class="quote-item-copy">
                                    <div class="name-main">{{ row.name }}</div>
                                    <div class="name-sub">{{ row.brand || '-' }} · {{ row.tab || '-' }} · {{ row.parent_name || '-' }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="排序" width="96">
                        <template #default="{ row }">
                            <el-input-number v-model="row.sort" :min="0" :controls="false" class="sort-input" @click.stop @change="saveItem(row, true)" />
                        </template>
                    </el-table-column>
                    <el-table-column label="控制" width="140">
                        <template #default="{ row }">
                            <div class="switch-line">
                                <span>显示</span>
                                <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0" @click.stop @change="saveItem(row, true)" />
                            </div>
                            <div class="switch-line">
                                <span>热门</span>
                                <el-switch v-model="row.is_hot" :active-value="1" :inactive-value="0" @click.stop @change="saveItem(row, true)" />
                            </div>
                            <div class="switch-line">
                                <span>跟随</span>
                                <el-switch v-model="row.follow_source" :active-value="1" :inactive-value="0" @click.stop @change="saveItem(row, true)" />
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="类型" width="76">
                        <template #default="{ row }">
                            <el-tag :type="row.is_image_quote ? 'info' : 'success'">{{ row.is_image_quote ? '图片' : '结构' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="浏览量" width="90" align="right">
                        <template #default="{ row }">
                            <span class="view-count">{{ row.view_count ?? 0 }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="240" fixed="right">
                        <template #default="{ row }">
                            <el-button link type="primary" @click.stop="openRowDrawer(row)">价格管理</el-button>
                            <el-button link type="primary" @click.stop="openEditDialog('item', row)">编辑</el-button>
                            <el-button link type="warning" @click.stop="prepareExcelImport(row)">Excel覆盖</el-button>
                            <el-button link type="danger" @click.stop="deleteItem(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-pagination
                    class="pager"
                    v-model:current-page="itemTable.page"
                    v-model:page-size="itemTable.limit"
                    :total="itemTable.total"
                    :page-sizes="[20, 50, 100]"
                    layout="total, sizes, prev, pager, next"
                    @size-change="handleItemSizeChange"
                    @current-change="loadItem"
                />
            </section>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import { ArrowDown } from '@element-plus/icons-vue'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const showMore = ref(false)
const syncBtnLoading = ref(false)

const {
    selectedSourceId,
    selectedCategoryId,
    selectedCategoryName,
    sourceOptions,
    itemQuery,
    itemDate,
    applyItemDate,
    disableFutureDate,
    filterOptions,
    categoryLoading,
    categoryTable,
    categorySelection,
    itemLoading,
    itemTable,
    itemSelection,
    switchSource,
    searchManage,
    resetFilters,
    handleItemSearch,
    handleItemSizeChange,
    openCreateCategory,
    openCreateItem,
    openEditDialog,
    clearCategorySelection,
    selectCategory,
    selectItem,
    openRowDrawer,
    prepareExcelImport,
    saveCategory,
    saveItem,
    deleteCategory,
    deleteItem,
    batchCategoryCommand,
    batchItemCommand,
    loadItem,
    resolveQuoteItemIcon,
    imageUrl,
    syncSource,
    selectedSource
} = useQuoteSpider()

const syncCurrent = async () => {
    if (!selectedSource.value) return
    syncBtnLoading.value = true
    try {
        await syncSource(selectedSource.value)
    } finally {
        syncBtnLoading.value = false
    }
}
</script>
