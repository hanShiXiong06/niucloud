<script lang="ts">
export default { name: 'HsxPage' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import HsxTitle from '../HsxTitle/index.vue'

type PagePadding = 'none' | 'compact' | 'default' | 'comfortable'
type ContentWidth = 'fluid' | 'wide' | 'standard' | 'narrow' | string

const props = withDefaults(defineProps<{
    title?: string
    subtitle?: string
    eyebrow?: string
    loading?: boolean
    surface?: boolean
    padding?: PagePadding
    contentWidth?: ContentWidth
    headerDivider?: boolean
    headerSticky?: boolean
}>(), {
    title: '',
    subtitle: '',
    eyebrow: '',
    loading: false,
    surface: false,
    padding: 'default',
    contentWidth: 'fluid',
    headerDivider: false,
    headerSticky: false
})

const WIDTHS: Record<string, string> = {
    fluid: '100%',
    wide: '1680px',
    standard: '1440px',
    narrow: '1120px'
}

const pageStyle = computed(() => ({
    '--hsx-page-content-width': WIDTHS[props.contentWidth] || props.contentWidth
}))
</script>

<template>
    <section
        class="hsx-page"
        :class="[
            `hsx-page--padding-${padding}`,
            { 'hsx-page--surface': surface, 'hsx-page--header-sticky': headerSticky }
        ]"
        :style="pageStyle"
        :aria-busy="loading"
    >
        <div class="hsx-page__inner">
            <div v-if="title || subtitle || eyebrow || $slots.header || $slots.extra || $slots.toolbar" class="hsx-page__header">
                <slot name="header">
                    <HsxTitle
                        :title="title"
                        :subtitle="subtitle"
                        :eyebrow="eyebrow"
                        size="page"
                        :divider="headerDivider"
                    >
                        <template v-if="$slots.prefix" #prefix><slot name="prefix" /></template>
                        <template v-if="$slots.extra" #extra><slot name="extra" /></template>
                    </HsxTitle>
                </slot>
                <div v-if="$slots.toolbar" class="hsx-page__toolbar"><slot name="toolbar" /></div>
            </div>

            <div v-loading="loading" class="hsx-page__body" :class="{ 'hsx-surface': surface }">
                <slot />
            </div>
        </div>
    </section>
</template>

<style scoped>
.hsx-page {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    color: var(--hsx-text-primary);
}

.hsx-page__inner {
    width: min(100%, var(--hsx-page-content-width));
    min-width: 0;
    margin: 0 auto;
}

.hsx-page--padding-none { padding: 0; }
.hsx-page--padding-compact { padding: var(--hsx-page-gutter-compact); }
.hsx-page--padding-default { padding: var(--hsx-page-gutter); }
.hsx-page--padding-comfortable { padding: clamp(20px, 2vw, 32px); }

.hsx-page__header {
    position: relative;
    z-index: 1;
    margin-bottom: var(--hsx-space-5);
}

.hsx-page--header-sticky .hsx-page__header {
    position: sticky;
    z-index: var(--hsx-z-sticky);
    top: 0;
    padding: var(--hsx-space-3) 0;
    background: var(--hsx-bg-page);
}

.hsx-page__toolbar {
    display: flex;
    min-width: 0;
    align-items: center;
    margin-top: var(--hsx-space-4);
}

.hsx-page__body {
    min-width: 0;
    min-height: 80px;
}

/* 管理业务页面的基础表面统一；复杂表格、表单结构仍由业务组件负责。 */
.hsx-page :deep(.el-card) { border-radius: var(--hsx-radius-md) !important; }
.hsx-page :deep(.el-table) { --el-table-header-bg-color: var(--hsx-bg-muted); --el-table-header-text-color: var(--hsx-text-secondary); --el-table-border-color: var(--hsx-border-color); }
.hsx-page :deep(.el-input__wrapper), .hsx-page :deep(.el-select__wrapper), .hsx-page :deep(.el-textarea__inner), .hsx-page :deep(.el-button:not(.is-round):not(.is-circle)) { border-radius: var(--hsx-radius-sm) !important; }

.hsx-page--surface .hsx-page__body {
    padding: var(--hsx-surface-padding);
}

@media (max-width: 1366px) {
    .hsx-page__header { margin-bottom: var(--hsx-space-4); }
    .hsx-page :deep(.summary-tile) { padding: 10px 12px; }
    .hsx-page :deep(.summary-value) { margin-top: 4px; font-size: 20px; line-height: 28px; }
}

@media (max-width: 768px) {
    .hsx-page__toolbar { overflow-x: auto; padding-bottom: 2px; }
}
</style>
