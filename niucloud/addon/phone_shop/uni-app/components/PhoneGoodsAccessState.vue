<template>
    <view class="goods-access-state">
        <u-loading-icon v-if="status === 'checking' || status === 'login'" mode="circle" size="28" />
        <template v-else-if="status === 'error'">
            <text class="goods-access-state__title">暂时无法打开商品</text>
            <text v-if="message" class="goods-access-state__message">{{ message }}</text>
            <button class="goods-access-state__button" @click="$emit('retry')">重新加载</button>
        </template>
    </view>
</template>

<script setup lang="ts">
import type { GoodsPageAccessStatus } from '@/addon/phone_shop/hooks/useGoodsPageAccess'
defineProps<{status: GoodsPageAccessStatus, message?: string}>()
defineEmits(['retry'])
</script>

<style lang="scss" scoped>
.goods-access-state { min-height: 60vh; padding: 80rpx 40rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; box-sizing: border-box; }
.goods-access-state__title { margin-top: 24rpx; color: #303133; font-size: 30rpx; font-weight: 600; }
.goods-access-state__message { max-width: 580rpx; margin-top: 16rpx; color: #737983; font-size: 26rpx; line-height: 1.6; }
.goods-access-state__button { margin-top: 32rpx; padding: 0 56rpx; border-radius: 40rpx; background: var(--primary-color, #1255e7); color: #fff; font-size: 28rpx; }
.goods-access-state__button::after { border: 0; }
</style>
