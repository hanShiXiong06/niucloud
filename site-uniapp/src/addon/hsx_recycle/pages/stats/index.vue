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
    { label: '本周', value: 'week' },
    { label: '本月', value: 'month' },
]

const businessMetrics = computed(() => [
    { key: 'order', label: '新增订单', value: statsData.value.today_order_count || 0, unit: '单', color: 'text-blue' },
    { key: 'consignment', label: '新增代卖', value: statsData.value.today_consignment_count || 0, unit: '单', color: 'text-purple' },
    { key: 'return', label: '退货设备', value: statsData.value.today_return_count || 0, unit: '台', color: 'text-gray' },
    { key: 'check', label: '质检完成', value: statsData.value.today_check_count || 0, unit: '台', color: 'text-green' },
])

const todoMetrics = computed(() => [
    { key: 'pending_check', label: '待质检', value: statsData.value.pending_check || 0 },
    { key: 'pending_confirm', label: '待确认', value: statsData.value.pending_confirm_count || 0 },
    { key: 'pending_pay', label: '待打款', value: statsData.value.pending_pay || 0 },
    { key: 'pending_return', label: '待退回', value: statsData.value.pending_return || 0 },
    { key: 'listing', label: '挂牌中', value: statsData.value.consignment_listing_count || 0 },
])

const financeMetrics = computed(() => [
    { key: 'payment', label: '打款金额', value: formatMoney(statsData.value.today_payment_amount), unit: '元', color: 'text-red' },
    { key: 'sold', label: '代卖成交额', value: formatMoney(statsData.value.consignment_sold_amount), unit: '元', color: 'text-green' },
    { key: 'fee', label: '代卖收益', value: formatMoney(statsData.value.consignment_service_fee), unit: '元', color: 'text-teal' },
])

const formatMoney = (val: any) => Number(val || 0).toFixed(2)

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
        const day = now.getDay() || 7
        const monday = new Date(now.getTime() - (day - 1) * 86400000)
        start = formatDate(monday)
        end = formatDate(now)
    } else if (type === 'month') {
        start = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`
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