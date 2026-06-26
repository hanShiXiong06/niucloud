<template>
    <el-dialog
        v-model="historyDialog.visible"
        :title="`历史价格 · ${historyDialog.row.model_name || historyDialog.row.name || ''}`"
        width="860px"
        class="qs-scope quote-spider-dialog"
        append-to-body
        destroy-on-close
        @opened="renderChart"
        @closed="disposeChart"
    >
        <div class="history-head">
            <el-radio-group :model-value="historyDialog.days" @change="setHistoryDays">
                <el-radio-button :value="7">近 7 天</el-radio-button>
                <el-radio-button :value="30">近 30 天</el-radio-button>
                <el-radio-button :value="90">近 90 天</el-radio-button>
            </el-radio-group>
            <span class="muted">点击图例可单独查看某个等级的价格走势</span>
        </div>

        <div v-loading="historyDialog.loading" class="history-chart-wrap">
            <div v-show="hasData" ref="chartRef" class="history-chart" />
            <el-empty v-if="!hasData && !historyDialog.loading" description="这个型号还没有历史价格数据（价格变动后会逐步累积）" />
        </div>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import * as echarts from 'echarts'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const { historyDialog, setHistoryDays } = useQuoteSpider()

const chartRef = ref<HTMLElement | null>(null)
let chart: echarts.ECharts | null = null

const hasData = computed(() => historyDialog.points.length > 0 && historyDialog.columns.length > 0)

const buildOption = () => {
    const dates = historyDialog.points.map(p => p.date)
    const series = historyDialog.columns.map((col, idx) => ({
        name: col,
        type: 'line' as const,
        smooth: true,
        connectNulls: true,
        symbolSize: 6,
        data: historyDialog.points.map(p => {
            const v = Number(p.prices?.[idx])
            return Number.isFinite(v) ? v : null
        })
    }))
    return {
        tooltip: { trigger: 'axis' },
        legend: { type: 'scroll', top: 0 },
        grid: { left: 48, right: 24, top: 40, bottom: 36 },
        xAxis: { type: 'category', boundaryGap: false, data: dates },
        yAxis: { type: 'value', scale: true },
        series
    }
}

const renderChart = () => {
    if (!hasData.value) return
    nextTick(() => {
        if (!chartRef.value) return
        if (!chart) chart = echarts.init(chartRef.value)
        chart.setOption(buildOption(), true)
        chart.resize()
    })
}

const disposeChart = () => {
    chart?.dispose()
    chart = null
}

const handleResize = () => chart?.resize()
window.addEventListener('resize', handleResize)

watch(
    () => [historyDialog.points, historyDialog.columns],
    () => {
        if (historyDialog.visible) renderChart()
    },
    { deep: true }
)

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
    disposeChart()
})
</script>

<style scoped>
.history-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.history-chart-wrap {
    min-height: 360px;
}

.history-chart {
    width: 100%;
    height: 360px;
}
</style>
