<template>
    <view class="school-select-page">
        <!-- 顶部导航 -->
        <view class="navbar" :style="{ paddingTop: statusBarHeight + 'px' }">
            <view class="navbar-left" @click="goBack">
                <u-icon name="arrow-left" size="20" color="#333"></u-icon>
            </view>
            <view class="navbar-title">选择一个学校</view>
            <view class="navbar-right"></view>
        </view>

        <!-- 搜索框 -->
        <view class="search-box">
            <view class="search-input">
                <u-icon name="search" size="16" color="#999"></u-icon>
                <input type="text" v-model="keyword" placeholder="输入关键字搜索" @input="onSearch" />
            </view>
            <view class="search-btn" @click="doSearch">搜索</view>
        </view>

        <!-- 学校列表 -->
        <scroll-view scroll-y class="school-list">
            <view 
                class="school-item" 
                v-for="(item, index) in filteredList" 
                :key="item.id"
                @click="selectSchool(item)"
            >
                <view class="school-logo">
                    <image v-if="item.logo" :src="img(item.logo)" mode="aspectFit"></image>
                    <view v-else class="default-logo">
                        <u-icon name="home" size="30" color="#999"></u-icon>
                    </view>
                </view>
                <view class="school-info">
                    <text class="school-name">{{ item.name }}</text>
                    <view class="school-tags">
                        <view class="tag area" v-if="item.area">{{ item.area }}</view>
                        <text class="distance" v-if="item.distance">{{ item.distance }}公里</text>
                    </view>
                </view>
            </view>

            <view class="empty" v-if="filteredList.length === 0 && !loading">
                <u-icon name="search" size="60" color="#ccc"></u-icon>
                <text>未找到相关学校</text>
            </view>

            <view class="loading" v-if="loading">
                <u-loading-icon mode="circle" color="#52c41a"></u-loading-icon>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { getSchoolList } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const keyword = ref('')
const loading = ref(false)
const schoolList = ref<any[]>([])

onMounted(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    loadSchools()
})

const filteredList = computed(() => {
    if (!keyword.value) return schoolList.value
    return schoolList.value.filter(item => 
        item.name.includes(keyword.value) || 
        (item.area && item.area.includes(keyword.value))
    )
})

const loadSchools = async () => {
    loading.value = true
    try {
        const res: any = await getSchoolList()
        if (res.code === 1) {
            schoolList.value = res.data.list || res.data || []
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const onSearch = () => {
    // 实时搜索，computed已处理
}

const doSearch = () => {
    // 点击搜索按钮
}

const selectSchool = (item: any) => {
    uni.setStorageSync('current_school', item)
    // 返回上一页并刷新
    const pages = getCurrentPages()
    if (pages.length > 1) {
        uni.navigateBack()
    } else {
        uni.reLaunch({ url: '/addon/sd_xiaoyuan/pages/index/index' })
    }
}

const goBack = () => {
    uni.navigateBack()
}
</script>

<style lang="scss" scoped>
.school-select-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30rpx;
    height: 88rpx;
    background: #fff;
    
    .navbar-left, .navbar-right {
        width: 60rpx;
    }
    
    .navbar-title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
    }
}

.search-box {
    display: flex;
    align-items: center;
    padding: 20rpx 30rpx;
    background: #fff;
    border-bottom: 1rpx solid #f0f0f0;
    
    .search-input {
        flex: 1;
        display: flex;
        align-items: center;
        background: #f5f5f5;
        border-radius: 16rpx;
        padding: 16rpx 24rpx;
        
        input {
            flex: 1;
            margin-left: 16rpx;
            font-size: 28rpx;
        }
    }
    
    .search-btn {
        margin-left: 20rpx;
        padding: 16rpx 32rpx;
        background: linear-gradient(135deg, #52c41a, #73d13d);
        color: #fff;
        font-size: 28rpx;
        border-radius: 16rpx;
    }
}

.school-list {
    height: calc(100vh - 200rpx);width: auto;
    padding: 20rpx;
}

.school-item {
    display: flex;
    align-items: center;
    background: #fff;
    padding: 30rpx;
    border-radius: 16rpx;
    margin-bottom: 20rpx;
    
    &:active {
        background: #f9f9f9;
    }
}

.school-logo {
    width: 100rpx;
    height: 100rpx;
    margin-right: 24rpx;
    flex-shrink: 0;
    
    image {
        width: 100%;
        height: 100%;
        border-radius: 50%;
    }
    
    .default-logo {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.school-info {
    flex: 1;
    
    .school-name {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
        display: block;
        margin-bottom: 12rpx;
    }
    
    .school-tags {
        display: flex;
        align-items: center;
        
        .tag {
            padding: 6rpx 16rpx;
            border-radius: 6rpx;
            font-size: 22rpx;
            margin-right: 16rpx;
            
            &.area {
                background: #e6f7e6;
                color: #52c41a;
            }
        }
        
        .distance {
            font-size: 24rpx;
            color: #999;
        }
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 200rpx;
    
    text {
        margin-top: 20rpx;
        font-size: 28rpx;
        color: #999;
    }
}

.loading {
    display: flex;
    justify-content: center;
    padding: 40rpx;
}
</style>
