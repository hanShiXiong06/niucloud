<template>
    <view class="quote-sheet">
        <view class="sheet-head">
            <view class="sheet-title">
                <text class="model">{{ data.model }}</text>
                <text class="capacity">{{ data.capacity }}</text>
            </view>
            <text class="source-count">{{ data.source_count || sources.length }} 张报价单</text>
        </view>

        <view v-for="source in sources" :key="source.dataset_id" class="source-section">
            <view class="source-head">
                <text class="source-name">{{ source.name }}</text>
                <text class="source-date">{{ source.price_date }}</text>
            </view>
            <text v-if="source.used_latest_snapshot" class="snapshot-note">该日期无数据，已采用此前最近快照</text>

            <view v-for="row in source.rows || []" :key="row.capacity_id" class="capacity-section">
                <text class="capacity-name">{{ row.capacity }}</text>
                <view class="grade-grid">
                    <view v-for="item in row.prices || []" :key="item.grade" class="grade-row" :class="{ selected: item.grade === data.selected_grade }">
                        <text class="grade-name">{{ item.grade }}</text>
                        <text class="grade-price">¥{{ price(item.price) }}</text>
                    </view>
                </view>
                <view v-if="row.adjustments?.length" class="adjustments">
                    <view v-for="(item, index) in row.adjustments" :key="`${item.name}_${index}`" class="adjustment-row">
                        <text v-if="item.name" class="adjustment-name">{{ item.name }}</text>
                        <text class="adjustment-content">{{ item.content }}</text>
                    </view>
                </view>
            </view>
            <text v-if="source.adjustment_notice" class="history-note">{{ source.adjustment_notice }}</text>
        </view>

        <text class="disclaimer">{{ data.disclaimer }}</text>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ data: any }>()
const sources = computed(() => Array.isArray(props.data?.datasets) ? props.data.datasets : [])
const price = (value: any) => Number(value || 0).toFixed(0)
</script>

<style lang="scss" scoped>
.quote-sheet { width: 100%; box-sizing: border-box; overflow: hidden; border: 1rpx solid #dfe4ea; border-radius: 8rpx; background: #fff; }
.sheet-head { display: flex; align-items: center; justify-content: space-between; gap: 18rpx; padding: 20rpx 22rpx; background: #f7f9fc; }
.sheet-title { display: flex; min-width: 0; align-items: baseline; gap: 12rpx; }
.model { color: #172033; font-size: 28rpx; font-weight: 650; }
.capacity, .source-count, .source-date { color: #667085; font-size: 20rpx; }
.source-count { flex: none; }
.source-section { padding: 20rpx 22rpx; border-top: 1rpx solid #e8ebef; }
.source-head { display: flex; align-items: center; justify-content: space-between; gap: 18rpx; }
.source-name { min-width: 0; color: #253047; font-size: 24rpx; font-weight: 650; }
.snapshot-note, .history-note { display: block; margin-top: 10rpx; color: #9a6700; font-size: 19rpx; line-height: 1.5; }
.capacity-section { margin-top: 18rpx; }
.capacity-name { display: block; margin-bottom: 10rpx; color: #475467; font-size: 21rpx; font-weight: 600; }
.grade-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9rpx; }
.grade-row { display: flex; min-height: 62rpx; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 8rpx; padding: 0 14rpx; border: 1rpx solid #e3e7ec; border-radius: 6rpx; background: #fafbfc; }
.grade-row.selected { border-color: #8eadee; background: #f1f5ff; }
.grade-name { overflow: hidden; color: #475467; font-size: 20rpx; text-overflow: ellipsis; white-space: nowrap; }
.grade-price { flex: none; color: #d92d20; font-size: 24rpx; font-weight: 700; }
.adjustments { margin-top: 12rpx; padding-top: 10rpx; border-top: 1rpx dashed #e4e7ec; }
.adjustment-row { display: flex; gap: 10rpx; padding: 5rpx 0; color: #667085; font-size: 19rpx; line-height: 1.5; }
.adjustment-name { flex: 0 0 104rpx; color: #344054; }
.adjustment-content { min-width: 0; flex: 1; }
.disclaimer { display: block; padding: 0 22rpx 20rpx; color: #98a2b3; font-size: 18rpx; line-height: 1.45; }
</style>
