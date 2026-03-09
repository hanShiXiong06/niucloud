<template>
    <view class="appeals-page">
        <!-- 申诉列表 -->
        <scroll-view 
            scroll-y 
            class="appeal-scroll"
            @scrolltolower="loadMore"
        >
            <view class="appeal-item" v-for="item in appealList" :key="item.id" @click="viewDetail(item)">
                <view class="appeal-header">
                    <text class="order-no">订单 {{ item.order_no }}</text>
                    <text class="status" :class="'status-' + item.status">{{ getStatusText(item.status) }}</text>
                </view>
                <view class="appeal-content">
                    <text class="type">申诉类型：{{ getTypeName(item.appeal_type) }}</text>
                    <text class="reason">{{ item.reason }}</text>
                </view>
                <view class="appeal-footer">
                    <text class="time">{{ formatTime(item.create_time) }}</text>
                </view>
            </view>

            <view class="empty" v-if="appealList.length === 0 && !loading">
                <u-icon name="order" size="120" color="#ccc"></u-icon>
                <text>暂无申诉记录</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>

            <view class="no-more" v-if="!hasMore && appealList.length > 0">
                <text>没有更多了</text>
            </view>
        </scroll-view>

        <!-- 发起申诉按钮 -->
        <button class="add-btn" @click="goToAdd">发起申诉</button>

        <!-- 申诉详情弹窗 -->
        <view class="detail-popup" v-if="showDetail" @click="showDetail = false">
            <view class="popup-content" @click.stop>
                <view class="popup-header">
                    <text class="title">申诉详情</text>
                    <text class="close" @click="showDetail = false">×</text>
                </view>
                <view class="popup-body" v-if="currentAppeal">
                    <view class="detail-item">
                        <text class="label">订单号</text>
                        <text class="value">{{ currentAppeal.order_no }}</text>
                    </view>
                    <view class="detail-item">
                        <text class="label">申诉类型</text>
                        <text class="value">{{ getTypeName(currentAppeal.appeal_type) }}</text>
                    </view>
                    <view class="detail-item">
                        <text class="label">申诉原因</text>
                        <text class="value">{{ currentAppeal.reason }}</text>
                    </view>
                    <view class="detail-item">
                        <text class="label">状态</text>
                        <text class="value" :class="'status-' + currentAppeal.status">{{ getStatusText(currentAppeal.status) }}</text>
                    </view>
                    <view class="detail-item" v-if="currentAppeal.images">
                        <text class="label">图片凭证</text>
                        <view class="images">
                            <image 
                                v-for="(imgUrl, index) in parseImages(currentAppeal.images)" 
                                :key="index" 
                                :src="img(imgUrl)" 
                                mode="aspectFill"
                                @click="previewImage(parseImages(currentAppeal.images), index)"
                            ></image>
                        </view>
                    </view>
                    <view class="detail-item" v-if="currentAppeal.handle_result">
                        <text class="label">处理结果</text>
                        <text class="value">{{ currentAppeal.handle_result }}</text>
                    </view>
                    <view class="detail-item">
                        <text class="label">申诉时间</text>
                        <text class="value">{{ formatTime(currentAppeal.create_time) }}</text>
                    </view>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { getAppealList } from '../../api/runner'
import { img } from '@/utils/common'

const appealList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const showDetail = ref(false)
const currentAppeal = ref<any>(null)

const statusMap: Record<number, string> = {
    0: '待处理',
    1: '已通过',
    2: '已拒绝'
}

const typeMap: Record<string, string> = {
    'ORDER_ISSUE': '订单问题',
    'FEE_ISSUE': '费用问题',
    'USER_ISSUE': '用户问题',
    'OTHER': '其他'
}

onMounted(() => {
    loadAppeals()
})

const loadAppeals = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const res: any = await getAppealList({
            page: page.value,
            limit: limit
        })
        
        if (res.code === 1) {
            if (refresh) {
                appealList.value = res.data.list
            } else {
                appealList.value = [...appealList.value, ...res.data.list]
            }
            
            if (res.data.list.length < limit) {
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

const loadMore = () => {
    loadAppeals()
}

const getStatusText = (status: number) => statusMap[status] || '未知'
const getTypeName = (type: string) => typeMap[type] || type

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

const parseImages = (images: any) => {
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

const previewImage = (images: string[], index: any) => {
    uni.previewImage({
        urls: images.map((u: string) => img(u)),
        current: Number(index)
    })
}

const viewDetail = (item: any) => {
    currentAppeal.value = item
    showDetail.value = true
}

const goToAdd = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/runner/appeal-add'
    })
}
</script>

<style lang="scss" scoped>
.appeals-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
    padding-bottom: 150rpx;
}

.appeal-scroll {
    height: calc(100vh - 180rpx);
}

.appeal-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.appeal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16rpx;
    
    .order-no {
        font-size: 28rpx;
        color: #333;
        font-weight: bold;
    }
    
    .status {
        font-size: 26rpx;
        
        &.status-0 { color: #ff9500; }
        &.status-1 { color: #52c41a; }
        &.status-2 { color: #ff4d4f; }
    }
}

.appeal-content {
    .type {
        display: block;
        font-size: 26rpx;
        color: #52c41a;
        margin-bottom: 8rpx;
    }
    
    .reason {
        font-size: 28rpx;
        color: #666;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
}

.appeal-footer {
    margin-top: 16rpx;
    
    .time {
        font-size: 24rpx;
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

.loading-more, .no-more {
    padding: 24rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;
    
    text {
        font-size: 24rpx;
        color: #999;
    }
}

.add-btn {
    position: fixed;
    bottom: 30rpx;
    left: 30rpx;
    right: 30rpx;
    height: 88rpx;
    line-height: 88rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 44rpx;
    font-size: 28rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
}

.detail-popup {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
}

.popup-content {
    width: 90%;
    max-height: 80vh;
    background: #fff;
    border-radius: 16rpx;
    overflow: hidden;
}

.popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24rpx;
    border-bottom: 1rpx solid #f0f0f0;
    
    .title {
        font-size: 28rpx;
        font-weight: bold;
    }
    
    .close {
        font-size: 40rpx;
        color: #999;
    }
}

.popup-body {
    padding: 24rpx;
    max-height: 60vh;
    overflow-y: auto;
}

.detail-item {
    margin-bottom: 20rpx;
    
    .label {
        display: block;
        font-size: 26rpx;
        color: #999;
        margin-bottom: 8rpx;
    }
    
    .value {
        font-size: 28rpx;
        color: #333;
        
        &.status-0 { color: #ff9500; }
        &.status-1 { color: #52c41a; }
        &.status-2 { color: #ff4d4f; }
    }
    
    .images {
        display: flex;
        gap: 16rpx;
        flex-wrap: wrap;
        
        image {
            width: 150rpx;
            height: 150rpx;
            border-radius: 8rpx;
        }
    }
}
</style>
