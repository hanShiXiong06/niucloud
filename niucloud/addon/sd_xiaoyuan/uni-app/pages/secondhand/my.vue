<template>
    <view class="my-page">
        <view class="tabs">
            <view class="tab-item" :class="{ active: currentTab === 'all' }" @click="switchTab('all')">全部</view>
            <view class="tab-item" :class="{ active: currentTab === 'published' }" @click="switchTab('published')">在售</view>
            <view class="tab-item" :class="{ active: currentTab === 'sold' }" @click="switchTab('sold')">已售出</view>
            <view class="tab-item" :class="{ active: currentTab === 'offline' }" @click="switchTab('offline')">已下架</view>
        </view>

        <scroll-view scroll-y class="goods-list" @scrolltolower="loadMore">
            <view class="goods-item" v-for="item in goodsList" :key="item.id" @click="goDetail(item.id)">
                <image class="goods-image" :src="img(getFirstImage(item.images))" mode="aspectFill"></image>
                <view class="goods-info">
                    <text class="title">{{ item.title }}</text>
                    <view class="meta">
                        <text class="price">¥{{ item.price }}</text>
                        <text class="status" :class="getStatusClass(item.status)">{{ getStatusText(item.status) }}</text>
                    </view>
                    <view class="stats">
                        <text>浏览 {{ item.view_count || 0 }}</text>
                        <text v-if="item.want_count"> · 想要 {{ item.want_count }}</text>
                    </view>
                </view>
                <view class="actions">
                    <button class="action-btn" @click.stop="editGoods(item)">编辑</button>
                    <button class="action-btn" v-if="item.status === 1" @click.stop="offGoods(item)">下架</button>
                    <button class="action-btn" v-if="item.status === 1" @click.stop="soldGoods(item)">已售出</button>
                    <button class="action-btn" v-if="item.status === 0" @click.stop="onGoods(item)">上架</button>
                    <button class="action-btn danger" @click.stop="deleteGoods(item)">删除</button>
                </view>
            </view>

            <view class="empty" v-if="goodsList.length === 0 && !loading">
                <u-icon name="shopping-cart" size="120" color="#ccc"></u-icon>
                <text>暂无闲置商品</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && goodsList.length > 0">没有更多了</view>
        </scroll-view>

        <view class="publish-btn" @click="goPublish">
            <text>发布闲置</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMyPublishSecondhand, offSecondhand, soldSecondhand, delSecondhand, onSecondhand } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const currentTab = ref('all')
const goodsList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)

const statusMap: Record<number, string> = {
    0: '已下架',
    1: '在售',
    2: '已售出',
    3: '已删除'
}

onShow(() => loadGoods(true))

const switchTab = (tab: string) => {
    currentTab.value = tab
    loadGoods(true)
}

const loadGoods = async (refresh = false) => {
    if (loading.value) return
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    if (!hasMore.value) return

    loading.value = true
    const params: any = { page: page.value, limit: 10 }
    if (currentTab.value === 'published') params.status = 1
    else if (currentTab.value === 'sold') params.status = 2
    else if (currentTab.value === 'offline') params.status = 0

    const res: any = await getMyPublishSecondhand(params)
    if (res.code === 1) {
        const list = res.data?.list || []
        goodsList.value = refresh ? list : [...goodsList.value, ...list]
        hasMore.value = list.length >= 10
        if (hasMore.value) page.value++
    }
    loading.value = false
}

const loadMore = () => loadGoods()

const getFirstImage = (images: any) => {
    if (typeof images === 'string') {
        try {
            const parsed = JSON.parse(images)
            if (Array.isArray(parsed)) return parsed[0] || ''
            return images.split(',').filter((s: string) => s)[0] || ''
        } catch {
            return images.split(',').filter((s: string) => s)[0] || ''
        }
    }
    return images?.[0] || ''
}

const getStatusText = (status: number) => statusMap[status] || '未知'
const getStatusClass = (status: number) => {
    if (status === 0) return 'offline'
    if (status === 1) return 'published'
    if (status === 2) return 'sold'
    return 'deleted'
}

const goDetail = (id: number) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/secondhand/detail?id=${id}` })
}

const editGoods = (item: any) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/secondhand/publish?id=${item.id}` })
}

const offGoods = (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定下架该商品？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await offSecondhand({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '下架成功', icon: 'success' })
                    loadGoods(true)
                }
            }
        }
    })
}

const soldGoods = (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定标记为已售出？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await soldSecondhand({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '操作成功', icon: 'success' })
                    loadGoods(true)
                }
            }
        }
    })
}

const deleteGoods = (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定删除该商品？删除后不可恢复',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await delSecondhand({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '删除成功', icon: 'success' })
                    loadGoods(true)
                }
            }
        }
    })
}

const onGoods = (item: any) => {
    uni.showModal({
        title: '提示',
        content: '确定重新上架该商品？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await onSecondhand({ id: item.id })
                if (result.code === 1) {
                    uni.showToast({ title: '上架成功', icon: 'success' })
                    loadGoods(true)
                }
            }
        }
    })
}

const goPublish = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/secondhand/publish' })
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

.goods-list {
    flex: 1;
    padding: 20rpx;
    padding-bottom: 120rpx;
}

.goods-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 20rpx;
    margin-bottom: 20rpx;
    
    .goods-image {
        width: 100%;
        height: 300rpx;
        border-radius: 12rpx;
        margin-bottom: 16rpx;
    }
    
    .goods-info {
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
                
                &.offline { background: #f5f5f5; color: #999; }
                &.published { background: #f6ffed; color: #52c41a; }
                &.sold { background: #e6f7ff; color: #1890ff; }
                &.deleted { background: #fff1f0; color: #ff4d4f; }
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
    font-size: 26rpx;
    color: #999;
}
</style>
