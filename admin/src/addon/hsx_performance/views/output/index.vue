<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="page-head">
                <div>
                    <div class="text-page-title">员工产出</div>
                    <p>每个数字都来自业务事实，可下钻、可冲红、可重建。</p>
                </div>
                <el-dropdown @command="handleTool">
                    <el-button :icon="Tools">数据工具<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="reconcile">运行数据对账</el-dropdown-item>
                            <el-dropdown-item command="rebuild">重建当前周期汇总</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>

            <div class="filters">
                <el-segmented v-model="query.period" :options="periodOptions" @click="refreshPeriod" />
                <el-select v-model="query.source_plugin" clearable placeholder="全部业务" @change="changePlugin">
                    <el-option v-for="item in filters.plugins" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
                <el-select v-model="query.metric_key" clearable filterable placeholder="全部事项" @change="refreshAll">
                    <el-option v-for="item in metricOptions" :key="`${item.source_plugin}:${item.metric_key}`" :label="item.metric_name" :value="item.metric_key" />
                </el-select>
                <el-select v-model="query.fact_scope" clearable placeholder="全部口径" @change="refreshAll">
                    <el-option label="工作动作" value="action" /><el-option label="有效结果" value="outcome" /><el-option label="质量事项" value="quality" />
                </el-select>
                <el-button :icon="Refresh" circle title="刷新" @click="refreshAll" />
            </div>

            <div class="summary-grid" v-loading="loading">
                <div><span>有产出员工</span><strong>{{ overview.summary.employee_count || 0 }}</strong><small>人</small></div>
                <div><span>有效完成事项</span><strong>{{ overview.summary.completed_count || 0 }}</strong><small>正向 {{ overview.summary.original_count || 0 }} · 冲红 {{ overview.summary.reversal_count || 0 }}</small></div>
                <div><span>业务金额</span><strong>¥{{ money(overview.summary.amount) }}</strong><small>仅汇总有金额的事实</small></div>
                <div><span>已接入指标</span><strong>{{ overview.summary.metric_count || 0 }}</strong><small>{{ rangeText }}</small></div>
            </div>

            <el-tabs v-model="activeTab" @tab-change="changeTab">
                <el-tab-pane label="产出总览" name="overview">
                    <div class="content-grid">
                        <section>
                            <div class="section-head"><h3>员工排行</h3><span>按有效完成事项排序</span></div>
                            <el-table :data="overview.employees" row-key="employee_uid" @row-click="openEmployee">
                                <el-table-column label="员工" min-width="150"><template #default="{ row }"><strong>{{ row.employee_name || `员工${row.employee_uid}` }}</strong><div class="secondary">{{ row.metric_count }} 类事项</div></template></el-table-column>
                                <el-table-column prop="completed_count" label="完成事项" width="105" />
                                <el-table-column label="业务金额" width="130"><template #default="{ row }">¥{{ money(row.amount) }}</template></el-table-column>
                                <el-table-column label="最近产出" width="170"><template #default="{ row }">{{ time(row.last_occurred_at) }}</template></el-table-column>
                                <el-table-column width="72" align="right"><template #default><el-icon><ArrowRight /></el-icon></template></el-table-column>
                            </el-table>
                            <el-empty v-if="!loading && !overview.employees.length" description="当前周期暂无员工产出" />
                        </section>
                        <section>
                            <div class="section-head"><h3>事项分布</h3><span>动作、结果和质量分开统计</span></div>
                            <el-table :data="overview.metrics" row-key="metric_key">
                                <el-table-column label="事项" min-width="180"><template #default="{ row }"><strong>{{ row.metric_name }}</strong><div class="secondary">{{ pluginName(row.source_plugin) }}</div></template></el-table-column>
                                <el-table-column label="口径" width="88"><template #default="{ row }"><el-tag :type="scopeType(row.fact_scope)" effect="plain">{{ scopeName(row.fact_scope) }}</el-tag></template></el-table-column>
                                <el-table-column prop="completed_count" label="完成" width="80" />
                                <el-table-column label="数量" width="95"><template #default="{ row }">{{ number(row.quantity) }}</template></el-table-column>
                            </el-table>
                            <el-empty v-if="!loading && !overview.metrics.length" description="尚未接收到产出指标" />
                        </section>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="事实明细" name="facts">
                    <el-table :data="facts.data" v-loading="factsLoading" row-key="id">
                        <el-table-column label="事项" min-width="210"><template #default="{ row }"><strong>{{ row.metric_name || row.action_key }}</strong><div class="secondary">{{ row.employee_name }} · {{ pluginName(row.source_plugin) }}</div></template></el-table-column>
                        <el-table-column label="业务对象" min-width="200"><template #default="{ row }"><span>{{ row.business_no || `${row.business_type} #${row.business_id}` }}</span><div v-if="row.imei" class="secondary">IMEI {{ row.imei }}</div></template></el-table-column>
                        <el-table-column label="数量" width="100"><template #default="{ row }"><span :class="{ negative: Number(row.quantity) < 0 }">{{ number(row.quantity) }}</span></template></el-table-column>
                        <el-table-column label="类型" width="95"><template #default="{ row }"><el-tag :type="row.fact_type === 'reversal' ? 'danger' : scopeType(row.fact_scope)" effect="plain">{{ row.fact_type === 'reversal' ? '冲红' : scopeName(row.fact_scope) }}</el-tag></template></el-table-column>
                        <el-table-column label="发生时间" width="180"><template #default="{ row }">{{ time(row.occurred_at) }}</template></el-table-column>
                    </el-table>
                    <div class="pagination"><el-pagination v-model:current-page="factQuery.page" v-model:page-size="factQuery.limit" layout="total, prev, pager, next" :total="facts.total" @current-change="loadFacts" /></div>
                </el-tab-pane>

                <el-tab-pane name="anomalies">
                    <template #label><span>数据异常<el-badge v-if="anomalies.total" :value="anomalies.total" class="tab-badge" /></span></template>
                    <el-table :data="anomalies.data" v-loading="anomalyLoading" row-key="id">
                        <el-table-column label="异常" min-width="210"><template #default="{ row }"><strong>{{ anomalyName(row.anomaly_type) }}</strong><div class="secondary">{{ row.event_id || '无事件ID' }}</div></template></el-table-column>
                        <el-table-column prop="message" label="说明" min-width="320" />
                        <el-table-column prop="occurrence_count" label="出现次数" width="90" />
                        <el-table-column label="最近发现" width="180"><template #default="{ row }">{{ time(row.update_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="100" align="right"><template #default="{ row }"><el-button link type="primary" @click="resolveAnomaly(row)">标记已处理</el-button></template></el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>

            <el-drawer v-model="employeeVisible" :title="employeeDetail.employee?.employee_name || '员工产出'" size="min(760px, 92vw)">
                <div v-loading="employeeLoading">
                    <div class="employee-summary"><div><span>完成事项</span><strong>{{ employeeDetail.employee?.completed_count || 0 }}</strong></div><div><span>业务金额</span><strong>¥{{ money(employeeDetail.employee?.amount) }}</strong></div><div><span>指标数</span><strong>{{ employeeDetail.employee?.metric_count || 0 }}</strong></div></div>
                    <h3>指标构成</h3>
                    <el-table :data="employeeDetail.metrics || []" size="small"><el-table-column prop="metric_name" label="事项" /><el-table-column prop="completed_count" label="完成" width="90" /><el-table-column label="数量" width="100"><template #default="{ row }">{{ number(row.quantity) }}</template></el-table-column></el-table>
                    <h3>最近事实</h3>
                    <el-timeline><el-timeline-item v-for="row in employeeDetail.facts || []" :key="row.id" :timestamp="time(row.occurred_at)"><strong>{{ row.metric_name }}</strong><div class="secondary">{{ row.business_no || row.imei || `#${row.business_id}` }}</div></el-timeline-item></el-timeline>
                </div>
            </el-drawer>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown, ArrowRight, Refresh, Tools } from '@element-plus/icons-vue'
import { getPerformanceOutputAnomalies, getPerformanceOutputEmployee, getPerformanceOutputFacts, getPerformanceOutputOverview, rebuildPerformanceOutput, reconcilePerformanceOutput, resolvePerformanceOutputAnomaly } from '../../api'

const periodOptions = [{ label: '今日', value: 'today' }, { label: '本周', value: 'week' }, { label: '近7天', value: 'last7' }, { label: '本月', value: 'month' }]
const query = reactive<any>({ period: 'month', source_plugin: '', metric_key: '', fact_scope: '' })
const factQuery = reactive({ page: 1, limit: 20 })
const overview = reactive<any>({ summary: {}, employees: [], metrics: [], trend: [], range: {}, filters: { plugins: [], metrics: [] } })
const filters = computed(() => overview.filters || { plugins: [], metrics: [] })
const metricOptions = computed(() => (filters.value.metrics || []).filter((item: any) => !query.source_plugin || item.source_plugin === query.source_plugin))
const activeTab = ref('overview'), loading = ref(false), factsLoading = ref(false), anomalyLoading = ref(false)
const facts = reactive<any>({ data: [], total: 0 }), anomalies = reactive<any>({ data: [], total: 0 })
const employeeVisible = ref(false), employeeLoading = ref(false), employeeDetail = reactive<any>({ employee: null, metrics: [], facts: [] })
const rangeText = computed(() => overview.range?.start_date ? `${overview.range.start_date} 至 ${overview.range.end_date}` : '')
let overviewRequestId = 0
let factRequestId = 0

const params = () => ({ ...query })
const loadOverview = async () => {
    const requestId = ++overviewRequestId
    loading.value = true
    try {
        const result = (await getPerformanceOutputOverview(params())).data || {}
        if (requestId === overviewRequestId) Object.assign(overview, result)
    } finally {
        if (requestId === overviewRequestId) loading.value = false
    }
}
const loadFacts = async () => {
    const requestId = ++factRequestId
    factsLoading.value = true
    try {
        const result = (await getPerformanceOutputFacts({ ...params(), ...factQuery })).data || {}
        if (requestId === factRequestId) Object.assign(facts, result)
    } finally {
        if (requestId === factRequestId) factsLoading.value = false
    }
}
const loadAnomalies = async () => { anomalyLoading.value = true; try { Object.assign(anomalies, (await getPerformanceOutputAnomalies({ status: 'open', page: 1, limit: 50 })).data || {}) } finally { anomalyLoading.value = false } }
const refreshAll = async () => { factQuery.page = 1; await loadOverview(); if (activeTab.value === 'facts') await loadFacts(); if (activeTab.value === 'anomalies') await loadAnomalies() }
const refreshPeriod = async () => { await nextTick(); refreshAll() }
const changePlugin = () => { query.metric_key = ''; refreshAll() }
const changeTab = (name: any) => { if (name === 'facts') loadFacts(); if (name === 'anomalies') loadAnomalies() }
const openEmployee = async (row: any) => { employeeVisible.value = true; employeeLoading.value = true; try { Object.assign(employeeDetail, (await getPerformanceOutputEmployee(Number(row.employee_uid), params())).data || {}) } finally { employeeLoading.value = false } }
const handleTool = async (command: string) => {
    if (command === 'rebuild') {
        await ElMessageBox.confirm(`将从原始事实重建${rangeText.value}的汇总，是否继续？`, '重建汇总', { type: 'warning' })
        const data: any = (await rebuildPerformanceOutput(params())).data || {}; ElMessage.success(`已重建 ${data.group_count || 0} 个汇总分组`); return refreshAll()
    }
    const data: any = (await reconcilePerformanceOutput(params())).data || {}
    data.passed ? ElMessage.success('对账通过，事实与汇总一致') : ElMessage.warning(`发现不一致：事实分组 ${data.fact_group_count}，汇总分组 ${data.summary_group_count}`)
    loadAnomalies()
}
const resolveAnomaly = async (row: any) => { await resolvePerformanceOutputAnomaly(Number(row.id)); ElMessage.success('已标记处理'); loadAnomalies() }
const money = (value: any) => Number(value || 0).toFixed(2)
const number = (value: any) => Number(value || 0).toLocaleString('zh-CN', { maximumFractionDigits: 4 })
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
const scopeName = (value: string) => ({ action: '动作', outcome: '结果', quality: '质量' }[value] || value)
const scopeType = (value: string) => ({ action: 'primary', outcome: 'success', quality: 'warning' }[value] || 'info') as any
const pluginName = (value: string) => ({ hsx_recycle: '回收业务', hsx_erp: 'ERP', hsx_member_card: '会员卡' }[value] || value)
const anomalyName = (value: string) => ({ invalid_fact: '事实格式无效', event_payload_conflict: '幂等内容冲突', orphan_reversal: '冲红缺少原事实', duplicate_reversal: '重复冲红', reversal_mismatch: '冲红字段不一致', projection_group_mismatch: '汇总分组不一致', projection_value_mismatch: '汇总数值不一致' }[value] || value)
onMounted(() => { loadOverview(); loadAnomalies() })
</script>

<style scoped lang="scss">
.page-head,.filters,.section-head { display:flex;align-items:center;justify-content:space-between;gap:12px; }.page-head { align-items:flex-start;margin-bottom:18px; }.page-head p { margin:6px 0 0;color:var(--el-text-color-secondary); }.filters { justify-content:flex-start;padding:12px 14px;margin-bottom:16px;background:var(--el-fill-color-lighter);border-radius:4px; }.filters .el-select { width:170px; }
.summary-grid { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid var(--el-border-color-lighter);margin-bottom:18px; }.summary-grid>div { min-height:92px;padding:16px 18px;display:flex;flex-direction:column;border-right:1px solid var(--el-border-color-lighter); }.summary-grid>div:last-child { border-right:0; }.summary-grid span,.section-head span,.secondary { color:var(--el-text-color-secondary);font-size:12px; }.summary-grid strong { margin-top:9px;font-size:24px;line-height:1.1; }.summary-grid small { margin-top:8px;color:var(--el-text-color-secondary);font-size:12px; }
.content-grid { display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:24px; }.section-head { margin:4px 0 10px; }.section-head h3,h3 { margin:0;font-size:15px; }.secondary { margin-top:4px; }.pagination { margin-top:15px;display:flex;justify-content:flex-end; }.negative { color:var(--el-color-danger); }.tab-badge { margin-left:6px; }.employee-summary { display:grid;grid-template-columns:repeat(3,1fr);border:1px solid var(--el-border-color-lighter); }.employee-summary>div { padding:14px;display:flex;flex-direction:column;border-right:1px solid var(--el-border-color-lighter); }.employee-summary>div:last-child { border-right:0; }.employee-summary span { color:var(--el-text-color-secondary);font-size:12px; }.employee-summary strong { margin-top:7px;font-size:20px; } .el-drawer h3 { margin:24px 0 10px; }
@media(max-width:1000px){.summary-grid{grid-template-columns:repeat(2,1fr)}.summary-grid>div:nth-child(2){border-right:0}.content-grid{grid-template-columns:1fr}} @media(max-width:700px){.page-head,.filters{align-items:stretch;flex-direction:column}.filters .el-select{width:100%}.summary-grid{grid-template-columns:1fr}.summary-grid>div{border-right:0;border-bottom:1px solid var(--el-border-color-lighter)}}
</style>
