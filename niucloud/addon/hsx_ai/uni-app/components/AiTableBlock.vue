<template>
    <view v-if="columns.length" class="data-table-card">
        <view v-if="data.title || data.subtitle" class="data-table-head">
            <text v-if="data.title" class="data-table-title">{{ data.title }}</text>
            <text v-if="data.subtitle" class="data-table-subtitle">{{ data.subtitle }}</text>
        </view>
        <scroll-view scroll-x :show-scrollbar="false" class="data-table-scroll">
            <view class="data-table" :style="{ minWidth: `${tableWidth}rpx` }">
                <view class="data-row data-head-row">
                    <view v-for="column in columns" :key="column.key" class="data-cell" :class="`align-${column.align}`" :style="{ width: `${column.width}rpx` }">{{ column.label }}</view>
                </view>
                <view v-for="(row, rowIndex) in rows" :key="rowIndex" class="data-row">
                    <view v-for="column in columns" :key="column.key" class="data-cell" :class="`align-${column.align}`" :style="{ width: `${column.width}rpx` }"><text selectable>{{ cell(row, column.key) }}</text></view>
                </view>
            </view>
        </scroll-view>
        <text v-if="data.note" class="data-table-note">{{ data.note }}</text>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ data: any }>()
const columns = computed(() => (Array.isArray(props.data?.columns) ? props.data.columns : []).slice(0, 12).map((column: any, index: number) => {
    const item = typeof column === 'string' ? { key: column, label: column } : (column || {})
    const requestedWidth = Number(item.width || 190)
    return {
        key: String(item.key || `column_${index}`),
        label: String(item.label || item.key || ''),
        align: ['left', 'center', 'right'].includes(item.align) ? item.align : 'left',
        width: Number.isFinite(requestedWidth) ? Math.max(140, Math.min(320, requestedWidth)) : 190,
    }
}))
const rows = computed(() => (Array.isArray(props.data?.rows) ? props.data.rows : []).slice(0, 100))
const tableWidth = computed(() => Math.max(380, columns.value.reduce((sum: number, column: any) => sum + column.width, 0)))
const cell = (row: any, key: string) => {
    const value = Array.isArray(row) ? row[columns.value.findIndex((column: any) => column.key === key)] : row?.[key]
    if (value === null || value === undefined) return '-'
    return typeof value === 'object' ? JSON.stringify(value) : String(value)
}
</script>

<style lang="scss" scoped>
.data-table-card { width: 100%; box-sizing: border-box; overflow: hidden; border: 1rpx solid #dfe3e8; border-radius: 8rpx; background: #fff; }.data-table-head { display: flex; flex-direction: column; gap: 5rpx; padding: 18rpx 20rpx; border-bottom: 1rpx solid #e7eaee; }.data-table-title { color: #172033; font-size: 25rpx; font-weight: 650; }.data-table-subtitle, .data-table-note { color: #7c8799; font-size: 19rpx; line-height: 1.5; }.data-table-scroll { width: 100%; }.data-table { width: max-content; }.data-row { display: flex; border-top: 1rpx solid #e7eaee; }.data-row:first-child { border-top: 0; }.data-head-row { background: #f4f6f8; color: #344054; font-weight: 650; }.data-cell { flex: none; box-sizing: border-box; min-height: 64rpx; padding: 13rpx 15rpx; border-left: 1rpx solid #e7eaee; color: #475467; font-size: 21rpx; line-height: 1.5; word-break: break-word; }.data-cell:first-child { border-left: 0; }.align-center { text-align: center; }.align-right { text-align: right; }.data-table-note { display: block; padding: 14rpx 20rpx; border-top: 1rpx solid #e7eaee; }
</style>
