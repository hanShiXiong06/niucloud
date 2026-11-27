<template>
    <HsxPage :title="title" :subtitle="subtitle" padding="compact" content-width="wide">
        <template #extra><div class="ic-actions"><slot name="actions" /></div></template>
        <section class="ic-surface">
            <div v-if="$slots.filters" class="ic-filters"><slot name="filters" /></div>
            <el-result v-if="error" icon="warning" title="未能加载" :sub-title="error">
                <template #extra><el-button @click="$emit('reload')">重新加载</el-button></template>
            </el-result>
            <div v-else v-loading="loading" :aria-busy="loading"><slot /></div>
            <div v-if="!error && total > 0" class="ic-pager"><span>共 {{ total }} 条</span>
                <el-pagination background layout="prev, pager, next" :current-page="page" :page-size="size" :total="total" @current-change="$emit('page', $event)" />
            </div>
        </section>
    </HsxPage>
</template>
<script setup lang="ts">
import HsxPage from '@/addon/hsx_components/components/HsxPage/index.vue'
withDefaults(defineProps<{title: string; subtitle?: string; loading?: boolean; error?: string; total?: number; page?: number; size?: number}>(), { total: 0, page: 1, size: 20 })
defineEmits(['reload', 'page'])
</script>
<style>
.ic-surface { background:var(--el-bg-color,#fff); border:1px solid var(--el-border-color-lighter,#e5e7eb); border-radius:8px; overflow:hidden; }
.ic-actions,.ic-filters,.ic-inline,.ic-pager { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.ic-filters { padding:16px; border-bottom:1px solid var(--el-border-color-lighter); }
.ic-filters .el-input { width:220px; max-width:100% } .ic-filters .el-select { width:150px }
.ic-filters .el-select .el-input { width:100% } .ic-filters .el-date-editor { flex:0 1 230px }
.ic-filters .el-button+.el-button,.ic-actions .el-button+.el-button,.ic-inline .el-button+.el-button { margin-left:0; }
.ic-pager { justify-content:flex-end; padding:14px 16px; color:var(--el-text-color-secondary); font-size:13px }
.ic-muted,.ic-help { color:var(--el-text-color-secondary); font-size:13px; line-height:1.6; } .ic-help { margin:5px 0 0; width:100%; }
.ic-state { font-weight:600; } .ic-form .el-select,.ic-form .el-input-number { width:100% } .ic-form .el-alert { margin-bottom:16px; }
.ic-summary { display:flex; flex-wrap:wrap; gap:22px; padding:12px 16px; background:var(--el-fill-color-lighter); }
.ic-detail { display:grid; grid-template-columns:105px minmax(0,1fr); gap:12px 10px; line-height:1.6; font-size:14px; margin:0 0 20px; }
.ic-detail dt {color:var(--el-text-color-secondary)} .ic-detail dd {margin:0; overflow-wrap:anywhere}
.ic-code { white-space:pre-wrap; overflow-wrap:anywhere; max-height:45vh; overflow:auto; background:var(--el-fill-color-lighter); padding:12px; border-radius:5px; font-size:12px; }
.ic-drawer .el-drawer__header { margin-bottom:0; padding-bottom:16px; border-bottom:1px solid var(--el-border-color-lighter); }
.ic-drawer .el-drawer__footer {border-top:1px solid var(--el-border-color-lighter)}
</style>
