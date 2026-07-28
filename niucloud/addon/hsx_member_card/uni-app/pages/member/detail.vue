<template>
    <view class="detail-page">
        <view v-if="loading" class="state-box">
            <u-loading-icon mode="circle" color="#2f6bff" />
            <text>正在读取会员卡...</text>
        </view>

        <template v-else-if="card.id">
            <view class="card-visual">
                <view class="card-visual__top">
                    <view>
                        <view class="card-visual__eyebrow">MEMBERSHIP CARD</view>
                        <view class="card-visual__name">{{ card.product_name }}</view>
                    </view>
                    <view class="card-visual__status">{{ card.status_text }}</view>
                </view>
                <view class="card-visual__no">{{ card.card_no }}</view>
                <view class="card-visual__valid">{{ card.validity_text }}</view>
            </view>

            <view class="section-card">
                <view class="section-title">服务权益</view>
                <view v-for="item in card.items || []" :key="item.id" class="benefit-item">
                    <view class="benefit-item__icon">
                        <u-icon name="gift-fill" color="#2f6bff" size="21" />
                    </view>
                    <view class="benefit-item__main">
                        <view class="benefit-item__name">{{ item.item_name }}</view>
                        <view class="progress-track" v-if="item.usage_mode === 'limited'">
                            <view class="progress-value" :style="{ width: progress(item) + '%' }" />
                        </view>
                        <view class="benefit-item__meta">
                            <template v-if="item.usage_mode === 'unlimited'">不限次数使用</template>
                            <template v-else>已使用 {{ item.used_times }} 次 · 共 {{ item.granted_times }} 次</template>
                        </view>
                    </view>
                    <view class="benefit-item__count">
                        <template v-if="item.usage_mode === 'unlimited'">不限</template>
                        <template v-else><text>{{ item.remaining_times }}</text> 次</template>
                    </view>
                </view>
            </view>

            <view v-if="card.order?.id" class="section-card">
                <view class="section-title">购买记录</view>
                <view class="info-row">
                    <text>开卡单号</text>
                    <text>{{ card.order.order_no }}</text>
                </view>
                <view class="info-row">
                    <text>开卡金额</text>
                    <text class="info-value">¥{{ money(card.order.order_amount) }}</text>
                </view>
                <view class="info-row">
                    <text>结算方式</text>
                    <text>{{ card.order.settlement_mode_text }}</text>
                </view>
                <view class="info-row">
                    <text>开卡时间</text>
                    <text>{{ formatDateTime(card.order.confirmed_at || card.order.create_at) }}</text>
                </view>
                <view class="info-row">
                    <text>开单人</text>
                    <text>{{ card.order.issuer_name || '门店员工' }}</text>
                </view>
            </view>

            <view class="section-card">
                <view class="section-title">
                    <text>核销记录</text>
                    <text class="section-count">{{ (card.redemptions || []).length }} 条</text>
                </view>
                <view v-if="card.redemptions?.length" class="timeline">
                    <view v-for="record in card.redemptions" :key="record.id" class="timeline-item">
                        <view class="timeline-axis">
                            <view class="timeline-dot" :class="{ 'timeline-dot--muted': record.status === 'reversed' }" />
                            <view class="timeline-line" />
                        </view>
                        <view class="timeline-content">
                            <view class="timeline-head">
                                <text class="timeline-title">{{ record.item_name }}</text>
                                <text :class="record.status === 'reversed' ? 'status-muted' : 'status-success'">{{ record.status_text }}</text>
                            </view>
                            <view class="timeline-desc" v-if="record.status === 'reversed'">
                                已撤销并恢复权益次数
                            </view>
                            <view class="timeline-desc" v-else>
                                本次使用 {{ record.redeem_times || 1 }} 次，剩余 {{ record.after_remaining }} 次
                            </view>
                            <view class="timeline-meta">
                                <text>{{ record.operator_name || '门店员工' }}</text>
                                <text>{{ formatDateTime(record.occurred_at) }}</text>
                            </view>
                        </view>
                    </view>
                </view>
                <view v-else class="empty-text">暂时没有核销记录</view>
            </view>
        </template>

        <view v-else class="state-box">
            <u-icon name="error-circle" color="#b1bac9" size="42" />
            <text>没有找到这张会员卡</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad, onPullDownRefresh } from '@dcloudio/uni-app'
import { getMyMemberCardDetail } from '@/addon/hsx_member_card/api/index'

const cardId = ref(0)
const card = ref<any>({})
const loading = ref(false)

const loadDetail = async () => {
    if (!cardId.value || loading.value) return
    loading.value = true
    try {
        const res = await getMyMemberCardDetail(cardId.value)
        card.value = res?.data || {}
    } finally {
        loading.value = false
        uni.stopPullDownRefresh()
    }
}

const progress = (item: any) => {
    const total = Number(item.granted_times || 0)
    if (!total) return 0
    return Math.max(0, Math.min(100, Number(item.remaining_times || 0) / total * 100))
}

const money = (value: any) => Number(value || 0).toFixed(2)
const formatDateTime = (value: any) => {
    const number = Number(value || 0)
    if (!number) return '-'
    const date = new Date(number < 1000000000000 ? number * 1000 : number)
    const pad = (v: number) => String(v).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

onLoad((options: any) => {
    cardId.value = Number(options?.id || 0)
    loadDetail()
})
onPullDownRefresh(loadDetail)
</script>

<style lang="scss" scoped>
.detail-page {
    min-height: 100vh;
    box-sizing: border-box;
    padding: 24rpx 24rpx calc(42rpx + env(safe-area-inset-bottom));
    background: #f4f6fa;
    color: #172033;
}

.card-visual {
    padding: 36rpx;
    color: #fff;
    border-radius: 28rpx;
    background: linear-gradient(135deg, #1f5cf5, #4c83fb 56%, #7aa2ff);
    box-shadow: 0 18rpx 44rpx rgba(47, 107, 255, .22);
}

.card-visual__top,
.section-title,
.benefit-item,
.info-row,
.timeline-head,
.timeline-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-visual__eyebrow {
    font-size: 19rpx;
    letter-spacing: 3rpx;
    opacity: .72;
}

.card-visual__name {
    margin-top: 9rpx;
    font-size: 36rpx;
    font-weight: 700;
}

.card-visual__status {
    padding: 8rpx 17rpx;
    font-size: 21rpx;
    border: 1rpx solid rgba(255, 255, 255, .35);
    border-radius: 999rpx;
    background: rgba(255, 255, 255, .15);
}

.card-visual__no {
    margin-top: 48rpx;
    font-size: 26rpx;
    letter-spacing: 2rpx;
}

.card-visual__valid {
    margin-top: 12rpx;
    font-size: 22rpx;
    opacity: .78;
}

.section-card {
    margin-top: 22rpx;
    padding: 28rpx;
    border: 1rpx solid #e8edf5;
    border-radius: 24rpx;
    background: #fff;
}

.section-title {
    margin-bottom: 23rpx;
    font-size: 29rpx;
    font-weight: 700;
}

.section-count {
    color: #909bad;
    font-size: 21rpx;
    font-weight: 400;
}

.benefit-item {
    padding: 22rpx 0;
    border-top: 1rpx solid #eef1f5;
}

.benefit-item__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 62rpx;
    height: 62rpx;
    border-radius: 18rpx;
    background: #edf3ff;
}

.benefit-item__main {
    min-width: 0;
    flex: 1;
    margin: 0 20rpx;
}

.benefit-item__name {
    font-size: 26rpx;
    font-weight: 600;
}

.benefit-item__meta {
    margin-top: 10rpx;
    color: #8c97a8;
    font-size: 21rpx;
}

.benefit-item__count {
    color: #5f6c82;
    font-size: 22rpx;
}

.benefit-item__count text {
    color: #2f6bff;
    font-size: 34rpx;
    font-weight: 700;
}

.progress-track {
    height: 7rpx;
    margin-top: 14rpx;
    overflow: hidden;
    border-radius: 999rpx;
    background: #e8edf5;
}

.progress-value {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #2f6bff, #79a1ff);
}

.info-row {
    min-height: 78rpx;
    color: #7c8799;
    font-size: 24rpx;
    border-top: 1rpx solid #eef1f5;
}

.info-row text:last-child {
    max-width: 68%;
    overflow: hidden;
    color: #354158;
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.info-row .info-value {
    color: #2f6bff;
    font-weight: 700;
}

.timeline-item {
    display: flex;
}

.timeline-axis {
    display: flex;
    align-items: center;
    flex-direction: column;
    flex: 0 0 auto;
    width: 34rpx;
}

.timeline-dot {
    width: 16rpx;
    height: 16rpx;
    margin-top: 9rpx;
    border: 6rpx solid #deebff;
    border-radius: 50%;
    background: #2f6bff;
}

.timeline-dot--muted {
    border-color: #edf0f4;
    background: #9aa4b4;
}

.timeline-line {
    flex: 1;
    width: 2rpx;
    min-height: 84rpx;
    background: #e6ebf2;
}

.timeline-item:last-child .timeline-line {
    display: none;
}

.timeline-content {
    min-width: 0;
    flex: 1;
    padding: 0 0 30rpx 18rpx;
}

.timeline-title {
    font-size: 26rpx;
    font-weight: 600;
}

.status-success,
.status-muted {
    padding: 6rpx 13rpx;
    font-size: 20rpx;
    border-radius: 999rpx;
}

.status-success {
    color: #16885b;
    background: #e9f8f1;
}

.status-muted {
    color: #778398;
    background: #eef1f5;
}

.timeline-desc {
    margin-top: 11rpx;
    color: #68758a;
    font-size: 23rpx;
}

.timeline-meta {
    margin-top: 11rpx;
    color: #9aa4b3;
    font-size: 20rpx;
}

.empty-text {
    padding: 36rpx 0;
    color: #a0a9b8;
    text-align: center;
    font-size: 23rpx;
}

.state-box {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    min-height: 65vh;
    color: #8d98aa;
    font-size: 24rpx;
}

.state-box text {
    margin-top: 20rpx;
}
</style>
