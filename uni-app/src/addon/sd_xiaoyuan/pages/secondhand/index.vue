<template>
    <view class="secondhand-page">
        <!-- 功能关闭提示 -->
        <view v-if="config && config.enable_secondhand == 0" style="text-align:center;margin-top:150rpx;">
            <u-icon name="info-circle" size="60" color="#ccc"></u-icon>
            <text style="display:block;margin-top:20rpx;color:#999;font-size:28rpx;">{{ config.close_text || '功能已下架' }}</text>
        </view>
        
        <!-- 正常内容 -->
        <view v-else>
        <!-- 头部搜索与背景 -->
        <view class="header-section">
            <view class="header-bg"></view>
            <view class="header-content">
                <view class="title-bar">
                    <text class="app-name">闲置市场</text>
                    <view class="location" @click="handleSchoolClick">
                        <u-icon name="map-fill" size="14" color="#fff"></u-icon>
                        <text>{{ currentSchool.name || '选择学校' }}</text>
                        <u-icon name="arrow-down" size="12" color="#fff"></u-icon>
                    </view>
                </view>
                <view class="search-box">
                    <view class="search-input">
                        <u-icon name="search" size="18" color="#999"></u-icon>
                        <input v-model="keyword" placeholder="搜一搜，发现好物..." @confirm="search" />
                    </view>
                    <view class="search-btn" @click="search">搜索</view>
                </view>
                
                <!-- 快捷入口 -->
                <view class="quick-nav">
                    <view class="nav-item" :class="{ selected: quickFilter === 'urgent' }" @click="setQuickFilter('urgent')">
                        <view class="icon-circle urgent">
                            <u-icon name="clock-fill" size="20" color="#fff"></u-icon>
                        </view>
                        <text>急售捡漏</text>
                    </view>
                    <view class="nav-item" :class="{ selected: quickFilter === 'free' }" @click="setQuickFilter('free')">
                        <view class="icon-circle free">
                            <u-icon name="gift-fill" size="20" color="#fff"></u-icon>
                        </view>
                        <text>免费送</text>
                    </view>
                    <view class="nav-item" @click="goToMy">
                        <view class="icon-circle my">
                            <u-icon name="list-dot" size="20" color="#fff"></u-icon>
                        </view>
                        <text>我的发布</text>
                    </view>
                </view>
            </view>
        </view>

        <!-- 分类导航 -->
        <view class="category-section">
            <scroll-view scroll-x class="category-scroll" :show-scrollbar="false">
                <view class="category-list">
                    <view class="category-item" :class="{ active: currentCategory === '' }" @click="changeCategory('')">
                        <view class="cat-icon" :style="{ background: getCategoryColor(-1) }">
                            <text class="cat-text">全</text>
                        </view>
                        <text class="cat-name">全部</text>
                    </view>
                    <view class="category-item" 
                        :class="{ active: currentCategory === String(item.id) }"
                        v-for="(item, index) in categoryList"
                        :key="item.id"
                        @click="changeCategory(String(item.id))"
                    >
                        <view class="cat-icon" :style="{ background: getCategoryColor(index) }">
                            <text class="cat-text">{{ item.name.substring(0, 1) }}</text>
                        </view>
                        <text class="cat-name">{{ item.name }}</text>
                    </view>
                </view>
            </scroll-view>
        </view>

        <!-- 排序筛选 -->
        <view class="filter-bar">
            <view class="filter-item" :class="{ active: sortType === 'new' }" @click="setSort('new')">最新发布</view>
            <view class="filter-item" :class="{ active: sortType === 'price_asc' || sortType === 'price_desc' }" @click="togglePriceSort">
                价格
                <view class="sort-icons">
                    <u-icon name="arrow-up-fill" size="8" :color="sortType === 'price_asc' ? '#00c853' : '#ccc'"></u-icon>
                    <u-icon name="arrow-down-fill" size="8" :color="sortType === 'price_desc' ? '#00c853' : '#ccc'"></u-icon>
                </view>
            </view>
            <view class="filter-item" :class="{ active: sortType === 'hot' }" @click="setSort('hot')">最多浏览</view>
        </view>

        <!-- 瀑布流商品列表 -->
        <scroll-view 
            scroll-y 
            class="goods-scroll"
            @scrolltolower="loadMore"
            refresher-enabled
            @refresherrefresh="onRefresh"
            :refresher-triggered="refreshing"
        >
            <view class="waterfall-container">
                <view class="waterfall-col">
                    <view class="goods-card" v-for="item in leftList" :key="item.id" @click="goToDetail(item.id)">
                        <view class="card-badge urgent" v-if="item.is_top">急售</view>
                        <view class="card-badge free" v-else-if="Number(item.price) === 0">免费送</view>
                        <image class="goods-img" :src="img(parseImages(item.images)[0])" mode="widthFix"></image>
                        <view class="goods-info">
                            <text class="goods-title">{{ item.title }}</text>
                            <view class="tags-row">
                                <text class="condition-tag" v-if="item.condition_level">{{ item.condition_level }}成新</text>
                                <text class="trade-tag" v-if="item.trade_method === 'FACE'">面交</text>
                                <text class="trade-tag express" v-if="item.trade_method === 'EXPRESS'">快递</text>
                                <text class="free-tag" v-if="Number(item.price) === 0">免费</text>
                            </view>
                            <view class="price-row" v-if="Number(item.price) > 0">
                                <text class="symbol">¥</text>
                                <text class="price">{{ item.price }}</text>
                                <text class="original" v-if="item.original_price > 0">¥{{ item.original_price }}</text>
                            </view>
                            <view class="price-row free" v-else>
                                <text class="price">免费赠送</text>
                            </view>
                            <view class="seller-row">
                                <image class="avatar" :src="img(item.member_avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                                <text class="name">{{ item.member_nickname || '同学' }}</text>
                                <text class="time">{{ formatTime(item.create_time) }}</text>
                            </view>
                        </view>
                    </view>
                </view>
                <view class="waterfall-col">
                    <view class="goods-card" v-for="item in rightList" :key="item.id" @click="goToDetail(item.id)">
                        <view class="card-badge urgent" v-if="item.is_top">急售</view>
                        <view class="card-badge free" v-else-if="Number(item.price) === 0">免费送</view>
                        <image class="goods-img" :src="img(parseImages(item.images)[0])" mode="widthFix"></image>
                        <view class="goods-info">
                            <text class="goods-title">{{ item.title }}</text>
                            <view class="tags-row">
                                <text class="condition-tag" v-if="item.condition_level">{{ item.condition_level }}成新</text>
                                <text class="trade-tag" v-if="item.trade_method === 'FACE'">面交</text>
                                <text class="free-tag" v-if="Number(item.price) === 0">免费</text>
                            </view>
                            <view class="price-row" v-if="Number(item.price) > 0">
                                <text class="symbol">¥</text>
                                <text class="price">{{ item.price }}</text>
                                <text class="original" v-if="item.original_price > 0">¥{{ item.original_price }}</text>
                            </view>
                            <view class="price-row free" v-else>
                                <text class="price">免费赠送</text>
                            </view>
                            <view class="seller-row">
                                <image class="avatar" :src="img(item.member_avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                                <text class="name">{{ item.member_nickname || '同学' }}</text>
                                <text class="time">{{ formatTime(item.create_time) }}</text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>

            <view class="empty-state" v-if="goodsList.length === 0 && !loading">
                <image src="/static/resource/images/empty.png" mode="widthFix" style="width: 240rpx;"></image>
                <text>暂无相关物品，去发布一个吧~</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#00c853"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && goodsList.length > 0">
                <text>—— 到底啦 ——</text>
            </view>
        </scroll-view>

        <!-- 发布按钮 -->
        <view class="fab-publish" @click="goToPublish">
            <view class="icon-wrap">
                <u-icon name="plus" size="28" color="#fff"></u-icon>
            </view>
            <text>卖闲置</text>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getSecondhandList, getSecondhandCategoryList, getConfig } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const config = ref<any>(null)
const keyword = ref('')
const currentCategory = ref('')
const sortType = ref('new')
const goodsList = ref<any[]>([])
const leftList = ref<any[]>([])
const rightList = ref<any[]>([])
const loading = ref(false)
const refreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const currentSchool = ref<any>(uni.getStorageSync('current_school') || {})
const quickFilter = ref('')
const categoryList = ref<any[]>([])

const categoryIcons = ['bag-fill', 'book', 'computer', 'phone', 'headphones', 'camera', 'bicycle', 'clothes', 'football', 'game', 'gift-fill', 'star-fill', 'heart-fill', 'home-fill', 'music', 'car']
const categoryColors = ['#ff6b00', '#1890ff', '#52c41a', '#722ed1', '#13c2c2', '#eb2f96', '#fa8c16', '#2f54eb', '#a0d911', '#f5222d', '#faad14', '#9254de', '#36cfc9', '#ff7a45', '#597ef7', '#73d13d']

const getCategoryIcon = (index: number) => {
    return categoryIcons[index % categoryIcons.length]
}

const getCategoryColor = (index: number) => {
    if (index === -1) return '#333333'
    return categoryColors[index % categoryColors.length]
}

onMounted(() => {
    loadConfig()
    loadCategories()
    loadGoods()
})

const loadConfig = async () => {
    const cachedConfig = uni.getStorageSync('xiaoyuan_config')
    if (cachedConfig) {
        config.value = cachedConfig
    }
    try {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            config.value = res.data
            uni.setStorageSync('xiaoyuan_config', res.data)
        }
    } catch (e) {
        console.error('获取配置失败:', e)
    }
}

onShow(() => {
    const cachedSchool = uni.getStorageSync('current_school')
    if (cachedSchool && cachedSchool.id !== currentSchool.value.id) {
        currentSchool.value = cachedSchool
    }
    loadGoods(true)
})

const changeCategory = (key: string) => {
    currentCategory.value = key
    loadGoods(true)
}

const setSort = (type: string) => {
    sortType.value = type
    loadGoods(true)
}

const togglePriceSort = () => {
    if (sortType.value === 'price_asc') {
        sortType.value = 'price_desc'
    } else {
        sortType.value = 'price_asc'
    }
    loadGoods(true)
}

const setQuickFilter = (type: string) => {
    if (quickFilter.value === type) {
        quickFilter.value = ''
    } else {
        quickFilter.value = type
    }
    loadGoods(true)
}

const handleSchoolClick = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/school/select'
    })
}

const loadCategories = async () => {
    const res: any = await getSecondhandCategoryList()
    if (res.code === 1) {
        categoryList.value = res.data || []
    }
}

const search = () => {
    loadGoods(true)
}

const loadGoods = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
        leftList.value = []
        rightList.value = []
        goodsList.value = []
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const params: any = {
            page: page.value,
            limit,
            category_id: currentCategory.value,
            keyword: keyword.value,
            sort: sortType.value
        }
        if (currentSchool.value?.id) {
            params.school_id = currentSchool.value.id
        }
        if (quickFilter.value === 'urgent') {
            params.is_urgent = 1
        }
        if (quickFilter.value === 'free') {
            params.is_free = 1
        }
        const res: any = await getSecondhandList(params)
        
        if (res.code === 1) {
            const list = res.data.list || []
            goodsList.value = refresh ? list : [...goodsList.value, ...list]
            
            // Waterfall distribution
            list.forEach((item: any, index: number) => {
                if (refresh && index === 0) {
                     // Reset on refresh handled above
                }
                // Simple distribution: add to shorter column
                if (leftList.value.length <= rightList.value.length) {
                    leftList.value.push(item)
                } else {
                    rightList.value.push(item)
                }
            })

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
        refreshing.value = false
    }
}

const loadMore = () => {
    loadGoods()
}

const onRefresh = () => {
    refreshing.value = true
    loadGoods(true)
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
    if (diff < 86400) return '今天'
    if (diff < 172800) return '昨天'
    const date = new Date(time * 1000)
    return `${date.getMonth() + 1}-${date.getDate()}`
}

const goToDetail = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/secondhand/detail?id=${id}`
    })
}

const goToPublish = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/secondhand/publish'
    })
}

const goToMy = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/secondhand/my'
    })
}
</script>

<style lang="scss" scoped>
.secondhand-page {
    min-height: 100vh;
    background: #f8f8f8;
    display: flex;
    flex-direction: column;
}

.header-section {
    position: relative;
    padding-bottom: 20rpx;
    background: #fff;
    
    .header-bg {
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 300rpx;
        background: linear-gradient(135deg, #00c853, #b2ff59);
        border-bottom-left-radius: 40rpx;
        border-bottom-right-radius: 40rpx;
        z-index: 0;
    }
    
    .header-content {
        position: relative;
        z-index: 1;
        padding: 88rpx 30rpx 0;
        
        .title-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30rpx;
            
            .app-name {
                font-size: 40rpx;
                font-weight: bold;
                color: #fff;
            }
            
            .location {
                display: flex;
                align-items: center;
                background: rgba(0,0,0,0.2);
                padding: 8rpx 20rpx;
                border-radius: 30rpx;
                gap: 8rpx;
                
                text {
                    font-size: 24rpx;
                    color: #fff;
                }
            }
        }
        
        .search-box {
            display: flex;
            gap: 20rpx;
            margin-bottom: 30rpx;
            
            .search-input {
                flex: 1;
                height: 80rpx;
                background: #fff;
                border-radius: 40rpx;
                display: flex;
                align-items: center;
                padding: 0 30rpx;
                gap: 12rpx;
                box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.05);
                
                input {
                    flex: 1;
                    font-size: 28rpx;
                }
            }
            
            .search-btn {
                width: 120rpx;
                height: 80rpx;
                background: #fff;
                color: #00c853;
                font-weight: bold;
                border-radius: 40rpx;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 28rpx;
                box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.05);
            }
        }
        
        .quick-nav {
            display: flex;
            justify-content: space-between;
            background: #fff;
            padding: 30rpx;
            border-radius: 20rpx;
            box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.04);
            
            .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 10rpx;
                
                .icon-circle {
                    width: 90rpx;
                    height: 90rpx;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    
                    &.urgent { background: linear-gradient(135deg, #ff9800, #ff5722); }
                    &.free { background: linear-gradient(135deg, #2196f3, #03a9f4); }
                    &.my { background: linear-gradient(135deg, #9c27b0, #ba68c8); }
                }
                
                text {
                    font-size: 24rpx;
                    color: #333;
                    font-weight: bold;
                }
                
                &.selected {
                    .icon-circle {
                        box-shadow: 0 0 0 4rpx rgba(0,200,83,0.4);
                    }
                    text {
                        color: #00c853;
                    }
                }
            }
        }
    }
}

.category-section {
    background: #fff;
    padding: 20rpx 0;
    margin-bottom: 20rpx;
    
    .category-scroll {
        white-space: nowrap;
    }
    
    .category-list {
        display: inline-flex;
        padding: 0 30rpx;
        gap: 30rpx;
    }
    
    .category-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6rpx;
        flex-shrink: 0;
        
        .cat-icon {
            width: 80rpx;
            height: 80rpx;
            background: #f5f5f5;
            border-radius: 24rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            
            .cat-text {
                font-size: 28rpx;
                color: #fff;
                font-weight: bold;
            }
        }
        
        .cat-name {
            font-size: 22rpx;
            color: #666;
            white-space: nowrap;
            // max-width: 80rpx;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        &.active {
            .cat-icon {
                background: #e8f5e9;
                border: 2rpx solid #00c853;
            }
            
            .cat-name {
                color: #00c853;
                font-weight: bold;
            }
        }
    }
}

.filter-bar {
    display: flex;
    background: #fff;
    padding: 20rpx 30rpx;
    border-bottom: 1rpx solid #f0f0f0;
    position: sticky;
    top: 0;
    z-index: 10;
    
    .filter-item {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28rpx;
        color: #666;
        gap: 4rpx;
        
        &.active {
            color: #00c853;
            font-weight: bold;
        }
        
        .sort-icons {
            display: flex;
            flex-direction: column;
            gap: 2rpx;
        }
    }
}

.goods-scroll {
    flex: 1;
}

.waterfall-container {
    display: flex;
    padding: 20rpx;
    gap: 20rpx;
    align-items: flex-start;
}

.waterfall-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 20rpx;
}

.goods-card {
    background: #fff;
    border-radius: 20rpx;
    overflow: hidden;
    box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.03);
    position: relative;
    
    .card-badge {
        position: absolute;
        top: 16rpx;
        left: 0;
        padding: 4rpx 16rpx 4rpx 12rpx;
        font-size: 20rpx;
        color: #fff;
        font-weight: bold;
        z-index: 2;
        border-radius: 0 20rpx 20rpx 0;
        
        &.urgent {
            background: linear-gradient(135deg, #ff5722, #ff9800);
        }
        &.free {
            background: linear-gradient(135deg, #2196f3, #03a9f4);
        }
    }
    
    .goods-img {
        width: 100%;
        display: block;
    }
    
    .goods-info {
        padding: 20rpx;
        
        .goods-title {
            font-size: 28rpx;
            font-weight: bold;
            color: #333;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 12rpx;
        }
        
        .tags-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8rpx;
            margin-bottom: 12rpx;
            
            .condition-tag {
                font-size: 20rpx;
                color: #00c853;
                background: #e8f5e9;
                padding: 2rpx 8rpx;
                border-radius: 6rpx;
            }
            
            .trade-tag {
                font-size: 20rpx;
                color: #ff9800;
                background: #fff3e0;
                padding: 2rpx 8rpx;
                border-radius: 6rpx;
                
                &.express {
                    color: #9c27b0;
                    background: #f3e5f5;
                }
            }
            
            .free-tag {
                font-size: 20rpx;
                color: #2196f3;
                background: #e3f2fd;
                padding: 2rpx 8rpx;
                border-radius: 6rpx;
            }
        }
        
        .price-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 16rpx;
            
            .symbol {
                font-size: 24rpx;
                color: #ff5722;
                font-weight: bold;
            }
            
            .price {
                font-size: 34rpx;
                color: #ff5722;
                font-weight: bold;
            }
            
            .original {
                font-size: 22rpx;
                color: #bbb;
                text-decoration: line-through;
                margin-left: 10rpx;
            }
            
            &.free .price {
                font-size: 28rpx;
                color: #2196f3;
                font-weight: bold;
            }
        }
        
        .seller-row {
            display: flex;
            align-items: center;
            
            .avatar {
                width: 36rpx;
                height: 36rpx;
                border-radius: 50%;
                margin-right: 8rpx;
            }
            
            .name {
                flex: 1;
                font-size: 22rpx;
                color: #999;
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
            }
            
            .time {
                font-size: 20rpx;
                color: #ccc;
            }
        }
    }
}

.fab-publish {
    position: fixed;
    bottom: 60rpx;
    left: 50%;
    transform: translateX(-50%);
    background: #00c853;
    padding: 12rpx 40rpx 12rpx 30rpx;
    border-radius: 50rpx;
    display: flex;
    align-items: center;
    gap: 16rpx;
    box-shadow: 0 8rpx 20rpx rgba(0, 200, 83, 0.4);
    z-index: 99;
    
    .icon-wrap {
        width: 60rpx;
        height: 60rpx;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    text {
        font-size: 30rpx;
        color: #fff;
        font-weight: bold;
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
</style>
