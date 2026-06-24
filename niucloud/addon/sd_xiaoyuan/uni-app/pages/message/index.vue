<template>
    <view class="message-page">
        <!-- Custom Header -->
        <view class="custom-header">
            <view class="header-bg"></view>
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="nav-bar" :style="{ height: navBarHeight + 'px', paddingRight: menuButtonRight + 'px' }">
                <view class="page-title">消息中心</view>
                <!-- <view class="nav-actions" style="position:reactive;z-index:21">
                    <view class="action-btn" @click="markAllRead">
                        <u-icon name="checkmark-circle" size="20" color="#333"></u-icon>
                    </view>
                    <view class="action-btn" @click="clearAll">
                        <u-icon name="trash" size="20" color="#333"></u-icon>
                    </view>
                </view> -->
            </view>
        </view>

        <!-- 消息列表 -->
        <view class="message-list-wrap" :style="{ paddingTop: (statusBarHeight + navBarHeight + 20) + 'px' }" style="position:reactive;z-index:2">
            <view class="message-list-content">
                <view 
                    class="message-item" 
                    v-for="(item, index) in messageList" 
                    :key="item.id"
                    @click="goToDetail(item)"
                    :class="{ unread: !item.is_read }"
                    :style="{ animationDelay: index * 0.05 + 's' }"
                >
                    <view class="message-avatar">
                        <view class="text-avatar">{{ item.title.charAt(0) }}</view>
                        <view class="unread-dot" v-if="!item.is_read"></view>
                    </view>
                    <view class="message-content">
                        <view class="message-top">
                            <text class="message-title">{{ item.title }}</text>
                            <text class="message-time">{{ (item.create_time) }}</text>
                        </view>
                        <view class="message-bottom">
                            <text class="message-text">{{ item.content }}</text>
                        </view>
                    </view>
                </view>

                <view class="empty-state" v-if="messageList.length === 0 && !loading">
                    <u-icon name="chat" size="120" color="#ccc"></u-icon>
                    <text>暂无消息</text>
                </view>

                <view class="loading-more" v-if="loading">
                    <u-loading-icon mode="circle" color="#1890ff"></u-loading-icon>
                    <text class="loading-text">加载中...</text>
                </view>

                <view class="no-more" v-if="!hasMore && messageList.length > 0">
                    <text>没有更多了</text>
                </view>
                
                <view style="height: 180rpx;"></view>
            </view>
        </view>

        <tabbar addon="sd_xiaoyuan" />
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import { getMessageList, readMessage, readAllMessages, clearMessages } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import tabbar from '@/components/tabbar/tabbar.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'

const { config, loadConfig } = useFeatureCheck()
const messageList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 15
const totalUnread = computed(() => {
    return messageList.value.reduce((sum, item) => sum + (item.unread || 0), 0)
})

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const menuButtonRight = ref(0)

onMounted(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    menuButtonRight.value = sysInfo.windowWidth - menuButton.left
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    loadConfig()
    loadMessages()
    uni.hideTabBar()
})

onShow(() => {
    loadConfig()
    uni.hideTabBar()
})

onReachBottom(() => {
    loadMore()
})

const navigateTo = (url: string) => {
    uni.navigateTo({ url })
}

const reLaunch = (url: string) => {
    uni.reLaunch({ url })
}

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
            limit: limit
        })
        
        if (res.code === 1 && res.data && res.data.list) {
            if (refresh) {
                messageList.value = res.data.list || []
            } else {
                messageList.value = [...messageList.value, ...(res.data.list || [])]
            }
            
            if (!res.data.list || res.data.list.length < limit) {
                hasMore.value = false
            } else {
                page.value++
            }
        } else {
            if (refresh) {
                messageList.value = []
            }
            hasMore.value = false
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



const goToDetail = (item: any) => {
    // 标记已读
    if (!item.is_read) {
        readMessage({ message_id: item.id })
        item.is_read = 1
    }
    
    // 没有link_id的消息（如后台发布的通知）不跳转
    if (!item.link_id) {
        return
    }
    
    // 根据link_type跳转到对应页面
    if (item.link_type === 'order_detail') {
        uni.navigateTo({
            url: `/addon/sd_xiaoyuan/pages/order/detail?id=${item.link_id}`
        })
    } else if (item.link_type === 'evaluate_detail') {
        uni.navigateTo({
            url: `/addon/sd_xiaoyuan/pages/order/evaluate?id=${item.link_id}`
        })
    } else if (item.type === 'ORDER' || item.type === 'order') {
        uni.navigateTo({
            url: `/addon/sd_xiaoyuan/pages/order/detail?id=${item.link_id}`
        })
    } else if (item.type === 'COMMUNITY' || item.type === 'community') {
        uni.navigateTo({
            url: `/addon/sd_xiaoyuan/pages/community/detail?id=${item.link_id}`
        })
    }
}

const getMessageIcon = (type: string) => {
    switch (type?.toLowerCase()) {
        case 'order': return 'order'
        case 'community': return 'chat'
        case 'system': return 'bell'
        default: return 'email'
    }
}

const markAllRead = async () => {
    try {
        const res: any = await readAllMessages()
        if (res.code === 1) {
            messageList.value.forEach((item: any) => { item.is_read = 1 })
            uni.showToast({ title: '已全部标为已读', icon: 'none' })
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const clearAll = async () => {
    uni.showModal({
        title: '提示',
        content: '确定要清空所有消息吗',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const result: any = await clearMessages()
                    if (result.code === 1) {
                        messageList.value = []
                        uni.showToast({ title: '已清空', icon: 'none' })
                    } else {
                        uni.showToast({ title: result.msg || '清空失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.showToast({ title: '网络错误', icon: 'none' })
                }
            }
        }
    })
}

</script>

<style lang="scss" scoped>
.message-page {
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
    z-index: 1;
    padding-bottom: 20rpx;
    
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
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20rpx 30rpx;
        z-index: 101;
        
        .page-title {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            font-size: 34rpx;
            font-weight: bold;
            color: #333;
        }
        
        .nav-actions {
            position: absolute;
            right: 30rpx;
            display: flex;
            align-items: center;
            gap: 16rpx;
            z-index: 102;
            
            .action-btn {
                width: 60rpx;
                height: 60rpx;
                background: rgba(255, 255, 255, 0.5);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                
                &:active {
                    background: rgba(255, 255, 255, 0.8);
                }
            }
        }
    }
}

.message-list-wrap {
    position: relative;
    z-index: 1;
}

.message-list-content {
    padding: 0 30rpx;position: relative;z-index: 2;
}

.message-avatar {
    position: relative;
    width: 100rpx;
    height: 100rpx;
    margin-right: 24rpx;
    flex-shrink: 0;
    
    image {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: #f0f0f0;
    }

    .text-avatar {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: linear-gradient(135deg, #c0fe95, #88f78d);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
        text-align: center;
    }

    .unread-dot {
        position: absolute;
        top: 4rpx;
        right: 4rpx;
        width: 16rpx;
        height: 16rpx;
        background: #ff4d4f;
        border-radius: 50%;
        border: 2rpx solid #fff;
    }
}

.message-item {
    display: flex;
    align-items: center;
    background: #fff;
    padding: 30rpx;
    border-radius: 24rpx;
    margin-bottom: 24rpx;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);
    animation: slideUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    opacity: 0;
    border: 1rpx solid transparent;
    transition: all 0.2s;
    
    &.unread {
        background: #fff;
        border-color: rgba(192, 254, 149, 0.3);
        box-shadow: 0 4rpx 20rpx rgba(192, 254, 149, 0.1);
    }
    
    &:active {
        transform: scale(0.98);
        background: #fafafa;
    }
}

.message-content {
    flex: 1;
    overflow: hidden;
}

.message-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8rpx;
}

.message-title {
    font-size: 30rpx;
    color: #1e293b;
    font-weight: 600;
}

.message-time {
    font-size: 22rpx;
    color: #94a3b8;
}

.message-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.message-text {
    font-size: 26rpx;
    color: #64748b;
    flex: 1;
    // overflow: hidden;
    // text-overflow: ellipsis;
    // white-space: nowrap;
}

.empty-state {
    padding-top: 100rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    
    text {
        font-size: 28rpx;
        color: #94a3b8;
        margin-top: 20rpx;
    }
}

.loading-more, .no-more {
    padding: 24rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12rpx;
    
    text {
        font-size: 24rpx;
        color: #94a3b8;
    }
    
    .loading-text {
        font-size: 24rpx;
        color: #94a3b8;
    }
}

@keyframes slideUp {
    from {
        transform: translateY(20rpx);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

</style>
