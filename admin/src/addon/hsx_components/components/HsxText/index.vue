<script lang="ts">
export default { name: 'HsxText', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useAttrs, watch } from 'vue'

type TextTone = 'primary' | 'regular' | 'secondary' | 'success' | 'warning' | 'danger' | 'inherit'
type TextSize = 'caption' | 'body' | 'subtitle' | 'title' | number

const props = withDefaults(defineProps<{
    text?: string | number
    tag?: string
    tone?: TextTone
    size?: TextSize
    weight?: number | string
    lines?: number
    ellipsis?: boolean
    tooltip?: boolean
    tooltipText?: string
    maxWidth?: string | number
    selectable?: boolean
}>(), {
    text: '',
    tag: 'span',
    tone: 'regular',
    size: 'body',
    lines: 0,
    ellipsis: false,
    tooltip: true,
    selectable: false
})

const attrs = useAttrs()
const rootRef = ref<HTMLElement>()
const overflowed = ref(false)
let observer: ResizeObserver | undefined

const sizeMap = { caption: '12px', body: '14px', subtitle: '16px', title: '20px' }
const lineHeightMap = { caption: '18px', body: '22px', subtitle: '24px', title: '28px' }
const actualSize = computed(() => typeof props.size === 'number' ? `${props.size}px` : sizeMap[props.size])
const actualLineHeight = computed(() => typeof props.size === 'number' ? `${Math.round(props.size * 1.5)}px` : lineHeightMap[props.size])
const clampLines = computed(() => Math.max(0, Math.floor(props.lines || (props.ellipsis ? 1 : 0))))
const rootStyle = computed(() => ({
    maxWidth: typeof props.maxWidth === 'number' ? `${props.maxWidth}px` : props.maxWidth,
    fontSize: actualSize.value,
    lineHeight: actualLineHeight.value,
    fontWeight: props.weight,
    WebkitLineClamp: clampLines.value > 1 ? clampLines.value : undefined
}))
const tooltipContent = computed(() => props.tooltipText || String(props.text ?? ''))

function measureOverflow() {
    const element = rootRef.value
    if (!element) return
    overflowed.value = element.scrollWidth > element.clientWidth + 1 || element.scrollHeight > element.clientHeight + 1
}

onMounted(async () => {
    await nextTick()
    measureOverflow()
    if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(measureOverflow)
        if (rootRef.value) observer.observe(rootRef.value)
    }
})
onBeforeUnmount(() => observer?.disconnect())
watch(() => [props.text, props.lines, props.ellipsis, props.maxWidth], () => nextTick(measureOverflow))
</script>

<template>
    <el-tooltip :disabled="!tooltip || !overflowed || !tooltipContent" :content="tooltipContent" placement="top" :show-after="300">
        <component
            :is="tag"
            ref="rootRef"
            v-bind="attrs"
            class="hsx-text"
            :class="[`hsx-text--${tone}`, { 'hsx-text--ellipsis': clampLines === 1, 'hsx-text--clamp': clampLines > 1, 'hsx-text--selectable': selectable }]"
            :style="rootStyle"
        ><slot>{{ text }}</slot></component>
    </el-tooltip>
</template>

<style scoped>
.hsx-text { min-width: 0; color: var(--hsx-text-regular); overflow-wrap: anywhere; }
.hsx-text--primary { color: var(--hsx-text-primary); }
.hsx-text--regular { color: var(--hsx-text-regular); }
.hsx-text--secondary { color: var(--hsx-text-secondary); }
.hsx-text--success { color: var(--hsx-color-success); }
.hsx-text--warning { color: var(--hsx-color-warning); }
.hsx-text--danger { color: var(--hsx-color-danger); }
.hsx-text--inherit { color: inherit; }
.hsx-text--ellipsis { display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hsx-text--clamp { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; }
.hsx-text--selectable { user-select: text; }
</style>

