<template>
    <view class="output-page">
        <view class="page-heading">
            <text class="page-title">员工产出</text>
            <button class="icon-button" aria-label="刷新" :disabled="loading" @click="load(true)"><u-icon name="reload" color="#2563eb" size="21" /></button>
        </view>
        <view class="period-tabs">
            <button v-for="item in periods" :key="item.value" class="period-tab" :class="{ active: period === item.value }" @click="changePeriod(item.value)">{{ item.label }}</button>
        </view>
        <view class="filter-row">
            <picker :range="sources" range-key="label" :value="sourceIndex" @change="changeSource">
                <view class="source-picker"><text>{{ sources[sourceIndex].label }}</text><u-icon name="arrow-down" size="13" color="#64748b" /></view>
            </picker>
            <text class="muted range">{{ range.start_date }} ~ {{ range.end_date }}</text>
        </view>
        <view class="search-row">
            <u-icon name="search" color="#94a3b8" size="18" />
            <input v-model="keyword" placeholder="员工姓名 / 业务指标" confirm-type="search" @confirm="load(true)" />
            <button class="text-button" @click="load(true)">搜索</button>
        </view>
        <view v-if="loading && !rows.length" class="state"><u-loading-icon size="26" /><text>正在读取员工产出</text></view>
        <view v-else-if="error && !rows.length" class="state"><text>{{ error }}</text><button class="text-button" @click="load(true)">重新加载</button></view>
        <template v-else>
            <view class="summary-strip">
                <view><text class="summary-value">{{ summary.employee_count || 0 }}</text><text class="muted">有产出员工</text></view>
                <view><text class="summary-value">{{ summary.completed_count || 0 }}</text><text class="muted">有效动作</text></view>
                <view><text class="summary-value">{{ summary.metric_count || 0 }}</text><text class="muted">业务指标</text></view>
            </view>
            <view class="section-heading"><text>员工明细</text><text class="muted">{{ total }} 人</text></view>
            <view v-if="!rows.length" class="state"><u-empty mode="data" text="当前范围暂无员工产出" /></view>
            <view v-else class="employee-list">
                <view v-for="row in rows" :key="row.employee_uid" class="employee-row" @click="openEmployee(row.employee_uid)">
                    <view class="employee-avatar">{{ (row.employee_name || '员').slice(0, 1) }}</view>
                    <view class="employee-copy"><text class="row-title">{{ row.employee_name || '未命名员工' }}</text><text class="muted">{{ row.metric_count }} 项指标</text></view>
                    <view class="employee-count"><text class="row-title">{{ row.completed_count }}</text><text class="muted">有效动作</text></view>
                    <u-icon name="arrow-right" color="#94a3b8" size="15" />
                </view>
            </view>
            <view v-if="error" class="inline-error">{{ error }}</view>
            <button v-if="rows.length && (rows.length < total || error)" class="load-more" :disabled="loading" @click="load(false)">{{ loading ? '加载中' : (error ? '重试加载' : '加载更多') }}</button>
        </template>
    </view>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { onReachBottom, onShow } from '@dcloudio/uni-app'
import { getMobilePerformanceOutputOverview, getMobilePerformanceOutputEmployees } from '@/addon/hsx_performance/api'

const periods = [{ value: 'today', label: '今日' }, { value: 'week', label: '本周' }, { value: 'month', label: '本月' }, { value: 'last7', label: '近7天' }]
const period = ref('month'), source = ref(''), keyword = ref('')
const sources = ref([{ value: '', label: '全部业务' }])
const sourceIndex = computed(() => Math.max(0, sources.value.findIndex(item => item.value === source.value)))
const range = ref({ start_date: '', end_date: '' })
const summary = ref<any>({}), rows = ref<any[]>([])
const page = ref(0), total = ref(0), loading = ref(false), error = ref('')
let sequence = 0
let appliedFilters = { period: 'month', source_plugin: '', keyword: '' }

async function load(reset = true) {
    if (!reset && (loading.value || rows.value.length >= total.value)) return
    const requestId = ++sequence
    const nextPage = reset ? 1 : page.value + 1
    if (reset) appliedFilters = { period: period.value, source_plugin: source.value, keyword: keyword.value.trim() }
    const params = { ...appliedFilters, page: nextPage, limit: 20 }
    loading.value = true
    error.value = ''
    if (reset) {
        rows.value = []
        summary.value = {}
        total.value = 0
        page.value = 0
    }
    try {
        // Apply overview and list together so rapid filter changes cannot mix periods.
        const overview: any = reset ? await getMobilePerformanceOutputOverview(params) : null
        if (requestId !== sequence) return
        const response: any = await getMobilePerformanceOutputEmployees(params)
        if (requestId !== sequence) return
        const data = response.data || {}
        if (overview) {
            summary.value = overview.data?.summary || {}
            sources.value = [{ value: '', label: '全部业务' }, ...(overview.data?.filters?.plugins || [])]
        }
        range.value = data.range || overview?.data?.range || range.value
        rows.value = reset ? (data.data || []) : [...rows.value, ...(data.data || [])]
        total.value = Number(data.total || 0)
        page.value = nextPage
    } catch (err: any) {
        if (requestId === sequence) error.value = err?.msg || err?.message || '暂时无法读取员工产出'
    } finally {
        if (requestId === sequence) loading.value = false
    }
}
function changePeriod(value: string) {
    if (period.value === value) return
    period.value = value
    load(true)
}
function changeSource(event: any) {
    source.value = sources.value[Number(event.detail.value)]?.value || ''
    load(true)
}
function openEmployee(uid: number) {
    uni.navigateTo({ url: `/addon/hsx_performance/pages/output/detail?uid=${uid}&period=${period.value}&source_plugin=${encodeURIComponent(source.value)}` })
}
onShow(() => load(true))
onReachBottom(() => load(false))
onBeforeUnmount(() => { sequence++ })
</script>

<style scoped lang="scss">
@import './output.scss';
.period-tabs { display:flex; gap:8rpx; margin:24rpx 0 18rpx; padding:6rpx; background:#e9edf3; border-radius:8px; }
.period-tab { flex:1; min-width:0; margin:0; padding:0 8rpx; height:72rpx; line-height:72rpx; font-size:26rpx; color:#64748b; background:transparent; border-radius:6px; }
.period-tab.active { background:#fff; color:#2563eb; font-weight:600; }
.filter-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12rpx; margin-bottom:20rpx; }
.source-picker { display:flex; align-items:center; gap:12rpx; font-size:26rpx; color:#334155; padding:10rpx 0; }
.range { font-size:22rpx; }
.search-row { display:flex; align-items:center; gap:12rpx; padding:4rpx 20rpx; background:#fff; border:1px solid #e2e8f0; border-radius:8px; }
.search-row input { flex:1; min-width:0; font-size:26rpx; height:72rpx; }
.summary-strip { display:flex; padding:30rpx 0; border-bottom:1px solid #e2e8f0; }
.summary-strip > view { flex:1; min-width:0; display:flex; flex-direction:column; gap:6rpx; text-align:center; }
.summary-value { font-size:36rpx; font-weight:700; color:#1e293b; }
.employee-list { background:#fff; border-radius:8px; overflow:hidden; }
.employee-row { display:flex; align-items:center; gap:18rpx; min-height:136rpx; padding:20rpx; box-sizing:border-box; border-bottom:1px solid #eef2f7; }
.employee-row:last-child { border-bottom:0; }
.employee-avatar { display:flex; align-items:center; justify-content:center; flex-shrink:0; width:64rpx; height:64rpx; border-radius:8px; background:#eef4ff; color:#2563eb; font-size:28rpx; }
.employee-copy { flex:1; min-width:0; display:flex; flex-direction:column; gap:8rpx; }
.employee-count { flex-shrink:0; display:flex; flex-direction:column; gap:8rpx; text-align:right; }
.load-more { margin-top:18rpx; color:#2563eb; background:#fff; border-radius:8px; font-size:26rpx; }
.inline-error { margin-top:20rpx; color:#b45309; font-size:24rpx; }
</style>
