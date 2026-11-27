<template>
    <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="catalog-source">
        <el-button link type="primary" @click="$emit('retry')">重新加载</el-button>
    </el-alert>
    <el-alert v-else-if="policy" :type="policy.read_only ? 'info' : 'warning'" :closable="false" show-icon class="catalog-source">
        <template #title>{{ policy.site_id === 0 ? '公共资料库 · 修改对所有使用站点生效' : policy.effective ? '公共资料库' : '本站资料库' }}</template>
        {{ policy.site_id === 0 ? '只管理型号和质检模板；订单、设备、库存、价格和打印配置仍归各站点。' : policy.message }}
    </el-alert>
</template>
<script setup lang="ts">
import type { CatalogPolicy } from '@/addon/hsx_recycle/api/device_catalog'
defineProps<{ policy?: CatalogPolicy; error?: string }>()
defineEmits<{ (event: 'retry'): void }>()
</script>
<style scoped>.catalog-source { margin-bottom: 14px; }</style>
