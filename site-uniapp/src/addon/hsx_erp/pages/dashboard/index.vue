<template>
    <view class="erp-page dashboard-page">
        <view class="dashboard-head">
            <view class="head-title">
                <text class="dash-title">ERP 经营看板</text>
                <text class="dash-sub">录入、库存、应收应付和折账统一入口</text>
            </view>
            <view>
            <u-button size="mini" type="primary" plain :loading="loading" @click="loadData">
                刷新
            </u-button>
            </view>
        </view>

        <view v-if="refurbishReminder.visible" class="refurbish-reminder">
            <view class="refurbish-reminder__head"><text>整备积压提醒</text><view @click="showReminderActions"><u-icon name="close" color="#92400e" size="18" /></view></view>
            <text class="refurbish-reminder__title">今日新增 {{ refurbishReminder.today_count }} 台待整备</text>
            <text class="refurbish-reminder__desc">当前待整备 {{ refurbishReminder.pending_count }} 台，整备中 {{ refurbishReminder.processing_count }} 台。请及时完成分配，避免影响动销。</text>
            <u-button size="small" type="warning" plain @click="go('/addon/hsx_erp/pages/stock/list?refurbish_status=pending')">查看整备设备</u-button>
        </view>

        <view v-if="turnoverReminder.visible" class="turnover-reminder">
            <view class="refurbish-reminder__head"><text>库存周转预警</text><view @click="showTurnoverReminderActions"><u-icon name="close" color="#991b1b" size="18" /></view></view>
            <text class="refurbish-reminder__title">{{ turnoverReminder.warning_total_count }} 台设备周转过慢</text>
            <text class="refurbish-reminder__desc">严重滞销 {{ turnoverReminder.critical_count }} 台，占用成本 ¥{{ money(turnoverReminder.warning_total_cost) }}，平均库龄 {{ turnoverReminder.average_age_days }} 天。</text>
            <u-button size="small" type="error" plain @click="go('/addon/hsx_erp/pages/stock/list?turnover_level=risk')">查看预警库存</u-button>
        </view>

        <view class="period-card">
            <u-tabs
                :list="periodTabs"
                :current="periodIndex"
                lineWidth="24"
                lineHeight="4"
                :activeStyle="tabActiveStyle"
                :inactiveStyle="tabInactiveStyle"
                @change="onPeriodChange"
            />
            <view v-if="period === 'custom'" class="custom-range" @click="calendarShow = true">
                <u-icon name="calendar" color="#3b6ef5" size="18" />
                <text>{{ customRangeText || '选择开始与结束日期' }}</text>
                <u-icon name="arrow-right" color="#94a3b8" size="15" />
            </view>
        </view>

        <view class="quick-card">
            <u-grid :border="false" col="4">
                <u-grid-item v-for="item in quickActions" :key="item.path" @click="go(item.path)">
                    <view class="quick-item">
                        <view class="quick-icon" :class="item.tone">
                            <text>{{ item.icon }}</text>
                        </view>
                        <text class="quick-label">{{ item.label }}</text>
                    </view>
                </u-grid-item>
            </u-grid>
        </view>

        <view class="overview-card">
            <view class="overview-main">
                <view>
                    <text class="metric-label">有效销售额</text>
                    <text class="overview-value">¥{{ money(summary.sale_amount) }}</text>
                    <text class="metric-sub">毛利 ¥{{ money(summary.profit_amount) }}</text>
                    <text class="metric-sub">扣经营费用后 ¥{{ money(summary.operating_profit_amount) }}</text>
                </view>
                <u-tag text="本期" type="primary" plain plainFill size="mini" />
            </view>
            <view class="overview-foot">
                <view class="amount-box">
                    <text class="amt-label">有效采购成本</text>
                    <text class="amt-value">¥{{ money(summary.purchase_amount) }}</text>
                    <text class="metric-sub">{{ summary.purchase_count || 0 }} 笔</text>
                </view>
                <view class="amount-box">
                    <text class="amt-label">库存成本</text>
                    <text class="amt-value blue">¥{{ money(summary.stock_cost) }}</text>
                    <text class="metric-sub">{{ summary.stock_count || 0 }} 台</text>
                </view>
                <view class="amount-box">
                    <text class="amt-label">折账金额</text>
                    <text class="amt-value orange">¥{{ money(summary.offset_amount) }}</text>
                    <text class="metric-sub">{{ todo.offset_party_count || 0 }} 个主体</text>
                </view>
            </view>
            <view class="turnover-strip">
                <view><text>今日动销率</text><strong>{{ Number(summary.turnover_rate || 0).toFixed(2) }}%</strong></view>
                <text>今日有效售出 {{ summary.today_sold_count || 0 }} 台 ÷ 今日零点库存 {{ summary.opening_stock_count || 0 }} 台</text>
            </view>
            <view class="turnover-strip stock-age-strip">
                <view><text>平均库龄</text><strong>{{ Number(summary.average_stock_age_days || 0).toFixed(1) }} 天</strong></view>
                <text>周转预警 {{ summary.turnover_warning_count || 0 }} 台 · 占用成本 ¥{{ money(summary.turnover_warning_cost) }}</text>
            </view>
        </view>

        <view class="chart-card">
            <view class="chart-head">
                <view><text class="chart-title">经营结构</text><text class="chart-sub">销售、经营收支与利润对比</text></view>
                <text class="chart-period">{{ periods[periodIndex]?.label }}</text>
            </view>
            <view class="chart-render">
                <qiun-data-charts v-if="dashboardReady && operationChartHasData" :key="`operation-${chartVersion}`" type="column" :chartData="operationChartData" :opts="operationChartOpts" :canvas2d="true" :canvasId="`erpOperationChart${chartVersion}`" :ontouch="true" />
                <view v-else-if="dashboardReady" class="chart-empty">本期暂无经营数据</view>
                <u-loading-icon v-else mode="circle" text="经营数据加载中" />
            </view>
        </view>

        <view class="kpi-card">
            <view class="chart-head"><view><text class="chart-title">员工绩效</text><text class="chart-sub">按已配置目标，由真实采购、销售、质检和财务事实计算</text></view><text class="chart-period">{{ periods[periodIndex]?.label }}</text></view>
            <view v-for="(staff, index) in (kpi.staff || []).slice(0, 5)" :key="staff.uid" class="kpi-row">
                <text class="kpi-rank">{{ index + 1 }}</text>
                <view class="kpi-main"><view class="kpi-name"><text>{{ staff.name }}</text><text>{{ staff.score }} 分</text></view><u-line-progress :percentage="Math.min(100, Number(staff.score || 0))" :showText="false" activeColor="#3b6ef5" height="7" /></view>
            </view>
            <u-empty v-if="dashboardReady && !(kpi.staff || []).length" mode="data" text="本期暂无员工业务事实" :image-size="56" />
            <u-loading-icon v-else-if="!dashboardReady" mode="circle" text="绩效数据加载中" />
        </view>

        <view class="chart-card">
            <view class="chart-head">
                <view><text class="chart-title">利润构成</text><text class="chart-sub">看清利润从哪里来、花到哪里去</text></view>
            </view>
            <view class="chart-render chart-render--ring">
                <qiun-data-charts v-if="dashboardReady && profitStructureHasData" :key="`profit-${chartVersion}`" type="ring" :chartData="profitStructureData" :opts="profitStructureOpts" :canvas2d="true" :canvasId="`erpProfitStructure${chartVersion}`" :ontouch="true" />
                <view v-else-if="dashboardReady" class="chart-empty">本期暂无利润构成数据</view>
                <u-loading-icon v-else mode="circle" text="利润数据加载中" />
            </view>
        </view>

        <view class="finance-card">
            <u-cell-group :border="false">
                <u-cell title="待付款" :label="`${todo.payable_count || 0} 笔`" isLink @click="go('/addon/hsx_erp/pages/payable/list')">
                    <template #value>
                        <text class="cell-amount red">¥{{ money(summary.payable_remain) }}</text>
                    </template>
                </u-cell>
                <u-cell title="待收款" :label="`${todo.receivable_count || 0} 笔`" isLink @click="go('/addon/hsx_erp/pages/receivable/list')">
                    <template #value>
                        <text class="cell-amount blue">¥{{ money(summary.receivable_remain) }}</text>
                    </template>
                </u-cell>
                <u-cell title="已收款" label="本期">
                    <template #value>
                        <text class="cell-amount green">¥{{ money(summary.receipt_amount) }}</text>
                    </template>
                </u-cell>
                <u-cell title="已付款" label="本期">
                    <template #value>
                        <text class="cell-amount orange">¥{{ money(summary.payment_amount) }}</text>
                    </template>
                </u-cell>
                <u-cell title="经营费用" label="房租、水电、办公等" isLink @click="go('/addon/hsx_erp/pages/operating_finance/list')">
                    <template #value><text class="cell-amount orange">¥{{ money(summary.operating_expense_amount) }}</text></template>
                </u-cell>
                <u-cell title="经营净利润" label="销售毛利 + 经营收入 - 经营费用">
                    <template #value><text class="cell-amount" :class="Number(summary.operating_profit_amount || 0)>=0?'green':'red'">{{ signedMoney(summary.operating_profit_amount) }}</text></template>
                </u-cell>
                <u-cell title="净现金流" label="本期实际收款 - 实际付款" :border="false">
                    <template #value>
                        <text class="cell-amount" :class="netCashFlowClass">{{ signedMoney(netCashFlow) }}</text>
                    </template>
                </u-cell>
            </u-cell-group>
        </view>

        <view class="section-title">最近结算</view>
        <view class="erp-card settlement-card" v-for="row in recent.settlements" :key="'s'+row.id" @click="goSettlement(row)">
            <view class="erp-card__head">
                <text class="card-title">{{ erpPartyDisplayName(row) }}</text>
                <u-tag :text="settlementLabel(row.settlement_type)" :type="settlementType(row.settlement_type)" plain plainFill size="mini" />
            </view>
            <view class="card-meta">{{ row.settlement_no }} · {{ row.capital_account_name || '无资金账户' }}</view>
            <view class="compact-foot">
                <text class="compact-amount">¥{{ money(row.amount) }}</text>
                <text class="card-time">{{ time(row.confirmed_at) }} · 查看账单</text>
            </view>
        </view>
        <view v-if="loading" class="loading-row">
            <u-loading-icon mode="circle" text="加载中" />
        </view>
        <u-empty v-else-if="!(recent.settlements || []).length" mode="list" text="暂无结算记录" />

        <view class="section-title">最近采购</view>
        <view v-for="row in (recent.purchases || [])" :key="'p'+row.id" class="erp-card recent-order" @click="goPurchase(row)">
            <view class="erp-card__head">
                <view class="recent-order__main"><text class="card-title">{{ erpPartyDisplayName(row, '未填写供应商') }}</text><text class="card-meta">{{ row.purchase_no || '-' }}</text></view>
                <u-tag :text="financeStatus(row.finance_status, '付款')" :type="financeStatusType(row.finance_status)" plain plainFill size="mini" />
            </view>
            <view class="compact-foot"><text class="compact-amount">¥{{ money(row.total_cost) }}</text><text class="card-time">{{ time(row.purchase_at) }} · 查看采购单</text></view>
        </view>
        <u-empty v-if="dashboardReady && !(recent.purchases || []).length" mode="list" text="暂无采购记录" />

        <view class="section-title">最近销售</view>
        <view v-for="row in (recent.sales || [])" :key="'sale'+row.id" class="erp-card recent-order" @click="goSale(row)">
            <view class="erp-card__head">
                <view class="recent-order__main"><text class="card-title">{{ erpPartyDisplayName(row, '未填写客户') }}</text><text class="card-meta">{{ row.sale_no || '-' }}</text></view>
                <u-tag :text="financeStatus(row.finance_status, '收款')" :type="financeStatusType(row.finance_status)" plain plainFill size="mini" />
            </view>
            <view class="recent-sale-metrics"><text>销售 ¥{{ money(row.total_amount) }}</text><text :class="Number(row.profit || 0) >= 0 ? 'green' : 'red'">毛利 {{ signedMoney(row.profit) }}</text></view>
            <view class="card-time">{{ time(row.sale_at) }} · 查看销售单</view>
        </view>
        <u-empty v-if="dashboardReady && !(recent.sales || []).length" mode="list" text="暂无销售记录" />
        <u-calendar :show="calendarShow" mode="range" title="选择统计日期" :defaultDate="customDates" :monthNum="12" @confirm="onCalendarConfirm" @close="calendarShow = false" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { dismissMobileErpRefurbishReminder, dismissMobileErpTurnoverReminder, getMobileErpDashboard, getMobileErpKpiDashboard } from '@/addon/hsx_erp/api/erp'
import qiunDataCharts from '@/addon/hsx_erp/components/qiun-data-charts/components/qiun-data-charts/qiun-data-charts.vue'
import { erpPartyDisplayName } from '@/addon/hsx_erp/hooks/useErpPartyText'

const loading = ref(false)
const period = ref('month')
const calendarShow = ref(false)
const customDates = ref<string[]>([])
const data = ref<any>({})
const kpi = ref<any>({})
const dashboardReady = ref(false)
const chartVersion = ref(0)
const periods = [
    { label: '今日', value: 'today' },
    { label: '昨天', value: 'yesterday' },
    { label: '近7天', value: 'last7' },
    { label: '本月', value: 'month' },
    { label: '上月', value: 'last_month' },
    { label: '自定义', value: 'custom' },
    { label: '全部', value: 'all' },
]
const periodTabs = periods.map(item => ({ name: item.label, value: item.value }))
const periodIndex = computed(() => Math.max(periods.findIndex(item => item.value === period.value), 0))
const customRangeText = computed(() => customDates.value.length ? `${customDates.value[0]} 至 ${customDates.value[customDates.value.length - 1]}` : '')
const tabActiveStyle = { color: '#3b6ef5', fontWeight: '700', fontSize: '28rpx' }
const tabInactiveStyle = { color: '#64748b', fontSize: '28rpx' }
const quickActions = [
    { label: '采购录入', icon: '采', tone: 'blue', path: '/addon/hsx_erp/pages/purchase/create' },
    { label: '销售出库', icon: '销', tone: 'green', path: '/addon/hsx_erp/pages/sale/create' },
    { label: '库存设备', icon: '库', tone: 'purple', path: '/addon/hsx_erp/pages/stock/list' },
    { label: '成本调整', icon: '调', tone: 'orange', path: '/addon/hsx_erp/pages/cost_adjust/list' },
    { label: '应付款', icon: '付', tone: 'red', path: '/addon/hsx_erp/pages/payable/list' },
    { label: '应收款', icon: '收', tone: 'cyan', path: '/addon/hsx_erp/pages/receivable/list' },
    { label: '经营收支', icon: '营', tone: 'orange', path: '/addon/hsx_erp/pages/operating_finance/list' },
    { label: '采购退货', icon: '退', tone: 'gray', path: '/addon/hsx_erp/pages/purchase_return/list' },
    { label: '销售退货', icon: '返', tone: 'gray', path: '/addon/hsx_erp/pages/sale_return/list' },
    { label: '串号追踪', icon: '码', tone: 'purple', path: '/addon/hsx_erp/pages/serial_trace/list' },
]

const summary = computed(() => data.value?.summary || {})
const todo = computed(() => data.value?.todo || {})
const recent = computed(() => data.value?.recent || {})
const refurbishReminder = computed(() => data.value?.reminders?.refurbish || {})
const turnoverReminder = computed(() => data.value?.reminders?.turnover || {})
const netCashFlow = computed(() => Number(summary.value.receipt_amount || 0) - Number(summary.value.payment_amount || 0))
const netCashFlowClass = computed(() => netCashFlow.value > 0 ? 'green' : (netCashFlow.value < 0 ? 'red' : ''))
const operationChartData = computed(() => ({
    categories: ['销售额', '销售毛利', '经营收入', '经营费用', '净利润'],
    series: [{ name: '金额', data: [
        Number(summary.value.sale_amount || 0),
        Number(summary.value.profit_amount || 0),
        Number(summary.value.operating_income_amount || 0),
        Number(summary.value.operating_expense_amount || 0),
        Number(summary.value.operating_profit_amount || 0),
    ] }]
}))
const operationChartHasData = computed(() => operationChartData.value.series[0].data.some(item => Number(item || 0) !== 0))
const profitStructureData = computed(() => ({ series: [{ data: [
    { name: '销售毛利', value: Math.max(0, Number(summary.value.profit_amount || 0)) },
    { name: '经营收入', value: Math.max(0, Number(summary.value.operating_income_amount || 0)) },
    { name: '经营费用', value: Math.max(0, Number(summary.value.operating_expense_amount || 0)) },
] }] }))
const profitStructureHasData = computed(() => profitStructureData.value.series[0].data.some(item => Number(item.value || 0) > 0))
const operationChartOpts = {
    color: ['#3b82f6'], padding: [12, 10, 8, 10], legend: { show: false }, dataLabel: false,
    xAxis: { disableGrid: true, fontColor: '#64748b', rotateLabel: true },
    yAxis: { gridType: 'dash', dashLength: 4, data: [{ fontColor: '#94a3b8' }] },
    extra: { column: { type: 'group', width: 18, activeBgColor: '#eff6ff', linearType: 'custom', customColor: ['#3b82f6', '#10b981', '#06b6d4', '#f97316', '#8b5cf6'] } }
}
const profitStructureOpts = {
    color: ['#10b981', '#3b82f6', '#f97316'], padding: [8, 8, 8, 8], dataLabel: true,
    legend: { show: true, position: 'bottom', fontColor: '#64748b' },
    extra: { ring: { ringWidth: 24, activeOpacity: 0.7, activeRadius: 8, labelWidth: 12 } }
}

onShow(loadData)
let loadSequence = 0

function switchPeriod(value: string) {
    period.value = value
    if (value === 'custom') { calendarShow.value = true; return }
    loadData()
}

function onPeriodChange(item: any) {
    const index = typeof item === 'number' ? item : Number(item?.index ?? 0)
    const value = item?.value || periods[index]?.value
    if (value && value !== period.value) switchPeriod(value)
}

function onCalendarConfirm(selected: string[]) {
    const dates = (selected || []).filter(Boolean)
    calendarShow.value = false
    if (!dates.length) return
    customDates.value = [dates[0], dates[dates.length - 1]]
    period.value = 'custom'
    loadData()
}

function dateTimestamp(value: string, end = false) {
    const [y, m, d] = value.split('-').map(Number)
    return Math.floor(new Date(y, m - 1, d, end ? 23 : 0, end ? 59 : 0, end ? 59 : 0).getTime() / 1000)
}

async function loadData() {
    const sequence = ++loadSequence
    loading.value = true
    try {
        const params: any = { period: period.value }
        if (period.value === 'custom' && customDates.value.length) {
            params.start_at = dateTimestamp(customDates.value[0])
            params.end_at = dateTimestamp(customDates.value[customDates.value.length - 1], true)
        }
        const [dashboardResult, kpiResult] = await Promise.allSettled([getMobileErpDashboard(params), getMobileErpKpiDashboard(params)])
        if (sequence !== loadSequence) return
        if (dashboardResult.status === 'rejected') throw dashboardResult.reason
        data.value = (dashboardResult.value as any)?.data || {}
        // 绩效是可选权限，失败时只隐藏绩效，不影响采购、销售、库存和财务首页。
        kpi.value = kpiResult.status === 'fulfilled' ? ((kpiResult.value as any)?.data || {}) : {}
        dashboardReady.value = true
        chartVersion.value += 1
    } catch (e: any) {
        if (sequence !== loadSequence) return
        const message = String(e?.message || e?.msg || '')
        // 登录拦截由框架统一跳转，避免在登录页残留“经营数据加载失败”的误导提示。
        if (!/MUST_LOGIN|未登录|请登录|登录失效/i.test(message)) {
            uni.showToast({ title: message || '经营数据加载失败', icon: 'none' })
        }
        data.value = {}
        kpi.value = {}
        dashboardReady.value = true
        chartVersion.value += 1
    } finally {
        if (sequence === loadSequence) loading.value = false
    }
}

function go(path: string) {
    uni.navigateTo({ url: path })
}

function showReminderActions() {
    uni.showActionSheet({
        itemList: ['今天不再提醒', '永久关闭此提醒'],
        success: async ({ tapIndex }) => {
            await dismissMobileErpRefurbishReminder(tapIndex === 1 ? 'forever' : 'today')
            uni.showToast({ title: tapIndex === 1 ? '已永久关闭，可在设置中重新开启' : '今天不再提醒', icon: 'none' })
            await loadData()
        }
    })
}

function showTurnoverReminderActions() {
    uni.showActionSheet({
        itemList: ['今天不再提醒', '永久关闭此提醒'],
        success: async ({ tapIndex }) => {
            await dismissMobileErpTurnoverReminder(tapIndex === 1 ? 'forever' : 'today')
            uni.showToast({ title: tapIndex === 1 ? '已永久关闭，可在设置中重新开启' : '今天不再提醒', icon: 'none' })
            await loadData()
        }
    })
}

function goSettlement(row: any) {
    const id = Number(row?.target_id || 0)
    if (!id || !['payable', 'receivable'].includes(String(row?.target_type || ''))) {
        uni.showToast({ title: '该结算关联多笔账单，请到应收应付查看', icon: 'none' })
        return
    }
    go(`/addon/hsx_erp/pages/${row.target_type}/detail?id=${id}`)
}

function goPurchase(row: any) {
    go(`/addon/hsx_erp/pages/purchase/detail?purchase_order_id=${Number(row?.id || 0)}&purchase_no=${encodeURIComponent(String(row?.purchase_no || ''))}`)
}

function goSale(row: any) {
    go(`/addon/hsx_erp/pages/sale/detail?sale_order_id=${Number(row?.id || 0)}&sale_no=${encodeURIComponent(String(row?.sale_no || ''))}`)
}

const money = (v: any) => Number(v || 0).toFixed(2)
const signedMoney = (v: any) => `${Number(v || 0) > 0 ? '+' : ''}¥${money(v)}`
const settlementLabel = (s: string) => ({ receipt: '收款', payment: '付款', offset: '折账' }[s] || '状态待确认')
const settlementType = (s: string) => ({ receipt: 'success', payment: 'warning', offset: 'error' }[s] || 'info')
const financeStatus = (status: string, action: string) => ({ settled: '已结清', partial: `部分${action}`, pending: `待${action}`, void: '已作废' }[status] || '状态待确认')
const financeStatusType = (status: string) => ({ settled: 'success', partial: 'warning', pending: 'warning', void: 'info' }[status] || 'info')
const time = (v: any) => {
    const ts = Number(v || 0)
    if (!ts) return '-'
    const d = new Date(ts * 1000)
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const h = String(d.getHours()).padStart(2, '0')
    const min = String(d.getMinutes()).padStart(2, '0')
    return `${m}-${day} ${h}:${min}`
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.dashboard-page {
    padding-bottom: 40rpx;
}
.refurbish-reminder { margin:0 24rpx 20rpx; padding:24rpx; border:1rpx solid #fed7aa; border-radius:24rpx; background:#fff7ed; }
.turnover-reminder { margin:0 24rpx 20rpx; padding:24rpx; border:1rpx solid #fecaca; border-radius:24rpx; background:#fef2f2; }
.refurbish-reminder__head { display:flex; align-items:center; justify-content:space-between; color:#92400e; font-size:23rpx; font-weight:700; }
.refurbish-reminder__title { display:block; margin-top:12rpx; color:#9a3412; font-size:30rpx; font-weight:800; }
.refurbish-reminder__desc { display:block; margin:10rpx 0 18rpx; color:#9a3412; font-size:23rpx; line-height:1.6; }
.dashboard-head {
    background: #fff;
    padding: 34rpx 28rpx 18rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}
.head-title {
    flex: 1;
    min-width: 0;
}
.dash-title {
    display: block;
    font-size: 38rpx;
    font-weight: 800;
    color: #0f172a;
}
.dash-sub {
    display: block;
    margin-top: 8rpx;
    font-size: 24rpx;
    color: #64748b;
}
.period-card,
.quick-card,
.overview-card,
.finance-card {
    background: #fff;
    border-radius: 28rpx;
    margin: 0 24rpx 20rpx;
    box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);
    overflow: hidden;
}
.chart-card {
    margin: 0 24rpx 20rpx;
    padding: 26rpx 24rpx 20rpx;
    border-radius: 28rpx;
    background: #fff;
    box-shadow: 0 2rpx 12rpx rgba(0,0,0,.04);
}
.kpi-card { margin:0 24rpx 20rpx; padding:26rpx 24rpx; border-radius:28rpx; background:#fff; box-shadow:0 2rpx 12rpx rgba(0,0,0,.04); }
.kpi-row { display:flex; align-items:center; gap:16rpx; padding:20rpx 0; border-bottom:1rpx solid #f1f5f9; }
.kpi-row:last-child { border-bottom:0; }
.kpi-rank { display:flex; width:44rpx; height:44rpx; align-items:center; justify-content:center; border-radius:14rpx; background:#eff6ff; color:#2563eb; font-size:22rpx; font-weight:800; }
.kpi-main { flex:1; min-width:0; }
.kpi-name { display:flex; justify-content:space-between; margin-bottom:10rpx; color:#334155; font-size:23rpx; }
.kpi-name text:last-child { color:#2563eb; font-weight:700; }
.chart-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16rpx; }
.chart-title { display:block; color:#0f172a; font-size:30rpx; font-weight:750; }
.chart-sub { display:block; margin-top:6rpx; color:#94a3b8; font-size:21rpx; }
.chart-period { padding:7rpx 14rpx; border-radius:999rpx; color:#2563eb; background:#eff6ff; font-size:21rpx; }
.chart-render { height:390rpx; margin-top:20rpx; }
.chart-render--ring { height:410rpx; }
.chart-empty { display:flex; height:100%; align-items:center; justify-content:center; color:#94a3b8; font-size:24rpx; }
.period-card {
    margin-top: 18rpx;
    padding: 4rpx 16rpx;
}
.custom-range { display:flex; align-items:center; gap:10rpx; margin:8rpx 8rpx 14rpx; padding:16rpx 18rpx; border-radius:14rpx; background:#eff6ff; color:#2563eb; font-size:23rpx; }
.custom-range text { flex:1; }
.quick-card {
    padding: 18rpx 0 14rpx;
}
.quick-item {
    min-height: 126rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10rpx;
}
.quick-icon {
    width: 72rpx;
    height: 72rpx;
    border-radius: 22rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 28rpx;
    font-weight: 800;
}
.quick-icon.blue { background: #2563eb; }
.quick-icon.green { background: #16a34a; }
.quick-icon.purple { background: #7c3aed; }
.quick-icon.orange { background: #ea580c; }
.quick-icon.red { background: #dc2626; }
.quick-icon.cyan { background: #0891b2; }
.quick-icon.gray { background: #64748b; }
.quick-label {
    font-size: 23rpx;
    color: #334155;
}
.overview-card {
    padding: 26rpx 28rpx 22rpx;
}
.overview-main {
    display: flex;
    justify-content: space-between;
    gap: 20rpx;
}
.metric-label {
    display: block;
    font-size: 23rpx;
    color: #64748b;
}
.overview-value {
    display: block;
    margin-top: 8rpx;
    font-size: 42rpx;
    font-weight: 800;
    color: #0f172a;
}
.metric-sub {
    display: block;
    margin-top: 6rpx;
    font-size: 22rpx;
    color: #94a3b8;
}
.overview-foot {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24rpx;
    margin-top: 22rpx;
    padding-top: 20rpx;
    border-top: 2rpx solid #f3f4f6;
}
.turnover-strip { display:flex; align-items:center; justify-content:space-between; gap:20rpx; margin-top:20rpx; padding:18rpx 20rpx; border-radius:16rpx; background:linear-gradient(135deg,#eff6ff,#f0fdfa); }
.turnover-strip > view { display:flex; flex-direction:column; gap:5rpx; color:#64748b; font-size:21rpx; }
.turnover-strip strong { color:#2563eb; font-size:34rpx; }
.turnover-strip > text { min-width:0; color:#64748b; font-size:21rpx; line-height:1.5; text-align:right; }
.finance-card {
    padding: 4rpx 0;
}
.cell-amount {
    font-size: 28rpx;
    font-weight: 700;
    color: #0f172a;
}
.cell-amount.red { color: #dc2626; }
.cell-amount.blue { color: #2563eb; }
.cell-amount.green { color: #16a34a; }
.cell-amount.orange { color: #ea580c; }
.settlement-card {
    padding: 22rpx 26rpx;
}
.compact-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12rpx;
}
.compact-amount {
    font-size: 30rpx;
    color: #0f172a;
    font-weight: 800;
}
.recent-order { padding:22rpx 26rpx; }
.recent-order__main { min-width:0; flex:1; }
.recent-order__main .card-title,
.recent-order__main .card-meta { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.recent-order__main .card-meta { margin-top:6rpx; }
.recent-sale-metrics { display:flex; justify-content:space-between; gap:16rpx; margin:16rpx 0 10rpx; padding-top:14rpx; border-top:1rpx solid #eef2f7; color:#475569; font-size:24rpx; font-weight:650; }
.recent-sale-metrics .green { color:#16a34a; }
.recent-sale-metrics .red { color:#dc2626; }
.loading-row {
    padding: 42rpx 0;
    display: flex;
    justify-content: center;
}
</style>
