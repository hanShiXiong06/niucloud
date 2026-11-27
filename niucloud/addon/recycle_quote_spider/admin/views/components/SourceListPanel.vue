<template>
    <div>
        <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
            <el-form :inline="true" :model="sourceQuery">
                <el-form-item label="报价源">
                    <el-input v-model="sourceQuery.keyword" clearable placeholder="名称、标识、服务商" @keyup.enter="loadSources(1)" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="sourceQuery.status" clearable placeholder="全部" class="w-[120px]">
                        <el-option label="启用" :value="1" />
                        <el-option label="停用" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadSources(1)">查询</el-button>
                    <el-button @click="resetSourceSearch">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>
        <el-table
            v-loading="sourceLoading"
            :data="sourceTable.data"
            border
            size="large"
            row-key="id"
            highlight-current-row
            class="mt-[10px]"
        >
            <template #empty>
                <el-empty v-if="!sourceLoading" description="还没有报价源，点右上角“新增报价源”或“创建默认源”开始" />
            </template>
            <el-table-column prop="source_name" label="报价源" min-width="180">
                <template #default="{ row }">
                    <div class="strong-text">{{ row.source_name }}</div>
                    <div class="muted">{{ row.source_key || '-' }}</div>
                </template>
            </el-table-column>
            <el-table-column prop="provider" label="服务商" min-width="120">
                <template #default="{ row }">{{ row.provider || '-' }}</template>
            </el-table-column>
            <el-table-column prop="base_url" label="基础地址" min-width="260" show-overflow-tooltip />
            <el-table-column label="状态" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="自动同步" min-width="180">
                <template #default="{ row }">
                    <div class="auto-sync-cell">
                        <el-tag :type="row.sync_enabled === 1 ? 'success' : 'info'">{{ row.sync_enabled === 1 ? '开启' : '关闭' }}</el-tag>
                        <span class="muted">{{ sourceSyncText(row) }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column label="最近同步" min-width="180">
                <template #default="{ row }">{{ formatTime(row.last_sync_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" fixed="right" width="260">
                <template #default="{ row }">
                    <el-button link type="primary" @click="openSourceData(row)">管理数据</el-button>
                    <el-button link type="primary" @click="openSourceDialog(row)">编辑</el-button>
                    <el-button link type="warning" :loading="syncingId === row.id" @click="syncSource(row)">同步</el-button>
                </template>
            </el-table-column>
        </el-table>
        <div class="pagination-wrap">
            <el-pagination
                v-model:current-page="sourceTable.page"
                v-model:page-size="sourceTable.limit"
                :total="sourceTable.total"
                :page-sizes="[10, 20, 50]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="handleSourceSizeChange"
                @current-change="loadSources"
            />
        </div>
    </div>
</template>

<script lang="ts" setup>
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const {
    sourceLoading,
    sourceTable,
    sourceQuery,
    syncingId,
    loadSources,
    resetSourceSearch,
    handleSourceSizeChange,
    openSourceData,
    openSourceDialog,
    syncSource,
    sourceSyncText,
    formatTime
} = useQuoteSpider()
</script>
