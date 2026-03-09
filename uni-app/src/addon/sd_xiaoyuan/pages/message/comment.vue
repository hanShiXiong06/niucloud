<template>
    <view class="message-page">
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
                    v-for="item in messageList" 
                    :key="item.id"
                    @click="goToDetail(item)"
                >
                    <view class="message-avatar">
                        <view class="text-avatar">{{ (item.title || '评').charAt(0) }}</view>
                        <view class="unread-dot" v-if="!item.is_read"></view>
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
                    <u-icon name="edit-pen" size="120" color="#ccc"></u-icon>
                    <text>暂无评论消息</text>
                </view>

                <view class="loading-more" v-if="loading">
                    <u-loading-icon mode="circle" color="#1890ff"></u-loading-icon>
                </view>

                <view class="no-more" v-if="!hasMore && messageList.length > 0">
                    <text>没有更多了</text>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { getMessageList } from '../../api/xiaoyuan'

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
    if (refresh) { page.value = 1; hasMore.value = true }
    if (!hasMore.value) return
    loading.value = true
    try {
        const res: any = await getMessageList({ page: page.value, limit, type: 'COMMENT' })
        if (res.code === 1) {
            const list = res.data.list || []
            messageList.value = refresh ? list : [...messageList.value, ...list]
            if (list.length < limit) { hasMore.value = false } else { page.value++ }
        }
    } catch (e) { console.error(e) }
    finally { loading.value = false; isRefreshing.value = false }
}

const onRefresh = () => { isRefreshing.value = true; loadMessages(true) }
const loadMore = () => { loadMessages() }

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const d = new Date(timestamp * 1000)
    return `${d.getMonth() + 1}-${d.getDate()} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`
}

const goToDetail = (item: any) => {
    if (!item.link_id) return
    if (item.link_type === 'confession') {
        uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/confession/detail?id=${item.link_id}` })
    } else {
        uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/community/detail?id=${item.link_id}` })
    }
}
</script>

<style lang="scss" scoped>
.message-page { min-height: 100vh; background: #f5f5f5; }
.message-scroll { height: 100vh; }
.message-list-content { padding: 20rpx; }
.message-item {
    display: flex; padding: 24rpx; background: #fff; border-radius: 16rpx; margin-bottom: 20rpx;
    .message-avatar {
        position: relative; margin-right: 20rpx;
        .text-avatar {
            width: 80rpx; height: 80rpx; border-radius: 50%; background: linear-gradient(135deg, #e0c3fc, #8ec5fc);
            display: flex; align-items: center; justify-content: center; font-size: 32rpx; color: #fff; font-weight: bold;
        }
        .unread-dot {
            position: absolute; top: 0; right: 0; width: 16rpx; height: 16rpx; background: #ff4d4f; border-radius: 50%;
        }
    }
    .message-content {
        flex: 1;
        .message-top {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 12rpx;
            .message-title { font-size: 28rpx; font-weight: bold; color: #333; }
            .message-time { font-size: 24rpx; color: #999; }
        }
        .message-bottom {
            .message-text { font-size: 26rpx; color: #666; line-height: 1.5; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
        }
    }
}
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 100rpx 0; text { font-size: 28rpx; color: #999; margin-top: 20rpx; } }
.loading-more, .no-more { display: flex; justify-content: center; padding: 30rpx 0; text { font-size: 26rpx; color: #999; } }
</style>
