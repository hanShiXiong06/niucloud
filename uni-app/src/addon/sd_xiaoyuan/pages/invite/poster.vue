<template>
    <view class="poster-page">
        <view class="header-section">
            <view class="header-title">邀请好友 共享福利</view>
            <view class="header-desc">{{ memberStore.info?.nickname || '同学' }}，邀请好友下单，双方都有奖励</view>
        </view>

        <view class="invite-info">
            <view class="info-item">
                <text class="label">我的邀请码</text>
                <text class="value">{{ inviteCode }}</text>
                <button class="copy-btn" @click="copyCode">复制</button>
            </view>
            <view class="info-item">
                <text class="label">邀请链接</text>
                <text class="value link">{{ inviteUrl }}</text>
                <button class="copy-btn" @click="copyLink">复制</button>
            </view>
        </view>

        <view class="action-bar">
            <button class="action-btn primary" @click="openSharePoster">
                <u-icon name="photo" size="28" color="#fff"></u-icon>
                <text>生成推广海报</text>
            </button>
        </view>

        <view class="tips">
            <text class="title">邀请说明</text>
            <text class="tip">1. 好友通过您的邀请码或链接注册</text>
            <text class="tip">2. 好友下单后您可获得佣金奖励</text>
            <text class="tip">3. 佣金可在个人中心提现</text>
        </view>
        
        <!-- 官方海报组件 -->
        <share-poster 
            ref="sharePosterRef" 
            posterType="xiaoyuan_invite" 
            :posterId="0" 
            :posterParam="posterParam" 
            :copyUrl="copyUrl"
            :copyUrlParam="copyUrlParam" 
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import useMemberStore from '@/stores/member'
import sharePoster from '@/components/share-poster/share-poster.vue'

const memberStore = useMemberStore()
const sharePosterRef = ref<any>(null)
const posterParam = ref<any>({})
const inviteCode = ref('')
const inviteUrl = ref('')

// 计算属性：确保memberId始终是最新的
const memberId = computed(() => {
    return memberStore.info?.member_id || 0
})

const copyUrlParam = computed(() => {
    return `?mid=${memberId.value}`
})

const copyUrl = '/addon/sd_xiaoyuan/pages/index/index'

onShow(() => {
    loadInviteInfo()
})

const loadInviteInfo = () => {
    // 从会员信息生成邀请码和链接
    if (memberStore.info) {
        inviteCode.value = memberStore.info.member_id ? `XY${memberStore.info.member_id}` : ''
        // 生成邀请链接
        const baseUrl = window?.location?.origin || ''
        inviteUrl.value = `${baseUrl}/wap/addon/sd_xiaoyuan/pages/index/index?mid=${memberStore.info.member_id || 0}`
    }
}

const openSharePoster = () => {
    if (memberId.value === 0) {
        uni.showToast({
            title: '请先登录',
            icon: 'none'
        })
        return
    }
    
    if (sharePosterRef.value) {
        posterParam.value = { member_id: memberId.value }
        sharePosterRef.value.openShare()
    }
}

const copyCode = () => {
    if (!inviteCode.value) {
        uni.showToast({ title: '请先登录', icon: 'none' })
        return
    }
    uni.setClipboardData({
        data: inviteCode.value,
        success: () => {
            uni.showToast({ title: '邀请码已复制', icon: 'success' })
        }
    })
}

const copyLink = () => {
    if (!inviteUrl.value || memberId.value === 0) {
        uni.showToast({ title: '请先登录', icon: 'none' })
        return
    }
    uni.setClipboardData({
        data: inviteUrl.value,
        success: () => {
            uni.showToast({ title: '链接已复制', icon: 'success' })
        }
    })
}
</script>

<style lang="scss" scoped>
.poster-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
}

.header-section {
    background: linear-gradient(135deg, #52c41a, #73d13d);
    border-radius: 16rpx;
    padding: 60rpx 30rpx;
    text-align: center;
    margin-bottom: 20rpx;
    
    .header-title {
        font-size: 40rpx;
        font-weight: bold;
        color: #fff;
        margin-bottom: 16rpx;
    }
    
    .header-desc {
        font-size: 28rpx;
        color: rgba(255,255,255,0.9);
    }
}

.action-bar {
    display: flex;
    gap: 20rpx;
    margin-bottom: 20rpx;
    
    .action-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 72rpx;
        background: #fff;
        border: none;
        border-radius: 44rpx;
        font-size: 28rpx;
        color: #333;
        
        .iconfont {
            font-size: 28rpx;
            margin-right: 8rpx;
        }
        
        &.primary {
            background: linear-gradient(to top, #aaf69b, #d1ff7c);
            color: #000000;
            font-weight: bold;
            box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
        }
    }
}

.invite-info {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.info-item {
    display: flex;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child { border-bottom: none; }
    
    .label {
        color: #333;
        width: 160rpx;
    }
    .value {
        flex: 1;
        font-size: 28rpx;
        color: #333;
        &.link {
            font-size: 24rpx;
            color: #333;
            word-break: break-all;
        }
    }
    .copy-btn {
        padding: 0 16rpx;
        height: 40rpx;
        line-height: 40rpx;
        background: linear-gradient(to top, #c0fe95, #f7f7f7);
        color: #000000;
        font-size: 22rpx;
        border: none;
        border-radius: 20rpx;
        font-weight: bold;
        &.small {
            padding: 0 16rpx;
            height: 40rpx;
            line-height: 40rpx;
            background: linear-gradient(to top, #c0fe95, #f7f7f7);
            color: #000000;
            font-size: 22rpx;
            border: none;
            border-radius: 20rpx;
            font-weight: bold;
        }
    }
}

.tips {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    .title {
        display: block;
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 20rpx;
    }
    .tip {
        display: block;
        font-size: 26rpx;
        color: #333;
        line-height: 2;
    }
}
</style>
