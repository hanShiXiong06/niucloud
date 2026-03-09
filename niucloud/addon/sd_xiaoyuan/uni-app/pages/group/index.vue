<template>
    <view class="group-page">
        <!-- 头部区域 -->
        <view class="header-section">
            <view class="header-bg"></view>
            <view class="header-content">
                <view class="title-area">
                    <text class="main-title">拼单好饭</text>
                    <text class="sub-title">领券拼单 · 运费全免</text>
                </view>
                <view class="stats-card">
                    <view class="stat-item">
                        <text class="num">{{ stats.completed || 0 }}</text>
                        <text class="label">已成团</text>
                    </view>
                    <view class="divider"></view>
                    <view class="stat-item">
                        <text class="num">{{ stats.total || 0 }}</text>
                        <text class="label">累计发起</text>
                    </view>
                    <view class="divider"></view>
                    <view class="stat-item">
                        <text class="num">{{ stats.today || 0 }}</text>
                        <text class="label">今日进行</text>
                    </view>
                </view>
            </view>
        </view>

        <!-- 分类Tab -->
        <view class="category-tabs">
            <view class="tab-item" :class="{ active: currentTab === '' }" @click="switchTab('')">
                <text>全部</text>
            </view>
            <view class="tab-item" :class="{ active: currentTab === 'TEA' }" @click="switchTab('TEA')">
                <text>奶茶</text>
            </view>
            <view class="tab-item" :class="{ active: currentTab === 'FOOD' }" @click="switchTab('FOOD')">
                <text>外卖</text>
            </view>
            <view class="tab-item" :class="{ active: currentTab === 'FRUIT' }" @click="switchTab('FRUIT')">
                <text>水果</text>
            </view>
            <view class="tab-item" :class="{ active: currentTab === 'RIDE' }" @click="switchTab('RIDE')">
                <text>拼车</text>
            </view>
        </view>

        <!-- 拼单列表 -->
        <view class="group-list">
                <view class="group-card" v-for="item in groupList" :key="item.id" @click="goDetail(item.id)">
                    <view class="card-status" :class="'status-' + item.status">
                        {{ statusMap[item.status] || '进行中' }}
                    </view>
                    
                    <view class="card-header">
                        <image class="shop-icon" :src="item.shop_logo ? img(item.shop_logo) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiByeD0iOCIgZmlsbD0iI0Y1RjVGNSIvPgo8cGF0aCBkPSJNMTAgMTBIMzBWMzBIMTBWMTBaIiBmaWxsPSIjQ0NDIi8+CjxwYXRoIGQ9Ik0xNSAxNUgyNVYxOEgxNVYxNVoiIGZpbGw9IiNGNUY1RjUiLz4KPC9zdmc+'" mode="aspectFill"></image>
                        <view class="shop-info">
                            <text class="shop-name text-ellipsis">{{ item.shop_name || '拼单' }}</text>
                            <view class="tags">
                                <text class="tag red" v-if="item.group_type === 'TEA'">免运费</text>
                                <text class="tag orange" v-if="item.discount">{{ item.discount }}折起</text>
                            </view>
                        </view>
                    </view>

                    <view class="card-body">
                        <view class="info-row">
                            <text class="label">目标：</text>
                            <text class="value">{{ item.title }}</text>
                        </view>
                        <view class="info-row">
                            <text class="label">当前：</text>
                            <view class="progress-wrap">
                                <view class="progress-bar">
                                    <view class="progress-inner" :style="{ width: getProgress(item) + '%' }"></view>
                                </view>
                                <text class="progress-text">{{ item.current_members }}/{{ item.max_members }}人</text>
                            </view>
                        </view>
                    </view>

                    <view class="card-footer">
                        <view class="members-stack">
                            <image 
                                class="avatar" 
                                v-for="(url, idx) in getMemberAvatars(item).slice(0, 4)" 
                                :key="idx" 
                                :src="img(url)"
                                mode="aspectFill"
                                :style="getAvatarStyle(Number(idx))"
                            ></image>
                            <view class="more-members" v-if="item.current_members > 4">...</view>
                        </view>
                        
                        <button class="join-btn" :disabled="item.status !== 0 && item.status !== 10" @click.stop="goDetail(item.id)">
                            <view v-if="item.status === 0 || item.status === 10" class="btn-content">
                                <text class="price">¥{{ item.per_price }}/人</text>
                                <text class="status">差{{ item.max_members - item.current_members }}人成团</text>
                            </view>
                            <text v-else>查看详情</text>
                        </button>
                    </view>
                </view>

            <view class="empty-state" v-if="groupList.length === 0 && !loading">
                <u-icon name="shopping-cart" size="80" color="#ff9800"></u-icon>
                <text class="empty-text">这里空空如也，快去发起拼单吧~</text>
            </view>

            <view class="load-more" v-if="loading">
                <u-loading-icon mode="circle" color="#ff9800"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && groupList.length > 0">
                <text>—— 到底啦 ——</text>
            </view>
        </view>

        <!-- 发起按钮 -->
        <view class="fab-btn" @click="goCreate">
            <view class="icon-box">
                <u-icon name="plus" size="24" color="#fff"></u-icon>
            </view>
            <text>发起拼单</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { onPullDownRefresh, onReachBottom, onShow } from '@dcloudio/uni-app'
import { getGroupOrderList, getGroupOrderStats } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const currentTab = ref('')
const groupList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const page = ref(1)
const hasMore = ref(true)
const stats = ref({
    total: 0,
    completed: 0,
    today: 0
})

const statusMap: Record<number, string> = {
    0: '拼单中',
    10: '拼单中',
    20: '配送中',
    30: '已完成',
    90: '已取消',
    91: '拼单失败'
}

const switchTab = (type: string) => {
    currentTab.value = type
    page.value = 1
    groupList.value = []
    hasMore.value = true
    loadList()
}

const loadList = async (refresh = false) => {
    if (refresh) { page.value = 1; hasMore.value = true; groupList.value = [] }
    if (loading.value || !hasMore.value) return
    loading.value = true
    try {
        const params: any = { page: page.value, limit: 10 }
        if (currentTab.value) params.group_type = currentTab.value
        
        // 获取当前选择的学校
        const currentSchool = uni.getStorageSync('current_school')
        if (currentSchool?.id) {
            params.school_id = currentSchool.id
        }

        const res: any = await getGroupOrderList(params)
        if (res.code === 1 && res.data) {
            const list = res.data.list || res.data.data || []
            if (refresh || page.value === 1) {
                groupList.value = list
            } else {
                groupList.value = [...groupList.value, ...list]
            }
            hasMore.value = list.length >= 10
            if (list.length >= 10) page.value++
        }
    } catch (e) {
        console.error('加载拼单列表失败:', e)
    } finally {
        loading.value = false
    }
}

const loadMore = () => {
    if (hasMore.value && !loading.value) {
        loadList()
    }
}

const onRefresh = () => {
    isRefreshing.value = true
    page.value = 1
    hasMore.value = true
    loadList(true).then(() => {
        isRefreshing.value = false
    })
}

const getProgress = (item: any) => {
    if (!item.max_members) return 0
    return Math.min(100, Math.floor((item.current_members / item.max_members) * 100))
}

const getMemberAvatars = (item: any) => {
    // 直接使用发布者头像
    if (item.member_headimg) {
        return [item.member_headimg]
    }
    // 如果有参与成员头像列表，使用列表
    if (item.member_avatars) {
        try {
            return JSON.parse(item.member_avatars)
        } catch {
            return item.member_avatars.split(',')
        }
    }
    // 使用简单的SVG默认头像
    return ['data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNGNUY1RjUiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNSIgcj0iNiIgZmlsbD0iI0NDQyIvPgo8cGF0aCBkPSJNOCAyOEM4IDI1IDEwIDIzIDEzIDIzSDI3QzMwIDIzIDMyIDI1IDMyIDI4VjMwQzMyIDMyIDMwIDM0IDI3IDM0SDEzQzEwIDM0IDggMzIgOCAzMFY4WiIgZmlsbD0iI0NDQyIvPgo8L3N2Zz4K']
}

const getAvatarStyle = (index: number) => {
    return {
        zIndex: 5 - index,
        marginLeft: index > 0 ? '-16rpx' : '0'
    }
}

const goDetail = (id: number) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/group/detail?id=${id}` })
}

const goCreate = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/group/create' })
}

const loadStats = async () => {
    const res: any = await getGroupOrderStats()
    if (res.code === 1 && res.data) {
        stats.value = res.data
    }
}

onMounted(() => {
    loadList()
    loadStats()
})

onShow(() => {
    loadList(true)
    loadStats()
})
</script>

<style lang="scss" scoped>
.group-page {
    min-height: 100vh;
    background: #f8f8f8;
    display: flex;
    flex-direction: column;
}

.header-section {
    position: relative;
    padding-bottom: 60rpx;
    background: linear-gradient(135deg, #ff9800, #ff5722);
    

    .header-content {
        padding: 60rpx 30rpx 20rpx;
        color: #fff;
        
        .title-area {
            margin-bottom: 20rpx;
            
            .main-title {
                display: block;
                font-size: 40rpx;
                font-weight: bold;
                margin-bottom: 6rpx;
            }
            
            .sub-title {
                font-size: 24rpx;
                opacity: 0.9;
            }
        }
        
        .stats-card {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 16rpx;
            padding: 20rpx 24rpx;
            display: flex;
            align-items: center;
            justify-content: space-around;
            position: relative;
            z-index: 10;
            
            .stat-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                
                .num {
                    font-size: 28rpx;
                    font-weight: bold;
                    margin-bottom: 2rpx;
                }
                
                .label {
                    font-size: 20rpx;
                    opacity: 0.8;
                }
            }
            
            .divider {
                width: 1rpx;
                height: 24rpx;
                background: rgba(255,255,255,0.3);
            }
        }
    }
}

.category-tabs {
    display: flex;
    justify-content: space-around;position: relative;z-index: 22;
    padding: 30rpx 20rpx 20rpx;margin-top: -50rpx;border-radius: 50rpx 50rpx 0 0;
    background: #f8f8f8;
    position: relative;
    z-index: 2;
    
    .tab-item {
        padding: 12rpx 30rpx;
        background: #fff;
        border-radius: 30rpx;
        font-size: 26rpx;
        color: #666;
        transition: all 0.3s;
        box-shadow: 0 2rpx 8rpx rgba(0,0,0,0.02);
        
        &.active {
            background: #ff5722;
            color: #fff;
            font-weight: bold;
            box-shadow: 0 4rpx 12rpx rgba(255,87,34,0.3);
        }
    }
}

.group-list {
    padding: 20rpx 24rpx 120rpx;
}

.group-card {
    background: #fff;
    border-radius: 24rpx;
    padding: 30rpx;
    margin-bottom: 24rpx;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.04);
    
    .card-status {
        position: absolute;
        top: 0;
        right: 0;
        padding: 8rpx 20rpx;
        background: #fff3e0;
        color: #ff9800;
        font-size: 22rpx;
        border-bottom-left-radius: 20rpx;
        font-weight: bold;
        
        &.status-30 {
            background: #f5f5f5;
            color: #999;
        }
    }
    
    .card-header {
        display: flex;
        align-items: center;
        margin-bottom: 24rpx;
        
        .shop-icon {
            width: 80rpx;
            height: 80rpx;
            border-radius: 12rpx;
            margin-right: 20rpx;
            background: #f8f8f8;
        }
        
        .shop-info {
            flex: 1;
            
            .shop-name {
                font-size: 30rpx;
                font-weight: bold;
                color: #333;
                margin-bottom: 8rpx;
            }
            
            .tags {
                display: flex;
                gap: 10rpx;
                
                .tag {
                    font-size: 20rpx;
                    padding: 2rpx 8rpx;
                    border-radius: 6rpx;
                    
                    &.red { background: #fff1f0; color: #f5222d; }
                    &.orange { background: #fff7e6; color: #fa8c16; }
                }
            }
        }
    }
    
    .card-body {
        margin-bottom: 24rpx;
        background: #fafafa;
        padding: 20rpx;
        border-radius: 12rpx;
        
        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 12rpx;
            
            &:last-child { margin-bottom: 0; }
            
            .label {
                font-size: 24rpx;
                color: #999;
                width: 80rpx;
            }
            
            .value {
                font-size: 26rpx;
                color: #333;
                flex: 1;
            }
            
            .progress-wrap {
                flex: 1;
                display: flex;
                align-items: center;
                gap: 12rpx;
                
                .progress-bar {
                    flex: 1;
                    height: 12rpx;
                    background: #eee;
                    border-radius: 6rpx;
                    overflow: hidden;
                    
                    .progress-inner {
                        height: 100%;
                        background: linear-gradient(90deg, #ff9800, #ff5722);
                        border-radius: 6rpx;
                    }
                }
                
                .progress-text {
                    font-size: 22rpx;
                    color: #ff5722;
                    font-weight: bold;
                }
            }
        }
    }
    
    .card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        
        .members-stack {
            display: flex;
            align-items: center;
            padding-left: 10rpx;
            
            .avatar {
                width: 56rpx;
                height: 56rpx;
                border-radius: 50%;
                border: 4rpx solid #fff;
            }
            
            .more-members {
                margin-left: 10rpx;
                color: #999;
                font-size: 24rpx;
            }
        }
        
        .join-btn {
            margin: 0;
            padding: 0 24rpx;
            height: 64rpx;
            background: linear-gradient(135deg, #ff9800, #ff5722);
            color: #fff;
            font-size: 24rpx;
            font-weight: bold;
            border-radius: 32rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            
            .btn-content {
                display: flex;
                flex-direction: column;
                align-items: center;
                line-height: 1.2;
                
                .price {
                    font-size: 26rpx;
                    font-weight: bold;
                }
                
                .status {
                    font-size: 20rpx;
                    opacity: 0.9;
                }
            }
            
            &[disabled] {
                background: #e0e0e0;
                color: #999;
            }
        }
    }
}

.fab-btn {
    position: fixed;
    right: 30rpx;
    bottom: 120rpx;
    background: linear-gradient(135deg, #ff9800, #ff5722);
    padding: 16rpx 40rpx 16rpx 30rpx;
    border-radius: 50rpx;
    display: flex;
    align-items: center;
    gap: 12rpx;
    box-shadow: 0 8rpx 20rpx rgba(255, 87, 34, 0.4);
    z-index: 99;
    
    .icon-box {
        width: 48rpx;
        height: 48rpx;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    text {
        font-size: 28rpx;
        color: #fff;
        font-weight: bold;
    }
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    .empty-text {
        color: #999;
        font-size: 26rpx;
        margin-top: 20rpx;
    }
}

.load-more, .no-more {
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
</style>
