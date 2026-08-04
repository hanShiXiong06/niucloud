<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="report-page">
                <div class="page-head">
                    <div>
                        <div class="text-page-title">经营报告</div>
                        <p>先看成交、毛利和异常，再进入报告核对员工、分类与业务明细。</p>
                    </div>
                    <el-button :icon="Setting" @click="configVisible = true">报告设置</el-button>
                </div>

                <div class="toolbar">
                    <el-segmented v-model="query.report_type" :options="typeOptions" @change="loadReports" />
                    <div class="toolbar-actions">
                        <el-dropdown @command="generateReport">
                            <el-button type="primary" :loading="generating">
                                <el-icon><Plus /></el-icon>立即生成<el-icon class="el-icon--right"><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="daily">经营日报</el-dropdown-item>
                                    <el-dropdown-item command="weekly">上周经营周报</el-dropdown-item>
                                    <el-dropdown-item command="monthly">上月经营月报</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-button :icon="Refresh" circle title="刷新" @click="loadReports" />
                    </div>
                </div>

                <section v-if="latestReport" class="overview">
                    <div class="overview-head">
                        <div>
                            <div class="overview-eyebrow">最新经营结果</div>
                            <h2>{{ latestReport.title }}</h2>
                            <p>{{ formatPeriod(latestReport, true) }}</p>
                        </div>
                        <div class="overview-actions">
                            <el-tag :type="dataStatus(latestReport).type" effect="plain">
                                {{ dataStatus(latestReport).text }}
                            </el-tag>
                            <el-button link type="primary" @click="openReport(latestReport)">查看详情</el-button>
                        </div>
                    </div>
                    <div class="core-grid">
                        <div v-for="item in latestCoreMetrics" :key="item.key" class="core-card" :class="`tone-${item.tone}`">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                            <small>{{ item.note }}</small>
                        </div>
                    </div>
                    <div class="attention-bar" :class="{ danger: latestAttention.providerErrors > 0 }">
                        <div><span>当前待处理</span><strong>{{ latestAttention.todos }} 项</strong></div>
                        <div><span>当前库存</span><strong>{{ latestAttention.stock }} 台</strong></div>
                        <div><span>设备动销率</span><strong>{{ latestAttention.turnover }}</strong></div>
                        <div v-if="latestAttention.providerErrors"><span>采集异常</span><strong>{{ latestAttention.providerErrors }} 个来源</strong></div>
                        <div class="scope-tip">库存和待办为报告生成时快照，其余指标按报告周期统计</div>
                    </div>
                </section>

                <div class="history-title"><span>历史报告</span><small>点击任意一行查看完整构成</small></div>
                <el-table :data="reports" v-loading="loading" row-key="id" @row-click="openReport">
                    <el-table-column label="报告周期" min-width="250">
                        <template #default="{ row }"><div class="report-title">{{ row.title }}</div><div class="secondary">{{ formatPeriod(row) }}</div></template>
                    </el-table-column>
                    <el-table-column label="销售额" width="130" align="right">
                        <template #default="{ row }">{{ moneyOrDash(summary(row), 'sale_amount') }}</template>
                    </el-table-column>
                    <el-table-column label="销售毛利" width="130" align="right">
                        <template #default="{ row }"><span :class="profitClass(summary(row).sale_profit)">{{ moneyOrDash(summary(row), 'sale_profit') }}</span></template>
                    </el-table-column>
                    <el-table-column label="销售件数" width="105" align="right"><template #default="{ row }">{{ quantity(summary(row).sale_count) }} 件</template></el-table-column>
                    <el-table-column label="回收入库" width="105" align="right"><template #default="{ row }">{{ quantity(summary(row).recycle_in_count) }} 台</template></el-table-column>
                    <el-table-column label="待处理" width="95" align="right"><template #default="{ row }"><span :class="{ warning: todoCount(row) > 0 }">{{ todoCount(row) }} 项</span></template></el-table-column>
                    <el-table-column label="数据状态" width="120"><template #default="{ row }"><el-tag :type="dataStatus(row).type" effect="plain">{{ dataStatus(row).text }}</el-tag></template></el-table-column>
                    <el-table-column label="通知" width="140"><template #default="{ row }"><el-tag :type="statusType(row.notify_status)" effect="plain">{{ statusText(row.notify_status) }}</el-tag></template></el-table-column>
                    <el-table-column label="操作" width="120" align="right">
                        <template #default="{ row }"><el-button link type="primary" @click.stop="openReport(row)">详情</el-button><el-button v-if="['failed', 'skipped'].includes(row.notify_status)" link type="warning" @click.stop="retry(row)">补推</el-button></template>
                    </el-table-column>
                </el-table>
                <div class="pagination"><el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" layout="total, prev, pager, next" :total="total" @current-change="loadReports" /></div>

                <el-drawer v-model="detailVisible" size="min(820px, 94vw)" :title="current?.title || '经营报告'">
                    <template v-if="current">
                        <el-alert type="info" :closable="false" show-icon>
                            <template #title>周期数据：{{ formatPeriod(current, true) }}</template>
                            库存、库存成本和待办取报告生成时的实时快照；销售、毛利、回收等按上方周期统计。
                        </el-alert>

                        <h3>老板核心数据</h3>
                        <div class="metric-grid">
                            <div v-for="item in periodMetricItems" :key="item.key" class="metric"><span>{{ item.label }}</span><strong :class="item.className">{{ item.value }}</strong></div>
                        </div>

                        <h3>生成时经营状态</h3>
                        <div class="realtime-grid">
                            <div v-for="item in realtimeMetricItems" :key="item.key"><span>{{ item.label }}</span><strong>{{ item.value }}</strong></div>
                        </div>

                        <template v-if="todoRows.length">
                            <h3>当前待处理</h3>
                            <div class="todo-list"><el-tag v-for="item in todoRows" :key="item.key" type="warning" effect="light">{{ item.label }} {{ item.value }}</el-tag></div>
                        </template>

                        <h3>员工产出</h3>
                        <el-table :data="snapshot.staff || []" size="small">
                            <el-table-column prop="name" label="员工" /><el-table-column prop="role_name" label="工作" />
                            <el-table-column prop="count" label="完成量" width="100" />
                            <el-table-column prop="amount" label="业务金额" width="130"><template #default="{ row }">¥{{ Number(row.amount || 0).toFixed(2) }}</template></el-table-column>
                            <el-table-column prop="profit" label="毛利" width="130"><template #default="{ row }"><span :class="profitClass(row.profit)">¥{{ Number(row.profit || 0).toFixed(2) }}</span></template></el-table-column>
                        </el-table>
                        <h3>货盘结构</h3>
                        <el-table :data="snapshot.categories || []" size="small"><el-table-column prop="name" label="分类" /><el-table-column prop="in_count" label="入库" width="90" /><el-table-column prop="sale_count" label="销售" width="90" /><el-table-column prop="stock_count" label="库存" width="90" /></el-table>
                        <h3>数据来源</h3>
                        <div class="providers"><el-tag v-for="item in current.providers_json || []" :key="item.provider" :type="item.available ? 'success' : 'danger'" effect="plain">{{ item.provider_name }} · {{ item.available ? '采集正常' : (item.error || '采集异常') }}</el-tag></div>
                    </template>
                </el-drawer>

                <el-dialog v-model="configVisible" title="经营报告设置" width="620px">
                    <el-form label-width="112px" v-loading="configLoading">
                        <el-form-item label="启用报告"><el-switch v-model="config.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                        <el-form-item label="报告周期"><el-checkbox v-model="config.daily_enabled" :true-value="1" :false-value="0">日报</el-checkbox><el-checkbox v-model="config.weekly_enabled" :true-value="1" :false-value="0">周报</el-checkbox><el-checkbox v-model="config.monthly_enabled" :true-value="1" :false-value="0">月报</el-checkbox></el-form-item>
                        <el-form-item label="推送时间"><el-time-select v-model="config.send_time" start="00:00" step="00:05" end="23:55" /></el-form-item>
                        <el-form-item label="日报统计日期">
                            <el-radio-group v-model="config.daily_scope">
                                <el-radio-button value="auto">智能判断</el-radio-button><el-radio-button value="current_day">当天截至推送</el-radio-button><el-radio-button value="previous_day">完整昨天</el-radio-button>
                            </el-radio-group>
                            <div class="form-help">智能判断：17:00 后推送当天数据，17:00 前推送昨天完整数据。</div>
                        </el-form-item>
                        <el-form-item label="老板接收人"><el-select v-model="config.receiver_uids" multiple filterable class="w-full" placeholder="选择接收经营报告的员工"><el-option v-for="item in config.staff_options || []" :key="item.uid" :label="item.name" :value="item.uid" /></el-select><div class="form-help">接收人还需要在企业微信插件中绑定成员 UserID。</div></el-form-item>
                        <el-form-item label="敏感数据"><el-checkbox v-model="config.show_finance" :true-value="1" :false-value="0">展示销售额</el-checkbox><el-checkbox v-model="config.show_profit" :true-value="1" :false-value="0">展示毛利</el-checkbox></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="configVisible = false">取消</el-button><el-button type="primary" :loading="configSaving" @click="saveConfig">保存</el-button></template>
                </el-dialog>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { ArrowDown, Plus, Refresh, Setting } from '@element-plus/icons-vue'
import { generatePerformanceReport, getPerformanceConfig, getPerformanceReport, getPerformanceReports, retryPerformanceReport, savePerformanceConfig } from '../../api'

const typeOptions = [{ label: '全部', value: '' }, { label: '日报', value: 'daily' }, { label: '周报', value: 'weekly' }, { label: '月报', value: 'monthly' }]
const query = reactive({ report_type: '', page: 1, limit: 15 })
const reports = ref<any[]>([]), total = ref(0), loading = ref(false), generating = ref(false)
const detailVisible = ref(false), current = ref<any>(null)
const configVisible = ref(false), configLoading = ref(false), configSaving = ref(false)
const config = reactive<any>({ enabled: 0, daily_enabled: 1, weekly_enabled: 1, monthly_enabled: 1, send_time: '09:10', daily_scope: 'auto', receiver_uids: [], show_finance: 1, show_profit: 1, staff_options: [] })
const snapshot = computed(() => current.value?.snapshot_json || {})
const latestReport = computed(() => reports.value[0] || null)
const latestSummary = computed(() => summary(latestReport.value))
const latestCoreMetrics = computed(() => {
    const s = latestSummary.value
    const items: any[] = []
    if (Object.prototype.hasOwnProperty.call(s, 'sale_amount')) items.push({ key: 'amount', label: '周期销售额', value: money(s.sale_amount), note: `${quantity(s.sale_order_count)} 笔有效成交`, tone: 'blue' })
    if (Object.prototype.hasOwnProperty.call(s, 'sale_profit')) items.push({ key: 'profit', label: '周期销售毛利', value: money(s.sale_profit), note: `毛利率 ${Number(s.gross_margin_rate || 0).toFixed(2)}%`, tone: Number(s.sale_profit || 0) < 0 ? 'red' : 'green' })
    items.push({ key: 'sale', label: '周期销售件数', value: `${quantity(s.sale_count)} 件`, note: '已剔除退货和作废明细', tone: 'violet' })
    items.push({ key: 'recycle', label: '周期回收入库', value: `${quantity(s.recycle_in_count)} 台`, note: '按报告统计周期汇总', tone: 'orange' })
    return items
})
const latestAttention = computed(() => ({
    todos: todoCount(latestReport.value), stock: quantity(latestSummary.value.stock_count),
    turnover: `${Number(latestSummary.value.turnover_rate || 0).toFixed(2)}%`, providerErrors: providerErrorCount(latestReport.value),
}))
const periodMetricItems = computed(() => {
    const s = snapshot.value.summary || {}
    const items: any[] = []
    if (Object.prototype.hasOwnProperty.call(s, 'sale_amount')) items.push({ key: 'sale_amount', label: '销售额', value: money(s.sale_amount) })
    if (Object.prototype.hasOwnProperty.call(s, 'sale_profit')) items.push({ key: 'sale_profit', label: '销售毛利', value: money(s.sale_profit), className: profitClass(s.sale_profit) })
    if (Object.prototype.hasOwnProperty.call(s, 'gross_margin_rate')) items.push({ key: 'margin', label: '毛利率', value: `${Number(s.gross_margin_rate || 0).toFixed(2)}%` })
    items.push({ key: 'sale_order', label: '有效成交', value: `${quantity(s.sale_order_count)} 笔` }, { key: 'sale', label: '销售件数', value: `${quantity(s.sale_count)} 件` }, { key: 'recycle', label: '回收入库', value: `${quantity(s.recycle_in_count)} 台` }, { key: 'turnover', label: '设备动销率', value: `${Number(s.turnover_rate || 0).toFixed(2)}%` })
    return items
})
const realtimeMetricItems = computed(() => { const s = snapshot.value.summary || {}; return [
    { key: 'stock', label: '当前库存', value: `${quantity(s.stock_count)} 台` },
    ...(Object.prototype.hasOwnProperty.call(s, 'stock_cost') ? [{ key: 'stock_cost', label: '库存成本', value: money(s.stock_cost) }] : []),
    { key: 'todo', label: '当前待处理', value: `${Number(s.todo_count ?? todoCount(current.value))} 项` },
    { key: 'source', label: '数据来源', value: `${Number(s.provider_count || (current.value?.providers_json || []).length)} 个` },
] })
const todoLabels: Record<string, string> = { recycle_pending_sign: '待签收', recycle_pending_check: '待质检', recycle_pending_price: '待回收定价', need_photo: '待拍照', need_price: '待销售定价', need_material: '待完善资料', ready: '待上架', pending_shop: '待商城处理', payable: '待付款', receivable: '待收款' }
const todoRows = computed(() => Object.entries(snapshot.value.todos || {}).map(([key, value]) => ({ key, label: todoLabels[key] || key, value: Number(value || 0) })).filter(item => item.value > 0))

const loadReports = async () => { loading.value = true; try { const data: any = (await getPerformanceReports(query)).data || {}; reports.value = data.data || []; total.value = data.total || 0 } finally { loading.value = false } }
const loadConfig = async () => { configLoading.value = true; try { Object.assign(config, (await getPerformanceConfig()).data || {}) } finally { configLoading.value = false } }
const saveConfig = async () => { configSaving.value = true; try { Object.assign(config, (await savePerformanceConfig(config)).data || {}); configVisible.value = false; ElMessage.success('经营报告设置已保存') } finally { configSaving.value = false } }
const generateReport = async (type: string) => { generating.value = true; try { const row: any = (await generatePerformanceReport(type)).data; ElMessage.success('报告已生成并进入通知队列'); await loadReports(); if (row?.id) openReport(row) } finally { generating.value = false } }
const openReport = async (row: any) => { current.value = (await getPerformanceReport(Number(row.id))).data; detailVisible.value = true }
const retry = async (row: any) => { await retryPerformanceReport(Number(row.id)); ElMessage.success('已重新派发报告通知'); loadReports() }
const summary = (row: any) => row?.summary_json || {}
const todoCount = (row: any) => Number(summary(row).todo_count ?? Object.values(row?.snapshot_json?.todos || {}).reduce((sum: number, value: any) => sum + Number(value || 0), 0))
const providerErrorCount = (row: any) => Number(summary(row).provider_error_count ?? (row?.providers_json || []).filter((item: any) => !item.available).length)
const dataStatus = (row: any) => {
    if (providerErrorCount(row) > 0) return { text: '采集异常', type: 'danger' as const }
    if (Number(row?.snapshot_json?.meta?.metric_version || 0) < 2) return { text: '旧统计口径', type: 'warning' as const }
    return { text: '口径正常', type: 'success' as const }
}
const money = (value: any) => `¥${Number(value || 0).toFixed(2)}`
const moneyOrDash = (data: any, key: string) => Object.prototype.hasOwnProperty.call(data || {}, key) ? money(data[key]) : '—'
const quantity = (value: any) => { const number = Number(value || 0); return Number.isInteger(number) ? String(number) : number.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') }
const profitClass = (value: any) => Number(value || 0) < 0 ? 'negative' : Number(value || 0) > 0 ? 'positive' : ''
const statusText = (value: string) => ({ pending: '待入队', success: '已进入通知队列', failed: '入队失败', skipped: '待补充配置' }[value] || value)
const statusType = (value: string) => ({ success: 'success', failed: 'danger', skipped: 'warning', pending: 'info' }[value] || 'info') as any
const dateParts = (value: any) => { const date = new Date(Number(value) * 1000); return { date: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`, time: `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}` } }
const formatPeriod = (row: any, withTime = false) => { if (!row?.start_at || !row?.end_at) return '—'; const start = dateParts(row.start_at), end = dateParts(row.end_at); if (!withTime) return start.date === end.date ? start.date : `${start.date} 至 ${end.date}`; return start.date === end.date ? `${start.date} ${start.time} 至 ${end.time}` : `${start.date} ${start.time} 至 ${end.date} ${end.time}` }
watch(configVisible, value => { if (value) loadConfig() })
onMounted(() => { loadReports(); loadConfig() })
</script>

<style scoped lang="scss">
.report-page { min-height: 100%; color: var(--el-text-color-primary); }
.page-head, .toolbar, .toolbar-actions, .overview-head, .overview-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.page-head { align-items: flex-start; margin-bottom: 20px; }.page-head p { margin: 6px 0 0; color: var(--el-text-color-secondary); }
.toolbar { margin-bottom: 16px; padding: 14px 16px; border-radius: 8px; background: var(--el-fill-color-lighter); }
.overview { margin-bottom: 24px; padding: 22px; overflow: hidden; border: 1px solid #dbe5ff; border-radius: 12px; background: linear-gradient(135deg, #f7faff 0%, #fff 70%); }
.overview-head { align-items: flex-start; }.overview-eyebrow { margin-bottom: 6px; color: #2563eb; font-size: 12px; font-weight: 700; letter-spacing: 1px; }.overview h2 { margin: 0; font-size: 20px; }.overview p { margin: 7px 0 0; color: var(--el-text-color-secondary); font-size: 13px; }
.core-grid { margin-top: 20px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }.core-card { min-height: 108px; padding: 16px; display: flex; flex-direction: column; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: #fff; }.core-card span { color: var(--el-text-color-secondary); font-size: 13px; }.core-card strong { margin-top: 9px; font-size: 25px; line-height: 1.2; }.core-card small { margin-top: auto; padding-top: 10px; color: var(--el-text-color-secondary); }.tone-blue strong { color: #2563eb; }.tone-green strong { color: #059669; }.tone-red strong, .negative { color: #dc2626 !important; }.tone-violet strong { color: #7c3aed; }.tone-orange strong { color: #d97706; }.positive { color: #059669; }
.attention-bar { margin-top: 14px; padding: 12px 14px; display: flex; align-items: center; gap: 26px; border-radius: 8px; background: #f8fafc; }.attention-bar > div:not(.scope-tip) { display: flex; gap: 7px; white-space: nowrap; }.attention-bar span, .scope-tip { color: var(--el-text-color-secondary); font-size: 12px; }.attention-bar strong { font-size: 13px; }.attention-bar.danger { background: #fef2f2; }.scope-tip { margin-left: auto; }
.history-title { margin: 20px 0 10px; display: flex; align-items: baseline; gap: 10px; font-size: 16px; font-weight: 650; }.history-title small { color: var(--el-text-color-secondary); font-size: 12px; font-weight: 400; }.report-title { font-weight: 600; }.secondary, .form-help { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; }.warning { color: #d97706; font-weight: 600; }.pagination { display: flex; justify-content: flex-end; margin-top: 16px; }
.metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }.metric { min-height: 82px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; background: var(--el-fill-color-lighter); }.metric span, .realtime-grid span { color: var(--el-text-color-secondary); font-size: 13px; }.metric strong { margin-top: 10px; font-size: 22px; }
.realtime-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }.realtime-grid > div { padding: 13px 14px; display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }.realtime-grid strong { font-size: 16px; }.todo-list, .providers { display: flex; flex-wrap: wrap; gap: 8px; } h3 { margin: 24px 0 10px; font-size: 16px; }.w-full { width: 100%; }
@media (max-width: 1100px) { .core-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.attention-bar { flex-wrap: wrap; }.scope-tip { width: 100%; margin-left: 0; }.realtime-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) { .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.page-head, .toolbar, .overview-head { align-items: stretch; flex-direction: column; }.core-grid { grid-template-columns: 1fr; } }
</style>
