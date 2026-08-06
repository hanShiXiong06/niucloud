<template>
    <view v-if="subtitle || serial" class="phone-goods-meta" :class="{ 'phone-goods-meta--compact': compact }">
        <view v-if="subtitle" class="phone-goods-meta__line">
            <text class="phone-goods-meta__subtitle">{{ subtitle }}</text>
        </view>
        <view v-if="serial" class="phone-goods-meta__line">
            <text class="phone-goods-meta__imei">IMEI {{ serial }}</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    subtitle?: string
    imei?: string | number
    compact?: boolean
}>(), {
    subtitle: '',
    imei: '',
    compact: false
})

const subtitle = computed(() => String(props.subtitle || '').trim())
const serial = computed(() => String(props.imei || '').trim())
</script>

<style lang="scss" scoped>
.phone-goods-meta {
    min-width: 0;
    overflow: hidden;
    color: #94a3b8;
    font-size: 22rpx;
    line-height: 34rpx;
}

.phone-goods-meta__line {
    min-width: 0;
    height: 34rpx;
    margin-top: 6rpx;
    display: flex;
    align-items: center;
    overflow: hidden;
    white-space: nowrap;
}

.phone-goods-meta__subtitle {
    min-width: 0;
    overflow: hidden;
    flex: 1;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.phone-goods-meta__divider {
    margin: 0 8rpx;
    flex-shrink: 0;
    color: #cbd5e1;
}

.phone-goods-meta__imei {
    flex-shrink: 0;
    color: #64748b;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 21rpx;
}

.phone-goods-meta--compact {
    font-size: 20rpx;
    line-height: 30rpx;
}

.phone-goods-meta--compact .phone-goods-meta__line {
    height: 30rpx;
    margin-top: 3rpx;
}

.phone-goods-meta--compact .phone-goods-meta__imei {
    font-size: 20rpx;
}
</style>
