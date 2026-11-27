<script lang="ts">
export default { name: 'HsxDateRange', inheritAttrs: false }
</script>

<script setup lang="ts">
import HsxDatePicker from '../HsxDatePicker/index.vue'
import type { HsxDateRangeShortcut } from '../../types'

type RangeValue = [string | number | Date, string | number | Date] | null

const props = withDefaults(
    defineProps<{
        modelValue?: RangeValue
        type?: 'daterange' | 'datetimerange'
        valueFormat?: string
        format?: string
        startPlaceholder?: string
        endPlaceholder?: string
        rangeSeparator?: string
        maxSpanDays?: number
        disableFuture?: boolean
        disabledDate?: (date: Date) => boolean
        shortcuts?: HsxDateRangeShortcut[]
        clearable?: boolean
        unlinkPanels?: boolean
        showDefaultShortcuts?: boolean
        showExceedMessage?: boolean
        normalizeRangeBoundary?: boolean
        startTime?: string
        endTime?: string
    }>(),
    {
        modelValue: null,
        type: 'datetimerange',
        startPlaceholder: '开始时间',
        endPlaceholder: '结束时间',
        rangeSeparator: '至',
        maxSpanDays: 0,
        disableFuture: false,
        clearable: true,
        unlinkPanels: true,
        showDefaultShortcuts: true,
        showExceedMessage: true,
        normalizeRangeBoundary: true,
        startTime: '00:00:00',
        endTime: '23:59:59'
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: RangeValue): void
    (event: 'change', value: RangeValue): void
    (event: 'exceed', value: RangeValue, maxSpanDays: number): void
}>()

function handleUpdate(value: any) {
    const rangeValue = value as RangeValue
    emit('update:modelValue', rangeValue)
    emit('change', rangeValue)
}

function handleExceed(value: any) {
    emit('exceed', value as RangeValue, props.maxSpanDays)
}
</script>

<template>
    <HsxDatePicker
        v-bind="$attrs"
        :model-value="modelValue"
        :type="type"
        :value-format="valueFormat"
        :format="format"
        :start-placeholder="startPlaceholder"
        :end-placeholder="endPlaceholder"
        :range-separator="rangeSeparator"
        :max-span-days="maxSpanDays"
        :disable-future="disableFuture"
        :disabled-date="disabledDate"
        :shortcuts="shortcuts"
        :clearable="clearable"
        :unlink-panels="unlinkPanels"
        :show-default-shortcuts="showDefaultShortcuts"
        :show-exceed-message="showExceedMessage"
        :normalize-range-boundary="normalizeRangeBoundary"
        :start-time="startTime"
        :end-time="endTime"
        @update:model-value="handleUpdate"
        @exceed="handleExceed"
    />
</template>
