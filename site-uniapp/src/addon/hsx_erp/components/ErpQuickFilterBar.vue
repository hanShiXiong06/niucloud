<template>
    <view class="quick-filter-bar" :style="barStyle">
        <view
            v-for="item in items"
            :key="item.key"
            class="quick-filter-bar__item"
            :class="{ active: isActive(item) }"
            @click="open(item)"
        >
            <text>{{ displayLabel(item) }}</text>
            <u-icon
                name="arrow-down-fill"
                size="9"
                :color="isActive(item) ? '#2563eb' : '#64748b'"
            />
        </view>
    </view>

    <u-popup
        :show="visible"
        mode="bottom"
        :safe-area-inset-bottom="true"
        border-radius="28rpx"
        @close="visible = false"
    >
        <view class="quick-filter-popup">
            <view class="quick-filter-popup__head">
                <text>{{ current?.title || current?.label || '请选择' }}</text>
                <u-icon name="close" size="20" color="#94a3b8" @click="visible = false" />
            </view>
            <scroll-view scroll-y class="quick-filter-popup__options">
                <view
                    v-for="option in current?.options || []"
                    :key="String(option.value)"
                    class="quick-filter-option"
                    :class="{ active: sameValue(current?.value, option.value) }"
                    @click="select(option.value)"
                >
                    <text>{{ option.label }}</text>
                    <u-icon
                        v-if="sameValue(current?.value, option.value)"
                        name="checkmark-circle-fill"
                        size="20"
                        color="#2563eb"
                    />
                </view>
            </scroll-view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

export type ErpQuickFilterOption = {
    label: string
    value: string | number
}

export type ErpQuickFilterItem = {
    key: string
    label: string
    title?: string
    value?: string | number
    options: ErpQuickFilterOption[]
}

const props = withDefaults(defineProps<{
    items?: ErpQuickFilterItem[]
    maxColumns?: number
}>(), {
    items: () => [],
    maxColumns: 4,
})

const emit = defineEmits<{
    (e: 'change', payload: { key: string; value: string | number }): void
    (e: 'trigger', item: ErpQuickFilterItem): void
}>()

const visible = ref(false)
const currentKey = ref('')
const current = computed(() => props.items.find(item => item.key === currentKey.value))
const columns = computed(() => Math.max(1, Math.min(props.maxColumns, props.items.length || 1)))
const barStyle = computed(() => props.items.length === 1
    ? 'grid-template-columns:minmax(0,180rpx);'
    : `grid-template-columns:repeat(${columns.value},minmax(0,1fr));`)

function sameValue(left: unknown, right: unknown) {
    return String(left ?? '') === String(right ?? '')
}

function isActive(item: ErpQuickFilterItem) {
    return !sameValue(item.value, '') && item.value !== undefined && item.value !== null
}

function displayLabel(item: ErpQuickFilterItem) {
    if (!isActive(item)) return item.label
    return item.options.find(option => sameValue(option.value, item.value))?.label || item.label
}

function open(item: ErpQuickFilterItem) {
    if (!item.options.length) {
        emit('trigger', item)
        return
    }
    currentKey.value = item.key
    visible.value = true
}

function select(value: string | number) {
    const key = current.value?.key
    if (!key) return
    visible.value = false
    emit('change', { key, value })
}
</script>

<style scoped lang="scss">
.quick-filter-bar {
    display: grid;
    gap: 10rpx;
}

.quick-filter-bar__item {
    height: 52rpx;
    min-width: 0;
    padding: 0 12rpx;
    border-radius: 10rpx;
    background: #fff;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6rpx;
    box-sizing: border-box;
    font-size: 23rpx;
}

.quick-filter-bar__item text {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-filter-bar__item.active {
    color: #2563eb;
    background: #eff6ff;
    font-weight: 600;
}

.quick-filter-popup {
    max-height: 68vh;
    background: #fff;
    display: flex;
    flex-direction: column;
}

.quick-filter-popup__head {
    height: 92rpx;
    padding: 0 32rpx;
    border-bottom: 1rpx solid #eef2f7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-sizing: border-box;
    color: #0f172a;
    font-size: 30rpx;
    font-weight: 700;
}

.quick-filter-popup__options {
    max-height: calc(68vh - 92rpx);
    padding: 8rpx 30rpx 28rpx;
    box-sizing: border-box;
}

.quick-filter-option {
    min-height: 88rpx;
    padding: 0 8rpx;
    border-bottom: 1rpx solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #334155;
    font-size: 27rpx;
}

.quick-filter-option.active {
    color: #2563eb;
    font-weight: 600;
}
</style>
