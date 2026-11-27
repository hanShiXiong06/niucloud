<script lang="ts">
export default { name: 'HsxDatePicker', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, ref, useAttrs, watch } from 'vue'
import { useFeedback } from '../../hooks/useFeedback'
import type { HsxDatePickerValue, HsxDateRangeShortcut } from '../../types'

type PickerType = 'date' | 'datetime' | 'daterange' | 'datetimerange'

const props = withDefaults(
    defineProps<{
        modelValue?: HsxDatePickerValue
        type?: PickerType
        valueFormat?: string
        format?: string
        placeholder?: string
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
        width?: string | number
        fullWidth?: boolean
    }>(),
    {
        modelValue: null,
        type: 'date',
        placeholder: '请选择时间',
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
        endTime: '23:59:59',
        fullWidth: false
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: HsxDatePickerValue): void
    (event: 'change', value: HsxDatePickerValue): void
    (event: 'raw-change', rawValue: HsxDatePickerValue, value: HsxDatePickerValue): void
    (event: 'exceed', value: HsxDatePickerValue, maxSpanDays: number): void
}>()

const innerValue = ref<HsxDatePickerValue>(props.modelValue)
const pickerValue = computed(() => innerValue.value === null ? undefined : innerValue.value)
const feedback = useFeedback()
const isRange = computed(() => props.type === 'daterange' || props.type === 'datetimerange')
const attrs = useAttrs()

function toCssSize(value?: string | number) {
    if (value === undefined || value === null || value === '') return undefined
    return typeof value === 'number' ? `${value}px` : value
}

const defaultWidth = computed(() => {
    if (props.fullWidth) return '100%'
    if (props.width !== undefined) return toCssSize(props.width)
    if (isRange.value) return '360px'
    return props.type === 'datetime' ? '220px' : '180px'
})

const rootStyle = computed(() => [
    { width: defaultWidth.value },
    attrs.style as any
])

const pickerAttrs = computed(() => {
    const { class: _class, style: _style, ...rest } = attrs
    return rest
})

watch(
    () => props.modelValue,
    (value) => {
        innerValue.value = value ?? null
    },
    { deep: true }
)

function pad(value: number) {
    return String(value).padStart(2, '0')
}

function toDatePart(value: string | number | Date) {
    if (typeof value === 'string') {
        const match = value.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/)
        if (match) return `${match[1]}-${pad(Number(match[2]))}-${pad(Number(match[3]))}`
    }
    const date = value instanceof Date ? value : new Date(value)
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 10)
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function toTimestamp(value: string | number | Date) {
    if (value instanceof Date) return value.getTime()
    if (typeof value === 'number') return value
    const normalized = value.replace(/-/g, '/')
    return new Date(normalized).getTime()
}

function normalizeValue(value: HsxDatePickerValue): HsxDatePickerValue {
    if (!isRange.value || !props.normalizeRangeBoundary || !Array.isArray(value) || value.length < 2) return value ?? null
    return [
        `${toDatePart(value[0])} ${props.startTime}`,
        `${toDatePart(value[1])} ${props.endTime}`
    ]
}

function startOfDay(date: Date) {
    const result = new Date(date)
    result.setHours(0, 0, 0, 0)
    return result
}

function endOfDay(date: Date) {
    const result = new Date(date)
    result.setHours(23, 59, 59, 999)
    return result
}

function daysAgo(days: number) {
    const end = endOfDay(new Date())
    const start = startOfDay(end)
    start.setDate(start.getDate() - days)
    return [start, end] as [Date, Date]
}

const defaultShortcuts = computed<HsxDateRangeShortcut[]>(() => [
    { text: '今天', value: () => daysAgo(0) },
    { text: '近 7 天', value: () => daysAgo(6) },
    { text: '近 30 天', value: () => daysAgo(29) },
    { text: '近 90 天', value: () => daysAgo(89) }
])

const actualShortcuts = computed(() => {
    if (!isRange.value) return []
    if (props.shortcuts) return props.shortcuts
    return props.showDefaultShortcuts ? defaultShortcuts.value : []
})

const actualValueFormat = computed(() => {
    if (props.valueFormat) return props.valueFormat
    if (isRange.value && props.normalizeRangeBoundary) return 'YYYY-MM-DD HH:mm:ss'
    return props.type === 'date' || props.type === 'daterange' ? 'YYYY-MM-DD' : 'YYYY-MM-DD HH:mm:ss'
})

function actualDisabledDate(date: Date) {
    if (props.disableFuture && date.getTime() > Date.now()) return true
    return props.disabledDate?.(date) || false
}

function exceedsSpan(value: HsxDatePickerValue) {
    if (!props.maxSpanDays || !Array.isArray(value) || !value[0] || !value[1]) return false
    const start = toTimestamp(value[0])
    const end = toTimestamp(value[1])
    if (Number.isNaN(start) || Number.isNaN(end)) return false
    return Math.abs(end - start) > props.maxSpanDays * 24 * 60 * 60 * 1000
}

function handleUpdate(rawValue: HsxDatePickerValue) {
    const nextValue = normalizeValue(rawValue)
    if (exceedsSpan(nextValue)) {
        emit('exceed', nextValue, props.maxSpanDays)
        if (props.showExceedMessage) feedback.warning(`时间跨度不能超过 ${props.maxSpanDays} 天`)
        return
    }
    innerValue.value = nextValue
    emit('update:modelValue', nextValue)
    emit('change', nextValue)
    emit('raw-change', rawValue, nextValue)
}
</script>

<template>
    <span class="hsx-date-picker" :class="$attrs.class" :style="rootStyle">
        <el-date-picker
            v-bind="pickerAttrs"
            :model-value="pickerValue"
            :type="type"
            :value-format="actualValueFormat"
            :format="format"
            :placeholder="placeholder"
            :start-placeholder="startPlaceholder"
            :end-placeholder="endPlaceholder"
            :range-separator="rangeSeparator"
            :disabled-date="actualDisabledDate"
            :shortcuts="actualShortcuts"
            :clearable="clearable"
            :unlink-panels="unlinkPanels"
            @update:model-value="handleUpdate"
        />
    </span>
</template>

<style scoped>
.hsx-date-picker {
    display: inline-flex;
    max-width: 100%;
    vertical-align: middle;
}

.hsx-date-picker :deep(.el-date-editor) {
    width: 100%;
}
</style>
