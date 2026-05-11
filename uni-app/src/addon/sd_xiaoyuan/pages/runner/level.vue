<template>
    <view class="runner-level-page">
        <view class="level-header">
            <view class="header-bg"></view>
            <view class="header-content">
                <view class="level-badge">
                    <text class="level-num">Lv.{{ levelInfo.level || 1 }}</text>
                </view>
                <text class="level-name">{{ levelInfo.level_name || '新手接单员' }}</text>
                <text class="commission">佣金比例：{{ levelInfo.commission_rate || 70 }}%</text>
            </view>
        </view>

        <view class="progress-card">
            <view class="progress-header">
                <text>升级进度</text>
                <text class="orders">已完成 {{ levelInfo.completed_orders || 0 }} 单</text>
            </view>
            <view class="progress-bar">
                <view class="progress-fill" :style="{ width: levelInfo.progress + '%' }"></view>
            </view>
            <view class="progress-footer" v-if="levelInfo.next_level">
                <text>距离 {{ levelInfo.next_level.name }} 还需 {{ levelInfo.orders_to_next }} 单</text>
            </view>
            <view class="progress-footer" v-else>
                <text>已达最高等级</text>
            </view>
        </view>

        <view class="level-list">
            <view class="section-title">等级权益</view>
            <view class="level-item" v-for="item in levels" :key="item.level" :class="{ current: item.level === levelInfo.level, locked: item.level > levelInfo.level }">
                <view class="level-icon">
                    <text class="lv">Lv.{{ item.level }}</text>
                </view>
                <view class="level-info">
                    <text class="name">{{ item.name }}</text>
                    <text class="desc">完成{{ item.min_orders }}单解锁</text>
                </view>
                <view class="level-rate">
                    <text class="rate">{{ item.commission_rate }}%</text>
                    <text class="label">佣金比例</text>
                </view>
            </view>
        </view>

        <view class="invite-section">
            <view class="section-title">邀请接单员</view>
            <view class="invite-card">
                <view class="invite-stats">
                    <view class="stat-item">
                        <text class="value">{{ inviteStats.total_invited || 0 }}</text>
                        <text class="label">邀请人数</text>
                    </view>
                    <view class="stat-item">
                        <text class="value">¥{{ inviteStats.total_reward || 0 }}</text>
                        <text class="label">累计奖励</text>
                    </view>
                </view>
                <button class="invite-btn" @click="goInvite">邀请好友成为接单员</button>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getRunnerLevels, getRunnerLevelInfo, getRunnerInviteStats } from '../../api/xiaoyuan'

const levelInfo = ref<any>({})
const levels = ref<any[]>([])
const inviteStats = ref<any>({})
const runnerId = ref(0)

onMounted(() => {
    // 从缓存获取接单员ID
    const runner = uni.getStorageSync('runnerInfo')
    if (runner && runner.id) {
        runnerId.value = runner.id
        loadData()
    }
})

const loadData = async () => {
    // 加载等级列表
    const levelsRes: any = await getRunnerLevels()
    if (levelsRes.code === 1) {
        levels.value = levelsRes.data
    }

    // 加载当前等级信息
    const infoRes: any = await getRunnerLevelInfo({ runner_id: runnerId.value })
    if (infoRes.code === 1) {
        levelInfo.value = infoRes.data
    }

    // 加载邀请统计
    const statsRes: any = await getRunnerInviteStats({ inviter_id: runnerId.value })
    if (statsRes.code === 1) {
        inviteStats.value = statsRes.data
    }
}

const goInvite = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/invite' })
}
</script>

<style lang="scss" scoped>
.runner-level-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.level-header {
    position: relative;
    height: 300rpx;
    
    .header-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 100%;
        background: linear-gradient(135deg, #32CD32, #CCFFCC);
    }
    
    .header-content {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 60rpx;
        color: #333;
        
        .level-badge {
            width: 120rpx;
            height: 120rpx;
            background: rgba(255,255,255,0.6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16rpx;
            
            .level-num {
                font-size: 30rpx;
                font-weight: bold;
                color: #333;
            }
        }
        
        .level-name {
            font-size: 28rpx;
            font-weight: bold;
            margin-bottom: 8rpx;
        }
        
        .commission {
            font-size: 26rpx;
            opacity: 0.9;
        }
    }
}

.progress-card {
    margin: -40rpx 20rpx 20rpx;
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    position: relative;
    
    .progress-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20rpx;
        
        text { font-size: 28rpx; color: #333; }
        .orders { color: #32CD32; }
    }
    
    .progress-bar {
        height: 16rpx;
        background: #f0f0f0;
        border-radius: 8rpx;
        overflow: hidden;
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #32CD32, #CCFFCC);
            border-radius: 8rpx;
            transition: width 0.3s;
        }
    }
    
    .progress-footer {
        margin-top: 16rpx;
        text-align: center;
        font-size: 24rpx;
        color: #999;
    }
}

.section-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    padding: 20rpx;
}

.level-list {
    background: #fff;
    margin: 0 20rpx 20rpx;
    border-radius: 16rpx;
    padding: 0 20rpx;
}

.level-item {
    display: flex;
    align-items: center;
    padding: 24rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child { border-bottom: none; }
    
    &.current {
        .level-icon { background: linear-gradient(135deg, #32CD32, #CCFFCC); }
        .name { color: #333; }
        
        .lv { color: #333 !important; }
    }
    
    &.locked {
        opacity: 0.5;
    }
    
    .level-icon {
        width: 80rpx;
        height: 80rpx;
        background: #e0e0e0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20rpx;
        
        .lv {
            font-size: 24rpx;
            color: #fff;
            font-weight: bold;
        }
    }
    
    .level-info {
        flex: 1;
        
        .name {
            display: block;
            font-size: 28rpx;
            color: #333;
            font-weight: bold;
        }
        
        .desc {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .level-rate {
        text-align: center;
        
        .rate {
            display: block;
            font-size: 28rpx;
            color: #000000;
            font-weight: bold;
        }
        
        .label {
            font-size: 22rpx;
            color: #999;
        }
    }
}

.invite-section {
    background: #fff;
    margin: 0 20rpx 20rpx;
    border-radius: 16rpx;
    padding: 0 20rpx 20rpx;
}

.invite-card {
    .invite-stats {
        display: flex;
        padding: 24rpx 0;
        
        .stat-item {
            flex: 1;
            text-align: center;
            
            .value {
                display: block;
                font-size: 40rpx;
                font-weight: bold;
                color: #000000;
            }
            
            .label {
                font-size: 24rpx;
                color: #999;
            }
        }
    }
    
    .invite-btn {
        width: 100%;
        height: 72rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
        font-size: 30rpx;
        border: none;
        border-radius: 44rpx;
        font-weight: bold;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    }
}
</style>
