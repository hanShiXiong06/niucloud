<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="house-page" v-if="isFeatureEnabled">
        <!-- 顶部搜索与筛选 -->
        <view class="header-section">
            <view class="search-box">
                <view class="search-input">
                    <u-icon name="search" size="18" color="#666"></u-icon>
                    <input v-model="keyword" placeholder="搜索校内/周边房源..." @confirm="doSearch" />
                </view>
            </view>
            <view class="filter-bar">
                <view class="filter-item" :class="{active: houseType === ''}" @click="setType('')">
                    <text>全部</text>
                </view>
                <view class="filter-item" :class="{active: houseType === 'RENT'}" @click="setType('RENT')">
                    <text>整租</text>
                </view>
                <view class="filter-item" :class="{active: houseType === 'SHARE'}" @click="setType('SHARE')">
                    <text>合租</text>
                </view>
                <view class="filter-item" :class="{active: houseType === 'SUBLEASE'}" @click="setType('SUBLEASE')">
                    <text>转租</text>
                </view>
            </view>
        </view>

        <!-- 房源列表 -->
        <scroll-view 
            scroll-y 
            class="content-scroll" 
            @scrolltolower="loadMore"
            refresher-enabled
            @refresherrefresh="onRefresh"
            :refresher-triggered="refreshing"
        >
            <view class="house-list">
                <view class="house-card" v-for="item in houseList" :key="item.id" @click="goToDetail(item)">
                    <view class="image-wrapper">
                        <image class="cover-img" :src="img(item.cover_image || getFirstImage(item.images))" mode="aspectFill"></image>
                        <view class="type-badge" :class="item.house_type">{{ getTypeName(item.house_type) }}</view>
                        <view class="price-badge">
                            <text class="symbol">¥</text>
                            <text class="num">{{ item.price }}</text>
                            <text class="unit">/月</text>
                        </view>
                    </view>
                    
                    <view class="info-wrapper">
                        <text class="title text-ellipsis">{{ item.title }}</text>
                        
                        <view class="meta-row">
                            <text class="meta-item">{{ item.room_type || '户型未知' }}</text>
                            <view class="divider"></view>
                            <text class="meta-item">{{ item.area ? item.area + '㎡' : '面积未知' }}</text>
                            <view class="divider"></view>
                            <text class="meta-item">{{ item.floor ? item.floor + '层' : '楼层未知' }}</text>
                        </view>
                        
                        <view class="tags-row">
                            <view class="tag" v-if="item.direction">{{ item.direction }}</view>
                            <view class="tag" v-if="item.elevator === 1">电梯</view>
                            <view class="tag highlight">近学校</view>
                        </view>
                        
                        <view class="address-row">
                            <u-icon name="map" size="14" color="#999"></u-icon>
                            <text class="address text-ellipsis">{{ item.address || '暂无详细地址' }}</text>
                        </view>
                        
                        <view class="footer-row">
                            <view class="user">
                                <image class="avatar" :src="img(item.member_avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                                <text class="name">{{ item.member_nickname || '房东' }}</text>
                            </view>
                            <text class="time">{{ formatTime(item.create_time) }}</text>
                        </view>
                    </view>
                </view>
            </view>

            <view class="empty-state" v-if="houseList.length === 0 && !loading">
                <image src="/static/resource/images/empty.png" mode="widthFix" style="width: 240rpx;"></image>
                <text>暂无房源信息</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#00c853"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && houseList.length > 0">
                <text>—— 到底啦 ——</text>
            </view>
        </scroll-view>

        <!-- 发布按钮 -->
        <!-- <view class="fab-btn" @click="goToPublish">
            <u-icon name="plus" size="24" color="#fff"></u-icon>
            <text>发布房源</text>
        </view> -->
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getHouseList } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_house')

const keyword = ref('')
const houseType = ref('')
const houseList = ref<any[]>([])
const loading = ref(false)
const refreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const currentSchool = ref<any>(uni.getStorageSync('current_school') || {})

const typeMap: Record<string, string> = {
    'RENT': '整租',
    'SHARE': '合租',
    'SUBLEASE': '转租'
}

onMounted(() => {
    loadConfig()
    loadHouses()
})

onShow(() => {
    const cachedSchool = uni.getStorageSync('current_school')
    if (cachedSchool && cachedSchool.id !== currentSchool.value.id) {
        currentSchool.value = cachedSchool
    }
    loadHouses(true)
})

const loadHouses = async (refresh = false) => {
    if (loading.value) return
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    if (!hasMore.value) return

    loading.value = true
    try {
        const res: any = await getHouseList({
            page: page.value,
            limit: 10,
            house_type: houseType.value,
            school_id: currentSchool.value.id || 0,
            keyword: keyword.value
        })
        if (res.code === 1) {
            if (refresh) {
                houseList.value = res.data.list || []
            } else {
                houseList.value = [...houseList.value, ...(res.data.list || [])]
            }
            if ((res.data.list || []).length < 10) {
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

const setType = (type: string) => {
    houseType.value = type
    loadHouses(true)
}

const loadMore = () => loadHouses()

const onRefresh = () => {
    refreshing.value = true
    loadHouses(true)
}

const doSearch = () => loadHouses(true)

const getFirstImage = (images: any) => {
    if (typeof images === 'string') {
        try {
            const arr = JSON.parse(images)
            if (Array.isArray(arr)) return arr[0] || ''
            const parts = images.split(',').filter((s: string) => s)
            return parts[0] || ''
        } catch {
            const parts = images.split(',').filter((s: string) => s)
            return parts[0] || ''
        }
    }
    return images?.[0] || ''
}

const getTypeName = (type: string) => typeMap[type] || type

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

const goToDetail = (item: any) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/house/detail?id=${item.id}` })
}

const goToPublish = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/house/publish' })
}
</script>

<style lang="scss" scoped>
.house-page {
    min-height: 100vh;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
}

.header-section {
    background: #fff;
    padding: 20rpx 30rpx 0;
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.03);
    
    .search-box {
        margin-bottom: 20rpx;
        
        .search-input {
            background: #f5f5f5;
            height: 72rpx;
            border-radius: 36rpx;
            display: flex;
            align-items: center;
            padding: 0 24rpx;
            gap: 12rpx;
            
            input {
                flex: 1;
                font-size: 28rpx;
                color: #333;
            }
        }
    }
    
    .filter-bar {
        display: flex;
        justify-content: space-between;
        padding-bottom: 20rpx;
        
        .filter-item {
            padding: 10rpx 32rpx;
            background: #f8f8f8;
            border-radius: 30rpx;
            font-size: 26rpx;
            color: #666;
            transition: all 0.3s;
            
            &.active {
                background: #e8f5e9;
                color: #00c853;
                font-weight: bold;
            }
        }
    }
}

.content-scroll {
    flex: 1;
    height: 0;
}

.house-list {
    padding: 24rpx;
}

.house-card {
    background: #fff;
    border-radius: 20rpx;
    overflow: hidden;
    margin-bottom: 30rpx;
    box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.04);
    
    .image-wrapper {
        position: relative;
        height: 360rpx;
        width: 100%;
        
        .cover-img {
            width: 100%;
            height: 100%;
            display: block;
        }
        
        .type-badge {
            position: absolute;
            top: 20rpx;
            left: 20rpx;
            padding: 6rpx 16rpx;
            border-radius: 8rpx;
            font-size: 24rpx;
            font-weight: bold;
            color: #fff;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            
            &.RENT { background: #2196f3; }
            &.SHARE { background: #ff9800; }
            &.SUBLEASE { background: #9c27b0; }
        }
        
        .price-badge {
            position: absolute;
            bottom: 20rpx;
            left: 20rpx;
            color: #fff;
            text-shadow: 0 2rpx 4rpx rgba(0,0,0,0.5);
            
            .symbol { font-size: 28rpx; font-weight: bold; }
            .num { font-size: 40rpx; font-weight: bold; margin: 0 4rpx; }
            .unit { font-size: 24rpx; }
        }
    }
    
    .info-wrapper {
        padding: 24rpx;
        
        .title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 16rpx;
        }
        
        .meta-row {
            display: flex;
            align-items: center;
            margin-bottom: 16rpx;
            
            .meta-item {
                font-size: 26rpx;
                color: #666;
            }
            
            .divider {
                width: 2rpx;
                height: 20rpx;
                background: #ddd;
                margin: 0 16rpx;
            }
        }
        
        .tags-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12rpx;
            margin-bottom: 20rpx;
            
            .tag {
                font-size: 22rpx;
                color: #666;
                background: #f5f5f5;
                padding: 4rpx 12rpx;
                border-radius: 6rpx;
                
                &.highlight {
                    color: #00c853;
                    background: #e8f5e9;
                }
            }
        }
        
        .address-row {
            display: flex;
            align-items: center;
            margin-bottom: 24rpx;
            gap: 8rpx;
            
            .address {
                font-size: 24rpx;
                color: #999;
                flex: 1;
            }
        }
        
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1rpx solid #f8f8f8;
            padding-top: 20rpx;
            
            .user {
                display: flex;
                align-items: center;
                gap: 12rpx;
                
                .avatar {
                    width: 48rpx;
                    height: 48rpx;
                    border-radius: 50%;
                }
                
                .name {
                    font-size: 26rpx;
                    color: #333;
                }
            }
            
            .time {
                font-size: 22rpx;
                color: #ccc;
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
        color: #999;
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

.fab-btn {
    position: fixed;
    right: 30rpx;
    bottom: 120rpx;
    background: linear-gradient(135deg, #00c853, #69f0ae);
    padding: 20rpx 40rpx;
    border-radius: 50rpx;
    display: flex;
    align-items: center;
    gap: 10rpx;
    box-shadow: 0 8rpx 20rpx rgba(0, 200, 83, 0.4);
    z-index: 99;
    
    text {
        color: #fff;
        font-weight: bold;
        font-size: 28rpx;
    }
}

.text-ellipsis {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
</style>
