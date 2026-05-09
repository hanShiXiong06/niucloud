<template>
    <view class="my-confession-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <view class="tabs">
            <view class="tab-item" :class="{ active: currentTab === 'all' }" @click="switchTab('all')">全部</view>
            <view class="tab-item" :class="{ active: currentTab === 'pending' }" @click="switchTab('pending')">待审核</view>
            <view class="tab-item" :class="{ active: currentTab === 'published' }" @click="switchTab('published')">已发布</view>
        </view>

        <scroll-view scroll-y class="confession-list" @scrolltolower="loadMore">
            <view class="confession-item" v-for="item in confessionList" :key="item.id" @click="goDetail(item)">
                <view class="school-row" v-if="item.school_name">
                    <text class="school-tag">{{ item.school_name }}</text>
                </view>
                <view class="item-header">
                    <text class="anonymous-tag" v-if="item.is_anonymous">匿名</text>
                    <text class="target" v-if="item.target_name">表白对象：{{ item.target_name }}</text>
                    <text class="status" :class="getStatusClass(item.status)">{{ getStatusText(item.status) }}</text>
                </view>
                <text class="content">{{ item.content }}</text>
                <view class="item-images" v-if="getImages(item.images).length > 0">
                    <image v-for="(imgUrl, idx) in getImages(item.images).slice(0, 3)" :key="idx" :src="img(imgUrl)" mode="aspectFill"></image>
                </view>
                <view class="item-meta">
                    <view class="stats">
                        <view class="stat-item"><u-icon name="heart-fill" size="14" color="#ff6b81"></u-icon><text>{{ item.like_count || 0 }}</text></view>
                        <view class="stat-item"><u-icon name="chat" size="14" color="#999"></u-icon><text>{{ item.comment_count || 0 }}</text></view>
                        <view class="stat-item"><u-icon name="eye" size="14" color="#999"></u-icon><text>{{ item.view_count || 0 }}</text></view>
                    </view>
                    <text class="time">{{ (item.create_time) }}</text>
                </view>
                <view class="item-actions">
                    <button class="action-btn danger" @click.stop="deleteConfession(item)">删除</button>
                </view>
            </view>

            <view class="empty" v-if="confessionList.length === 0 && !loading">
                <view class="empty-icon">
                    <u-icon name="heart" size="120" color="#ccc"></u-icon>
                </view>
                <text class="empty-text">暂无表白</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && confessionList.length > 0">没有更多了</view>
        </scroll-view>

        <view class="publish-btn" @click="goPublish">
            <text>发表白</text>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMyConfession, deleteConfession as deleteApi } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_confession')

const currentTab = ref('all')
const confessionList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)

const statusMap: Record<number, string> = {
    0: '待审核',
    1: '已发布',
    2: '已下架'
}

onShow(() => {
    loadConfig()
    loadConfessions(true)
})

const switchTab = (tab: string) => {
    currentTab.value = tab
    loadConfessions(true)
}

const loadConfessions = async (refresh = false) => {
    if (loading.value) return
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    if (!hasMore.value) return

    loading.value = true
    try {
        const params: any = { page: page.value, limit: 10 }
        if (currentTab.value === 'pending') params.status = 0
        else if (currentTab.value === 'published') params.status = 1

        const res: any = await getMyConfession(params)
        if (res.code === 1) {
            const list = res.data.list || []
            confessionList.value = refresh ? list : [...confessionList.value, ...list]
            hasMore.value = list.length >= 10
            if (hasMore.value) page.value++
        }
    } finally {
        loading.value = false
    }
}

const loadMore = () => loadConfessions()

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

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getMonth() + 1}-${date.getDate()}`
}

const goDetail = (item: any) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/confession/detail?id=${item.id}` })
}

const deleteConfession = async (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定删除该表白？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await deleteApi({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '删除成功', icon: 'success' })
                    loadConfessions(true)
                }
            }
        }
    })
}

const goPublish = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/confession/publish' })
}
</script>

<style lang="scss" scoped>
.my-confession-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #fff5f5, #f5f5f5 100rpx);
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

.confession-list {
    flex: 1;
    padding: 20rpx;
    padding-bottom: 120rpx;
}

.confession-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    
    .school-row {
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
    
    .item-header {
        display: flex;
        align-items: center;
        margin-bottom: 16rpx;
        
        .anonymous-tag {
            background: #ff6b6b;
            color: #fff;
            font-size: 22rpx;
            padding: 4rpx 12rpx;
            border-radius: 4rpx;
            margin-right: 12rpx;
        }
        
        .target {
            flex: 1;
            font-size: 26rpx;
            color: #666;
        }
        
        .status {
            font-size: 24rpx;
            padding: 4rpx 16rpx;
            border-radius: 4rpx;
            
            &.pending { background: #fff7e6; color: #fa8c16; }
            &.published { background: #f6ffed; color: #52c41a; }
            &.offline { background: #f5f5f5; color: #999; }
        }
    }
    
    .content {
        font-size: 28rpx;
        color: #333;
        line-height: 1.6;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-bottom: 16rpx;
    }
    
    .item-images {
        display: flex;
        gap: 12rpx;
        margin-bottom: 16rpx;
        
        image {
            width: 180rpx;
            height: 180rpx;
            border-radius: 8rpx;
        }
    }
    
    .item-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        
        .stats {
            display: flex;
            align-items: center;
            gap: 24rpx;
            
            .stat-item {
                display: flex;
                align-items: center;
                gap: 6rpx;
                font-size: 24rpx;
                color: #999;
            }
        }
        
        .time {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .item-actions {
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
            background: #fff1f0;
            color: #ff4d4f;
            border: none;
            border-radius: 8rpx;
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
