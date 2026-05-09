<template>
    <view class="search-page">
        <view class="search-header">
            <view class="search-bar">
                <u-icon name="search" size="16" color="#999"></u-icon>
                <input 
                    class="search-input" 
                    v-model="keyword" 
                    placeholder="搜索订单任务..." 
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
                    <view class="tab-item" :class="{ active: currentTab === 'all' }" @click="switchTab('all')">
                        <text>全部</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'EXPRESS' }" @click="switchTab('EXPRESS')">
                        <text>代取快递</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'BUY' }" @click="switchTab('BUY')">
                        <text>代买</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'ERRAND' }" @click="switchTab('ERRAND')">
                        <text>跑腿</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'PRINT' }" @click="switchTab('PRINT')">
                        <text>代打印</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'TRASH' }" @click="switchTab('TRASH')">
                        <text>扔垃圾</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'CARRY' }" @click="switchTab('CARRY')">
                        <text>帮搬运</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'CLEAN' }" @click="switchTab('CLEAN')">
                        <text>代清洁</text>
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 'HELP' }" @click="switchTab('HELP')">
                        <text>帮帮忙</text>
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
            <view class="result-item" v-for="item in resultList" :key="item.id" @click="goToDetail(item)">
                <view class="result-type">
                    <text class="type-tag">{{ orderTypeMap[item.task_type] || item.task_type }}</text>
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
import { ref, watch } from 'vue'
import { getOrderHall } from '../../api/xiaoyuan'

const keyword = ref('')
const currentTab = ref('all')
const hasSearched = ref(false)
const loading = ref(false)
const resultList = ref<any[]>([])

const hotTags = ['代取快递', '帮我买', '代打印', '扔垃圾', '帮搬运']

const orderTypeMap: any = {
    'EXPRESS': '代取快递',
    'BUY': '代买',
    'ERRAND': '跑腿',
    'QUEUE': '代排队',
    'PRINT': '代打印',
    'SEAT': '代占座',
    'CLEAN': '代清洁',
    'TRASH': '扔垃圾',
    'CARRY': '帮搬运',
    'HELP': '帮帮忙'
}

const switchTab = (tab: string) => {
    currentTab.value = tab
    if (keyword.value.trim()) {
        doSearch()
    }
}

const getTypeLabel = () => {
    return orderTypeMap[currentTab.value] || '全部'
}

const getItemTitle = (item: any) => {
    return item.task_desc || item.order_no || '跑腿订单'
}

const getItemDesc = (item: any) => {
    const from = item.pickup_address || item.from_address || ''
    const to = item.receive_address || item.to_address || ''
    return from && to ? `${from} → ${to}` : (from || to || '')
}

const getItemPrice = (item: any) => {
    return item.total_fee || item.fee || 0
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
        
        // 如果不是全部，添加任务类型筛选
        if (currentTab.value !== 'all') {
            params.task_type = currentTab.value
        }
        
        const res: any = await getOrderHall(params)
        
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
    const url = `/addon/sd_xiaoyuan/pages/order/detail?id=${item.id}`
    uni.navigateTo({ url })
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
            background: #e6f7ff;
            color: #1890ff;
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
