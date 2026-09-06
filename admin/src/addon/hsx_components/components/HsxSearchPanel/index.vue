<script lang="ts">export default { name: 'HsxSearchPanel' }</script>
<script setup lang="ts">
import { computed, ref } from 'vue'
import HsxTitle from '../HsxTitle/index.vue'
const props = withDefaults(defineProps<{
    title?: string
    summary?: string
    collapsible?: boolean
    modelValue?: boolean
    defaultCollapsed?: boolean
}>(), { title: '', summary: '', collapsible: false, modelValue: undefined, defaultCollapsed: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const localCollapsed = ref(props.defaultCollapsed)
const collapsed = computed({ get: () => props.collapsible && (props.modelValue ?? localCollapsed.value), set: value => { localCollapsed.value = value; emit('update:modelValue', value) } })
</script>
<template>
    <section class="hsx-search-panel" :class="{ 'hsx-search-panel--collapsed': collapsed }">
        <HsxTitle v-if="title || collapsible || $slots.extra" :title="title || '筛选条件'" size="subsection" class="hsx-search-panel__heading">
            <template #extra>
                <span v-if="summary" class="hsx-search-panel__summary">{{ summary }}</span>
                <slot name="extra" />
                <el-button v-if="collapsible" link type="primary" :aria-expanded="!collapsed" @click="collapsed = !collapsed">{{ collapsed ? '展开筛选' : '收起筛选' }}</el-button>
            </template>
        </HsxTitle>
        <!-- 仅隐藏展示，不销毁表单，不改变已生效的筛选条件。 -->
        <div v-show="!collapsed" class="hsx-search-panel__body"><slot /></div>
        <div v-if="$slots.actions" class="hsx-search-panel__actions"><slot name="actions" /></div>
    </section>
</template>
<style scoped>
.hsx-search-panel { min-width: 0; margin: 16px 0; padding: 14px 16px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-sm); background: var(--hsx-bg-muted); }
.hsx-search-panel__heading { margin-bottom: 12px; }
.hsx-search-panel--collapsed .hsx-search-panel__heading { margin-bottom: 0; }
.hsx-search-panel__summary { color: var(--hsx-text-secondary); font-size: 12px; }
.hsx-search-panel__body { min-width: 0; }
.hsx-search-panel__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 12px; }
.hsx-search-panel :deep(.el-form--inline) { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; margin: 0; }
.hsx-search-panel :deep(.el-form--inline > .el-form-item) { display: flex; flex-direction: column; align-items: stretch; max-width: 100%; min-width: 0; margin: 0; }
.hsx-search-panel :deep(.el-form--inline > .el-form-item > .el-form-item__label) { justify-content: flex-start; width: auto !important; height: auto; padding: 0 0 4px; line-height: 20px; font-size: 12px; color: var(--hsx-text-secondary); }
.hsx-search-panel :deep(.el-form-item__content) { min-width: 0; }
.hsx-search-panel :deep(.el-input), .hsx-search-panel :deep(.el-select), .hsx-search-panel :deep(.el-date-editor) { max-width: 100%; }
@media (max-width: 640px) { .hsx-search-panel { padding: 12px; } .hsx-search-panel :deep(.el-form--inline > .el-form-item) { width: 100%; } .hsx-search-panel :deep(.el-form-item__content > .el-input), .hsx-search-panel :deep(.el-form-item__content > .el-select), .hsx-search-panel :deep(.el-form-item__content > .el-date-editor) { width: 100% !important; } }
</style>
