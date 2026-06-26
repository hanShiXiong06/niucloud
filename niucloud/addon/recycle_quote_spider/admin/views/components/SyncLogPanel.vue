<template>
    <div>
        <div class="panel-head">
            <div class="muted">展示当前报价源最近的同步记录{{ selectedSourceName ? '：' + selectedSourceName : '（全部报价源）' }}</div>
            <el-button :loading="logLoading" @click="loadLogs">刷新</el-button>
        </div>
        <el-table v-loading="logLoading" :data="logTable.data" border size="large">
            <template #empty>
                <el-empty v-if="!logLoading" description="暂无同步记录" />
            </template>
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="sync_type" label="类型" width="100" />
            <el-table-column prop="status" label="状态" width="110">
                <template #default="{ row }">
                    <el-tag :type="row.status === 1 ? 'success' : row.status === 2 ? 'danger' : row.status === 3 ? 'warning' : 'info'">
                        {{ row.status === 1 ? '成功' : row.status === 2 ? '失败' : row.status === 3 ? '部分成功' : '同步中' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="message" label="说明" min-width="180" show-overflow-tooltip />
            <el-table-column prop="error_detail" label="失败原因" min-width="240" show-overflow-tooltip>
                <template #default="{ row }">{{ row.error_detail || '-' }}</template>
            </el-table-column>
            <el-table-column prop="total_count" label="报价项" width="90" />
            <el-table-column prop="success_count" label="详情成功" width="100" />
            <el-table-column prop="failed_count" label="详情失败" width="100" />
            <el-table-column prop="started_at" label="开始时间" width="180">
                <template #default="{ row }">{{ formatTime(row.started_at) }}</template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const { logLoading, logTable, loadLogs, formatTime, selectedSource } = useQuoteSpider()

const selectedSourceName = computed(() => selectedSource.value?.source_name || '')
</script>
