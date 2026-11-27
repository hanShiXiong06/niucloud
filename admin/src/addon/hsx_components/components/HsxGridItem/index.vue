<script lang="ts">
export default { name: 'HsxGridItem' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import type { HsxGridBreakpoint, HsxResponsiveNumber } from '../HsxGrid/types'

interface HsxGridItemProps {
    span?: HsxResponsiveNumber
    rowSpan?: number
    full?: boolean
    order?: number
    alignSelf?: 'auto' | 'start' | 'center' | 'end' | 'stretch'
    clickable?: boolean
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

const props = withDefaults(defineProps<HsxGridItemProps>(), {
    span: 1,
    rowSpan: 1,
    full: false,
    order: 0,
    alignSelf: 'stretch',
    clickable: false,
    background: '',
    backgroundColor: 'var(--hsx-bg-surface, #fff)',
    gradient: '',
    backgroundImage: '',
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    backgroundRepeat: 'no-repeat',
    overlay: '',
    color: '',
    padding: 16,
    radius: 14,
    border: '1px solid var(--hsx-border-color, #e5e7eb)',
    shadow: false,
    minHeight: '',
    tag: 'div'
})

const emit = defineEmits<{ (event: 'click', payload: MouseEvent): void }>()
const size = (value?: string | number) => typeof value === 'number' ? `${value}px` : value || undefined
const clampSpan = (value?: number) => Math.min(24, Math.max(1, Number(value) || 1))
const normalizedImage = computed(() => {
    const source = (props.backgroundImage || '').trim()
    if (!source) return ''
    if (/^(url\(|linear-gradient\(|radial-gradient\(|conic-gradient\()/i.test(source)) return source
    return `url("${source.replace(/"/g, '\\"')}")`
})
const layers = computed(() => [props.overlay, props.gradient, normalizedImage.value].filter(Boolean).join(', ') || undefined)

function spanAt(name: HsxGridBreakpoint) {
    if (typeof props.span === 'number') return clampSpan(props.span)
    const fallbacks: HsxGridBreakpoint[] = ['xs', 'sm', 'md', 'lg', 'xl']
    const index = fallbacks.indexOf(name)
    for (let cursor = index; cursor >= 0; cursor -= 1) {
        const value = props.span[fallbacks[cursor]]
        if (value != null) return clampSpan(value)
    }
    return 1
}

const itemStyle = computed(() => ({
    '--hsx-grid-item-xs': spanAt('xs'),
    '--hsx-grid-item-sm': spanAt('sm'),
    '--hsx-grid-item-md': spanAt('md'),
    '--hsx-grid-item-lg': spanAt('lg'),
    '--hsx-grid-item-xl': spanAt('xl'),
    gridRow: `span ${Math.max(1, props.rowSpan)}`,
    order: props.order,
    alignSelf: props.alignSelf,
    background: props.background || undefined,
    backgroundColor: props.backgroundColor || undefined,
    backgroundImage: layers.value,
    backgroundSize: props.backgroundSize,
    backgroundPosition: props.backgroundPosition,
    backgroundRepeat: props.backgroundRepeat,
    color: props.color || undefined,
    padding: size(props.padding),
    borderRadius: size(props.radius),
    border: props.border || undefined,
    boxShadow: props.shadow === true ? 'var(--hsx-shadow-card, 0 12px 30px rgba(15, 23, 42, .10))' : props.shadow || undefined,
    minHeight: size(props.minHeight)
}))
</script>

<template>
    <component
        :is="tag"
        class="hsx-grid-item"
        :class="{ 'hsx-grid-item--full': full, 'hsx-grid-item--clickable': clickable }"
        :style="itemStyle"
        :role="clickable ? 'button' : undefined"
        :tabindex="clickable ? 0 : undefined"
        @click="emit('click', $event)"
        @keydown.enter="clickable && ($el as HTMLElement).click()"
    >
        <slot />
    </component>
</template>

<style scoped>
.hsx-grid-item { min-width: 0; box-sizing: border-box; grid-column: span var(--hsx-grid-item-xs); }
.hsx-grid-item--full { grid-column: 1 / -1 !important; }
.hsx-grid-item--clickable { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
.hsx-grid-item--clickable:hover { box-shadow: var(--hsx-shadow-card, 0 12px 30px rgba(15, 23, 42, .10)); transform: translateY(-2px); }
.hsx-grid-item--clickable:focus-visible { outline: 2px solid var(--hsx-color-primary, #2563eb); outline-offset: 2px; }
@media (min-width: 640px) { .hsx-grid-item:not(.hsx-grid-item--full) { grid-column: span var(--hsx-grid-item-sm); } }
@media (min-width: 768px) { .hsx-grid-item:not(.hsx-grid-item--full) { grid-column: span var(--hsx-grid-item-md); } }
@media (min-width: 1024px) { .hsx-grid-item:not(.hsx-grid-item--full) { grid-column: span var(--hsx-grid-item-lg); } }
@media (min-width: 1280px) { .hsx-grid-item:not(.hsx-grid-item--full) { grid-column: span var(--hsx-grid-item-xl); } }
@media (prefers-reduced-motion: reduce) { .hsx-grid-item--clickable { transition: none; } .hsx-grid-item--clickable:hover { transform: none; } }
</style>
