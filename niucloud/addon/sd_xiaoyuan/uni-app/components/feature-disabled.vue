<template>
    <view class="feature-disabled" v-if="show">
        <u-icon name="info-circle" size="60" color="#ccc"></u-icon>
        <text class="tip-text">{{ text || '功能已下架' }}</text>
    </view>
</template>

<script setup lang="ts">
defineProps<{
    show: boolean
    text?: string
}>()
</script>

<style lang="scss" scoped>
.feature-disabled {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 40rpx;
    
    .tip-text {
        display: block;
        margin-top: 20rpx;
        color: #999;
        font-size: 28rpx;
    }
}
</style>
