<template>
    <view class="order-page">
        <!-- Custom Header -->
        <view class="custom-header">
            <view class="header-bg"></view>
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="nav-bar" :style="{ height: navBarHeight + 'px', paddingRight: menuButtonRight + 'px' }">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="24" color="#333"></u-icon>
                </view>
                <view class="page-title">我的订单</view>
            </view>
            
            <!-- 状态筛选 -->
            <view class="status-tabs">
                <view 
                    class="tab-item" 
                    :class="{ active: currentStatus === '' }"
                    @click="changeStatus('')"
                >全部</view>
                <view 
                    class="tab-item" 
                    :class="{ active: currentStatus === '0' }"
                    @click="changeStatus('0')"
                >待支付</view>
                <view 
                    class="tab-item" 
                    :class="{ active: currentStatus === '10' }"
                    @click="changeStatus('10')"
                >待接单</view>
                <view 
                    class="tab-item" 
                    :class="{ active: currentStatus === '20,30,40' }"
                    @click="changeStatus('20,30,40')"
                >进行中</view>
                <view 
                    class="tab-item" 
                    :class="{ active: currentStatus === '50' }"
                    @click="changeStatus('50')"
                >已完成</view>
            </view>
        </view>

        <!-- 订单列表 -->
        <scroll-view 
            scroll-y 
            class="order-scroll"
            :style="{ paddingTop: (statusBarHeight + navBarHeight + 70) + 'px' }"
            :scroll-top="scrollTop"
            :scroll-with-animation="true"
            @scrolltolower="loadMore"
            refresher-enabled
            :refresher-triggered="isRefreshing"
            @refresherrefresh="onRefresh"
        >
            <sd-order-item
                v-for="order in orderList"
                :key="order.id"
                :order="order"
                :show-user="true"
                :show-runner-first="true"
                :enable-default-actions="true"
                @click="goToDetail(order.id)"
                @cancel="cancelOrder"
                @pay="payOrder"
                @tip="goToTip"
                @evaluate="goToEvaluate"
            />

            <view class="empty" v-if="orderList.length === 0 && !loading">
                <u-icon name="order" size="120" color="#ccc"></u-icon>
                <text>暂无订单</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#1890ff"></u-loading-icon>
            </view>

            <view class="no-more" v-if="!hasMore && orderList.length > 0">
                <text>没有更多了</text>
            </view>
            
            <view style="height: 180rpx;"></view>
        </scroll-view>
        
        <!-- Shared Tabbar -->
        <tabbar addon="sd_xiaoyuan" />
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getOrderList, cancelOrder as cancelOrderApi } from '../../api/xiaoyuan'
import sdOrderItem from '../../components/sd-order-item.vue'
import tabbar from '@/components/tabbar/tabbar.vue'

const currentStatus = ref('')
const orderList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const scrollTop = ref(0)

/** 与 sd-order-item 展示字段对齐（接口字段名不一致时补齐） */
const normalizeOrderRow = (row: any) => {
    const o = { ...row }
    o.remark = o.remark || o.task_desc || ''
    o.member_credit = o.member_credit ?? o.credit_score ?? 100
    if (!o.create_time_text && o.create_time) {
        const t = Number(o.create_time)
        if (Number.isFinite(t)) {
            const ms = t > 1e12 ? t : t * 1000
            const d = new Date(ms)
            if (!Number.isNaN(d.getTime())) {
                o.create_time_text = `${d.getMonth() + 1}-${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
            }
        }
    }
    return o
}

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const menuButtonRight = ref(0)

onShow(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    menuButtonRight.value = sysInfo.windowWidth - menuButton.left
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    loadOrders(true)
    uni.hideTabBar()
})

const loadOrders = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const res: any = await getOrderList({
            status: currentStatus.value,
            page: page.value,
            limit: limit
        })
        
        if (res.code === 1) {
            const list = (res.data.list || []).map(normalizeOrderRow)
            if (refresh) {
                orderList.value = list
            } else {
                orderList.value = [...orderList.value, ...list]
            }
            
            if (list.length < limit) {
                hasMore.value = false
            } else {
                page.value++
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
        isRefreshing.value = false
    }
}

const changeStatus = (status: string) => {
    currentStatus.value = status
    scrollTop.value = 1
    scrollTop.value = 0
    loadOrders(true)
}

const onRefresh = () => {
    isRefreshing.value = true
    scrollTop.value = 1
    scrollTop.value = 0
    loadOrders(true)
}

const loadMore = () => {
    loadOrders()
}

const goToDetail = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/detail?id=${id}`
    })
}

const cancelOrder = async (id: number) => {
    uni.showModal({
        title: '提示',
        content: '确定要取消订单吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const result: any = await cancelOrderApi({ id })
                    if (result.code === 1) {
                        uni.showToast({ title: '取消成功', icon: 'success' })
                        loadOrders(true)
                    } else {
                        uni.showToast({ title: result.msg || '取消失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.showToast({ title: '网络错误', icon: 'none' })
                }
            }
        }
    })
}

const payOrder = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/detail?id=${id}`
    })
}

const goToEvaluate = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/evaluate?id=${id}`
    })
}

const goToTip = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/detail?id=${id}`
    })
}

const goBack = () => {
    uni.navigateBack()
}
</script>

<style lang="scss" scoped>
.order-page {
    min-height: 100vh;
    background: #f7f7f7;
    display: flex;
    flex-direction: column;
}

.custom-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 99;
    padding-bottom: 10rpx;
    
    .header-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 200rpx;
        background: linear-gradient(135deg, #c0fe95, #f7f7f7);
        z-index: -1;
    }
    
    .nav-bar {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20rpx 30rpx;
        position: relative;
        
        .back-btn {
            position: absolute;
            left: 30rpx;
            width: 60rpx;
            height: 60rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .page-title {
            font-size: 34rpx;
            font-weight: bold;
            color: #333;
        }
    }
}

.status-tabs {
    display: flex;
    background: transparent;
    padding: 10rpx 0;
}

.tab-item {
    flex: 1;
    text-align: center;
    font-size: 28rpx;
    color: #666;
    padding: 16rpx 0;
    position: relative;
    transition: all 0.3s;
    
    &.active {
        color: #333;
        font-weight: bold;
        font-size: 30rpx;
        
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

.order-scroll {
    width: auto;
    height: 100vh;
    box-sizing: border-box;
    padding: 20rpx;
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    image {
        width: 200rpx;
        height: 200rpx;
        margin-bottom: 20rpx;
    }
    
    text {
        font-size: 28rpx;
        color: #999;
    }
}

.loading-more, .no-more {
    padding: 24rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;
    
    text {
        font-size: 24rpx;
        color: #999;
    }
}
</style>
