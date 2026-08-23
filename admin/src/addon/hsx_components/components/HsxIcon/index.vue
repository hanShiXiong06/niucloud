<script lang="ts">
export default { name: 'HsxIcon', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, resolveDynamicComponent } from 'vue'
import type { Component } from 'vue'

const props = withDefaults(
    defineProps<{
        name?: string
        component?: Component
        src?: string
        size?: string | number
        color?: string
        spin?: boolean
        clickable?: boolean
        label?: string
    }>(),
    {
        name: '',
        size: 16,
        color: 'currentColor',
        spin: false,
        clickable: false,
        label: ''
    }
)

const emit = defineEmits<{
    (event: 'click', value: MouseEvent | KeyboardEvent): void
}>()

const parts = computed(() => props.name.trim().split(/\s+/).filter(Boolean))
const sourceType = computed(() => {
    if (props.src) return 'image'
    if (props.component) return 'component'
    if (parts.value[0] === 'element') return 'element'
    if (parts.value.length > 1) return 'font'
    return props.name ? 'element' : 'slot'
})
const elementName = computed(() => parts.value[0] === 'element' ? parts.value.slice(1).join('') : props.name)
const iconComponent = computed(() => props.component || resolveDynamicComponent(elementName.value))
const fontClasses = computed(() => parts.value)
const actualSize = computed(() => typeof props.size === 'number' ? `${props.size}px` : props.size)
const iconStyle = computed(() => ({ color: props.color, fontSize: actualSize.value, width: actualSize.value, height: actualSize.value }))

function handleClick(event: MouseEvent | KeyboardEvent) {
    if (props.clickable) emit('click', event)
}

function handleKeydown(event: KeyboardEvent) {
    if (!props.clickable || !['Enter', ' '].includes(event.key)) return
    event.preventDefault()
    handleClick(event)
}
</script>

<template>
    <span
        v-bind="$attrs"
        class="hsx-icon"
        :class="{ 'is-spin': spin, 'is-clickable': clickable }"
        :style="iconStyle"
        :role="clickable ? 'button' : undefined"
        :tabindex="clickable ? 0 : undefined"
        :aria-label="label || undefined"
        @click="handleClick"
        @keydown="handleKeydown"
    >
        <img v-if="sourceType === 'image'" class="hsx-icon__image" :src="src" :alt="label" />
        <el-icon v-else-if="sourceType === 'element' || sourceType === 'component'" class="hsx-icon__element">
            <component :is="iconComponent" />
        </el-icon>
        <i v-else-if="sourceType === 'font'" :class="fontClasses" />
        <slot v-else />
    </span>
</template>

<style scoped>
.hsx-icon {
    display: inline-flex;
    flex: none;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    line-height: 1;
}
.hsx-icon__element,
.hsx-icon__image,
.hsx-icon > i { width: 100%; height: 100%; font-size: inherit; }
.hsx-icon__image { display: block; object-fit: contain; }
.hsx-icon.is-clickable { cursor: pointer; transition: opacity .18s ease, transform .18s ease; }
.hsx-icon.is-clickable:hover { opacity: .78; transform: translateY(-1px); }
.hsx-icon.is-spin { animation: hsx-icon-spin 1s linear infinite; }
@keyframes hsx-icon-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .hsx-icon.is-spin { animation: none; } }
</style>
