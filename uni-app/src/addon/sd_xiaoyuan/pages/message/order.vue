<template>
    <view class="message-page">
        <!-- 顶部导航栏 -->
        <view class="navbar">
            <view class="navbar-bg"></view>
            <view class="navbar-content">
                <text class="page-title">订单消息</text>
            </view>
        </view>

        <!-- 消息列表 -->
        <scroll-view 
            scroll-y 
            class="message-scroll" 
            refresher-enabled
            :refresher-triggered="isRefreshing"
            @refresherrefresh="onRefresh"
            @scrolltolower="loadMore"
        >
            <view class="message-list-content">
                <view 
                    class="message-item" 
                    v-for="(item, index) in messageList" 
                    :key="item.id"
                    @click="goToDetail(item)"
                    :style="{ animationDelay: index * 0.05 + 's' }"
                >
                    <view class="message-avatar">
                        <image :src="img(item.avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                        <view class="unread-badge" v-if="!item.is_read">未读</view>
                    </view>
                    <view class="message-content">
                        <view class="message-top">
                            <text class="message-title">{{ item.title }}</text>
                            <text class="message-time">{{ formatTime(item.create_time) }}</text>
                        </view>
                        <view class="message-bottom">
                            <text class="message-text">{{ item.content }}</text>
                        </view>
                    </view>
                </view>

                <view class="empty-state" v-if="messageList.length === 0 && !loading">
                    <u-icon name="order" size="120" color="#ccc"></u-icon>
                    <text>暂无订单消息</text>
                </view>

                <view class="loading-more" v-if="loading">
                    <u-loading-icon mode="circle" color="#52c41a"></u-loading-icon>
                </view>

                <view class="no-more" v-if="!hasMore && messageList.length > 0">
                    <text>没有更多了</text>
                </view>
            </view>
        </scroll-view>

        <!-- 底部导航 -->
        <tabbar addon="sd_xiaoyuan" />
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { getMessageList } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const messageList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 15

onMounted(() => {
    loadMessages()
})

const loadMessages = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const res: any = await getMessageList({
            page: page.value,
            limit,
            type: 'ORDER'
        })
        
        if (res.code === 1) {
            const list = res.data.list || []
            if (refresh) {
                messageList.value = list
            } else {
                messageList.value = [...messageList.value, ...list]
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

const onRefresh = () => {
    isRefreshing.value = true
    loadMessages(true)
}

const loadMore = () => {
    loadMessages()
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const now = Date.now() / 1000
    const diff = now - timestamp
    
    if (diff < 60) return '刚刚'
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前'
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前'
    
    const date = new Date(timestamp * 1000)
    return `${date.getMonth() + 1}-${date.getDate()}`
}

const goToDetail = (item: any) => {
    if (!item.link_id) return
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/detail?id=${item.link_id}`
    })
}
</script>

<style lang="scss" scoped>
.message-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.navbar {
    position: relative;
    height: 88rpx;
    
    .navbar-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 100%;
        background: linear-gradient(135deg, #52c41a, #73d13d);
    }
    
    .navbar-content {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        padding-top: var(--status-bar-height);
        
        .page-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #fff;
        }
    }
}

.message-scroll {
    height: calc(100vh - 88rpx - 100rpx);
}

.message-list-content {
    padding: 20rpx;
}

.message-item {
    display: flex;
    padding: 24rpx;
    background: #fff;
    border-radius: 16rpx;
    margin-bottom: 20rpx;
    animation: fadeInUp 0.3s ease-out;
    
    .message-avatar {
        position: relative;
        margin-right: 20rpx;
        
        image {
            width: 80rpx;
            height: 80rpx;
            border-radius: 50%;
        }
        
        .unread-badge {
            position: absolute;
            top: -6rpx;
            right: -6rpx;
            padding: 2rpx 8rpx;
            background: #ff4d4f;
            color: #fff;
            font-size: 20rpx;
            border-radius: 10rpx;
        }
    }
    
    .message-content {
        flex: 1;
        
        .message-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12rpx;
            
            .message-title {
                font-size: 28rpx;
                font-weight: bold;
                color: #333;
            }
            
            .message-time {
                font-size: 24rpx;
                color: #999;
            }
        }
        
        .message-bottom {
            .message-text {
                font-size: 26rpx;
                color: #666;
                line-height: 1.5;
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                overflow: hidden;
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
    display: flex;
    justify-content: center;
    padding: 30rpx 0;
    
    text {
        font-size: 26rpx;
        color: #999;
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20rpx);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
