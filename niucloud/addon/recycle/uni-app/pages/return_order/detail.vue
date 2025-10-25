<template>
    <view class="return-order-detail">
        <!-- 顶部导航栏 -->
        <view class="nav-bar">
            <view class="nav-back" @click="goBack">
                <text class="iconfont iconarrow-left"></text>
            </view>
            <view class="nav-title">退回订单详情</view>
        </view>

        <!-- 加载中 -->
        <view class="loading-container" v-if="loading">
            <uni-load-more status="loading" :content-text="{ contentdown: '加载中...' }"></uni-load-more>
        </view>

        <!-- 订单内容 -->
        <view class="order-content" v-else>
            <!-- 订单状态 -->
            <view class="status-card">
                <view class="status-text">{{ getStatusText(orderDetail.status) }}</view>
                <view class="status-desc">{{ getStatusDesc(orderDetail.status) }}</view>
            </view>

            <!-- 订单信息 -->
            <view class="info-card">
                <view class="card-title">订单信息</view>
                <view class="info-item">
                    <text class="label">订单编号：</text>
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
            </view>

            <!-- 设备列表 -->
            <view class="device-card">
                <view class="card-title">退回设备</view>
                <view class="device-list">
                    <view class="device-item" v-for="(item, index) in deviceList" :key="index">
                        <view class="device-header">
                            <text class="device-title">设备 #{{ index + 1 }}</text>
                            <text class="device-status" :class="'status-' + (item.device ? item.device.status : 0)">
                                {{ getDeviceStatusText(item.device ? item.device.status : 0) }}
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
                            <view class="info-row">
                                <text class="info-label">初始价格：</text>
                                <text class="info-value">{{ formatPrice(item.device.initial_price) }}</text>
                            </view>
                            <view class="info-row" v-if="item.device.final_price">
                                <text class="info-label">最终价格：</text>
                                <text class="info-value">{{ formatPrice(item.device.final_price) }}</text>
                            </view>
                        </view>
                        <view class="device-empty" v-else>
                            <text>设备信息不存在</text>
                        </view>
                    </view>
                </view>
            </view>

            <!-- 操作按钮 -->
            <view class="action-bar" v-if="orderDetail.status === 1">
                <button class="btn-confirm" @click="handleConfirmReceive">确认收货</button>
            </view>
        </view>

        <!-- 确认收货弹窗 -->
        <uni-popup ref="confirmPopup" type="dialog">
            <uni-popup-dialog title="确认收货" content="确认已收到退回的设备吗？" :before-close="true" @confirm="confirmReceive"
                @close="closeConfirmPopup"></uni-popup-dialog>
        </uni-popup>
    </view>
</template>

<script>
import { getReturnOrderDetail, confirmReceiveReturnOrder } from '../../api/return_order';
// import uniLoadMore from '@/components/uni-load-more/uni-load-more.vue';
// import uniPopup from '@/components/uni-popup/uni-popup.vue';
// import uniPopupDialog from '@/components/uni-popup/uni-popup-dialog.vue';

export default {
    components: {
        // uniLoadMore,
        // uniPopup,
        // uniPopupDialog
    },
    data() {
        return {
            orderId: 0,
            orderDetail: {},
            deviceList: [],
            loading: true
        };
    },
    onLoad(options) {
        if (options.id) {
            this.orderId = options.id;
            this.getOrderDetail();
        } else {
            this.goBack();
        }
    },
    methods: {
        // 获取订单详情
        async getOrderDetail() {
            this.loading = true;
            try {
                const res = await getReturnOrderDetail(this.orderId);

                if (res.data.code === 200) {
                    this.orderDetail = res.data.data;
                    this.deviceList = res.data.data.returnDevices || [];
                } else {
                    uni.showToast({
                        title: res.data.message || '获取订单详情失败',
                        icon: 'none'
                    });
                    setTimeout(() => {
                        this.goBack();
                    }, 1500);
                }
            } catch (error) {
                console.error(error);
                uni.showToast({
                    title: '获取订单详情失败',
                    icon: 'none'
                });
                setTimeout(() => {
                    this.goBack();
                }, 1500);
            } finally {
                this.loading = false;
            }
        },

        // 返回上一页
        goBack() {
            uni.navigateBack();
        },

        // 获取状态文本
        getStatusText(status) {
            const statusMap = {
                0: '待处理',
                1: '退货中',
                2: '已完成',
                3: '已取消'
            };
            return statusMap[status] || '未知状态';
        },

        // 获取状态描述
        getStatusDesc(status) {
            const statusMap = {
                0: '商家正在处理您的退货申请',
                1: '商家已确认退货，请尽快寄回设备',
                2: '退货已完成',
                3: '退货已取消'
            };
            return statusMap[status] || '';
        },

        // 获取设备状态文本
        getDeviceStatusText(status) {
            const statusMap = {
                0: '待检测',
                1: '检测中',
                2: '已回收',
                3: '已退回',
                4: '已取消',
                6: '已退回'
            };
            return statusMap[status] || '未知状态';
        },

        // 格式化价格
        formatPrice(price) {
            if (!price) return '¥0.00';
            return `¥${Number(price).toFixed(2)}`;
        },

        // 复制文本
        copyText(text) {
            uni.setClipboardData({
                data: text,
                success: () => {
                    uni.showToast({
                        title: '复制成功',
                        icon: 'success'
                    });
                }
            });
        },

        // 处理确认收货
        handleConfirmReceive() {
            this.$refs.confirmPopup.open();
        },

        // 关闭确认弹窗
        closeConfirmPopup() {
            this.$refs.confirmPopup.close();
        },

        // 确认收货
        async confirmReceive() {
            try {
                const res = await confirmReceiveReturnOrder(this.orderId, {});

                if (res.data.code === 200) {
                    uni.showToast({
                        title: '确认收货成功',
                        icon: 'success'
                    });

                    // 刷新详情
                    this.getOrderDetail();
                } else {
                    uni.showToast({
                        title: res.data.message || '确认收货失败',
                        icon: 'none'
                    });
                }
            } catch (error) {
                console.error(error);
                uni.showToast({
                    title: '确认收货失败',
                    icon: 'none'
                });
            }
        }
    }
};
</script>

<style lang="scss" scoped>
.return-order-detail {
    min-height: 100vh;
    background-color: #f5f5f5;
    padding-bottom: 120rpx;
}

.nav-bar {
    display: flex;
    align-items: center;
    height: 90rpx;
    background-color: #ffffff;
    padding: 0 30rpx;
    position: relative;

    .nav-back {
        width: 60rpx;
        height: 60rpx;
        display: flex;
        align-items: center;
        justify-content: center;

        .iconfont {
            font-size: 36rpx;
            color: #333;
        }
    }

    .nav-title {
        position: absolute;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
    }
}

.loading-container {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 300rpx;
}

.status-card {
    background-color: #2979ff;
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

.info-card,
.device-card {
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
                    width: 150rpx;
                }

                .info-value {
                    color: #333;
                    flex: 1;
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

.action-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #ffffff;
    padding: 20rpx 30rpx;
    display: flex;
    justify-content: center;
    box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);

    .btn-confirm {
        background-color: #2979ff;
        color: #ffffff;
        border-radius: 40rpx;
        font-size: 30rpx;
        padding: 0 60rpx;
        height: 80rpx;
        line-height: 80rpx;
    }
}
</style>