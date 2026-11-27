<script lang="ts">export default { name: 'HsxCheckbox' }</script>

<script setup lang="ts">
import { computed } from 'vue'
import { resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import type { MobileOption, MobileResponsiveValue } from '../../types'

type CheckboxValue = string | number | boolean
type ResponsiveSize = MobileResponsiveValue<string | number>

const props = withDefaults(
    defineProps<{
        modelValue?: CheckboxValue[]
        options?: MobileOption[]
        placement?: 'row' | 'column'
        shape?: 'square' | 'circle'
        min?: number
        max?: number
        disabled?: boolean
        size?: ResponsiveSize
        iconSize?: ResponsiveSize
        labelSize?: ResponsiveSize
        columns?: MobileResponsiveValue<number>
        gap?: MobileResponsiveValue<number>
        rowGap?: MobileResponsiveValue<number>
        itemMinWidth?: MobileResponsiveValue<number>
        activeColor?: string
        inactiveColor?: string
        labelColor?: string
    }>(),
    {
        modelValue: () => [],
        options: () => [],
        placement: 'row',
        shape: 'square',
        min: 0,
        max: 0,
        disabled: false,
        size: () => ({ compact: 18, medium: 20, expanded: 20 }),
        iconSize: () => ({ compact: 12, medium: 13, expanded: 14 }),
        labelSize: () => ({ compact: 14, medium: 15, expanded: 16 }),
        columns: 0,
        gap: () => ({ compact: 14, medium: 16, expanded: 18 }),
        rowGap: () => ({ compact: 8, medium: 10, expanded: 12 }),
        itemMinWidth: () => ({ compact: 0, medium: 100, expanded: 112 }),
        activeColor: 'var(--hsx-mobile-primary, #2563eb)',
        inactiveColor: 'var(--hsx-mobile-border-strong, #cbd5e1)',
        labelColor: 'var(--hsx-mobile-text-primary, #172033)'
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: CheckboxValue[]): void
    (event: 'change', value: CheckboxValue[]): void
    (event: 'limit-exceed', type: 'min' | 'max', value: CheckboxValue[]): void
}>()

const layout = useAdaptiveContext()
const resolve = <T,>(value: MobileResponsiveValue<T>, fallback: T) =>
    resolveAdaptiveValue(value, layout.widthClass.value, fallback)
const actualSize = computed(() => resolve(props.size, 18))
const actualIconSize = computed(() => resolve(props.iconSize, 12))
const actualLabelSize = computed(() => resolve(props.labelSize, 14))
const actualColumns = computed(() => Math.max(0, Math.floor(resolve(props.columns, 0))))
const actualGap = computed(() => Math.max(0, resolve(props.gap, 16)))
const actualRowGap = computed(() => Math.max(0, resolve(props.rowGap, 8)))
const actualMinWidth = computed(() => Math.max(0, resolve(props.itemMinWidth, 0)))
const controlHeight = computed(() => layout.isExpanded.value ? 46 : layout.isMedium.value ? 44 : 40)
const groupStyle = computed(() => props.placement === 'column'
    ? {
        display: 'flex',
        flexDirection: 'column',
        rowGap: `${actualRowGap.value}px`
    }
    : actualColumns.value > 0
        ? {
            display: 'grid',
            gridTemplateColumns: `repeat(${actualColumns.value}, minmax(0, 1fr))`,
            columnGap: `${actualGap.value}px`,
            rowGap: `${actualRowGap.value}px`
        }
        : {
            display: 'flex',
            flexFlow: 'row wrap',
            columnGap: `${actualGap.value}px`,
            rowGap: `${actualRowGap.value}px`
        })
const itemStyle = computed(() => ({
    minWidth: actualMinWidth.value ? `${actualMinWidth.value}px` : undefined,
    minHeight: `${controlHeight.value}px`,
    margin: '0',
    boxSizing: 'border-box'
}))

function change(value: CheckboxValue[]) {
    if (props.min > 0 && value.length < props.min) {
        emit('limit-exceed', 'min', value)
        return
    }
    if (props.max > 0 && value.length > props.max) {
        emit('limit-exceed', 'max', value)
        return
    }
    emit('update:modelValue', value)
    emit('change', value)
}
</script>

<template>
    <u-checkbox-group
        class="hsx-checkbox-group"
        :style="groupStyle"
        :model-value="modelValue"
        :placement="placement"
        :disabled="disabled"
        :max="max || undefined"
        @change="change"
    >
        <u-checkbox
            v-for="option in options"
            :key="String(option.value)"
            :name="option.value"
            :label="option.label"
            :disabled="option.disabled"
            :shape="shape"
            :size="actualSize"
            :icon-size="actualIconSize"
            :label-size="actualLabelSize"
            :active-color="activeColor"
            :inactive-color="inactiveColor"
            :label-color="labelColor"
            :custom-style="itemStyle"
        />
    </u-checkbox-group>
</template>

<style scoped>
.hsx-checkbox-group {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}
</style>
