<template>
    <view class="evaluates-page">
        <scroll-view 
            scroll-y 
            class="evaluate-scroll"
            @scrolltolower="loadMore"
        >
            <view class="evaluate-item" v-for="item in evaluateList" :key="item.id">
                <view class="item-header">
                    <view class="order-info">
                        <text class="order-no">订单 {{ item.order_no }}</text>
                        <text class="task-type">{{ getTaskTypeName(item.task_type) }}</text>
                    </view>
                    <text class="time">{{ item.create_time }}</text>
                </view>
                <view class="item-content">
                    <view class="runner-info">
                        <image class="avatar" :src="img(item.runner_avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                        <text class="name">{{ item.runner_name }}</text>
                    </view>
                    <view class="scores">
                        <view class="score-item">
                            <text class="label">综合</text>
                            <view class="stars">
                                <text class="star-icon active" v-for="i in item.score" :key="i">★</text>
                            </view>
                        </view>
                        <view class="score-item">
                            <text class="label">服务</text>
                            <text class="value">{{ item.service_score }}分</text>
                        </view>
                        <view class="score-item">
                            <text class="label">速度</text>
                            <text class="value">{{ item.speed_score }}分</text>
                        </view>
                    </view>
                </view>
                <view class="item-text" v-if="item.content">
                    <text>{{ item.content }}</text>
                </view>
                <view class="item-images" v-if="item.images && parseImages(item.images).length > 0">
                    <image 
                        v-for="(imgUrl, index) in parseImages(item.images)" 
                        :key="index" 
                        :src="img(imgUrl)" 
                        mode="aspectFill"
                        @click="previewImage(parseImages(item.images), index)"
                    ></image>
                </view>
            </view>

            <view class="empty" v-if="evaluateList.length === 0 && !loading">
                <u-icon name="order" size="120" color="#ccc"></u-icon>
                <text>暂无评价记录</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>

            <view class="no-more" v-if="!hasMore && evaluateList.length > 0">
                <text>没有更多了</text>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getMyEvaluates } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const evaluateList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '帮我买',
    'ERRAND': '跑腿服务',
    'QUEUE': '代排队',
    'PRINT': '代打印',
    'SEAT': '代占座',
    'TRASH': '扔垃圾',
    'CARRY': '帮搬运',
    'CLEAN': '代清洁',
    'GAME': '游戏陪玩',
    'HELP': '帮帮忙'
}

onMounted(() => {
    loadEvaluates()
})

const loadEvaluates = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const res: any = await getMyEvaluates({
            page: page.value,
            limit: limit
        })
        
        if (res.code === 1) {
            if (refresh) {
                evaluateList.value = res.data.list || res.data
            } else {
                evaluateList.value = [...evaluateList.value, ...(res.data.list || res.data)]
            }
            
            const list = res.data.list || res.data
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
    }
}

const loadMore = () => {
    loadEvaluates()
}

const getTaskTypeName = (type: string) => {
    return taskTypeMap[type] || type
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
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
</script>

<style lang="scss" scoped>
.evaluates-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.evaluate-scroll {
    height: 100vh;
    padding: 20rpx;
    box-sizing: border-box;
}

.evaluate-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20rpx;
    
    .order-info {
        .order-no {
            font-size: 28rpx;
            color: #333;
            margin-right: 16rpx;
        }
        
        .task-type {
            font-size: 24rpx;
            color: #333;
            background: #c0fe95;
            padding: 4rpx 12rpx;
            border-radius: 4rpx;
            font-weight: bold;
        }
    }
    
    .time {
        font-size: 24rpx;
        color: #999;
    }
}

.item-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20rpx;
}

.runner-info {
    display: flex;
    align-items: center;
    
    .avatar {
        width: 60rpx;
        height: 60rpx;
        border-radius: 50%;
        margin-right: 16rpx;
    }
    
    .name {
        font-size: 28rpx;
        color: #333;
    }
}

.scores {
    display: flex;
    gap: 20rpx;
    
    .score-item {
        display: flex;
        align-items: center;
        
        .label {
            font-size: 24rpx;
            color: #999;
            margin-right: 8rpx;
        }
        
        .stars {
            .star-icon {
                font-size: 24rpx;
                color: #ddd;
                
                &.active {
                    color: #ff9500;
                }
            }
        }
        
        .value {
            font-size: 24rpx;
            color: #333;
        }
    }
}

.item-text {
    margin-bottom: 20rpx;
    
    text {
        font-size: 28rpx;
        color: #666;
        line-height: 1.6;
    }
}

.item-images {
    display: flex;
    gap: 16rpx;
    flex-wrap: wrap;
    
    image {
        width: 160rpx;
        height: 160rpx;
        border-radius: 8rpx;
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
</style>
