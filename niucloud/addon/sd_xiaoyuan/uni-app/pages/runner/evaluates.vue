<template>
    <view class="evaluates-page">
        <!-- 评分统计 -->
        <view class="stat-section">
            <view class="score-display">
                <text class="score">{{ avgScore }}</text>
                <view class="stars">
                    <text 
                        class="star-icon" 
                        :class="{ active: i <= Math.round(avgScore) }"
                        v-for="i in 5" 
                        :key="i"
                    >★</text>
                </view>
                <text class="count">共{{ totalCount }}条评价</text>
            </view>
        </view>

        <!-- 评价列表 -->
        <view class="list-section">
            <scroll-view 
                scroll-y 
                class="evaluate-scroll"
                @scrolltolower="loadMore"
            >
                <view class="evaluate-item" v-for="item in evaluateList" :key="item.id">
                    <view class="item-header">
                        <view class="user-info">
                            <image class="avatar" :src="img(item.member_headimg || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                            <text class="name">{{ item.is_anonymous ? '匿名用户' : (item.member_nickname || '用户') }}</text>
                            <view class="stars">
                                <text 
                                    class="star-icon active" 
                                    v-for="i in item.score" 
                                    :key="i"
                                >★</text>
                            </view>
                        </view>
                        <text class="time">{{ formatTime(item.create_time) }}</text>
                    </view>
                    <view class="item-content" v-if="item.content">
                        <text>{{ item.content }}</text>
                    </view>
                    <view class="item-images" v-if="item.images && item.images.length > 0">
                        <image 
                            v-for="(imgUrl, index) in parseImages(item.images)" 
                            :key="index" 
                            :src="img(imgUrl)" 
                            mode="aspectFill"
                            @click="previewImage(parseImages(item.images), index)"
                        ></image>
                    </view>
                    <view class="item-scores">
                        <text>服务：{{ item.service_score }}分</text>
                        <text>速度：{{ item.speed_score }}分</text>
                    </view>
                </view>

                <view class="empty" v-if="evaluateList.length === 0 && !loading">
                    <u-icon name="star" size="120" color="#ccc"></u-icon>
                    <text>暂无评价</text>
                </view>

                <view class="loading-more" v-if="loading">
                    <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
                </view>

                <view class="no-more" v-if="!hasMore && evaluateList.length > 0">
                    <text>没有更多了</text>
                </view>
            </scroll-view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { getRunnerEvaluates } from '../../api/runner'
import { img } from '@/utils/common'

const avgScore = ref(5)
const totalCount = ref(0)
const evaluateList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

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
        const res: any = await getRunnerEvaluates({
            page: page.value,
            limit: limit
        })
        
        if (res.code === 1) {
            if (refresh) {
                evaluateList.value = res.data.list
            } else {
                evaluateList.value = [...evaluateList.value, ...res.data.list]
            }
            
            avgScore.value = res.data.avg_score || 5
            totalCount.value = res.data.count || 0
            
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
    loadEvaluates()
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
    display: flex;
    flex-direction: column;
}

.stat-section {
    background: linear-gradient(135deg, #c0fe95, #88f78d);
    padding: 60rpx 30rpx;
}

.score-display {
    text-align: center;
    
    .score {
        display: block;
        font-size: 80rpx;
        color: #333;
        font-weight: bold;
        margin-bottom: 16rpx;
    }
    
    .stars {
        margin-bottom: 16rpx;
        
        .star-icon {
            font-size: 30rpx;
            color: #ddd;
            margin: 0 4rpx;
            
            &.active {
                color: #ff9500;
            }
        }
    }
    
    .count {
        font-size: 26rpx;
        color: #666;
    }
}

.list-section {
    flex: 1;
    padding: 20rpx;
}

.evaluate-scroll {
    height: 100%;
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
    margin-bottom: 16rpx;
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 16rpx;
        
        .avatar {
            width: 64rpx;
            height: 64rpx;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .name {
            font-size: 28rpx;
            color: #333;
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
    }
    
    .time {
        font-size: 24rpx;
        color: #999;
    }
}

.item-content {
    margin-bottom: 16rpx;
    
    text {
        font-size: 28rpx;
        color: #333;
        line-height: 1.6;
    }
}

.item-images {
    display: flex;
    gap: 16rpx;
    margin-bottom: 16rpx;
    
    image {
        width: 160rpx;
        height: 160rpx;
        border-radius: 8rpx;
    }
}

.item-scores {
    display: flex;
    gap: 20rpx;
    
    text {
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
</style>
