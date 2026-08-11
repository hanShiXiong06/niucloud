<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="page-head"><div><div class="text-page-title">奖励台账</div><p>每次领取、发放、失败重试和冲红均可查询，不再依赖业务插件日志猜结果。</p></div><el-button @click="load">刷新</el-button></div>
            <div class="toolbar">
                <el-input v-model="query.keyword" clearable placeholder="奖励单号或奖励名称" class="search" @keyup.enter="load" />
                <el-select v-model="query.status" clearable placeholder="全部状态" class="status-select" @change="load">
                    <el-option v-for="item in statuses" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
                <el-button type="primary" @click="load">查询</el-button>
            </div>
            <el-table :data="rows" v-loading="loading" row-key="id">
                <el-table-column label="奖励单" min-width="210"><template #default="{ row }"><div class="primary">{{ row.reward_no }}</div><div class="secondary">{{ row.campaign_title || '营销活动' }}</div></template></el-table-column>
                <el-table-column label="会员" min-width="150"><template #default="{ row }"><div>{{ row.member_name || `会员 ${row.member_id}` }}</div><div class="secondary">{{ row.member_mobile || `ID ${row.member_id}` }}</div></template></el-table-column>
                <el-table-column label="奖励内容" min-width="190"><template #default="{ row }"><div>{{ row.reward_name }}</div><div class="secondary">{{ content(row) }}</div></template></el-table-column>
                <el-table-column label="发放方式" width="110"><template #default="{ row }">{{ row.grant_mode === 'auto' ? '自动发放' : '用户领取' }}</template></el-table-column>
                <el-table-column label="状态" width="120"><template #default="{ row }"><el-tag :type="statusType(row.status)" effect="plain">{{ statusText(row.status) }}</el-tag></template></el-table-column>
                <el-table-column label="失败原因" min-width="220" show-overflow-tooltip><template #default="{ row }">{{ row.failure_reason || '—' }}</template></el-table-column>
                <el-table-column label="创建时间" width="180"><template #default="{ row }">{{ datetime(row.create_at) }}</template></el-table-column>
                <el-table-column label="操作" width="100" fixed="right" align="right"><template #default="{ row }"><el-button v-if="row.status === 'failed'" link type="primary" @click="retry(row)">重试</el-button></template></el-table-column>
            </el-table>
            <div class="pagination"><el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" layout="total,prev,pager,next" :total="total" @current-change="load" /></div>
        </el-card>
    </div>
</template>
<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getMarketingRewards, retryMarketingReward } from '../../api'
const query = reactive({ keyword: '', status: '', page: 1, limit: 15 })
const rows = ref<any[]>([]), total = ref(0), loading = ref(false)
const statuses = [{ label: '待领取', value: 'claimable' }, { label: '待发放', value: 'pending' }, { label: '发放中', value: 'processing' }, { label: '已到账', value: 'success' }, { label: '失败', value: 'failed' }, { label: '已失效', value: 'expired' }, { label: '已冲红', value: 'cancelled' }]
const load = async () => { loading.value = true; try { const data: any = (await getMarketingRewards(query)).data || {}; rows.value = data.data || []; total.value = Number(data.total || 0) } finally { loading.value = false } }
const retry = async (row: any) => { await retryMarketingReward(Number(row.id)); ElMessage.success('已提交重新发放'); load() }
const content = (row: any) => ['point', 'growth'].includes(row.reward_type) ? `${Number(row.reward_value || 0) * Number(row.reward_quantity || 1)}` : `数量 × ${row.reward_quantity}`
const statusText = (value: string) => statuses.find(item => item.value === value)?.label || value
const statusType = (value: string) => ({ success: 'success', failed: 'danger', expired: 'info', cancelled: 'info', claimable: 'warning', pending: 'warning', processing: 'primary' } as any)[value] || 'info'
const datetime = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
onMounted(load)
</script>
<style scoped lang="scss">
.page-head,.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px}.page-head{align-items:flex-start;margin-bottom:18px}.page-head p{margin:6px 0 0;color:var(--el-text-color-secondary)}.toolbar{justify-content:flex-start;margin-bottom:14px}.search{width:260px}.status-select{width:150px}.primary{font-weight:600}.secondary{margin-top:4px;color:var(--el-text-color-secondary);font-size:12px}.pagination{display:flex;justify-content:flex-end;margin-top:16px}
</style>
