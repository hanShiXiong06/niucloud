<template>
    <view class="mc-between" style="margin-bottom: 18rpx">
        <view class="mc-grow">
            <text class="mc-section-title">{{ title }}</text>
            <view v-if="subtitle" class="mc-sub">{{ subtitle }}</view>
        </view>
        <slot name="right" />
    </view>
</template>
<script setup lang="ts">
withDefaults(defineProps<{ title: string; subtitle?: string; icon?: string; tone?: string }>(), {
    subtitle: '',
    icon: '',
    tone: 'blue'
})
</script>
