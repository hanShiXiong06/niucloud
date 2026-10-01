<template>
    <view class="output-page">
        <view v-if="loading" class="state"><u-loading-icon size="26" /><text>正在读取产出明细</text></view>
        <view v-else-if="error" class="state"><text>{{ error }}</text><button class="text-button" @click="load">重新加载</button></view>
        <template v-else-if="detail">
            <view class="page-heading"><text class="page-title">{{ detail.employee.employee_name || '未命名员工' }}</text><button class="icon-button" aria-label="刷新" @click="load"><u-icon name="reload" color="#2563eb" size="21" /></button></view>
            <text class="muted date-range">{{ detail.range.start_date }} ~ {{ detail.range.end_date }}</text>
            <view class="employee-summary"><text class="total-value">{{ detail.employee.completed_count }}</text><text class="muted">有效动作 · {{ detail.employee.metric_count }} 项指标</text></view>
            <view class="section-heading"><text>业务指标</text></view>
            <view class="metric-list">
                <view v-for="item in detail.metrics" :key="`${item.source_plugin}:${item.business_chain}:${item.metric_key}`" class="metric-row">
                    <view class="metric-main"><text class="row-title">{{ item.metric_name }}</text><text class="metric-quantity">{{ quantity(item.quantity) }} {{ item.unit }}</text></view>
                    <view class="metric-foot"><text>{{ item.completed_count }} 次有效动作</text><text v-if="Number(item.amount)">金额 ¥{{ money(item.amount) }}</text><text v-if="Number(item.profit)">毛利 ¥{{ money(item.profit) }}</text></view>
                </view>
            </view>
            <view class="section-heading"><text>最近记录</text><text class="muted">最近 {{ detail.facts.length }} 条</text></view>
            <view v-if="!detail.facts.length" class="state"><u-empty mode="data" text="暂无业务记录" /></view>
            <view v-for="item in detail.facts" :key="item.id" class="fact-row">
                <view class="fact-heading"><text class="row-title">{{ item.metric_name || item.action_key }}</text><text class="fact-type" :class="{ reversal: item.fact_type === 'reversal' }">{{ item.fact_type === 'reversal' ? '冲红' : '完成' }}</text></view>
                <view class="fact-meta"><text>{{ dateTime(item.occurred_at) }}</text><text>{{ quantity(item.quantity) }} {{ item.unit }}</text></view>
                <text v-if="item.business_no" class="business-no">单号 {{ item.business_no }}</text>
            </view>
        </template>
    </view>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getMobilePerformanceOutputEmployee } from '@/addon/hsx_performance/api'

const detail = ref<any>(null), loading = ref(false), error = ref('')
let uid = 0, sequence = 0
const filters = { period: 'month', source_plugin: '' }
async function load() {
    if (!Number.isSafeInteger(uid) || uid <= 0) {
        error.value = '员工参数无效，请返回员工列表重新选择'
        return
    }
    const requestId = ++sequence
    loading.value = true
    error.value = ''
    try {
        const response: any = await getMobilePerformanceOutputEmployee(uid, filters)
        if (requestId === sequence) detail.value = response.data
    } catch (err: any) {
        if (requestId === sequence) error.value = err?.msg || err?.message || '暂时无法读取产出明细'
    } finally {
        if (requestId === sequence) loading.value = false
    }
}
const quantity = (value: any) => Number(value || 0).toFixed(3).replace(/\.?0+$/, '')
const money = (value: any) => Number(value || 0).toFixed(2)
function dateTime(value: any) {
    if (!value) return ''
    const date = new Date(Number(value) * 1000)
    const pad = (part: number) => String(part).padStart(2, '0')
    return `${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}
onLoad((options: any) => {
    uid = Number(options?.uid)
    filters.period = ['today', 'week', 'month', 'last7'].includes(options?.period) ? options.period : 'month'
    filters.source_plugin = String(options?.source_plugin || '')
    load()
})
onBeforeUnmount(() => { sequence++ })
</script>

<style scoped lang="scss">
@import './output.scss';
.date-range { display:block; margin-top:12rpx; }
.employee-summary { display:flex; align-items:baseline; gap:18rpx; padding:26rpx 0; border-bottom:1px solid #e2e8f0; }
.total-value { font-size:48rpx; font-weight:700; color:#1e293b; }
.metric-list { background:#fff; border-radius:8px; overflow:hidden; }
.metric-row { padding:24rpx; border-bottom:1px solid #eef2f7; }
.metric-row:last-child { border-bottom:0; }
.metric-main { display:flex; align-items:baseline; justify-content:space-between; gap:20rpx; }
.metric-quantity { flex-shrink:0; font-size:28rpx; color:#2563eb; font-weight:600; }
.metric-foot { display:flex; flex-wrap:wrap; gap:10rpx 22rpx; margin-top:10rpx; color:#64748b; font-size:22rpx; }
.fact-row { margin-bottom:16rpx; padding:24rpx; background:#fff; border-radius:8px; }
.fact-heading,.fact-meta { display:flex; align-items:center; justify-content:space-between; gap:16rpx; }
.fact-type { flex-shrink:0; color:#15803d; background:#f0fdf4; padding:4rpx 12rpx; font-size:22rpx; border-radius:4px; }
.fact-type.reversal { color:#b45309; background:#fffbeb; }
.fact-meta,.business-no { margin-top:12rpx; font-size:23rpx; color:#64748b; }
.business-no { display:block; overflow-wrap:anywhere; }
</style>
