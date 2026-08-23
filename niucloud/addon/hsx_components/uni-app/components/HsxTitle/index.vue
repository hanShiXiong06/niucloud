<script lang="ts">export default { name: 'HsxTitle' }</script>
<script setup lang="ts">
withDefaults(defineProps<{
    title?: string
    subtitle?: string
    eyebrow?: string
    size?: 'page' | 'section' | 'card'
    divider?: boolean
}>(), { title: '', subtitle: '', eyebrow: '', size: 'section', divider: false })
</script>
<template>
    <view class="hsx-mobile-title" :class="[`hsx-mobile-title--${size}`, { 'hsx-mobile-title--divider': divider }]">
        <view v-if="$slots.prefix" class="hsx-mobile-title__prefix"><slot name="prefix" /></view>
        <view class="hsx-mobile-title__content">
            <text v-if="eyebrow" class="hsx-mobile-title__eyebrow">{{ eyebrow }}</text>
            <text class="hsx-mobile-title__heading"><slot>{{ title }}</slot></text>
            <text v-if="subtitle || $slots.subtitle" class="hsx-mobile-title__subtitle"><slot name="subtitle">{{ subtitle }}</slot></text>
        </view>
        <view v-if="$slots.extra" class="hsx-mobile-title__extra"><slot name="extra" /></view>
    </view>
</template>
<style scoped lang="scss">
.hsx-mobile-title { display: flex; min-width: 0; align-items: flex-start; gap: 12px; }
.hsx-mobile-title--divider { padding-bottom: 14px; border-bottom: 1px solid var(--hsx-mobile-border); }
.hsx-mobile-title__prefix, .hsx-mobile-title__extra { flex: none; } .hsx-mobile-title__content { min-width: 0; flex: 1; }
.hsx-mobile-title__heading, .hsx-mobile-title__subtitle, .hsx-mobile-title__eyebrow { display: block; }
.hsx-mobile-title__heading { color: var(--hsx-mobile-text-primary); font-size: var(--hsx-mobile-title-size); font-weight: 650; line-height: var(--hsx-mobile-title-line-height); }
.hsx-mobile-title__subtitle { margin-top: 4px; color: var(--hsx-mobile-text-secondary); font-size: var(--hsx-mobile-font-caption, 12px); line-height: var(--hsx-mobile-line-caption, 18px); }
.hsx-mobile-title__eyebrow { margin-bottom: 4px; color: var(--hsx-mobile-primary); font-size: var(--hsx-mobile-font-caption, 12px); font-weight: 700; letter-spacing: 1px; }
.hsx-mobile-title--page { --hsx-mobile-title-size: var(--hsx-mobile-font-page-title, 22px); --hsx-mobile-title-line-height: 1.35; }
.hsx-mobile-title--section { --hsx-mobile-title-size: var(--hsx-mobile-font-title, 18px); --hsx-mobile-title-line-height: var(--hsx-mobile-line-title, 24px); }
.hsx-mobile-title--card { --hsx-mobile-title-size: var(--hsx-mobile-font-subtitle, 15px); --hsx-mobile-title-line-height: 1.45; }
</style>
