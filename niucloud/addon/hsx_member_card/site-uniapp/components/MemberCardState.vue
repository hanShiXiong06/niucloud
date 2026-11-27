<template>
    <view class="mc-state">
        <u-loading-icon v-if="loading" size="24" />
        <u-icon v-else :name="error ? 'info-circle' : 'list'" :color="error ? '#b57a31' : '#a7b3c3'" size="34" />
        <view class="mc-state__text">{{ loading ? '正在加载…' : error || text }}</view>
        <view v-if="!loading && (error || action)" class="mc-state__action">
            <MemberCardButton :text="action || '重新加载'" @click="$emit('action')" />
        </view>
    </view>
</template>
<script setup lang="ts">
import MemberCardButton from './MemberCardButton.vue'
withDefaults(defineProps<{ loading?: boolean; error?: string; text?: string; action?: string }>(), {
    loading: false,
    error: '',
    text: '暂无记录',
    action: ''
})
defineEmits(['action'])
</script>
