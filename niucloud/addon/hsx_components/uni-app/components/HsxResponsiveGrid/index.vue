<script lang="ts">
export default { name: 'HsxResponsiveGrid' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import { classifyAdaptiveWidth, resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import type { MobileResponsiveValue } from '../../types'

const props = withDefaults(
    defineProps<{
        columns?: MobileResponsiveValue<number>
        gap?: MobileResponsiveValue<number>
        rowGap?: MobileResponsiveValue<number>
        viewportWidth?: number
    }>(),
    {
        columns: () => ({ compact: 1, medium: 2, expanded: 3, large: 4, 'extra-large': 5 }),
        gap: 16
    }
)

const layout = useAdaptiveContext()
const widthClass = computed(() => props.viewportWidth
    ? classifyAdaptiveWidth(props.viewportWidth)
    : layout.widthClass.value)
const resolvedColumns = computed(() => Math.max(1, resolveAdaptiveValue(props.columns, widthClass.value, 1)))
const resolvedGap = computed(() => Math.max(0, resolveAdaptiveValue(props.gap, widthClass.value, 16)))
const resolvedRowGap = computed(() => Math.max(0, resolveAdaptiveValue(props.rowGap, widthClass.value, resolvedGap.value)))
const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${resolvedColumns.value}, minmax(0, 1fr))`,
    columnGap: `${resolvedGap.value}px`,
    rowGap: `${resolvedRowGap.value}px`
}))
</script>

<template>
    <view class="hsx-responsive-grid" :class="`hsx-responsive-grid--${widthClass}`" :style="gridStyle">
        <!--
            小程序端不要给默认插槽传参。UniApp 会把它编译成具名作用域插槽，
            在多层自定义组件中可能直接丢失整块子内容。网格状态由组件内部处理，
            调用方如需断点信息应使用 useAdaptiveLayout，而不是依赖插槽参数。
        -->
        <slot />
    </view>
</template>

<style scoped>
.hsx-responsive-grid {
    display: grid;
    min-width: 0;
    box-sizing: border-box;
}
</style>
