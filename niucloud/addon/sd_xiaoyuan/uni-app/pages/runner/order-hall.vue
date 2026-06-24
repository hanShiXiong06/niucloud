<template>
    <view class="order-hall">
        <!-- 顶部筛选 -->
        <scroll-view scroll-x class="filter-bar">
            <view class="filter-items">
                <view class="filter-item" :class="{ active: currentType === '' }" @click="changeType('')">全部</view>
                <view class="filter-item" :class="{ active: currentType === 'EXPRESS' }" @click="changeType('EXPRESS')">代取快递</view>
                <view class="filter-item" :class="{ active: currentType === 'BUY' }" @click="changeType('BUY')">帮我买</view>
                <view class="filter-item" :class="{ active: currentType === 'SEND' }" @click="changeType('SEND')">帮我送</view>
                <view class="filter-item" :class="{ active: currentType === 'PRINT' }" @click="changeType('PRINT')">帮打印</view>
                <view class="filter-item" :class="{ active: currentType === 'TRASH' }" @click="changeType('TRASH')">扔垃圾</view>
                <view class="filter-item" :class="{ active: currentType === 'CARRY' }" @click="changeType('CARRY')">帮搬运</view>
                <view class="filter-item" :class="{ active: currentType === 'CLEAN' }" @click="changeType('CLEAN')">代清洁</view>
                <view class="filter-item" :class="{ active: currentType === 'HELP' }" @click="changeType('HELP')">帮帮忙</view>
                <view class="filter-item" :class="{ active: currentType === 'GAME' }" @click="changeType('GAME')">游戏陪练</view>
                <view class="filter-item" :class="{ active: currentType === 'PARTTIME' }" @click="changeType('PARTTIME')">兼职招聘</view>
                <view class="filter-item" :class="{ active: currentType === 'COMPANION' }" @click="changeType('COMPANION')">约伴组局</view>
            </view>
        </scroll-view>

        <!-- 订单列表 -->
        <scroll-view 
            scroll-y 
            class="order-scroll"
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
                >
                    <template #actions>
                        <button class="accept-btn" @click.stop="acceptOrder(order.id)">接单</button>
                    </template>
                </sd-order-item>

            <view class="empty" v-if="orderList.length === 0 && !loading">
                <u-icon name="order" size="120" color="#ccc"></u-icon>
                <text>暂无待接订单</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>

            <view class="no-more" v-if="!hasMore && orderList.length > 0">
                <text>没有更多了</text>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getOrderHall, acceptOrder as acceptOrderApi } from '../../api/runner'
import sdOrderItem from '../../components/sd-order-item.vue'

const currentType = ref('')
const orderList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '代买服务',
    'SEND': '帮我送',
    'ERRAND': '跑腿服务',
    'QUEUE': '代排队',
    'CLASS': '代上课',
    'PRINT': '帮打印',
    'SEAT': '代占座',
    'CLEAN': '代清洁',
    'TRASH': '扔垃圾',
    'CARRY': '帮搬运',
    'HELP': '帮帮忙',
    'GAME': '游戏陪练',
    'GROUP': '拼单',
    'PARTTIME': '兼职招聘',
    'COMPANION': '约伴组局'
}

onMounted(() => {
    loadOrders()
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
        const params: any = {
            page: page.value,
            limit: limit
        }
        
        if (currentType.value) {
            params.task_type = currentType.value
        }
        
        const res: any = await getOrderHall(params)
        
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

const changeType = (type: string) => {
    currentType.value = type
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

const formatTime = (timestamp: number) => {
    const now = Date.now() / 1000
    const diff = now - timestamp
    
    if (diff < 60) {
        return '刚刚'
    } else if (diff < 3600) {
        return Math.floor(diff / 60) + '分钟前'
    } else if (diff < 86400) {
        return Math.floor(diff / 3600) + '小时前'
    } else {
        const date = new Date(timestamp * 1000)
        return `${date.getMonth() + 1}-${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`
    }
}

const calculateIncome = (fee: number) => {
    return (fee * 0.8).toFixed(2)
}

const acceptOrder = async (id: number) => {
    uni.showModal({
        title: '确认接单',
        content: '确定要接取这个订单吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    uni.showLoading({ title: '接单中...' })
                    const result: any = await acceptOrderApi({ id })
                    uni.hideLoading()
                    
                    if (result.code === 1) {
                        uni.showToast({ title: '接单成功', icon: 'success' })
                        loadOrders(true)
                    } else {
                        uni.showToast({ title: result.msg || '接单失败', icon: 'none' })
                    }
                } catch (e: any) {
                    uni.hideLoading()
                    // 框架已经显示了错误信息，这里只处理真正的网络错误
                    if (e && e.msg) {
                        uni.showToast({ title: e.msg, icon: 'none' })
                    } else if (!e || !e.code) {
                        uni.showToast({ title: '网络错误', icon: 'none' })
                    }
                }
            }
        }
    })
}
</script>

<style lang="scss" scoped>
.order-hall {
    min-height: 100vh;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
}

.filter-bar {
    background: #fff;
    padding: 20rpx 0;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
}

.filter-items {
    display: inline-flex;
    gap: 16rpx;
    padding: 0 20rpx;
}

.filter-item {
    padding: 16rpx 30rpx;
    background: #f5f5f5;
    border-radius: 30rpx;
    font-size: 26rpx;
    color: #666;
    white-space: nowrap;
    flex-shrink: 0;
    
    &.active {
        background: #c0fe95;
        color: #333;
        font-weight: bold;
    }
}

.order-scroll {
    flex: 1;
    padding: 20rpx;width: auto;
}

.order-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20rpx;
    
    .task-type {
        display: flex;
        align-items: center;
        gap: 12rpx;
        
        .type-tag {
            padding: 6rpx 16rpx;
            background: #c0fe95;
            color: #333;
            font-size: 24rpx;
            border-radius: 6rpx;
            font-weight: bold;
        }
        
        .urgent-tag {
            padding: 6rpx 16rpx;
            background: #fff2f0;
            color: #ff4d4f;
            font-size: 24rpx;
            border-radius: 6rpx;
        }
    }
    
    .order-time {
        font-size: 24rpx;
        color: #999;
    }
}

.order-content {
    .address-info {
        .address-item {
            display: flex;
            align-items: center;
            margin-bottom: 12rpx;
            
            .dot {
                width: 12rpx;
                height: 12rpx;
                border-radius: 50%;
                margin-right: 16rpx;
                
                &.pickup { background: #c0fe95; }
                &.receive { background: #000; }
            }
            
            .address {
                font-size: 28rpx;
                color: #333;
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }
    }
    
    .distance-info {
        display: flex;
        align-items: center;
        margin-top: 16rpx;
        font-size: 24rpx;
        color: #999;
        
        .iconfont {
            margin-right: 8rpx;
        }
    }
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20rpx;
    padding-top: 20rpx;
    border-top: 1rpx solid #f0f0f0;
    
    .price-info {
        .label {
            font-size: 24rpx;
            color: #999;
            margin-right: 8rpx;
        }
        
        .price {
            font-size: 30rpx;
            color: #ff6b00;
            font-weight: bold;
        }
    }
    
    .accept-btn {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
        border: none;
        border-radius: 16rpx;
        padding: 0 50rpx;
        height: 70rpx;
        line-height: 70rpx;
        font-size: 28rpx;
        font-weight: bold;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    text {
        font-size: 28rpx;
        color: #999;
        margin-top: 20rpx;
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
