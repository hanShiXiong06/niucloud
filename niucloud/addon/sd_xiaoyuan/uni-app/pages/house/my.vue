<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="my-house-page" v-if="isFeatureEnabled">
        <view class="tabs">
            <view class="tab-item" :class="{ active: currentTab === 'all' }" @click="switchTab('all')">全部</view>
            <view class="tab-item" :class="{ active: currentTab === 'pending' }" @click="switchTab('pending')">待审核</view>
            <view class="tab-item" :class="{ active: currentTab === 'published' }" @click="switchTab('published')">已发布</view>
            <view class="tab-item" :class="{ active: currentTab === 'offline' }" @click="switchTab('offline')">已下架</view>
        </view>

        <view class="house-list">
            <view class="house-item" v-for="item in houseList" :key="item.id">
                <image class="house-image" :src="img(item.cover_image || getFirstImage(item.images))" mode="aspectFill"></image>
                <view class="house-info">
                    <text class="title">{{ item.title }}</text>
                    <view class="meta">
                        <text class="price">¥{{ item.price }}/月</text>
                        <text class="status" :class="getStatusClass(item.status)">{{ getStatusText(item.status) }}</text>
                    </view>
                    <view class="stats">
                        <text>浏览 {{ item.view_count || 0 }}</text>
                    </view>
                </view>
                <view class="actions">
                    <button class="action-btn" @click="editHouse(item)">编辑</button>
                    <button class="action-btn" v-if="item.status === 1" @click="offlineHouse(item)">下架</button>
                    <button class="action-btn danger" @click="deleteHouse(item)">删除</button>
                </view>
            </view>

            <view class="empty" v-if="houseList.length === 0 && !loading">
                <view class="empty-icon">
                    <u-icon name="home" size="120" color="#ccc"></u-icon>
                </view>
                <text class="empty-text">暂无房源</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && houseList.length > 0">没有更多了</view>
        </view>

        <view class="publish-btn" @click="goPublish">
            <text>发布房源</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import { getMyHouse, offlineHouse as offlineApi, deleteHouse as deleteApi } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_house')

const currentTab = ref('all')
const houseList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

const statusMap: Record<number, string> = {
    0: '待审核',
    1: '已发布',
    2: '已下架',
    3: '已出租'
}

onShow(() => { loadConfig(); loadHouses(true) })

const switchTab = (tab: string) => {
    currentTab.value = tab
    loadHouses(true)
}

const loadHouses = async (refresh = false) => {
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
        else if (currentTab.value === 'offline') params.status = 2

        const res: any = await getMyHouse(params)
        if (res.code === 1) {
            const list = res.data.list || []
            houseList.value = refresh ? list : [...houseList.value, ...list]
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
    if (!loading.value && hasMore.value) loadHouses()
}

onReachBottom(() => {
    loadMore()
})

const getFirstImage = (images: any) => {
    if (typeof images === 'string') {
        try {
            const arr = JSON.parse(images)
            if (Array.isArray(arr)) return arr[0] || '/static/images/default-house.png'
            const parts = images.split(',').filter((s: string) => s)
            return parts[0] || '/static/images/default-house.png'
        } catch {
            const parts = images.split(',').filter((s: string) => s)
            return parts[0] || '/static/images/default-house.png'
        }
    }
    return images?.[0] || '/static/images/default-house.png'
}

const getStatusText = (status: number) => statusMap[status] || '未知'
const getStatusClass = (status: number) => {
    if (status === 0) return 'pending'
    if (status === 1) return 'published'
    return 'offline'
}

const editHouse = (item: any) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/house/publish?id=${item.id}` })
}

const offlineHouse = async (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定下架该房源？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await offlineApi({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '下架成功', icon: 'success' })
                    loadHouses(true)
                }
            }
        }
    })
}

const deleteHouse = async (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定删除该房源？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await deleteApi({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '删除成功', icon: 'success' })
                    loadHouses(true)
                }
            }
        }
    })
}

const goPublish = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/house/publish' })
}
</script>

<style lang="scss" scoped>
.my-house-page {
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

.house-list {
    flex: 1;
    padding: 20rpx;
    padding-bottom: 120rpx;
}

.house-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 20rpx;
    margin-bottom: 20rpx;
    
    .house-image {
        width: 100%;
        height: 300rpx;
        border-radius: 12rpx;
        margin-bottom: 16rpx;
    }
    
    .house-info {
        .title {
            display: block;
            font-size: 30rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 12rpx;
        }
        
        .meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12rpx;
            
            .price {
                font-size: 28rpx;
                color: #ff6b00;
                font-weight: bold;
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
        
        .stats {
            font-size: 24rpx;
            color: #999;
        }
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
    display: flex;
    justify-content: center;
    
    text {
        font-size: 26rpx;
        color: #999;
    }
}
</style>
