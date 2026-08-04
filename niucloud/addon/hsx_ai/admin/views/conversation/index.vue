<template>
    <div class="main-container conversation-page">
        <div class="page-head">
            <div><h1 class="text-page-title">用户会话</h1><p>查看用户咨询、模型回复和关联商品，业务事实仍由对应插件负责。</p></div>
            <el-button :icon="Refresh" circle title="刷新" @click="load" />
        </div>

        <div class="filter-bar">
            <el-input v-model.trim="query.keyword" clearable placeholder="搜索会员、手机号或会话内容" class="keyword" @keyup.enter="search" />
            <el-select v-model="query.status" class="status" @change="search"><el-option label="全部状态" value="" /><el-option label="进行中" value="active" /><el-option label="已归档" value="archived" /><el-option label="已拦截" value="blocked" /></el-select>
            <el-button type="primary" @click="search">查询</el-button><el-button @click="reset">重置</el-button>
        </div>

        <el-table v-loading="loading" :data="rows" row-key="id" @row-click="openDetail">
            <el-table-column label="用户" min-width="180"><template #default="{ row }"><div class="member-cell"><el-avatar :size="34" :src="row.headimg" /><div><strong>{{ row.nickname || `会员 ${row.actor_id}` }}</strong><span>{{ row.mobile || '未留手机号' }}</span></div></div></template></el-table-column>
            <el-table-column label="最近咨询" min-width="280"><template #default="{ row }"><div class="conversation-title">{{ row.title || '新的咨询' }}</div><div class="secondary">{{ sceneName(row.scene_key) }} · {{ row.message_count }} 条消息</div></template></el-table-column>
            <el-table-column label="最近意图" min-width="150"><template #default="{ row }">{{ row.last_intent || '待识别' }}</template></el-table-column>
            <el-table-column label="风险" width="90" align="center"><template #default="{ row }"><el-tag :type="row.risk_level ? 'danger' : 'success'" effect="plain">{{ row.risk_level ? `L${row.risk_level}` : '正常' }}</el-tag></template></el-table-column>
            <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag :type="statusType(row.status)" effect="plain">{{ statusName(row.status) }}</el-tag></template></el-table-column>
            <el-table-column label="最后消息" width="180"><template #default="{ row }">{{ formatTime(row.last_message_at) }}</template></el-table-column>
            <el-table-column label="操作" width="90" align="right"><template #default="{ row }"><el-button type="primary" link @click.stop="openDetail(row)">查看</el-button></template></el-table-column>
        </el-table>
        <div class="pagination"><el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" layout="total, prev, pager, next" :total="total" @current-change="load" /></div>

        <el-drawer v-model="detailVisible" size="min(820px, 94vw)" destroy-on-close>
            <template #header><div class="drawer-head"><div><strong>{{ detail.conversation?.title || '会话详情' }}</strong><span>{{ detail.messages.length }} 条消息 · {{ formatTime(detail.conversation?.last_message_at) }}</span></div><el-tag effect="plain">{{ sceneName(detail.conversation?.scene_key) }}</el-tag></div></template>
            <div v-loading="detailLoading" class="message-list">
                <div v-if="detail.demands?.length" class="demand-summary">
                    <div><span>识别意图</span><strong>{{ demandName(detail.demands[0].intent) }}</strong></div>
                    <div><span>预算</span><strong>{{ budgetText(detail.demands[0]) }}</strong></div>
                    <div><span>线索评分</span><strong>{{ Number(detail.demands[0].lead_score || 0).toFixed(0) }}</strong></div>
                    <div class="demand-entities"><span>已识别条件</span><p>{{ [...(detail.demands[0].entities_json || []), ...(detail.demands[0].requirements_json || [])].join('、') || '待补充' }}</p></div>
                </div>
                <div v-for="message in detail.messages" :key="message.id" class="message-row" :class="message.role">
                    <div class="message-meta"><span>{{ message.role === 'user' ? '用户' : 'AI' }}</span><time>{{ formatTime(message.create_at) }}</time></div>
                    <div class="message-content">{{ message.content || (message.status === 'failed' ? message.error_message : '无文本内容') }}</div>
                    <div v-if="message.resources?.length" class="resource-list"><span v-for="item in message.resources" :key="`${message.id}_${item.goods_id}`">{{ item.name }} · ¥{{ Number(item.price || 0).toFixed(0) }}</span></div>
                    <div v-if="message.role === 'assistant'" class="model-meta">{{ message.provider_id || '系统' }} · {{ message.model || '规则回复' }} · {{ message.total_tokens || 0 }} tokens · {{ message.latency_ms || 0 }}ms</div>
                </div>
                <el-empty v-if="!detailLoading && !detail.messages.length" description="该会话暂无消息" :image-size="70" />
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { getAiConversation, getAiConversations } from '../../api'

const query = reactive({ keyword: '', status: '', page: 1, limit: 15 })
const rows = ref<any[]>([]), total = ref(0), loading = ref(false)
const detailVisible = ref(false), detailLoading = ref(false)
const detail = reactive<any>({ conversation: null, messages: [], demands: [] })
const load = async () => { loading.value = true; try { const data: any = (await getAiConversations(query)).data || {}; rows.value = data.data || []; total.value = Number(data.total || 0) } finally { loading.value = false } }
const search = () => { query.page = 1; load() }
const reset = () => { Object.assign(query, { keyword: '', status: '', page: 1, limit: 15 }); load() }
const openDetail = async (row: any) => { detailVisible.value = true; detailLoading.value = true; try { Object.assign(detail, (await getAiConversation(Number(row.id))).data || { conversation: row, messages: [], demands: [] }) } finally { detailLoading.value = false } }
const formatTime = (value: any) => Number(value || 0) ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
const sceneName = (value: string) => value === 'phone_shop.customer_assistant' ? '商城选机助手' : (value || '通用场景')
const demandName = (value: string) => ({ purchase_consultation: '购买咨询', product_consultation: '商品咨询' }[value] || value || '待识别')
const budgetText = (row: any) => {
    const min = Number(row?.budget_min || 0)
    const max = Number(row?.budget_max || 0)
    if (min > 0 && max > 0) return `¥${min.toFixed(0)} - ¥${max.toFixed(0)}`
    if (max > 0) return `≤ ¥${max.toFixed(0)}`
    if (min > 0) return `≥ ¥${min.toFixed(0)}`
    return '待补充'
}
const statusName = (value: string) => ({ active: '进行中', archived: '已归档', blocked: '已拦截' }[value] || value)
const statusType = (value: string) => ({ active: 'success', archived: 'info', blocked: 'danger' }[value] || 'info') as any
onMounted(load)
</script>

<style scoped lang="scss">
.conversation-page { min-height: 100%; }.page-head, .filter-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }.page-head { align-items: flex-start; margin-bottom: 20px; }.page-head p { margin: 6px 0 0; color: var(--el-text-color-secondary); }.filter-bar { justify-content: flex-start; margin-bottom: 14px; padding: 14px 16px; border-radius: 4px; background: var(--el-fill-color-lighter); }.keyword { width: 320px; }.status { width: 140px; }.pagination { display: flex; justify-content: flex-end; margin-top: 16px; }
.member-cell { display: flex; align-items: center; gap: 10px; }.member-cell > div { display: flex; min-width: 0; flex-direction: column; }.member-cell strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.member-cell span, .secondary { margin-top: 3px; color: var(--el-text-color-secondary); font-size: 12px; }.conversation-title { overflow: hidden; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.drawer-head { display: flex; width: 100%; align-items: center; justify-content: space-between; padding-right: 20px; }.drawer-head > div { display: flex; min-width: 0; flex-direction: column; }.drawer-head strong { font-size: 17px; }.drawer-head span { margin-top: 5px; color: var(--el-text-color-secondary); font-size: 12px; }.message-list { padding: 0 4px 28px; }.message-row { max-width: 88%; margin: 0 0 20px; }.message-row.user { margin-left: auto; }.message-meta { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 6px; color: var(--el-text-color-secondary); font-size: 12px; }.message-content { padding: 14px 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 6px; background: var(--el-bg-color); line-height: 1.7; white-space: pre-wrap; word-break: break-word; }.message-row.user .message-content { border-color: var(--el-color-primary-light-7); background: var(--el-color-primary-light-9); }.resource-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }.resource-list span { padding: 5px 8px; border-radius: 4px; background: var(--el-fill-color-lighter); color: var(--el-text-color-regular); font-size: 12px; }.model-meta { margin-top: 7px; color: var(--el-text-color-placeholder); font-size: 11px; }
.demand-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 24px; padding: 14px 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 6px; background: var(--el-fill-color-lighter); }.demand-summary > div { display: flex; flex-direction: column; gap: 5px; }.demand-summary span { color: var(--el-text-color-secondary); font-size: 12px; }.demand-summary strong { font-size: 15px; }.demand-entities { grid-column: 1 / -1; }.demand-entities p { margin: 0; color: var(--el-text-color-regular); line-height: 1.6; }
@media (max-width: 760px) { .page-head, .filter-bar { align-items: stretch; flex-direction: column; }.keyword, .status { width: 100%; }.message-row { max-width: 96%; } }
</style>
