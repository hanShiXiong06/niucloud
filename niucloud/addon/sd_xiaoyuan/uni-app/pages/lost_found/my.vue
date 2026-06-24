<template>
    <view class="my-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <view class="tabs">
            <view class="tab-item" :class="{ active: currentTab === 'all' }" @click="switchTab('all')">全部</view>
            <view class="tab-item" :class="{ active: currentTab === 'active' }" @click="switchTab('active')">进行中</view>
            <view class="tab-item" :class="{ active: currentTab === 'resolved' }" @click="switchTab('resolved')">已找到</view>
            <view class="tab-item" :class="{ active: currentTab === 'closed' }" @click="switchTab('closed')">已关闭</view>
        </view>

        <view class="item-list">
            <view class="list-item" v-for="item in itemList" :key="item.id" @click="goDetail(item.id)">
                <view class="item-header">
                    <text class="type-tag" :class="item.type === 'LOST' ? 'lost' : 'found'">{{ item.type === 'LOST' ? '寻物' : '招领' }}</text>
                    <text class="status" :class="getStatusClass(item.status)">{{ getStatusText(item.status) }}</text>
                </view>
                <text class="title">{{ item.title }}</text>
                <view class="item-meta">
                    <text v-if="item.lost_address">地点：{{ item.lost_address }}</text>
                    <text>{{ (item.create_time) }}</text>
                </view>
                <view class="actions">
                    <button class="action-btn" @click.stop="editItem(item)">编辑</button>
                    <button class="action-btn" v-if="item.status === 1" @click.stop="closeItem(item)">关闭</button>
                    <button class="action-btn danger" @click.stop="deleteItem(item)">删除</button>
                </view>
            </view>

            <view class="empty" v-if="itemList.length === 0 && !loading">
                <u-icon name="search" size="120" color="#ccc"></u-icon>
                <text>暂无记录</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && itemList.length > 0">没有更多了</view>
        </view>

        <view class="publish-btn" @click="goPublish">
            <text>发布</text>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import { getMyPublishLostFound, closeLostFound, delLostFound } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_lost_found')

const currentTab = ref('all')
const itemList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

const statusMap: Record<number, string> = {
    0: '已关闭',
    1: '进行中',
    2: '已找到'
}

onShow(() => {
    loadConfig()
    loadItems(true)
})

const switchTab = (tab: string) => {
    currentTab.value = tab
    loadItems(true)
}

const loadItems = async (refresh = false) => {
    if (loading.value) return
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    if (!hasMore.value) return

    loading.value = true
    const params: any = { page: page.value, limit }
    if (currentTab.value === 'active') params.status = 1
    else if (currentTab.value === 'resolved') params.status = 2
    else if (currentTab.value === 'closed') params.status = 0

    const res: any = await getMyPublishLostFound(params)
    if (res.code === 1) {
        const list = res.data?.list || []
        itemList.value = refresh ? list : [...itemList.value, ...list]
        if (list.length < limit) {
            hasMore.value = false
        } else {
            page.value++
        }
    }
    loading.value = false
}

const loadMore = () => {
    if (!loading.value && hasMore.value) loadItems()
}

onReachBottom(() => {
    loadMore()
})

const getStatusText = (status: number) => statusMap[status] || '未知'
const getStatusClass = (status: number) => {
    if (status === 0) return 'closed'
    if (status === 1) return 'active'
    return 'resolved'
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const d = new Date(timestamp * 1000)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const goDetail = (id: number) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/lost_found/detail?id=${id}` })
}

const editItem = (item: any) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/lost_found/publish?id=${item.id}` })
}

const closeItem = (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定关闭该信息？关闭后将不再展示',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await closeLostFound({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '关闭成功', icon: 'success' })
                    loadItems(true)
                }
            }
        }
    })
}

const deleteItem = (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定删除？删除后不可恢复',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await delLostFound({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '删除成功', icon: 'success' })
                    loadItems(true)
                }
            }
        }
    })
}

const goPublish = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/lost_found/publish' })
}
</script>

<style lang="scss" scoped>
.my-page {
    min-height: 100vh;
    background: #f7f7f7;
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
                width: 40rpx;
                height: 6rpx;
                background: #c0fe95;
                border-radius: 3rpx;
            }
        }
    }
}

.item-list {
    flex: 1;
    padding: 20rpx;
    padding-bottom: 120rpx;
}

.list-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 20rpx;
    margin-bottom: 20rpx;
    
    .item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12rpx;
        
        .type-tag {
            font-size: 24rpx;
            padding: 4rpx 16rpx;
            border-radius: 4rpx;
            font-weight: bold;
            
            &.lost { background: #fff1f0; color: #ff4d4f; }
            &.found { background: #f6ffed; color: #52c41a; }
        }
        
        .status {
            font-size: 24rpx;
            padding: 4rpx 16rpx;
            border-radius: 4rpx;
            
            &.closed { background: #f5f5f5; color: #999; }
            &.active { background: #f6ffed; color: #52c41a; }
            &.resolved { background: #e6f7ff; color: #1890ff; }
        }
    }
    
    .title {
        display: block;
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 12rpx;
    }
    
    .item-meta {
        display: flex;
        justify-content: space-between;
        font-size: 24rpx;
        color: #999;
        margin-bottom: 8rpx;
    }
    
    .actions {
        display: flex;
        gap: 16rpx;
        margin-top: 16rpx;
        padding-top: 16rpx;
        border-top: 1rpx solid #f0f0f0;
        
        .action-btn {
            flex: 1;
            height: 64rpx;
            line-height: 64rpx;
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
    font-size: 26rpx;
    color: #999;
}
</style>
