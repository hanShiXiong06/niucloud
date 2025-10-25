<template>
    <view class="return-order-list">
        <!-- 顶部导航栏 -->
        <view class="nav-bar">
            <view class="nav-back" @click="goBack">
                <text class="iconfont iconarrow-left"></text>
            </view>
            <view class="nav-title">退回订单</view>
        </view>

        <!-- 状态筛选 -->
        <scroll-view scroll-x class="status-scroll" :scroll-left="scrollLeft">
            <view class="status-tabs">
                <view v-for="(item, index) in statusList" :key="index" class="status-tab"
                    :class="{ active: currentStatus === item.value }" @click="handleStatusChange(item.value)">
                    <text>{{ item.text }}</text>
                    <text v-if="statusCount[getStatusCountKey(item.value)]" class="status-count">
                        {{ statusCount[getStatusCountKey(item.value)] }}
                    </text>
                </view>
            </view>
        </scroll-view>

        <!-- 订单列表 -->
        <view class="order-list" v-if="orderList.length > 0">
            <view class="order-item" v-for="(item, index) in orderList" :key="index" @click="goToDetail(item.id)">
                <view class="order-header">
                    <view class="order-no">订单编号：{{ item.order_no }}</view>
                    <view class="order-status" :class="'status-' + item.status">
                        {{ getStatusText(item.status) }}
                    </view>
                </view>
                <view class="order-content">
                    <view class="device-info">
                        <view class="device-count">
                            退回设备：{{ item.returnDevices ? item.returnDevices.length : 0 }}台
                        </view>
                        <view class="express-info" v-if="item.express_company">
                            <text>{{ item.express_company }}</text>
                            <text v-if="item.express_no">：{{ item.express_no }}</text>
                        </view>
                    </view>
                    <view class="order-time">{{ item.create_at }}</view>
                </view>
                <view class="order-footer" v-if="item.status === 1">
                    <button class="btn-confirm" @click.stop="handleConfirmReceive(item.id)">确认收货</button>
                </view>
            </view>
        </view>

        <!-- 空状态 -->
        <view class="empty-state" v-else>
            <image class="empty-image" src="/static/images/empty-order.png" mode="aspectFit"></image>
            <text class="empty-text">暂无退回订单</text>
        </view>

        <!-- 加载更多 -->
        <uni-load-more :status="loadMoreStatus" :content-text="contentText"></uni-load-more>

        <!-- 确认收货弹窗 -->
        <uni-popup ref="confirmPopup" type="dialog">
            <uni-popup-dialog title="确认收货" content="确认已收到退回的设备吗？" :before-close="true" @confirm="confirmReceive"
                @close="closeConfirmPopup"></uni-popup-dialog>
        </uni-popup>
    </view>
</template>

<script>
import { getReturnOrderList, getReturnOrderStatusCount, getReturnOrderStatusList, confirmReceiveReturnOrder } from '../../api/return_order';
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
            statusList: [],
            currentStatus: -1,
            scrollLeft: 0,
            orderList: [],
            page: 1,
            limit: 10,
            hasMore: true,
            loadMoreStatus: 'more',
            contentText: {
                contentdown: '上拉加载更多',
                contentrefresh: '加载中...',
                contentnomore: '没有更多数据了'
            },
            statusCount: {
                all: 0,
                pending: 0,
                returning: 0,
                completed: 0,
                cancelled: 0
            },
            confirmOrderId: 0
        };
    },
    onLoad() {
        this.statusList = getReturnOrderStatusList();
        this.getStatusCount();
        this.getOrderList(true);
    },
    onPullDownRefresh() {
        this.page = 1;
        this.hasMore = true;
        this.loadMoreStatus = 'more';
        this.getStatusCount();
        this.getOrderList(true, () => {
            uni.stopPullDownRefresh();
        });
    },
    onReachBottom() {
        if (this.hasMore) {
            this.loadMoreStatus = 'loading';
            this.page++;
            this.getOrderList();
        }
    },
    methods: {
        // 获取订单列表
        async getOrderList(reset = false, callback) {
            if (reset) {
                this.page = 1;
                this.orderList = [];
            }

            try {
                const params = {
                    page: this.page,
                    limit: this.limit
                };

                if (this.currentStatus !== -1) {
                    params.status = this.currentStatus;
                }

                const res = await getReturnOrderList(params);

                if (res.data.code === 200) {
                    const { list, count } = res.data.data;

                    if (reset) {
                        this.orderList = list;
                    } else {
                        this.orderList = [...this.orderList, ...list];
                    }

                    this.hasMore = this.orderList.length < count;
                    this.loadMoreStatus = this.hasMore ? 'more' : 'noMore';
                } else {
                    uni.showToast({
                        title: res.data.message || '获取订单列表失败',
                        icon: 'none'
                    });
                }
            } catch (error) {
                console.error(error);
                uni.showToast({
                    title: '获取订单列表失败',
                    icon: 'none'
                });
            } finally {
                if (typeof callback === 'function') {
                    callback();
                }
            }
        },

        // 获取状态统计
        async getStatusCount() {
            try {
                const res = await getReturnOrderStatusCount();

                if (res.data.code === 200) {
                    this.statusCount = res.data.data;
                }
            } catch (error) {
                console.error(error);
            }
        },

        // 返回上一页
        goBack() {
            uni.navigateBack();
        },

        // 跳转到详情页
        goToDetail(id) {
            uni.navigateTo({
                url: `/addon/recycle/pages/return-order/detail?id=${id}`
            });
        },

        // 处理状态切换
        handleStatusChange(status) {
            if (this.currentStatus === status) return;

            this.currentStatus = status;
            this.page = 1;
            this.hasMore = true;
            this.loadMoreStatus = 'more';
            this.getOrderList(true);
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

        // 获取状态统计键名
        getStatusCountKey(status) {
            const keyMap = {
                '-1': 'all',
                0: 'pending',
                1: 'returning',
                2: 'completed',
                3: 'cancelled'
            };
            return keyMap[status] || 'all';
        },

        // 处理确认收货
        handleConfirmReceive(id) {
            this.confirmOrderId = id;
            this.$refs.confirmPopup.open();
        },

        // 关闭确认弹窗
        closeConfirmPopup() {
            this.$refs.confirmPopup.close();
        },

        // 确认收货
        async confirmReceive() {
            try {
                const res = await confirmReceiveReturnOrder(this.confirmOrderId, {});

                if (res.data.code === 200) {
                    uni.showToast({
                        title: '确认收货成功',
                        icon: 'success'
                    });

                    // 刷新列表和统计
                    this.getStatusCount();
                    this.getOrderList(true);
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
.return-order-list {
    min-height: 100vh;
    background-color: #f5f5f5;
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

.status-scroll {
    background-color: #ffffff;
    white-space: nowrap;
    height: 90rpx;
    border-bottom: 1rpx solid #f0f0f0;

    .status-tabs {
        display: flex;
        height: 100%;

        .status-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 0 30rpx;
            font-size: 28rpx;
            color: #666;
            position: relative;

            &.active {
                color: #2979ff;
                font-weight: bold;

                &::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 40rpx;
                    height: 4rpx;
                    background-color: #2979ff;
                    border-radius: 2rpx;
                }
            }

            .status-count {
                margin-left: 6rpx;
                font-size: 22rpx;
                background-color: #ff4d4f;
                color: #fff;
                border-radius: 20rpx;
                padding: 0 10rpx;
                min-width: 30rpx;
                text-align: center;
                line-height: 30rpx;
            }
        }
    }
}

.order-list {
    padding: 20rpx;

    .order-item {
        background-color: #ffffff;
        border-radius: 12rpx;
        margin-bottom: 20rpx;
        overflow: hidden;
        box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.05);

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20rpx 30rpx;
            border-bottom: 1rpx solid #f5f5f5;

            .order-no {
                font-size: 26rpx;
                color: #666;
            }

            .order-status {
                font-size: 26rpx;
                font-weight: bold;

                &.status-0 {
                    color: #faad14;
                }

                &.status-1 {
                    color: #1890ff;
                }

                &.status-2 {
                    color: #52c41a;
                }

                &.status-3 {
                    color: #999;
                }
            }
        }

        .order-content {
            padding: 20rpx 30rpx;

            .device-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15rpx;

                .device-count {
                    font-size: 28rpx;
                    color: #333;
                }

                .express-info {
                    font-size: 26rpx;
                    color: #666;
                }
            }

            .order-time {
                font-size: 24rpx;
                color: #999;
            }
        }

        .order-footer {
            padding: 20rpx 30rpx;
            border-top: 1rpx solid #f5f5f5;
            display: flex;
            justify-content: flex-end;

            .btn-confirm {
                background-color: #2979ff;
                color: #ffffff;
                font-size: 26rpx;
                padding: 0 30rpx;
                height: 60rpx;
                line-height: 60rpx;
                border-radius: 30rpx;
            }
        }
    }
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 100rpx 0;

    .empty-image {
        width: 200rpx;
        height: 200rpx;
        margin-bottom: 20rpx;
    }

    .empty-text {
        font-size: 28rpx;
        color: #999;
    }
}
</style>