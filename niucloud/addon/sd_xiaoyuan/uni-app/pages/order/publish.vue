<template>
    <view class="publish-page">
        <view class="publish-popup">
            <view class="popup-header">
                <text class="popup-title">选择发布类型</text>
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

        <view style="height: 120rpx;"></view>
        <custom-tabbar v-if="config?.enable_custom_tabbar === 1" current="publish" />
        <tabbar v-else addon="sd_xiaoyuan" />
        <!-- #ifdef MP-WEIXIN -->
        <wx-privacy-popup ref="wxPrivacyPopupRef" @agree="onWxPrivacyAgree" @disagree="onWxPrivacyDisagree"></wx-privacy-popup>
        <!-- #endif -->
    </view>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import customTabbar from '../../components/custom-tabbar.vue'
import tabbar from '@/components/tabbar/tabbar.vue'
import { getConfig } from '../../api/xiaoyuan'
import { ensureCanPublish } from '../../composables/usePublishAuth'
import { useWxPrivacyBeforeAlbum } from '../../composables/useWxPrivacy'

const config = ref<any>(null)
const { wxPrivacyPopupRef, onWxPrivacyAgree, onWxPrivacyDisagree } = useWxPrivacyBeforeAlbum({ proactiveOnMount: false })
const secondhandName = computed(() => config.value?.secondhand_name || '闲置市场')
const communityName = computed(() => config.value?.community_name || '校园树洞')

const allPublishTypes = [
    { id: 'buy', name: '帮我买', icon: 'shopping-cart', color: '#ff6b00', url: '/addon/sd_xiaoyuan/pages/buy/create', key: 'enable_buy' },
    { id: 'send', name: '帮我送', icon: 'car', color: '#13c2c2', url: '/addon/sd_xiaoyuan/pages/send/create', key: 'enable_send' },
    { id: 'express', name: '代取快递', icon: 'gift', color: '#52c41a', url: '/addon/sd_xiaoyuan/pages/express/pickup', key: 'enable_express' },
    { id: 'print', name: '帮打印', icon: 'file-text', color: '#ff9800', url: '/addon/sd_xiaoyuan/pages/print/create', key: 'enable_print' },
    { id: 'trash', name: '扔垃圾', icon: 'trash', color: '#9c27b0', url: '/addon/sd_xiaoyuan/pages/trash/create', key: 'enable_trash' },
    { id: 'queue', name: '代排队', icon: 'clock', color: '#ff5722', url: '/addon/sd_xiaoyuan/pages/queue/create', key: 'enable_queue' },
    { id: 'class', name: '代上课', icon: 'bookmark-fill', color: '#5c6bc0', url: '/addon/sd_xiaoyuan/pages/daike/create', key: 'enable_class' },
    { id: 'seat', name: '代占座', icon: 'bookmark-fill', color: '#009688', url: '/addon/sd_xiaoyuan/pages/seat/create', key: 'enable_seat' },
    { id: 'carry', name: '帮搬运', icon: 'car', color: '#4caf50', url: '/addon/sd_xiaoyuan/pages/carry/create', key: 'enable_carry' },
    { id: 'clean', name: '代清洁', icon: 'star', color: '#2196f3', url: '/addon/sd_xiaoyuan/pages/clean/create', key: 'enable_clean' },
    { id: 'help', name: '帮帮忙', icon: 'question-circle', color: '#e91e63', url: '/addon/sd_xiaoyuan/pages/help/create', key: 'enable_help' },
    { id: 'game', name: '游戏陪玩', icon: 'red-packet', color: '#ff7243', url: '/addon/sd_xiaoyuan/pages/game/publish', key: 'enable_game' },
    { id: 'parttime', name: '兼职招聘', icon: 'coupon', color: '#ff8f1f', url: '/addon/sd_xiaoyuan/pages/parttime/create', key: 'enable_parttime' },
    { id: 'companion', name: '约伴组局', icon: 'account', color: '#6c7bff', url: '/addon/sd_xiaoyuan/pages/companion/create', key: 'enable_companion' },
    { id: 'community', name: '', icon: 'chat', color: '#673ab7', url: '/addon/sd_xiaoyuan/pages/community/publish', key: 'enable_community' },
    { id: 'confession', name: '表白墙', icon: 'heart', color: '#fa709a', url: '/addon/sd_xiaoyuan/pages/confession/publish', key: 'enable_confession' },
    { id: 'secondhand', name: '', icon: 'bag', color: '#13c2c2', url: '/addon/sd_xiaoyuan/pages/secondhand/publish', key: 'enable_secondhand' },
    { id: 'lost', name: '失物招领', icon: 'search', color: '#ff4d4f', url: '/addon/sd_xiaoyuan/pages/lost_found/publish', key: 'enable_lost_found' },
    { id: 'group', name: '拼单好饭', icon: 'heart-fill', color: '#ff9500', url: '/addon/sd_xiaoyuan/pages/group/create', key: 'enable_group' }
]

const publishTypes = computed(() => {
    if (!config.value) return allPublishTypes
    return allPublishTypes
        .filter(item => config.value[item.key] !== 0)
        .map(item => {
            if (item.id === 'community') return { ...item, name: `${communityName.value}发布` }
            if (item.id === 'secondhand') return { ...item, name: `${secondhandName.value}发布` }
            return item
        })
})

const loadFeatureConfig = async () => {
    const cachedConfig = uni.getStorageSync('xiaoyuan_config')
    if (cachedConfig) {
        config.value = cachedConfig
    }
    
    try {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            config.value = res.data
            uni.setStorageSync('xiaoyuan_config', res.data)
        }
    } catch (e) {
        console.error('获取配置失败:', e)
        if (!cachedConfig) {
            config.value = {
                enable_buy: 1,
                enable_send: 1,
                enable_express: 1,
                enable_print: 1,
                enable_trash: 1,
                enable_queue: 1,
                enable_class: 1,
                enable_seat: 1,
                enable_carry: 1,
                enable_clean: 1,
                enable_help: 1,
                enable_game: 1,
                enable_parttime: 1,
                enable_companion: 1,
                enable_house: 1,
                enable_schedule: 1,
                enable_group: 1,
                enable_secondhand: 1,
                enable_lost_found: 1,
                enable_community: 1,
                enable_confession: 1,
                enable_sign: 1,
                enable_points_mall: 1,
                require_auth_publish: 1,
                secondhand_name: '闲置市场',
                community_name: '校园树洞'
            }
        }
    }
}

const selectPublishType = (item: any) => {
    ensureCanPublish('/addon/sd_xiaoyuan/pages/order/publish').then((canPublish) => {
        if (!canPublish) return
        uni.navigateTo({
            url: item.url
        })
    })
}

onShow(() => {
    loadFeatureConfig()
    uni.hideTabBar()
    nextTick(() => {
        if (wxPrivacyPopupRef.value) {
            wxPrivacyPopupRef.value.proactive()
        }
    })
})
</script>

<style lang="scss" scoped>
.publish-page {
    min-height: 100vh;
    background: #f7f7f7;
    padding: 40rpx 20rpx;
}

.publish-popup {
    background: #fff;
    padding: 20rpx;
    border-radius: 24rpx;
    box-shadow: 0 4rpx 24rpx rgba(0, 0, 0, 0.06);
    height: auto;
    min-height: auto;

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
            padding: 20rpx;
            border-radius: 16rpx;
            background: #fafafa;
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
                border: 3rpx solid transparent;
                box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
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
