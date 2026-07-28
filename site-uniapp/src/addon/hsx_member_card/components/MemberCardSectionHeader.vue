<template>
    <view class="flex min-w-0 items-center gap-[14rpx]">
        <view
            v-if="icon"
            class="flex h-[54rpx] w-[54rpx] flex-none items-center justify-center rounded-[16rpx]"
            :style="{ backgroundColor: iconBackground }"
        >
            <u-icon :name="icon" :color="iconColor" size="18" />
        </view>
        <view class="flex min-w-0 flex-1 flex-col gap-[5rpx]">
            <text class="truncate text-[28rpx] font-semibold leading-[1.35] text-[#334155]">{{ title }}</text>
            <text v-if="subtitle" class="truncate text-[22rpx] leading-[1.4] text-[#8290a5]">{{ subtitle }}</text>
        </view>
        <view v-if="$slots.right" class="flex-none"><slot name="right" /></view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    title: string
    subtitle?: string
    icon?: string
    tone?: 'blue' | 'green' | 'orange' | 'violet' | 'gray'
}>(), {
    subtitle: '',
    icon: '',
    tone: 'blue',
})

const iconColor = computed(() => ({
    blue: '#2563eb',
    green: '#16a34a',
    orange: '#d97706',
    violet: '#7c3aed',
    gray: '#64748b',
}[props.tone] || '#2563eb'))
const iconBackground = computed(() => ({
    blue: '#eff6ff',
    green: '#ecfdf3',
    orange: '#fff7ed',
    violet: '#f5f3ff',
    gray: '#f1f5f9',
}[props.tone] || '#eff6ff'))
</script>
