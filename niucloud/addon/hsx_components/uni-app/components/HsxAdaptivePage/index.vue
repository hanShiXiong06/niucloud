<script lang="ts">
export default { name: 'HsxAdaptivePage' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import { provideAdaptiveLayout, resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import type { MobileResponsiveValue } from '../../types'

const props = withDefaults(
    defineProps<{
        maxWidth?: string | number
        gutter?: MobileResponsiveValue<number>
        minHeight?: string | number
        background?: string
        safeAreaTop?: boolean
        safeAreaBottom?: boolean
        centered?: boolean
        mediumBreakpoint?: number
        expandedBreakpoint?: number
    }>(),
    {
        maxWidth: 1280,
        gutter: () => ({ compact: 12, medium: 20, expanded: 24 }),
        minHeight: '100vh',
        background: 'var(--hsx-mobile-bg-page, #f5f6f8)',
        safeAreaTop: false,
        safeAreaBottom: true,
        centered: true,
        mediumBreakpoint: 600,
        expandedBreakpoint: 840
    }
)

const layout = provideAdaptiveLayout(useAdaptiveContext({
    mediumBreakpoint: props.mediumBreakpoint,
    expandedBreakpoint: props.expandedBreakpoint
}))
const toSize = (value: string | number) => typeof value === 'number' ? `${value}px` : value
const gutter = computed(() => resolveAdaptiveValue(props.gutter, layout.widthClass.value, 12))
const rootStyle = computed(() => ({ minHeight: toSize(props.minHeight), background: props.background }))
const contentStyle = computed(() => {
    const insets = layout.safeAreaInsets.value
    return {
        maxWidth: toSize(props.maxWidth),
        marginLeft: props.centered ? 'auto' : undefined,
        marginRight: props.centered ? 'auto' : undefined,
        paddingTop: `${gutter.value + (props.safeAreaTop ? insets.top : 0)}px`,
        paddingRight: `${gutter.value + insets.right}px`,
        paddingBottom: `${gutter.value + (props.safeAreaBottom ? insets.bottom : 0)}px`,
        paddingLeft: `${gutter.value + insets.left}px`
    }
})
</script>

<template>
    <view class="hsx-adaptive-page" :class="`hsx-adaptive-page--${layout.widthClass.value}`" :style="rootStyle">
        <view class="hsx-adaptive-page__content" :style="contentStyle">
            <slot
                :layout="layout.snapshot.value"
                :width-class="layout.widthClass.value"
                :is-wide="layout.isWide.value"
            />
        </view>
    </view>
</template>

<style scoped>
.hsx-adaptive-page,
.hsx-adaptive-page__content {
    width: 100%;
    box-sizing: border-box;
}
</style>
