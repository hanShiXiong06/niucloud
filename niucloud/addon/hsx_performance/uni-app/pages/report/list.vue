<template>
    <view class="page">
        <view class="head"><view><text class="title">经营报告</text><text class="subtitle">店铺经营与员工产出快照</text></view><view class="refresh" @click="load(true)"><u-icon name="reload" color="#2563eb" size="20" /></view></view>
        <scroll-view scroll-x class="tabs" :show-scrollbar="false"><view class="tabs-inner"><view v-for="item in types" :key="item.value" class="tab" :class="{ active: type === item.value }" @click="changeType(item.value)">{{ item.label }}</view></view></scroll-view>
        <view v-if="loading" class="state"><u-loading-icon size="28" /><text>正在读取经营报告</text></view>
        <view v-else-if="!rows.length" class="state"><u-empty mode="data" text="暂无经营报告" /></view>
        <view v-else class="list">
            <view v-for="row in rows" :key="row.id" class="report" @click="open(row.id)">
                <view class="report-head"><view><text class="report-title">{{ row.title }}</text><text class="period">{{ date(row.start_at) }} 至 {{ date(row.end_at) }}</text></view><u-icon name="arrow-right" color="#94a3b8" size="17" /></view>
                <view class="metrics"><view><strong>{{ summary(row).recycle_in_count || 0 }}</strong><text>回收入库</text></view><view><strong>{{ summary(row).sale_count || 0 }}</strong><text>销售</text></view><view><strong>{{ summary(row).stock_count || 0 }}</strong><text>库存</text></view><view><strong>{{ Number(summary(row).turnover_rate || 0).toFixed(1) }}%</strong><text>动销率</text></view></view>
                <view class="report-foot"><text>{{ typeName(row.report_type) }}</text><text :class="`notify-${row.notify_status}`">{{ notifyName(row.notify_status) }}</text></view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { onShow } from '@dcloudio/uni-app'
import { ref } from 'vue'
import { getMobilePerformanceReports } from '@/addon/hsx_performance/api'
const types = [{ label: '全部', value: '' }, { label: '日报', value: 'daily' }, { label: '周报', value: 'weekly' }, { label: '月报', value: 'monthly' }]
const type = ref(''), rows = ref<any[]>([]), loading = ref(false)
const load = async (showToast = false) => { loading.value = true; try { const data: any = (await getMobilePerformanceReports({ report_type: type.value, page: 1, limit: 50 })).data || {}; rows.value = data.data || []; if (showToast) uni.showToast({ title: '已刷新', icon: 'none' }) } finally { loading.value = false } }
const changeType = (value: string) => { type.value = value; load() }
const open = (id: number) => uni.navigateTo({ url: `/addon/hsx_performance/pages/report/detail?id=${id}` })
const summary = (row: any) => row.summary_json || {}
const date = (value: any) => value ? new Date(Number(value) * 1000).toLocaleDateString('zh-CN').replace(/\//g, '-') : '—'
const typeName = (value: string) => ({ daily: '日报', weekly: '周报', monthly: '月报' }[value] || '报告')
const notifyName = (value: string) => ({ success: '已进入通知队列', pending: '待入队', failed: '入队失败', skipped: '待补充配置' }[value] || value)
onShow(() => load())
</script>

<style scoped lang="scss">
.page { min-height: 100vh; padding: 28rpx; box-sizing: border-box; background: #f5f7fa; }
.head { display: flex; align-items: center; justify-content: space-between; }.title { display: block; color: #111827; font-size: 38rpx; font-weight: 700; }.subtitle { display: block; margin-top: 6rpx; color: #64748b; font-size: 23rpx; }.refresh { width: 68rpx; height: 68rpx; display: flex; align-items: center; justify-content: center; background: #fff; border: 2rpx solid #e5e7eb; border-radius: 12rpx; }
.tabs { margin: 24rpx 0 18rpx; white-space: nowrap; }.tabs-inner { display: inline-flex; gap: 12rpx; }.tab { min-width: 96rpx; padding: 14rpx 22rpx; box-sizing: border-box; color: #475569; font-size: 25rpx; text-align: center; background: #fff; border: 2rpx solid #e5e7eb; border-radius: 12rpx; }.tab.active { color: #fff; background: #2563eb; border-color: #2563eb; }
.list { display: flex; flex-direction: column; gap: 18rpx; }.report { padding: 24rpx; background: #fff; border: 2rpx solid #e8edf3; border-radius: 16rpx; }.report-head { display: flex; align-items: flex-start; justify-content: space-between; }.report-title { display: block; color: #111827; font-size: 29rpx; font-weight: 650; }.period { display: block; margin-top: 6rpx; color: #94a3b8; font-size: 21rpx; }.metrics { margin-top: 22rpx; display: grid; grid-template-columns: repeat(4, 1fr); gap: 8rpx; }.metrics view { min-width: 0; display: flex; flex-direction: column; }.metrics strong { color: #111827; font-size: 28rpx; }.metrics text { margin-top: 4rpx; color: #64748b; font-size: 20rpx; }.report-foot { margin-top: 20rpx; padding-top: 16rpx; display: flex; justify-content: space-between; color: #64748b; font-size: 21rpx; border-top: 2rpx solid #f1f5f9; }.notify-success { color: #059669; }.notify-failed { color: #dc2626; }.notify-pending { color: #d97706; }.state { height: 500rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18rpx; color: #94a3b8; font-size: 24rpx; }
</style>
