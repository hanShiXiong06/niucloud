<script lang="ts">export default { name: 'HsxText' }</script>
<script setup lang="ts">
import { computed } from 'vue'
type Tone = 'primary' | 'regular' | 'secondary' | 'success' | 'warning' | 'danger' | 'price' | 'inherit'
type Size = 'caption' | 'body' | 'subtitle' | 'title' | number
const props = withDefaults(defineProps<{
    text?: string | number
    tone?: Tone
    size?: Size
    weight?: string | number
    lines?: number
    ellipsis?: boolean
    selectable?: boolean
}>(), { text: '', tone: 'regular', size: 'body', lines: 0, ellipsis: false, selectable: false })
const actualLines = computed(() => Math.max(0, Math.floor(props.lines || (props.ellipsis ? 1 : 0))))
const sizeMap = {
    caption: ['var(--hsx-mobile-font-caption, 12px)', 'var(--hsx-mobile-line-caption, 18px)'],
    body: ['var(--hsx-mobile-font-body, 14px)', 'var(--hsx-mobile-line-body, 21px)'],
    subtitle: ['var(--hsx-mobile-font-subtitle, 15px)', '1.45'],
    title: ['var(--hsx-mobile-font-title, 18px)', 'var(--hsx-mobile-line-title, 24px)']
}
const textStyle = computed(() => {
    if (typeof props.size === 'number') {
        return { fontSize: `${props.size}rpx`, lineHeight: `${Math.round(props.size * 1.5)}rpx`, fontWeight: props.weight }
    }
    return { fontSize: sizeMap[props.size][0], lineHeight: sizeMap[props.size][1], fontWeight: props.weight }
})
const rootStyle = computed(() => ({ ...textStyle.value, WebkitLineClamp: actualLines.value > 1 ? actualLines.value : undefined }))
</script>
<template><text class="hsx-mobile-text" :class="[`hsx-mobile-text--${tone}`, { 'hsx-mobile-text--ellipsis': actualLines === 1, 'hsx-mobile-text--clamp': actualLines > 1 }]" :style="rootStyle" :selectable="selectable"><slot>{{ text }}</slot></text></template>
<style scoped lang="scss">
.hsx-mobile-text { display: block; min-width: 0; color: var(--hsx-mobile-text-regular); overflow-wrap: anywhere; }
.hsx-mobile-text--primary { color: var(--hsx-mobile-text-primary); } .hsx-mobile-text--regular { color: var(--hsx-mobile-text-regular); } .hsx-mobile-text--secondary { color: var(--hsx-mobile-text-secondary); }
.hsx-mobile-text--success { color: var(--hsx-mobile-success); } .hsx-mobile-text--warning { color: var(--hsx-mobile-warning); } .hsx-mobile-text--danger { color: var(--hsx-mobile-danger); } .hsx-mobile-text--price { color: var(--hsx-mobile-price); } .hsx-mobile-text--inherit { color: inherit; }
.hsx-mobile-text--ellipsis { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hsx-mobile-text--clamp { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; }
</style>
