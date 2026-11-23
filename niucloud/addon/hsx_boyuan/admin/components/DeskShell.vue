<template>
    <HsxPage class="boyuan-desk" :title="title" :subtitle="subtitle" eyebrow="博远 ERP 对接" content-width="standard" padding="comfortable">
        <template v-if="$slots.extra" #extra><slot name="extra" /></template>
        <slot />
    </HsxPage>
</template>
<script setup lang="ts">
import HsxPage from '@/addon/hsx_components/components/HsxPage/index.vue'
import '@/addon/hsx_components/styles/theme.scss'
defineProps<{title: string; subtitle?: string}>()
</script>
<style scoped>
.boyuan-desk {min-height:72vh;background:var(--el-bg-color-page)}
.boyuan-desk :deep(.desk-card){background:var(--el-bg-color);border:1px solid var(--el-border-color-lighter);border-radius:10px;padding:24px;min-width:0}
.boyuan-desk :deep(.desk-card h2){margin:0;color:var(--el-text-color-primary);font-size:18px;line-height:1.5;font-weight:650}
.boyuan-desk :deep(.desk-caption){font-size:13px;line-height:1.65;color:var(--el-text-color-secondary);margin:5px 0 0}
.boyuan-desk :deep(.desk-split){display:grid;grid-template-columns:minmax(0,2fr) minmax(250px,.85fr);align-items:start;gap:16px}
.boyuan-desk :deep(.desk-retry){margin:9px 0}
@media(max-width:960px){.boyuan-desk :deep(.desk-split){grid-template-columns:1fr}}
@media(max-width:560px){.boyuan-desk :deep(.desk-card){padding:16px}}
</style>
