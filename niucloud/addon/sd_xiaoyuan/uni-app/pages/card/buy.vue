<template>
    <view class="card-buy-page">
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar" :style="{ height: navBarHeight + 'px' }">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">{{ cardInfo.name || '次卡购买' }}</text>
                <view class="placeholder"></view>
            </view>
        </view>

        <view class="page-body" v-if="loaded">
            <view class="hero-card" :class="'theme-' + cardType.toLowerCase()">
                <view class="hero-left">
                    <view class="hero-name">{{ cardInfo.name }}</view>
                    <view class="hero-sub">{{ cardInfo.subtitle }}</view>
                    <view class="hero-times">含 {{ cardInfo.times || 1 }} 次可用</view>
                </view>
                <view class="hero-price">
                    <text class="unit">¥</text>
                    <text class="num">{{ cardInfo.price }}</text>
                    <text class="origin" v-if="Number(cardInfo.origin_price) > Number(cardInfo.price)">¥{{ cardInfo.origin_price }}</text>
                </view>
            </view>

            <view class="info-card">
                <view class="info-row">
                    <text class="label">我的剩余次数</text>
                    <text class="value">{{ cardInfo.remain_times || 0 }} 次</text>
                </view>
                <view class="info-row">
                    <text class="label">购买后增加</text>
                    <text class="value">{{ cardInfo.times || 1 }} 次</text>
                </view>
            </view>

            <view class="tips-card">
                <view class="tips-title">使用说明</view>
                <view class="tips-item">1. 支付成功后次数自动到账</view>
                <view class="tips-item">2. 可在对应服务下单时使用次卡抵扣</view>
                <view class="tips-item">3. 次卡不可转让，不可提现</view>
            </view>

            <view class="log-card">
                <view class="log-title">使用记录</view>
                <view class="log-empty" v-if="useLogs.length === 0">暂无使用记录</view>
                <view class="log-item" v-for="item in useLogs" :key="item.id" @click="goOrderDetail(item.id)">
                    <view class="log-main">
                        <text class="log-name">{{ item.title || item.task_type_text }}</text>
                        <text class="log-time">{{ item.use_time_text }}</text>
                    </view>
                    <view class="log-sub">
                        <text class="log-order">{{ item.order_no }}</text>
                        <text class="log-discount">抵扣 ¥{{ item.discount }}</text>
                    </view>
                </view>
            </view>
        </view>

        <view class="bottom-bar" v-if="loaded">
            <view class="price-box">
                <text class="pay-label">应付</text>
                <text class="pay-price">¥{{ cardInfo.price }}</text>
            </view>
            <view class="buy-btn" @tap.stop="submitBuy">立即购买</view>
        </view>

        <pay ref="payRef" @confirm="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import pay from '@/components/pay/pay.vue'
import { getCardDetail, createCardOrder, getCardUseLogs } from '../../api/xiaoyuan'

const statusBarHeight = ref(uni.getSystemInfoSync().statusBarHeight || 0)
const navBarHeight = ref(44)
const cardType = ref('EXPRESS')
const cardInfo = ref<any>({})
const useLogs = ref<any[]>([])
const loaded = ref(false)
const payRef = ref<any>(null)
const currentOrderId = ref(0)

onLoad((options: any) => {
    cardType.value = String(options?.type || 'EXPRESS').toUpperCase()
    loadDetail()
    loadUseLogs()
})

const loadUseLogs = async () => {
    const res: any = await getCardUseLogs(cardType.value)
    if (res.code === 1 && res.data) {
        useLogs.value = res.data.list || []
    }
}

const loadDetail = async () => {
    const res: any = await getCardDetail(cardType.value)
    if (res.code === 1 && res.data) {
        cardInfo.value = res.data
        loaded.value = true
    } else {
        uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
    }
}

const submitBuy = async () => {
    uni.showLoading({ title: '提交中...' })
    const res: any = await createCardOrder(cardType.value)
    uni.hideLoading()
    if (res.code === 1 && res.data?.order_id) {
        currentOrderId.value = res.data.order_id
        if (!payRef.value) {
            uni.showToast({ title: '支付组件加载失败', icon: 'none' })
            return
        }
        payRef.value.open('sd_xiaoyuan_card', res.data.order_id, '/addon/sd_xiaoyuan/pages/card/buy?type=' + cardType.value)
        return
    }
    uni.showToast({ title: res.msg || '下单失败', icon: 'none' })
}

const onPaySuccess = () => {
    uni.showToast({ title: '购买成功', icon: 'success' })
    setTimeout(() => {
        loadDetail()
        loadUseLogs()
    }, 1200)
}

const goOrderDetail = (id: number) => {
    if (!id) return
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/order/detail?id=' + id })
}

const onPayFail = () => {
    uni.showToast({ title: '支付未完成', icon: 'none' })
}

const goBack = () => {
    uni.navigateBack()
}
</script>

<style lang="scss" scoped>
.card-buy-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 160rpx;
}

.header-bg {
    background: linear-gradient(to bottom, #e8ffcc, #f5f5f5);
}

.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24rpx;
}

.back-btn, .placeholder {
    width: 60rpx;
}

.title {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
}

.page-body {
    padding: 24rpx;
}

.hero-card {
    border-radius: 24rpx;
    padding: 32rpx;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 24rpx;
}

.theme-express { background: linear-gradient(135deg, #e8ffcc, #c0fe95); }
.theme-errand { background: linear-gradient(135deg, #fff3e0, #ffd591); }
.theme-print { background: linear-gradient(135deg, #f6ffed, #b7eb8f); }

.hero-name {
    font-size: 40rpx;
    font-weight: bold;
    color: #333;
}

.hero-sub {
    margin-top: 12rpx;
    font-size: 24rpx;
    color: #666;
}

.hero-times {
    margin-top: 16rpx;
    font-size: 24rpx;
    color: #333;
}

.hero-price .unit {
    font-size: 28rpx;
    color: #333;
}

.hero-price .num {
    font-size: 56rpx;
    font-weight: bold;
    color: #333;
}

.hero-price .origin {
    display: block;
    font-size: 24rpx;
    color: #999;
    text-decoration: line-through;
    text-align: right;
}

.info-card, .tips-card {
    background: #fff;
    border-radius: 20rpx;
    padding: 24rpx;
    margin-bottom: 24rpx;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 16rpx 0;
    font-size: 28rpx;
}

.info-row .label { color: #666; }
.info-row .value { color: #333; font-weight: bold; }

.tips-title {
    font-size: 30rpx;
    font-weight: bold;
    margin-bottom: 16rpx;
}

.tips-item {
    font-size: 24rpx;
    color: #666;
    line-height: 1.8;
}

.log-card {
    background: #fff;
    border-radius: 20rpx;
    padding: 24rpx;
    margin-bottom: 24rpx;
}

.log-title {
    font-size: 30rpx;
    font-weight: bold;
    margin-bottom: 16rpx;
}

.log-empty {
    font-size: 26rpx;
    color: #999;
    text-align: center;
    padding: 40rpx 0;
}

.log-item {
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
}

.log-item:last-child {
    border-bottom: none;
}

.log-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.log-name {
    font-size: 28rpx;
    color: #333;
    font-weight: bold;
}

.log-time {
    font-size: 24rpx;
    color: #999;
}

.log-sub {
    display: flex;
    justify-content: space-between;
    margin-top: 10rpx;
    font-size: 24rpx;
}

.log-order {
    color: #999;
}

.log-discount {
    color: #52c41a;
}

.bottom-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    background: #fff;
    padding: 20rpx 24rpx calc(20rpx + env(safe-area-inset-bottom));
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.06);
}

.pay-label {
    font-size: 24rpx;
    color: #666;
    margin-right: 8rpx;
}

.pay-price {
    font-size: 40rpx;
    font-weight: bold;
    color: #333;
}

.buy-btn {
    margin: 0;
    min-width: 240rpx;
    height: 80rpx;
    line-height: 80rpx;
    text-align: center;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000;
    border: none;
    border-radius: 40rpx;
    font-size: 30rpx;
    font-weight: bold;
}
</style>
