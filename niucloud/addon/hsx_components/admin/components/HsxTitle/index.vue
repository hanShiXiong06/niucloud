<script lang="ts">
export default { name: 'HsxTitle' }
</script>

<script setup lang="ts">
import { computed } from 'vue'

type TitleSize = 'page' | 'section' | 'card' | 'subsection'
const props = withDefaults(defineProps<{
    title?: string
    subtitle?: string
    eyebrow?: string
    level?: 1 | 2 | 3 | 4 | 5 | 6
    size?: TitleSize
    divider?: boolean
    dense?: boolean
}>(), {
    title: '',
    subtitle: '',
    eyebrow: '',
    level: 2,
    size: 'section',
    divider: false,
    dense: false
})
const headingTag = computed(() => `h${props.level}`)
</script>

<template>
    <header class="hsx-title" :class="[`hsx-title--${size}`, { 'hsx-title--divider': divider, 'hsx-title--dense': dense }]">
        <div v-if="$slots.prefix" class="hsx-title__prefix"><slot name="prefix" /></div>
        <div class="hsx-title__content">
            <span v-if="eyebrow" class="hsx-title__eyebrow">{{ eyebrow }}</span>
            <component :is="headingTag" class="hsx-title__heading"><slot>{{ title }}</slot></component>
            <p v-if="subtitle || $slots.subtitle" class="hsx-title__subtitle"><slot name="subtitle">{{ subtitle }}</slot></p>
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
.hsx-title__extra { display: flex; flex: none; align-items: center; gap: var(--hsx-space-2); }
.hsx-title--page { --hsx-title-size: 28px; --hsx-title-line-height: 38px; }
.hsx-title--section { --hsx-title-size: 20px; --hsx-title-line-height: 28px; }
.hsx-title--card { --hsx-title-size: 16px; --hsx-title-line-height: 24px; }
.hsx-title--subsection { --hsx-title-size: 14px; --hsx-title-line-height: 22px; }
.hsx-title--dense .hsx-title__subtitle { margin-top: 0; }
@media (max-width: 640px) { .hsx-title--page { --hsx-title-size: 24px; --hsx-title-line-height: 34px; } .hsx-title { flex-wrap: wrap; } .hsx-title__extra { width: 100%; } }
</style>

