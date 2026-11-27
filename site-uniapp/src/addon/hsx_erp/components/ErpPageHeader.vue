<template>
    <!-- 原生客户端由 pages.json 导航栏提供标题和返回能力，避免重复显示两层头部。 -->
    <!-- ERP 小程序页面使用 pages.json 原生导航栏；仅 H5 需要补充页面头部。 -->
    <!-- #ifdef H5 -->
    <view class="erp-page-header" :style="headerStyle">
        <view class="erp-page-header__content" :style="contentStyle">
            <view class="erp-page-header__side">
                <view v-if="showBack" class="erp-page-header__back" @tap="handleBack">
                    <u-icon :name="canGoBack ? 'arrow-left' : 'home'" size="22" color="#0f172a" />
                </view>
            </view>
            <view class="erp-page-header__center">
                <text class="erp-page-header__title">{{ title }}</text>
                <text v-if="subtitle" class="erp-page-header__subtitle">{{ subtitle }}</text>
            </view>
            <view class="erp-page-header__side erp-page-header__right">
                <slot name="right"></slot>
            </view>
        </view>
    </view>
    <view v-if="fill" :style="placeholderStyle"></view>
    <!-- #endif -->
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { getErpNavbarMetrics } from '@/addon/hsx_erp/utils/navbar'

withDefaults(defineProps<{
    title: string
    subtitle?: string
    showBack?: boolean
    fill?: boolean
}>(), {
    subtitle: '',
    showBack: true,
    fill: true
})

const metrics = getErpNavbarMetrics()
const headerStyle = computed(() => `height:${metrics.navbarHeightPx}px;`)
const placeholderStyle = computed(() => `height:${metrics.navbarHeightPx}px;`)
const contentStyle = computed(() => [
    `height:${metrics.contentHeightPx}px`,
    `padding-top:${metrics.statusTopPx}px`,
    `padding-bottom:${metrics.bottomGapPx}px`,
].join(';') + ';')

const canGoBack = computed(() => getCurrentPages().length > 1)

function goHome() {
    uni.reLaunch({ url: '/app/pages/index/index' })
}

function handleBack() {
    if (!canGoBack.value) {
        goHome()
        return
    }
    uni.navigateBack({ delta: 1, fail: goHome })
}
</script>

<style scoped lang="scss">
.erp-page-header {
    position: fixed;
    left: 0;
    right: 0;
    top: 0;
    z-index: 999;
    background: #fff;
    color: #0f172a;
    border-bottom: 1rpx solid #eef2f7;
    box-shadow: 0 6rpx 18rpx rgba(15, 23, 42, 0.04);
}

.erp-page-header__content {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-left: 18rpx;
    padding-right: 18rpx;
    box-sizing: content-box;
}

.erp-page-header__side {
    width: 96rpx;
    height: 100%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
}

.erp-page-header__right {
    justify-content: flex-end;
}

.erp-page-header__back {
    width: 68rpx;
    height: 68rpx;
    border-radius: 34rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.erp-page-header__center {
    position: absolute;
    left: 120rpx;
    right: 120rpx;
    top: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.erp-page-header__title {
    max-width: 100%;
    font-size: 32rpx;
    line-height: 42rpx;
    font-weight: 700;
    color: #0f172a;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.erp-page-header__subtitle {
    max-width: 100%;
    margin-top: 2rpx;
    font-size: 22rpx;
    line-height: 30rpx;
    color: #64748b;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
