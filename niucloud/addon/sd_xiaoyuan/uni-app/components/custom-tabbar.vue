<template>
    <view>
        <!-- Custom Tabbar -->
        <view class="custom-tabbar">
            <view class="tabbar-item" :class="{ active: current === 'home' }" @click="switchTab('home')">
                <view class="icon-box">
                    <u-icon :name="current === 'home' ? 'home-fill' : 'home'" size="28" :color="current === 'home' ? '#333' : '#999'"></u-icon>
                </view>
                <text>主页</text>
            </view>
            <view class="tabbar-item" :class="{ active: current === 'hall' }" @click="switchTab('hall')">
                <view class="icon-box">
                    <u-icon :name="current === 'hall' ? 'grid-fill' : 'grid'" size="28" :color="current === 'hall' ? '#333' : '#999'"></u-icon>
                </view>
                <text>大厅</text>
            </view>
            <view class="tabbar-item center" @click="onPlusClick">
                <view class="plus-btn">
                    <u-icon name="plus" size="24" color="#c0fe95"></u-icon>
                </view>
            </view>
            <view class="tabbar-item" :class="{ active: current === 'message' }" @click="switchTab('message')">
                <view class="icon-box">
                    <u-icon :name="current === 'message' ? 'chat-fill' : 'chat'" size="28" :color="current === 'message' ? '#333' : '#999'"></u-icon>
                </view>
                <text>消息</text>
            </view>
            <view class="tabbar-item" :class="{ active: current === 'user' }" @click="switchTab('user')">
                <view class="icon-box">
                    <u-icon :name="current === 'user' ? 'account-fill' : 'account'" size="28" :color="current === 'user' ? '#333' : '#999'"></u-icon>
                </view>
                <text>我的</text>
            </view>
        </view>

        <!-- Cert Check Popup -->
        <u-popup :show="showCertPopup" mode="center" round="16" @close="showCertPopup = false" :customStyle="{ width: '80%' }">
            <view class="cert-popup">
                <view class="cert-icon">
                    <u-icon name="lock" size="60" color="#ff9500"></u-icon>
                </view>
                <text class="cert-title">未完成校园认证</text>
                <text class="cert-desc">发布内容需要先完成校园帮实名认证，认证后即可使用所有功能</text>
                <button class="cert-btn" @click="goToCert">去认证</button>
                <text class="cert-cancel" @click="showCertPopup = false">暂不认证</text>
            </view>
        </u-popup>

        <!-- Publish Type Selection Popup -->
        <u-popup :show="showPublishPopup" mode="bottom" round="20" @close="showPublishPopup = false">
            <view class="publish-popup">
                <view class="popup-header">
                    <text class="popup-title">选择发布类型</text>
                    <view class="close-btn" @click="showPublishPopup = false">
                        <u-icon name="close" size="20" color="#999"></u-icon>
                    </view>
                </view>
                <view class="publish-grid">
                    <view
                        class="publish-item"
                        v-for="item in publishTypes"
                        :key="item.id"
                        @click="selectPublishType(item)"
                    >
                        <view class="publish-icon" :style="{ background: item.color + '20' }">
                            <u-icon :name="item.icon" :color="item.color" size="32"></u-icon>
                        </view>
                        <text class="publish-name">{{ item.name }}</text>
                    </view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps<{
    current: string
    isAuthed?: boolean
}>()

const showPublishPopup = ref(false)
const showCertPopup = ref(false)

const tabRoutes: Record<string, string> = {
    home: '/addon/sd_xiaoyuan/pages/index/index',
    hall: '/addon/sd_xiaoyuan/pages/order/hall',
    message: '/addon/sd_xiaoyuan/pages/message/index',
    user: '/addon/sd_xiaoyuan/pages/user/index'
}

const publishTypes = [
    { id: 'buy', name: '帮我买', icon: 'shopping-cart', color: '#ff6b00', url: '/addon/sd_xiaoyuan/pages/buy/create' },
    { id: 'send', name: '帮我送', icon: 'car', color: '#13c2c2', url: '/addon/sd_xiaoyuan/pages/send/create' },
    { id: 'express', name: '代取快递', icon: 'gift', color: '#52c41a', url: '/addon/sd_xiaoyuan/pages/express/pickup' },
    { id: 'print', name: '帮打印', icon: 'file-text', color: '#ff9800', url: '/addon/sd_xiaoyuan/pages/print/create' },
    { id: 'queue', name: '代排队', icon: 'clock', color: '#ff5722', url: '/addon/sd_xiaoyuan/pages/queue/create' },
    { id: 'seat', name: '代占座', icon: 'account', color: '#795548', url: '/addon/sd_xiaoyuan/pages/seat/create' },
    { id: 'trash', name: '扔垃圾', icon: 'trash', color: '#9c27b0', url: '/addon/sd_xiaoyuan/pages/trash/create' },
    { id: 'carry', name: '帮搬运', icon: 'car', color: '#4caf50', url: '/addon/sd_xiaoyuan/pages/carry/create' },
    { id: 'clean', name: '代清洁', icon: 'star', color: '#2196f3', url: '/addon/sd_xiaoyuan/pages/clean/create' },
    { id: 'help', name: '帮帮忙', icon: 'question-circle', color: '#e91e63', url: '/addon/sd_xiaoyuan/pages/help/create' },
    { id: 'game', name: '游戏陪玩', icon: 'red-packet', color: '#ff7243', url: '/addon/sd_xiaoyuan/pages/game/publish' },
    { id: 'community', name: '树洞发布', icon: 'chat', color: '#673ab7', url: '/addon/sd_xiaoyuan/pages/community/publish' },
    { id: 'confession', name: '表白墙', icon: 'heart', color: '#fa709a', url: '/addon/sd_xiaoyuan/pages/confession/publish' },
    { id: 'secondhand', name: '闲置发布', icon: 'bag', color: '#13c2c2', url: '/addon/sd_xiaoyuan/pages/secondhand/publish' },
    { id: 'lost', name: '失物招领', icon: 'search', color: '#ff4d4f', url: '/addon/sd_xiaoyuan/pages/lost_found/publish' },
    { id: 'group', name: '拼单好饭', icon: 'heart-fill', color: '#ff9500', url: '/addon/sd_xiaoyuan/pages/group/create' }
]

onMounted(() => {
    uni.$on('openPublishPopup', () => {
        showPublishPopup.value = true
    })
})

onUnmounted(() => {
    uni.$off('openPublishPopup')
})

const switchTab = (tab: string) => {
    if (tab === props.current) return
    uni.reLaunch({ url: tabRoutes[tab] })
}

const onPlusClick = () => {
    showPublishPopup.value = true
}

const selectPublishType = (item: any) => {
    showPublishPopup.value = false
    if (!props.isAuthed) {
        showCertPopup.value = true
        return
    }
    uni.navigateTo({ url: item.url })
}

const goToCert = () => {
    showCertPopup.value = false
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/campus/auth' })
}

// Expose for parent to trigger cert popup
const triggerCertPopup = () => {
    showCertPopup.value = true
}

defineExpose({ triggerCertPopup })
</script>

<style lang="scss" scoped>
.custom-tabbar {
    position: fixed;
    bottom: calc(10rpx + env(safe-area-inset-bottom));
    left: 40rpx;
    right: 40rpx;
    height: 120rpx;
    background: #fff;
    border-radius: 60rpx;
    display: flex;
    justify-content: space-around;
    align-items: center;
    box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.1);
    z-index: 100;

    .tabbar-item {
        display: flex;
        flex-direction: column;
        align-items: center;

        .icon-box {
            margin-bottom: 4rpx;
        }

        text {
            font-size: 22rpx;
            color: #999;
        }

        &.active {
            text {
                color: #333;
                font-weight: bold;
            }
        }

        &.center {
            position: relative;
            top: -30rpx;

            .plus-btn {
                width: 100rpx;
                height: 100rpx;
                background: #000;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.3);
            }
        }
    }
}

.cert-popup {
    padding: 60rpx 40rpx;
    display: flex;
    flex-direction: column;
    align-items: center;

    .cert-icon {
        margin-bottom: 30rpx;
    }

    .cert-title {
        font-size: 34rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 16rpx;
    }

    .cert-desc {
        font-size: 26rpx;
        color: #999;
        text-align: center;
        line-height: 1.6;
        margin-bottom: 40rpx;
        padding: 0 20rpx;
    }

    .cert-btn {
        width: 80%;
        height: 80rpx;
        line-height: 80rpx;
        background: linear-gradient(to right, #d1ff7c, #aaf69b);
        color: #333;
        font-size: 30rpx;
        font-weight: bold;
        border-radius: 40rpx;
        border: none;
        margin-bottom: 20rpx;

        &::after { border: none; }
    }

    .cert-cancel {
        font-size: 26rpx;
        color: #999;
        padding: 10rpx 0;
    }
}

.publish-popup {
    background: #fff;
    padding: 20rpx 20rpx calc(20rpx + env(safe-area-inset-bottom));
    max-height: 70vh;

    .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30rpx;
        padding-bottom: 20rpx;
        border-bottom: 1rpx solid #f0f0f0;

        .popup-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }

        .close-btn {
            width: 60rpx;
            height: 60rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f5f5f5;
        }
    }

    .publish-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 30rpx;

        .publish-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20rpx 0;
            border-radius: 16rpx;
            transition: all 0.2s;

            &:active {
                transform: scale(0.95);
                background: #f0f0f0;
            }

            .publish-icon {
                width: 100rpx;
                height: 100rpx;
                border-radius: 20rpx;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 12rpx;
            }

            .publish-name {
                font-size: 24rpx;
                color: #666;
                text-align: center;
                line-height: 1.3;
            }
        }
    }
}
</style>
