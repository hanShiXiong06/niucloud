<template>
    <scroll-view
        scroll-y
        class="stats-page"
        refresher-enabled
        :refresher-triggered="refreshing"
        @refresherrefresh="onRefresh"
    >
        <view class="hero-card">
            <view class="hero-top">
                <text class="hero-kicker">{{ currentDateLabel }} · 回收经营</text>
                <view class="refresh-btn" @click="loadStats">
                    <text class="nc-iconfont nc-icon-shuaxinV6xx"></text>
                </view>
            </view>
            <view class="hero-main">
                <text class="hero-num">{{ heroRecycled }}</text>
                <text class="hero-num__unit">台</text>
                <text class="hero-num__label">收货设备</text>
            </view>
            <view class="hero-stats">
                <view class="hero-stat">
                    <text class="hero-stat__v">{{ heroOrders }}</text>
                    <text class="hero-stat__k">订单</text>
                </view>
                <view class="hero-stat">
                    <text class="hero-stat__v">{{ heroNew }}</text>
                    <text class="hero-stat__k">新增设备</text>
                </view>
                <view class="hero-stat">
                    <text class="hero-stat__v">¥{{ heroPaid }}</text>
                    <text class="hero-stat__k">已打款</text>
                </view>
            </view>
        </view>

        <view class="date-filter">
            <RecycleTagGroup
                :model-value="currentDate"
                :options="dateOptions"
                :deselectable="false"
                @change="switchDate"
            />
        </view>

        <view v-if="loading" class="loading-box">
            <view class="loading-dot"></view>
            <text>正在整理经营数据...</text>
        </view>

        <template v-else>
            <view class="section" v-if="stageBoard.stages && stageBoard.stages.length">
                <view class="section-head">
                    <view class="section-title-row">
                        <u-icon name="list" color="#7c3aed" size="15"></u-icon>
                        <text class="section-title">各环节在途</text>
                    </view>
                    <text class="section-desc">实时 · 点击查看任务</text>
                </view>
                <view class="metric-grid">
                    <view
                        v-for="s in stageBoard.stages"
                        :key="s.stage_key"
                        class="metric-card metric-card--link"
                        @click="toStageTask(s.stage_key)"
                    >
                        <view class="metric-main">
                            <text class="metric-value" :style="{ color: stageColor(s.stage_key) }">{{ s.count }}</text>
                            <text class="metric-unit">{{ s.stage_key === 'sign' ? '单' : '台' }}</text>
                        </view>
                        <text class="metric-label">{{ s.name }}</text>
                    </view>
                </view>
            </view>

            <view class="section">
                <view class="section-head">
                    <view class="section-title-row">
                        <u-icon name="grid-fill" color="#3c9cff" size="15"></u-icon>
                        <text class="section-title">关键指标</text>
                    </view>
                </view>
                <view class="metric-grid">
                    <view
                        v-for="item in primaryMetrics"
                        :key="item.key"
                        class="metric-card"
                        :class="{ 'metric-card--link': item.drilldown }"
                        @click="openDrilldown(item)"
                    >
                        <view class="metric-main">
                            <text class="metric-value" :class="item.color">{{ item.value }}</text>
                            <text class="metric-unit">{{ item.unit }}</text>
                        </view>
                        <text class="metric-label">{{ item.label }}</text>
                    </view>
                </view>
            </view>

            <view class="section section--card">
                <view class="section-head">
                    <view class="section-title-row">
                        <u-icon name="star-fill" color="#fa5c1e" size="15"></u-icon>
                        <text class="section-title">型号分布</text>
                    </view>
                    <text class="section-desc">收货 TOP 型号</text>
                </view>
                <view v-if="modelBreakdown.length" class="model-list">
                    <view v-for="(m, i) in modelBreakdown" :key="i" class="model-row">
                        <text class="model-rank" :class="{ 'model-rank--top': i < 3 }">{{ i + 1 }}</text>
                        <view class="model-main">
                            <view class="model-name-row">
                                <text class="model-name">{{ m.name }}</text>
                                <text class="model-count">{{ m.count }} 台</text>
                            </view>
                            <view class="model-bar">
                                <view class="model-bar__fill" :style="{ width: m.rate + '%' }"></view>
                            </view>
                        </view>
                    </view>
                </view>
                <u-empty v-else text="暂无收货型号" mode="list"></u-empty>
            </view>

            <view class="section">
                <view class="section-head">
                    <view class="section-title-row">
                        <u-icon name="clock-fill" color="#f97316" size="15"></u-icon>
                        <text class="section-title">待办跟进</text>
                    </view>
                </view>
                <view class="todo-grid">
                    <view
                        v-for="item in todoMetrics"
                        :key="item.key"
                        class="todo-card"
                        :class="{ 'todo-card--urgent': item.urgent }"
                        @click="openDrilldown(item)"
                    >
                        <text class="todo-value">{{ item.value }}</text>
                        <text class="todo-label">{{ item.label }}</text>
                    </view>
                </view>
            </view>

            <view class="chart-card">
                <view class="section-head">
                    <text class="section-title">经营趋势</text>
                </view>
                <view class="uchart-box uchart-box--trend">
                    <view class="chart-render chart-render--trend">
                        <qiun-data-charts
                            v-if="hasTrendData"
                            type="column"
                            :chartData="trendChart"
                            :opts="trendOpts"
                            :canvas2d="true"
                            canvasId="recycleTrend"
                            :ontouch="true"
                        />
                        <view v-else class="chart-empty">暂无趋势数据</view>
                    </view>
                    <view class="chart-legend">
                        <view class="legend-item">
                            <view class="legend-dot legend-dot--order"></view>
                            <text>新增订单</text>
                        </view>
                        <view class="legend-item">
                            <view class="legend-dot legend-dot--device"></view>
                            <text>新增设备</text>
                        </view>
                    </view>
                    <view v-if="trendPaidText" class="chart-note">{{ trendPaidText }}</view>
                </view>
            </view>

            <view class="chart-card">
                <view class="section-head">
                    <text class="section-title">设备生命周期</text>
                </view>
                <view class="uchart-box">
                    <view class="chart-render chart-render--lifecycle">
                        <qiun-data-charts
                            v-if="hasLifecycleData"
                            type="column"
                            :chartData="lifecycleChartData"
                            :opts="lifecycleOpts"
                            :canvas2d="true"
                            canvasId="recycleLifecycle"
                            :ontouch="true"
                        />
                        <view v-else class="chart-empty">暂无生命周期数据</view>
                    </view>
                </view>
            </view>

            <view class="chart-card">
                <view class="section-head">
                    <text class="section-title">来源与履约</text>
                </view>
                <view class="uchart-box uchart-box--ring">
                    <view class="chart-render chart-render--ring">
                        <qiun-data-charts
                            v-if="hasSourceData"
                            type="ring"
                            :chartData="sourceChartData"
                            :opts="sourceOpts"
                            :canvas2d="true"
                            canvasId="recycleSource"
                            :ontouch="true"
                        />
                        <view v-else class="chart-empty">暂无来源数据</view>
                    </view>
                </view>
            </view>

            <view class="section section--card">
                <view class="section-head">
                    <view class="section-title-row">
                        <u-icon name="list" color="#3c9cff" size="15"></u-icon>
                        <text class="section-title">分类排行</text>
                    </view>
                    <text class="section-desc">按设备数量</text>
                </view>
                <view v-if="categoryRank.length" class="model-list">
                    <view v-for="(c, i) in categoryRank" :key="i" class="model-row">
                        <text class="model-rank" :class="{ 'model-rank--top': i < 3 }">{{ i + 1 }}</text>
                        <view class="model-main">
                            <view class="model-name-row">
                                <text class="model-name">{{ c.name }}</text>
                                <text class="model-count model-count--blue">{{ c.count }} 台</text>
                            </view>
                            <view class="model-bar">
                                <view class="model-bar__fill model-bar__fill--blue" :style="{ width: c.rate + '%' }"></view>
                            </view>
                        </view>
                    </view>
                </view>
                <u-empty v-else text="暂无分类数据" mode="list"></u-empty>
            </view>

            <view class="section">
                <view class="section-head">
                    <text class="section-title">资金概览</text>
                    <text class="section-desc">成本、待打款与已打款</text>
                </view>
                <view class="finance-list">
                    <view v-for="item in financeMetrics" :key="item.key" class="finance-row">
                        <view>
                            <text class="finance-label">{{ item.label }}</text>
                            <text class="finance-note">{{ item.note }}</text>
                        </view>
                        <text class="finance-value">¥{{ item.value }}</text>
                    </view>
                </view>
            </view>

            <view class="footer-note">{{ dashboardExplain }}</view>
        </template>
    </scroll-view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh } from '@dcloudio/uni-app'
import { getDashboardOverview, getDashboardTrend } from '@/addon/hsx_recycle/api/stats'
import { getStatBoard } from '@/addon/hsx_recycle/api/task'
import { redirect } from '@/utils/common'
import qiunDataCharts from '@/components/qiun-data-charts/components/qiun-data-charts/qiun-data-charts.vue'
import RecycleTagGroup from '@/addon/hsx_recycle/components/RecycleTagGroup.vue'

type DrilldownTarget = {
    filter_key: string
    title?: string
    view_mode?: string
}

type MetricItem = {
    key: string
    label: string
    value: string | number
    unit?: string
    note?: string
    color?: string
    urgent?: boolean
    drilldown?: DrilldownTarget
}

type DashboardCard = {
    key: string
    title?: string
    unit?: string
    value?: string | number
    description?: string
    caliber?: string
    drilldown?: {
        target?: string
        filter_key?: string
        view_mode?: string
    }
}

const loading = ref(true)
const refreshing = ref(false)
const currentDate = ref('week')
const statsData = ref<Record<string, any>>({})
const trendData = ref<Record<string, any>>({})
// 各环节在途（任务流转埋点，实时；与时间筛选无关）
const stageBoard = ref<any>({ stages: [] })
const stageColors: Record<string, string> = {
    sign: '#5b6b7a', check: '#3c9cff', price: '#10b981', confirm: '#f97316', pay: '#7c3aed', abnormal: '#dc2626'
}
const stageColor = (k: string) => stageColors[k] || '#334155'
const toStageTask = (stage: string) => redirect({ url: `/addon/hsx_recycle/pages/task/index?stage=${ stage }` })
const dateOptions = [
    { label: '今日', value: 'today' },
    { label: '昨日', value: 'yesterday' },
    { label: '近7天', value: 'week' },
    { label: '近30天', value: 'month' },
]

const lifecycleStatusOrder = [
    '待签收',
    '已签收',
    '待质检',
    '质检中',
    '已质检',
    '待确认',
    '已定价',
    '待打款',
    '已打款',
    '已回收',
    '已完成',
    '已退回',
    '已取消',
    '已关闭',
]

const currentDateLabel = computed(() => dateOptions.find((item) => item.value === currentDate.value)?.label || '所选时间')
const dashboardExplain = computed(() => statsData.value.explain || '本看板用于老板查看回收台账，销售出库未接入前，库存成本不等同于利润。')
const trendExplain = computed(() => trendData.value.explain || '按所选时间逐日统计')
const ledger = computed(() => statsData.value.ledger || {})
const financeSummary = computed(() => statsData.value.finance_summary || {})
const todayBusiness = computed(() => statsData.value.today_business || {})

const dashboardCardMap = computed<Record<string, DashboardCard>>(() => {
    const cards = Array.isArray(statsData.value.cards) ? statsData.value.cards : []
    return cards.reduce((map: Record<string, DashboardCard>, card: DashboardCard) => {
        if (card?.key) map[String(card.key)] = card
        return map
    }, {})
})

const overviewText = computed(() => {
    const orderCount = valueOf(ledger.value, 'order_count', statsData.value.today_order_count)
    const deviceCount = valueOf(ledger.value, 'device_count', statsData.value.today_device_count)
    const paidAmount = formatMoney(valueOf(financeSummary.value, 'selected_paid_amount', statsData.value.selected_payment_amount))
    const pending = valueOf(ledger.value, 'pending_check_device_count') + valueOf(ledger.value, 'checking_device_count') + valueOf(ledger.value, 'pending_confirm_count')
    return `${currentDateLabel.value}新增 ${orderCount} 单、${deviceCount} 台，已打款 ¥${paidAmount}，当前待处理 ${pending} 台。`
})

// hero 主视觉：收了多少台 + 单数/新增/打款
const heroRecycled = computed(() => valueOf(ledger.value, 'signed_device_count'))
const heroOrders = computed(() => valueOf(ledger.value, 'order_count', statsData.value.today_order_count))
const heroNew = computed(() => valueOf(ledger.value, 'device_count', statsData.value.today_device_count))
const heroPaid = computed(() => formatMoney(valueOf(financeSummary.value, 'selected_paid_amount', statsData.value.selected_payment_amount)))

// 型号分布（老板要看：收了哪些型号、各多少台）。bar 宽按最大值归一
const modelBreakdown = computed(() => {
    const rows = Array.isArray(todayBusiness.value.model_breakdown) ? todayBusiness.value.model_breakdown : []
    const max = Math.max(...rows.map((r: any) => Number(r.count || 0)), 1)
    return rows.slice(0, 8).map((r: any) => ({
        name: r.model_name || r.model || '未识别型号',
        count: Number(r.count || 0),
        amount: r.amount,
        rate: Math.round(Number(r.count || 0) / max * 100)
    }))
})

// 分类排行：换成榜单（原柱状图左侧标签占太大、不好看）
const categoryRank = computed(() => {
    const rows = normalizeBreakdown(todayBusiness.value.category_breakdown)
    const max = Math.max(...rows.map((r: any) => Number(r.count || 0)), 1)
    return [...rows]
        .sort((a, b) => b.count - a.count)
        .slice(0, 8)
        .map((r: any) => ({ name: r.label, count: r.count, rate: Math.round(Number(r.count || 0) / max * 100) }))
})

const primaryMetrics = computed<MetricItem[]>(() => [
    cardMetric('today_order_count', { label: '新增订单', value: valueOf(ledger.value, 'order_count'), unit: '单', color: 'text-blue', note: '提交订单数' }),
    cardMetric('today_device_count', { label: '新增设备', value: valueOf(ledger.value, 'device_count'), unit: '台', color: 'text-purple', note: '订单内设备数' }),
    cardMetric('signed_device_count', { label: '签收设备', value: valueOf(ledger.value, 'signed_device_count'), unit: '台', color: 'text-green', note: '已进入仓内处理' }),
    cardMetric('today_paid_amount', { label: '已打款金额', value: formatMoney(valueOf(financeSummary.value, 'selected_paid_amount')), unit: '元', color: 'text-red', note: '所选时间打款' }),
])

const todoMetrics = computed<MetricItem[]>(() => [
    cardMetric('pending_check_device_count', { label: '待质检', value: valueOf(ledger.value, 'pending_check_device_count'), urgent: true }),
    cardMetric('checking_device_count', { label: '质检中', value: valueOf(ledger.value, 'checking_device_count') }),
    cardMetric('pending_confirm_count', { label: '待确认', value: valueOf(ledger.value, 'pending_confirm_count'), urgent: true }),
    cardMetric('pending_pay', { label: '待打款', value: valueOf(ledger.value, 'pending_pay_order_count', statsData.value.pending_pay), urgent: true }),
    cardMetric('pending_return', { label: '退货待处理', value: valueOf(ledger.value, 'pending_return_count') }),
    cardMetric('completed_order_count', { label: '已完成', value: valueOf(ledger.value, 'completed_order_count') }),
])

const financeMetrics = computed(() => [
    { key: 'selected_paid', label: '所选时间打款', note: '按设备打款/订单打款口径', value: formatMoney(valueOf(financeSummary.value, 'selected_paid_amount')) },
    { key: 'pending_pay', label: '待打款金额', note: '待打款设备报价合计', value: formatMoney(valueOf(financeSummary.value, 'pending_pay_amount')) },
    { key: 'inventory_cost', label: '库存回收成本', note: '已回收未出库成本快照', value: formatMoney(valueOf(financeSummary.value, 'inventory_recovery_cost')) },
    { key: 'month_paid', label: '近30天打款', note: '滚动周期成本支出', value: formatMoney(valueOf(financeSummary.value, 'last_30_days_paid_amount', financeSummary.value.month_paid_amount)) },
])

const trendChartData = computed(() => {
    const categories = Array.isArray(trendData.value.x_axis) ? trendData.value.x_axis.map(formatShortDate) : []
    const rows = Array.isArray(trendData.value.series) ? trendData.value.series : []
    const visibleNames = ['新增订单', '新增设备', '打款金额']
    return {
        categories,
        series: rows
            .filter((item: any) => visibleNames.includes(item.name))
            .map((item: any) => ({
                name: item.name,
                type: item.type === 'line' ? 'line' : 'column',
                data: normalizeNumberList(item.data)
            }))
    }
})

const lifecycleChartData = computed(() => {
    const rows = normalizeBreakdown(todayBusiness.value.device_status_breakdown)
    const ordered = sortByLifecycle(rows)
    return {
        categories: ordered.map((item) => item.label),
        series: [{ name: '设备', data: ordered.map((item) => item.count) }]
    }
})

const sourceChartData = computed(() => {
    const sourceRows = normalizeBreakdown(todayBusiness.value.source_breakdown)
    const deliveryRows = normalizeBreakdown(todayBusiness.value.delivery_breakdown)
    const rows = sourceRows.length ? sourceRows : deliveryRows
    return {
        series: [{
            data: rows.length ? rows.map((item) => ({ name: item.label, value: item.count })) : [{ name: '暂无数据', value: 0 }]
        }]
    }
})

const categoryChartData = computed(() => {
    const rows = normalizeBreakdown(todayBusiness.value.category_breakdown)
        .sort((a, b) => b.count - a.count)
        .slice(0, 6)
    return {
        categories: rows.map((item) => item.label),
        series: [{ name: '设备', data: rows.map((item) => item.count) }]
    }
})

const hasTrendData = computed(() => {
    return Boolean(trendChartData.value.categories.length && trendChartData.value.series.some((item: any) => sumList(item.data) > 0))
})

const hasLifecycleData = computed(() => {
    return Boolean(lifecycleChartData.value.categories.length && sumList(lifecycleChartData.value.series?.[0]?.data) > 0)
})

const hasSourceData = computed(() => {
    const rows = sourceChartData.value.series?.[0]?.data || []
    return rows.some((item: any) => Number(item.value || 0) > 0)
})

const hasCategoryData = computed(() => {
    return Boolean(categoryChartData.value.categories.length && sumList(categoryChartData.value.series?.[0]?.data) > 0)
})

const trendPaidText = computed(() => {
    const paidSeries = trendChartData.value.series.find((item: any) => item.name === '打款金额')?.data || []
    const total = paidSeries.reduce((sum: number, item: any) => sum + Number(item || 0), 0)
    return total > 0 ? `所选周期打款合计 ¥${formatMoney(total)}` : ''
})

const getDashboardCard = (key: string) => dashboardCardMap.value[key]

const cardMetric = (
    cardKey: string,
    fallback: {
        label: string
        value: string | number
        unit?: string
        note?: string
        color?: string
        urgent?: boolean
    }
): MetricItem => {
    const card = getDashboardCard(cardKey)
    return {
        key: cardKey,
        label: String(card?.title || fallback.label),
        value: card?.value ?? fallback.value,
        unit: String(card?.unit ?? fallback.unit ?? ''),
        note: String(card?.description || card?.caliber || fallback.note || ''),
        color: fallback.color,
        urgent: fallback.urgent,
        drilldown: getCardDrilldown(card)
    }
}

const getCardDrilldown = (card: DashboardCard | undefined): DrilldownTarget | undefined => {
    const drilldown = card?.drilldown
    if (!drilldown?.filter_key) return undefined
    if (drilldown.target && drilldown.target !== 'order_list') return undefined
    return {
        filter_key: String(drilldown.filter_key),
        title: card?.title,
        view_mode: drilldown.view_mode ? String(drilldown.view_mode) : ''
    }
}

const openDrilldown = (item: MetricItem) => {
    // 退货待处理 → 退货单列表（而非订单列表）
    if (item.key === 'pending_return') {
        redirect({ url: '/addon/hsx_recycle/pages/return/list' })
        return
    }
    if (!item.drilldown) return
    const range = getDateRange(currentDate.value)
    const param: Record<string, any> = {
        filter_key: item.drilldown.filter_key,
        start_time: range.start_time,
        end_time: range.end_time,
        dashboard_title: `${currentDateLabel.value} · ${item.drilldown.title || item.label}`
    }
    if (item.drilldown.view_mode) param.view_mode = item.drilldown.view_mode
    redirect({ url: '/addon/hsx_recycle/pages/order/list', param })
}

const valueOf = (source: Record<string, any>, key: string, fallback: any = 0) => {
    const value = source?.[key]
    if (value === undefined || value === null || value === '') return Number(fallback || 0)
    const num = Number(value)
    return Number.isFinite(num) ? num : 0
}

const normalizeNumberList = (value: any) => {
    return Array.isArray(value) ? value.map((item) => Number(item || 0)) : []
}

const sumList = (value: any) => normalizeNumberList(value).reduce((sum, item) => sum + Number(item || 0), 0)

const normalizeBreakdown = (rows: any): Array<{ label: string, count: number }> => {
    if (!Array.isArray(rows)) return []
    return rows
        .map((item: any) => ({
            label: String(item.label || item.name || item.status_name || item.category_name || item.delivery_type_name || item.source_name || item.key || '未分类'),
            count: Number(item.count || item.device_count || item.order_count || item.value || 0)
        }))
        .filter((item) => item.label && item.count >= 0)
}

const sortByLifecycle = (rows: Array<{ label: string, count: number }>) => {
    const map = rows.reduce((acc: Record<string, number>, item) => {
        acc[item.label] = (acc[item.label] || 0) + item.count
        return acc
    }, {})
    const ordered = lifecycleStatusOrder
        .filter((label) => map[label] !== undefined)
        .map((label) => ({ label, count: map[label] }))
    const rest = rows.filter((item) => !lifecycleStatusOrder.includes(item.label))
    return [...ordered, ...rest].filter((item) => item.count > 0).slice(0, 10)
}

const formatMoney = (val: any) => Number(val || 0).toFixed(2)

const formatDate = (d: Date) => {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const formatShortDate = (value: any) => {
    // 趋势 X 轴只留「日」，去掉年月，30 天也不会挤满看不清
    const text = String(value || '')
    const parts = text.split('-')
    return parts.length ? parts[parts.length - 1] : text
}

// trend 图只展示 订单/设备 两条柱（打款金额另用文字 trendPaidText 展示）
const trendChart = computed(() => ({
    categories: trendChartData.value.categories,
    series: trendChartData.value.series.filter((s: any) => s.name === '新增订单' || s.name === '新增设备')
}))

// qiun-data-charts(canvas2d) 各图配置。颜色用真实 hex（canvas 不识别 CSS 变量）
const trendOpts = {
    color: ['#3c9cff', '#10b981'],
    padding: [12, 10, 8, 10],
    legend: { show: false },
    dataLabel: false,
    xAxis: { disableGrid: true, fontColor: '#94a3b8' },
    yAxis: { gridType: 'dash', dashLength: 4, data: [{ min: 0, fontColor: '#94a3b8' }] },
    extra: { column: { type: 'group', width: 12, activeBgColor: '#eef2ff' } }
}
const lifecycleOpts = {
    color: ['#3c9cff'],
    padding: [12, 10, 18, 10],
    legend: { show: false },
    dataLabel: true,
    xAxis: { disableGrid: true, rotateLabel: true, fontColor: '#64748b' },
    yAxis: { gridType: 'dash', dashLength: 4, data: [{ min: 0, fontColor: '#94a3b8' }] },
    extra: { column: { type: 'group', width: 16, activeBgColor: '#eef2ff' } }
}
const sourceOpts = {
    color: ['#3c9cff', '#10b981', '#f97316', '#7c3aed', '#dc2626', '#0d9488'],
    padding: [8, 8, 8, 8],
    legend: { show: true, position: 'bottom', fontColor: '#64748b' },
    dataLabel: true,
    extra: { ring: { ringWidth: 24, activeOpacity: 0.65, activeRadius: 8, offsetAngle: 0, labelWidth: 12 } }
}
const categoryOpts = {
    color: ['#7c3aed'],
    padding: [8, 12, 8, 30],
    legend: { show: false },
    dataLabel: true,
    xAxis: { disabled: true },
    yAxis: { disabled: false, fontColor: '#64748b' },
    extra: { bar: { type: 'group', width: 16, categoryGap: 8 } }
}

const getDateRange = (type: string) => {
    const now = new Date()
    let start = ''
    let end = ''

    if (type === 'today') {
        start = end = formatDate(now)
    } else if (type === 'yesterday') {
        const d = new Date(now.getTime() - 86400000)
        start = end = formatDate(d)
    } else if (type === 'week') {
        const d = new Date(now.getTime() - 6 * 86400000)
        start = formatDate(d)
        end = formatDate(now)
    } else if (type === 'month') {
        const d = new Date(now.getTime() - 29 * 86400000)
        start = formatDate(d)
        end = formatDate(now)
    }
    return { start_time: start, end_time: end }
}

let statsInflight = false
const loadStats = async () => {
    // in-flight 守卫：下拉/切日期/刷新按钮/onShow 任一在请求中，后续调用直接跳过，避免并发与抖动
    if (statsInflight) {
        refreshing.value = false
        return
    }
    statsInflight = true
    loading.value = true
    try {
        const params = getDateRange(currentDate.value)
        const [overviewRes, trendRes, boardRes]: any[] = await Promise.all([
            getDashboardOverview(params),
            getDashboardTrend(params),
            getStatBoard({ days: 7 })
        ])
        statsData.value = overviewRes?.data || {}
        trendData.value = trendRes?.data || {}
        stageBoard.value = boardRes?.data || { stages: [] }
    } catch (e: any) {
        statsData.value = {}
        trendData.value = {}
        uni.showToast({ title: e?.msg || e?.message || '统计数据加载失败', icon: 'none' })
    } finally {
        statsInflight = false
        loading.value = false
        refreshing.value = false
        uni.stopPullDownRefresh()
        // 图表由 qiun-data-charts 响应式自渲染，无需手动触发
    }
}

const onRefresh = () => {
    if (refreshing.value) return
    refreshing.value = true
    loadStats()
}

const switchDate = (value: string) => {
    if (currentDate.value === value) return
    currentDate.value = value
    loadStats()
}

onLoad(() => {
    loadStats()
})

onPullDownRefresh(() => {
    loadStats()
})
</script>

<style scoped lang="scss">
.stats-page {
    min-height: 100vh;
    height: 100vh;
    background: #f3f6fb;
    box-sizing: border-box;
    padding: 20rpx 22rpx calc(36rpx + env(safe-area-inset-bottom));
}

.hero-card {
    padding: 30rpx;
    border-radius: 24rpx;
    color: #fff;
    background: linear-gradient(135deg, var(--hsx-primary-dark), #0f766e);
    box-shadow: 0 18rpx 48rpx rgba(29, 78, 216, 0.22);
}

.hero-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}

.hero-kicker {
    font-size: 22rpx;
    color: rgba(255, 255, 255, 0.78);
}

.hero-main {
    display: flex;
    align-items: baseline;
    gap: 8rpx;
    margin-top: 16rpx;
}

.hero-num {
    font-size: 72rpx;
    font-weight: 800;
    line-height: 1;
}

.hero-num__unit {
    font-size: 28rpx;
    font-weight: 600;
}

.hero-num__label {
    margin-left: 10rpx;
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.82);
}

.hero-stats {
    display: flex;
    margin-top: 24rpx;
    padding-top: 22rpx;
    border-top: 1rpx solid rgba(255, 255, 255, 0.18);
}

.hero-stat {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6rpx;
}

.hero-stat__v {
    font-size: 30rpx;
    font-weight: 700;
}

.hero-stat__k {
    font-size: 21rpx;
    color: rgba(255, 255, 255, 0.72);
}

.refresh-btn {
    width: 56rpx;
    height: 56rpx;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.16);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26rpx;
    flex-shrink: 0;
}

.date-filter {
    display: flex;
    gap: 14rpx;
    margin: 22rpx 0;
}

.date-chip {
    flex: 1;
    height: 64rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16rpx;
    font-size: 24rpx;
    color: #64748b;
    background: #fff;
    border: 1rpx solid #e5e7eb;
}

.date-chip--active {
    background: var(--hsx-primary-dark);
    border-color: var(--hsx-primary-dark);
    color: #fff;
    font-weight: 600;
}

.loading-box {
    height: 420rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 18rpx;
    color: #94a3b8;
    font-size: 26rpx;
}

.loading-dot {
    width: 42rpx;
    height: 42rpx;
    border-radius: 50%;
    border: 5rpx solid var(--hsx-primary-100);
    border-top-color: var(--hsx-primary);
    animation: rotate 0.8s linear infinite;
}

.section,
.chart-card {
    margin-bottom: 22rpx;
}

.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
    margin-bottom: 14rpx;
}

.section-title-row {
    display: flex;
    align-items: center;
    gap: 10rpx;
}

/* 整块白色卡片（如型号分布） */
.section--card {
    padding: 24rpx;
    border-radius: 20rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}
.section--card .section-head {
    margin-bottom: 18rpx;
}

.section-title {
    font-size: 29rpx;
    font-weight: 700;
    color: #0f172a;
}

/* 型号分布榜单 */
.model-list {
    display: flex;
    flex-direction: column;
    gap: 18rpx;
}
.model-row {
    display: flex;
    align-items: center;
    gap: 16rpx;
}
.model-rank {
    flex-shrink: 0;
    width: 40rpx;
    height: 40rpx;
    line-height: 40rpx;
    text-align: center;
    border-radius: 12rpx;
    background: #f1f5f9;
    color: #94a3b8;
    font-size: 24rpx;
    font-weight: 700;
}
.model-rank--top {
    background: #fff2e9;
    color: #fa5c1e;
}
.model-main {
    flex: 1;
    min-width: 0;
}
.model-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    margin-bottom: 10rpx;
}
.model-name {
    flex: 1;
    min-width: 0;
    color: #1f2937;
    font-size: 26rpx;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.model-count {
    flex-shrink: 0;
    color: #fa5c1e;
    font-size: 24rpx;
    font-weight: 700;
}
.model-bar {
    height: 12rpx;
    border-radius: 999rpx;
    background: #f1f5f9;
    overflow: hidden;
}
.model-bar__fill {
    height: 100%;
    border-radius: 999rpx;
    background: linear-gradient(90deg, #fa5c1e, #ff8a4c);
}
.model-count--blue {
    color: #3c9cff;
}
.model-bar__fill--blue {
    background: linear-gradient(90deg, #3c9cff, #5aa9ff);
}

.section-desc {
    font-size: 21rpx;
    color: #94a3b8;
    text-align: right;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16rpx;
}

.metric-card,
.todo-card,
.chart-card,
.finance-row {
    background: #fff;
    border: 1rpx solid #e8eef6;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.metric-card {
    min-height: 168rpx;
    border-radius: 20rpx;
    padding: 22rpx;
    box-sizing: border-box;
}

.metric-card--link:active,
.todo-card:active {
    opacity: 0.78;
}

.metric-main {
    display: flex;
    align-items: baseline;
    gap: 8rpx;
}

.metric-value {
    font-size: 42rpx;
    font-weight: 800;
    color: #0f172a;
}

.metric-unit {
    font-size: 21rpx;
    color: #94a3b8;
}

.metric-label {
    display: block;
    margin-top: 10rpx;
    font-size: 24rpx;
    font-weight: 600;
    color: #334155;
}

.metric-note {
    display: block;
    margin-top: 6rpx;
    font-size: 20rpx;
    line-height: 1.4;
    color: #94a3b8;
}

.todo-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14rpx;
}

.todo-card {
    min-height: 128rpx;
    border-radius: 18rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.todo-card--urgent {
    border-color: #fed7aa;
    background: #fff7ed;
}

.todo-value {
    font-size: 38rpx;
    font-weight: 800;
    color: #ea580c;
}

.todo-label {
    margin-top: 8rpx;
    font-size: 21rpx;
    color: #64748b;
}

.chart-card {
    border-radius: 22rpx;
    padding: 22rpx 18rpx 12rpx;
    box-sizing: border-box;
    overflow: hidden;
}

.uchart-box {
    width: 100%;
    overflow: hidden;
}

/* 图表渲染区：必须定高，qiun-data-charts 才能正确测量尺寸、不溢出/不错位 */
.chart-render {
    position: relative;
    width: 100%;
    height: 260px;
}
.chart-render--trend {
    height: 300px;
}
.chart-render--lifecycle {
    height: 260px;
}
.chart-render--ring {
    height: 240px;
}
.chart-render--bar {
    height: 280px;
}

.chart-empty {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24rpx;
    color: #94a3b8;
}

.chart-legend {
    margin-top: 12rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 28rpx;
    font-size: 21rpx;
    color: #64748b;
}

.legend-item {
    display: flex;
    align-items: center;
}

.legend-dot {
    width: 14rpx;
    height: 14rpx;
    margin-right: 8rpx;
    border-radius: 50%;
    flex-shrink: 0;
}

.legend-dot--order {
    background: var(--hsx-primary);
}

.legend-dot--device {
    background: #10b981;
}

.chart-note {
    margin-top: 12rpx;
    text-align: center;
    font-size: 21rpx;
    color: #94a3b8;
}

.finance-list {
    display: flex;
    flex-direction: column;
    gap: 14rpx;
}

.finance-row {
    border-radius: 18rpx;
    padding: 20rpx 22rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}

.finance-label {
    display: block;
    font-size: 25rpx;
    font-weight: 600;
    color: #334155;
}

.finance-note {
    display: block;
    margin-top: 6rpx;
    font-size: 21rpx;
    color: #94a3b8;
}

.finance-value {
    flex-shrink: 0;
    font-size: 29rpx;
    font-weight: 800;
    color: #dc2626;
}

.footer-note {
    padding: 4rpx 4rpx 28rpx;
    font-size: 21rpx;
    line-height: 1.6;
    color: #94a3b8;
}

.text-blue { color: var(--hsx-primary); }
.text-green { color: #16a34a; }
.text-red { color: #dc2626; }
.text-purple { color: #7c3aed; }
.text-gray { color: #64748b; }

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
