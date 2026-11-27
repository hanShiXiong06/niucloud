<template>
    <view class="quote-result">
        <view class="result-topline">
            <text class="sheet-name">{{ row.item_name }}</text>
            <text class="price-date">{{ row.price_date || '日期待更新' }}</text>
        </view>
        <view class="model-line">
            <text class="model-name">{{ row.model_name }}</text>
            <text v-if="row.capacity && row.capacity !== '--'" class="capacity">{{ row.capacity }}</text>
        </view>
        <view v-if="prices.length" class="price-grid">
            <view v-for="entry in prices" :key="entry.name" class="price-cell">
                <text class="price-label">{{ entry.name }}</text>
                <text class="price-value">{{ entry.display }}</text>
            </view>
        </view>
        <text v-else class="no-price">暂未发布价格</text>
        <view v-if="row.remark" class="remark-wrap">
            <text class="remark" :class="{ collapsed: !expanded }">{{ row.remark }}</text>
            <button v-if="row.remark.length > 42" class="remark-toggle" @click="expanded = !expanded">{{ expanded ? '收起备注' : '展开备注' }}</button>
        </view>
        <view class="result-bottom">
            <text class="source-name">{{ row.source_name }}</text>
            <button class="detail-button" @click="emit('open', row)">查看报价单<up-icon name="arrow-right" size="12" color="#2563eb" /></button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { QuoteSearchRow } from '@/addon/recycle_quote_spider/api/quotation'
import { normalizeSpiderPrices } from '@/addon/recycle_quote_spider/pages/price/utils/spiderQuote'
const props = defineProps<{ row: QuoteSearchRow }>()
const emit = defineEmits<{ (event: 'open', row: QuoteSearchRow): void }>()
const expanded = ref(false)
const prices = computed(() => Object.entries(normalizeSpiderPrices(props.row.final_prices || {}, props.row.columns || [])).map(([name, price]) => {
    const value = price && typeof price === 'object' ? ((price as any).final ?? (price as any).price ?? '') : price
    const numeric = value !== null && value !== '' && /^-?\d+(\.\d+)?$/.test(String(value))
    return { name, display: numeric ? `¥${value}` : String(value ?? '').trim() || '--' }
}))
</script>

<style scoped lang="scss">
.quote-result { padding: 28rpx; background: #fff; border: 1px solid #e8ebef; border-radius: 8px; }
.result-topline, .model-line, .result-bottom { display: flex; align-items: center; gap: 16rpx; min-width: 0; }
.result-topline { justify-content: space-between; margin-bottom: 18rpx; align-items: flex-start; }
.sheet-name { flex: 1; min-width: 0; font-size: 24rpx; line-height: 1.5; color: #627080; overflow-wrap: anywhere; }
.price-date { flex-shrink: 0; font-size: 22rpx; line-height: 1.6; color: #87909c; }
.model-line { align-items: baseline; flex-wrap: wrap; gap: 8rpx 16rpx; }
.model-name { font-size: 32rpx; font-weight: 600; color: #20252e; line-height: 1.4; overflow-wrap: anywhere; }
.capacity { font-size: 26rpx; color: #566171; }
.price-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24rpx 16rpx; margin: 24rpx 0; }
.price-cell { min-width: 0; display: flex; flex-direction: column; gap: 8rpx; }
.price-label { color: #6d7580; font-size: 24rpx; line-height: 1.5; overflow-wrap: anywhere; }
.price-value { font-size: 30rpx; color: #bd4c27; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; }
.no-price { display: block; padding: 24rpx 0; color: #87909c; font-size: 26rpx; }
.remark-wrap { border-top: 1px solid #f0f2f4; padding-top: 18rpx; }
.remark { display: block; font-size: 24rpx; color: #707885; line-height: 1.6; white-space: pre-line; overflow-wrap: anywhere; }
.remark.collapsed { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.remark-toggle, .detail-button { display: flex; align-items: center; margin: 0; padding: 0; border: 0; border-radius: 0; background: transparent; line-height: 1.5; }
.remark-toggle::after, .detail-button::after { border: 0; }
.remark-toggle { color: #627080; font-size: 24rpx; padding: 8rpx 0; }
.result-bottom { margin-top: 20rpx; justify-content: space-between; }
.source-name { flex: 1; min-width: 0; color: #9299a3; font-size: 22rpx; overflow-wrap: anywhere; }
.detail-button { gap: 6rpx; flex-shrink: 0; font-size: 26rpx; color: #2563eb; }
</style>
