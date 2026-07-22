<template>
    <view class="mc-page dashboard-page">
        <view class="dashboard-hero mc-surface">
            <view class="hero-main">
                <view class="hero-icon"><u-icon name="account" color="#ffffff" size="23" /></view>
                <view class="hero-copy"><strong>会员服务</strong><text>开卡、收款、核销一站完成</text></view>
                <view class="hero-count"><strong>{{ overview.active_card_count || 0 }}</strong><text>有效卡</text></view>
            </view>
            <view class="hero-actions">
                <view @click="go('/addon/hsx_member_card/pages/order/create')"><u-icon name="plus" color="#2563eb" size="19" /><text>快速开卡</text></view>
                <view @click="go('/addon/hsx_member_card/pages/card/search')"><u-icon name="checkmark-circle" color="#16a34a" size="19" /><text>手机号核销</text></view>
            </view>
        </view>

        <view class="stat-switch mc-surface">
            <view :class="{ active: statMode === 'sale' }" @click="statMode = 'sale'">开卡统计</view>
            <view :class="{ active: statMode === 'redeem' }" @click="statMode = 'redeem'">核销统计</view>
        </view>

        <view class="period-panel mc-surface">
            <view class="period-list">
                <view v-for="item in periods" :key="item.value" class="period-chip" :class="{ active: period === item.value }" @click="switchPeriod(item.value)">{{ item.label }}</view>
            </view>
            <view v-if="period === 'custom'" class="custom-period" @click="calendarShow = true"><u-icon name="calendar" color="#2563eb" size="16" /><text>{{ customRangeText || '请选择统计日期' }}</text><u-icon name="arrow-right" color="#94a3b8" size="14" /></view>
        </view>

        <view class="metric-grid">
            <view v-for="item in metrics" :key="item.label" class="metric-card mc-surface">
                <view class="metric-card__head"><view :class="['metric-icon', item.tone]"><u-icon :name="item.icon" :color="item.color" size="17" /></view><text>{{ item.label }}</text></view>
                <strong :style="{ color: item.valueColor || '#0f172a' }">{{ item.value }}</strong>
                <small>{{ item.help }}</small>
            </view>
        </view>

        <view class="mc-section-head"><view class="mc-section-head__main"><text class="mc-section-head__title">常用管理</text><text class="mc-section-head__sub">高频操作</text></view><view class="refresh" @click="load"><u-icon name="reload" color="#2563eb" size="14" /><text>刷新</text></view></view>
        <view class="manage-grid mc-surface">
            <view v-for="item in manageActions" :key="item.path" @click="go(item.path)">
                <view :class="['manage-icon', item.tone]"><u-icon :name="item.icon" :color="item.color" size="21" /></view>
                <text>{{ item.label }}</text>
                <small>{{ item.help }}</small>
            </view>
        </view>

        <view class="mc-section-head"><view class="mc-section-head__main"><text class="mc-section-head__title">业务记录</text></view></view>
        <view class="entry-list mc-surface">
            <u-cell-group :border="false">
                <u-cell title="开卡订单" label="查看收款状态与异常订单" isLink @click="go('/addon/hsx_member_card/pages/order/list')"><template #icon><view class="mc-icon-box"><u-icon name="order" color="#2563eb" size="19" /></view></template></u-cell>
                <u-cell title="核销记录" label="查看操作人、核销和冲正" isLink @click="go('/addon/hsx_member_card/pages/redemption/list')"><template #icon><view class="mc-icon-box mc-icon-box--green"><u-icon name="checkmark-circle" color="#16a34a" size="19" /></view></template></u-cell>
            </u-cell-group>
        </view>

        <u-calendar :show="calendarShow" mode="range" title="选择统计日期" :defaultDate="customDates" :monthNum="12" @confirm="onCalendarConfirm" @close="calendarShow = false" />
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
    { label: '会员管理', help: '按姓名手机号找客户', icon: 'account', color: '#2563eb', tone: 'blue', path: '/addon/hsx_member_card/pages/member/list' },
    { label: '卡种设置', help: '售价次数与有效期', icon: 'order', color: '#7c3aed', tone: 'violet', path: '/addon/hsx_member_card/pages/product/list' },
    { label: '快速开卡', help: '选择客户完成收款', icon: 'plus', color: '#16a34a', tone: 'green', path: '/addon/hsx_member_card/pages/order/create' },
    { label: '立即核销', help: '手机号与姓名核验', icon: 'checkmark-circle', color: '#d97706', tone: 'orange', path: '/addon/hsx_member_card/pages/card/search' },
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
@import '../../styles/member-card-mobile.scss';
.dashboard-page { padding-bottom: 42rpx; }
.dashboard-hero { padding: 24rpx; overflow: hidden; }.hero-main { display: flex; align-items: center; gap: 15rpx; }.hero-icon { display: flex; width: 66rpx; height: 66rpx; flex: 0 0 66rpx; align-items: center; justify-content: center; border-radius: 17rpx; background: linear-gradient(145deg,#2563eb,#3b82f6); }.hero-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 5rpx; }.hero-copy strong { font-size: 31rpx; }.hero-copy text { color: #64748b; font-size: 21rpx; }.hero-count { display: flex; padding-left: 17rpx; border-left: 1rpx solid #e6ebf2; flex-direction: column; align-items: center; }.hero-count strong { color: #2563eb; font-size: 30rpx; }.hero-count text { color: #94a3b8; font-size: 18rpx; }
.hero-actions { display: grid; grid-template-columns: repeat(2,1fr); margin-top: 21rpx; overflow: hidden; border: 1rpx solid #e6ebf2; border-radius: 13rpx; background: #f8fafc; }.hero-actions view { display: flex; padding: 17rpx; align-items: center; justify-content: center; gap: 8rpx; font-size: 23rpx; font-weight: 650; }.hero-actions view + view { border-left: 1rpx solid #e6ebf2; }
.stat-switch { display: grid; grid-template-columns: repeat(2,1fr); margin-top: 18rpx; padding: 8rpx; }.stat-switch view { padding: 17rpx; border-radius: 12rpx; color: #64748b; text-align: center; font-size: 25rpx; }.stat-switch view.active { background: #eff6ff; color: #2563eb; font-weight: 700; }
.period-panel { margin-top: 14rpx; padding: 14rpx; }.period-list { display: flex; align-items: center; justify-content: space-between; gap: 5rpx; }.period-chip { padding: 12rpx 18rpx; border-radius: 25rpx; color: #475569; font-size: 22rpx; }.period-chip.active { background: #2563eb; color: #fff; }.custom-period { display: flex; margin-top: 13rpx; padding: 14rpx 16rpx; align-items: center; gap: 9rpx; border-radius: 11rpx; background: #f8fafc; color: #475569; font-size: 21rpx; }.custom-period text { flex: 1; }
.metric-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 14rpx; margin-top: 16rpx; }.metric-card { display: flex; min-height: 150rpx; padding: 20rpx; box-sizing: border-box; flex-direction: column; justify-content: space-between; }.metric-card__head { display: flex; align-items: center; gap: 9rpx; color: #64748b; font-size: 20rpx; }.metric-icon { display: flex; width: 36rpx; height: 36rpx; align-items: center; justify-content: center; border-radius: 9rpx; background: #eff6ff; }.metric-icon.green { background:#ecfdf3 }.metric-icon.orange { background:#fff7ed }.metric-icon.violet { background:#f5f3ff }.metric-icon.red { background:#fef2f2 }.metric-card > strong { margin-top: 13rpx; font-size: 29rpx; }.metric-card > small { margin-top: 4rpx; color: #94a3b8; font-size: 18rpx; }
.refresh { display: flex; align-items: center; gap: 6rpx; color: #2563eb; font-size: 21rpx; }
.manage-grid { display: grid; grid-template-columns: repeat(2,1fr); overflow: hidden; }.manage-grid > view { display: grid; min-height: 128rpx; padding: 21rpx; box-sizing: border-box; grid-template-columns: 48rpx 1fr; grid-template-rows: auto auto; column-gap: 12rpx; align-content: center; }.manage-grid > view:nth-child(odd) { border-right:1rpx solid #edf1f6 }.manage-grid > view:nth-child(-n+2) { border-bottom:1rpx solid #edf1f6 }.manage-icon { display:flex; width:48rpx; height:48rpx; grid-row:1/3; align-items:center; justify-content:center; border-radius:13rpx; background:#eff6ff }.manage-icon.green{background:#ecfdf3}.manage-icon.orange{background:#fff7ed}.manage-icon.violet{background:#f5f3ff}.manage-grid text { font-size:24rpx; font-weight:700 }.manage-grid small { margin-top:4rpx; color:#94a3b8; font-size:18rpx }
.entry-list { overflow:hidden }.entry-list :deep(.u-cell__body) { padding:23rpx }.entry-list .mc-icon-box { width:54rpx; height:54rpx; flex-basis:54rpx; border-radius:14rpx }
</style>
