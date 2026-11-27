<template>
    <el-table ref="table" class="erp-data-table" v-bind="$attrs" size="default" :max-height="height">
        <template v-for="(_, name) in $slots" #[name]="scope"><slot :name="name" v-bind="scope || {}" /></template>
    </el-table>
</template>

<script lang="ts">
export default { inheritAttrs: false }
</script>
<script setup lang="ts">
import { nextTick, onActivated, onBeforeUnmount, onDeactivated, onMounted, ref } from 'vue'
import { erpTableHeight } from '../utils/desktop-layout'

const table = ref()
const height = ref(420)
let observer: ResizeObserver | undefined
let frame = 0
function measure() {
    cancelAnimationFrame(frame)
    frame = requestAnimationFrame(() => {
        const element = table.value?.$el as HTMLElement | undefined
        if (!element?.getClientRects().length) return
        // Correct for an already-scrolled page: scrolling must never resize the table.
        let top = element.getBoundingClientRect().top
        for (let parent = element.parentElement; parent; parent = parent.parentElement) top += parent.scrollTop
        const footer = element.parentElement?.querySelector('.erp-pagination')
        height.value = erpTableHeight(window.innerHeight, top, Math.max(58, (footer?.getBoundingClientRect().height || 0) + 20))
    })
}
async function observe() {
    await nextTick()
    observer?.disconnect()
    const element = table.value?.$el as HTMLElement | undefined
    const surface = element?.closest('.erp-list-surface')
    if (!surface) return
    observer = new ResizeObserver(measure)
    // Watch the content above the table, not the table's own changing height.
    for (const child of surface.children) if (!child.classList.contains('erp-data-table')) observer.observe(child)
    window.addEventListener('resize', measure)
    measure()
}
function stop() {
    observer?.disconnect()
    cancelAnimationFrame(frame)
    window.removeEventListener('resize', measure)
}
onMounted(observe)
onActivated(observe)
onDeactivated(stop)
onBeforeUnmount(stop)
</script>
