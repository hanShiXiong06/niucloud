<template>
    <view class="min-h-screen bg-gray-50 pb-20">
        <!-- 加载中 -->
        <view v-if="loading" class="loading-container">
            <up-loading-icon mode="circle" size="40"></up-loading-icon>
            <text class="text-sm text-gray-500 mt-2">加载中...</text>
        </view>

        <!-- 订单内容 -->
        <view v-else-if="orderDetail.id" class="order-content">
            <!-- 订单状态 -->
            <view class="status-card">
                <view class="status-text">{{ getStatusText(orderDetail.status) }}</view>
                <view class="status-desc">{{ getStatusDesc(orderDetail.status) }}</view>
            </view>

            <!-- 订单信息 -->
            <view class="info-card">
                <view class="card-title">订单信息</view>
                <view class="info-item">
                    <text class="label">退货单号：</text>
                    <text class="value">{{ orderDetail.order_no }}</text>
                </view>
                <view class="info-item">
                    <text class="label">创建时间：</text>
                    <text class="value">{{ orderDetail.create_at }}</text>
                </view>
                <view class="info-item" v-if="orderDetail.over_at">
                    <text class="label">完成时间：</text>
                    <text class="value">{{ orderDetail.over_at }}</text>
                </view>
                <view class="info-item" v-if="orderDetail.remark">
                    <text class="label">备注：</text>
                    <text class="value">{{ orderDetail.remark }}</text>
                </view>
                <view class="info-item" v-if="orderDetail.comment">
                    <text class="label">备注信息：</text>
                    <text class="value">{{ orderDetail.comment }}</text>
                </view>
            </view>

            <!-- 快递信息 -->
            <view class="info-card" v-if="orderDetail.express_company || orderDetail.express_no">
                <view class="card-title">快递信息</view>
                <view class="info-item" v-if="orderDetail.express_company">
                    <text class="label">快递公司：</text>
                    <text class="value">{{ orderDetail.express_company }}</text>
                </view>
                <view class="info-item" v-if="orderDetail.express_no">
                    <text class="label">快递单号：</text>
                    <text class="value copy-text" @click="copyText(orderDetail.express_no)">
                        {{ orderDetail.express_no }}
                        <text class="copy-icon">复制</text>
                    </text>
                </view>
                <view class="info-item" v-if="orderDetail.return_address">
                    <text class="label">退回地址：</text>
                    <text class="value">{{ orderDetail.return_address }}</text>
                </view>
                <!-- 查看物流按钮 -->
                <view v-if="orderDetail.express_no" class="track-btn-wrapper">
                    <button class="btn-track" @click="showExpressTracking = true">
                        <up-icon name="car" size="16" color="#2979ff" class="mr-1"></up-icon>
                        查看物流
                    </button>
                </view>
            </view>

            <!-- 设备列表 -->
            <view class="info-card">
                <view class="card-title">退回设备</view>
                <view class="device-list">
                    <view class="device-item" v-for="(item, index) in deviceList" :key="index">
                        <view class="device-header">
                            <text class="device-title">设备 #{{ index + 1 }}</text>
                            <text class="device-status" :class="'status-' + (item.device ? item.device.status : 0)">
                                {{ item.status_name || getDeviceStatusText(item.device ? item.device.status : 0) }}
                            </text>
                        </view>
                        <view class="device-info" v-if="item.device">
                            <view class="info-row">
                                <text class="info-label">IMEI：</text>
                                <text class="info-value">{{ item.device.imei || '-' }}</text>
                            </view>
                            <view class="info-row">
                                <text class="info-label">型号：</text>
                                <text class="info-value">{{ item.device.model || '-' }}</text>
                            </view>
                            <view class="info-row" v-if="item.device.final_price">
                                <text class="info-label">价格：</text>
                                <text class="info-value price">¥{{ Number(item.device.final_price).toFixed(2) }}</text>
                            </view>
                        </view>
                        <view class="device-empty" v-else>
                            <text>设备信息不存在</text>
                        </view>
                    </view>
                </view>
            </view>
        </view>

        <!-- 空状态 -->
        <view v-else class="empty-container">
            <up-empty
                mode="data"
                icon="http://cdn.uviewui.com/uview/empty/data.png"
                text="退货订单不存在"
                textColor="#999999"
                textSize="15"
            ></up-empty>
        </view>

        <!-- 物流查询弹窗 -->
        <ExpressTrackingModal
            :visible="showExpressTracking"
            :expressNo="orderDetail.express_no || ''"
            :mobile="orderDetail.member_mobile || ''"
            @update:visible="showExpressTracking = $event"
        />
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getReturnOrderDetail } from '../../api/return_order'
import ExpressTrackingModal from '../order/components/ExpressTrackingModal.vue'

// 状态
const loading = ref(true)
const orderDetail = ref<any>({})
const deviceList = ref<any[]>([])
const showExpressTracking = ref(false)

// 获取订单详情
const loadDetail = async (id: number | string) => {
    loading.value = true
    try {
        const res = await getReturnOrderDetail(Number(id))
        if (res.code === 1 && res.data) {
            orderDetail.value = res.data
            deviceList.value = res.data.return_devices || res.data.returnDevices || []
        } else {
            orderDetail.value = {}
            deviceList.value = []
        }
    } catch (error) {
        console.error('获取退货订单详情失败:', error)
        orderDetail.value = {}
        deviceList.value = []
    } finally {
        loading.value = false
    }
}

// 获取状态文本
const getStatusText = (status: number) => {
    const statusMap: Record<number, string> = {
        0: '待处理',
        1: '退货中',
        2: '已完成',
        3: '已取消'
    }
    return statusMap[status] || '未知状态'
}

// 获取状态描述
const getStatusDesc = (status: number) => {
    const statusMap: Record<number, string> = {
        0: '商家正在处理您的退货申请',
        1: '商家已确认退货，设备退回中',
        2: '退货已完成',
        3: '退货已取消'
    }
    return statusMap[status] || ''
}

// 获取设备状态文本
const getDeviceStatusText = (status: number) => {
    const statusMap: Record<number, string> = {
        0: '待检测',
        1: '检测中',
        2: '已回收',
        3: '已退回',
        4: '已取消',
        6: '已退回'
    }
    return statusMap[status] || '未知状态'
}

// 复制文本
const copyText = (text: string) => {
    uni.setClipboardData({
        data: text,
        success: () => {
            uni.showToast({
                title: '复制成功',
                icon: 'success'
            })
        }
    })
}

// 页面加载
onLoad((options?: Record<string, any>) => {
    if (options?.id) {
        loadDetail(options.id)
    }
})
</script>

<style lang="scss" scoped>
.loading-container {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 300rpx;
    padding-top: 200rpx;
}

.empty-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40rpx;
}

.status-card {
    background: linear-gradient(135deg, #2979ff, #1e5fcc);
    padding: 40rpx 30rpx;
    color: #ffffff;

    .status-text {
        font-size: 36rpx;
        font-weight: bold;
        margin-bottom: 10rpx;
    }

    .status-desc {
        font-size: 26rpx;
        opacity: 0.9;
    }
}

.info-card {
    background-color: #ffffff;
    margin: 20rpx;
    border-radius: 12rpx;
    padding: 30rpx;
    box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.05);

    .card-title {
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 20rpx;
        border-left: 6rpx solid #2979ff;
        padding-left: 15rpx;
    }

    .info-item {
        display: flex;
        margin-bottom: 15rpx;
        font-size: 28rpx;

        .label {
            color: #999;
            width: 180rpx;
            flex-shrink: 0;
        }

        .value {
            color: #333;
            flex: 1;
        }

        .copy-text {
            display: flex;
            align-items: center;

            .copy-icon {
                margin-left: 10rpx;
                color: #2979ff;
                font-size: 24rpx;
                background-color: rgba(41, 121, 255, 0.1);
                padding: 4rpx 10rpx;
                border-radius: 4rpx;
            }
        }
    }
}

.track-btn-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16rpx;

    .btn-track {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26rpx;
        color: #2979ff;
        background: rgba(41, 121, 255, 0.08);
        border: 1rpx solid rgba(41, 121, 255, 0.3);
        border-radius: 30rpx;
        padding: 10rpx 30rpx;
        height: auto;
        line-height: normal;
    }
}

.device-list {
    .device-item {
        background-color: #f9f9f9;
        border-radius: 8rpx;
        margin-bottom: 20rpx;
        overflow: hidden;

        &:last-child {
            margin-bottom: 0;
        }

        .device-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20rpx;
            background-color: #f5f5f5;

            .device-title {
                font-size: 28rpx;
                font-weight: bold;
                color: #333;
            }

            .device-status {
                font-size: 24rpx;
                padding: 4rpx 12rpx;
                border-radius: 4rpx;

                &.status-0 {
                    background-color: #e6f7ff;
                    color: #1890ff;
                }

                &.status-1 {
                    background-color: #fff7e6;
                    color: #fa8c16;
                }

                &.status-2 {
                    background-color: #f6ffed;
                    color: #52c41a;
                }

                &.status-3,
                &.status-6 {
                    background-color: #fff1f0;
                    color: #f5222d;
                }

                &.status-4 {
                    background-color: #f5f5f5;
                    color: #999;
                }
            }
        }

        .device-info {
            padding: 20rpx;

            .info-row {
                display: flex;
                margin-bottom: 10rpx;
                font-size: 26rpx;

                &:last-child {
                    margin-bottom: 0;
                }

                .info-label {
                    color: #999;
                    width: 120rpx;
                }

                .info-value {
                    color: #333;
                    flex: 1;

                    &.price {
                        color: #ff6b00;
                        font-weight: bold;
                    }
                }
            }
        }

        .device-empty {
            padding: 30rpx;
            text-align: center;
            color: #999;
            font-size: 26rpx;
        }
    }
}
</style>
