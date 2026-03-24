<template>
    <view class="coupon-page">
        <!-- 标签切换 -->
        <view class="tabs">
            <view class="tab-item" :class="{ active: currentTab === 'available' }" @click="currentTab = 'available'">可领取</view>
            <view class="tab-item" :class="{ active: currentTab === 'my' }" @click="currentTab = 'my'">我的优惠券</view>
        </view>

        <!-- 可领取优惠券 -->
        <scroll-view 
            v-if="currentTab === 'available'"
            scroll-y 
            class="coupon-scroll"
            @scrolltolower="loadMore"
        >
            <view class="coupon-item" v-for="item in couponList" :key="item.id">
                <view class="coupon-left" :class="item.type === 'DISCOUNT' ? 'discount' : 'amount'">
                    <view class="value">
                        <text class="num">{{ item.type === 'DISCOUNT' ? item.discount_value : item.discount_value }}</text>
                        <text class="unit">{{ item.type === 'DISCOUNT' ? '折' : '元' }}</text>
                    </view>
                    <text class="condition">{{ item.min_amount > 0 ? '满' + item.min_amount + '元可用' : '无门槛' }}</text>
                </view>
                <view class="coupon-right">
                    <text class="name">{{ item.name }}</text>
                    <text class="time">{{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</text>
                    <text class="remain">剩余 {{ item.total_count - item.receive_count }} 张</text>
                </view>
                <view class="coupon-action">
                    <button class="receive-btn" @click="receiveCoupon(item)">领取</button>
                </view>
            </view>

            <view class="empty" v-if="couponList.length === 0 && !loading">
                <u-icon name="coupon" size="120" color="#ccc"></u-icon>
                <text>暂无可领取的优惠券</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
        </scroll-view>

        <!-- 我的优惠券 -->
        <scroll-view 
            v-if="currentTab === 'my'"
            scroll-y 
            class="coupon-scroll"
            @scrolltolower="loadMoreMy"
        >
            <!-- 状态筛选 -->
            <view class="status-filter">
                <view class="filter-item" :class="{ active: myStatus === '' }" @click="changeMyStatus('')">全部</view>
                <view class="filter-item" :class="{ active: myStatus === '0' }" @click="changeMyStatus('0')">未使用</view>
                <view class="filter-item" :class="{ active: myStatus === '1' }" @click="changeMyStatus('1')">已使用</view>
                <view class="filter-item" :class="{ active: myStatus === '2' }" @click="changeMyStatus('2')">已过期</view>
            </view>

            <view class="coupon-item" :class="{ used: item.status !== 0 }" v-for="item in myCouponList" :key="item.id">
                <view class="coupon-left" :class="item.coupon_type === 'DISCOUNT' ? 'discount' : 'amount'">
                    <view class="value">
                        <text class="num">{{ item.coupon_type === 'DISCOUNT' ? item.discount_value : item.discount_value }}</text>
                        <text class="unit">{{ item.coupon_type === 'DISCOUNT' ? '折' : '元' }}</text>
                    </view>
                    <text class="condition">{{ item.min_amount > 0 ? '满' + item.min_amount + '元可用' : '无门槛' }}</text>
                </view>
                <view class="coupon-right">
                    <text class="name">{{ item.coupon_name }}</text>
                    <text class="time">有效期至 {{ formatTime(item.expire_time) }}</text>
                </view>
                <view class="coupon-status" v-if="item.status !== 0">
                    <text>{{ item.status === 1 ? '已使用' : '已过期' }}</text>
                </view>
            </view>

            <view class="empty" v-if="myCouponList.length === 0 && !loadingMy">
                <u-icon name="coupon" size="120" color="#ccc"></u-icon>
                <text>暂无优惠券</text>
            </view>

            <view class="loading-more" v-if="loadingMy">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { getCouponList, receiveCoupon as receiveCouponApi, getMyCoupons } from '../../api/xiaoyuan'

const currentTab = ref('available')
const couponList = ref<any[]>([])
const myCouponList = ref<any[]>([])
const loading = ref(false)
const loadingMy = ref(false)
const hasMore = ref(true)
const hasMoreMy = ref(true)
const page = ref(1)
const pageMy = ref(1)
const myStatus = ref('')
const limit = 10

onMounted(() => {
    loadCoupons()
})

watch(currentTab, (val) => {
    if (val === 'my' && myCouponList.value.length === 0) {
        loadMyCoupons()
    }
})

const loadCoupons = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const res: any = await getCouponList({ page: page.value, limit })
        if (res.code === 1) {
            if (refresh) {
                couponList.value = res.data.list || res.data
            } else {
                couponList.value = [...couponList.value, ...(res.data.list || res.data)]
            }
            
            const list = res.data.list || res.data
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
    }
}

const loadMyCoupons = async (refresh = false) => {
    if (loadingMy.value) return
    
    if (refresh) {
        pageMy.value = 1
        hasMoreMy.value = true
    }
    
    if (!hasMoreMy.value) return
    
    loadingMy.value = true
    
    try {
        const res: any = await getMyCoupons({ page: pageMy.value, limit, status: myStatus.value })
        if (res.code === 1) {
            if (refresh) {
                myCouponList.value = res.data.list || res.data
            } else {
                myCouponList.value = [...myCouponList.value, ...(res.data.list || res.data)]
            }
            
            const list = res.data.list || res.data
            if (list.length < limit) {
                hasMoreMy.value = false
            } else {
                pageMy.value++
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loadingMy.value = false
    }
}

const loadMore = () => {
    loadCoupons()
}

const loadMoreMy = () => {
    loadMyCoupons()
}

const changeMyStatus = (status: string) => {
    myStatus.value = status
    loadMyCoupons(true)
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getMonth() + 1}.${date.getDate()}`
}

const receiveCoupon = async (item: any) => {
    try {
        uni.showLoading({ title: '领取中...' })
        const res: any = await receiveCouponApi({ coupon_id: item.id })
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '领取成功', icon: 'success' })
            loadCoupons(true)
        } else {
            uni.showToast({ title: res.msg || '领取失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.coupon-page {
    min-height: 100vh;
    background: #f7f7f7;
    display: flex;
    flex-direction: column;
}

.tabs {
    display: flex;
    background: #fff;
    
    .tab-item {
        flex: 1;
        text-align: center;
        padding: 24rpx 0;
        font-size: 30rpx;
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
                width: 60rpx;
                height: 6rpx;
                background: #c0fe95;
                border-radius: 3rpx;
            }
        }
    }
}

.coupon-scroll {
    flex: 1;
    padding: 20rpx;
}

.status-filter {
    display: flex;
    gap: 20rpx;
    margin-bottom: 20rpx;
    
    .filter-item {
        padding: 12rpx 24rpx;
        background: #fff;
        border-radius: 30rpx;
        font-size: 26rpx;
        color: #666;
        
        &.active {
            background: #c0fe95;
            color: #333;
            font-weight: bold;
        }
    }
}

.coupon-item {
    display: flex;
    background: #fff;
    border-radius: 16rpx;
    margin-bottom: 20rpx;
    overflow: hidden;
    
    &.used {
        opacity: 0.6;
    }
}

.coupon-left {
    width: 200rpx;
    padding: 24rpx 20rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    
    &.amount {
        background: linear-gradient(135deg, #ff6b00, #ff9500);
    }
    
    &.discount {
        background: linear-gradient(135deg, #52c41a, #73d13d);
    }
    
    .value {
        display: flex;
        align-items: baseline;
        
        .num {
            font-size: 48rpx;
            font-weight: bold;
        }
        
        .unit {
            font-size: 24rpx;
            margin-left: 4rpx;
        }
    }
    
    .condition {
        font-size: 22rpx;
        margin-top: 8rpx;
        opacity: 0.9;
    }
}

.coupon-right {
    flex: 1;
    padding: 24rpx 20rpx;
    display: flex;
    flex-direction: column;
    justify-content: center;
    
    .name {
        font-size: 30rpx;
        color: #333;
        font-weight: bold;
        margin-bottom: 12rpx;
    }
    
    .time, .remain {
        font-size: 24rpx;
        color: #999;
        margin-bottom: 4rpx;
    }
}

.coupon-action {
    display: flex;
    align-items: center;
    padding-right: 20rpx;
    
    .receive-btn {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
        border: none;
        border-radius: 30rpx;
        padding: 0 30rpx;
        height: 60rpx;
        line-height: 60rpx;
        font-size: 26rpx;
        font-weight: bold;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    }
}

.coupon-status {
    display: flex;
    align-items: center;
    padding-right: 30rpx;
    
    text {
        font-size: 26rpx;
        color: #999;
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

.loading-more {
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
