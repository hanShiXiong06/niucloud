<template>
    <view class="my-posts-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <view class="tabs">
            <view class="tab-item" :class="{ active: currentTab === 'all' }" @click="switchTab('all')">全部</view>
            <view class="tab-item" :class="{ active: currentTab === 'pending' }" @click="switchTab('pending')">待审核</view>
            <view class="tab-item" :class="{ active: currentTab === 'published' }" @click="switchTab('published')">已发布</view>
        </view>

        <view class="post-list">
            <view class="post-item" v-for="item in postList" :key="item.id" @click="goDetail(item)">
                <view class="post-header" v-if="item.school_name">
                    <text class="school-tag">{{ item.school_name }}</text>
                </view>
                <view class="post-content">
                    <text class="title" v-if="item.title">{{ item.title }}</text>
                    <text class="content">{{ item.content }}</text>
                </view>
                <view class="post-images" v-if="getImages(item.images).length > 0">
                    <image v-for="(imgUrl, idx) in getImages(item.images).slice(0, 3)" :key="idx" :src="img(imgUrl)" mode="aspectFill"></image>
                </view>
                <view class="post-meta">
                    <view class="stats">
                        <text>浏览 {{ item.view_count || 0 }}</text>
                        <text>点赞 {{ item.like_count || 0 }}</text>
                        <text>评论 {{ item.comment_count || 0 }}</text>
                    </view>
                    <text class="status" :class="getStatusClass(item.status)">{{ getStatusText(item.status) }}</text>
                </view>
                <view class="post-actions">
                    <button class="action-btn danger" @click.stop="deletePost(item)">删除</button>
                </view>
            </view>

            <view class="empty" v-if="postList.length === 0 && !loading">
                <view class="empty-icon">
                    <u-icon name="chat" size="120" color="#ccc"></u-icon>
                </view>
                <text class="empty-text">暂无帖子</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && postList.length > 0">没有更多了</view>
        </view>

        <view class="publish-btn" @click="goPublish">
            <text>发布帖子</text>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import { getMyCommunity, deleteCommunity } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_community')

const currentTab = ref('all')
const postList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

const statusMap: Record<number, string> = {
    0: '待审核',
    1: '已发布',
    2: '已下架'
}

onShow(() => {
    loadConfig()
    loadPosts(true)
})

const switchTab = (tab: string) => {
    currentTab.value = tab
    loadPosts(true)
}

const loadPosts = async (refresh = false) => {
    if (loading.value) return
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    if (!hasMore.value) return

    loading.value = true
    try {
        const params: any = { page: page.value, limit }
        if (currentTab.value === 'pending') params.status = 0
        else if (currentTab.value === 'published') params.status = 1

        const res: any = await getMyCommunity(params)
        if (res.code === 1) {
            const list = res.data.list || []
            postList.value = refresh ? list : [...postList.value, ...list]
            if (list.length < limit) {
                hasMore.value = false
            } else {
                page.value++
            }
        }
    } finally {
        loading.value = false
    }
}

const loadMore = () => {
    if (!loading.value && hasMore.value) loadPosts()
}

onReachBottom(() => {
    loadMore()
})

const getImages = (images: any) => {
    if (typeof images === 'string') {
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

const getStatusText = (status: number) => statusMap[status] || '未知'
const getStatusClass = (status: number) => {
    if (status === 0) return 'pending'
    if (status === 1) return 'published'
    return 'offline'
}

const goDetail = (item: any) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/community/detail?id=${item.id}` })
}

const deletePost = async (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定删除该帖子？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await deleteCommunity({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '删除成功', icon: 'success' })
                    loadPosts(true)
                }
            }
        }
    })
}

const goPublish = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/community/publish' })
}
</script>

<style lang="scss" scoped>
.my-posts-page {
    min-height: 100vh;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
}

.tabs {
    display: flex;
    background: #fff;
    padding: 20rpx;
    
    .tab-item {
        flex: 1;
        text-align: center;
        font-size: 28rpx;
        color: #666;
        padding: 16rpx 0;
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

.post-list {
    flex: 1;
    padding: 20rpx;
    padding-bottom: 120rpx;width: auto;
}

.post-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;width: auto;
    margin-bottom: 20rpx;
    
    .post-header {
        margin-bottom: 12rpx;
        
        .school-tag {
            display: inline-block;
            font-size: 22rpx;
            color: #1890ff;
            background: #e6f7ff;
            padding: 4rpx 16rpx;
            border-radius: 6rpx;
        }
    }
    
    .post-content {
        margin-bottom: 16rpx;
        
        .title {
            display: block;
            font-size: 30rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 8rpx;
        }
        
        .content {
            font-size: 28rpx;
            color: #666;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    }
    
    .post-images {
        display: flex;
        gap: 12rpx;
        margin-bottom: 16rpx;
        flex-wrap: wrap;

        image {
            flex: 0 0 calc((100% - 24rpx) / 3);
            width: calc((100% - 24rpx) / 3);
            height: 200rpx;
            border-radius: 8rpx;
            object-fit: cover;
        }
    }
    
    .post-meta {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 12rpx;
        
        .stats {
            font-size: 24rpx;
            color: #999;
            flex: 1;
            
            text { margin-right: 20rpx; }
        }
        
        .status {
            font-size: 22rpx;
            padding: 6rpx 12rpx;
            border-radius: 6rpx;
            white-space: nowrap;
            flex-shrink: 0;
            margin-left: 12rpx;
            
            &.pending { background: #fff7e6; color: #fa8c16; }
            &.published { background: #f6ffed; color: #52c41a; }
            &.offline { background: #f5f5f5; color: #999; }
        }
    }
    
    .post-actions {
        margin-top: 16rpx;
        padding-top: 16rpx;
        border-top: 1rpx solid #f0f0f0;
        display: flex;
        justify-content: flex-end;
        
        .action-btn {
            padding: 0 32rpx;
            height: 56rpx;
            line-height: 56rpx;
            font-size: 26rpx;
            background: #f5f5f5;
            color: #666;
            border: none;
            border-radius: 8rpx;
            
            &.danger {
                background: #fff1f0;
                color: #ff4d4f;
            }
        }
    }
}

.publish-btn {
    position: fixed;
    bottom: 30rpx;
    left: 50%;
    transform: translateX(-50%);
    padding: 10rpx 80rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border-radius: 16rpx;
    font-size: 30rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 20rpx rgba(170, 246, 155, 0.5);
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
        font-size: 26rpx;
        color: #999;
    }
}
</style>
