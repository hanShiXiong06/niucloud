<template>
    <view class="runner-invite-page">
        <view class="invite-header">
            <view class="header-bg"></view>
            <view class="header-content">
                <text class="title">邀请好友成为接单员</text>
                <text class="desc">好友审核通过后，您可获得奖励</text>
            </view>
        </view>

        <view class="invite-card">
            <view class="invite-code">
                <text class="label">我的邀请码</text>
                <text class="code">{{ inviteCode }}</text>
                <button class="copy-btn" @click="copyCode">复制</button>
            </view>
        </view>

        <view class="stats-card">
            <view class="stat-item">
                <text class="value">{{ stats.total_invited || 0 }}</text>
                <text class="label">邀请人数</text>
            </view>
            <view class="stat-item">
                <text class="value">¥{{ stats.total_reward || 0 }}</text>
                <text class="label">累计奖励</text>
            </view>
        </view>

        <view class="invite-list">
            <view class="section-header">
                <text class="title">邀请记录</text>
            </view>
            <view class="list-content">
                <view class="invite-item" v-for="item in invitedList" :key="item.id">
                    <view class="user-info">
                        <image class="avatar" :src="img(item.avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                        <view class="info">
                            <text class="name">{{ item.real_name || '接单员' }}</text>
                            <text class="time">{{ formatTime(item.create_time) }}</text>
                        </view>
                    </view>
                    <view class="status">
                        <text class="status-tag" :class="getStatusClass(item.status)">{{ getStatusText(item.status) }}</text>
                    </view>
                </view>
                <view class="empty" v-if="invitedList.length === 0">
                    <text>暂无邀请记录</text>
                </view>
            </view>
        </view>

        <view class="reward-list">
            <view class="section-header">
                <text class="title">奖励记录</text>
            </view>
            <view class="list-content">
                <view class="reward-item" v-for="item in rewardList" :key="item.id">
                    <view class="reward-info">
                        <text class="desc">邀请接单员奖励</text>
                        <text class="time">{{ formatTime(item.create_time) }}</text>
                    </view>
                    <text class="amount" :class="{ pending: item.status === 0 }">
                        {{ item.status === 0 ? '待发放' : '+¥' + item.reward_amount }}
                    </text>
                </view>
                <view class="empty" v-if="rewardList.length === 0">
                    <text>暂无奖励记录</text>
                </view>
            </view>
        </view>

        <view class="rules-card">
            <view class="section-header">
                <text class="title">邀请规则</text>
            </view>
            <view class="rule-list">
                <text class="rule">1. 分享邀请码给好友，好友申请成为接单员时填写您的邀请码</text>
                <text class="rule">2. 好友审核通过并完成指定订单数后，您可获得奖励</text>
                <text class="rule">3. 奖励将自动发放到您的接单员余额</text>
                <text class="rule">4. 每位好友只能被邀请一次</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getInvitedRunners, getRunnerInviteRewards, getRunnerInviteStats } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const inviteCode = ref('')
const stats = ref<any>({})
const invitedList = ref<any[]>([])
const rewardList = ref<any[]>([])
const runnerId = ref(0)

const statusMap: Record<number, string> = {
    0: '待审核',
    1: '已通过',
    2: '已拒绝',
    3: '已禁用'
}

onMounted(() => {
    const runner = uni.getStorageSync('runnerInfo')
    if (runner && runner.id) {
        runnerId.value = runner.id
        inviteCode.value = 'R' + runner.id.toString().padStart(6, '0')
        loadData()
    }
})

const loadData = async () => {
    // 加载统计
    const statsRes: any = await getRunnerInviteStats({ inviter_id: runnerId.value })
    if (statsRes.code === 1) {
        stats.value = statsRes.data
    }

    // 加载邀请列表
    const invitedRes: any = await getInvitedRunners({ inviter_id: runnerId.value, page: 1, limit: 20 })
    if (invitedRes.code === 1) {
        invitedList.value = invitedRes.data.list || []
    }

    // 加载奖励记录
    const rewardRes: any = await getRunnerInviteRewards({ inviter_id: runnerId.value, page: 1, limit: 20 })
    if (rewardRes.code === 1) {
        rewardList.value = rewardRes.data.list || []
    }
}

const copyCode = () => {
    uni.setClipboardData({
        data: inviteCode.value,
        success: () => {
            uni.showToast({ title: '邀请码已复制', icon: 'success' })
        }
    })
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const getStatusText = (status: number) => statusMap[status] || '未知'
const getStatusClass = (status: number) => {
    if (status === 0) return 'pending'
    if (status === 1) return 'success'
    return 'failed'
}
</script>

<style lang="scss" scoped>
.runner-invite-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.invite-header {
    position: relative;
    height: 240rpx;
    
    .header-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 100%;
        background: linear-gradient(135deg, #c0fe95, #f7f7f7);
    }
    
    .header-content {
        position: relative;
        padding: 60rpx 30rpx;
        color: #333;
        text-align: center;
        
        .title {
            display: block;
            font-size: 40rpx;
            font-weight: bold;
            margin-bottom: 12rpx;
        }
        
        .desc {
            font-size: 28rpx;
            opacity: 0.9;
        }
    }
}

.invite-card {
    margin: -40rpx 20rpx 20rpx;
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    position: relative;
}

.invite-code {
    .copy-btn {
        padding: 0 32rpx;
        height: 64rpx;
        line-height: 64rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
        font-size: 26rpx;
        border: none;
        border-radius: 32rpx;
        font-weight: bold;
    }
}

.section-header {
    padding: 20rpx;
    
    .title {
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
    }
}

.invite-list, .reward-list, .rules-card {
    background: #fff;
    margin: 0 20rpx 20rpx;
    border-radius: 16rpx;
}

.list-content {
    padding: 0 20rpx 20rpx;
}

.invite-item {
    display: flex;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child { border-bottom: none; }
    
    .user-info {
        flex: 1;
        display: flex;
        align-items: center;
        
        .avatar {
            width: 80rpx;
            height: 80rpx;
            border-radius: 50%;
            margin-right: 16rpx;
        }
        
        .info {
            .name {
                display: block;
                font-size: 28rpx;
                color: #333;
            }
            
            .time {
                font-size: 24rpx;
                color: #999;
            }
        }
    }
    
    .status-tag {
        font-size: 24rpx;
        padding: 4rpx 16rpx;
        border-radius: 4rpx;
        
        &.pending { background: #fff7e6; color: #fa8c16; }
        &.success { background: #f6ffed; color: #52c41a; }
        &.failed { background: #fff1f0; color: #ff4d4f; }
    }
}

.reward-item {
    display: flex;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child { border-bottom: none; }
    
    .reward-info {
        flex: 1;
        
        .desc {
            display: block;
            font-size: 28rpx;
            color: #333;
        }
        
        .time {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .amount {
        font-size: 28rpx;
        font-weight: bold;
        color: #52c41a;
        
        &.pending {
            color: #fa8c16;
            font-size: 26rpx;
            font-weight: normal;
        }
    }
}

.rule-list {
    padding: 0 20rpx 20rpx;
    
    .rule {
        display: block;
        font-size: 26rpx;
        color: #666;
        line-height: 2;
    }
}

.empty {
    text-align: center;
    padding: 40rpx;
    font-size: 26rpx;
    color: #999;
}
</style>
