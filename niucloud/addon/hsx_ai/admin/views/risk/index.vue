<template>
    <div class="main-container risk-page">
        <div class="page-head"><div><h1 class="text-page-title">风险事件</h1><p>AI 能力采用渐进式限制，单次可疑请求不会直接锁定会员账号。</p></div><el-button :icon="Refresh" circle title="刷新" @click="load" /></div>
        <div class="filter-bar">
            <el-input v-model.trim="query.keyword" clearable placeholder="搜索会员或风险类型" class="keyword" @keyup.enter="search" />
            <el-select v-model="query.risk_level" class="level" @change="search"><el-option label="全部等级" :value="0" /><el-option label="L1 及以上" :value="1" /><el-option label="L2 及以上" :value="2" /></el-select>
            <el-button type="primary" @click="search">查询</el-button><el-button @click="reset">重置</el-button>
        </div>
        <el-table v-loading="loading" :data="rows" row-key="id">
            <el-table-column label="主体" min-width="180"><template #default="{ row }"><div class="member"><strong>{{ row.nickname || (row.actor_type === 'guest' ? '访客' : `会员 ${row.actor_id}`) }}</strong><span>{{ row.mobile || row.actor_type }}</span></div></template></el-table-column>
            <el-table-column label="风险" min-width="190"><template #default="{ row }"><div class="risk-type">{{ riskName(row.risk_type) }}</div><div class="secondary">评分 {{ Number(row.risk_score || 0).toFixed(0) }}</div></template></el-table-column>
            <el-table-column label="等级" width="90"><template #default="{ row }"><el-tag :type="row.risk_level >= 2 ? 'danger' : 'warning'" effect="plain">L{{ row.risk_level }}</el-tag></template></el-table-column>
            <el-table-column label="处置" min-width="150"><template #default="{ row }"><span>{{ actionName(row.action) }}</span><div v-if="row.frozen_until" class="secondary">至 {{ formatTime(row.frozen_until) }}</div></template></el-table-column>
            <el-table-column label="证据" min-width="240"><template #default="{ row }"><code>{{ evidence(row) }}</code></template></el-table-column>
            <el-table-column label="时间" width="180"><template #default="{ row }">{{ formatTime(row.create_at) }}</template></el-table-column>
        </el-table>
        <div class="pagination"><el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" layout="total, prev, pager, next" :total="total" @current-change="load" /></div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { getAiRisks } from '../../api'
const query = reactive({ keyword: '', risk_level: 0, page: 1, limit: 15 })
const rows = ref<any[]>([]), total = ref(0), loading = ref(false)
const load = async () => { loading.value = true; try { const data: any = (await getAiRisks(query)).data || {}; rows.value = data.data || []; total.value = Number(data.total || 0) } finally { loading.value = false } }
const search = () => { query.page = 1; load() }
const reset = () => { Object.assign(query, { keyword: '', risk_level: 0, page: 1, limit: 15 }); load() }
const formatTime = (value: any) => Number(value || 0) ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
const riskName = (value: string) => ({ rate_limit: '高频访问', override_instruction: '覆盖系统指令', system_prompt_probe: '探测系统提示', credential_probe: '探测敏感凭据', role_escalation: '尝试绕过权限' }[value] || value)
const actionName = (value: string) => ({ reject: '拒绝本次请求', freeze_ai: '临时冻结 AI' }[value] || value)
const evidence = (row: any) => { const data = row.evidence_json || {}; return data.rule || (data.minute_count ? `${data.minute_count} 次/分钟` : data.prompt_hash?.slice(0, 16) || '规则命中') }
onMounted(load)
</script>

<style scoped lang="scss">
.page-head, .filter-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }.page-head { align-items: flex-start; margin-bottom: 20px; }.page-head p { margin: 6px 0 0; color: var(--el-text-color-secondary); }.filter-bar { justify-content: flex-start; margin-bottom: 14px; padding: 14px 16px; border-radius: 4px; background: var(--el-fill-color-lighter); }.keyword { width: 300px; }.level { width: 150px; }.pagination { display: flex; justify-content: flex-end; margin-top: 16px; }.member { display: flex; flex-direction: column; }.member span, .secondary { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; }.risk-type { font-weight: 600; }code { color: var(--el-text-color-regular); font-size: 12px; word-break: break-all; }
@media (max-width: 700px) { .page-head, .filter-bar { align-items: stretch; flex-direction: column; }.keyword, .level { width: 100%; } }
</style>
