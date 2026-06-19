<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never" v-loading="loading">
            <!-- 头部 + 筛选 -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-page-title">运营看板</div>
                    <div class="mt-1 text-sm text-gray-500">人 · 货 · 钱，一屏看清经营全局。数据范围：{{ data.range.start }} ~ {{ data.range.end }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <el-radio-group v-model="rangeType" @change="onRangeTypeChange">
                        <el-radio-button label="today">今天</el-radio-button>
                        <el-radio-button label="week">本周</el-radio-button>
                        <el-radio-button label="month">本月</el-radio-button>
                        <el-radio-button label="last_month">上月</el-radio-button>
                    </el-radio-group>
                    <el-date-picker
                        v-model="customRange"
                        type="daterange"
                        value-format="X"
                        range-separator="至"
                        start-placeholder="开始"
                        end-placeholder="结束"
                        style="width: 240px"
                        @change="onCustomRange"
                    />
                    <el-select v-model="warehouseId" placeholder="全部仓库" clearable filterable style="width: 150px" @change="load">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.warehouse_name" :value="w.id" />
                    </el-select>
                    <el-button :icon="Refresh" @click="load">刷新</el-button>
                </div>
            </div>

            <!-- KPI 卡 -->
            <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div class="kpi" style="--c:#5b8ff9">
                    <div class="kpi-label">销售额</div>
                    <div class="kpi-value">¥{{ money(data.kpi.sales_amount) }}</div>
                    <div class="kpi-sub">{{ data.kpi.sold_count }} 台</div>
                </div>
                <div class="kpi" style="--c:#5ad8a6">
                    <div class="kpi-label">毛利</div>
                    <div class="kpi-value">¥{{ money(data.kpi.gross_profit) }}</div>
                    <div class="kpi-sub">毛利率 {{ data.kpi.margin }}%</div>
                </div>
                <div class="kpi" style="--c:#f6bd16">
                    <div class="kpi-label">采购额</div>
                    <div class="kpi-value">¥{{ money(data.kpi.purchase_cost) }}</div>
                    <div class="kpi-sub">{{ data.kpi.purchase_count }} 台入库</div>
                </div>
                <div class="kpi" style="--c:#5d7092">
                    <div class="kpi-label">在手库存</div>
                    <div class="kpi-value">{{ data.kpi.on_hand_count }} 台</div>
                    <div class="kpi-sub">成本 ¥{{ money(data.kpi.on_hand_cost) }}</div>
                </div>
                <div class="kpi" style="--c:#6dc8ec">
                    <div class="kpi-label">可售</div>
                    <div class="kpi-value">{{ data.kpi.sellable_count }} 台</div>
                    <div class="kpi-sub">¥{{ money(data.kpi.sellable_amount) }}</div>
                </div>
                <div class="kpi" style="--c:#ff9d4d">
                    <div class="kpi-label">平均库龄</div>
                    <div class="kpi-value" :class="Number(data.kpi.avg_age_days) >= 30 ? '!text-red-500' : ''">{{ data.kpi.avg_age_days }} 天</div>
                    <div class="kpi-sub">周转率 {{ data.kpi.turnover }}</div>
                </div>
            </div>

            <!-- 趋势 -->
            <div class="chart-card mt-4">
                <div class="chart-title">销售额 &amp; 毛利趋势</div>
                <vue-chart :option="trendOption" height="320px" />
            </div>

            <!-- 结构图 -->
            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="chart-card">
                    <div class="chart-title">库存状态分布</div>
                    <vue-chart :option="statusOption" height="300px" />
                </div>
                <div class="chart-card">
                    <div class="chart-title">库龄分布（在手）</div>
                    <vue-chart :option="ageOption" height="300px" />
                </div>
            </div>

            <!-- 热销机型 -->
            <div class="chart-card mt-4">
                <div class="chart-title">热销机型 Top10（按毛利）</div>
                <vue-chart :option="modelOption" height="360px" />
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref, computed } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { getErpDashboard } from '@/addon/hsx_erp/api/dashboard'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import VueChart from '@/addon/hsx_erp/components/vue-chart/index.vue'

const money = (v: any) => Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })

const loading = ref(false)
const rangeType = ref('month')
const customRange = ref<any>([])
const warehouseId = ref<any>('')
const warehouses = ref<any[]>([])

const data = reactive<any>({
    range: { start: '', end: '' },
    kpi: { sales_amount: 0, gross_profit: 0, margin: 0, purchase_cost: 0, purchase_count: 0, sold_count: 0, on_hand_count: 0, on_hand_cost: 0, sellable_count: 0, sellable_amount: 0, avg_age_days: 0, turnover: 0 },
    sales_trend: [],
    top_models: [],
    stock_status: [],
    stock_age: [],
})

// 计算时间区间（秒级时间戳）
const presetRange = (): [number, number] => {
    const now = Math.floor(Date.now() / 1000)
    const d = new Date()
    const startOfDay = (dt: Date) => Math.floor(new Date(dt.getFullYear(), dt.getMonth(), dt.getDate()).getTime() / 1000)
    if (rangeType.value === 'today') return [startOfDay(d), now]
    if (rangeType.value === 'week') {
        const day = (d.getDay() + 6) % 7 // 周一=0
        const monday = new Date(d); monday.setDate(d.getDate() - day)
        return [startOfDay(monday), now]
    }
    if (rangeType.value === 'last_month') {
        const s = new Date(d.getFullYear(), d.getMonth() - 1, 1)
        const e = new Date(d.getFullYear(), d.getMonth(), 1)
        return [Math.floor(s.getTime() / 1000), Math.floor(e.getTime() / 1000) - 1]
    }
    // month
    const s = new Date(d.getFullYear(), d.getMonth(), 1)
    return [Math.floor(s.getTime() / 1000), now]
}

const onRangeTypeChange = () => { customRange.value = []; load() }
const onCustomRange = (val: any) => { if (val && val.length === 2) load() }

const load = async () => {
    loading.value = true
    try {
        let start = 0, end = 0
        if (Array.isArray(customRange.value) && customRange.value.length === 2) {
            start = Number(customRange.value[0]); end = Number(customRange.value[1])
        } else {
            const [s, e] = presetRange(); start = s; end = e
        }
        const res: any = await getErpDashboard({ start, end, warehouse_id: warehouseId.value || 0 })
        Object.assign(data, res.data || {})
    } finally {
        loading.value = false
    }
}

const loadWarehouses = async () => {
    try {
        const res: any = await getErpWarehouseOptions()
        warehouses.value = res.data || []
    } catch (e) { /* ignore */ }
}

// —— 图表 option ——
const grad = (from: string, to: string) => ({
    type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
    colorStops: [{ offset: 0, color: from }, { offset: 1, color: to }],
})

const trendOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    legend: { data: ['销售额', '毛利'], right: 10, top: 0 },
    grid: { left: 50, right: 20, top: 36, bottom: 30 },
    xAxis: { type: 'category', boundaryGap: false, data: data.sales_trend.map((x: any) => x.day.slice(5)), axisLine: { lineStyle: { color: '#dcdfe6' } }, axisLabel: { color: '#909399' } },
    yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f2f5' } }, axisLabel: { color: '#909399' } },
    series: [
        {
            name: '销售额', type: 'line', smooth: true, symbol: 'circle', symbolSize: 6,
            data: data.sales_trend.map((x: any) => x.sale),
            lineStyle: { width: 3, color: '#5b8ff9' }, itemStyle: { color: '#5b8ff9' },
            areaStyle: { color: grad('rgba(91,143,249,0.35)', 'rgba(91,143,249,0.02)') },
            animationDuration: 1200,
        },
        {
            name: '毛利', type: 'line', smooth: true, symbol: 'circle', symbolSize: 6,
            data: data.sales_trend.map((x: any) => x.profit),
            lineStyle: { width: 3, color: '#5ad8a6' }, itemStyle: { color: '#5ad8a6' },
            areaStyle: { color: grad('rgba(90,216,166,0.30)', 'rgba(90,216,166,0.02)') },
            animationDuration: 1400,
        },
    ],
}))

const statusColors = ['#5b8ff9', '#5ad8a6', '#f6bd16', '#ff9d4d', '#6dc8ec', '#9270ca', '#ff99c3', '#5d7092', '#e8684a']
const statusOption = computed(() => ({
    tooltip: { trigger: 'item', formatter: '{b}: {c} 台 ({d}%)' },
    legend: { bottom: 0, type: 'scroll' },
    series: [{
        type: 'pie', radius: ['42%', '68%'], center: ['50%', '46%'], avoidLabelOverlap: true,
        itemStyle: { borderColor: '#fff', borderWidth: 2, borderRadius: 6 },
        label: { show: true, formatter: '{b}\n{c}台' },
        data: data.stock_status.map((x: any, i: number) => ({ name: x.name, value: x.count, itemStyle: { color: statusColors[i % statusColors.length] } })),
        animationType: 'scale', animationDuration: 1000,
    }],
}))

const ageOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: 40, right: 20, top: 20, bottom: 30 },
    xAxis: { type: 'category', data: data.stock_age.map((x: any) => x.bucket), axisLabel: { color: '#909399' }, axisLine: { lineStyle: { color: '#dcdfe6' } } },
    yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f2f5' } }, axisLabel: { color: '#909399' } },
    series: [{
        type: 'bar', barWidth: '46%',
        data: data.stock_age.map((x: any, i: number) => ({
            value: x.count,
            itemStyle: { borderRadius: [6, 6, 0, 0], color: grad(i >= 3 ? '#ff7a7a' : '#5b8ff9', i >= 3 ? '#ffb4b4' : '#a8c5fb') },
        })),
        animationDelay: (i: number) => i * 120, animationDuration: 800,
    }],
}))

const modelOption = computed(() => {
    const list = [...data.top_models].reverse()
    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (p: any) => `${p[0].name}<br/>毛利 ¥${money(p[0].value)} · ${list[p[0].dataIndex].count}台` },
        grid: { left: 110, right: 30, top: 10, bottom: 20 },
        xAxis: { type: 'value', splitLine: { lineStyle: { color: '#f0f2f5' } }, axisLabel: { color: '#909399' } },
        yAxis: { type: 'category', data: list.map((x: any) => x.model), axisLabel: { color: '#606266' }, axisLine: { lineStyle: { color: '#dcdfe6' } } },
        series: [{
            type: 'bar', barWidth: 14,
            data: list.map((x: any) => x.profit),
            itemStyle: { borderRadius: [0, 7, 7, 0], color: grad('#5ad8a6', '#5b8ff9') },
            animationDelay: (i: number) => i * 80, animationDuration: 900,
        }],
    }
})

onMounted(() => { loadWarehouses(); load() })
</script>

<style lang="scss" scoped>
.kpi {
    position: relative;
    border-radius: 10px;
    padding: 16px 18px;
    background: #fff;
    border: 1px solid #f0f2f5;
    overflow: hidden;
}
.kpi::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: var(--c);
}
.kpi-label { font-size: 13px; color: #909399; }
.kpi-value { margin-top: 6px; font-size: 24px; font-weight: 700; color: #1f2733; line-height: 1.2; }
.kpi-sub { margin-top: 4px; font-size: 12px; color: #b0b3bb; }

.chart-card {
    border: 1px solid #f0f2f5;
    border-radius: 10px;
    padding: 14px 16px 6px;
    background: #fff;
}
.chart-title { font-size: 15px; font-weight: 600; color: #1f2733; margin-bottom: 6px; }
</style>
