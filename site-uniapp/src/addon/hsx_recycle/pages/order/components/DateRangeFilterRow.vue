<template>
    <view class="date-range-row">
        <text class="date-range-row__label">{{ label }}</text>
        <view class="date-range-row__control">
            <!-- 快捷选项 -->
            <view class="date-range-row__shortcuts">
                <view
                    v-for="item in shortcutOptions"
                    :key="item.value"
                    class="date-range-row__shortcut"
                    :class="{ 'date-range-row__shortcut--active': currentShortcut === item.value }"
                    @click="handleShortcut(item.value)"
                >
                    {{ item.label }}
                </view>
            </view>
            
            <!-- 自定义日期范围：点击调起页面级 u-calendar 范围日历（由父级在抽屉外渲染，避免被抽屉裁切） -->
            <view v-if="showCustomRange" class="date-range-row__custom" @click="emit('request-calendar')">
                <view class="date-range-row__date" :class="{ 'date-range-row__date--empty': !start }">
                    {{ start || startPlaceholder }}
                </view>
                <text class="date-range-row__separator">至</text>
                <view class="date-range-row__date" :class="{ 'date-range-row__date--empty': !end }">
                    {{ end || endPlaceholder }}
                </view>
                <view v-if="start || end" class="date-range-row__clear" @click.stop="clearRange">
                    <text class="nc-iconfont nc-icon-cuohaoV6xx1"></text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

interface ShortcutOption {
    label: string
    value: string
}

const props = withDefaults(defineProps<{
    label: string
    start?: string
    end?: string
    startPlaceholder?: string
    endPlaceholder?: string
    minDate?: number
    maxDate?: number
    shortcuts?: ShortcutOption[]
}>(), {
    start: '',
    end: '',
    startPlaceholder: '开始日期',
    endPlaceholder: '结束日期',
    minDate: new Date('2000/01/01 00:00:00').getTime(),
    maxDate: Date.now(),
    shortcuts: () => [
        { label: '今天', value: 'today' },
        { label: '昨天', value: 'yesterday' },
        { label: '近7天', value: 'last7days' },
        { label: '自定义', value: 'custom' }
    ]
})

const emit = defineEmits<{
    (event: 'update:start', value: string): void
    (event: 'update:end', value: string): void
    (event: 'change', value: { start: string, end: string }): void
    (event: 'request-calendar'): void
}>()

const currentShortcut = ref<string>('')
const showCustomRange = ref(false)

const formatDate = (value: string | number) => {
    const date = new Date(Number(value))
    if (Number.isNaN(date.getTime())) return ''
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

// 监听外部值变化，自动判断当前快捷选项
watch(() => [props.start, props.end], ([newStart, newEnd]) => {
    if (!newStart && !newEnd) {
        currentShortcut.value = ''
        showCustomRange.value = false
        return
    }
    
    const today = formatDate(Date.now())
    const yesterday = formatDate(Date.now() - 86400000)
    const last7DaysStart = formatDate(Date.now() - 6 * 86400000)
    
    if (newStart === today && newEnd === today) {
        currentShortcut.value = 'today'
        showCustomRange.value = false
    } else if (newStart === yesterday && newEnd === yesterday) {
        currentShortcut.value = 'yesterday'
        showCustomRange.value = false
    } else if (newStart === last7DaysStart && newEnd === today) {
        currentShortcut.value = 'last7days'
        showCustomRange.value = false
    } else if (newStart || newEnd) {
        currentShortcut.value = 'custom'
        showCustomRange.value = true
    } else {
        currentShortcut.value = ''
        showCustomRange.value = false
    }
}, { immediate: true })

const shortcutOptions = computed(() => props.shortcuts)

// 处理快捷选项点击
const handleShortcut = (value: string) => {
    currentShortcut.value = value
    
    const today = formatDate(Date.now())
    const yesterday = formatDate(Date.now() - 86400000)
    const last7DaysStart = formatDate(Date.now() - 6 * 86400000)
    
    let startValue = ''
    let endValue = ''
    
    switch (value) {
        case 'today':
            startValue = today
            endValue = today
            showCustomRange.value = false
            break
        case 'yesterday':
            startValue = yesterday
            endValue = yesterday
            showCustomRange.value = false
            break
        case 'last7days':
            startValue = last7DaysStart
            endValue = today
            showCustomRange.value = false
            break
        case 'custom':
            showCustomRange.value = true
            // 一点即开：展开自定义范围的同时请求父级调起范围日历；不清空已有值
            emit('request-calendar')
            return
        default:
            // 支持自定义扩展的快捷选项
            startValue = ''
            endValue = ''
            showCustomRange.value = false
    }
    
    emit('update:start', startValue)
    emit('update:end', endValue)
    emit('change', { start: startValue, end: endValue })
}

const clearRange = () => {
    emit('update:start', '')
    emit('update:end', '')
    emit('change', { start: '', end: '' })
    showCustomRange.value = false
    currentShortcut.value = ''
}
</script>

<style scoped lang="scss">
.date-range-row {
    min-height: 72rpx;
    display: flex;
    align-items: flex-start;
    border-bottom: 1rpx solid #f1f5f9;
    padding: 16rpx 0;
}

.date-range-row:last-child {
    border-bottom: 0;
}

.date-range-row__label {
    width: 138rpx;
    flex-shrink: 0;
    font-size: 24rpx;
    color: #475569;
    padding-top: 10rpx;
}

.date-range-row__control {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12rpx;
}

.date-range-row__shortcuts {

    display: flex;
    gap: 8rpx;
    flex-wrap: no-wrap;
    width: 400rpx;
}

.date-range-row__shortcut {
    padding: 6rpx 16rpx;
    font-size: 22rpx;
    color: #64748b;
    background: #f1f5f9;
    border-radius: 6rpx;
    border: 1rpx solid transparent;
    transition: all 0.2s;
    
    &:active {
        opacity: 0.7;
    }
    
    &--active {
        color: var(--hsx-primary-light);
        background: var(--hsx-primary-50);
        border-color: var(--hsx-primary-light);
    }
}

.date-range-row__custom {
    display: flex;
    align-items: center;
}

.date-range-row__date {
    min-width: 160rpx;
    height: 56rpx;
    padding: 0 14rpx;
    border-radius: 10rpx;
    background: #f6f8fb;
    color: #111827;
    font-size: 20rpx;
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
    flex-shrink: 0;
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