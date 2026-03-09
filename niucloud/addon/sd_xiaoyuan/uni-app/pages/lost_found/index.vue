<template>
    <view class="lostfound-page">
        <!-- 顶部统计与搜索 -->
        <view class="header-section" :class="currentType.toLowerCase()">
            <view class="header-content">
                <view class="app-info">
                    <text class="title">失物招领</text>
                    <text class="subtitle">互助传递温暖 · 让失物回家</text>
                </view>
                <view class="stats-box">
                    <view class="stat-item">
                        <text class="num">{{ stats.total || 0 }}</text>
                        <text class="label">累计发布</text>
                    </view>
                    <view class="divider"></view>
                    <view class="stat-item">
                        <text class="num">{{ stats.found || 0 }}</text>
                        <text class="label">成功寻回</text>
                    </view>
                </view>
            </view>
            <view class="search-container">
                <view class="search-input">
                    <u-icon name="search" size="18" color="#999"></u-icon>
                    <input v-model="keyword" placeholder="搜索物品名称/地点..." @confirm="loadItems(true)" />
                </view>
            </view>
        </view>

        <!-- 分类与筛选 -->
        <view class="filter-section">
            <view class="type-switch">
                <view class="switch-item" :class="{ active: currentType === 'LOST' }" @click="changeType('LOST')">
                    <text class="iconfont icon-search"></text>
                    <text>寻物启事</text>
                </view>
                <view class="switch-item" :class="{ active: currentType === 'FOUND' }" @click="changeType('FOUND')">
                    <text class="iconfont icon-gift"></text>
                    <text>招领信息</text>
                </view>
            </view>
            
            <scroll-view scroll-x class="tags-scroll" :show-scrollbar="false">
                <view class="tags-list">
                    <view class="tag-item" :class="{ active: currentCategory === '' }" @click="changeCategory('')">全部</view>
                    <view class="tag-item" 
                        :class="{ active: currentCategory === item.key }" 
                        v-for="item in categoryList" 
                        :key="item.key"
                        @click="changeCategory(item.key)"
                    >{{ item.name }}</view>
                </view>
            </scroll-view>
        </view>

        <!-- 紧急寻物轮播 -->
        <view class="urgent-section" v-if="urgentList.length > 0 && currentType === 'LOST'">
            <view class="section-title">
                <u-icon name="bell-fill" color="#ff4d4f" size="18"></u-icon>
                <text>急寻物品</text>
            </view>
            <swiper class="urgent-swiper" circular autoplay interval="4000" next-margin="40rpx">
                <swiper-item v-for="item in urgentList" :key="item.id">
                    <view class="urgent-card" @click="goToDetail(item.id)">
                        <image class="thumb" :src="img(parseImages(item.images)[0])" mode="aspectFill"></image>
                        <view class="info">
                            <text class="name text-ellipsis">{{ item.title }}</text>
                            <text class="loc text-ellipsis">
                                <u-icon name="map-fill" size="12" color="#999"></u-icon>
                                {{ item.lost_address }}
                            </text>
                            <view class="reward-badge" v-if="item.reward > 0">悬赏 ¥{{ item.reward }}</view>
                        </view>
                    </view>
                </swiper-item>
            </swiper>
        </view>

        <!-- 列表内容 -->
        <scroll-view 
            scroll-y 
            class="list-scroll"
            @scrolltolower="loadMore"
            refresher-enabled
            @refresherrefresh="onRefresh"
            :refresher-triggered="refreshing"
        >
            <view class="item-list">
                <view class="item-card" v-for="item in itemList" :key="item.id" @click="goToDetail(item.id)">
                    <view class="card-status" :class="getStatusClass(item.status)">
                        {{ getStatusText(item.status) }}
                    </view>
                    
                    <view class="card-main">
                        <image class="card-img" :src="img(parseImages(item.images)[0])" mode="aspectFill"></image>
                        <view class="card-info">
                            <view class="title-row">
                                <text class="title text-ellipsis">{{ item.title }}</text>
                                <text class="time">{{ formatTime(item.create_time) }}</text>
                            </view>
                            <view class="desc text-ellipsis-2">{{ item.content }}</view>
                            <view class="meta-row">
                                <view class="location text-ellipsis">
                                    <u-icon name="map" size="14" color="#666"></u-icon>
                                    <text>{{ item.lost_address || '地点未知' }}</text>
                                </view>
                            </view>
                            <view class="tags-row">
                                <view class="tag">{{ getCategoryName(item.category) }}</view>
                                <view class="tag reward" v-if="item.reward > 0 && currentType === 'LOST'">悬赏¥{{ item.reward }}</view>
                            </view>
                        </view>
                    </view>
                    
                    <view class="card-footer">
                        <view class="user">
                            <image class="avatar" :src="item.member_avatar ? img(item.member_avatar) : 'https://cdn.niucloud.com/img/default_headimg.png'" mode="aspectFill"></image>
                            <text class="nickname">{{ item.member_nickname || '同学' }}</text>
                        </view>
                        <view class="contact-btn" @click.stop="handleContact(item)">
                            <u-icon name="phone-fill" size="14" color="#fff"></u-icon>
                            <text>联系TA</text>
                        </view>
                    </view>
                </view>
            </view>

            <view class="empty-state" v-if="itemList.length === 0 && !loading">
                <u-icon name="search" size="80" color="#ccc"></u-icon>
                <text>暂无相关信息</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" :color="currentType === 'LOST' ? '#ff4d4f' : '#52c41a'"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && itemList.length > 0">
                <text>—— 到底啦 ——</text>
            </view>
        </scroll-view>

        <!-- 悬浮发布按钮组 -->
        <view class="fab-group">
            <view class="fab-btn lost" @click="goToPublish('LOST')">
                <text>我丢了</text>
            </view>
            <view class="fab-btn found" @click="goToPublish('FOUND')">
                <text>我捡到</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getLostFoundList, getLostFoundStats } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const currentType = ref('LOST')
const currentCategory = ref('')
const keyword = ref('')
const itemList = ref<any[]>([])
const urgentList = ref<any[]>([])
const loading = ref(false)
const refreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const stats = ref({
    total: 0,
    found: 0
})

const categoryList = [
    { key: '证件卡片', name: '证件' },
    { key: '电子产品', name: '数码' },
    { key: '钥匙', name: '钥匙' },
    { key: '衣物饰品', name: '衣物' },
    { key: '书籍文具', name: '书籍' },
    { key: '其他', name: '其他' }
]

const loadStats = async () => {
    const cachedSchool = uni.getStorageSync('current_school')
    const params: any = {}
    if (cachedSchool?.id) {
        params.school_id = cachedSchool.id
    }
    const res: any = await getLostFoundStats(params)
    if (res.code === 1 && res.data) {
        stats.value = res.data
    }
}

onMounted(() => {
    loadItems()
    loadUrgent()
    loadStats()
})

onShow(() => {
    loadItems(true)
    loadStats()
})

const changeType = (type: string) => {
    currentType.value = type
    changeCategory('')
}

const changeCategory = (key: string) => {
    currentCategory.value = key
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
    
    try {
        const params: any = {
            page: page.value,
            limit,
            type: currentType.value,
            category: currentCategory.value,
            keyword: keyword.value
        }
        const cachedSchool = uni.getStorageSync('current_school')
        if (cachedSchool?.id) {
            params.school_id = cachedSchool.id
        }
        const res: any = await getLostFoundList(params)
        
        if (res.code === 1) {
            if (refresh) {
                itemList.value = res.data.list || []
            } else {
                itemList.value = [...itemList.value, ...(res.data.list || [])]
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
        refreshing.value = false
    }
}

const loadUrgent = async () => {
    // 只加载状态为1（进行中）的急寻物品
    const res: any = await getLostFoundList({ page: 1, limit: 5, type: 'LOST', is_urgent: 1, status: 1 })
    if (res.code === 1) {
        urgentList.value = res.data.list || []
    }
}

const loadMore = () => {
    loadItems()
}

const onRefresh = () => {
    refreshing.value = true
    loadItems(true)
    loadUrgent()
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

const formatTime = (time: any) => {
    if (!time) return ''
    if (typeof time === 'string') return time
    const now = Date.now() / 1000
    const diff = now - time
    if (diff < 3600) return Math.ceil(diff / 60) + '分钟前'
    if (diff < 86400) return Math.ceil(diff / 3600) + '小时前'
    const date = new Date(time * 1000)
    return `${date.getMonth() + 1}-${date.getDate()}`
}

const getStatusText = (status: number) => {
    const map: Record<number, string> = {
        0: '已关闭',
        1: '进行中',
        2: '已找到'
    }
    return map[status] || '进行中'
}

const getStatusClass = (status: number) => {
    if (status === 2) return 'success'
    if (status === 0) return 'closed'
    return 'active'
}

const getCategoryName = (key: string) => {
    const item = categoryList.find(c => c.key === key)
    return item ? item.name : '物品'
}

const goToDetail = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/lost_found/detail?id=${id}`
    })
}

const goToPublish = (type: string) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/lost_found/publish?type=${type}`
    })
}

const handleContact = (item: any) => {
    uni.showActionSheet({
        itemList: ['拨打电话', '复制微信'],
        success: (res) => {
            if (res.tapIndex === 0 && item.mobile) {
                uni.makePhoneCall({ phoneNumber: item.mobile })
            } else if (res.tapIndex === 1) {
                // Copy wechat logic
                uni.showToast({ title: '暂无微信', icon: 'none' })
            } else {
                if (!item.mobile) uni.showToast({ title: '对方未留电话', icon: 'none' })
            }
        }
    })
}
</script>

<style lang="scss" scoped>
.lostfound-page {
    min-height: 100vh;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
}

.header-section {
    position: relative;
    padding: 100rpx 30rpx 40rpx;
    background: linear-gradient(135deg, #ff4d4f, #ff7875);
    color: #fff;
    transition: all 0.3s;
    
    &.found {
        background: linear-gradient(135deg, #52c41a, #95de64);
    }
    
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 30rpx;
        
        .title {
            font-size: 48rpx;
            font-weight: bold;
            display: block;
            margin-bottom: 8rpx;
        }
        
        .subtitle {
            font-size: 24rpx;
            opacity: 0.9;
        }
        
        .stats-box {
            display: flex;
            background: rgba(255,255,255,0.2);
            padding: 12rpx 24rpx;
            border-radius: 12rpx;
            align-items: center;
            
            .stat-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                
                .num { font-size: 28rpx; font-weight: bold; }
                .label { font-size: 20rpx; opacity: 0.8; }
            }
            
            .divider {
                width: 1rpx;
                height: 24rpx;
                background: rgba(255,255,255,0.4);
                margin: 0 20rpx;
            }
        }
    }
    
    .search-container {
        .search-input {
            background: #fff;
            height: 80rpx;
            border-radius: 40rpx;
            display: flex;
            align-items: center;
            padding: 0 30rpx;
            gap: 16rpx;
            
            input {
                flex: 1;
                font-size: 28rpx;
                color: #333;
            }
        }
    }
}

.filter-section {
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;
    margin-top: -24rpx;
    padding: 30rpx 0;
    position: relative;
    z-index: 10;
    
    .type-switch {
        display: flex;
        margin: 0 30rpx 30rpx;
        background: #f5f5f5;
        padding: 8rpx;
        border-radius: 16rpx;
        
        .switch-item {
            flex: 1;
            text-align: center;
            padding: 16rpx 0;
            border-radius: 12rpx;
            font-size: 28rpx;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8rpx;
            
            &.active {
                background: #fff;
                color: #333;
                font-weight: bold;
                box-shadow: 0 2rpx 8rpx rgba(0,0,0,0.05);
            }
        }
    }
    
    .tags-scroll {
        white-space: nowrap;
    }
    
    .tags-list {
        display: inline-flex;
        padding: 0 30rpx;
        gap: 20rpx;
    }
    
    .tag-item {
        padding: 10rpx 30rpx;
        background: #f5f5f5;
        border-radius: 30rpx;
        font-size: 26rpx;
        color: #666;
        
        &.active {
            background: #e6f7ff;
            color: #1890ff;
            font-weight: bold;
        }
    }
}

.urgent-section {
    margin: 0 24rpx 24rpx;
    background: #fff;
    border-radius: 20rpx;
    padding: 24rpx;
    
    .section-title {
        display: flex;
        align-items: center;
        gap: 8rpx;
        margin-bottom: 20rpx;
        
        text {
            font-size: 30rpx;
            font-weight: bold;
            color: #333;
        }
    }
    
    .urgent-swiper {
        height: 200rpx;
        
        .urgent-card {
            display: flex;
            align-items: center;
            background: #fff5f5;
            padding: 20rpx;
            border-radius: 16rpx;
            margin-right: 20rpx;
            height: calc(100% - 10rpx);
            box-sizing: border-box;
            
            .thumb {
                width: 120rpx;
                height: 120rpx;
                border-radius: 12rpx;
                margin-right: 20rpx;
                flex-shrink: 0;
            }
            
            .info {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 10rpx;
                
                .name { 
                    font-size: 28rpx; 
                    font-weight: bold; 
                    color: #333; 
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }
                .loc { 
                    font-size: 24rpx; 
                    color: #999; 
                    display: flex;
                    align-items: center;
                    gap: 4rpx;
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }
                
                .reward-badge {
                    align-self: flex-start;
                    font-size: 22rpx;
                    color: #fff;
                    background: #ff4d4f;
                    padding: 4rpx 14rpx;
                    border-radius: 8rpx;
                }
            }
        }
    }
}

.list-scroll {
    flex: 1;
    height: 0;
}

.item-list {
    padding: 0 24rpx 120rpx;
}

.item-card {
    background: #fff;
    border-radius: 20rpx;
    padding: 24rpx;
    margin-bottom: 24rpx;
    position: relative;
    box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.02);
    
    .card-status {
        display: inline-block;
        font-size: 22rpx;
        padding: 4rpx 12rpx;
        border-radius: 16rpx;
        background: #f5f5f5;
        color: #999;
        margin-bottom: 16rpx;
        
        &.active {
            background: #e6f7ff;
            color: #1890ff;
        }
        
        &.success {
            background: #f6ffed;
            color: #52c41a;
        }
    }
    
    .card-main {
        display: flex;
        gap: 20rpx;
        margin-bottom: 16rpx;
        
        .card-img {
            width: 160rpx;
            height: 160rpx;
            border-radius: 12rpx;
            flex-shrink: 0;
            background: #f5f5f5;
        }
        
        .card-info {
            flex: 1;
            min-width: 0;
            
            .title-row {
                margin-bottom: 8rpx;
                
                .title { 
                    font-size: 28rpx; 
                    font-weight: bold; 
                    color: #333; 
                    display: block;
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }
                .time { 
                    font-size: 22rpx; 
                    color: #ccc; 
                    display: block;
                    margin-top: 4rpx;
                }
            }
            
            .desc {
                font-size: 24rpx;
                color: #666;
                line-height: 1.5;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                word-break: break-all;
                margin-bottom: 8rpx;
            }
            
            .meta-row {
                margin-bottom: 8rpx;
                .location {
                    font-size: 22rpx;
                    color: #999;
                    display: flex;
                    align-items: center;
                    gap: 4rpx;
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }
            }
            
            .tags-row {
                display: flex;
                gap: 8rpx;
                flex-wrap: wrap;
                
                .tag {
                    font-size: 20rpx;
                    padding: 4rpx 12rpx;
                    background: #f5f5f5;
                    color: #666;
                    border-radius: 6rpx;
                    white-space: nowrap;
                    
                    &.reward {
                        background: #fff1f0;
                        color: #ff4d4f;
                        font-weight: bold;
                    }
                }
            }
        }
    }
    
    .card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 20rpx;
        border-top: 1rpx solid #f5f5f5;
        
        .user {
            display: flex;
            align-items: center;
            
            .avatar {
                width: 44rpx;
                height: 44rpx;
                border-radius: 50%;
                margin-right: 12rpx;
            }
            
            .nickname {
                font-size: 26rpx;
                color: #666;
            }
        }
        
        .contact-btn {
            display: flex;
            align-items: center;
            gap: 8rpx;
            padding: 10rpx 24rpx;
            border-radius: 30rpx;
            background: #1890ff;
            
            text {
                color: #fff;
                font-size: 24rpx;
            }
        }
    }
}

.fab-group {
    position: fixed;
    bottom: 60rpx;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 40rpx;
    z-index: 99;
    
    .fab-btn {
        width: 200rpx;
        height: 80rpx;
        border-radius: 40rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8rpx 20rpx rgba(0,0,0,0.15);
        font-weight: bold;
        color: #fff;
        font-size: 30rpx;
        
        &.lost {
            background: linear-gradient(135deg, #ff4d4f, #ff7875);
        }
        
        &.found {
            background: linear-gradient(135deg, #52c41a, #95de64);
        }
    }
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    text {
        color: #999;
        font-size: 26rpx;
        margin-top: 20rpx;
    }
}

.loading-more, .no-more {
    padding: 30rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;
    
    text {
        font-size: 24rpx;
        color: #ccc;
    }
}

.text-ellipsis {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.text-ellipsis-2 {
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
}
</style>
