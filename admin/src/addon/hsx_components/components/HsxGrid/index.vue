<script lang="ts">
export default { name: 'HsxGrid' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import type { HsxGridBreakpoint, HsxResponsiveNumber } from './types'

interface HsxGridProps {
    columns?: HsxResponsiveNumber
    gap?: string | number
    columnGap?: string | number
    rowGap?: string | number
    minItemWidth?: string | number
    align?: 'start' | 'center' | 'end' | 'stretch'
    justifyItems?: 'start' | 'center' | 'end' | 'stretch'
    dense?: boolean
    equalHeight?: boolean
    maxWidth?: string | number
    tag?: string
    background?: string
    backgroundColor?: string
    gradient?: string
    backgroundImage?: string
    backgroundSize?: string
    backgroundPosition?: string
    backgroundRepeat?: string
    overlay?: string
    color?: string
    padding?: string | number
    radius?: string | number
    border?: string
    shadow?: boolean | string
    minHeight?: string | number
}

const props = withDefaults(defineProps<HsxGridProps>(), {
    columns: () => ({ xs: 1, sm: 2, md: 3, lg: 4, xl: 5 }),
    gap: 16,
    align: 'stretch',
    justifyItems: 'stretch',
    dense: false,
    equalHeight: true,
    background: '',
    backgroundColor: '',
    gradient: '',
    backgroundImage: '',
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    backgroundRepeat: 'no-repeat',
    overlay: '',
    color: '',
    padding: 0,
    radius: 0,
    border: '',
    shadow: false,
    minHeight: '',
    maxWidth: '',
    tag: 'div'
})

const size = (value?: string | number) => typeof value === 'number' ? `${value}px` : value || undefined
const clampColumns = (value?: number) => Math.min(24, Math.max(1, Number(value) || 1))
const normalizedImage = computed(() => {
    const source = (props.backgroundImage || '').trim()
    if (!source) return ''
    if (/^(url\(|linear-gradient\(|radial-gradient\(|conic-gradient\()/i.test(source)) return source
    return `url("${source.replace(/"/g, '\\"')}")`
})
const backgroundLayers = computed(() => [props.overlay, props.gradient, normalizedImage.value].filter(Boolean).join(', ') || undefined)

function columnAt(name: HsxGridBreakpoint) {
    if (typeof props.columns === 'number') return clampColumns(props.columns)
    const fallbacks: HsxGridBreakpoint[] = ['xs', 'sm', 'md', 'lg', 'xl']
    const index = fallbacks.indexOf(name)
    for (let cursor = index; cursor >= 0; cursor -= 1) {
        const value = props.columns[fallbacks[cursor]]
        if (value != null) return clampColumns(value)
    }
    return 1
}

const gridStyle = computed(() => ({
    '--hsx-grid-xs': columnAt('xs'),
    '--hsx-grid-sm': columnAt('sm'),
    '--hsx-grid-md': columnAt('md'),
    '--hsx-grid-lg': columnAt('lg'),
    '--hsx-grid-xl': columnAt('xl'),
    '--hsx-grid-gap': size(props.gap),
    '--hsx-grid-column-gap': size(props.columnGap ?? props.gap),
    '--hsx-grid-row-gap': size(props.rowGap ?? props.gap),
    '--hsx-grid-min': size(props.minItemWidth),
    '--hsx-grid-item-height': props.equalHeight ? '100%' : 'auto',
    alignItems: props.align,
    justifyItems: props.justifyItems,
    gridAutoFlow: props.dense ? 'row dense' : 'row',
    background: props.background || undefined,
    backgroundColor: props.backgroundColor || undefined,
    backgroundImage: backgroundLayers.value,
    backgroundSize: props.backgroundSize,
    backgroundPosition: props.backgroundPosition,
    backgroundRepeat: props.backgroundRepeat,
    color: props.color || undefined,
    padding: size(props.padding),
    borderRadius: size(props.radius),
    border: props.border || undefined,
    boxShadow: props.shadow === true ? 'var(--hsx-shadow-card, 0 12px 30px rgba(15, 23, 42, .10))' : props.shadow || undefined,
    minHeight: size(props.minHeight),
    maxWidth: size(props.maxWidth)
}))
</script>

<template>
    <component
        :is="tag"
        class="hsx-grid"
        :class="{ 'hsx-grid--auto': minItemWidth, 'hsx-grid--equal': equalHeight }"
        :style="gridStyle"
    >
        <slot :columns="columns" />
    </component>
</template>

<style scoped>
.hsx-grid {
    display: grid;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    grid-template-columns: repeat(var(--hsx-grid-xs), minmax(0, 1fr));
    column-gap: var(--hsx-grid-column-gap);
    row-gap: var(--hsx-grid-row-gap);
}
.hsx-grid--auto { grid-template-columns: repeat(auto-fit, minmax(min(100%, var(--hsx-grid-min)), 1fr)); }
.hsx-grid--equal > :deep(*) { height: var(--hsx-grid-item-height); }
@media (min-width: 640px) { .hsx-grid:not(.hsx-grid--auto) { grid-template-columns: repeat(var(--hsx-grid-sm), minmax(0, 1fr)); } }
@media (min-width: 768px) { .hsx-grid:not(.hsx-grid--auto) { grid-template-columns: repeat(var(--hsx-grid-md), minmax(0, 1fr)); } }
@media (min-width: 1024px) { .hsx-grid:not(.hsx-grid--auto) { grid-template-columns: repeat(var(--hsx-grid-lg), minmax(0, 1fr)); } }
@media (min-width: 1280px) { .hsx-grid:not(.hsx-grid--auto) { grid-template-columns: repeat(var(--hsx-grid-xl), minmax(0, 1fr)); } }
</style>
