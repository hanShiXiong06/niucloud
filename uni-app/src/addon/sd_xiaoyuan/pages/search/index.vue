<template>
    <view class="search-page">
        <view class="search-header" :style="{ paddingTop: headerPaddingTop, height: headerHeight, paddingRight: headerPaddingRight }">
            <view class="search-bar">
                <u-icon name="search" size="16" color="#999"></u-icon>
                <input 
                    class="search-input" 
                    v-model="keyword" 
                    placeholder="搜索订单/闲置/表白/失物..." 
                    confirm-type="search"
                    focus
                    @confirm="doSearch"
                />
                <view class="clear-btn" v-if="keyword" @click="keyword = ''">
                    <u-icon name="close-circle-fill" size="16" color="#ccc"></u-icon>
                </view>
            </view>
            <text class="cancel-btn" @click="goBack">取消</text>
        </view>

        <view class="search-tabs">
            <scroll-view scroll-x class="tabs-scroll">
                <view class="tabs-container">
                    <view class="tab-item" :class="{ active: currentTab === 'order' }" @click="switchTab('order')">
                        <text>跑腿订单</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'secondhand' }" @click="switchTab('secondhand')">
                        <text>闲置</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'confession' }" @click="switchTab('confession')">
                        <text>表白墙</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'lostfound' }" @click="switchTab('lostfound')">
                        <text>失物招领</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'community' }" @click="switchTab('community')">
                        <text>树洞</text>
                    </view>
                </view>
            </scroll-view>
        </view>

        <view class="hot-search" v-if="!hasSearched">
            <view class="section-title">热门搜索</view>
            <view class="hot-tags">
                <view class="hot-tag" v-for="(tag, index) in hotTags" :key="index" @click="keyword = tag; doSearch()">
                    <text>{{ tag }}</text>
                </view>
            </view>
        </view>

        <view class="result-list" v-if="hasSearched">
            <view class="result-item" v-for="item in resultList" :key="item.id + '-' + currentTab" @click="goToDetail(item)">
                <view class="result-type">
                    <text class="type-tag" :class="currentTab">{{ getTypeLabel() }}</text>
                </view>
                <view class="result-title">{{ getItemTitle(item) }}</view>
                <view class="result-desc">{{ getItemDesc(item) }}</view>
                <view class="result-footer">
                    <text class="result-price" v-if="getItemPrice(item)">¥{{ getItemPrice(item) }}</text>
                    <text class="result-time">{{ item.create_time }}</text>
                </view>
            </view>

            <view class="empty-result" v-if="resultList.length === 0 && !loading">
                <u-icon name="search" size="60" color="#ddd"></u-icon>
                <text>没有找到相关结果</text>
            </view>
            
            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" size="24"></u-loading-icon>
                <text>搜索中...</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, watch, computed } from 'vue'
import { getOrderHall, getSecondhandList, getConfessionList, getLostFoundList, getCommunityList } from '../../api/xiaoyuan'

const statusBarHeight = uni.getSystemInfoSync().statusBarHeight || 0
// #ifdef MP-WEIXIN
const menuButtonInfo = uni.getMenuButtonBoundingClientRect()
const navBarHeight = (menuButtonInfo.top - statusBarHeight) * 2 + menuButtonInfo.height
const headerPaddingTop = menuButtonInfo.top + 'px'
const headerHeight = menuButtonInfo.height + 'px'
const screenWidth = uni.getSystemInfoSync().windowWidth
const menuButtonRight = screenWidth - menuButtonInfo.right
const headerPaddingRight = (screenWidth - menuButtonInfo.left + menuButtonRight) + 'px'
// #endif
// #ifndef MP-WEIXIN
const navBarHeight = 44
const headerPaddingTop = statusBarHeight + 'px'
const headerHeight = '44px'
const headerPaddingRight = '24rpx'
// #endif

const keyword = ref('')
const currentTab = ref('order')
const hasSearched = ref(false)
const loading = ref(false)
const resultList = ref<any[]>([])

const hotTags = ['代取快递', '帮我买', '二手教材', '手机', '书籍', '表白', '钥匙', '校园卡']

const switchTab = (tab: string) => {
    currentTab.value = tab
    if (keyword.value.trim()) {
        doSearch()
    }
}

const getTypeLabel = () => {
    const labels: any = {
        order: '跑腿',
        secondhand: '闲置',
        confession: '表白',
        lostfound: '失物',
        community: '树洞'
    }
    return labels[currentTab.value] || ''
}

const getItemTitle = (item: any) => {
    switch (currentTab.value) {
        case 'order':
            return item.task_desc || item.order_no || '跑腿订单'
        case 'secondhand':
            return item.title || '闲置物品'
        case 'confession':
            return item.target_name ? `表白 ${item.target_name}` : '匿名表白'
        case 'lostfound':
            return item.title || (item.type === 1 ? '寻物启事' : '失物招领')
        case 'community':
            return item.title || '树洞帖子'
        default:
            return item.title || ''
    }
}

const getItemDesc = (item: any) => {
    switch (currentTab.value) {
        case 'order':
            return `${item.pickup_address || ''} → ${item.receive_address || ''}`
        case 'secondhand':
            return item.content || ''
        case 'confession':
            return item.content || ''
        case 'lostfound':
            return item.content || ''
        case 'community':
            return item.content || ''
        default:
            return item.content || ''
    }
}

const getItemPrice = (item: any) => {
    switch (currentTab.value) {
        case 'order':
            return item.total_fee
        case 'secondhand':
            return item.price
        case 'lostfound':
            return item.reward
        default:
            return null
    }
}

const doSearch = async () => {
    if (!keyword.value.trim()) return
    hasSearched.value = true
    loading.value = true
    resultList.value = []

    try {
        const currentSchool = uni.getStorageSync('current_school')
        const schoolId = currentSchool?.id || 0
        
        const params: any = {
            keyword: keyword.value,
            page: 1,
            limit: 20,
            school_id: schoolId
        }
        
        let res: any = null
        
        switch (currentTab.value) {
            case 'order':
                res = await getOrderHall(params)
                break
            case 'secondhand':
                params.status = 1
                res = await getSecondhandList(params)
                break
            case 'confession':
                params.status = 1
                res = await getConfessionList(params)
                break
            case 'lostfound':
                params.status = 1
                res = await getLostFoundList(params)
                break
            case 'community':
                params.status = 1
                res = await getCommunityList(params)
                break
        }
        
        console.log('搜索结果:', res)
        
        if (res && res.code === 1) {
            resultList.value = res.data?.list || res.data?.data || res.data || []
        }
    } catch (e) {
        console.error('搜索失败:', e)
    }
    loading.value = false
}

const goToDetail = (item: any) => {
    let url = ''
    switch (currentTab.value) {
        case 'order':
            url = `/addon/sd_xiaoyuan/pages/order/detail?id=${item.id}`
            break
        case 'secondhand':
            url = `/addon/sd_xiaoyuan/pages/secondhand/detail?id=${item.id}`
            break
        case 'confession':
            url = `/addon/sd_xiaoyuan/pages/confession/detail?id=${item.id}`
            break
        case 'lostfound':
            url = `/addon/sd_xiaoyuan/pages/lost_found/detail?id=${item.id}`
            break
        case 'community':
            url = `/addon/sd_xiaoyuan/pages/community/detail?id=${item.id}`
            break
    }
    if (url) {
        uni.navigateTo({ url })
    }
}

const goBack = () => {
    uni.navigateBack()
}
</script>

<style lang="scss" scoped>
.search-page {
    min-height: 100vh;
    background: #f7f7f7;
}

.search-header {
    display: flex;
    align-items: center;
    padding: 20rpx 24rpx;
    background: #fff;
    position: sticky;
    top: 0;
    z-index: 10;

    .search-bar {
        flex: 1;
        display: flex;
        align-items: center;
        background: #f5f5f5;
        border-radius: 32rpx;
        padding: 0 24rpx;
        height: 68rpx;


        .search-input {
            flex: 1;
            margin-left: 12rpx;
            font-size: 28rpx;
            color: #333;
        }

        .clear-btn {
            padding: 10rpx;
        }
    }

    .cancel-btn {
        margin-left: 20rpx;
        font-size: 28rpx;
        color: #666;
    }
}

.search-tabs {
    background: #fff;
    border-bottom: 1rpx solid #f0f0f0;
    
    .tabs-scroll {
        white-space: nowrap;
        
        .tabs-container {
            display: flex;
            padding: 0 20rpx;
        }
    }

    .tab-item {
        flex-shrink: 0;
        text-align: center;
        padding: 24rpx 32rpx;
        position: relative;
        white-space: nowrap;

        text {
            font-size: 28rpx;
            color: #666;
        }

        &.active {
            text {
                color: #333;
                font-weight: bold;
            }

            &::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 48rpx;
                height: 6rpx;
                background: #00c853;
                border-radius: 3rpx;
            }
        }
    }
}

.hot-search {
    padding: 30rpx;

    .section-title {
        font-size: 28rpx;
        color: #333;
        font-weight: bold;
        margin-bottom: 20rpx;
    }

    .hot-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 16rpx;

        .hot-tag {
            padding: 12rpx 28rpx;
            background: #fff;
            border-radius: 30rpx;
            
            text {
                font-size: 26rpx;
                color: #666;
            }
        }
    }
}

.result-list {
    padding: 20rpx;
}

.result-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 16rpx;

    &:active {
        background: #f5f5f5;
    }
    
    .result-type {
        margin-bottom: 12rpx;
        
        .type-tag {
            display: inline-block;
            padding: 4rpx 16rpx;
            border-radius: 8rpx;
            font-size: 22rpx;
            
            &.order {
                background: #e6f7ff;
                color: #1890ff;
            }
            &.secondhand {
                background: #fff7e6;
                color: #fa8c16;
            }
            &.confession {
                background: #fff0f6;
                color: #eb2f96;
            }
            &.lostfound {
                background: #f6ffed;
                color: #52c41a;
            }
            &.community {
                background: #f9f0ff;
                color: #722ed1;
            }
        }
    }

    .result-title {
        font-size: 28rpx;
        color: #333;
        font-weight: bold;
        margin-bottom: 8rpx;
    }

    .result-desc {
        font-size: 26rpx;
        color: #999;
        margin-bottom: 12rpx;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .result-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;

        .result-price {
            font-size: 28rpx;
            color: #ff6b00;
            font-weight: bold;
        }

        .result-time {
            font-size: 24rpx;
            color: #ccc;
        }
    }
}

.loading-more {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30rpx;
    
    text {
        font-size: 24rpx;
        color: #999;
        margin-left: 12rpx;
    }
}

.empty-result {
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
</style>
