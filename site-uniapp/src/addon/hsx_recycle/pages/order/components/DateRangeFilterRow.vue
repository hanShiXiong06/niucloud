<template>
    <view class="date-range-row">
        <text class="date-range-row__label">{{ label }}</text>
        <view class="date-range-row__control">
            <view
                class="date-range-row__date"
                :class="{ 'date-range-row__date--empty': !start }"
                @click="openPicker('start')"
            >
                {{ start || startPlaceholder }}
            </view>
            <text class="date-range-row__separator">至</text>
            <view
                class="date-range-row__date"
                :class="{ 'date-range-row__date--empty': !end }"
                @click="openPicker('end')"
            >
                {{ end || endPlaceholder }}
            </view>
            <view v-if="start || end" class="date-range-row__clear" @click.stop="clearRange">
                <text class="nc-iconfont nc-icon-cuohaoV6xx1"></text>
            </view>
        </view>

        <u-datetime-picker
            v-model="pickerValue"
            :show="pickerVisible"
            mode="date"
            :title="pickerTitle"
            :minDate="minDate"
            :maxDate="maxDate"
            closeOnClickOverlay
            @confirm="confirmPicker"
            @cancel="closePicker"
            @close="closePicker"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(defineProps<{
    label: string
    start?: string
    end?: string
    startPlaceholder?: string
    endPlaceholder?: string
    minDate?: number
    maxDate?: number
}>(), {
    start: '',
    end: '',
    startPlaceholder: '开始',
    endPlaceholder: '结束',
    minDate: new Date('2000/01/01 00:00:00').getTime(),
    maxDate: new Date('2099/12/31 23:59:59').getTime()
})

const emit = defineEmits<{
    (event: 'update:start', value: string): void
    (event: 'update:end', value: string): void
    (event: 'change', value: { start: string, end: string }): void
}>()

const pickerVisible = ref(false)
const pickerField = ref<'start' | 'end'>('start')
const pickerValue = ref(Date.now())

const pickerTitle = computed(() => {
    return `${ props.label }${ pickerField.value === 'start' ? '开始日期' : '结束日期' }`
})

const openPicker = (field: 'start' | 'end') => {
    pickerField.value = field
    const currentValue = field === 'start' ? props.start : props.end
    pickerValue.value = parseDateValue(currentValue) || Date.now()
    pickerVisible.value = true
}

const closePicker = () => {
    pickerVisible.value = false
}

const confirmPicker = (event: any) => {
    const value = formatDate(event?.value || pickerValue.value)
    if (pickerField.value === 'start') {
        emit('update:start', value)
        emit('change', { start: value, end: props.end || '' })
    } else {
        emit('update:end', value)
        emit('change', { start: props.start || '', end: value })
    }
    closePicker()
}

const clearRange = () => {
    emit('update:start', '')
    emit('update:end', '')
    emit('change', { start: '', end: '' })
}

const parseDateValue = (value: string) => {
    if (!value) return 0
    const timestamp = new Date(`${ value.replace(/-/g, '/') } 00:00:00`).getTime()
    return Number.isFinite(timestamp) ? timestamp : 0
}

const formatDate = (value: string | number) => {
    const date = new Date(Number(value))
    if (Number.isNaN(date.getTime())) return ''
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${ year }-${ month }-${ day }`
}
</script>

<style scoped lang="scss">
.date-range-row {
    min-height: 72rpx;
    display: flex;
    align-items: center;
    border-bottom: 1rpx solid #f1f5f9;
}

.date-range-row:last-child {
    border-bottom: 0;
}

.date-range-row__label {
    width: 138rpx;
    flex-shrink: 0;
    font-size: 24rpx;
    color: #475569;
}

.date-range-row__control {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.date-range-row__date {
    min-width: 180rpx;
    height: 56rpx;
    padding: 0 14rpx;
    border-radius: 10rpx;
    background: #f6f8fb;
    color: #111827;
    font-size: 23rpx;
    line-height: 56rpx;
    text-align: center;
    box-sizing: border-box;
}

.date-range-row__date--empty {
    color: #94a3b8;
}

.date-range-row__separator {
    margin: 0 10rpx;
    font-size: 22rpx;
    color: #94a3b8;
}

.date-range-row__clear {
    width: 44rpx;
    height: 56rpx;
    margin-left: 6rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 22rpx;
}
</style>
