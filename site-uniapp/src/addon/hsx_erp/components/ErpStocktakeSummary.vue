<template>
    <view class="stocktake-summary" :class="{ 'stocktake-summary--compact': compact }" @click="emit('click')">
        <view class="stocktake-summary__head">
            <view class="stocktake-summary__main">
                <text class="stocktake-summary__place">{{ scopeText }}</text>
                <text v-if="!compact" class="stocktake-summary__no">{{ data.stocktake_no || '库存盘点' }}</text>
            </view>
            <u-tag
                :text="statusLabel"
                :type="data.status_meta?.type || 'info'"
                plain
                plainFill
                size="mini"
            />
        </view>

        <view class="stocktake-summary__progress-head">
            <text>盘点进度</text>
            <text>{{ data.scanned_count || 0 }} / {{ data.expected_count || 0 }}</text>
        </view>
        <u-line-progress
            :percentage="progress"
            :showText="false"
            activeColor="#3b6ef5"
            inactiveColor="#eef2f7"
            height="7"
        />

        <view class="stocktake-summary__metrics">
            <view v-for="item in metrics" :key="item.key" class="stocktake-summary__metric">
                <text class="stocktake-summary__metric-value" :class="item.tone">{{ data[item.key] || 0 }}</text>
                <text class="stocktake-summary__metric-label">{{ item.label }}</text>
            </view>
        </view>

        <view v-if="showFooter" class="stocktake-summary__foot">
            <view class="stocktake-summary__operator">
                <u-icon name="account" color="#94a3b8" size="13" />
                <text>盘点人 {{ data.counter_name || '-' }}</text>
            </view>
            <text>{{ formattedTime }}</text>
            <view v-if="linkText" class="stocktake-summary__link">
                <text>{{ linkText }}</text>
                <u-icon name="arrow-right" color="#3b6ef5" size="12" />
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import { erpEnumLabel } from '@/addon/hsx_erp/utils/display'

const props = withDefaults(defineProps<{
    data: Record<string, any>
    compact?: boolean
    showFooter?: boolean
    linkText?: string
}>(), {
    compact: false,
    showFooter: false,
    linkText: '',
})

const emit = defineEmits<{ (e: 'click'): void }>()
const statusLabels: Record<string, string> = { counting: '盘点中', pending_review: '待复核', completed: '已完成', cancelled: '已取消' }
const statusLabel = computed(() => erpEnumLabel(props.data.status_meta?.label, statusLabels, erpEnumLabel(props.data.status, statusLabels)))

const metrics = [
    { key: 'normal_count', label: '正常', tone: 'success' },
    { key: 'missing_count', label: '盘亏', tone: 'danger' },
    { key: 'surplus_count', label: '盘盈', tone: 'warning' },
    { key: 'unresolved_count', label: '待处理', tone: 'primary' },
]

const scopeText = computed(() => {
    const warehouse = props.data.warehouse_name || '未设置仓库'
    return `${warehouse}${props.data.location_name ? ` / ${props.data.location_name}` : ' / 全部库位'}`
})

const progress = computed(() => Math.min(100, Math.max(0, Number(props.data.progress_percent || 0))))
const formattedTime = computed(() => formatErpTime(props.data.create_at))
</script>

<style scoped lang="scss">
.stocktake-summary {
    background: #fff;
    border-radius: 24rpx;
    padding: 26rpx 28rpx;
    box-shadow: 0 2rpx 12rpx rgba(15, 23, 42, 0.04);
}

.stocktake-summary__head,
.stocktake-summary__progress-head,
.stocktake-summary__foot,
.stocktake-summary__operator,
.stocktake-summary__link {
    display: flex;
    align-items: center;
}

.stocktake-summary__head {
    justify-content: space-between;
    gap: 18rpx;
}

.stocktake-summary__main {
    min-width: 0;
}

.stocktake-summary__place,
.stocktake-summary__no {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stocktake-summary__place {
    color: #0f172a;
    font-size: 30rpx;
    font-weight: 650;
}

.stocktake-summary__no {
    margin-top: 7rpx;
    color: #94a3b8;
    font-size: 22rpx;
}

.stocktake-summary__progress-head {
    justify-content: space-between;
    margin: 22rpx 0 10rpx;
    color: #64748b;
    font-size: 22rpx;
}

.stocktake-summary__metrics {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    margin-top: 22rpx;
    padding: 18rpx 0;
    border-radius: 16rpx;
    background: #f8fafc;
}

.stocktake-summary__metric {
    position: relative;
    text-align: center;
}

.stocktake-summary__metric + .stocktake-summary__metric::before {
    position: absolute;
    top: 8rpx;
    bottom: 8rpx;
    left: 0;
    width: 1rpx;
    background: #e8edf4;
    content: '';
}

.stocktake-summary__metric-value,
.stocktake-summary__metric-label {
    display: block;
}

.stocktake-summary__metric-value {
    color: #334155;
    font-size: 30rpx;
    font-weight: 700;
}

.stocktake-summary__metric-value.success { color: #16a34a; }
.stocktake-summary__metric-value.danger { color: #dc2626; }
.stocktake-summary__metric-value.warning { color: #d97706; }
.stocktake-summary__metric-value.primary { color: #2563eb; }

.stocktake-summary__metric-label {
    margin-top: 5rpx;
    color: #94a3b8;
    font-size: 20rpx;
}

.stocktake-summary__foot {
    gap: 16rpx;
    margin-top: 18rpx;
    padding-top: 16rpx;
    border-top: 1rpx solid #eef2f7;
    color: #94a3b8;
    font-size: 21rpx;
}

.stocktake-summary__operator,
.stocktake-summary__link {
    gap: 6rpx;
}

.stocktake-summary__link {
    margin-left: auto;
    color: #3b6ef5;
}

.stocktake-summary--compact {
    border-radius: 20rpx;
    padding: 24rpx;
}
</style>
