<template>
    <view class="team-page">
        <view class="page-header">
            <view class="header-bg"></view>
            <view class="header-content">
                <text class="title">我的团队</text>
                <text class="desc">查看您邀请的好友</text>
            </view>
        </view>

        <view class="stat-card">
            <view class="stat-item">
                <text class="value">{{ teamStat.total || 0 }}</text>
                <text class="label">团队总人数</text>
            </view>
            <view class="stat-item">
                <text class="value">{{ teamStat.level1 || 0 }}</text>
                <text class="label">一级好友</text>
            </view>
            <view class="stat-item">
                <text class="value">{{ teamStat.level2 || 0 }}</text>
                <text class="label">二级好友</text>
            </view>
        </view>

        <view class="tabs">
            <view class="tab-item" :class="{ active: currentTab === 1 }" @click="switchTab(1)">一级好友</view>
            <view class="tab-item" :class="{ active: currentTab === 2 }" @click="switchTab(2)">二级好友</view>
        </view>

        <view class="team-list">
            <view class="team-item" v-for="item in teamList" :key="item.member_id">
                <image class="avatar" :src="img(item.headimg || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                <view class="info">
                    <text class="nickname">{{ item.nickname || '用户' }}</text>
                    <text class="time">加入时间：{{ item.create_time || '-' }}</text>
                </view>
                <view class="order-count">
                    <text class="count">{{ item.order_count || 0 }}</text>
                    <text class="label">订单数</text>
                </view>
            </view>

            <view class="empty" v-if="teamList.length === 0 && !loading">
                <u-icon name="account" size="120" color="#ccc"></u-icon>
                <text>暂无团队成员</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && teamList.length > 0">没有更多了</view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import { getMyTeam, getTeamStat } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const currentTab = ref(1)
const teamList = ref<any[]>([])
const teamStat = ref<any>({})
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 20

onShow(() => {
    loadStat()
    loadTeam(true)
})

const switchTab = (tab: number) => {
    currentTab.value = tab
    loadTeam(true)
}

const loadStat = async () => {
    const res: any = await getTeamStat()
    if (res.code === 1 && res.data) {
        teamStat.value = res.data
    }
}

const loadTeam = async (refresh = false) => {
    if (loading.value) return
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    if (!hasMore.value) return

    loading.value = true
    const res: any = await getMyTeam({ level: currentTab.value, page: page.value, limit })
    loading.value = false

    if (res.code === 1) {
        const list = res.data?.list || []
        if (refresh) {
            teamList.value = list
        } else {
            teamList.value = [...teamList.value, ...list]
        }
        if (list.length < limit) {
            hasMore.value = false
        } else {
            page.value++
        }
    }
}

const loadMore = () => {
    if (!loading.value && hasMore.value) loadTeam()
}

onReachBottom(() => {
    loadMore()
})
</script>

<style lang="scss" scoped>
.team-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.page-header {
    position: relative;
    height: 230rpx;
    overflow: hidden;
    
    .header-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, #ff9500 0%, #ffb347 100%);
    }
    
    .header-content {
        position: relative;
        z-index: 1;
        padding: 10rpx 40rpx;
        
        .title {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
            color: #fff;
            margin-bottom: 16rpx;
        }
        
        .desc {
            font-size: 28rpx;
            color: rgba(255, 255, 255, 0.8);
        }
    }
}

.stat-card {
    display: flex;
    background: #fff;
    margin: -30rpx 24rpx 24rpx;
    border-radius: 16rpx;
    padding: 12rpx;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.08);
    position: relative;
    z-index: 2;
    
    .stat-item {
        flex: 1;
        text-align: center;
        
        .value {
            display: block;
            font-size: 44rpx;
            font-weight: bold;
            color: #333;
        }
        
        .label {
            font-size: 24rpx;
            color: #999;
            margin-top: 8rpx;
        }
    }
}

.tabs {
    display: flex;
    background: #fff;
    margin: 0 24rpx 24rpx;
    border-radius: 16rpx;
    padding: 2rpx;
    
    .tab-item {
        flex: 1;
        text-align: center;
        padding: 20rpx 0;
        font-size: 28rpx;
        color: #666;
        border-radius: 12rpx;
        transition: all 0.3s;
        
        &.active {
            background: linear-gradient(135deg, #ff9500 0%, #ffb347 100%);
            color: #fff;
            font-weight: bold;
        }
    }
}

.team-list {
    height: calc(100vh - 500rpx);
    padding: 0 24rpx;
}

.team-item {
    display: flex;
    align-items: center;
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 16rpx;
    
    .avatar {
        width: 88rpx;
        height: 88rpx;
        border-radius: 50%;
        margin-right: 20rpx;
    }
    
    .info {
        flex: 1;
        
        .nickname {
            display: block;
            font-size: 30rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 8rpx;
        }
        
        .time {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .order-count {
        text-align: center;
        
        .count {
            display: block;
            font-size: 36rpx;
            font-weight: bold;
            color: #ff9500;
        }
        
        .label {
            font-size: 22rpx;
            color: #999;
        }
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 100rpx 0;
    
    text {
        margin-top: 20rpx;
        font-size: 28rpx;
        color: #999;
    }
}

.loading-more {
    display: flex;
    justify-content: center;
    padding: 30rpx 0;
}

.no-more {
    text-align: center;
    padding: 30rpx 0;
    font-size: 24rpx;
    color: #999;
}
</style>
