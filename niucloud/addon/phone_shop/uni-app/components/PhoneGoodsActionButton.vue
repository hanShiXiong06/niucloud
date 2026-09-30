<template>
    <button v-if="action" class="goods-action" :class="{ 'goods-action--icon': iconOnly }" :aria-label="action.text" @click.stop="emit('action')">
        <text v-if="iconOnly" class="nc-iconfont" :class="action.style === 'style-2' ? 'nc-icon-tianjiaV6xx' : 'nc-icon-gouwucheV6xx6'"></text>
        <text v-else>{{ action.text }}</text>
    </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { GoodsCardAction } from '@/addon/phone_shop/utils/goods-card'

const props = defineProps<{ action: GoodsCardAction | null }>()
const emit = defineEmits<{ (event: 'action'): void }>()
const iconOnly = computed(() => props.action?.event === 'cart' && props.action.style !== 'style-1')
</script>

<style scoped lang="scss">
.goods-action {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    margin: 0;
    padding: 0 16rpx;
    min-width: 76rpx;
    height: 46rpx;
    border: 0;
    border-radius: 24rpx;
    background: var(--primary-color);
    color: #fff;
    font-size: 22rpx;
    line-height: 46rpx;
    white-space: nowrap;
    &::after { border: 0; }
    &--icon { min-width: 46rpx; width: 46rpx; padding: 0; font-size: 30rpx; }
}
</style>
