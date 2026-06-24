<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="orders-page" v-if="isFeatureEnabled">
        <view class="status-tabs">
            <view class="tab-item" :class="{ active: currentStatus === -1 }" @click="changeStatus(-1)">全部</view>
            <view class="tab-item" :class="{ active: currentStatus === 0 }" @click="changeStatus(0)">待发货</view>
            <view class="tab-item" :class="{ active: currentStatus === 1 }" @click="changeStatus(1)">已发货</view>
            <view class="tab-item" :class="{ active: currentStatus === 2 }" @click="changeStatus(2)">已完成</view>
        </view>

        <view class="order-list">
            <view class="order-item" v-for="order in orderList" :key="order.id" @click="goToDetail(order.id)">
                <view class="order-header">
                    <text class="order-no">订单号：{{ order.order_no }}</text>
                    <text class="order-status" :class="'s-' + order.status">{{ getStatusText(order.status) }}</text>
                </view>
                <view class="order-body">
                    <image class="goods-img" :src="order.goods_image ? img(order.goods_image) : '/static/images/default_goods.png'" mode="aspectFill"></image>
                    <view class="goods-info">
                        <text class="goods-name">{{ order.goods_name }}</text>
                        <text class="goods-price">{{ order.total_points }} 积分</text>
                    </view>
                </view>
                <view class="order-footer">
                    <view class="receiver-info">
                        <text class="receiver">收货人：{{ order.receiver_name }} {{ order.receiver_phone }}</text>
                        <text class="address">{{ order.receiver_address }}</text>
                    </view>
                    <view class="footer-row">
                        <text class="time">{{ order.create_time }}</text>
                        <view class="btn-detail" @click.stop="goToDetail(order.id)">查看详情</view>
                    </view>
                </view>
            </view>

            <view class="empty" v-if="orderList.length === 0 && !loading">
                <u-icon name="shopping-cart" size="60" color="#ccc"></u-icon>
                <text>暂无兑换记录</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getMyPointsOrders } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_points_mall')

const loading = ref(false)
const orderList = ref<any[]>([])
const currentStatus = ref(-1)

const statusMap: Record<number, string> = {
    0: '待发货',
    1: '已发货',
    2: '已完成'
}

const getStatusText = (status: number) => statusMap[status] || '未知'


onMounted(() => {
    loadConfig()
    loadOrders()
})

const changeStatus = (status: number) => {
    currentStatus.value = status
    loadOrders()
}

const loadOrders = async () => {
    loading.value = true
    try {
        const res: any = await getMyPointsOrders({
            status: currentStatus.value,
            page: 1,
            limit: 50
        })
        if (res.code === 1) {
            orderList.value = res.data.list || []
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const goToDetail = (orderId: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/points/order-detail?id=${orderId}`
    })
}
</script>

<style lang="scss" scoped>
.orders-page {
    min-height: 100vh;
    background: #f7f7f7;
}

.status-tabs {
    display: flex;
    background: #fff;
    padding: 0 20rpx;
    border-bottom: 1rpx solid #f0f0f0;

    .tab-item {
        flex: 1;
        text-align: center;
        padding: 24rpx 0;
        font-size: 28rpx;
        color: #666;
        position: relative;

        &.active {
            color: #333;
            font-weight: bold;

            &::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 40rpx;
                height: 6rpx;
                background: #c0fe95;
                border-radius: 3rpx;
            }
        }
    }
}

.order-list {
    padding: 20rpx;
}

.order-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.05);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20rpx;
    padding-bottom: 16rpx;
    border-bottom: 1rpx solid #f5f5f5;

    .order-no {
        font-size: 24rpx;
        color: #999;
    }

    .order-status {
        font-size: 26rpx;
        font-weight: bold;

        &.s-0 { color: #ff6b00; }
        &.s-1 { color: #1890ff; }
        &.s-2 { color: #52c41a; }
    }
}

.order-body {
    display: flex;
    gap: 20rpx;
    margin-bottom: 20rpx;

    .goods-img {
        width: 140rpx;
        height: 140rpx;
        border-radius: 12rpx;
        flex-shrink: 0;
    }

    .goods-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;

        .goods-name {
            font-size: 28rpx;
            color: #333;
            font-weight: 500;
            margin-bottom: 12rpx;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .goods-price {
            font-size: 30rpx;
            font-weight: bold;
            color: #ff6b00;
        }
    }
}

.order-footer {
    .receiver-info {
        background: #f9f9f9;
        border-radius: 8rpx;
        padding: 16rpx;
        margin-bottom: 16rpx;

        .receiver {
            display: block;
            font-size: 26rpx;
            color: #333;
            margin-bottom: 8rpx;
        }

        .address {
            display: block;
            font-size: 24rpx;
            color: #666;
            line-height: 1.5;
        }
    }

    .footer-row {
        display: flex;
        justify-content: space-between;
        align-items: center;

        .time {
            font-size: 24rpx;
            color: #999;
        }

        .btn-detail {
            padding: 12rpx 32rpx;
            font-size: 24rpx;
            color: #333;
            background: linear-gradient(135deg, #c0fe95, #7ed957);
            border: none;
            border-radius: 24rpx;
            font-weight: 500;
        }
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 150rpx 0;
    color: #999;
    font-size: 28rpx;
    gap: 20rpx;
}
</style>
