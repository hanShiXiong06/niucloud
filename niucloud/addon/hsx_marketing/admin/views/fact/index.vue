<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="page-head"><div><div class="text-page-title">事实台账</div><p>记录各业务插件送达的原始事实、冲红与处理结果，异常可直接定位和重试。</p></div><el-button @click="load">刷新</el-button></div>
            <div class="toolbar">
                <el-input v-model="query.keyword" clearable placeholder="来源单号、事件编号或事实类型" class="search" @keyup.enter="load" />
                <el-select v-model="query.process_status" clearable placeholder="全部处理状态" class="status-select" @change="load"><el-option v-for="item in statuses" :key="item.value" :label="item.label" :value="item.value" /></el-select>
                <el-button type="primary" @click="load">查询</el-button>
            </div>
            <el-table :data="rows" v-loading="loading" row-key="id">
                <el-table-column label="业务事实" min-width="230"><template #default="{ row }"><div class="primary">{{ factName(row.fact_key) }}</div><div class="secondary">{{ row.business_no || row.event_id }}</div></template></el-table-column>
                <el-table-column label="来源" width="130"><template #default="{ row }">{{ row.source_plugin }}<div class="secondary">{{ row.fact_type === 'reversal' ? '冲红' : '原始事实' }}</div></template></el-table-column>
                <el-table-column label="会员" width="100" prop="member_id" />
                <el-table-column label="数量" width="100"><template #default="{ row }"><span :class="row.quantity < 0 ? 'danger' : ''">{{ Number(row.quantity) }}</span></template></el-table-column>
                <el-table-column label="发生时间" width="180"><template #default="{ row }">{{ datetime(row.occurred_at) }}</template></el-table-column>
                <el-table-column label="处理状态" width="120"><template #default="{ row }"><el-tag :type="statusType(row.process_status)" effect="plain">{{ row.process_status_name }}</el-tag></template></el-table-column>
                <el-table-column label="异常信息" min-width="240" show-overflow-tooltip><template #default="{ row }">{{ row.error_message || '—' }}</template></el-table-column>
                <el-table-column label="操作" width="100" fixed="right" align="right"><template #default="{ row }"><el-button v-if="row.process_status !== 'success'" link type="primary" @click="retry(row)">重试</el-button></template></el-table-column>
            </el-table>
            <div class="pagination"><el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" layout="total,prev,pager,next" :total="total" @current-change="load" /></div>
        </el-card>
    </div>
</template>
<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getMarketingFacts, retryMarketingFact } from '../../api'
const query = reactive({ keyword: '', process_status: '', page: 1, limit: 15 })
const rows = ref<any[]>([]), total = ref(0), loading = ref(false)
const statuses = [{ label: '待处理', value: 'pending' }, { label: '处理中', value: 'processing' }, { label: '已处理', value: 'success' }, { label: '处理失败', value: 'failed' }]
const load = async () => { loading.value = true; try { const data: any = (await getMarketingFacts(query)).data || {}; rows.value = data.data || []; total.value = Number(data.total || 0) } finally { loading.value = false } }
const retry = async (row: any) => { await retryMarketingFact(Number(row.id)); ElMessage.success('事实已重新处理'); load() }
const factName = (key: string) => ({ recycle_device_delivered: '回收设备完成交货' } as any)[key] || key
const statusType = (value: string) => ({ success: 'success', failed: 'danger', pending: 'warning', processing: 'primary' } as any)[value] || 'info'
const datetime = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
onMounted(load)
</script>
<style scoped lang="scss">
.page-head,.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px}.page-head{align-items:flex-start;margin-bottom:18px}.page-head p{margin:6px 0 0;color:var(--el-text-color-secondary)}.toolbar{justify-content:flex-start;margin-bottom:14px}.search{width:320px}.status-select{width:160px}.primary{font-weight:600}.secondary{margin-top:4px;color:var(--el-text-color-secondary);font-size:12px}.danger{color:var(--el-color-danger)}.pagination{display:flex;justify-content:flex-end;margin-top:16px}
</style>
