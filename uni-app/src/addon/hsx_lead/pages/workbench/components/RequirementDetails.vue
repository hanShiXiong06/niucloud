<template>
    <view class="requirements-detail">
        <view class="requirements-title">完整购机需求</view>
        <view class="requirements-mode">{{ mode === 'all' ? '分别要多台' : '任选一款' }} · {{ items.length }} 款机型</view>
        <view class="requirements-explanation">{{ mode === 'all' ? '以下每项分别需要，请逐项核对设备、数量和预算。' : '以下是可接受的备选机型，只需满足其中一款，不是每款都要。' }}</view>
        <view v-for="(item, index) in items" :key="`${item.catalog_id || item.title}-${index}`" class="requirement-row">
            <view class="requirement-heading"><text>{{ index + 1 }}. {{ item.title }}</text><text class="requirement-quantity">需求 {{ item.quantity || 1 }} 台</text></view>
            <view v-if="item.brand" class="requirement-meta">品牌：{{ item.brand }}</view>
            <view class="requirement-meta">单台预算：{{ budget(item) }}</view>
            <view v-if="item.confirmed_quantity !== undefined" class="requirement-meta">已核验成交 {{ item.confirmed_quantity }} 台 · 剩余需求 {{ item.remaining_quantity }} 台</view>
            <view class="requirement-remark">补充要求：{{ item.remark || '无补充要求' }}</view>
        </view>
        <view class="requirements-explanation">需求数量不是门店已确认的可售库存；接单前仍需核实，不代表已锁货。</view>
    </view>
</template>
<script setup lang="ts">
withDefaults(defineProps<{ items: Record<string, any>[], mode?: string }>(), { mode: 'any' })
const money = (value: any) => `¥${Number(value || 0).toFixed(2)}`
const budget = (row: Record<string, any>) => Number(row.budget_min) > 0 && Number(row.budget_max) > 0 ? `${money(row.budget_min)} – ${money(row.budget_max)}` : Number(row.budget_max) > 0 ? `最高 ${money(row.budget_max)}` : Number(row.budget_min) > 0 ? `${money(row.budget_min)} 起` : '预算未确定'
</script>
<style scoped lang="scss">
.requirements-detail { padding: 28rpx 30rpx; border-bottom: 1rpx solid #edf0f5; }
.requirements-title { font-size: 28rpx; font-weight: 650; margin-bottom: 20rpx; }
.requirements-mode { display: inline-block; background: #edf1ff; color: #4264ec; padding: 9rpx 16rpx; border-radius: 10rpx; font-size: 23rpx; }
.requirements-explanation { margin: 16rpx 0; color: #8797af; font-size: 22rpx; line-height: 1.8; }
.requirement-row { border: 1rpx solid #e8edf6; border-radius: 14rpx; padding: 24rpx; margin: 20rpx 0; }
.requirement-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16rpx; font-size: 26rpx; font-weight: 600; line-height: 1.7; }
.requirement-heading > text:first-child { flex: 1; min-width: 0; }
.requirement-quantity { flex: none; color: #4264ec; font-size: 23rpx; }
.requirement-meta, .requirement-remark { margin-top: 12rpx; font-size: 23rpx; line-height: 1.8; color: #7f90a9; }
.requirement-remark { color: #536987; white-space: pre-wrap; word-break: break-word; }
</style>
