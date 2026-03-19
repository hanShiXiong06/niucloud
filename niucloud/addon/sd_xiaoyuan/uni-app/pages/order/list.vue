<template>
    <view class="order-page">
        <!-- Custom Header -->
        <view class="custom-header">
            <view class="header-bg"></view>
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="nav-bar" :style="{ height: navBarHeight + 'px', paddingRight: menuButtonRight + 'px' }">
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
                    :class="{ active: currentStatus === '20' }"
                    @click="changeStatus('20')"
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
            @scrolltolower="loadMore"
            refresher-enabled
            :refresher-triggered="isRefreshing"
            @refresherrefresh="onRefresh"
        >
            <sd-order-item 
                    v-for="order in orderList" 
                    :key="order.id" 
                    :order="order"
                    @click="goToDetail(order.id)"
                >
                    <template #actions>
                        <template v-if="order.status === 0">
                            <button class="btn-cancel" @click.stop="cancelOrder(order.id)">取消订单</button>
                            <button class="btn-pay" @click.stop="payOrder(order.id)">立即支付</button>
                        </template>
                        <template v-if="order.status === 50 && !order.is_evaluated">
                            <button class="btn-tip" @click.stop="goToTip(order.id)">打赏</button>
                            <button class="btn-evaluate" @click.stop="goToEvaluate(order.id)">去评价</button>
                        </template>
                    </template>
                </sd-order-item>

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
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getOrderList, cancelOrder as cancelOrderApi, payOrder as payOrderApi } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import sdOrderItem from '../../components/sd-order-item.vue'

const currentStatus = ref('')
const orderList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

const navigateTo = (url: string) => {
    uni.navigateTo({ url })
}

const reLaunch = (url: string) => {
    uni.reLaunch({ url })
}

const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '代买服务',
    'ERRAND': '跑腿服务',
    'QUEUE': '代排队',
    'PRINT': '代打印',
    'SEAT': '代占座',
    'CARRY': '帮搬运',
    'TRASH': '扔垃圾',
    'CLEAN': '代清洁',
    'HELP': '帮帮忙',
    'GAME': '游戏陪玩'
}

const getExtTags = (order: any) => {
    const tags: string[] = []
    let ext: any = {}
    if (order.ext) {
        try { ext = typeof order.ext === 'string' ? JSON.parse(order.ext) : order.ext } catch {}
    }
    const taskType = order.task_type || ''
    if (taskType === 'GAME') {
        if (ext.game_type) tags.push(ext.game_type)
        if (ext.service_type) tags.push(ext.service_type)
        if (ext.rank_level) tags.push(ext.rank_level)
        if (ext.voice_chat) tags.push('语音陪玩')
    } else if (taskType === 'PRINT') {
        if (ext.print_side) tags.push(ext.print_side === 'single' ? '单面' : '双面')
        if (ext.print_color) tags.push(ext.print_color === 'color' ? '彩印' : '黑白')
        if (ext.paper_size) tags.push(ext.paper_size)
        if (ext.page_count) tags.push(ext.page_count + '页')
        if (ext.files && ext.files.length > 0) tags.push(ext.files.length + '个文件')
    } else if (taskType === 'EXPRESS') {
        if (ext.express_company) tags.push(ext.express_company)
        if (ext.weight) tags.push(ext.weight + 'kg')
    } else if (taskType === 'BUY') {
        if (ext.shop_name) tags.push(ext.shop_name)
    }
    if (order.is_urgent) tags.push('加急')
    return tags.slice(0, 4)
}

const statusMap: Record<number, string> = {
    0: '待支付',
    10: '待接单',
    20: '已接单',
    30: '取货中',
    40: '配送中',
    50: '已完成',
    90: '已取消',
    91: '已退款'
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
            if (refresh) {
                orderList.value = res.data.list
            } else {
                orderList.value = [...orderList.value, ...res.data.list]
            }
            
            if (res.data.list.length < limit) {
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
    loadOrders(true)
}

const onRefresh = () => {
    isRefreshing.value = true
    loadOrders(true)
}

const loadMore = () => {
    loadOrders()
}

const getTaskTypeName = (type: string) => {
    return taskTypeMap[type] || type
}

const getStatusText = (status: number) => {
    return statusMap[status] || '未知'
}

const formatTime = (timestamp: number) => {
    const date = new Date(timestamp * 1000)
    return `${date.getMonth() + 1}-${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`
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

const getOrderImages = (order: any) => {
    if (order.images) {
        let images = order.images
        if (typeof images === 'string') {
            try {
                const parsed = JSON.parse(images)
                images = Array.isArray(parsed) ? parsed : images.split(',').filter((s: string) => s)
            } catch {
                images = images.split(',').filter((s: string) => s)
            }
        }
        return Array.isArray(images) ? images : []
    }
    return []
}

const getAllImages = (order: any) => {
    const list: string[] = []
    if (order.goods_image) list.push(order.goods_image)
    const imgs = getOrderImages(order)
    for (let i = 0; i < imgs.length; i++) {
        if (list.indexOf(imgs[i]) === -1) list.push(imgs[i])
    }
    return list.slice(0, 3)
}

const getAllImagesCount = (order: any) => {
    let count = order.goods_image ? 1 : 0
    count += getOrderImages(order).length
    return count
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
    flex: 1;
    padding: 20rpx;
}

.order-item {
    background: #fff;
    border-radius: 24rpx;
    padding: 30rpx;
    margin-bottom: 24rpx;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);
    border: 1rpx solid transparent;
    transition: all 0.2s;
    
    &:active {
        transform: scale(0.98);
        background: #fafafa;
    }
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24rpx;
    padding-bottom: 20rpx;
    border-bottom: 1rpx solid #f8fafc;
    
    .order-no {
        font-size: 24rpx;
        color: #94a3b8;
    }
    
    .order-status {
        font-size: 26rpx;
        font-weight: bold;
        padding: 4rpx 16rpx;
        border-radius: 8rpx;
        
        &.status-0 { background: #fff7ed; color: #f97316; }
        &.status-10 { background: #f0fdf4; color: #22c55e; }
        &.status-20, &.status-30, &.status-40 { background: #eff6ff; color: #3b82f6; }
        &.status-50 { background: #f8fafc; color: #64748b; }
        &.status-90, &.status-91 { background: #fef2f2; color: #ef4444; }
    }
}

.order-content {
    .order-main {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12rpx;
        margin-bottom: 16rpx;
    }

    .task-type {
        .type-tag {
            display: inline-block;
            padding: 6rpx 20rpx;
            background: #c0fe95;
            color: #333;
            font-size: 24rpx;
            font-weight: bold;
            border-radius: 12rpx;
        }
    }

    .ext-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8rpx;

        .ext-tag {
            display: inline-block;
            padding: 4rpx 12rpx;
            background: #f0f0f0;
            color: #666;
            font-size: 22rpx;
            border-radius: 6rpx;
        }
    }

    .order-images {
        display: flex;
        gap: 12rpx;
        margin-bottom: 20rpx;

        .order-image {
            width: 160rpx;
            height: 160rpx;
            border-radius: 12rpx;
            flex-shrink: 0;
        }

        .img-more {
            width: 160rpx;
            height: 160rpx;
            border-radius: 12rpx;
            background: rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28rpx;
            color: #999;
            flex-shrink: 0;
        }
    }
}

.address-info {
    display: flex;
    flex-direction: column;
    gap: 16rpx;
    
    .address-item {
        display: flex;
        align-items: flex-start;
        
        .addr-tag {
            width: 36rpx;
            height: 36rpx;
            border-radius: 50%;
            font-size: 20rpx;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12rpx;
            flex-shrink: 0;
            
            &.pickup { background: #52c41a; }
            &.receive { background: #ff4d4f; }
        }
        
        .address {
            font-size: 28rpx;
            color: #1e293b;
            flex: 1;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
        }
    }
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30rpx;
    padding-top: 24rpx;
    border-top: 1rpx solid #f8fafc;
    
    .time {
        font-size: 24rpx;
        color: #94a3b8;
    }
    
    .price-wrap {
        display: flex;
        align-items: baseline;
        
        .unit {
            font-size: 24rpx;
            color: #ef4444;
            font-weight: bold;
            margin-right: 2rpx;
        }
        
        .price {
            font-size: 36rpx;
            color: #ef4444;
            font-weight: 900;
        }
    }
}

.order-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 30rpx;
    gap: 20rpx;
    
    button {
        margin: 0;
        padding: 0 36rpx;
        height: 72rpx;
        line-height: 72rpx;
        font-size: 26rpx;
        font-weight: bold;
        border-radius: 36rpx;
        transition: all 0.2s;
        
        &::after { border: none; }
    }
    
    .btn-cancel {
        background: #f1f5f9;
        color: #64748b;
    }
    
    .btn-pay, .btn-evaluate {
        background: #000;
        color: #fff;
        box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
        
        &:active {
            transform: scale(0.95);
        }
    }
    
    .btn-tip {
        background: #fff7ed;
        color: #f97316;
        border: 1rpx solid #fed7aa;
    }
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
