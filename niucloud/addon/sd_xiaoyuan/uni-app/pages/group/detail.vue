<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="detail-page" v-if="isFeatureEnabled">
        <view class="detail-header" v-if="info.id">
            <view class="header-bg"></view>
            <view class="header-content">
                <view class="type-tag" :class="'type-' + info.group_type">{{ typeMap[info.group_type] || info.group_type }}</view>
                <view class="title">{{ info.title }}</view>
                <view class="status-bar">
                    <view class="status-tag" :class="'status-' + info.status">{{ statusMap[info.status] ?? '未知' }}</view>
                    <text class="time">{{ info.create_time }}</text>
                </view>
            </view>
        </view>

        <view class="info-card" v-if="info.id">
            <view class="info-row" v-if="info.shop_name">
                <text class="info-label">店铺</text>
                <text class="info-value">{{ info.shop_name }}</text>
            </view>
            <view class="info-row">
                <text class="info-label">人数</text>
                <text class="info-value">{{ info.current_members }}/{{ info.max_members }}人 (最少{{ info.min_members }}人成团)</text>
            </view>
            <view class="info-row">
                <text class="info-label">人均</text>
                <text class="info-value price">¥{{ info.per_price }}</text>
            </view>
            <view class="info-row" v-if="info.delivery_fee > 0">
                <text class="info-label">配送费</text>
                <text class="info-value">¥{{ info.delivery_fee }}</text>
            </view>
            <view class="info-row" v-if="info.delivery_address">
                <text class="info-label">配送地址</text>
                <text class="info-value">{{ info.delivery_address }}</text>
            </view>
            <view class="info-row" v-if="info.deadline">
                <text class="info-label">截止时间</text>
                <text class="info-value">{{ formatTime(info.deadline) }}</text>
            </view>
            <view class="info-row" v-if="info.content">
                <text class="info-label">详情</text>
                <text class="info-value">{{ info.content }}</text>
            </view>
        </view>

        <view class="progress-card" v-if="info.id">
            <view class="card-title">拼单进度 ({{ info.current_members }}/{{ info.min_members }}人成团)</view>
            <view class="progress-bar">
                <view class="progress-fill" :style="{ width: Math.min(info.current_members / info.min_members * 100, 100) + '%' }"></view>
            </view>
        </view>

        <view class="members-card" v-if="info.members && info.members.length > 0">
            <view class="card-title">参与成员 ({{ info.members.length }}人)</view>
            <view class="member-list">
                <view class="member-item" v-for="(m, idx) in info.members" :key="idx">
                    <view class="member-avatar">
                        <text>{{ m.is_leader ? '团' : (Number(idx) + 1) }}</text>
                    </view>
                    <view class="member-info">
                        <text class="member-name">{{ m.is_leader ? '团长' : '成员' + idx }}</text>
                        <text class="member-order" v-if="m.order_content">{{ m.order_content }}</text>
                    </view>
                    <text class="member-amount">¥{{ m.amount }}</text>
                </view>
            </view>
        </view>

        <view class="action-bar" v-if="info.id">
            <template v-if="info.status === 0">
                <view class="join-section" v-if="!isJoined && !isLeader">
                    <input v-model="orderContent" placeholder="输入你的点单内容" class="join-input" />
                    <button class="join-btn" @click="handleJoin">参与拼单</button>
                </view>
                <view class="leader-actions" v-if="isLeader">
                    <button class="cancel-btn" @click="handleCancel">取消拼单</button>
                    <button class="confirm-btn" @click="handleConfirm" v-if="info.current_members >= info.min_members">确认成团</button>
                    <!-- #ifdef MP-WEIXIN -->
                    <button class="share-btn-inline" open-type="share">
                        <u-icon name="share" size="18" color="#666"></u-icon>
                    </button>
                    <!-- #endif -->
                    <!-- #ifdef H5 -->
                    <button class="share-btn-inline" @click="handleShare">
                        <u-icon name="share" size="18" color="#666"></u-icon>
                    </button>
                    <!-- #endif -->
                </view>
                <view class="quit-section" v-if="isJoined && !isLeader">
                    <button class="quit-btn" @click="handleQuit">退出拼单</button>
                </view>
            </template>
            <template v-if="info.status === 10 && isLeader">
                <button class="complete-btn" @click="handleComplete">确认完成</button>
            </template>
            
            <!-- 分享按钮 - 非团长时单独显示 -->
            <view class="share-section" v-if="!isLeader || info.status !== 0">
                <!-- #ifdef MP-WEIXIN -->
                <button class="share-btn" open-type="share">
                    <u-icon name="share" size="20" color="#666"></u-icon>
                    <text>分享</text>
                </button>
                <!-- #endif -->
                
                <!-- #ifdef H5 -->
                <button class="share-btn" @click="handleShare">
                    <u-icon name="share" size="20" color="#666"></u-icon>
                    <text>分享</text>
                </button>
                <!-- #endif -->
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad, onShareAppMessage } from '@dcloudio/uni-app'
import { getGroupOrderInfo, joinGroupOrder, quitGroupOrder, cancelGroupOrder, confirmGroupOrderSuccess, completeGroupOrder } from '../../api/xiaoyuan'
import useMemberStore from '@/stores/member'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_group')

const memberStore = useMemberStore()
const info = ref<any>({})
const orderContent = ref('')
const groupId = ref(0)

const typeMap: Record<string, string> = {
    'TEA': '拼奶茶', 'FOOD': '拼外卖', 'FRUIT': '拼水果', 'RIDE': '拼车', 'OTHER': '其他'
}
const statusMap: Record<number, string> = {
    0: '拼单中', 10: '已成团', 20: '配送中', 30: '已完成', 90: '已取消', 91: '拼单失败'
}

const isLeader = computed(() => {
    return info.value.member_id === memberStore.info?.member_id
})

const isJoined = computed(() => {
    if (!info.value.members) return false
    return info.value.members.some((m: any) => m.member_id === memberStore.info?.member_id)
})

const formatTime = (ts: number) => {
    if (!ts) return ''
    const d = new Date(ts * 1000)
    return `${d.getMonth() + 1}-${d.getDate()} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`
}

const loadInfo = async () => {
    try {
        const res: any = await getGroupOrderInfo(groupId.value)
        if (res.code === 1) {
            info.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const handleJoin = async () => {
    if (!orderContent.value) {
        uni.showToast({ title: '请输入点单内容', icon: 'none' })
        return
    }
    try {
        uni.showLoading({ title: '加入中...' })
        const res: any = await joinGroupOrder({
            group_id: groupId.value,
            order_content: orderContent.value,
            amount: info.value.per_price
        })
        uni.hideLoading()
        if (res.code === 1) {
            uni.showToast({ title: '参与成功', icon: 'success' })
            loadInfo()
        } else {
            uni.showToast({ title: res.msg || '参与失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.message || '操作失败', icon: 'none' })
    }
}

const handleQuit = async () => {
    uni.showModal({
        title: '提示',
        content: '确定退出拼单吗？',
        success: async (r) => {
            if (r.confirm) {
                const res: any = await quitGroupOrder({ group_id: groupId.value })
                if (res.code === 1) {
                    uni.showToast({ title: '已退出', icon: 'success' })
                    loadInfo()
                }
            }
        }
    })
}

const handleCancel = async () => {
    uni.showModal({
        title: '提示',
        content: '确定取消拼单吗？',
        success: async (r) => {
            if (r.confirm) {
                const res: any = await cancelGroupOrder({ group_id: groupId.value })
                if (res.code === 1) {
                    uni.showToast({ title: '已取消', icon: 'success' })
                    loadInfo()
                }
            }
        }
    })
}

const handleConfirm = async () => {
    uni.showModal({
        title: '提示',
        content: '确认成团吗？',
        success: async (r) => {
            if (r.confirm) {
                const res: any = await confirmGroupOrderSuccess({ group_id: groupId.value })
                if (res.code === 1) {
                    uni.showToast({ title: '成团成功', icon: 'success' })
                    loadInfo()
                }
            }
        }
    })
}

const handleComplete = async () => {
    uni.showModal({
        title: '提示',
        content: '确认完成拼单吗？',
        success: async (r) => {
            if (r.confirm) {
                const res: any = await completeGroupOrder({ group_id: groupId.value })
                if (res.code === 1) {
                    uni.showToast({ title: '已完成', icon: 'success' })
                    loadInfo()
                }
            }
        }
    })
}

const handleShare = () => {
    // #ifdef H5
    uni.showModal({
        title: '分享提示',
        content: '请点击右上角...按钮，选择"发送给朋友"或"分享到朋友圈"进行分享',
        showCancel: false
    })
    // #endif
}

// #ifdef MP-WEIXIN
onShareAppMessage(() => {
    return {
        title: info.value.title || '拼单好饭',
        path: `/addon/sd_xiaoyuan/pages/group/detail?id=${groupId.value}`,
        imageUrl: '' // 可选，自定义分享图片
    }
})
// #endif

onLoad((options: any) => {
    groupId.value = parseInt(options?.id || '0')
    if (groupId.value) {
        loadConfig()
        loadInfo()
    }
})
</script>

<style lang="scss" scoped>
.detail-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 180rpx;
}

.detail-header {
    position: relative;
    padding: 40rpx 30rpx 30rpx;
    
    .header-bg {
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 300rpx;
        background: linear-gradient(135deg, #ff5722, #ff9800);
        z-index: 0;
    }
    
    .header-content {
        position: relative;
        z-index: 1;
        
        .type-tag {
            display: inline-block;
            font-size: 22rpx;
            padding: 6rpx 16rpx;
            border-radius: 20rpx;
            color: #fff;
            background: rgba(255,255,255,0.3);
            margin-bottom: 16rpx;
        }
        
        .title {
            font-size: 36rpx;
            font-weight: bold;
            color: #fff;
            margin-bottom: 16rpx;
        }
        
        .status-bar {
            display: flex;
            align-items: center;
            gap: 16rpx;
            
            .status-tag {
                font-size: 22rpx;
                padding: 6rpx 16rpx;
                border-radius: 20rpx;
                background: rgba(255,255,255,0.9);
                
                &.status-0 { color: #ff9800; }
                &.status-10 { color: #4caf50; }
                &.status-20 { color: #2196f3; }
                &.status-30 { color: #999; }
                &.status-90, &.status-91 { color: #ccc; }
            }
            
            .time {
                font-size: 24rpx;
                color: rgba(255,255,255,0.8);
            }
        }
    }
}

.info-card, .progress-card, .members-card {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
}

.card-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.info-row {
    display: flex;
    padding: 12rpx 0;
    border-bottom: 1rpx solid #f9f9f9;
    
    &:last-child { border-bottom: none; }
    
    .info-label {
        width: 140rpx;
        font-size: 26rpx;
        color: #999;
        flex-shrink: 0;
    }
    
    .info-value {
        flex: 1;
        font-size: 26rpx;
        color: #333;
        
        &.price {
            color: #ff5722;
            font-weight: bold;
            font-size: 30rpx;
        }
    }
}

.progress-bar {
    height: 16rpx;
    background: #f5f5f5;
    border-radius: 8rpx;
    overflow: hidden;
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(135deg, #ff5722, #ff9800);
        border-radius: 8rpx;
        transition: width 0.3s;
    }
}

.member-list {
    .member-item {
        display: flex;
        align-items: center;
        padding: 16rpx 0;
        border-bottom: 1rpx solid #f9f9f9;
        
        &:last-child { border-bottom: none; }
    }
    
    .member-avatar {
        width: 60rpx;
        height: 60rpx;
        border-radius: 50%;
        background: linear-gradient(135deg, #ff5722, #ff9800);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16rpx;
        
        text {
            color: #fff;
            font-size: 24rpx;
            font-weight: bold;
        }
    }
    
    .member-info {
        flex: 1;
        
        .member-name {
            font-size: 28rpx;
            color: #333;
            display: block;
        }
        
        .member-order {
            font-size: 24rpx;
            color: #999;
            display: block;
            margin-top: 4rpx;
        }
    }
    
    .member-amount {
        font-size: 28rpx;
        color: #ff5722;
        font-weight: bold;
    }
}

.action-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -2rpx 12rpx rgba(0,0,0,0.05);
    
    .join-section {
        display: flex;
        gap: 16rpx;
        
        .join-input {
            flex: 1;
            height: 80rpx;
            background: #f5f5f5;
            border-radius: 40rpx;
            padding: 0 24rpx;
            font-size: 28rpx;
        }
        
        .join-btn {
            padding: 0 40rpx;
            height: 80rpx;
            background: linear-gradient(135deg, #ff5722, #ff9800);
            color: #fff;
            font-size: 28rpx;
            font-weight: bold;
            border-radius: 40rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            white-space: nowrap;
        }
    }
    
    .leader-actions {
        display: flex;
        gap: 16rpx;
        
        button {
            height: 80rpx;
            border-radius: 40rpx;
            font-size: 28rpx;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
        }
        
        .cancel-btn {
            flex: 1;
            background: #f5f5f5;
            color: #999;
        }
        
        .confirm-btn {
            flex: 1;
            background: linear-gradient(135deg, #ff5722, #ff9800);
            color: #fff;
        }
        
        .share-btn-inline {
            width: 80rpx;
            flex-shrink: 0;
            background: #f5f5f5;
            color: #666;
            padding: 0;
        }
    }
    
    .quit-section {
        .quit-btn {
            width: 100%;
            height: 80rpx;
            background: #f5f5f5;
            color: #ff4d4f;
            font-size: 28rpx;
            border-radius: 40rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
        }
    }
    
    .complete-btn {
        width: 100%;
        height: 80rpx;
        background: linear-gradient(135deg, #4caf50, #8bc34a);
        color: #fff;
        font-size: 28rpx;
        font-weight: bold;
        border-radius: 40rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
    }
    
    .share-section {
        margin-top: 20rpx;
        
        .share-btn {
            width: 100%;
            height: 80rpx;
            background: #f8f8f8;
            color: #666;
            font-size: 26rpx;
            border-radius: 40rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12rpx;
            border: none;
            
            &::after {
                border: none;
            }
        }
    }
}
</style>
