<template>
    <view class="min-h-screen bg-[#f5f6f8] px-[24rpx] pb-[60rpx] pt-[24rpx] text-[#1f2937]">
        <view class="overflow-hidden rounded-[28rpx] bg-[#2468f2] px-[28rpx] pb-[26rpx] pt-[28rpx] text-white">
            <view class="flex items-center justify-between">
                <view>
                    <text class="block text-[34rpx] font-bold">会员卡</text>
                    <text class="mt-[6rpx] block text-[23rpx] text-[rgba(255,255,255,0.75)]">门店开卡与核销</text>
                </view>
                <view class="rounded-[18rpx] bg-[rgba(255,255,255,0.14)] px-[22rpx] py-[14rpx] text-right">
                    <text class="block text-[36rpx] font-bold leading-none">{{ overview.active_card_count || 0 }}</text>
                    <text class="mt-[6rpx] block text-[20rpx] text-[rgba(255,255,255,0.72)]">有效卡</text>
                </view>
            </view>
            <view class="mt-[28rpx] grid grid-cols-2 gap-[14rpx]">
                <view class="flex h-[78rpx] items-center justify-center gap-[10rpx] rounded-[18rpx] bg-white text-[27rpx] font-semibold text-[#2468f2]" hover-class="opacity-90" @click="go('/addon/hsx_member_card/pages/order/create')">
                    <u-icon name="plus" color="#2468f2" size="19" />
                    <text>快速开卡</text>
                </view>
                <view class="flex h-[78rpx] items-center justify-center gap-[10rpx] rounded-[18rpx] bg-[rgba(255,255,255,0.14)] text-[27rpx] font-semibold text-white" hover-class="opacity-80" @click="go('/addon/hsx_member_card/pages/card/search')">
                    <u-icon name="scan" color="#ffffff" size="19" />
                    <text>立即核销</text>
                </view>
            </view>
        </view>

        <view class="mt-[20rpx] overflow-hidden rounded-[24rpx] bg-white">
            <view class="flex items-center border-b border-[#eef1f5] px-[20rpx] py-[18rpx]">
                <view class="grid flex-1 grid-cols-2 rounded-[14rpx] bg-[#f2f4f7] p-[5rpx]">
                    <view class="rounded-[10rpx] py-[12rpx] text-center text-[25rpx]" :class="statMode === 'sale' ? 'bg-white font-semibold text-[#2468f2]' : 'text-[#7b8798]'" @click="statMode = 'sale'">开卡数据</view>
                    <view class="rounded-[10rpx] py-[12rpx] text-center text-[25rpx]" :class="statMode === 'redeem' ? 'bg-white font-semibold text-[#2468f2]' : 'text-[#7b8798]'" @click="statMode = 'redeem'">核销数据</view>
                </view>
                <view class="ml-[14rpx] flex h-[54rpx] w-[54rpx] items-center justify-center rounded-full bg-[#f5f7fa]" hover-class="bg-[#edf1f5]" @click="load">
                    <u-icon name="reload" color="#778397" size="16" />
                </view>
            </view>
            <scroll-view scroll-x class="w-full whitespace-nowrap" :show-scrollbar="false">
                <view class="inline-flex items-center gap-[8rpx] px-[20rpx] py-[16rpx]">
                    <view v-for="item in periods" :key="item.value" class="rounded-full px-[20rpx] py-[10rpx] text-[23rpx]" :class="period === item.value ? 'bg-[#edf4ff] font-semibold text-[#2468f2]' : 'text-[#667085]'" @click="switchPeriod(item.value)">{{ item.label }}</view>
                </view>
            </scroll-view>
            <view v-if="period === 'custom'" class="mx-[20rpx] mb-[12rpx] flex items-center gap-[10rpx] rounded-[14rpx] bg-[#f7f8fa] px-[16rpx] py-[14rpx] text-[23rpx] text-[#667085]" @click="calendarShow = true">
                <u-icon name="calendar" color="#2468f2" size="16" />
                <text class="flex-1">{{ customRangeText || '选择统计日期' }}</text>
                <u-icon name="arrow-right" color="#a3acba" size="14" />
            </view>
            <view class="grid grid-cols-2 border-t border-[#eef1f5]">
                <view v-for="item in metrics" :key="item.label" class="metric-item min-h-[118rpx] px-[22rpx] py-[20rpx]">
                    <view class="flex items-center gap-[8rpx]">
                        <view class="h-[8rpx] w-[8rpx] rounded-full" :style="{ backgroundColor: item.color }"></view>
                        <text class="text-[22rpx] text-[#7b8798]">{{ item.label }}</text>
                    </view>
                    <text class="mt-[10rpx] block truncate text-[32rpx] font-semibold" :style="{ color: item.valueColor || '#253247' }">{{ item.value }}</text>
                </view>
            </view>
        </view>

        <view class="mb-[14rpx] mt-[30rpx] flex items-end justify-between">
            <text class="text-[29rpx] font-bold text-[#263449]">常用功能</text>
            <text class="text-[21rpx] text-[#98a2b3]">高频操作</text>
        </view>
        <view class="grid grid-cols-4 rounded-[24rpx] bg-white px-[8rpx] py-[22rpx]">
            <view v-for="item in manageActions" :key="item.path" class="flex flex-col items-center gap-[11rpx]" hover-class="opacity-70" @click="go(item.path)">
                <view class="flex h-[64rpx] w-[64rpx] items-center justify-center rounded-[18rpx]" :style="{ backgroundColor: item.background }">
                    <u-icon :name="item.icon" :color="item.color" size="20" />
                </view>
                <text class="max-w-full truncate text-[22rpx] font-medium text-[#4b586c]">{{ item.label }}</text>
            </view>
        </view>

        <view class="mb-[14rpx] mt-[30rpx] flex items-end justify-between">
            <text class="text-[29rpx] font-bold text-[#263449]">业务记录</text>
            <text class="text-[21rpx] text-[#98a2b3]">订单与核销留痕</text>
        </view>
        <view class="entry-list overflow-hidden rounded-[24rpx] bg-white">
            <u-cell-group :border="false">
                <u-cell title="开卡订单" label="订单、收款与退款" isLink @click="go('/addon/hsx_member_card/pages/order/list')">
                    <template #icon><view class="mr-[16rpx] flex h-[56rpx] w-[56rpx] items-center justify-center rounded-[16rpx] bg-[#edf4ff]"><u-icon name="order" color="#2468f2" size="19" /></view></template>
                </u-cell>
                <u-cell title="核销记录" label="核销明细与冲正" isLink @click="go('/addon/hsx_member_card/pages/redemption/list')">
                    <template #icon><view class="mr-[16rpx] flex h-[56rpx] w-[56rpx] items-center justify-center rounded-[16rpx] bg-[#eaf9f0]"><u-icon name="checkmark-circle" color="#16a34a" size="19" /></view></template>
                </u-cell>
            </u-cell-group>
        </view>

        <u-calendar
            :show="calendarShow"
            mode="range"
            title="选择统计日期"
            :defaultDate="customDates"
            :monthNum="12"
            @confirm="onCalendarConfirm"
            @close="calendarShow = false"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMemberCardDashboard } from '../../api'

const overview = ref<any>({})
const loading = ref(false)
const statMode = ref('sale')
const period = ref('today')
const calendarShow = ref(false)
const customDates = ref<string[]>([])
const periods = [{ label: '今日', value: 'today' }, { label: '昨日', value: 'yesterday' }, { label: '本月', value: 'month' }, { label: '上月', value: 'last_month' }, { label: '自定义', value: 'custom' }]
const manageActions = [
    { label: '会员管理', icon: 'account', color: '#2563eb', background: '#eff6ff', path: '/addon/hsx_member_card/pages/member/list' },
    { label: '卡种设置', icon: 'order', color: '#7c3aed', background: '#f5f3ff', path: '/addon/hsx_member_card/pages/product/list' },
    { label: '快速开卡', icon: 'plus', color: '#16a34a', background: '#ecfdf3', path: '/addon/hsx_member_card/pages/order/create' },
    { label: '立即核销', icon: 'checkmark-circle', color: '#d97706', background: '#fff7ed', path: '/addon/hsx_member_card/pages/card/search' },
    { label: '收款与耗材', icon: 'rmb-circle', color: '#0891b2', background: '#ecfeff', path: '/addon/hsx_member_card/pages/config/payment' },
]
const money = (value: any) => Number(value || 0).toFixed(2)
const metrics = computed(() => statMode.value === 'sale' ? [
    { label: '开卡总额', value: `¥${money(overview.value.card_sale_amount)}`, help: `${overview.value.issued_count || 0} 张新卡`, icon: 'rmb-circle', color: '#2563eb', tone: 'blue', valueColor: '#2563eb' },
    { label: '实际收款', value: `¥${money(overview.value.actual_received_amount)}`, help: '已进入资金流水', icon: 'account', color: '#16a34a', tone: 'green', valueColor: '#16a34a' },
    { label: '待收款', value: `¥${money(overview.value.pending_receivable_amount)}`, help: '财务待跟进', icon: 'clock', color: '#d97706', tone: 'orange', valueColor: '#d97706' },
    { label: '有效会员卡', value: `${overview.value.active_card_count || 0} 张`, help: '当前仍可使用', icon: 'order', color: '#7c3aed', tone: 'violet' },
] : [
    { label: '核销次数', value: `${overview.value.redemption_count || 0} 次`, help: '本期成功核销', icon: 'checkmark-circle', color: '#16a34a', tone: 'green', valueColor: '#16a34a' },
    { label: '确认收入', value: `¥${money(overview.value.recognized_amount)}`, help: '按权益规则确认', icon: 'rmb-circle', color: '#2563eb', tone: 'blue', valueColor: '#2563eb' },
    { label: '退款金额', value: `¥${money(overview.value.refund_amount)}`, help: '本期已完成退款', icon: 'reload', color: '#dc2626', tone: 'red', valueColor: '#dc2626' },
    { label: '有效会员卡', value: `${overview.value.active_card_count || 0} 张`, help: '当前仍可使用', icon: 'order', color: '#7c3aed', tone: 'violet' },
])
const customRangeText = computed(() => customDates.value.length ? `${customDates.value[0]} 至 ${customDates.value[customDates.value.length - 1]}` : '')
const dayStart = (date: Date) => Math.floor(new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime() / 1000)
const dayEnd = (date: Date) => dayStart(date) + 86399
const range = () => {
    const now = new Date()
    if (period.value === 'yesterday') { const date = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1); return [dayStart(date), dayEnd(date)] }
    if (period.value === 'month') return [Math.floor(new Date(now.getFullYear(), now.getMonth(), 1).getTime() / 1000), Math.floor(now.getTime() / 1000)]
    if (period.value === 'last_month') return [Math.floor(new Date(now.getFullYear(), now.getMonth() - 1, 1).getTime() / 1000), Math.floor(new Date(now.getFullYear(), now.getMonth(), 1).getTime() / 1000) - 1]
    if (period.value === 'custom' && customDates.value.length) return [dateTimestamp(customDates.value[0]), dateTimestamp(customDates.value[customDates.value.length - 1], true)]
    return [dayStart(now), Math.floor(now.getTime() / 1000)]
}
const dateTimestamp = (value: string, end = false) => { const [y, m, d] = value.split('-').map(Number); return Math.floor(new Date(y, m - 1, d, end ? 23 : 0, end ? 59 : 0, end ? 59 : 0).getTime() / 1000) }
const switchPeriod = (value: string) => { period.value = value; if (value === 'custom') { calendarShow.value = true; return } load() }
const onCalendarConfirm = (selected: string[]) => { const dates = (selected || []).filter(Boolean); calendarShow.value = false; if (!dates.length) return; customDates.value = [dates[0], dates[dates.length - 1]]; period.value = 'custom'; load() }
const load = async () => {
    if (loading.value) return
    loading.value = true
    try { const [start_at, end_at] = range(); overview.value = ((await getMemberCardDashboard({ start_at, end_at })) as any)?.data || {} }
    finally { loading.value = false }
}
const go = (url: string) => uni.navigateTo({ url })
onShow(load)
</script>

<style scoped lang="scss">
.metric-item:nth-child(odd) { border-right: 1rpx solid #eef1f5; }
.metric-item:nth-child(-n+2) { border-bottom: 1rpx solid #eef1f5; }
.entry-list :deep(.u-cell__body) { padding: 25rpx 22rpx; }
.entry-list :deep(.u-cell__title-text) { color: #344054; font-size: 27rpx; font-weight: 600; }
.entry-list :deep(.u-cell__label) { color: #98a2b3; font-size: 22rpx; }
</style>
