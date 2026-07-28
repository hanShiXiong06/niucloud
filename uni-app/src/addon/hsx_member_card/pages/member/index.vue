<template>
    <view class="member-card-page">
        <view class="hero-card">
            <view class="hero-card__top">
                <view>
                    <view class="hero-card__eyebrow">MEMBER BENEFITS</view>
                    <view class="hero-card__title">我的会员权益</view>
                </view>
                <view class="hero-card__notice" @tap="subscribeNotice">
                    <u-icon name="bell-fill" color="#ffffff" size="18" />
                    <text>核销通知</text>
                </view>
            </view>
            <view class="summary-grid">
                <view class="summary-item">
                    <text class="summary-value">{{ overview.available_card_count || 0 }}</text>
                    <text class="summary-label">可用卡</text>
                </view>
                <view class="summary-line" />
                <view class="summary-item">
                    <text class="summary-value">{{ overview.remaining_times || 0 }}</text>
                    <text class="summary-label">剩余次数</text>
                </view>
                <view class="summary-line" />
                <view class="summary-item">
                    <text class="summary-value">{{ overview.used_times || 0 }}</text>
                    <text class="summary-label">累计核销</text>
                </view>
            </view>
        </view>

        <view v-if="overview.latest_redemption?.id" class="latest-card">
            <view class="latest-card__icon">
                <u-icon name="checkmark-circle-fill" color="#2f6bff" size="23" />
            </view>
            <view class="latest-card__body">
                <view class="latest-card__title">最近核销 · {{ overview.latest_redemption.item_name }}</view>
                <view class="latest-card__desc">
                    {{ formatDateTime(overview.latest_redemption.occurred_at) }} ·
                    {{ overview.latest_redemption.status_text }}
                </view>
            </view>
            <u-icon name="arrow-right" color="#a7b1c2" size="17" />
        </view>

        <view class="tabs-wrap">
            <view
                v-for="tab in tabs"
                :key="tab.key"
                class="tab-item"
                :class="{ 'tab-item--active': activeTab === tab.key }"
                @tap="switchTab(tab.key)"
            >
                <u-icon :name="tab.icon" :color="activeTab === tab.key ? '#2f6bff' : '#7f8a9c'" size="17" />
                <text>{{ tab.label }}</text>
            </view>
        </view>

        <view class="content-wrap">
            <view v-if="loading && !list.length" class="loading-box">
                <u-loading-icon mode="circle" color="#2f6bff" />
                <text>正在读取会员记录...</text>
            </view>

            <template v-else-if="list.length">
                <template v-if="activeTab === 'cards'">
                    <view v-for="card in list" :key="card.id" class="record-card card-record" @tap="openCard(card)">
                        <view class="record-head">
                            <view class="record-title-wrap">
                                <view class="record-mark">
                                    <u-icon name="coupon-fill" color="#2f6bff" size="22" />
                                </view>
                                <view class="record-title">{{ card.product_name }}</view>
                            </view>
                            <view class="status-chip" :class="statusClass(card.status)">{{ card.status_text }}</view>
                        </view>
                        <view class="card-no">{{ card.card_no }}</view>
                        <view class="benefit-list">
                            <view v-for="item in card.items || []" :key="item.id" class="benefit-row">
                                <view class="benefit-name">{{ item.item_name }}</view>
                                <view class="benefit-count">
                                    <template v-if="item.usage_mode === 'unlimited'">不限次数</template>
                                    <template v-else>
                                        剩 <text>{{ item.remaining_times }}</text> 次
                                    </template>
                                </view>
                            </view>
                        </view>
                        <view class="record-foot">
                            <view class="validity">
                                <u-icon name="clock" color="#97a1b2" size="15" />
                                <text>{{ card.validity_text }}</text>
                            </view>
                            <view class="detail-link">查看详情 <u-icon name="arrow-right" color="#2f6bff" size="14" /></view>
                        </view>
                    </view>
                </template>

                <template v-else-if="activeTab === 'orders'">
                    <view v-for="order in list" :key="order.id" class="record-card">
                        <view class="record-head">
                            <view>
                                <view class="record-title">{{ order.product_name }}</view>
                                <view class="record-subtitle">{{ order.order_no }}</view>
                            </view>
                            <view class="status-chip" :class="statusClass(order.business_status)">
                                {{ order.business_status_text }}
                            </view>
                        </view>
                        <view class="money-row">
                            <view>
                                <text class="money-label">开卡金额</text>
                                <text class="money-value">¥{{ money(order.order_amount) }}</text>
                            </view>
                            <view class="money-row__right">
                                <text class="money-label">实收</text>
                                <text class="money-paid">¥{{ money(order.paid_amount) }}</text>
                            </view>
                        </view>
                        <view class="record-foot">
                            <text>{{ order.settlement_mode_text }}</text>
                            <text>{{ formatDateTime(order.create_at) }}</text>
                        </view>
                    </view>
                </template>

                <template v-else>
                    <view v-for="record in list" :key="record.id" class="record-card redemption-record">
                        <view class="timeline-dot" :class="{ 'timeline-dot--muted': record.status === 'reversed' }" />
                        <view class="redemption-main">
                            <view class="record-head">
                                <view>
                                    <view class="record-title">{{ record.item_name }}</view>
                                    <view class="record-subtitle">{{ record.redeem_no }}</view>
                                </view>
                                <view class="status-chip" :class="record.status === 'reversed' ? 'status-muted' : 'status-success'">
                                    {{ record.status_text }}
                                </view>
                            </view>
                            <view class="redemption-result">
                                <template v-if="record.status === 'reversed'">
                                    本次核销已撤销，权益次数已恢复
                                </template>
                                <template v-else>
                                    本次使用 {{ record.redeem_times || 1 }} 次，核销后剩余
                                    <text>{{ record.after_remaining }}</text> 次
                                </template>
                            </view>
                            <view class="record-foot">
                                <text>操作人：{{ record.operator_name || '门店员工' }}</text>
                                <text>{{ formatDateTime(record.occurred_at) }}</text>
                            </view>
                        </view>
                    </view>
                </template>

                <view class="load-more">
                    <u-loadmore :status="loadStatus" loadmore-text="上拉加载更多" loading-text="加载中..." nomore-text="没有更多记录了" />
                </view>
            </template>

            <view v-else class="empty-card">
                <view class="empty-card__icon">
                    <u-icon :name="emptyIcon" color="#b3bdcc" size="42" />
                </view>
                <view class="empty-card__title">{{ emptyTitle }}</view>
                <view class="empty-card__desc">{{ emptyDescription }}</view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onPullDownRefresh, onReachBottom, onShow } from '@dcloudio/uni-app'
import {
    getMemberCardOverview,
    getMyMemberCards,
    getMyMemberCardOrders,
    getMyMemberCardRedemptions
} from '@/addon/hsx_member_card/api/index'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'

type TabKey = 'cards' | 'orders' | 'redemptions'

const tabs = [
    { key: 'cards' as TabKey, label: '我的卡', icon: 'coupon' },
    { key: 'orders' as TabKey, label: '购买记录', icon: 'order' },
    { key: 'redemptions' as TabKey, label: '核销记录', icon: 'clock' }
]
const activeTab = ref<TabKey>('cards')
const overview = ref<any>({})
const list = ref<any[]>([])
const page = ref(1)
const total = ref(0)
const loading = ref(false)
const loadStatus = ref<'loadmore' | 'loading' | 'nomore'>('loadmore')
const loadToken = ref(0)

const emptyIcon = computed(() => activeTab.value === 'cards' ? 'coupon' : activeTab.value === 'orders' ? 'order' : 'clock')
const emptyTitle = computed(() => activeTab.value === 'cards' ? '暂无会员卡' : activeTab.value === 'orders' ? '暂无购买记录' : '暂无核销记录')
const emptyDescription = computed(() => activeTab.value === 'cards'
    ? '门店为您开卡后，会员权益会显示在这里'
    : activeTab.value === 'orders'
        ? '门店完成开卡后可在这里查看记录'
        : '使用会员权益后可在这里查看核销明细')

const responseData = (res: any) => res?.data ?? {}
const pageRows = (data: any) => Array.isArray(data?.data) ? data.data : (Array.isArray(data?.list) ? data.list : [])

const loadOverview = async () => {
    try {
        const res = await getMemberCardOverview()
        overview.value = responseData(res) || {}
    } catch (error) {
        overview.value = {}
    }
}

const fetchList = async (append = false) => {
    if (loading.value && append) return
    const token = ++loadToken.value
    const tab = activeTab.value
    loading.value = true
    loadStatus.value = 'loading'
    try {
        const params = { page: page.value, limit: 10 }
        const res = tab === 'cards'
            ? await getMyMemberCards(params)
            : tab === 'orders'
                ? await getMyMemberCardOrders(params)
                : await getMyMemberCardRedemptions(params)
        if (token !== loadToken.value || tab !== activeTab.value) return
        const data = responseData(res)
        const rows = pageRows(data)
        list.value = append ? list.value.concat(rows) : rows
        total.value = Number(data?.total || list.value.length)
        loadStatus.value = list.value.length >= total.value || rows.length < 10 ? 'nomore' : 'loadmore'
    } catch (error) {
        if (token !== loadToken.value) return
        loadStatus.value = 'loadmore'
        if (!append) list.value = []
    } finally {
        if (token === loadToken.value) loading.value = false
        uni.stopPullDownRefresh()
    }
}

const refresh = async () => {
    page.value = 1
    await Promise.all([loadOverview(), fetchList(false)])
}

const switchTab = (key: TabKey) => {
    if (activeTab.value === key) return
    activeTab.value = key
    list.value = []
    page.value = 1
    fetchList(false)
}

const openCard = (card: any) => {
    uni.navigateTo({ url: `/addon/hsx_member_card/pages/member/detail?id=${Number(card.id)}` })
}

const subscribeNotice = () => {
    useSubscribeMessage().request('hsx_member_card_redeem_success,hsx_member_card_redeem_reversed')
}

const statusClass = (status: string) => {
    if (['active', 'completed'].includes(status)) return 'status-success'
    if (['pending', 'processing', 'refund_pending'].includes(status)) return 'status-warning'
    return 'status-muted'
}

const money = (value: any) => Number(value || 0).toFixed(2)
const formatDateTime = (value: any) => {
    const number = Number(value || 0)
    if (!number) return '-'
    const date = new Date(number < 1000000000000 ? number * 1000 : number)
    const pad = (v: number) => String(v).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

onShow(refresh)
onPullDownRefresh(refresh)
onReachBottom(() => {
    if (!loading.value && loadStatus.value === 'loadmore') {
        page.value++
        fetchList(true)
    }
})
</script>

<style lang="scss" scoped>
.member-card-page {
    min-height: 100vh;
    box-sizing: border-box;
    padding: 24rpx 24rpx calc(40rpx + env(safe-area-inset-bottom));
    background: #f4f6fa;
    color: #172033;
}

.hero-card {
    padding: 34rpx;
    overflow: hidden;
    color: #fff;
    border-radius: 28rpx;
    background: linear-gradient(135deg, #1f5cf5 0%, #387cff 54%, #6f9cff 100%);
    box-shadow: 0 18rpx 44rpx rgba(47, 107, 255, .22);
}

.hero-card__top,
.record-head,
.record-foot,
.benefit-row,
.latest-card,
.tabs-wrap,
.money-row {
    display: flex;
    align-items: center;
}

.hero-card__top,
.record-head,
.record-foot,
.benefit-row,
.money-row {
    justify-content: space-between;
}

.hero-card__eyebrow {
    font-size: 20rpx;
    letter-spacing: 3rpx;
    opacity: .72;
}

.hero-card__title {
    margin-top: 8rpx;
    font-size: 38rpx;
    font-weight: 700;
}

.hero-card__notice {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8rpx;
    height: 58rpx;
    padding: 0 18rpx;
    font-size: 21rpx;
    border: 1rpx solid rgba(255, 255, 255, .24);
    border-radius: 999rpx;
    background: rgba(255, 255, 255, .14);
}

.summary-grid {
    display: grid;
    grid-template-columns: 1fr 1rpx 1fr 1rpx 1fr;
    align-items: center;
    margin-top: 38rpx;
}

.summary-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.summary-value {
    font-size: 36rpx;
    font-weight: 700;
}

.summary-label {
    margin-top: 7rpx;
    font-size: 23rpx;
    opacity: .78;
}

.summary-line {
    width: 1rpx;
    height: 46rpx;
    background: rgba(255, 255, 255, .25);
}

.latest-card {
    gap: 20rpx;
    margin-top: 22rpx;
    padding: 24rpx;
    border: 1rpx solid #e8edf6;
    border-radius: 22rpx;
    background: #fff;
}

.latest-card__icon,
.record-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 66rpx;
    height: 66rpx;
    border-radius: 20rpx;
    background: #edf3ff;
}

.latest-card__body {
    min-width: 0;
    flex: 1;
}

.latest-card__title {
    overflow: hidden;
    font-size: 27rpx;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.latest-card__desc,
.record-subtitle,
.card-no {
    margin-top: 8rpx;
    color: #8a95a7;
    font-size: 22rpx;
}

.tabs-wrap {
    margin-top: 24rpx;
    padding: 8rpx;
    border-radius: 22rpx;
    background: #fff;
}

.tab-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9rpx;
    flex: 1;
    height: 72rpx;
    color: #7f8a9c;
    font-size: 25rpx;
    border-radius: 17rpx;
}

.tab-item--active {
    color: #2f6bff;
    font-weight: 600;
    background: #edf3ff;
}

.content-wrap {
    padding-top: 22rpx;
}

.record-card {
    margin-bottom: 18rpx;
    padding: 28rpx;
    border: 1rpx solid #e9edf4;
    border-radius: 24rpx;
    background: #fff;
}

.record-title-wrap {
    display: flex;
    align-items: center;
    min-width: 0;
}

.record-title {
    overflow: hidden;
    color: #182238;
    font-size: 29rpx;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.record-mark {
    width: 58rpx;
    height: 58rpx;
    margin-right: 18rpx;
    border-radius: 16rpx;
}

.status-chip {
    flex: 0 0 auto;
    margin-left: 16rpx;
    padding: 7rpx 14rpx;
    font-size: 21rpx;
    border-radius: 999rpx;
}

.status-success {
    color: #16885b;
    background: #e9f8f1;
}

.status-warning {
    color: #b26a00;
    background: #fff4dc;
}

.status-muted {
    color: #778398;
    background: #eef1f5;
}

.card-no {
    padding-left: 76rpx;
}

.benefit-list {
    margin-top: 24rpx;
    padding: 4rpx 22rpx;
    border-radius: 18rpx;
    background: #f7f9fc;
}

.benefit-row {
    min-height: 76rpx;
    border-bottom: 1rpx solid #e9edf4;
}

.benefit-row:last-child {
    border-bottom: 0;
}

.benefit-name {
    font-size: 25rpx;
}

.benefit-count {
    color: #657186;
    font-size: 23rpx;
}

.benefit-count text,
.redemption-result text {
    color: #2f6bff;
    font-size: 30rpx;
    font-weight: 700;
}

.record-foot {
    margin-top: 23rpx;
    color: #929cad;
    font-size: 21rpx;
}

.validity,
.detail-link {
    display: flex;
    align-items: center;
    gap: 8rpx;
}

.detail-link {
    color: #2f6bff;
}

.money-row {
    margin-top: 26rpx;
    padding: 23rpx;
    border-radius: 17rpx;
    background: #f7f9fc;
}

.money-label {
    display: block;
    color: #8893a5;
    font-size: 21rpx;
}

.money-value,
.money-paid {
    display: block;
    margin-top: 8rpx;
    font-size: 30rpx;
    font-weight: 700;
}

.money-paid {
    color: #2f6bff;
}

.money-row__right {
    text-align: right;
}

.redemption-record {
    position: relative;
    display: flex;
    gap: 20rpx;
}

.timeline-dot {
    flex: 0 0 auto;
    width: 18rpx;
    height: 18rpx;
    margin-top: 11rpx;
    border: 7rpx solid #deebff;
    border-radius: 50%;
    background: #2f6bff;
}

.timeline-dot--muted {
    border-color: #edf0f4;
    background: #9aa4b4;
}

.redemption-main {
    min-width: 0;
    flex: 1;
}

.redemption-result {
    margin-top: 22rpx;
    padding: 20rpx;
    color: #647087;
    font-size: 23rpx;
    line-height: 1.7;
    border-radius: 16rpx;
    background: #f7f9fc;
}

.loading-box,
.empty-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 110rpx 30rpx;
    color: #8c97a8;
}

.loading-box text {
    margin-top: 20rpx;
    font-size: 24rpx;
}

.empty-card {
    border: 1rpx dashed #dce2eb;
    border-radius: 24rpx;
    background: #fff;
}

.empty-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 104rpx;
    height: 104rpx;
    border-radius: 50%;
    background: #f1f4f8;
}

.empty-card__title {
    margin-top: 25rpx;
    color: #4c586d;
    font-size: 28rpx;
    font-weight: 600;
}

.empty-card__desc {
    margin-top: 12rpx;
    color: #9aa4b3;
    font-size: 23rpx;
}

.load-more {
    padding: 12rpx 0 24rpx;
}
</style>
