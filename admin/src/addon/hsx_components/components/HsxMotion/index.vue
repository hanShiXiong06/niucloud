<script lang="ts">
export default { name: 'HsxMotion' }
</script>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
type MotionPreset = 'fade' | 'fade-up' | 'scale' | 'slide-left' | 'slide-right'
const props = withDefaults(defineProps<{
    show?: boolean
    appear?: boolean
    preset?: MotionPreset
    duration?: number
    delay?: number
    tag?: string
}>(), { show: true, appear: true, preset: 'fade-up', duration: 200, delay: 0, tag: 'div' })
const ready = ref(!props.appear)
const visible = computed(() => props.show && ready.value)
const motionStyle = computed(() => ({ '--hsx-motion-duration-custom': `${props.duration}ms`, '--hsx-motion-delay': `${props.delay}ms` }))
onMounted(async () => { await nextTick(); requestAnimationFrame(() => { ready.value = true }) })
</script>

<template><Transition :name="`hsx-motion-${preset}`"><component :is="tag" v-if="visible" class="hsx-motion" :style="motionStyle"><slot /></component></Transition></template>

