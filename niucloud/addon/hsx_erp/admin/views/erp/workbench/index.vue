<template>
    <ErpDesktopPage class="main-container">
        <section class="erp-workbench" v-loading="loading">
            <ErpWorkspaceHeader page="workbench" region-label="顶部经营指标" @change="resizeCharts">
                <template #title>经营工作台 <span class="period-caption">{{ periodLabel }}</span></template>
                <template #extra>
                    <el-button :icon="Setting" @click="openKpiConfig">绩效配置</el-button>
                    <el-tooltip content="刷新经营数据"><el-button :icon="Refresh" circle :loading="loading" aria-label="刷新经营数据" @click="loadDashboard" /></el-tooltip>
                </template>
            <div class="workbench-period">
                <el-radio-group v-model="period" @change="onPeriodChange">
                    <el-radio-button label="today">今日</el-radio-button>
                    <el-radio-button label="yesterday">昨天</el-radio-button>
                    <el-radio-button label="last7">近7天</el-radio-button>
                    <el-radio-button label="month">本月</el-radio-button>
                    <el-radio-button label="last_month">上月</el-radio-button>
                    <el-radio-button label="all">全部</el-radio-button>
                </el-radio-group>
                <el-date-picker v-model="customRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" clearable @change="onCustomRangeChange" />
                <span class="workbench-updated">{{ updatedAt ? '更新于 ' + updatedAt : '等待数据' }}</span>
            </div>
            <template v-if="hasLoaded">
                <div class="workbench-kpis">
                    <div v-for="item in primarySummaryCards" :key="item.label" class="workbench-kpi">
                        <span>{{ item.label }}</span>
                        <strong :class="item.className">{{ item.value }}</strong>
                        <small>{{ item.hint }}</small>
                    </div>
                </div>
                <div class="workbench-indicators">
                    <el-tooltip v-for="item in secondarySummaryCards" :key="item.label" :content="item.hint || item.label" placement="top">
                        <div class="workbench-indicator"><span>{{ item.label }}</span><strong :class="item.className">{{ item.value }}</strong></div>
                    </el-tooltip>
                </div>
            </template>
            </ErpWorkspaceHeader>
            <el-alert v-if="loadError" :title="loadError" type="error" :closable="false" show-icon class="workbench-error" />
            <template v-if="hasLoaded">
                <div class="workbench-analysis">
                    <section class="workbench-chart">
                        <header><h3>经营结构</h3><span>元 · {{ periodLabel }}</span></header>
                        <div ref="operationChartRef" class="operation-chart"></div>
                    </section>
                    <section class="workbench-chart">
                        <header><h3>利润构成</h3><el-tooltip content="仅展示正值构成，实际净利润以上方指标为准"><el-icon><InfoFilled /></el-icon></el-tooltip></header>
                        <div ref="structureChartRef" class="operation-chart"></div>
                    </section>
                    <section class="workbench-todos">
                        <header><h3>财务待办</h3><span>当前未结</span></header>
                        <button type="button" class="finance-todo" @click="router.push('/site/hsx_erp/payable')">
                            <span><i class="todo-dot todo-dot--pay"></i>待付款 <small>{{ todo.payable_count || 0 }} 笔</small></span>
                            <strong class="text-[color:var(--el-color-warning)]">{{ money(summary.payable_remain) }} <el-icon><ArrowRight /></el-icon></strong>
                        </button>
                        <button type="button" class="finance-todo" @click="router.push('/site/hsx_erp/receivable')">
                            <span><i class="todo-dot todo-dot--receive"></i>待收款 <small>{{ todo.receivable_count || 0 }} 笔</small></span>
                            <strong class="text-[color:var(--erp-text-accent)]">{{ money(summary.receivable_remain) }} <el-icon><ArrowRight /></el-icon></strong>
                        </button>
                        <div class="offset-todo"><span>可折账主体</span><strong>{{ todo.offset_party_count || 0 }} 个</strong></div>
                        <div v-if="refurbishReminder.visible" class="workbench-reminder">
                            <el-button link type="warning" @click="goRefurbishQueue">待整备 {{ refurbishReminder.pending_count }} 台 / 整备中 {{ refurbishReminder.processing_count }} 台</el-button>
                            <el-dropdown @command="dismissRefurbish" :disabled="reminderClosing.refurbish"><el-button :icon="Close" circle size="small" aria-label="关闭整备提醒" /><template #dropdown><el-dropdown-menu><el-dropdown-item command="today">今天不再提醒</el-dropdown-item><el-dropdown-item command="forever">永久关闭</el-dropdown-item></el-dropdown-menu></template></el-dropdown>
                        </div>
                        <div v-if="turnoverReminder.visible" class="workbench-reminder">
                            <el-button link type="warning" @click="goTurnoverQueue">周转预警 {{ turnoverReminder.warning_total_count }} 台 / 严重 {{ turnoverReminder.critical_count }} 台</el-button>
                            <el-dropdown @command="dismissTurnover" :disabled="reminderClosing.turnover"><el-button :icon="Close" circle size="small" aria-label="关闭周转提醒" /><template #dropdown><el-dropdown-menu><el-dropdown-item command="today">今天不再提醒</el-dropdown-item><el-dropdown-item command="forever">永久关闭</el-dropdown-item></el-dropdown-menu></template></el-dropdown>
                        </div>
                    </section>
                </div>
                <section ref="activitySection" class="workbench-activity">
                    <el-tabs v-model="activityTab">
                        <el-tab-pane label="最近结算" name="settlements" />
                        <el-tab-pane label="最近采购" name="purchases" />
                        <el-tab-pane label="最近销售" name="sales" />
                        <el-tab-pane label="员工绩效" name="staff" />
                    </el-tabs>
                    <el-table v-if="activityTab === 'settlements'" :data="recent.settlements || []" :max-height="activityHeight" empty-text="暂无结算记录">
                        <el-table-column prop="settlement_no" label="结算单号" min-width="170" />
                        <el-table-column prop="party_name" label="往来单位" min-width="140" />
                        <el-table-column label="类型" width="90"><template #default="{ row }"><el-tag size="small" :type="settlementType(row.settlement_type)">{{ settlementLabel(row.settlement_type) }}</el-tag></template></el-table-column>
                        <el-table-column label="金额" width="140" align="right"><template #default="{ row }">{{ money(row.amount) }}</template></el-table-column>
                        <el-table-column prop="capital_account_name" label="资金账户" min-width="130" />
                        <el-table-column label="确认时间" width="170"><template #default="{ row }">{{ formatTime(row.confirmed_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="100" fixed="right"><template #default="{ row }"><el-button link type="primary" :disabled="!row.target_id" @click="goSettlement(row)">查看账单</el-button></template></el-table-column>
                    </el-table>
                    <el-table v-else-if="activityTab === 'purchases'" :data="recent.purchases || []" :max-height="activityHeight" empty-text="暂无采购记录">
                        <el-table-column prop="purchase_no" label="采购单号" min-width="180" />
                        <el-table-column prop="party_name" label="采购渠道" min-width="150" />
                        <el-table-column label="金额" min-width="140" align="right"><template #default="{ row }">{{ money(row.total_cost) }}</template></el-table-column>
                        <el-table-column label="付款状态" width="120"><template #default="{ row }">{{ financeStatus(row.finance_status, '付款') }}</template></el-table-column>
                    </el-table>
                    <el-table v-else-if="activityTab === 'sales'" :data="recent.sales || []" :max-height="activityHeight" empty-text="暂无销售记录">
                        <el-table-column prop="sale_no" label="销售单号" min-width="180" />
                        <el-table-column prop="party_name" label="客户" min-width="150" />
                        <el-table-column label="销售额" min-width="130" align="right"><template #default="{ row }">{{ money(row.total_amount) }}</template></el-table-column>
                        <el-table-column label="毛利" min-width="130" align="right"><template #default="{ row }">{{ money(row.profit) }}</template></el-table-column>
                        <el-table-column label="收款状态" width="120"><template #default="{ row }">{{ financeStatus(row.finance_status, '收款') }}</template></el-table-column>
                    </el-table>
                    <el-table v-else :data="kpi.staff || []" :max-height="activityHeight" :empty-text="kpiUnavailable ? '绩效数据暂不可用，或当前账号无查看权限' : '本期暂无员工业务事实'">
                        <el-table-column type="expand"><template #default="{ row }"><div class="grid grid-cols-3 gap-3 p-3"><div v-for="item in row.details" :key="item.metric_key"><div>{{ item.metric_name }}</div><div class="text-xs text-[color:var(--el-text-color-secondary)]">实际 {{ item.actual }}{{ item.unit }} / 目标 {{ item.target }}{{ item.unit }}</div><el-progress :percentage="Math.min(100, Number(item.completion_rate || 0))" /></div></div></template></el-table-column>
                        <el-table-column type="index" label="排名" width="80" />
                        <el-table-column prop="name" label="员工" min-width="180" />
                        <el-table-column label="综合得分" min-width="240"><template #default="{ row }"><el-progress :percentage="Math.min(100, Number(row.score || 0))" :format="() => row.score + ' 分'" /></template></el-table-column>
                    </el-table>
                </section>
            </template>
            <HsxDialog class="erp-desktop-overlay" :confirm-loading="kpiSaving" v-model="kpiConfigVisible" title="员工绩效配置" width="680px" append-to-body>
                <HsxNotice default-expanded title="目标按当前看板统计周期计算；权重决定综合得分占比，超额完成最高计到该项 120%。" type="info" :closable="false" class="mb-4" />
                <el-table :data="kpiRules">
                    <el-table-column prop="metric_name" label="指标" min-width="140" />
                    <el-table-column label="周期目标" width="180"><template #default="{ row }"><el-input-number v-model="row.target_value" :min="0.01" :precision="2" /><span class="ml-1 text-xs text-[color:var(--el-text-color-secondary)]">{{ row.unit }}</span></template></el-table-column>
                    <el-table-column label="权重" width="160"><template #default="{ row }"><el-input-number v-model="row.weight" :min="0.01" :max="100" :precision="1" /><span class="ml-1 text-xs text-[color:var(--el-text-color-secondary)]">%</span></template></el-table-column>
                    <el-table-column label="启用" width="80"><template #default="{ row }"><el-switch v-model="row.enabled" :active-value="1" :inactive-value="0" /></template></el-table-column>
                </el-table>
                <template #footer><el-button :disabled="kpiSaving" @click="kpiConfigVisible=false">取消</el-button><el-button :disabled="kpiSaving" type="primary" :loading="kpiSaving" @click="saveKpiConfig">保存配置</el-button></template>
            </HsxDialog>
        </section>
    </ErpDesktopPage>
</template>

<script setup lang="ts">
import { erpEnumLabel } from '@/addon/hsx_erp/utils/display'
import { computed, nextTick, onActivated, onBeforeUnmount, onDeactivated, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import useSystemStore from '@/stores/modules/system'
import * as echarts from 'echarts'
import { Refresh, Setting, InfoFilled, ArrowRight, Close } from '@element-plus/icons-vue'
import { HsxDialog, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import ErpDesktopPage from '@/addon/hsx_erp/components/ErpDesktopPage.vue'
import ErpWorkspaceHeader from '@/addon/hsx_erp/components/ErpWorkspaceHeader.vue'
const feedback = useFeedback()
const reminderClosing = ref({ refurbish: false, turnover: false })
import { getErpDashboard, getErpKpiDashboard, getErpKpiRules, saveErpKpiRules } from '@/addon/hsx_erp/api/erp'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
import { dismissErpRefurbishReminder, dismissErpTurnoverReminder } from '@/addon/hsx_erp/api/config'

const loading = ref(false)
const hasLoaded = ref(false)
const loadError = ref('')
const updatedAt = ref('')
const activityTab = ref('settlements')
const activitySection = ref<HTMLElement>()
const activityHeight = ref(190)
const kpiUnavailable = ref(false)
const period = ref('month')
const customRange = ref<Date[]>([])
const router = useRouter()
const systemStore = useSystemStore()
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
let chartObserver: ResizeObserver | undefined
const summaryCards = computed(() => [
    { label: '采购单数', value: summary.value.purchase_count || 0, hint: money(summary.value.purchase_amount) },
    { label: '销售单数', value: summary.value.sale_count || 0, hint: money(summary.value.sale_amount) },
    { label: '销售毛利', value: money(summary.value.profit_amount), className: Number(summary.value.profit_amount || 0) >= 0 ? 'text-[color:var(--el-color-success)]' : 'text-[color:var(--el-color-danger)]' },
    { label: '经营费用', value: money(summary.value.operating_expense_amount), className: 'text-[color:var(--el-color-warning)]' },
    { label: '经营净利润', primary: true, value: money(summary.value.operating_profit_amount), hint: `其他经营收入 ${money(summary.value.operating_income_amount)}`, className: Number(summary.value.operating_profit_amount || 0) >= 0 ? 'text-[color:var(--el-color-success)]' : 'text-[color:var(--el-color-danger)]' },
    { label: '库存设备', primary: true, value: summary.value.stock_count || 0, hint: `库存成本 ${money(summary.value.stock_cost)}` },
    { label: '今日动销率', value: `${Number(summary.value.turnover_rate || 0).toFixed(2)}%`, hint: `今日售出 ${summary.value.today_sold_count || 0} 台 ÷ 零点库存 ${summary.value.opening_stock_count || 0} 台`, className: Number(summary.value.turnover_rate || 0) > 0 ? 'text-[color:var(--erp-text-accent)]' : '' },
    { label: '库存周转', primary: true, value: `${Number(summary.value.average_stock_age_days || 0).toFixed(1)} 天`, hint: `预警 ${summary.value.turnover_warning_count || 0} 台 · 占用 ${money(summary.value.turnover_warning_cost)}`, className: Number(summary.value.turnover_warning_count || 0) > 0 ? 'text-[color:var(--el-color-warning)]' : 'text-[color:var(--el-color-success)]' },
    { label: '期间收款', value: money(summary.value.receipt_amount), className: 'text-[color:var(--el-color-success)]' },
    { label: '期间付款', value: money(summary.value.payment_amount), className: 'text-[color:var(--el-color-warning)]' },
    { label: '期间折账', value: money(summary.value.offset_amount), className: 'text-[color:var(--erp-text-accent)]' },
    { label: '净现金流', primary: true, value: money(Number(summary.value.receipt_amount || 0) - Number(summary.value.payment_amount || 0)), hint: '期间收款 − 期间付款；不等于利润' },
])
const primarySummaryCards = computed(() => summaryCards.value.filter(item => item.primary))
const secondarySummaryCards = computed(() => summaryCards.value.filter(item => !item.primary))

useErpPageRefresh(loadDashboard)

async function loadDashboard() {
    const sequence = ++dashboardLoadSequence
    loading.value = true
    loadError.value = ''
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
        hasLoaded.value = true
        updatedAt.value = new Date().toLocaleTimeString('zh-CN', { hour12: false })
        // KPI 是扩展模块。没有绩效权限或配置异常时，不得拖垮老板工作台核心经营数据。
        kpi.value = kpiResult.status === 'fulfilled' ? ((kpiResult.value as any)?.data || {}) : {}
        kpiUnavailable.value = kpiResult.status === 'rejected'
        await nextTick()
        renderCharts()
        observeCharts()
    } catch (error: any) {
        if (sequence !== dashboardLoadSequence) return
        hasLoaded.value = false
        loadError.value = '经营数据加载失败，请刷新重试。'
        feedback.error(error?.message || error?.msg || '经营数据加载失败，请稍后重试')
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
    if (reminderClosing.value.refurbish) return
    if (mode === 'forever' && !await feedback.confirm({ title: '关闭整备积压提醒？', message: '只关闭提醒，不影响设备数据和待办。以后可在ERP业务规则中重新开启。', confirmText: '关闭此提醒' })) return
    reminderClosing.value.refurbish = true
    try {
        await dismissErpRefurbishReminder(mode)
        feedback.success(mode === 'forever' ? '已关闭整备积压提醒，可在业务规则中重新开启' : '今天不再提醒')
        await loadDashboard()
    } finally { reminderClosing.value.refurbish = false }
}

async function dismissTurnover(mode: 'today' | 'forever') {
    if (reminderClosing.value.turnover) return
    if (mode === 'forever' && !await feedback.confirm({ title: '关闭库存周转提醒？', message: '只关闭提醒，不影响设备数据和待办。以后可在ERP业务规则中重新开启。', confirmText: '关闭此提醒' })) return
    reminderClosing.value.turnover = true
    try {
        await dismissErpTurnoverReminder(mode)
        feedback.success(mode === 'forever' ? '已关闭库存周转提醒，可在业务规则中重新开启' : '今天不再提醒')
        await loadDashboard()
    } finally { reminderClosing.value.turnover = false }
}

async function openKpiConfig() {
    const res: any = await getErpKpiRules()
    kpiRules.value = (res?.data || []).map((row: any) => ({ ...row, target_value: Number(row.target_value), weight: Number(row.weight), enabled: Number(row.enabled) }))
    kpiConfigVisible.value = true
}

async function saveKpiConfig() {
    kpiSaving.value = true
    try { await saveErpKpiRules(kpiRules.value); feedback.success('绩效配置已保存'); kpiConfigVisible.value = false; await loadDashboard() }
    finally { kpiSaving.value = false }
}

function onPeriodChange() {
    customRange.value = []
    loadDashboard()
}

function onCustomRangeChange(value: Date[] | null) {
    period.value = value?.length === 2 ? 'custom' : 'month'
    loadDashboard()
}

function goSettlement(row: any) {
    if (!row?.target_type) return
    router.push({ path: `/site/hsx_erp/${row.target_type}`, query: row.target_source_no ? { source_no: row.target_source_no } : {} })
}

function renderCharts() {
    const style = getComputedStyle(document.documentElement)
    const color = (name: string) => style.getPropertyValue(`--el-${name}`).trim()
    const tooltip = { backgroundColor: color('bg-color-overlay'), borderColor: color('border-color'), textStyle: { color: color('text-color-primary') } }
    if (operationChartRef.value) {
        if (operationChart?.getDom() !== operationChartRef.value) { operationChart?.dispose(); operationChart = echarts.init(operationChartRef.value) }
        const values = [summary.value.sale_amount, summary.value.profit_amount, summary.value.operating_income_amount, summary.value.operating_expense_amount, summary.value.operating_profit_amount].map(value => Number(value || 0))
        operationChart.setOption({
            animation: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            animationDuration: 250,
            grid: { left: 18, right: 18, top: 26, bottom: 14, containLabel: true },
            tooltip: { ...tooltip, trigger: 'axis', valueFormatter: (value: any) => money(value) },
            xAxis: { type: 'category', data: ['销售额', '销售毛利', '经营收入', '经营费用', '净利润'], axisTick: { show: false }, axisLine: { lineStyle: { color: color('border-color') } }, axisLabel: { color: color('text-color-regular') } },
            yAxis: { type: 'value', axisLabel: { color: color('text-color-secondary'), formatter: (value: number) => value >= 10000 ? `${(value / 10000).toFixed(1)}万` : value }, splitLine: { lineStyle: { color: color('border-color-lighter'), type: 'dashed' } } },
            series: [{ type: 'bar', barMaxWidth: 38, data: values.map((value, index) => ({ value, itemStyle: { color: [color('color-primary'), color('color-success'), '#06b6d4', color('color-warning'), '#8b5cf6'][index], borderRadius: value >= 0 ? [7, 7, 0, 0] : [0, 0, 7, 7] } })) }]
        }, true)
    }
    if (structureChartRef.value) {
        if (structureChart?.getDom() !== structureChartRef.value) { structureChart?.dispose(); structureChart = echarts.init(structureChartRef.value) }
        const source = [
            { name: '销售毛利', value: Math.max(0, Number(summary.value.profit_amount || 0)) },
            { name: '经营收入', value: Math.max(0, Number(summary.value.operating_income_amount || 0)) },
            { name: '经营费用', value: Math.max(0, Number(summary.value.operating_expense_amount || 0)) },
        ]
        const hasData = source.some(item => item.value > 0)
        structureChart.setOption({
            animation: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            animationDuration: 250,
            color: [color('color-success'), color('color-primary'), color('color-warning')],
            tooltip: { ...tooltip, trigger: 'item', formatter: (item: any) => `${item.name}<br/>${money(item.value)} · ${item.percent}%` },
            legend: { show: hasData, bottom: 0, icon: 'circle', textStyle: { color: color('text-color-regular') } },
            graphic: hasData ? [] : [{ type: 'text', left: 'center', top: 'middle', style: { text: '本期暂无构成数据', fill: color('text-color-secondary'), fontSize: 14 } }],
            series: [{ type: 'pie', showEmptyCircle: false, radius: ['42%', '66%'], center: ['50%', '42%'], avoidLabelOverlap: true, padAngle: 2, itemStyle: { borderRadius: 4, borderColor: color('bg-color-overlay'), borderWidth: 2 }, label: { show: false }, data: hasData ? source : [] }]
        }, true)
    }
}

const resizeCharts = () => {
    operationChart?.resize()
    structureChart?.resize()
    if (activitySection.value) {
        let top = activitySection.value.getBoundingClientRect().top
        for (let parent = activitySection.value.parentElement; parent; parent = parent.parentElement) top += parent.scrollTop
        const tabsHeight = activitySection.value.querySelector('.el-tabs')?.getBoundingClientRect().height || 40
        activityHeight.value = Math.max(90, Math.floor(window.innerHeight - top - tabsHeight - 24))
    }
}
function observeCharts() {
    chartObserver?.disconnect()
    chartObserver = new ResizeObserver(resizeCharts)
    if (operationChartRef.value) chartObserver.observe(operationChartRef.value)
    if (structureChartRef.value) chartObserver.observe(structureChartRef.value)
    if (activitySection.value) chartObserver.observe(activitySection.value)
}
watch(() => [systemStore.dark, systemStore.theme], async () => { await nextTick(); if (hasLoaded.value) renderCharts() }, { flush: 'post' })
onActivated(() => { if (hasLoaded.value) renderCharts(); observeCharts(); resizeCharts() })
onDeactivated(() => chartObserver?.disconnect())
onBeforeUnmount(() => {
    chartObserver?.disconnect()
    operationChart?.dispose()
    structureChart?.dispose()
})

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}

function settlementLabel(type: string) {
    return erpEnumLabel(type, { receipt: '收款', payment: '付款', offset: '折账' }, '其他结算')
}

function settlementType(type: string) {
    return ({ receipt: 'success', payment: 'warning', offset: 'primary' } as Record<string, string>)[type] || 'info'
}

function financeStatus(status: string, action: string) {
    return erpEnumLabel(status, { settled: '已结清', partial: `部分${action}`, pending: `待${action}`, void: '已作废' })
}

function formatTime(value: any) {
    const timestamp = Number(value || 0)
    return timestamp ? new Date(timestamp * 1000).toLocaleString() : '-'
}
</script>

<style scoped>
.period-caption { margin-left: 8px; font-size: 12px; font-weight: 400; color: var(--el-text-color-secondary); vertical-align: middle; }
.workbench-period { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.workbench-period :deep(.el-date-editor) { width: 260px; flex: 0 0 260px; }
.workbench-updated { margin-left: auto; font-size: 12px; color: var(--el-text-color-secondary); }
.workbench-error { margin-block: 12px; }
.workbench-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.workbench-kpi { min-width: 0; display: flex; flex-direction: column; padding: 10px 14px; border: 1px solid var(--el-border-color); border-radius: 6px; background: var(--el-bg-color-overlay); }
.workbench-kpi > span { color: var(--el-text-color-secondary); font-size: 12px; }
.workbench-kpi > strong { margin-top: 3px; font-size: 24px; line-height: 30px; font-weight: 650; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.workbench-kpi > small { margin-top: 3px; color: var(--el-text-color-secondary); font-size: 11px; line-height: 16px; }
.workbench-indicators { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 8px; padding: 12px 0; border-bottom: 1px solid var(--el-border-color); }
.workbench-indicator { min-width: 0; display: flex; flex-direction: column; gap: 4px; padding-left: 10px; border-left: 1px solid var(--el-border-color); }
.workbench-indicator:first-child { border: 0; padding-left: 0; }
.workbench-indicator span { font-size: 12px; color: var(--el-text-color-secondary); }
.workbench-indicator strong { font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.workbench-analysis { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(260px, 1fr); gap: 18px; margin-top: 12px; }
.workbench-analysis > section { min-width: 0; }
.workbench-analysis header { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 24px; color: var(--el-text-color-secondary); font-size: 12px; }
.workbench-analysis h3 { margin: 0; color: var(--el-text-color-primary); font-size: 14px; font-weight: 600; }
.operation-chart { width: 100%; height: 170px; }
.workbench-todos { border-left: 1px solid var(--el-border-color); padding-left: 18px; }
.finance-todo { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 0; border: 0; border-bottom: 1px solid var(--el-border-color-lighter); background: none; font-size: 13px; text-align: left; cursor: pointer; transition: background-color .16s; }
.finance-todo:hover { background: var(--el-fill-color-light); }
.finance-todo > span { display: flex; align-items: center; gap: 6px; color: var(--el-text-color-regular); }
.finance-todo small { color: var(--el-text-color-secondary); font-size: 12px; }
.finance-todo strong { display: flex; align-items: center; gap: 4px; font-weight: 600; font-variant-numeric: tabular-nums; }
.todo-dot { width: 6px; height: 6px; border-radius: 50%; flex: none; }
.todo-dot--pay { background: var(--el-color-warning); }
.todo-dot--receive { background: var(--el-color-primary); }
.offset-todo { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; color: var(--el-text-color-secondary); font-size: 12px; }
.offset-todo strong { color: var(--el-text-color-primary); font-weight: 500; }
.workbench-reminder { display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-top: 4px; }
.workbench-reminder :deep(.el-button.is-link) { white-space: normal; text-align: left; font-size: 12px; }
.workbench-activity { border-top: 1px solid var(--el-border-color); margin-top: 10px; }
.workbench-activity :deep(.el-tabs__header) { margin-bottom: 4px; }
.workbench-activity :deep(.el-tabs__item) { height: 36px; font-size: 13px; }
.workbench-activity :deep(.el-table__cell) { padding: 6px 0; }
.workbench-activity :deep(.el-table__header th) { font-weight: 500; background: var(--el-fill-color-light); }
.workbench-activity :deep(.cell) { font-size: 13px; }
.workbench-activity :deep(.el-table__empty-block) { min-height: 70px; }
@media (max-width: 1100px) {
    .workbench-analysis { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 12px; }
    .workbench-todos { grid-column: 1 / -1; display: grid; grid-template-columns: 110px 1fr 1fr; gap: 0 16px; padding-left: 0; border-left: 0; border-top: 1px solid var(--el-border-color); }
    .workbench-todos header { flex-direction: column; align-items: flex-start; justify-content: center; gap: 2px; }
    .offset-todo { grid-column: 2 / -1; }
    .workbench-indicators { grid-template-columns: repeat(4, minmax(0, 1fr)); row-gap: 10px; }
    .workbench-indicator:nth-child(5) { border: 0; padding-left: 0; }
    .workbench-kpi { padding: 10px; }
    .workbench-kpi > strong { font-size: 21px; }
}
@media (min-width: 1600px) and (min-height: 850px) {
    .operation-chart { height: 230px; }
    .workbench-kpi { padding: 14px 16px; }
}
@media (min-width: 1101px) and (max-height: 800px) {
    .operation-chart { height: 140px; }
    .workbench-kpi { padding-block: 8px; }
}
</style>
