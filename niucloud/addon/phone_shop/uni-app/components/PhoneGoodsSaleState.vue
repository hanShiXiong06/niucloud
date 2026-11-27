<template>
    <view v-if="visible" class="sale-state" :class="`sale-state--${state.code || 'unavailable'}`">
        <text>{{ state.name || '暂不可售' }}</text>
        <text v-if="showReason && state.reason" class="sale-state__reason">· {{ state.reason }}</text>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    state?: Record<string, any>
    showSellable?: boolean
    showReason?: boolean
}>(), {
    state: () => ({}),
    showSellable: false,
    showReason: true
})

const state = computed(() => props.state || {})
const visible = computed(() => props.showSellable || state.value.code !== 'sellable')
</script>

<style scoped lang="scss">
.sale-state {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    margin-top: 6rpx;
    padding: 3rpx 10rpx;
    border-radius: 999rpx;
    background: #fff1f2;
    color: #e11d48;
    font-size: 20rpx;
    line-height: 30rpx;
}

.sale-state--locked {
    background: #fff7ed;
    color: #d97706;
}

.sale-state--sold {
    background: #f1f5f9;
    color: #64748b;
}

.sale-state--sellable {
    background: #ecfdf5;
    color: #059669;
}

.sale-state__reason {
    margin-left: 3rpx;
    opacity: .82;
}
</style>
