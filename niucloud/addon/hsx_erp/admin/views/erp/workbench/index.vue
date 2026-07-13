<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">ERP 经营工作台</div>
                    <div class="mt-1 text-sm text-gray-500">统一查看采购、销售、库存、利润与财务待办，统计来自完整业务数据。</div>
                </div>
                <div class="flex items-center gap-3">
                    <el-button @click="openKpiConfig">绩效配置</el-button>
                    <el-radio-group v-model="period" @change="loadDashboard">
                        <el-radio-button label="today">今日</el-radio-button>
                        <el-radio-button label="yesterday">昨天</el-radio-button>
                        <el-radio-button label="last7">近7天</el-radio-button>
                        <el-radio-button label="month">本月</el-radio-button>
                        <el-radio-button label="last_month">上月</el-radio-button>
                        <el-radio-button label="all">全部</el-radio-button>
                    </el-radio-group>
                    <el-date-picker v-model="customRange" type="daterange" range-separator="至" start-placeholder="自定义开始" end-placeholder="自定义结束" :clearable="true" @change="onCustomRangeChange" />
                    <el-button :icon="Refresh" :loading="loading" @click="loadDashboard">刷新</el-button>
                </div>
            </div>

            <el-alert v-if="refurbishReminder.visible" class="mt-4" type="warning" show-icon :closable="false">
                <template #title>今日新增 {{ refurbishReminder.today_count }} 台待整备设备，已达到提醒阈值</template>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>当前待整备 {{ refurbishReminder.pending_count }} 台、整备中 {{ refurbishReminder.processing_count }} 台。请及时完成分配，避免设备积压影响动销。</span>
                    <div class="flex gap-2">
                        <el-button size="small" type="warning" @click="goRefurbishQueue">查看整备设备</el-button>
                        <el-dropdown @command="dismissRefurbish">
                            <el-button size="small">关闭提醒</el-button>
                            <template #dropdown><el-dropdown-menu><el-dropdown-item command="today">今天不再提醒</el-dropdown-item><el-dropdown-item command="forever" divided>永久关闭此提醒</el-dropdown-item></el-dropdown-menu></template>
                        </el-dropdown>
                    </div>
                </div>
            </el-alert>

            <el-alert v-if="turnoverReminder.visible" class="mt-4" type="error" show-icon :closable="false">
                <template #title>库存周转预警：{{ turnoverReminder.warning_total_count }} 台设备已超过预警库龄</template>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>其中严重滞销 {{ turnoverReminder.critical_count }} 台，占用库存成本 {{ money(turnoverReminder.warning_total_cost) }}；当前平均库龄 {{ turnoverReminder.average_age_days }} 天。</span>
                    <div class="flex gap-2">
                        <el-button size="small" type="danger" @click="goTurnoverQueue">查看预警库存</el-button>
                        <el-dropdown @command="dismissTurnover">
                            <el-button size="small">关闭提醒</el-button>
                            <template #dropdown><el-dropdown-menu><el-dropdown-item command="today">今天不再提醒</el-dropdown-item><el-dropdown-item command="forever" divided>永久关闭此提醒</el-dropdown-item></el-dropdown-menu></template>
                        </el-dropdown>
                    </div>
                </div>
            </el-alert>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="item in summaryCards" :key="item.label" class="summary-tile">
                    <div class="summary-label">{{ item.label }}</div>
                    <div class="summary-value" :class="item.className">{{ item.value }}</div>
                    <div v-if="item.hint" class="mt-1 text-xs text-gray-400">{{ item.hint }}</div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-5">
                <div class="panel xl:col-span-3">
                    <div class="panel-head"><div><div class="panel-title">经营结构</div><div class="mt-1 text-xs text-gray-400">销售、经营收支与最终利润对比</div></div><el-tag effect="plain">{{ periodLabel }}</el-tag></div>
                    <div ref="operationChartRef" class="operation-chart"></div>
                </div>
                <div class="panel xl:col-span-2">
                    <div class="panel-head"><div><div class="panel-title">利润构成</div><div class="mt-1 text-xs text-gray-400">收入来源与经营费用占比</div></div></div>
                    <div ref="structureChartRef" class="operation-chart"></div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div class="panel xl:col-span-3">
                    <div class="panel-head"><div><div class="panel-title">员工绩效</div><div class="mt-1 text-xs text-gray-400">按真实采购、销售、质检与财务事实计算；展开可看每项指标</div></div><el-tag effect="plain">{{ periodLabel }}</el-tag></div>
                    <el-table :data="kpi.staff || []" empty-text="本期暂无员工业务事实">
                        <el-table-column type="expand"><template #default="{ row }"><div class="grid grid-cols-1 gap-3 p-3 md:grid-cols-3"><div v-for="item in row.details" :key="item.metric_key" class="rounded bg-slate-50 p-3"><div class="text-sm font-medium">{{ item.metric_name }}</div><div class="mt-2 text-xs text-gray-500">实际 {{ item.actual }}{{ item.unit }} / 目标 {{ item.target }}{{ item.unit }}</div><el-progress class="mt-2" :percentage="Math.min(100, Number(item.completion_rate || 0))" /></div></div></template></el-table-column>
                        <el-table-column type="index" label="排名" width="70" />
                        <el-table-column prop="name" label="员工" min-width="150" />
                        <el-table-column label="综合得分" width="180"><template #default="{ row }"><el-progress :percentage="Math.min(100, Number(row.score || 0))" :format="() => `${row.score} 分`" /></template></el-table-column>
                    </el-table>
                </div>
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title">财务待办</div>
                        <div class="text-xs text-gray-400">当前未结余额，不受统计期间影响</div>
                    </div>
                    <div class="todo-grid">
                        <div class="todo-item"><span>待付款</span><strong>{{ todo.payable_count || 0 }} 笔</strong></div>
                        <div class="todo-item"><span>待收款</span><strong>{{ todo.receivable_count || 0 }} 笔</strong></div>
                        <div class="todo-item"><span>可折账主体</span><strong>{{ todo.offset_party_count || 0 }} 个</strong></div>
                        <div class="todo-item"><span>剩余应付</span><strong class="text-orange-600">{{ money(summary.payable_remain) }}</strong></div>
                        <div class="todo-item"><span>剩余应收</span><strong class="text-blue-600">{{ money(summary.receivable_remain) }}</strong></div>
                    </div>
                </div>

                <div class="panel xl:col-span-2">
                    <div class="panel-head">
                        <div class="panel-title">最近结算</div>
                        <div class="text-xs text-gray-400">收款、付款与折账事实</div>
                    </div>
                    <el-table :data="recent.settlements || []" v-loading="loading" size="large" max-height="320" empty-text="暂无结算记录">
                        <el-table-column prop="settlement_no" label="结算单号" min-width="170" />
                        <el-table-column prop="party_name" label="往来单位" min-width="140" />
                        <el-table-column label="类型" width="90">
                            <template #default="{ row }"><el-tag :type="settlementType(row.settlement_type)">{{ settlementLabel(row.settlement_type) }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="金额" width="130" align="right"><template #default="{ row }">{{ money(row.amount) }}</template></el-table-column>
                        <el-table-column prop="capital_account_name" label="资金账户" min-width="130" />
                        <el-table-column label="确认时间" width="170"><template #default="{ row }">{{ formatTime(row.confirmed_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="90" fixed="right"><template #default="{ row }"><el-button link type="primary" :disabled="!row.target_id" @click="goSettlement(row)">查看账单</el-button></template></el-table-column>
                    </el-table>
                </div>
            </div>

            <el-dialog v-model="kpiConfigVisible" title="员工绩效配置" width="680px" append-to-body>
                <el-alert title="目标按当前看板统计周期计算；权重决定综合得分占比，超额完成最高计到该项 120%。" type="info" :closable="false" class="mb-4" />
                <el-table :data="kpiRules">
                    <el-table-column prop="metric_name" label="指标" min-width="140" />
                    <el-table-column label="周期目标" width="180"><template #default="{ row }"><el-input-number v-model="row.target_value" :min="0.01" :precision="2" /><span class="ml-1 text-xs text-gray-400">{{ row.unit }}</span></template></el-table-column>
                    <el-table-column label="权重" width="160"><template #default="{ row }"><el-input-number v-model="row.weight" :min="0.01" :max="100" :precision="1" /><span class="ml-1 text-xs text-gray-400">%</span></template></el-table-column>
                    <el-table-column label="启用" width="80"><template #default="{ row }"><el-switch v-model="row.enabled" :active-value="1" :inactive-value="0" /></template></el-table-column>
                </el-table>
                <template #footer><el-button @click="kpiConfigVisible=false">取消</el-button><el-button type="primary" :loading="kpiSaving" @click="saveKpiConfig">保存配置</el-button></template>
            </el-dialog>

            <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">最近采购</div><div class="text-xs text-gray-400">最近 5 张采购单</div></div>
                    <el-table :data="recent.purchases || []" v-loading="loading" size="large" max-height="320" empty-text="暂无采购记录">
                        <el-table-column prop="purchase_no" label="采购单号" min-width="165" />
                        <el-table-column prop="party_name" label="采购渠道" min-width="140" />
                        <el-table-column label="金额" width="125" align="right"><template #default="{ row }">{{ money(row.total_cost) }}</template></el-table-column>
                        <el-table-column label="状态" width="100"><template #default="{ row }">{{ financeStatus(row.finance_status, '付款') }}</template></el-table-column>
                    </el-table>
                </div>
                <div class="panel">
                    <div class="panel-head"><div class="panel-title">最近销售</div><div class="text-xs text-gray-400">最近 5 张销售单</div></div>
                    <el-table :data="recent.sales || []" v-loading="loading" size="large" max-height="320" empty-text="暂无销售记录">
                        <el-table-column prop="sale_no" label="销售单号" min-width="165" />
                        <el-table-column prop="party_name" label="销售客户" min-width="140" />
                        <el-table-column label="销售额" width="120" align="right"><template #default="{ row }">{{ money(row.total_amount) }}</template></el-table-column>
                        <el-table-column label="毛利" width="120" align="right"><template #default="{ row }">{{ money(row.profit) }}</template></el-table-column>
                        <el-table-column label="状态" width="100"><template #default="{ row }">{{ financeStatus(row.finance_status, '收款') }}</template></el-table-column>
                    </el-table>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import * as echarts from 'echarts'
import { Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getErpDashboard, getErpKpiDashboard, getErpKpiRules, saveErpKpiRules } from '@/addon/hsx_erp/api/erp'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
import { dismissErpRefurbishReminder, dismissErpTurnoverReminder } from '@/addon/hsx_erp/api/config'

const loading = ref(false)
const period = ref('month')
const customRange = ref<Date[]>([])
const router = useRouter()
const data = ref<any>({})
const kpi = ref<any>({})
const kpiConfigVisible = ref(false)
const kpiSaving = ref(false)
const kpiRules = ref<any[]>([])
let dashboardLoadSequence = 0

const summary = computed(() => data.value?.summary || {})
const todo = computed(() => data.value?.todo || {})
const recent = computed(() => data.value?.recent || {})
const refurbishReminder = computed(() => data.value?.reminders?.refurbish || {})
const turnoverReminder = computed(() => data.value?.reminders?.turnover || {})
const periodLabel = computed(() => ({ today: '今日', yesterday: '昨天', last7: '近7天', month: '本月', last_month: '上月', custom: '自定义', all: '全部' } as Record<string, string>)[period.value] || '本期')
const operationChartRef = ref<HTMLElement>()
const structureChartRef = ref<HTMLElement>()
let operationChart: echarts.ECharts | undefined
let structureChart: echarts.ECharts | undefined
const summaryCards = computed(() => [
    { label: '采购单数', value: summary.value.purchase_count || 0, hint: money(summary.value.purchase_amount) },
    { label: '销售单数', value: summary.value.sale_count || 0, hint: money(summary.value.sale_amount) },
    { label: '销售毛利', value: money(summary.value.profit_amount), className: Number(summary.value.profit_amount || 0) >= 0 ? 'text-green-600' : 'text-red-600' },
    { label: '经营费用', value: money(summary.value.operating_expense_amount), className: 'text-orange-600' },
    { label: '经营净利润', value: money(summary.value.operating_profit_amount), hint: `其他经营收入 ${money(summary.value.operating_income_amount)}`, className: Number(summary.value.operating_profit_amount || 0) >= 0 ? 'text-green-600' : 'text-red-600' },
    { label: '库存设备', value: summary.value.stock_count || 0, hint: `库存成本 ${money(summary.value.stock_cost)}` },
    { label: '今日动销率', value: `${Number(summary.value.turnover_rate || 0).toFixed(2)}%`, hint: `今日售出 ${summary.value.today_sold_count || 0} 台 ÷ 零点库存 ${summary.value.opening_stock_count || 0} 台`, className: Number(summary.value.turnover_rate || 0) > 0 ? 'text-blue-600' : '' },
    { label: '库存周转', value: `${Number(summary.value.average_stock_age_days || 0).toFixed(1)} 天`, hint: `预警 ${summary.value.turnover_warning_count || 0} 台 · 占用 ${money(summary.value.turnover_warning_cost)}`, className: Number(summary.value.turnover_warning_count || 0) > 0 ? 'text-orange-600' : 'text-green-600' },
    { label: '期间收款', value: money(summary.value.receipt_amount), className: 'text-green-600' },
    { label: '期间付款', value: money(summary.value.payment_amount), className: 'text-orange-600' },
    { label: '期间折账', value: money(summary.value.offset_amount), className: 'text-blue-600' },
    { label: '净现金流', value: money(Number(summary.value.receipt_amount || 0) - Number(summary.value.payment_amount || 0)) },
])

useErpPageRefresh(loadDashboard)

async function loadDashboard() {
    const sequence = ++dashboardLoadSequence
    loading.value = true
    try {
        const params: Record<string, any> = { period: period.value }
        if (period.value === 'custom' && customRange.value?.length === 2) {
            const start = new Date(customRange.value[0]); start.setHours(0, 0, 0, 0)
            const end = new Date(customRange.value[1]); end.setHours(23, 59, 59, 999)
            params.start_at = Math.floor(start.getTime() / 1000)
            params.end_at = Math.floor(end.getTime() / 1000)
        }
        const [dashboardResult, kpiResult] = await Promise.allSettled([getErpDashboard(params), getErpKpiDashboard(params)])
        if (sequence !== dashboardLoadSequence) return
        if (dashboardResult.status === 'rejected') throw dashboardResult.reason
        data.value = (dashboardResult.value as any)?.data || {}
        // KPI 是扩展模块。没有绩效权限或配置异常时，不得拖垮老板工作台核心经营数据。
        kpi.value = kpiResult.status === 'fulfilled' ? ((kpiResult.value as any)?.data || {}) : {}
        await nextTick()
        renderCharts()
    } catch (error: any) {
        if (sequence !== dashboardLoadSequence) return
        data.value = {}
        kpi.value = {}
        ElMessage.error(error?.message || error?.msg || '经营数据加载失败，请稍后重试')
    } finally {
        if (sequence === dashboardLoadSequence) loading.value = false
    }
}

function goRefurbishQueue() {
    router.push({ path: '/site/hsx_erp/stock', query: { refurbish_status: 'pending' } })
}

function goTurnoverQueue() {
    router.push({ path: '/site/hsx_erp/stock', query: { turnover_level: 'risk' } })
}

async function dismissRefurbish(mode: 'today' | 'forever') {
    await dismissErpRefurbishReminder(mode)
    ElMessage.success(mode === 'forever' ? '已永久关闭整备积压提醒，可在业务规则中重新开启' : '今天不再提醒')
    await loadDashboard()
}

async function dismissTurnover(mode: 'today' | 'forever') {
    await dismissErpTurnoverReminder(mode)
    ElMessage.success(mode === 'forever' ? '已永久关闭库存周转提醒，可在业务规则中重新开启' : '今天不再提醒')
    await loadDashboard()
}

async function openKpiConfig() {
    const res: any = await getErpKpiRules()
    kpiRules.value = (res?.data || []).map((row: any) => ({ ...row, target_value: Number(row.target_value), weight: Number(row.weight), enabled: Number(row.enabled) }))
    kpiConfigVisible.value = true
}

async function saveKpiConfig() {
    kpiSaving.value = true
    try { await saveErpKpiRules(kpiRules.value); ElMessage.success('绩效配置已保存'); kpiConfigVisible.value = false; await loadDashboard() }
    finally { kpiSaving.value = false }
}

function onCustomRangeChange(value: Date[]) {
    if (!value?.length) return
    period.value = 'custom'
    loadDashboard()
}

function goSettlement(row: any) {
    if (!row?.target_type) return
    router.push({ path: `/site/hsx_erp/${row.target_type}`, query: row.target_source_no ? { source_no: row.target_source_no } : {} })
}

function renderCharts() {
    if (operationChartRef.value) {
        operationChart ||= echarts.init(operationChartRef.value)
        const values = [summary.value.sale_amount, summary.value.profit_amount, summary.value.operating_income_amount, summary.value.operating_expense_amount, summary.value.operating_profit_amount].map(Number)
        operationChart.setOption({
            animationDuration: 650,
            grid: { left: 18, right: 18, top: 26, bottom: 14, containLabel: true },
            tooltip: { trigger: 'axis', valueFormatter: (value: any) => money(value) },
            xAxis: { type: 'category', data: ['销售额', '销售毛利', '经营收入', '经营费用', '净利润'], axisTick: { show: false }, axisLine: { lineStyle: { color: '#e2e8f0' } }, axisLabel: { color: '#64748b' } },
            yAxis: { type: 'value', axisLabel: { color: '#94a3b8', formatter: (value: number) => value >= 10000 ? `${(value / 10000).toFixed(1)}万` : value }, splitLine: { lineStyle: { color: '#eef2f7', type: 'dashed' } } },
            series: [{ type: 'bar', barMaxWidth: 38, data: values.map((value, index) => ({ value, itemStyle: { color: ['#3b82f6', '#10b981', '#06b6d4', '#f97316', '#8b5cf6'][index], borderRadius: value >= 0 ? [7, 7, 0, 0] : [0, 0, 7, 7] } })) }]
        }, true)
    }
    if (structureChartRef.value) {
        structureChart ||= echarts.init(structureChartRef.value)
        const source = [
            { name: '销售毛利', value: Math.max(0, Number(summary.value.profit_amount || 0)) },
            { name: '经营收入', value: Math.max(0, Number(summary.value.operating_income_amount || 0)) },
            { name: '经营费用', value: Math.max(0, Number(summary.value.operating_expense_amount || 0)) },
        ]
        const hasData = source.some(item => item.value > 0)
        structureChart.setOption({
            color: ['#10b981', '#3b82f6', '#f97316'],
            tooltip: { trigger: 'item', formatter: (item: any) => `${item.name}<br/>${money(item.value)} · ${item.percent}%` },
            legend: { bottom: 0, icon: 'circle', textStyle: { color: '#64748b' } },
            graphic: hasData ? [] : [{ type: 'text', left: 'center', top: 'middle', style: { text: '本期暂无构成数据', fill: '#94a3b8', fontSize: 14 } }],
            series: [{ type: 'pie', radius: ['46%', '68%'], center: ['50%', '43%'], avoidLabelOverlap: true, padAngle: 2, itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 3 }, label: { color: '#475569', formatter: '{b}\n{d}%' }, data: hasData ? source : [] }]
        }, true)
    }
}

const resizeCharts = () => { operationChart?.resize(); structureChart?.resize() }
window.addEventListener('resize', resizeCharts)
onBeforeUnmount(() => {
    window.removeEventListener('resize', resizeCharts)
    operationChart?.dispose()
    structureChart?.dispose()
})

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}

function settlementLabel(type: string) {
    return ({ receipt: '收款', payment: '付款', offset: '折账' } as Record<string, string>)[type] || type || '-'
}

function settlementType(type: string) {
    return ({ receipt: 'success', payment: 'warning', offset: 'primary' } as Record<string, string>)[type] || 'info'
}

function financeStatus(status: string, action: string) {
    return ({ settled: '已结清', partial: `部分${action}`, pending: `待${action}`, void: '已作废' } as Record<string, string>)[status] || status || '-'
}

function formatTime(value: any) {
    const timestamp = Number(value || 0)
    return timestamp ? new Date(timestamp * 1000).toLocaleString() : '-'
}
</script>

<style scoped>
.summary-tile {
    border-radius: 8px;
    background: #f8fafc;
    padding: 14px 16px;
}
.summary-label {
    color: #64748b;
    font-size: 13px;
}
.summary-value {
    margin-top: 6px;
    color: #111827;
    font-size: 22px;
    font-weight: 650;
}
.panel {
    border: 1px solid #eef2f7;
    border-radius: 8px;
    padding: 16px;
}
.panel-head {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}
.panel-title {
    color: #111827;
    font-size: 16px;
    font-weight: 650;
}
.operation-chart { width: 100%; height: 320px; }
.todo-grid {
    display: grid;
    gap: 12px;
}
.todo-item {
    align-items: center;
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 10px;
    color: #64748b;
}
.todo-item:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}
.todo-item strong {
    color: #111827;
}
</style>
