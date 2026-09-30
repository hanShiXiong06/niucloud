<template>
    <view class="price-picker" :style="themeColor()">
        <view class="price-picker__inputs">
            <view class="price-picker__input">
                <text class="price-picker__currency">¥</text>
                <input :value="startValue" type="digit" placeholder="最低价" @input="updateStart" />
            </view>
            <view class="price-picker__divider"></view>
            <view class="price-picker__input">
                <text class="price-picker__currency">¥</text>
                <input :value="endValue" type="digit" placeholder="最高价" @input="updateEnd" />
            </view>
        </view>
        <view v-if="ranges.length" class="price-picker__ranges">
            <view
                v-for="range in ranges"
                :key="range.label"
                class="price-picker__range"
                :class="{ 'price-picker__range--active': isActive(range) }"
                @click="toggleRange(range)"
            >
                {{ range.label }}
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
    startValue: string | number
    endValue: string | number
    ranges?: Array<{ label: string; min: string | number; max: string | number }>
}>(), {
    ranges: () => []
})

const emit = defineEmits(['update:startValue', 'update:endValue'])

const normalizeInput = (event: any) => event?.detail?.value ?? ''
const updateStart = (event: any) => emit('update:startValue', normalizeInput(event))
const updateEnd = (event: any) => emit('update:endValue', normalizeInput(event))

const isActive = (range: any) => String(props.startValue) === String(range.min) && String(props.endValue) === String(range.max)
const toggleRange = (range: any) => {
    const active = isActive(range)
    emit('update:startValue', active ? '' : range.min)
    emit('update:endValue', active ? '' : range.max)
}
</script>

<style lang="scss" scoped>
.price-picker__inputs {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 34rpx minmax(0, 1fr);
    align-items: center;
}

.price-picker__input {
    height: 76rpx;
    padding: 0 20rpx;
    display: flex;
    align-items: center;
    box-sizing: border-box;
    border: 2rpx solid #edf1f6;
    border-radius: 16rpx;
    background: #f7f9fc;
}

.price-picker__currency {
    margin-right: 8rpx;
    color: #94a3b8;
    font-size: 24rpx;
}

.price-picker__input input {
    min-width: 0;
    flex: 1;
    color: #1e293b;
    font-size: 25rpx;
}

.price-picker__divider {
    width: 14rpx;
    height: 2rpx;
    margin: 0 auto;
    background: #cbd5e1;
}

.price-picker__ranges {
    margin-top: 18rpx;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14rpx;
}

.price-picker__range {
    min-height: 64rpx;
    padding: 0 10rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    border: 2rpx solid transparent;
    border-radius: 14rpx;
    background: #f5f7fa;
    color: #475569;
    font-size: 22rpx;
    text-align: center;
}

.price-picker__range--active {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: rgba(var(--primary-color-rgb, 18, 85, 231), 0.08);
    font-weight: 600;
}
</style>
