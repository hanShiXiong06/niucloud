<template>
    <view class="page">
        <view v-if="loading" class="state"><u-loading-icon size="30" /><text>正在读取报告</text></view>
        <template v-else-if="report">
            <view class="hero"><text class="hero-title">{{ report.title }}</text><text class="hero-period">{{ date(report.start_at) }} 至 {{ date(report.end_at) }}</text><view class="hero-meta"><text>{{ typeName(report.report_type) }}</text><text>{{ time(report.generated_at) }} 生成</text></view></view>
            <view class="scope-tip">销售、毛利和回收入库按报告周期统计；库存和待办为生成时快照。</view>
            <view class="metric-grid"><view v-for="item in periodMetrics" :key="item.label" class="metric"><text>{{ item.label }}</text><strong :class="item.className">{{ item.value }}</strong></view></view>
            <view class="section"><text class="section-title">生成时经营状态</text><view class="snapshot-grid"><view v-for="item in realtimeMetrics" :key="item.label"><text>{{ item.label }}</text><strong>{{ item.value }}</strong></view></view></view>
            <view class="section"><text class="section-title">员工产出</text><view v-if="!snapshot.staff?.length" class="empty-line">暂无员工产出</view><view v-for="item in snapshot.staff || []" :key="`${item.uid}:${item.role_key}`" class="staff-row"><view><text class="staff-name">{{ item.name }}</text><text class="staff-role">{{ item.role_name }}</text></view><strong>{{ item.count }} 项</strong></view></view>
            <view class="section"><text class="section-title">货盘结构</text><view v-if="!snapshot.categories?.length" class="empty-line">暂无分类数据</view><view v-for="item in snapshot.categories || []" :key="item.key" class="category-row"><text class="category-name">{{ item.name }}</text><view><text>入库 {{ item.in_count || 0 }}</text><text>销售 {{ item.sale_count || 0 }}</text><text>库存 {{ item.stock_count || 0 }}</text></view></view></view>
            <view class="section"><text class="section-title">当前待处理</text><view class="todo-grid"><view v-for="item in todos" :key="item.label"><strong>{{ item.value }}</strong><text>{{ item.label }}</text></view></view></view>
            <view class="section"><text class="section-title">数据来源</text><view v-for="item in report.providers_json || []" :key="item.provider" class="provider"><view class="dot" :class="{ off: !item.available }"></view><text>{{ item.provider_name }}</text><text class="provider-state">{{ item.available ? '已接入' : (item.error || '采集异常') }}</text></view></view>
        </template>
        <view v-else class="state"><u-empty mode="data" text="报告不存在" /></view>
    </view>
</template>

<script setup lang="ts">
import { onLoad } from '@dcloudio/uni-app'
import { computed, ref } from 'vue'
import { getMobilePerformanceReport } from '@/addon/hsx_performance/api'
const report = ref<any>(null), loading = ref(true)
const snapshot = computed(() => report.value?.snapshot_json || {})
const quantity = (value: any) => { const number = Number(value || 0); return Number.isInteger(number) ? String(number) : number.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') }
const periodMetrics = computed(() => { const s = snapshot.value.summary || {}; return [
    ...(Object.prototype.hasOwnProperty.call(s, 'sale_amount') ? [{ label: '销售额', value: `¥${Number(s.sale_amount || 0).toFixed(2)}` }] : []),
    ...(Object.prototype.hasOwnProperty.call(s, 'sale_profit') ? [{ label: '销售毛利', value: `¥${Number(s.sale_profit || 0).toFixed(2)}`, className: Number(s.sale_profit || 0) < 0 ? 'negative' : 'positive' }] : []),
    ...(Object.prototype.hasOwnProperty.call(s, 'gross_margin_rate') ? [{ label: '毛利率', value: `${Number(s.gross_margin_rate || 0).toFixed(2)}%` }] : []),
    { label: '销售件数', value: `${quantity(s.sale_count)} 件` }, { label: '回收入库', value: `${quantity(s.recycle_in_count)} 台` }, { label: '设备动销率', value: `${Number(s.turnover_rate || 0).toFixed(2)}%` },
] })
const realtimeMetrics = computed(() => { const s = snapshot.value.summary || {}; return [
    { label: '当前库存', value: `${quantity(s.stock_count)} 台` },
    ...(Object.prototype.hasOwnProperty.call(s, 'stock_cost') ? [{ label: '库存成本', value: `¥${Number(s.stock_cost || 0).toFixed(2)}` }] : []),
    { label: '当前待处理', value: `${Number(s.todo_count ?? Object.values(snapshot.value.todos || {}).reduce((sum: number, value: any) => sum + Number(value || 0), 0))} 项` },
] })
const todoLabels: Record<string, string> = { recycle_pending_sign: '待签收', recycle_pending_check: '待质检', recycle_pending_price: '待定价', need_photo: '待拍照', need_price: '待销售定价', need_material: '待完善资料', ready: '待上架', payable: '待付款', receivable: '待收款' }
const todos = computed(() => Object.entries(snapshot.value.todos || {}).map(([key, value]) => ({ label: todoLabels[key] || key, value: Number(value || 0) })).filter(item => item.value > 0))
const date = (value: any) => value ? new Date(Number(value) * 1000).toLocaleDateString('zh-CN').replaceAll('/', '-') : '—'
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
const typeName = (value: string) => ({ daily: '经营日报', weekly: '经营周报', monthly: '经营月报' }[value] || '经营报告')
onLoad(async options => { try { const id = Number(options?.id || 0); if (id > 0) report.value = (await getMobilePerformanceReport(id)).data || null } finally { loading.value = false } })
</script>

<style scoped lang="scss">
.page { min-height: 100vh; padding: 26rpx; box-sizing: border-box; background: #f3f4f6; }.hero { padding: 28rpx; color: #fff; background: #1d4ed8; border-radius: 16rpx; }.hero-title { display: block; font-size: 34rpx; font-weight: 700; }.hero-period { display: block; margin-top: 8rpx; color: #dbeafe; font-size: 23rpx; }.hero-meta { margin-top: 24rpx; display: flex; justify-content: space-between; color: #bfdbfe; font-size: 21rpx; }.scope-tip { margin-top: 16rpx; padding: 16rpx 20rpx; color: #64748b; font-size: 21rpx; line-height: 1.55; background: #eaf1ff; border-radius: 12rpx; }
.metric-grid { margin-top: 18rpx; display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rpx; overflow: hidden; background: #e5e7eb; border: 2rpx solid #e5e7eb; border-radius: 16rpx; }.metric { min-height: 112rpx; padding: 22rpx; display: flex; flex-direction: column; justify-content: space-between; background: #fff; }.metric text { color: #64748b; font-size: 22rpx; }.metric strong { color: #111827; font-size: 31rpx; }.positive { color: #059669 !important; }.negative { color: #dc2626 !important; }.snapshot-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12rpx; }.snapshot-grid view { padding: 18rpx; display: flex; flex-direction: column; background: #f8fafc; border-radius: 12rpx; }.snapshot-grid text { color: #64748b; font-size: 21rpx; }.snapshot-grid strong { margin-top: 7rpx; color: #111827; font-size: 27rpx; }
.section { margin-top: 18rpx; padding: 24rpx; background: #fff; border: 2rpx solid #e8edf3; border-radius: 16rpx; }.section-title { display: block; margin-bottom: 14rpx; color: #111827; font-size: 27rpx; font-weight: 650; }.staff-row, .category-row, .provider { min-height: 76rpx; display: flex; align-items: center; justify-content: space-between; border-top: 2rpx solid #f1f5f9; }.staff-row:first-of-type, .category-row:first-of-type, .provider:first-of-type { border-top: 0; }.staff-name { color: #111827; font-size: 25rpx; }.staff-role { margin-left: 12rpx; color: #64748b; font-size: 21rpx; }.staff-row strong { color: #3b6ef5; font-size: 25rpx; }.category-name { max-width: 220rpx; color: #111827; font-size: 24rpx; }.category-row view { display: flex; gap: 16rpx; color: #64748b; font-size: 20rpx; }.todo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12rpx; }.todo-grid view { min-height: 94rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; border-radius: 12rpx; }.todo-grid strong { color: #dc2626; font-size: 28rpx; }.todo-grid text { margin-top: 5rpx; color: #64748b; font-size: 20rpx; }.provider { justify-content: flex-start; gap: 12rpx; }.dot { width: 14rpx; height: 14rpx; background: #10b981; border-radius: 50%; }.dot.off { background: #ef4444; }.provider text { color: #334155; font-size: 23rpx; }.provider-state { margin-left: auto; color: #64748b !important; font-size: 20rpx !important; }.empty-line { padding: 24rpx 0; color: #94a3b8; font-size: 22rpx; text-align: center; }.state { height: 700rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18rpx; color: #94a3b8; font-size: 24rpx; }
</style>
