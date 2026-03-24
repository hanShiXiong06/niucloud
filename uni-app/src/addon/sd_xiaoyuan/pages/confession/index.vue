<template>
    <view class="confession-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <!-- 头部背景与搜索 -->
        <view class="header-section">
            <image class="header-bg" src="https://picsum.photos/750/350?random=love" mode="aspectFill"></image>
            <view class="header-overlay"></view>
            <view class="header-content">
                <view class="title-area">
                    <text class="main-title">表白墙</text>
                    <text class="sub-title">在这里，说出你的心声</text>
                </view>
                <view class="search-box" @click="goSearch">
                    <u-icon name="search" size="18" color="#ff6b81"></u-icon>
                    <input v-model="keyword" placeholder="搜索那个TA的名字..." placeholder-class="placeholder" @confirm="loadConfessions(true)" />
                </view>
                <view class="stats-row">
                    <view class="stat-item">
                        <text class="num">{{ stats.total || 0 }}</text>
                        <text class="label">已发布</text>
                    </view>
                    <view class="divider"></view>
                    <view class="stat-item">
                        <text class="num">{{ stats.today || 0 }}</text>
                        <text class="label">今日新增</text>
                    </view>
                    <view class="divider"></view>
                    <view class="stat-item">
                        <text class="num">{{ stats.like || 0 }}</text>
                        <text class="label">获赞数</text>
                    </view>
                </view>
            </view>
        </view>

        <!-- 通知栏 -->
        <view class="notice-bar">
            <u-icon name="volume-fill" size="16" color="#ff6b81"></u-icon>
            <swiper class="notice-swiper" vertical autoplay circular interval="3000">
                <swiper-item v-for="(msg, index) in notices" :key="index">
                    <view class="notice-item text-ellipsis">{{ msg }}</view>
                </swiper-item>
            </swiper>
        </view>

        <!-- 分类导航 -->
        <view class="category-tabs">
            <view class="tab-item" :class="{ active: currentTab === 'ALL' }" @click="switchTab('ALL')">全部</view>
            <view class="tab-item" :class="{ active: currentTab === 'CRUSH' }" @click="switchTab('CRUSH')">暗恋</view>
            <view class="tab-item" :class="{ active: currentTab === 'REAL' }" @click="switchTab('REAL')">实名</view>
            <view class="tab-item" :class="{ active: currentTab === 'FIND' }" @click="switchTab('FIND')">寻人</view>
            <view class="tab-item" :class="{ active: currentTab === 'WISH' }" @click="switchTab('WISH')">祝福</view>
        </view>

        <!-- 列表内容 -->
        <scroll-view 
            scroll-y 
            class="content-scroll"
            @scrolltolower="loadMore"
            refresher-enabled
            :refresher-triggered="isRefreshing"
            @refresherrefresh="onRefresh"
        >
            <view class="masonry-layout">
                <view class="confession-card" v-for="(item, index) in confessionList" :key="item.id" @click="goToDetail(item)">
                    <view class="card-decoration">
                        <u-icon name="heart-fill" size="14" color="#ff6b81" v-if="index % 2 === 0"></u-icon>
                        <u-icon name="star-fill" size="14" color="#ffb74d" v-else></u-icon>
                    </view>
                    
                    <view class="card-header">
                        <view class="to-tag">TO: {{ item.target_name || '某人' }}</view>
                        <text class="time">{{ (item.create_time) }}</text>
                    </view>
                    
                    <view class="card-content">
                        <text class="content-text">{{ item.content }}</text>
                    </view>

                    <view class="card-images" v-if="item.images && parseImages(item.images).length > 0">
                         <image 
                            :src="img(parseImages(item.images)[0])" 
                            mode="aspectFill"
                            class="main-img"
                        ></image>
                        <view class="img-count" v-if="parseImages(item.images).length > 1">
                            {{ parseImages(item.images).length }}张
                        </view>
                    </view>

                    <view class="card-footer">
                        <view class="from-info">
                            <image class="avatar" :src="item.is_anonymous ? img('/static/resource/images/default_headimg.png') : img(item.avatar || item.headimg || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                            <text class="name">{{ item.is_anonymous ? '匿名同学' : (item.nickname || '用户') }}</text>
                        </view>
                        <view class="actions">
                            <view class="action-btn" @click.stop="likeConfession(item)">
                                <u-icon :name="item.is_liked ? 'heart-fill' : 'heart'" size="18" :color="item.is_liked ? '#ff6b81' : '#ccc'"></u-icon>
                                <text :class="{ active: item.is_liked }">{{ item.like_count || 0 }}</text>
                            </view>
                            <view class="action-btn">
                                <u-icon name="chat" size="18" color="#ccc"></u-icon>
                                <text>{{ item.comment_count || 0 }}</text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>

            <view class="empty-state" v-if="confessionList.length === 0 && !loading">
                <u-icon name="heart" size="80" color="#ffb7c5"></u-icon>
                <text>还没有表白内容，勇敢一点~</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#ff6b81"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && confessionList.length > 0">
                <text>—— 到底啦 ——</text>
            </view>
        </scroll-view>

        <!-- 发布按钮 -->
        <view class="fab-btn" @click="goToPublish">
            <u-icon name="edit-pen" size="24" color="#fff"></u-icon>
            <text>表白</text>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getConfessionList, likeConfession as likeConfessionApi, getConfessionStats } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_confession')

const keyword = ref('')
const currentTab = ref('ALL')
const confessionList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const stats = ref({
    total: 0,
    today: 0,
    like: 0
})
const notices = ref([
    '温馨提示：文明表白，拒绝骚扰',
    '匿名表白请注意保护个人隐私',
    '勇敢说出你的心声吧~'
])

onMounted(() => {
    loadConfig()
    loadConfessions()
    loadStats()
})

onShow(() => {
    loadConfessions(true)
    loadStats()
})

const loadStats = async () => {
    const res: any = await getConfessionStats()
    if (res.code === 1 && res.data) {
        stats.value = res.data
    }
}

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
        const params: any = {
            page: page.value,
            limit,
            keyword: keyword.value
        }
        if (currentTab.value !== 'ALL') {
            params.type = currentTab.value
        }
        
        // 获取当前选择的学校
        const currentSchool = uni.getStorageSync('current_school')
        if (currentSchool?.id) {
            params.school_id = currentSchool.id
        }

        const res: any = await getConfessionList(params)
        
        if (res.code === 1) {
            if (refresh) {
                confessionList.value = res.data.list || []
            } else {
                confessionList.value = [...confessionList.value, ...(res.data.list || [])]
            }
            
            if ((res.data.list || []).length < limit) {
                hasMore.value = false
            } else {
                page.value++
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
        isRefreshing.value = false
    }
}

const loadMore = () => {
    loadConfessions()
}

const onRefresh = () => {
    isRefreshing.value = true
    loadConfessions(true)
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

const formatTime = (timeVal: any) => {
    if (!timeVal) return ''
    let d: Date
    if (typeof timeVal === 'string') {
        d = new Date(timeVal.replace(/-/g, '/'))
    } else if (typeof timeVal === 'number') {
        d = timeVal > 9999999999 ? new Date(timeVal) : new Date(timeVal * 1000)
    } else {
        return ''
    }
    if (isNaN(d.getTime())) return ''
    const now = new Date()
    const diff = now.getTime() - d.getTime()
    if (diff < 60000) return '刚刚'
    if (diff < 3600000) return Math.floor(diff / 60000) + '分钟前'
    if (diff < 86400000) return Math.floor(diff / 3600000) + '小时前'
    
    const M = (d.getMonth() + 1).toString().padStart(2, '0')
    const D = d.getDate().toString().padStart(2, '0')
    return `${M}-${D}`
}

const goToDetail = (item: any) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/confession/detail?id=${item.id}`
    })
}

const goToPublish = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/confession/publish'
    })
}

const goSearch = () => {
    // Search handled in input
}

const likeConfession = async (item: any) => {
    try {
        const res: any = await likeConfessionApi({ confession_id: item.id })
        if (res.code === 1) {
            item.like_count = (item.like_count || 0) + 1
            item.is_liked = true
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e: any) {
        console.error('点赞失败', e)
        uni.showToast({ title: e.msg || '操作失败', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.confession-page {
    min-height: 100vh;
    background: #fdf5f7;
    display: flex;
    flex-direction: column;
}

.header-section {
    position: relative;
    height: 460rpx;
    
    .header-bg {
        width: 100%;
        height: 100%;
        position: absolute;
        top: 0; left: 0;
    }
    
    .header-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(to bottom, rgba(255,107,129,0.2), rgba(253,245,247,1));
    }
    
    .header-content {
        position: relative;
        z-index: 10;
        padding: 100rpx 40rpx 40rpx;
        display: flex;
        flex-direction: column;
        align-items: center;
        
        .title-area {
            text-align: center;
            margin-bottom: 30rpx;
            
            .main-title {
                display: block;
                font-size: 56rpx;
                font-weight: bold;
                color: #fff;
                text-shadow: 0 4rpx 12rpx rgba(255,107,129,0.5);
                font-family: 'Times New Roman', serif;
                letter-spacing: 4rpx;
            }
            
            .sub-title {
                font-size: 26rpx;
                color: rgba(255,255,255,0.9);
                letter-spacing: 2rpx;
                margin-top: 10rpx;
            }
        }
        
        .search-box {
            width: 100%;
            height: 80rpx;
            background: #fff;
            border-radius: 40rpx;
            display: flex;
            align-items: center;
            padding: 0 30rpx;
            margin-bottom: 30rpx;
            box-shadow: 0 8rpx 20rpx rgba(255,107,129,0.15);
            
            input {
                flex: 1;
                margin-left: 16rpx;
                font-size: 28rpx;
                color: #333;
            }
            
            .placeholder {
                color: #ffb7c5;
            }
        }
        
        .stats-row {
            width: 100%;
            display: flex;
            justify-content: space-around;
            align-items: center;
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(10px);
            border-radius: 20rpx;
            padding: 20rpx 0;
            
            .stat-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                
                .num {
                    font-size: 32rpx;
                    font-weight: bold;
                    color: #ff6b81;
                }
                
                .label {
                    font-size: 22rpx;
                    color: #888;
                    margin-top: 4rpx;
                }
            }
            
            .divider {
                width: 2rpx;
                height: 30rpx;
                background: rgba(255,107,129,0.2);
            }
        }
    }
}

.notice-bar {
    margin: 0 24rpx 20rpx;
    background: #fff0f3;
    border-radius: 50rpx;
    padding: 0 20rpx;
    height: 64rpx;
    display: flex;
    align-items: center;
    gap: 12rpx;
    
    .notice-swiper {
        flex: 1;
        height: 64rpx;
        
        .notice-item {
            line-height: 64rpx;
            font-size: 24rpx;
            color: #ff6b81;
        }
    }
}

.category-tabs {
    display: flex;
    justify-content: space-around;
    padding: 0 20rpx 20rpx;
    
    .tab-item {
        padding: 10rpx 30rpx;
        font-size: 28rpx;
        color: #888;
        border-radius: 30rpx;
        transition: all 0.3s;
        
        &.active {
            background: #ff6b81;
            color: #fff;
            box-shadow: 0 4rpx 12rpx rgba(255,107,129,0.3);
        }
    }
}

.content-scroll {
    flex: 1;
    height: 0;
}

.masonry-layout {
    padding: 0 24rpx 120rpx;
    column-count: 2;
    column-gap: 20rpx;
}

.confession-card {
    break-inside: avoid;
    background: #fff;
    border-radius: 24rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 4rpx 16rpx rgba(255,107,129,0.08);
    position: relative;
    overflow: hidden;
    border: 1rpx solid #fff0f5;
    
    .card-decoration {
        position: absolute;
        top: 10rpx;
        right: 10rpx;
        opacity: 0.2;
        transform: rotate(15deg);
    }
    
    .card-header {
        margin-bottom: 16rpx;
        
        .to-tag {
            display: inline-block;
            font-size: 26rpx;
            font-weight: bold;
            color: #ff6b81;
            background: #fff0f3;
            padding: 4rpx 12rpx;
            border-radius: 8rpx;
            margin-bottom: 8rpx;
        }
        
        .time {
            display: block;
            font-size: 20rpx;
            color: #ccc;
        }
    }
    
    .card-content {
        margin-bottom: 16rpx;
        
        .content-text {
            font-size: 28rpx;
            color: #444;
            line-height: 1.6;
            word-break: break-all;
        }
    }
    
    .card-images {
        margin-bottom: 16rpx;
        position: relative;
        border-radius: 12rpx;
        overflow: hidden;
        
        .main-img {
            width: 100%;
            height: 240rpx;
            display: block;
        }
        
        .img-count {
            position: absolute;
            right: 10rpx;
            bottom: 10rpx;
            background: rgba(0,0,0,0.5);
            color: #fff;
            font-size: 20rpx;
            padding: 2rpx 10rpx;
            border-radius: 20rpx;
        }
    }
    
    .card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        
        .from-info {
            display: flex;
            align-items: center;
            
            .avatar {
                width: 40rpx;
                height: 40rpx;
                border-radius: 50%;
                margin-right: 8rpx;
                border: 1rpx solid #eee;
            }
            
            .name {
                font-size: 22rpx;
                color: #999;
                max-width: 120rpx;
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
            }
        }
        
        .actions {
            display: flex;
            gap: 16rpx;
            
            .action-btn {
                display: flex;
                align-items: center;
                gap: 4rpx;
                
                text {
                    font-size: 22rpx;
                    color: #ccc;
                    
                    &.active {
                        color: #ff6b81;
                    }
                }
            }
        }
    }
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    text {
        font-size: 26rpx;
        color: #ffb7c5;
        margin-top: 20rpx;
    }
}

.loading-more, .no-more {
    padding: 20rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;
    
    text {
        font-size: 22rpx;
        color: #ffb7c5;
    }
}

.fab-btn {
    position: fixed;
    right: 30rpx;
    bottom: 150rpx;
    background: linear-gradient(135deg, #ff6b81, #ff8e9e);
    color: #fff;
    padding: 20rpx 40rpx;
    border-radius: 50rpx;
    display: flex;
    align-items: center;
    gap: 10rpx;
    box-shadow: 0 8rpx 20rpx rgba(255,107,129,0.4);
    z-index: 100;
    animation: pulse 2s infinite;
    
    text {
        font-weight: bold;
        font-size: 28rpx;
    }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.text-ellipsis {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
</style>
