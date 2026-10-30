<template>
    <view>
        <view class="sale-note">{{ mode === 'all' ? '只填本批实际售出的数量，其余保持 0。未满足的需求默认继续跟进。' : '任选一款，本批只填写一种机型；部分成交后只能继续该款或结束剩余。' }}</view>
        <view v-for="row in modelValue" :key="row.catalog_id" class="sale-item"><view class="sale-title">{{ row.title }}</view><view class="sale-progress">需求 {{ row.quantity }} 台 · 已核验成交 {{ row.confirmed_quantity }} 台 · 剩余 {{ row.remaining_quantity }} 台</view><view v-if="locked(row)" class="sale-note">已部分成交其他备选，本项不可申报</view><view class="sale-fields"><view><text>本批售出数量</text><input type="number" :value="row.sale_quantity" :disabled="locked(row) || Number(row.remaining_quantity) <= 0" placeholder="默认为 0" @input="update(row, 'sale_quantity', Number($event.detail.value || 0))" /></view><view><text>实际单价（元）</text><input type="digit" :value="row.unit_price" :disabled="!Number(row.sale_quantity)" placeholder="售出后填写" @input="update(row, 'unit_price', $event.detail.value)" /></view></view><view class="sale-progress">小计 {{ money(Number(row.sale_quantity || 0) * Number(row.unit_price || 0)) }}</view></view>
        <view class="sale-total">本批金额预览 {{ money(saleTotal(modelValue)) }}</view><view class="sale-progress">最终金额由服务器按售出数量 × 实际单价重新计算，不手填总金额。</view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { saleTotal } from '../../../utils/deal'
type Row = Record<string, any>
const props = defineProps<{ modelValue: Row[], task: Row }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: Row[]): void }>()
const mode = computed(() => props.task.demand_mode || props.task.lead?.demand_mode || 'any')
const money = (value: any) => `¥${Number(value || 0).toFixed(2)}`
function locked(row: Row) { return !!props.task.any_selected_catalog_id && String(row.catalog_id) !== String(props.task.any_selected_catalog_id) }
function update(row: Row, key: string, value: any) { emit('update:modelValue', props.modelValue.map(item => String(item.catalog_id) === String(row.catalog_id) ? { ...item, [key]: value } : item)) }
</script>
<style scoped lang="scss">
.sale-note { margin: 18rpx 0; padding: 20rpx; background: #eef3ff; color: #7489ad; font-size: 23rpx; line-height: 1.8; border-radius: 12rpx; }.sale-item { padding: 24rpx; border: 1rpx solid #e2e9f5; border-radius: 14rpx; margin: 18rpx 0; background: #fff; }.sale-title { font-size: 26rpx; font-weight: 600; }.sale-progress { color: #899ab2; font-size: 22rpx; line-height: 1.8; margin-top: 12rpx; }.sale-fields { display: flex; gap: 18rpx; margin-top: 18rpx; }.sale-fields > view { width: 50%; }.sale-fields text { display: block; font-size: 23rpx; color: #7186a5; margin-bottom: 12rpx; }.sale-fields input { background: #f4f7fc; padding: 16rpx; border-radius: 10rpx; font-size: 26rpx; }.sale-total { font-size: 28rpx; color: #4264ec; font-weight: 650; margin-top: 22rpx; }
</style>
