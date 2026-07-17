<template>
    <view class="mc-page dashboard-page">
        <view class="business-card mc-surface">
            <view class="business-card__top">
                <view class="brand-mark">
                    <u-icon name="order" color="#ffffff" size="24" />
                </view>
                <view class="business-card__copy">
                    <text class="business-card__title">会员服务</text>
                    <text class="business-card__sub">开卡、收款与核销一站完成</text>
                </view>
                <view class="active-count">
                    <strong>{{ overview.active_card_count || 0 }}</strong>
                    <text>有效卡</text>
                </view>
            </view>
            <view class="business-card__tip">
                <u-icon name="info-circle" color="#64748b" size="15" />
                <text>手机号后四位配合姓名核验，服务更快也更稳妥</text>
            </view>
        </view>

        <view class="quick-grid">
            <view class="quick-card quick-card--primary" @click="go('/addon/hsx_member_card/pages/order/create')">
                <view class="quick-card__icon"><u-icon name="plus" color="#ffffff" size="23" /></view>
                <view class="quick-card__copy">
                    <text>快速开卡</text>
                    <small>选择客户与卡种，完成收款</small>
                </view>
                <u-icon name="arrow-right" color="rgba(255,255,255,.75)" size="16" />
            </view>
            <view class="quick-card mc-surface" @click="go('/addon/hsx_member_card/pages/card/search')">
                <view class="quick-card__icon quick-card__icon--light"><u-icon name="phone" color="#2563eb" size="23" /></view>
                <view class="quick-card__copy">
                    <text>手机号核销</text>
                    <small>后四位 + 姓名当面核对</small>
                </view>
                <u-icon name="arrow-right" color="#94a3b8" size="16" />
            </view>
        </view>

        <view class="mc-section-head">
            <view class="mc-section-head__main">
                <text class="mc-section-head__title">今日经营</text>
                <text class="mc-section-head__sub">实时数据</text>
            </view>
            <view class="refresh" @click="load">
                <u-icon name="reload" color="#2563eb" size="15" />
                <text>刷新</text>
            </view>
        </view>

        <view class="metrics mc-surface">
            <view v-for="item in metrics" :key="item.label" class="metric">
                <view class="metric__head">
                    <view class="metric__icon" :class="item.tone">
                        <u-icon :name="item.icon" :color="item.iconColor" size="17" />
                    </view>
                    <text>{{ item.label }}</text>
                </view>
                <strong :class="item.color">{{ item.value }}</strong>
                <small>{{ item.help }}</small>
            </view>
        </view>

        <view class="mc-section-head">
            <view class="mc-section-head__main"><text class="mc-section-head__title">业务管理</text></view>
        </view>
        <view class="entry-list mc-surface">
            <u-cell-group :border="false">
                <u-cell title="开卡订单" label="查看收款状态与异常订单" isLink @click="go('/addon/hsx_member_card/pages/order/list')">
                    <template #icon><view class="mc-icon-box"><u-icon name="order" color="#2563eb" size="20" /></view></template>
                </u-cell>
                <u-cell title="核销记录" label="追溯服务人员与冲正记录" isLink @click="go('/addon/hsx_member_card/pages/redemption/list')">
                    <template #icon><view class="mc-icon-box mc-icon-box--green"><u-icon name="checkmark-circle" color="#16a34a" size="20" /></view></template>
                </u-cell>
            </u-cell-group>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMemberCardDashboard } from '../../api'

const overview = ref<any>({})
const money = (value: any) => Number(value || 0).toFixed(2)
const metrics = computed(() => [
    { label: '今日开卡', value: `${overview.value.issued_count || 0} 张`, help: '有效开卡订单', icon: 'order', tone: 'blue', iconColor: '#2563eb' },
    { label: '实际收款', value: `¥${money(overview.value.actual_received_amount)}`, help: 'ERP 已确认', icon: 'account', tone: 'green', iconColor: '#16a34a', color: 'green' },
    { label: '待收款', value: `¥${money(overview.value.pending_receivable_amount)}`, help: '财务待跟进', icon: 'clock', tone: 'orange', iconColor: '#d97706', color: 'orange' },
    { label: '今日核销', value: `${overview.value.redemption_count || 0} 次`, help: `确认收入 ¥${money(overview.value.recognized_amount)}`, icon: 'checkmark-circle', tone: 'violet', iconColor: '#7c3aed' },
])

const load = async () => {
    try {
        overview.value = ((await getMemberCardDashboard()) as any)?.data || {}
    } catch {}
}
const go = (url: string) => uni.navigateTo({ url })
onShow(load)
</script>

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';

.business-card { padding: 28rpx; overflow: hidden; }
.business-card__top { display: flex; align-items: center; gap: 18rpx; }
.brand-mark { display: flex; width: 76rpx; height: 76rpx; flex: 0 0 76rpx; align-items: center; justify-content: center; border-radius: 19rpx; background: linear-gradient(145deg, #2563eb, #1d4ed8); box-shadow: 0 10rpx 24rpx rgba(37, 99, 235, .2); }
.business-card__copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 6rpx; }
.business-card__title { font-size: 36rpx; font-weight: 750; }
.business-card__sub { color: #64748b; font-size: 22rpx; }
.active-count { display: flex; min-width: 100rpx; padding-left: 18rpx; border-left: 1rpx solid #e6ebf2; flex-direction: column; align-items: center; }
.active-count strong { color: #2563eb; font-size: 34rpx; }
.active-count text { margin-top: 3rpx; color: #94a3b8; font-size: 20rpx; }
.business-card__tip { display: flex; margin-top: 24rpx; padding: 16rpx 18rpx; align-items: center; gap: 10rpx; border-radius: 12rpx; background: #f8fafc; color: #64748b; font-size: 21rpx; }

.quick-grid { display: grid; grid-template-columns: 1fr; gap: 16rpx; margin-top: 20rpx; }
.quick-card { display: flex; min-height: 116rpx; padding: 22rpx 24rpx; box-sizing: border-box; align-items: center; gap: 18rpx; }
.quick-card--primary { border: 1rpx solid #2563eb; border-radius: 18rpx; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; box-shadow: 0 10rpx 24rpx rgba(37, 99, 235, .16); }
.quick-card__icon { display: flex; width: 64rpx; height: 64rpx; flex: 0 0 64rpx; align-items: center; justify-content: center; border-radius: 16rpx; background: rgba(255, 255, 255, .16); }
.quick-card__icon--light { background: #eff6ff; }
.quick-card__copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 7rpx; }
.quick-card__copy text { font-size: 28rpx; font-weight: 700; }
.quick-card__copy small { color: #94a3b8; font-size: 21rpx; }
.quick-card--primary small { color: rgba(255, 255, 255, .74); }

.refresh { display: flex; padding: 8rpx 2rpx; align-items: center; gap: 7rpx; color: #2563eb; font-size: 23rpx; }
.metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); overflow: hidden; }
.metric { display: flex; min-height: 148rpx; padding: 22rpx; box-sizing: border-box; flex-direction: column; justify-content: space-between; }
.metric:nth-child(odd) { border-right: 1rpx solid #edf1f6; }
.metric:nth-child(-n + 2) { border-bottom: 1rpx solid #edf1f6; }
.metric__head { display: flex; align-items: center; gap: 10rpx; color: #64748b; font-size: 21rpx; }
.metric__icon { display: flex; width: 38rpx; height: 38rpx; align-items: center; justify-content: center; border-radius: 10rpx; background: #eff6ff; }
.metric__icon.green { background: #ecfdf3; }
.metric__icon.orange { background: #fff7ed; }
.metric__icon.violet { background: #f5f3ff; }
.metric strong { margin-top: 14rpx; font-size: 30rpx; }
.metric strong.green { color: #16a34a; }
.metric strong.orange { color: #d97706; }
.metric small { margin-top: 5rpx; color: #94a3b8; font-size: 20rpx; }
.entry-list { overflow: hidden; }
.entry-list :deep(.u-cell__body) { padding: 24rpx; }
</style>
