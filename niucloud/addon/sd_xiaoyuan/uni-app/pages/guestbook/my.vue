<template>
    <view class="my-guestbook-page">
        <!-- 导航栏 -->
        <u-navbar title="我的留言" :autoBack="true" bgColor="#fff"></u-navbar>

        <!-- 留言列表 -->
        <view class="main-container">
            <scroll-view 
                scroll-y 
                class="message-scroll"
                @scrolltolower="loadMore"
                refresher-enabled
                @refresherrefresh="onRefresh"
                :refresher-triggered="refreshing"
            >
                <!-- 列表为空 -->
                <view class="empty" v-if="messageList.length === 0 && !loading">
                    <image src="/static/resource/images/empty.png" mode="widthFix" style="width: 200rpx;"></image>
                    <text class="empty-title">暂无留言</text>
                    <text class="empty-desc">快去留言板留下你的足迹吧~</text>
                </view>

                <!-- 留言卡片 -->
                <view class="message-card" v-for="item in messageList" :key="item.id">
                    <view class="card-header">
                        <view class="status-tag" :class="{ pending: item.status === 0 }">
                            {{ item.status === 1 ? '已发布' : '待审核' }}
                        </view>
                        <text class="time">{{ formatTime(item.create_time) }}</text>
                        <view class="more-btn" @click.stop="showActionSheet(item)">
                            <u-icon name="more-dot-fill" size="20" color="#ccc"></u-icon>
                        </view>
                    </view>

                    <view class="card-body">
                        <text class="message-content">{{ item.content }}</text>
                        
                        <!-- 图片网格 -->
                        <view class="media-grid" v-if="item.images && parseImages(item.images).length > 0">
                            <view class="grid-layout" :class="'layout-' + getGridClass(parseImages(item.images).length)">
                                <image 
                                    v-for="(imgUrl, idx) in parseImages(item.images).slice(0, 9)" 
                                    :key="idx"
                                    :src="img(imgUrl)"
                                    mode="aspectFill"
                                    class="media-item"
                                    @click.stop="previewImage(parseImages(item.images), idx)"
                                ></image>
                            </view>
                        </view>

                        <!-- 管理员回复 -->
                        <view class="reply-box" v-if="item.reply">
                            <view class="reply-header">
                                <u-icon name="chat-fill" size="14" color="#00c853"></u-icon>
                                <text class="reply-label">管理员回复</text>
                            </view>
                            <text class="reply-content">{{ item.reply }}</text>
                        </view>
                    </view>
                </view>

                <view class="loading-status" v-if="loading || !hasMore">
                    <u-loading-icon v-if="loading" mode="circle" color="#00c853"></u-loading-icon>
                    <text v-else-if="messageList.length > 0" class="no-more">—— 到底啦 ——</text>
                </view>
                
                <view style="height: 40rpx;"></view>
            </scroll-view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMyGuestbook, deleteGuestbook } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const messageList = ref<any[]>([])
const loading = ref(false)
const refreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

onMounted(() => {
    loadMessages()
})

onShow(() => {
    loadMessages(true)
})

const loadMessages = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    const params = {
        page: page.value,
        limit
    }
    
    const res: any = await getMyGuestbook(params)
    
    if (res.code === 1) {
        if (refresh) {
            messageList.value = res.data.list || []
        } else {
            messageList.value = [...messageList.value, ...(res.data.list || [])]
        }
        
        if ((res.data.list || []).length < limit) {
            hasMore.value = false
        } else {
            page.value++
        }
    }
    
    loading.value = false
    refreshing.value = false
}

const loadMore = () => {
    loadMessages()
}

const onRefresh = () => {
    refreshing.value = true
    loadMessages(true)
}

const parseImages = (images: any) => {
    if (typeof images === 'string') {
        if (!images) return []
        try {
            const parsed = JSON.parse(images)
            if (Array.isArray(parsed)) return parsed
            return images.split(',').filter((s: string) => s)
        } catch {
            return images.split(',').filter((s: string) => s)
        }
    }
    return images || []
}

const getGridClass = (count: number) => {
    if (count === 1) return '1'
    if (count === 2) return '2'
    if (count === 4) return '2' 
    return '3'
}

const formatTime = (time: any) => {
    if (!time) return ''
    if (typeof time === 'string') return time
    const now = Date.now() / 1000
    const diff = now - time
    if (diff < 60) return '刚刚'
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前'
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前'
    const date = new Date(time * 1000)
    return `${date.getMonth() + 1}-${date.getDate()}`
}

const previewImage = (urls: string[], index: any) => {
    uni.previewImage({
        urls: urls.map(u => img(u)),
        current: Number(index)
    })
}

const showActionSheet = (item: any) => {
    uni.showActionSheet({
        itemList: ['删除'],
        success: async (res) => {
            if (res.tapIndex === 0) {
                uni.showModal({
                    title: '提示',
                    content: '确定要删除这条留言吗？',
                    success: async (modalRes) => {
                        if (modalRes.confirm) {
                            const delRes: any = await deleteGuestbook({ id: item.id })
                            if (delRes.code === 1) {
                                uni.showToast({ title: '删除成功', icon: 'success' })
                                loadMessages(true)
                            } else {
                                uni.showToast({ title: delRes.msg || '删除失败', icon: 'none' })
                            }
                        }
                    }
                })
            }
        }
    })
}
</script>

<style lang="scss" scoped>
.my-guestbook-page {
    min-height: 100vh;
    background: #f8f9fa;
}

.main-container {
    padding: 24rpx;
}

.message-scroll {
    height: calc(100vh - 100rpx);
}

.message-card {
    background: #fff;
    border-radius: 20rpx;
    padding: 30rpx;
    margin-bottom: 24rpx;
    box-shadow: 0 2rpx 10rpx rgba(0,0,0,0.02);
    
    .card-header {
        display: flex;
        align-items: center;
        margin-bottom: 20rpx;
        
        .status-tag {
            background: #e8f5e9;
            color: #00c853;
            font-size: 22rpx;
            padding: 4rpx 16rpx;
            border-radius: 20rpx;
            margin-right: 16rpx;
            
            &.pending {
                background: #fff3e0;
                color: #ff9800;
            }
        }
        
        .time {
            font-size: 24rpx;
            color: #999;
            flex: 1;
        }
    }
    
    .card-body {
        .message-content {
            font-size: 28rpx;
            color: #444;
            line-height: 1.6;
            margin-bottom: 20rpx;
            display: block;
        }
        
        .media-grid {
            margin-bottom: 20rpx;
            
            .grid-layout {
                display: grid;
                gap: 10rpx;
                
                &.layout-1 {
                    grid-template-columns: 1fr;
                    .media-item { height: 360rpx; border-radius: 12rpx; max-width: 70%; }
                }
                
                &.layout-2 {
                    grid-template-columns: repeat(2, 1fr);
                    .media-item { height: 240rpx; border-radius: 12rpx; }
                }
                
                &.layout-3 {
                    grid-template-columns: repeat(3, 1fr);
                    .media-item { height: 200rpx; border-radius: 12rpx; }
                }
                
                .media-item {
                    width: 100%;
                    background: #f5f5f5;
                }
            }
        }

        .reply-box {
            background: #f8f9fa;
            border-radius: 12rpx;
            padding: 20rpx;
            margin-top: 16rpx;

            .reply-header {
                display: flex;
                align-items: center;
                margin-bottom: 10rpx;

                .reply-label {
                    font-size: 24rpx;
                    color: #00c853;
                    font-weight: bold;
                    margin-left: 8rpx;
                }
            }

            .reply-content {
                font-size: 26rpx;
                color: #666;
                line-height: 1.5;
            }
        }
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    .empty-title {
        font-size: 32rpx;
        color: #333;
        font-weight: bold;
        margin: 20rpx 0 10rpx;
    }
    
    .empty-desc {
        font-size: 26rpx;
        color: #999;
    }
}

.loading-status {
    padding: 30rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;
    
    .no-more {
        font-size: 24rpx;
        color: #ccc;
    }
}
</style>
