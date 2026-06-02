<template>
    <view class="stats-page">
        <view class="date-filter">
            <view
                v-for="item in dateOptions"
                :key="item.value"
                class="date-chip"
                :class="{ 'date-chip--active': currentDate === item.value }"
                @click="switchDate(item.value)"
            >
                <text>{{ item.label }}</text>
            </view>
        </view>

        <view v-if="loading" class="loading-box">
            <text>加载中...</text>
        </view>

        <template v-else>
            <view class="section">
                <view class="section-title">业务概览</view>
                <view class="metric-grid">
                    <view class="metric-card" v-for="item in businessMetrics" :key="item.key">
                        <text class="metric-card__value" :class="item.color">{{ item.value }}</text>
                        <text class="metric-card__label">{{ item.label }}</text>
                        <text class="metric-card__unit">{{ item.unit }}</text>
                    </view>
                </view>
            </view>

            <view class="section">
                <view class="section-title">待办事项</view>
                <view class="todo-grid">
                    <view class="todo-card" v-for="item in todoMetrics" :key="item.key">
                        <text class="todo-card__value">{{ item.value }}</text>
                        <text class="todo-card__label">{{ item.label }}</text>
                    </view>
                </view>
            </view>

            <view class="section">
                <view class="section-title">资金数据</view>
                <view class="metric-grid">
                    <view class="metric-card" v-for="item in financeMetrics" :key="item.key">
                        <text class="metric-card__value" :class="item.color">{{ item.value }}</text>
                        <text class="metric-card__label">{{ item.label }}</text>
                        <text class="metric-card__unit">{{ item.unit }}</text>
                    </view>
                </view>
            </view>
        </template>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getDashboardOverview } from '@/addon/hsx_recycle/api/stats'

const loading = ref(true)
const currentDate = ref('today')
const statsData = ref<Record<string, any>>({})

const dateOptions = [
    { label: '今日', value: 'today' },
    { label: '昨日', value: 'yesterday' },
    { label: '近7天', value: 'week' },
    { label: '近30天', value: 'month' },
]

const businessMetrics = computed(() => [
    { key: 'signed', label: '签收手机', value: ledgerValue('signed_device_count'), unit: '台', color: 'text-blue' },
    { key: 'order', label: '新增订单', value: ledgerValue('order_count', 'today_order_count'), unit: '单', color: 'text-purple' },
    { key: 'completed', label: '已完成', value: ledgerValue('completed_device_count'), unit: '台', color: 'text-green' },
    { key: 'return', label: '退货设备', value: ledgerValue('return_device_count', 'today_return_count'), unit: '台', color: 'text-gray' },
])

const todoMetrics = computed(() => [
    { key: 'pending_check', label: '待质检', value: ledgerValue('pending_check_device_count', 'pending_check') },
    { key: 'checking', label: '质检中', value: ledgerValue('checking_device_count', 'checking_count') },
    { key: 'pending_confirm', label: '待确认', value: ledgerValue('pending_confirm_count') },
    { key: 'pending_pay', label: '待打款', value: ledgerValue('pending_pay_device_count', 'pending_pay') },
    { key: 'pending_return', label: '退货待处理', value: ledgerValue('pending_return_count', 'pending_return') },
    { key: 'listing', label: '挂牌中', value: statsData.value.consignment_listing_count || 0 },
])

const financeMetrics = computed(() => [
    { key: 'payment', label: '所选时间打款', value: formatMoney(financeValue('selected_paid_amount', 'selected_payment_amount')), unit: '元', color: 'text-red' },
    { key: 'today_payment', label: '今日打款', value: formatMoney(financeValue('today_paid_amount', 'today_payment_amount')), unit: '元', color: 'text-blue' },
    { key: 'week_payment', label: '近7天打款', value: formatMoney(financeValue('last_7_days_paid_amount', 'week_payment_amount')), unit: '元', color: 'text-purple' },
    { key: 'month_payment', label: '近30天打款', value: formatMoney(financeValue('last_30_days_paid_amount', 'month_payment_amount')), unit: '元', color: 'text-green' },
    { key: 'sold', label: '代卖成交额', value: formatMoney(statsData.value.consignment_sold_amount), unit: '元', color: 'text-green' },
])

const formatMoney = (val: any) => Number(val || 0).toFixed(2)

const ledgerValue = (key: string, fallbackKey = '') => Number(statsData.value.ledger?.[key] ?? (fallbackKey ? statsData.value[fallbackKey] : statsData.value[key]) ?? 0)

const financeValue = (key: string, fallbackKey = '') => {
    const finance = statsData.value.finance_summary || {}
    if (finance[key] !== undefined) return finance[key]
    if (key === 'last_7_days_paid_amount' && finance.week_paid_amount !== undefined) return finance.week_paid_amount
    if (key === 'last_30_days_paid_amount' && finance.month_paid_amount !== undefined) return finance.month_paid_amount
    return (fallbackKey ? statsData.value[fallbackKey] : statsData.value[key]) ?? 0
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

const formatDate = (d: Date) => {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const loadStats = async () => {
    loading.value = true
    try {
        const params = getDateRange(currentDate.value)
        const res: any = await getDashboardOverview(params)
        statsData.value = res.data || res || {}
    } catch (e) {
        statsData.value = {}
    } finally {
        loading.value = false
    }
}

const switchDate = (value: string) => {
    currentDate.value = value
    loadStats()
}

onLoad(() => {
    loadStats()
})
</script>

<style scoped lang="scss">
.stats-page {
    min-height: 100vh;
    background: #f5f7fa;
    padding: 20rpx;
}

.date-filter {
    display: flex;
    gap: 16rpx;
    margin-bottom: 24rpx;
    padding: 8rpx 0;
}

.date-chip {
    flex: 1;
    height: 64rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12rpx;
    font-size: 26rpx;
    color: #666;
    background: #fff;
    border: 1rpx solid #e5e7eb;
}

.date-chip--active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
    font-weight: 500;
}

.loading-box {
    padding: 200rpx 0;
    text-align: center;
    color: #999;
    font-size: 28rpx;
}

.section {
    margin-bottom: 24rpx;
}

.section-title {
    font-size: 28rpx;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 16rpx;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16rpx;
}

.metric-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    display: flex;
    flex-direction: column;
}

.metric-card__value {
    font-size: 40rpx;
    font-weight: 700;
    color: #1f2937;
}

.metric-card__label {
    font-size: 24rpx;
    color: #6b7280;
    margin-top: 8rpx;
}

.metric-card__unit {
    font-size: 20rpx;
    color: #9ca3af;
    margin-top: 4rpx;
}

.todo-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16rpx;
}

.todo-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 20rpx;
    text-align: center;
     display: flex;
    flex-direction: column;
    align-items: center;
}

.todo-card__value {
    font-size: 36rpx;
    font-weight: 700;
    color: #ea580c;
}

.todo-card__label {
    font-size: 22rpx;
    color: #6b7280;
    margin-top: 8rpx;
}

.text-blue { color: #2563eb; }
.text-green { color: #16a34a; }
.text-red { color: #dc2626; }
.text-purple { color: #7c3aed; }
.text-gray { color: #6b7280; }
.text-teal { color: #0d9488; }
</style>
