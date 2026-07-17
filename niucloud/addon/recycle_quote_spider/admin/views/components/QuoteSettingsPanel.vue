<template>
    <div class="quote-settings">
        <div class="section-switcher">
            <el-radio-group v-model="settingsMode">
                <el-radio-button value="source">数据源</el-radio-button>
                <el-radio-button value="category">报价分类</el-radio-button>
            </el-radio-group>
            <span class="toolbar-spacer" />
            <template v-if="settingsMode === 'source'">
                <el-button @click="createDefaultSource">创建默认源</el-button>
                <el-button type="primary" @click="openSourceDialog()">新增数据源</el-button>
            </template>
            <el-button v-else type="primary" @click="openCreateCategory(0)">新增一级分类</el-button>
        </div>

        <SourceListPanel v-if="settingsMode === 'source'" />
        <template v-else>
            <div class="settings-filter">
                <el-select v-model="selectedSourceId" clearable placeholder="全部数据源" class="toolbar-select" @change="handleSourceFilterChange">
                    <el-option v-for="source in sourceOptions" :key="source.id" :label="source.source_name" :value="source.id" />
                </el-select>
                <el-input v-model="categoryQuery.keyword" clearable placeholder="搜索分类" class="w-[240px]" @keyup.enter="handleCategorySearch" />
                <el-button @click="handleCategorySearch">查询</el-button>
            </div>
            <el-table
                v-loading="categoryLoading"
                :data="categoryTable.data"
                row-key="id"
                default-expand-all
                :tree-props="{ children: 'children' }"
                class="settings-table"
                @selection-change="categorySelection = $event"
            >
                <template #empty><el-empty description="还没有报价分类" /></template>
                <el-table-column type="selection" width="44" />
                <el-table-column label="分类" min-width="300">
                    <template #default="{ row }">
                        <div class="name-main">{{ row.name }}</div>
                        <div class="name-sub">第 {{ row.level }} 级分类</div>
                    </template>
                </el-table-column>
                <el-table-column label="排序" width="120">
                    <template #default="{ row }">
                        <el-input-number v-model="row.sort" :min="0" :controls="false" class="sort-input" @change="saveCategory(row, true)" />
                    </template>
                </el-table-column>
                <el-table-column label="前台显示" width="130">
                    <template #default="{ row }"><el-switch v-model="row.is_show" :active-value="1" :inactive-value="0" @change="saveCategory(row, true)" /></template>
                </el-table-column>
                <el-table-column label="热门" width="100">
                    <template #default="{ row }"><el-switch v-model="row.is_hot" :active-value="1" :inactive-value="0" @change="saveCategory(row, true)" /></template>
                </el-table-column>
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="openCreateCategory(row.id)">新增子分类</el-button>
                        <el-button link type="primary" @click="openEditDialog('category', row)">编辑</el-button>
                        <el-button link type="danger" @click="deleteCategory(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </template>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'
import SourceListPanel from './SourceListPanel.vue'

const settingsMode = ref<'source' | 'category'>('source')
const {
    selectedSourceId,
    sourceOptions,
    categoryQuery,
    categoryLoading,
    categoryTable,
    categorySelection,
    createDefaultSource,
    openSourceDialog,
    openCreateCategory,
    openEditDialog,
    deleteCategory,
    saveCategory,
    handleSourceFilterChange,
    handleCategorySearch
} = useQuoteSpider()
</script>
