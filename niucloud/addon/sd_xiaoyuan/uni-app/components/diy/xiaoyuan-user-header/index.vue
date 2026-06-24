<template>
    <view class="diy-xiaoyuan-user-header" :style="headerWrapStyle">
        <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
        <view
            class="user-header"
            :style="headerRowStyle"
        >
            <view class="user-main" @click="onHeaderClick">
                <image class="avatar" :src="img(avatarUrl)" mode="aspectFill"></image>
                <view class="user-info">
                    <text class="nickname">{{ displayName }}</text>
                    <view class="credit-row" v-if="showMemberRow">
                        <text class="member-id">ID: {{ displayMemberId }}</text>
                        <view class="credit-badge" v-if="diyComponent.showCredit !== false" @click.stop="goCredit">
                            <u-icon name="star-fill" size="14" color="#ff9500"></u-icon>
                            <text>信誉 {{ creditScore }}分</text>
                            <u-icon name="arrow-right" size="12" color="#ff9500"></u-icon>
                        </view>
                    </view>
                    <text class="desc">{{ displayDesc }}</text>
                </view>
            </view>
            <view class="settings-btn" @click.stop="goSettings">
                <u-icon name="setting" size="24" color="#333"></u-icon>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { img, redirect } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import { getCreditInfo } from '../../../api/xiaoyuan'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()
const memberStore = useMemberStore()

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const menuButtonRight = ref(0)
const creditScore = ref(100)

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index] || props.component
    }
    return props.component
})

const userInfo = computed(() => memberStore.info || {})
const isLoggedIn = computed(() => !!userInfo.value?.member_id)

const displayName = computed(() => {
    if (diyStore.mode == 'decorate') return '校园帮用户'
    return userInfo.value.nickname || '未登录'
})
const displayMemberId = computed(() => {
    if (diyStore.mode == 'decorate') return '10001'
    return userInfo.value.member_id || ''
})
const showMemberRow = computed(() => diyStore.mode == 'decorate' || isLoggedIn.value)
const displayDesc = computed(() => {
    if (diyStore.mode == 'decorate') return diyComponent.value.welcomeText || '欢迎使用校园帮'
    if (!isLoggedIn.value) return diyComponent.value.loginTip || '点击登录'
    return diyComponent.value.welcomeText || '欢迎使用校园帮'
})
const avatarUrl = computed(() => {
    if (diyStore.mode == 'decorate') return '/static/resource/images/default_headimg.png'
    return userInfo.value.headimg || userInfo.value.avatar || '/static/resource/images/default_headimg.png'
})

const headerWrapStyle = computed(() => {
    const s = diyComponent.value.bgStartColor || '#c0fe95'
    const e = diyComponent.value.bgEndColor || '#f7f7f7'
    return `background: linear-gradient(135deg, ${s}, ${e}); margin-bottom: -30rpx;`
})

const headerRowStyle = computed(() => {
    const style: Record<string, string> = {
        minHeight: navBarHeight.value + 'px'
    }
    if (menuButtonRight.value > 0) {
        style.paddingRight = (menuButtonRight.value + 12) + 'px'
    }
    return style
})

const resolveUrl = (link: any, fallback: string) => {
    if (link && typeof link === 'object' && link.url) return link.url
    if (typeof link === 'string' && link) return link
    return fallback
}

const goSettings = () => {
    if (diyStore.mode == 'decorate') return
    redirect({ url: resolveUrl(diyComponent.value.settingsUrl, '/app/pages/setting/index') })
}

const goCredit = () => {
    if (diyStore.mode == 'decorate') return
    redirect({ url: resolveUrl(diyComponent.value.creditUrl, '/addon/sd_xiaoyuan/pages/credit/log') })
}

const onHeaderClick = () => {
    if (diyStore.mode == 'decorate') return
    if (!isLoggedIn.value) {
        useLogin().setLoginBack({ url: '/addon/sd_xiaoyuan/pages/user/index' })
    }
}

const loadCredit = async () => {
    if (diyStore.mode == 'decorate' || !isLoggedIn.value) return
    try {
        const res: any = await getCreditInfo()
        if (res.code === 1 && res.data) {
            creditScore.value = res.data.credit_score ?? 100
        }
    } catch (e) {
        console.error(e)
    }
}

const initNav = () => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    menuButtonRight.value = sysInfo.windowWidth - menuButton.left
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
}

onMounted(() => {
    initNav()
    loadCredit()
})

onShow(() => {
    loadCredit()
})
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-user-header {
    .user-header {
        display: flex;
        align-items: center;
        padding: 16rpx 30rpx 60rpx;
        box-sizing: border-box;
    }
    .user-main {
        flex: 1;
        display: flex;
        align-items: center;
        min-width: 0;
    }
    .settings-btn {
        flex-shrink: 0;
        width: 60rpx;
        height: 60rpx;
        margin-left: 16rpx;
        background: rgba(255, 255, 255, 0.6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .avatar {
        width: 120rpx;
        height: 120rpx;
        border-radius: 50%;
        border: 4rpx solid rgba(255, 255, 255, 0.8);
        margin-right: 24rpx;
        background: #fff;
        flex-shrink: 0;
    }
    .user-info {
        flex: 1;
        min-width: 0;
            .nickname {
                display: block;
                font-size: 34rpx;
                font-weight: 900;
                color: #333;
                margin-bottom: 8rpx;
            }
            .credit-row {
                display: flex;
                align-items: center;
                margin-bottom: 4rpx;
            }
            .member-id {
                font-size: 24rpx;
                color: #666;
                margin-right: 16rpx;
            }
            .credit-badge {
                display: flex;
                align-items: center;
                background: rgba(255, 149, 0, 0.15);
                border-radius: 20rpx;
                padding: 2rpx 14rpx;
                text {
                    font-size: 22rpx;
                    color: #ff9500;
                    margin-left: 4rpx;
                    font-weight: bold;
                }
            }
            .desc {
                display: block;
                font-size: 26rpx;
                color: #666;
            }
    }
}
</style>
