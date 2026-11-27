<template>
    <div class="operation-records">
        <div class="section-switcher">
            <el-radio-group v-model="recordMode" @change="loadCurrent">
                <el-radio-button value="sync">同步记录</el-radio-button>
                <el-radio-button value="import">Excel 导入记录</el-radio-button>
            </el-radio-group>
            <el-select v-model="selectedSourceId" clearable placeholder="全部数据源" class="toolbar-select" @change="loadCurrent">
                <el-option v-for="source in sourceOptions" :key="source.id" :label="source.source_name" :value="source.id" />
            </el-select>
            <span class="toolbar-spacer" />
            <el-button :loading="currentLoading" :icon="Refresh" @click="loadCurrent">刷新</el-button>
        </div>

        <el-table v-if="recordMode === 'sync'" v-loading="logLoading" :data="logTable.data" class="records-table">
            <template #empty><el-empty description="暂无同步记录" /></template>
            <el-table-column label="执行方式" width="120">
                <template #default="{ row }">{{ syncTypeText(row.sync_type) }}</template>
            </el-table-column>
            <el-table-column label="结果" width="110">
                <template #default="{ row }"><el-tag :type="statusType(row.status)">{{ syncStatusText(row.status) }}</el-tag></template>
            </el-table-column>
            <el-table-column label="处理数据" min-width="220">
                <template #default="{ row }">
                    <div class="metric-main">报价单 {{ row.total_count || 0 }} 个</div>
                    <div class="name-sub">详情成功 {{ row.success_count || 0 }}，失败 {{ row.failed_count || 0 }}</div>
                </template>
            </el-table-column>
            <el-table-column label="说明" min-width="300" show-overflow-tooltip>
                <template #default="{ row }">{{ row.error_detail || row.message || '-' }}</template>
            </el-table-column>
            <el-table-column label="执行时间" width="180">
                <template #default="{ row }">{{ formatTime(row.started_at) }}</template>
            </el-table-column>
        </el-table>

        <el-table v-else v-loading="importLogLoading" :data="importLogTable.data" class="records-table">
            <template #empty><el-empty description="暂无 Excel 导入记录" /></template>
            <el-table-column label="文件" min-width="260">
                <template #default="{ row }">
                    <div class="metric-main">{{ row.file_name || '未命名文件' }}</div>
                    <div class="name-sub">{{ row.sheet_count || 0 }} 个工作表</div>
                </template>
            </el-table-column>
            <el-table-column label="结果" width="110">
                <template #default="{ row }"><el-tag :type="importStatusType(row.status)">{{ importStatusText(row.status) }}</el-tag></template>
            </el-table-column>
            <el-table-column label="导入数据" min-width="180">
                <template #default="{ row }">
                    <div class="metric-main">{{ row.row_count || 0 }} 行</div>
                    <div class="name-sub">错误 {{ row.error_count || 0 }} 行</div>
                </template>
            </el-table-column>
            <el-table-column prop="message" label="说明" min-width="280" show-overflow-tooltip>
                <template #default="{ row }">{{ row.message || '-' }}</template>
            </el-table-column>
            <el-table-column label="创建时间" width="180">
                <template #default="{ row }">{{ formatTime(row.create_at) }}</template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const recordMode = ref<'sync' | 'import'>('sync')
const {
    selectedSourceId,
    sourceOptions,
    logLoading,
    importLogLoading,
    logTable,
    importLogTable,
    loadLogs,
    loadImportLogs,
    formatTime
} = useQuoteSpider()

const currentLoading = computed(() => (recordMode.value === 'sync' ? logLoading.value : importLogLoading.value))
const loadCurrent = () => (recordMode.value === 'sync' ? loadLogs() : loadImportLogs())
const syncTypeText = (value: string) => ({ manual: '手动同步', auto: '自动同步', queue: '队列同步' }[value] || '系统同步')
const syncStatusText = (value: number) => ({ 0: '执行中', 1: '成功', 2: '失败', 3: '部分成功' }[Number(value)] || '未知')
const statusType = (value: number) => ({ 1: 'success', 2: 'danger', 3: 'warning' }[Number(value)] || 'info')
const importStatusText = (value: number) => ({ 0: '待确认', 1: '已导入', 2: '失败' }[Number(value)] || '未知')
const importStatusType = (value: number) => ({ 1: 'success', 2: 'danger' }[Number(value)] || 'info')
</script>
