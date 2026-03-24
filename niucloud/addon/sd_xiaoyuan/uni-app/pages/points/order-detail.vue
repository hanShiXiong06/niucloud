<template>
    <view class="detail-page">
        <view class="order-card" v-if="order">
            <view class="status-section">
                <view class="status-icon" :class="'s-' + order.status">
                    <u-icon :name="getStatusIcon(order.status)" size="40" color="#fff"></u-icon>
                </view>
                <text class="status-text">{{ order.status_text || getStatusText(order.status) }}</text>
            </view>

            <view class="goods-section">
                <image class="goods-img" :src="order.goods_image ? img(order.goods_image) : '/static/images/default_goods.png'" mode="aspectFill"></image>
                <view class="goods-info">
                    <text class="goods-name">{{ order.goods_name }}</text>
                    <text class="goods-price">{{ order.total_points }} 积分</text>
                </view>
            </view>

            <view class="info-section">
                <view class="info-item">
                    <text class="label">订单编号</text>
                    <text class="value">{{ order.order_no }}</text>
                </view>
                <view class="info-item">
                    <text class="label">下单时间</text>
                    <text class="value">{{ order.create_time }}</text>
                </view>
                <view class="info-item" v-if="order.update_time && order.update_time !== order.create_time">
                    <text class="label">更新时间</text>
                    <text class="value">{{ order.update_time }}</text>
                </view>
            </view>

            <view class="receiver-section">
                <view class="section-title">收货信息</view>
                <view class="receiver-info">
                    <view class="receiver-row">
                        <text class="name">{{ order.receiver_name }}</text>
                        <text class="phone">{{ order.receiver_phone }}</text>
                    </view>
                    <text class="address">{{ order.receiver_address }}</text>
                </view>
            </view>

            <view class="remark-section" v-if="order.remark">
                <view class="section-title">备注</view>
                <text class="remark-text">{{ order.remark }}</text>
            </view>

            <!-- 物流信息 -->
            <view class="logistics-section" v-if="order.express_no">
                <view class="section-header">
                    <view class="section-title">物流信息</view>
                    <view class="view-btn" @click="viewLogistics">查看物流</view>
                </view>
                <view class="express-info">
                    <text class="express-company">{{ order.express_company }}</text>
                    <text class="express-no">{{ order.express_no }}</text>
                </view>
            </view>
        </view>

        <!-- 物流详情弹窗 -->
        <u-popup :show="logisticsPopup" mode="bottom" @close="logisticsPopup = false" round="16">
            <view class="logistics-popup">
                <view class="popup-header">
                    <text class="popup-title">物流详情</text>
                    <u-icon name="close" size="24" @click="logisticsPopup = false"></u-icon>
                </view>
                <view class="express-header">
                    <text>快递公司：{{ logisticsData.express_company }}</text>
                    <text>单号：{{ logisticsData.express_no }}</text>
                </view>
                <scroll-view scroll-y class="traces-list" v-if="logisticsData.traces && logisticsData.traces.length > 0">
                    <view class="trace-item" v-for="(trace, index) in logisticsData.traces" :key="index" :class="{ active: index === 0 }">
                        <view class="trace-dot"></view>
                        <view class="trace-content">
                            <text class="trace-time">{{ trace.ftime || trace.time }}</text>
                            <text class="trace-text">{{ trace.context }}</text>
                        </view>
                    </view>
                </scroll-view>
                <view class="empty-traces" v-else>
                    <text>{{ logisticsLoading ? '加载中...' : '暂无物流信息' }}</text>
                </view>
            </view>
        </u-popup>

        <view class="loading" v-if="loading">
            <u-loading-icon></u-loading-icon>
            <text>加载中...</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getPointsOrderDetail, getPointsLogistics } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const loading = ref(false)
const order = ref<any>(null)
const orderId = ref(0)
const logisticsPopup = ref(false)
const logisticsLoading = ref(false)
const logisticsData = ref<any>({ traces: [], express_company: '', express_no: '' })

const statusMap: Record<number, string> = {
    0: '待发货',
    1: '已发货',
    2: '已完成'
}

const getStatusText = (status: number) => statusMap[status] || '未知'

const getStatusIcon = (status: number) => {
    const icons: Record<number, string> = {
        0: 'clock',
        1: 'car',
        2: 'checkmark-circle'
    }
    return icons[status] || 'info-circle'
}

onLoad((options: any) => {
    orderId.value = parseInt(options?.id || '0')
    if (orderId.value) {
        loadDetail()
    }
})

const loadDetail = async () => {
    loading.value = true
    try {
        const res: any = await getPointsOrderDetail({ id: orderId.value })
        if (res.code === 1) {
            order.value = res.data
        } else {
            uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.showToast({ title: e.message || '加载失败', icon: 'none' })
    } finally {
        loading.value = false
    }
}

const viewLogistics = async () => {
    logisticsPopup.value = true
    logisticsLoading.value = true
    logisticsData.value = {
        express_company: order.value?.express_company || '',
        express_no: order.value?.express_no || '',
        traces: []
    }
    try {
        const res: any = await getPointsLogistics({ id: orderId.value })
        if (res.code === 1 && res.data?.success) {
            logisticsData.value = {
                express_company: res.data.express_company || order.value?.express_company || '',
                express_no: res.data.express_no || order.value?.express_no || '',
                traces: res.data.data?.traces || []
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        logisticsLoading.value = false
    }
}
</script>

<style lang="scss" scoped>
.detail-page {
    min-height: 100vh;
    background: #f7f7f7;
    padding: 20rpx;
}

.order-card {
    background: #fff;
    border-radius: 16rpx;
    overflow: hidden;
}

.status-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 40rpx 20rpx;
    background: linear-gradient(135deg, #c0fe95, #7ed957);

    .status-icon {
        width: 80rpx;
        height: 80rpx;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16rpx;

        &.s-0 { background: #ff9500; }
        &.s-1 { background: #1890ff; }
        &.s-2 { background: #52c41a; }
    }

    .status-text {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
    }
}

.goods-section {
    display: flex;
    gap: 20rpx;
    padding: 24rpx;
    border-bottom: 1rpx solid #f5f5f5;

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
        }

        .goods-price {
            font-size: 30rpx;
            font-weight: bold;
            color: #ff6b00;
        }
    }
}

.info-section {
    padding: 24rpx;
    border-bottom: 1rpx solid #f5f5f5;

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12rpx 0;

        .label {
            font-size: 26rpx;
            color: #999;
        }

        .value {
            font-size: 26rpx;
            color: #333;
        }
    }
}

.receiver-section, .remark-section {
    padding: 24rpx;

    .section-title {
        font-size: 28rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 16rpx;
    }
}

.receiver-info {
    background: #f9f9f9;
    border-radius: 12rpx;
    padding: 20rpx;

    .receiver-row {
        display: flex;
        gap: 20rpx;
        margin-bottom: 12rpx;

        .name {
            font-size: 28rpx;
            font-weight: 500;
            color: #333;
        }

        .phone {
            font-size: 28rpx;
            color: #666;
        }
    }

    .address {
        font-size: 26rpx;
        color: #666;
        line-height: 1.5;
    }
}

.remark-text {
    font-size: 26rpx;
    color: #666;
    line-height: 1.6;
}

.loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 100rpx 0;
    color: #999;
    font-size: 28rpx;
    gap: 20rpx;
}

.logistics-section {
    padding: 24rpx;
    border-top: 1rpx solid #f5f5f5;

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16rpx;
    }

    .view-btn {
        font-size: 24rpx;
        color: #1890ff;
        padding: 8rpx 20rpx;
        background: #e6f7ff;
        border-radius: 20rpx;
    }

    .express-info {
        background: #f9f9f9;
        border-radius: 12rpx;
        padding: 20rpx;

        .express-company {
            display: block;
            font-size: 28rpx;
            color: #333;
            margin-bottom: 8rpx;
        }

        .express-no {
            font-size: 26rpx;
            color: #1890ff;
        }
    }
}

.logistics-popup {
    padding: 30rpx;
    padding-bottom: calc(30rpx + env(safe-area-inset-bottom));
    max-height: 70vh;

    .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24rpx;

        .popup-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }
    }

    .express-header {
        background: #f5f5f5;
        border-radius: 12rpx;
        padding: 20rpx;
        margin-bottom: 24rpx;

        text {
            display: block;
            font-size: 26rpx;
            color: #666;
            margin-bottom: 8rpx;

            &:last-child { margin-bottom: 0; }
        }
    }

    .traces-list {
        max-height: 50vh;
    }

    .trace-item {
        display: flex;
        padding: 16rpx 0;
        position: relative;

        &::before {
            content: '';
            position: absolute;
            left: 10rpx;
            top: 40rpx;
            bottom: -16rpx;
            width: 2rpx;
            background: #e5e5e5;
        }

        &:last-child::before { display: none; }

        &.active {
            .trace-dot { background: #52c41a; }
            .trace-text { color: #333; }
        }

        .trace-dot {
            width: 20rpx;
            height: 20rpx;
            border-radius: 50%;
            background: #ccc;
            margin-right: 20rpx;
            margin-top: 6rpx;
            flex-shrink: 0;
        }

        .trace-content {
            flex: 1;

            .trace-time {
                display: block;
                font-size: 24rpx;
                color: #999;
                margin-bottom: 8rpx;
            }

            .trace-text {
                font-size: 26rpx;
                color: #666;
                line-height: 1.5;
            }
        }
    }

    .empty-traces {
        text-align: center;
        padding: 60rpx 0;
        color: #999;
        font-size: 28rpx;
    }
}
</style>
