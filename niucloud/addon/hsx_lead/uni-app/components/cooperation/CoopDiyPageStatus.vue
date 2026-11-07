<template>
    <loading-page :loading="loading && !error" />
    <view v-if="error" class="coop-diy-page-status"><CoopState :error="error" @retry="$emit('retry')" /></view>
</template>
<script setup lang="ts">
import CoopState from './CoopState.vue'
defineProps<{ loading: boolean; error: string }>()
defineEmits<{ (event: 'retry'): void }>()
</script>
<style scoped>.coop-diy-page-status { padding: 48px 20px 24px; }</style>
