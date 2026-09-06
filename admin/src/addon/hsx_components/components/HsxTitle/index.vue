<script lang="ts">
export default { name: 'HsxTitle' }
</script>

<script setup lang="ts">
import { computed, ref } from 'vue'

type TitleSize = 'page' | 'section' | 'card' | 'subsection'
const props = withDefaults(defineProps<{
    title?: string
    subtitle?: string
    eyebrow?: string
    level?: 1 | 2 | 3 | 4 | 5 | 6
    size?: TitleSize
    divider?: boolean
    dense?: boolean
    collapsibleSubtitle?: boolean
}>(), {
    title: '',
    subtitle: '',
    eyebrow: '',
    level: 2,
    size: 'section',
    divider: false,
    dense: false,
    collapsibleSubtitle: false
})
const headingTag = computed(() => `h${props.level}`)
const subtitleExpanded = ref(false)
</script>

<template>
    <header class="hsx-title" :class="[`hsx-title--${size}`, { 'hsx-title--divider': divider, 'hsx-title--dense': dense }]">
        <div v-if="$slots.prefix" class="hsx-title__prefix"><slot name="prefix" /></div>
        <div class="hsx-title__content">
            <span v-if="eyebrow" class="hsx-title__eyebrow">{{ eyebrow }}</span>
            <component :is="headingTag" class="hsx-title__heading"><slot>{{ title }}</slot></component>
            <button v-if="collapsibleSubtitle && (subtitle || $slots.subtitle)" class="hsx-title__help" type="button" :aria-expanded="subtitleExpanded" @click="subtitleExpanded = !subtitleExpanded">{{ subtitleExpanded ? '收起说明' : '查看说明' }}</button>
            <p v-if="subtitle || $slots.subtitle" v-show="!collapsibleSubtitle || subtitleExpanded" class="hsx-title__subtitle"><slot name="subtitle">{{ subtitle }}</slot></p>
        </div>
        <div v-if="$slots.extra" class="hsx-title__extra"><slot name="extra" /></div>
    </header>
</template>

<style scoped>
.hsx-title { display: flex; min-width: 0; align-items: flex-start; gap: var(--hsx-space-3); }
.hsx-title--divider { padding-bottom: var(--hsx-space-4); border-bottom: 1px solid var(--hsx-border-color); }
.hsx-title__prefix { flex: none; padding-top: 2px; }
.hsx-title__content { min-width: 0; flex: 1; }
.hsx-title__heading { margin: 0; color: var(--hsx-text-primary); font-size: var(--hsx-title-size); font-weight: 650; line-height: var(--hsx-title-line-height); letter-spacing: -.015em; }
.hsx-title__subtitle { margin: var(--hsx-space-1) 0 0; color: var(--hsx-text-secondary); font-size: 13px; line-height: 20px; }
.hsx-title__eyebrow { display: block; margin-bottom: var(--hsx-space-1); color: var(--hsx-color-primary); font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
.hsx-title__extra { display: flex; flex-wrap: wrap; flex: 0 1 auto; max-width: 100%; align-items: center; justify-content: flex-end; gap: var(--hsx-space-2); }
.hsx-title__extra :deep(.el-button + .el-button) { margin-left: 0; }
.hsx-title__help { padding: 0; margin-top: 3px; background: none; border: 0; font-size: 12px; line-height: 20px; color: var(--hsx-text-secondary); cursor: pointer; }
.hsx-title__help:hover { color: var(--hsx-color-primary); }
.hsx-title__help:focus-visible { outline: 2px solid var(--hsx-color-primary); outline-offset: 2px; }
.hsx-title--page { --hsx-title-size: 22px; --hsx-title-line-height: 30px; }
.hsx-title--section { --hsx-title-size: 20px; --hsx-title-line-height: 28px; }
.hsx-title--card { --hsx-title-size: 16px; --hsx-title-line-height: 24px; }
.hsx-title--subsection { --hsx-title-size: 14px; --hsx-title-line-height: 22px; }
.hsx-title--dense .hsx-title__subtitle { margin-top: 0; }
@media (max-width: 1366px) { .hsx-title--page { --hsx-title-size: 20px; --hsx-title-line-height: 28px; } }
@media (max-width: 768px) { .hsx-title { flex-wrap: wrap; } .hsx-title__extra { width: 100%; justify-content: flex-start; } }
</style>
