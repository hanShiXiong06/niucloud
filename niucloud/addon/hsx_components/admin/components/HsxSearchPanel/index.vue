<script lang="ts">export default { name: 'HsxSearchPanel' }</script>
<script setup lang="ts">
import { computed, ref } from 'vue'
import HsxTitle from '../HsxTitle/index.vue'
import { useSearchLayout } from '../../hooks/useSearchLayout'
const props = withDefaults(defineProps<{
    title?: string
    summary?: string
    collapsible?: boolean
    modelValue?: boolean
    defaultCollapsed?: boolean
    showLayoutSwitch?: boolean
}>(), { title: '', summary: '', collapsible: true, modelValue: undefined, defaultCollapsed: false, showLayoutSwitch: true })
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const localCollapsed = ref(props.defaultCollapsed)
const { layout, labelPosition } = useSearchLayout()
const collapsed = computed({ get: () => props.collapsible && (props.modelValue ?? localCollapsed.value), set: value => { localCollapsed.value = value; emit('update:modelValue', value) } })
</script>
<template>
    <section class="hsx-search-panel" :class="[`hsx-search-panel--${layout}`, { 'hsx-search-panel--collapsed': collapsed }]">
        <HsxTitle v-if="title || collapsible || showLayoutSwitch || $slots.extra" :title="title || '搜索'" size="subsection" class="hsx-search-panel__heading">
            <template #extra>
                <span v-if="summary" class="hsx-search-panel__summary">{{ summary }}</span>
                <slot name="extra" />
                <div v-if="showLayoutSwitch" class="hsx-search-panel__layout" role="group" aria-label="搜索表单排列方式">
                    <button type="button" :aria-pressed="layout === 'horizontal'" title="字段名与输入框左右排列，记住此选择" @click="layout = 'horizontal'">左右</button>
                    <button type="button" :aria-pressed="layout === 'vertical'" title="字段名与输入框上下排列，记住此选择" @click="layout = 'vertical'">上下</button>
                </div>
                <el-button v-if="collapsible" link type="primary" :aria-expanded="!collapsed" @click="collapsed = !collapsed">{{ collapsed ? '展开筛选' : '收起筛选' }}</el-button>
            </template>
        </HsxTitle>
        <!-- 仅隐藏展示，不销毁表单，不改变已生效的筛选条件。 -->
        <div v-show="!collapsed" class="hsx-search-panel__body"><slot :layout="layout" :label-position="labelPosition" /></div>
        <div v-if="$slots.actions" class="hsx-search-panel__actions"><slot name="actions" /></div>
    </section>
</template>
<style scoped>
.hsx-search-panel { min-width: 0; margin: 16px 0; padding: 14px 16px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-sm); background: var(--hsx-bg-muted); }
.hsx-search-panel__heading { margin-bottom: 12px; }
.hsx-search-panel--collapsed .hsx-search-panel__heading { margin-bottom: 0; }
.hsx-search-panel__summary { color: var(--hsx-text-secondary); font-size: 12px; }
.hsx-search-panel__body { min-width: 0; }
.hsx-search-panel__layout { display: inline-flex; padding: 2px; border: 1px solid var(--hsx-border-color); border-radius: 6px; background: var(--hsx-bg-surface); }
.hsx-search-panel__layout button { padding: 3px 8px; border: 0; border-radius: 4px; background: transparent; color: var(--hsx-text-secondary); font-size: 12px; line-height: 18px; cursor: pointer; }
.hsx-search-panel__layout button[aria-pressed="true"] { color: var(--hsx-color-primary); background: var(--el-color-primary-light-9); }
.hsx-search-panel__layout button:focus-visible { outline: 2px solid var(--hsx-color-primary); outline-offset: 1px; }
.hsx-search-panel__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 12px; }
.hsx-search-panel :deep(.el-form--inline) { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; margin: 0; }
.hsx-search-panel :deep(.el-form--inline > .el-form-item) { display: flex; flex-direction: column; align-items: stretch; max-width: 100%; min-width: 0; margin: 0; }
.hsx-search-panel :deep(.el-form--inline > .el-form-item > .el-form-item__label) { justify-content: flex-start; width: auto !important; height: auto; padding: 0 0 4px; line-height: 20px; font-size: 12px; color: var(--hsx-text-secondary); }
.hsx-search-panel :deep(.el-form-item__content) { min-width: 0; }
.hsx-search-panel :deep(.el-form--inline > .hsx-fold) { flex-basis: 100%; width: 100%; }
.hsx-search-panel :deep(.el-form--inline > .hsx-fold > .hsx-fold__body) { display: flex; flex-wrap: wrap; gap: 12px 16px; align-items: flex-end; }
.hsx-search-panel :deep(.el-form--inline > .hsx-fold > .hsx-fold__body > .el-form-item) { margin: 0; max-width: 100%; }
.hsx-search-panel :deep(.el-input), .hsx-search-panel :deep(.el-select), .hsx-search-panel :deep(.el-date-editor) { max-width: 100%; }
.hsx-search-panel--horizontal :deep(.el-form .el-form-item) { display: flex; flex-direction: row; align-items: center; }
.hsx-search-panel--horizontal :deep(.el-form .el-form-item > .el-form-item__label) { flex: none; height: auto; width: auto !important; padding: 0 8px 0 0; margin: 0; line-height: 20px; white-space: nowrap; font-size: 12px; }
.hsx-search-panel--horizontal :deep(.el-form .el-form-item > .el-form-item__content) { flex: 1; margin-left: 0 !important; }
.hsx-search-panel--vertical :deep(.el-form .el-form-item) { display: flex; flex-direction: column; align-items: stretch; }
.hsx-search-panel--vertical :deep(.el-form .el-form-item > .el-form-item__label) { flex: none; height: auto; width: auto !important; padding: 0 0 4px; margin: 0; line-height: 20px; text-align: left; justify-content: flex-start; font-size: 12px; }
.hsx-search-panel--vertical :deep(.el-form .el-form-item > .el-form-item__content) { margin-left: 0 !important; }
@media (max-width: 640px) { .hsx-search-panel { padding: 12px; } .hsx-search-panel :deep(.el-form--inline > .el-form-item) { width: 100%; } .hsx-search-panel :deep(.el-form-item__content > .el-input), .hsx-search-panel :deep(.el-form-item__content > .el-select), .hsx-search-panel :deep(.el-form-item__content > .el-date-editor) { width: 100% !important; } }
</style>
