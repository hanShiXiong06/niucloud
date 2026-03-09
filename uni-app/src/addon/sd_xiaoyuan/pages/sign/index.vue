<template>
    <view class="sign-page">
        <!-- 头部 -->
        <view class="page-header">
            <view class="header-bg"></view>
            <view class="header-content">
                <text class="title">每日签到</text>
                <text class="desc">连续签到7天可获得额外奖励</text>
            </view>
        </view>

        <!-- 签到卡片 -->
        <view class="sign-card">
            <view class="continuous-info">
                <text class="label">已连续签到</text>
                <view class="days">
                    <text class="num">{{ signStatus.continuous_days || 0 }}</text>
                    <text class="unit">天</text>
                </view>
            </view>

            <!-- 7天签到进度 -->
            <view class="week-progress">
                <view 
                    class="day-item" 
                    v-for="(reward, index) in weekRewards" 
                    :key="index"
                    :class="{ 
                        signed: isDaySigned(index + 1),
                        today: index + 1 === getTodayIndex(),
                        future: index + 1 > getTodayIndex()
                    }"
                >
                    <view class="day-circle">
                        <u-icon name="checkmark" size="28" color="#333" v-if="isDaySigned(index + 1)"></u-icon>
                        <text class="day-num" v-else>{{ index + 1 }}</text>
                    </view>
                    <text class="day-label">第{{ index + 1 }}天</text>
                    <text class="reward">+{{ reward.points }}积分</text>
                </view>
            </view>

            <!-- 签到按钮 -->
            <button 
                class="sign-btn" 
                :class="{ signed: signStatus.is_signed }"
                :disabled="signStatus.is_signed"
                @click="doSign"
            >
                {{ signStatus.is_signed ? '今日已签到' : '立即签到' }}
            </button>

            <!-- 我的积分 + 兑换奖品 -->
            <view class="points-bar">
                <view class="my-points">
                    <text class="points-label">我的积分</text>
                    <text class="points-value">{{ signStatus.total_points || 0 }}</text>
                </view>
                <button class="exchange-btn" @click="goToPointsMall">
                    <u-icon name="gift" size="18" color="#000"></u-icon>
                    <text>兑换奖品</text>
                </button>
            </view>
        </view>

        <!-- 签到规则 -->
        <view class="rules-card">
            <view class="card-title">签到规则</view>
            <view class="rule-list">
                <view class="rule-item">
                    <text class="dot"></text>
                    <text>每日签到可获得积分奖励</text>
                </view>
                <view class="rule-item">
                    <text class="dot"></text>
                    <text>连续签到天数越多，奖励越丰厚</text>
                </view>
                <view class="rule-item">
                    <text class="dot"></text>
                    <text>连续签到7天可获得100积分大礼包</text>
                </view>
                <view class="rule-item">
                    <text class="dot"></text>
                    <text>中断签到后，连续天数将重新计算</text>
                </view>
            </view>
        </view>

        <!-- 签到记录 -->
        <view class="history-card">
            <view class="card-title">
                <text>签到记录</text>
                <text class="more" @click="goToHistory">查看更多</text>
            </view>
            <view class="history-list">
                <view class="history-item" v-for="item in recentHistory" :key="item.id">
                    <text class="date">{{ formatDate(item.sign_date) }}</text>
                    <text class="reward">+{{ item.reward_points }}积分</text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { getSignStatus, doSign as doSignApi, getSignHistory } from '../../api/xiaoyuan'

const signStatus = ref<any>({
    is_signed: false,
    continuous_days: 0,
    week_signs: []
})

const weekRewards = ref([
    { day: 1, points: 10 },
    { day: 2, points: 20 },
    { day: 3, points: 30 },
    { day: 4, points: 40 },
    { day: 5, points: 50 },
    { day: 6, points: 60 },
    { day: 7, points: 100 }
])

const recentHistory = ref<any[]>([])

onMounted(() => {
    loadSignStatus()
    loadHistory()
})

const loadSignStatus = async () => {
    try {
        const res: any = await getSignStatus()
        if (res.code === 1) {
            signStatus.value = res.data
            if (res.data.rewards) {
                weekRewards.value = res.data.rewards
            }
        }
    } catch (e) {
        console.error(e)
    }
}

const loadHistory = async () => {
    try {
        const res: any = await getSignHistory({ page: 1, limit: 5 })
        if (res.code === 1) {
            recentHistory.value = res.data.list || []
        }
    } catch (e) {
        console.error(e)
    }
}

const doSign = async () => {
    if (signStatus.value.is_signed) return
    
    try {
        uni.showLoading({ title: '签到中...' })
        const res: any = await doSignApi()
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({
                title: `签到成功！+${res.data.reward_points}积分`,
                icon: 'success'
            })
            loadSignStatus()
            loadHistory()
        } else {
            uni.showToast({ title: res.msg || '签到失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const isDaySigned = (day: number) => {
    const weekStart = getWeekStart()
    const targetDate = new Date(weekStart.getTime() + (day - 1) * 24 * 60 * 60 * 1000)
    const dateStr = formatDateStr(targetDate)
    return signStatus.value.week_signs?.includes(dateStr)
}

const getTodayIndex = () => {
    const today = new Date().getDay()
    return today === 0 ? 7 : today
}

const getWeekStart = () => {
    const today = new Date()
    const day = today.getDay() || 7
    return new Date(today.getTime() - (day - 1) * 24 * 60 * 60 * 1000)
}

const formatDateStr = (date: Date) => {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const goToPointsMall = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/points/mall'
    })
}

const formatDate = (dateStr: string) => {
    if (!dateStr) return ''
    const parts = dateStr.split('-')
    return `${parts[1]}月${parts[2]}日`
}

const goToHistory = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/sign/history'
    })
}
</script>

<style lang="scss" scoped>
.sign-page {
    min-height: 100vh;
    background: #f7f7f7;
}

.page-header {
    position: relative;
    height: 300rpx;
    
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
        padding: 80rpx 30rpx;
        color: #333;
        
        .title {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
            margin-bottom: 12rpx;
        }
        
        .desc {
            font-size: 28rpx;
            color: #666;
        }
    }
}

.sign-card {
    margin: -80rpx 20rpx 20rpx;
    background: #fff;
    border-radius: 16rpx;
    padding: 40rpx 30rpx;
    position: relative;
}

.continuous-info {
    text-align: center;
    margin-bottom: 40rpx;
    
    .label {
        font-size: 28rpx;
        color: #666;
    }
    
    .days {
        margin-top: 12rpx;
        
        .num {
            font-size: 72rpx;
            font-weight: bold;
            color: #000000;
        }
        
        .unit {
            font-size: 28rpx;
            color: #666;
            margin-left: 8rpx;
        }
    }
}

.week-progress {
    display: flex;
    justify-content: space-between;
    margin-bottom: 40rpx;
}

.day-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    
    .day-circle {
        width: 60rpx;
        height: 60rpx;
        border-radius: 50%;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 8rpx;
        
        .day-num {
            font-size: 26rpx;
            color: #999;
        }
        
        .iconfont {
            font-size: 28rpx;
            color: #333;
        }
    }
    
    .day-label {
        font-size: 22rpx;
        color: #999;
        margin-bottom: 4rpx;
    }
    
    .reward {
        font-size: 20rpx;
        color: #ff6b00;
    }
    
    &.signed .day-circle {
        background: #c0fe95;
        .iconfont { color: #333; }
    }
    
    &.today .day-circle {
        border: 2rpx solid #c0fe95;
    }
}

.sign-btn {
    width: 100%;
    height: 88rpx;
    line-height: 88rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 44rpx;
    font-size: 28rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    
    &.signed {
        background: #e5e5e5;
        color: #999;
        box-shadow: none;
    }
}

.points-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 30rpx;
    padding-top: 24rpx;
    border-top: 1rpx solid #f0f0f0;

    .my-points {
        display: flex;
        align-items: baseline;
        gap: 8rpx;

        .points-label {
            font-size: 26rpx;
            color: #666;
        }

        .points-value {
            font-size: 40rpx;
            font-weight: bold;
            color: #ff6b00;
        }
    }

    .exchange-btn {
        display: flex;
        align-items: center;
        gap: 8rpx;
        padding: 14rpx 32rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        font-size: 26rpx;
        font-weight: bold;
        border: none;
        border-radius: 30rpx;
    }
}

.rules-card, .history-card {
    margin: 20rpx;
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
}

.card-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
    
    .more {
        font-size: 26rpx;
        color: #333;
        font-weight: normal;
    }
}

.rule-list {
    .rule-item {
        display: flex;
        align-items: center;
        margin-bottom: 16rpx;
        
        &:last-child {
            margin-bottom: 0;
        }
        
        .dot {
            width: 8rpx;
            height: 8rpx;
            background: #ff6b00;
            border-radius: 50%;
            margin-right: 16rpx;
        }
        
        text {
            font-size: 26rpx;
            color: #666;
        }
    }
}

.history-list {
    .history-item {
        display: flex;
        justify-content: space-between;
        padding: 16rpx 0;
        border-bottom: 1rpx solid #f0f0f0;
        
        &:last-child {
            border-bottom: none;
        }
        
        .date {
            font-size: 28rpx;
            color: #333;
        }
        
        .reward {
            font-size: 28rpx;
            color: #ff6b00;
        }
    }
}
</style>
