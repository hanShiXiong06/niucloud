<template>
    <view class="page">
        <view v-if="loading" class="state"><u-loading-icon size="28" /><text>正在读取员工产出</text></view>
        <template v-else>
            <view class="employee-head"><view class="avatar">{{ initial }}</view><view><text class="name">{{ data.employee?.employee_name || '员工产出' }}</text><text class="period">{{ data.range?.start_date }} 至 {{ data.range?.end_date }}</text></view></view>
            <view class="summary"><view><strong>{{ data.employee?.completed_count || 0 }}</strong><text>完成事项</text></view><view><strong>{{ data.employee?.metric_count || 0 }}</strong><text>指标数</text></view><view><strong>¥{{ money(data.employee?.amount) }}</strong><text>业务金额</text></view></view>
            <view class="section-title">指标构成</view>
            <view class="metric-list"><view v-for="row in data.metrics" :key="row.metric_key" class="metric"><view><text class="metric-name">{{ row.metric_name }}</text><text class="metric-meta">{{ scopeName(row.fact_scope) }}</text></view><text class="metric-count">{{ row.completed_count }}</text></view></view>
            <view class="section-title">事实明细</view>
            <view v-if="!data.facts?.length" class="empty">暂无事实明细</view>
            <view v-else class="facts"><view v-for="row in data.facts" :key="row.id" class="fact"><view class="dot" :class="{ reversal: row.fact_type === 'reversal' }" /><view class="fact-main"><view class="fact-title"><text>{{ row.metric_name }}</text><text :class="{ negative: row.fact_type === 'reversal' }">{{ row.fact_type === 'reversal' ? '冲红' : `+${Number(row.quantity || 0)}` }}</text></view><text class="fact-object">{{ row.business_no || row.imei || `${row.business_type} #${row.business_id}` }}</text><text v-if="row.imei" class="imei">IMEI {{ row.imei }}</text><text class="fact-time">{{ time(row.occurred_at) }}</text></view></view></view>
        </template>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getMobilePerformanceOutputEmployee } from '@/addon/hsx_performance/api'
const params = reactive({ uid: 0, period: 'month', source_plugin: '' })
const data = reactive<any>({ employee: null, metrics: [], facts: [], range: {} })
const loading = ref(false)
const initial = computed(() => String(data.employee?.employee_name || '员').slice(0, 1))
const load = async () => { if (!params.uid) return; loading.value = true; try { Object.assign(data, (await getMobilePerformanceOutputEmployee(params.uid, { period: params.period, source_plugin: params.source_plugin })).data || {}) } finally { loading.value = false } }
const money = (value: any) => Number(value || 0).toFixed(2)
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
const scopeName = (value: string) => ({ action: '工作动作', outcome: '有效结果', quality: '质量事项' }[value] || value)
onLoad((options: any) => { params.uid = Number(options?.uid || 0); params.period = String(options?.period || 'month'); params.source_plugin = String(options?.source_plugin || ''); load() })
</script>

<style scoped lang="scss">
.page{min-height:100vh;padding:28rpx;box-sizing:border-box;background:#f5f7fa}.employee-head{padding:20rpx 4rpx;display:flex;align-items:center;gap:20rpx}.avatar{width:82rpx;height:82rpx;display:flex;align-items:center;justify-content:center;color:#fff;font-size:32rpx;font-weight:700;background:#2563eb;border-radius:12rpx}.name{display:block;color:#111827;font-size:34rpx;font-weight:700}.period{display:block;margin-top:6rpx;color:#64748b;font-size:21rpx}.summary{margin-top:12rpx;display:grid;grid-template-columns:repeat(3,1fr);background:#fff;border:2rpx solid #e5e7eb;border-radius:12rpx}.summary view{padding:22rpx 8rpx;display:flex;flex-direction:column;align-items:center;border-right:2rpx solid #edf0f4}.summary view:last-child{border-right:0}.summary strong{max-width:100%;overflow:hidden;color:#111827;font-size:30rpx;text-overflow:ellipsis}.summary text{margin-top:6rpx;color:#64748b;font-size:20rpx}.section-title{margin:30rpx 2rpx 14rpx;color:#111827;font-size:28rpx;font-weight:650}
.metric-list,.facts,.empty{background:#fff;border:2rpx solid #e5e7eb;border-radius:12rpx}.metric{min-height:96rpx;padding:18rpx 22rpx;display:flex;align-items:center;justify-content:space-between;border-bottom:2rpx solid #edf0f4}.metric:last-child{border-bottom:0}.metric-name{display:block;color:#111827;font-size:26rpx;font-weight:600}.metric-meta{display:block;margin-top:4rpx;color:#94a3b8;font-size:20rpx}.metric-count{color:#111827;font-size:30rpx;font-weight:700}.fact{padding:22rpx;display:flex;gap:18rpx;border-bottom:2rpx solid #edf0f4}.fact:last-child{border-bottom:0}.dot{width:14rpx;height:14rpx;margin-top:12rpx;flex:none;background:#16a34a;border-radius:50%}.dot.reversal{background:#dc2626}.fact-main{min-width:0;flex:1}.fact-title{display:flex;align-items:center;justify-content:space-between;color:#111827;font-size:25rpx;font-weight:600}.fact-object,.imei,.fact-time{display:block;margin-top:7rpx;color:#64748b;font-size:21rpx}.imei{color:#475569}.fact-time{color:#94a3b8}.negative{color:#dc2626}.empty{padding:80rpx 20rpx;color:#94a3b8;font-size:23rpx;text-align:center}.state{height:700rpx;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18rpx;color:#94a3b8;font-size:24rpx}
</style>
