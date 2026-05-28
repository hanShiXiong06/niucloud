<template>
    <view class="recycle-page-header" :class="{ compact }" :style="headerStyle">
        <view class="recycle-page-header__content" :style="contentStyle">
            <view class="recycle-page-header__side" :style="sideStyle">
                <view v-if="showBack" class="recycle-page-header__back" @tap="handleNavAction">
                    <text class="nc-iconfont recycle-page-header__back-icon" :class="navIconClass"></text>
                </view>
            </view>

            <view class="recycle-page-header__center" :style="centerStyle">
                <slot>
                    <text class="recycle-page-header__title">{{ title }}</text>
                    <text v-if="subtitle" class="recycle-page-header__subtitle">{{ subtitle }}</text>
                </slot>
            </view>

            <view class="recycle-page-header__side" :style="sideStyle">
                <slot name="right"></slot>
            </view>
        </view>
    </view>
    <view v-if="fill" class="recycle-page-header__placeholder" :style="placeholderStyle"></view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { getRecycleNavbarMetrics } from '@/addon/recycle/utils/navbar'

const props = withDefaults(defineProps<{
    title: string
    subtitle?: string
    showBack?: boolean
    fill?: boolean
    compact?: boolean
}>(), {
    subtitle: '',
    showBack: true,
    fill: true,
    compact: false
})

const navbarMetrics = getRecycleNavbarMetrics()
const navStatusTopPx = navbarMetrics.statusTopPx
const navBottomGapPx = navbarMetrics.bottomGapPx
const navContentHeightPx = navbarMetrics.contentHeightPx
const navBarHeightPx = navbarMetrics.navbarHeightPx
const navSideWidthRpx = navbarMetrics.sideWidthRpx

const headerStyle = computed(() => `height:${ navBarHeightPx }px;`)
const placeholderStyle = computed(() => `height:${ navBarHeightPx }px;`)
const contentStyle = computed(() => [
    `height:${ navContentHeightPx }px`,
    `padding-top:${ navStatusTopPx }px`,
    `padding-bottom:${ navBottomGapPx }px`,
    'padding-left:18rpx',
    'padding-right:18rpx'
].join(';') + ';')
//width:${ navSideWidthRpx }rpx;
const sideStyle = computed(() => `height:${ navContentHeightPx }px;`)
const centerStyle = computed(() => `height:${ navContentHeightPx }px;`)

const canGoBack = computed(() => {
    const pages = getCurrentPages()
    return pages.length > 1
})
const navIconClass = computed(() => canGoBack.value ? 'nc-icon-zuoV6xx' : 'nc-icon-shouyeV6xx')

const goHome = () => {
    uni.reLaunch({ url: '/app/pages/index/index' })
}

const handleNavAction = () => {
    if (!canGoBack.value) {
        goHome()
        return
    }

    uni.navigateBack({
        delta: 1,
        fail: goHome
    })
}

defineExpose({
    navBarHeightPx
})
</script>

<style scoped lang="scss">
.recycle-page-header {
    position: fixed;
    left: 0;
    right: 0;
    top: 0;
    z-index: 999;
    background: #2563eb;
    color: #fff;
    box-shadow: 0 8rpx 20rpx rgba(31, 41, 55, 0.14);
}

.recycle-page-header.compact {
    background: #2563eb;
}

.recycle-page-header__content {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-sizing: content-box;
}

.recycle-page-header__side {
    flex-shrink: 0;
    display: flex;
    align-items: center;
}

.recycle-page-header__side:last-child {
    justify-content: flex-end;
}

.recycle-page-header__back {
    width: 64rpx;
    height: 64rpx;
    border-radius: 32rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.recycle-page-header__back-icon {
    font-size: 40rpx;
    color: #fff;
}

.recycle-page-header__center {
    position: absolute;
    left: 40%;
    transform: translateX(-50%);
    width: min(440rpx, 68vw);
    display: flex;
    flex-direction: column;
    justify-content: center;
    pointer-events: auto;
}

.recycle-page-header__title {
    display: block;
    width: 100%;
    font-size: 31rpx;
    line-height: 40rpx;
    font-weight: 800;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.recycle-page-header__subtitle {
    display: block;
    width: 100%;
    margin-top: 2rpx;
    font-size: 20rpx;
    line-height: 28rpx;
    color: rgba(255, 255, 255, 0.78);
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.recycle-page-header__placeholder {
    width: 100%;
}
</style>
