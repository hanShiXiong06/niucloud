<template>
    <view class="invite-page">
        <view class="page-header">
            <view class="header-bg"></view>
            <view class="header-content">
                <text class="title">我的团队</text>
                <text class="desc">邀请好友注册，赚取佣金奖励</text>
            </view>
        </view>

        <view class="stat-card">
            <view class="stat-item">
                <text class="value">{{ inviteStat.total_invite || 0 }}</text>
                <text class="label">邀请人数</text>
            </view>
            <view class="stat-item">
                <text class="value">¥{{ inviteStat.total_commission || '0.00' }}</text>
                <text class="label">累计佣金</text>
            </view>
        </view>

        <view class="invite-card">
            <view class="invite-action-bar">
                <button class="invite-btn" @click="openSharePoster">
                    <u-icon name="gift" size="22" color="#000"></u-icon>
                    <text>邀请好友</text>
                </button>
            </view>
        </view>

        <view class="rules-card">
            <view class="card-title">奖励规则</view>
            <view class="rule-list">
                <view class="rule-item" v-for="(rule, index) in inviteRules" :key="index">
                    <text class="num">{{ index + 1 }}</text>
                    <text>{{ rule }}</text>
                </view>
            </view>
        </view>

        
        <!-- 官方海报组件 -->
        <share-poster 
            ref="sharePosterRef" 
            posterType="xiaoyuan_invite" 
            :posterId="memberId" 
            :posterParam="posterParam" 
            :copyUrl="copyUrl"
            :copyUrlParam="copyUrlParam" 
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getInviteStat, getConfig } from '../../api/xiaoyuan'
import useMemberStore from '@/stores/member'
import sharePoster from '@/components/share-poster/share-poster.vue'
import { img } from '@/utils/common'

const memberStore = useMemberStore()
const inviteStat = ref<any>({})
const sharePosterRef = ref<any>(null)
const posterParam = ref<any>({})
const inviteCode = ref('')
const inviteUrl = ref('')
const inviteRules = ref<string[]>(['邀请好友注册并下单', '好友每笔订单您可获得10%佣金', '二级好友订单可获得5%佣金'])

const memberId = computed(() => {
    return memberStore.info?.member_id || 0
})

const copyUrlParam = computed(() => {
    return `?mid=${memberId.value}`
})

const copyUrl = '/addon/sd_xiaoyuan/pages/index/index'

onMounted(() => {
    loadStat()
    loadInviteInfo()
})

onShow(() => {
    loadInviteInfo()
})

const loadInviteInfo = async () => {
    if (memberStore.info) {
        inviteCode.value = memberStore.info.member_id ? `XY${memberStore.info.member_id}` : ''
        const baseUrl = window?.location?.origin || ''
        inviteUrl.value = `${baseUrl}/wap/addon/sd_xiaoyuan/pages/index/index?mid=${memberStore.info.member_id || 0}`
        
        // 获取奖励规则
        try {
            const res: any = await getConfig()
            if (res.code === 1 && res.data) {
                const config = res.data
                // 根据分销比例动态生成规则
                const rate1 = config.fenxiao_rate1 || 10
                const rate2 = config.fenxiao_rate2 || 5
                
                inviteRules.value = [
                    '邀请好友注册并下单',
                    `好友每笔订单您可获得${rate1}%佣金`,
                    `二级好友订单可获得${rate2}%佣金`
                ]
            }
        } catch (error) {
            // 默认规则
            inviteRules.value = [
                '邀请好友注册并下单',
                '好友每笔订单您可获得10%佣金',
                '二级好友订单可获得5%佣金'
            ]
        }
    }
}

const loadStat = async () => {
    try {
        const res: any = await getInviteStat()
        if (res.code === 1 && res.data) {
            inviteStat.value = res.data
        } else {
            inviteStat.value = { total_invite: 0, total_commission: '0.00' }
        }
    } catch (e) {
        console.error(e)
        inviteStat.value = { total_invite: 0, total_commission: '0.00' }
    }
}


const openSharePoster = () => {
    if (memberId.value === 0) {
        uni.showToast({ title: '请先登录', icon: 'none' })
        return
    }
    if (sharePosterRef.value) {
        posterParam.value = {
            nickname: memberStore.info?.nickname || '校园用户',
            avatar: memberStore.info?.headimg || memberStore.info?.avatar || '/static/resource/images/default_headimg.png',
            member_id: memberId.value,
            invite_code: inviteCode.value,
            invite_url: inviteUrl.value
        }
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
.invite-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.page-header {
    position: relative;
    height: 280rpx;
    
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
        text-align: center;
        
        .title {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
        }
        
        .desc {
            display: block;
            font-size: 28rpx;
            opacity: 0.9;
            margin-top: 8rpx;
        }
    }
}

.stat-card {
    display: flex;
    margin: 20rpx;
    background: #fff;
    border-radius: 16rpx;
    padding: 40rpx;
    position: relative;
    z-index: 10;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.06);
    
    .stat-item {
        flex: 1;
        text-align: center;
        
        .value {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
            color: #333;
        }
        
        .label {
            font-size: 26rpx;
            color: #999;
        }
    }
}

.invite-card, .rules-card, .team-card {
    margin: 20rpx;
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
}

.invite-info {
    .info-item {
        display: flex;
        align-items: center;
        padding: 20rpx 0;
        border-bottom: 1rpx solid #f0f0f0;

        &:last-child { border-bottom: none; }

        .label {
            color: #333;
            font-size: 26rpx;
            width: 160rpx;
            flex-shrink: 0;
        }

        .value {
            flex: 1;
            font-size: 28rpx;
            color: #333;

            &.link {
                font-size: 22rpx;
                word-break: break-all;
            }
        }

        .copy-btn {
            padding: 0 20rpx;
            height: 48rpx;
            line-height: 48rpx;
            background: linear-gradient(to top, #c0fe95, #f7f7f7);
            color: #000;
            font-size: 22rpx;
            border: none;
            border-radius: 24rpx;
            font-weight: bold;
        }
    }
}

.invite-action-bar {
    margin-top: 24rpx;
    display: flex;
    justify-content: center;

    .invite-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10rpx;
        width: 80%;
        height: 80rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        font-size: 30rpx;
        font-weight: bold;
        border: none;
        border-radius: 40rpx;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    }
}

.card-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
    
    .tabs {
        display: flex;
        gap: 20rpx;
        
        text {
            font-size: 26rpx;
            color: #999;
            font-weight: normal;
        }
    }
    
    .desc {
        color: #fff;
        opacity: 0.9;
    }
}

.team-list {
    .team-item {
        display: flex;
        align-items: center;
        padding: 20rpx 0;
        border-bottom: 1rpx solid #f0f0f0;
        
        .avatar {
            width: 80rpx;
            height: 80rpx;
            border-radius: 50%;
            margin-right: 20rpx;
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
    
    .empty {
        text-align: center;
        padding: 40rpx;
        color: #999;
        font-size: 26rpx;
    }
}
</style>
