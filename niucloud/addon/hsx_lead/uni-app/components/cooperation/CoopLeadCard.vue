<template>
    <view class="coop-lead-card" @tap="$emit('open', lead.id)">
<view class="coop-lead-card__head">
<view class="coop-lead-card__customer">{{ lead.customer_name || '客户需求' }}<text class="coop-business-tag">{{ businessLabels[lead.business_type] || '购机需求' }}</text>
</view>
<text class="coop-status" :class="'is-' + lead.status">{{ lead.status_label || coopStatusLabels[lead.status] || '待核对' }}</text>
</view>
<CoopModels :items="lead.models || []" />
<view class="coop-lead-card__region">
<u-icon name="map" size="13" color="#8b929c" />
<text>{{ lead.region_text || '地区未填写' }}</text>
</view>
<view v-if="counterparty?.name" class="coop-lead-card__shop">
<u-icon name="home" size="14" color="#8b929c" />
<text>{{ direction === 'incoming' ? '来自' : '交付给' }} {{ counterparty.name }}</text>
</view>
<view class="coop-lead-card__foot">
<text>{{ coopDate(lead.update_time || lead.create_time) }}</text>
<text v-if="lead.can_cancel" class="coop-card-cancel" @tap.stop="$emit('cancel', lead.id)">取消线索</text>
<button class="coop-card-action" :class="{ primary: lead.can_assign || lead.can_accept || lead.can_report || lead.can_pay }" @tap.stop="$emit(lead.can_assign ? 'select' : 'open', lead.id)">{{ lead.can_assign ? '选择门店' : lead.can_accept ? '查看并接单' : lead.can_report ? '反馈结果' : lead.can_pay ? '去付佣' : '查看详情' }}</button>
</view>
</view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { businessLabels, coopDate, coopStatusLabels, type CoopRow, type Direction } from '../../api/cooperation'
import CoopModels from './CoopModels.vue'
const props = defineProps<{ lead: CoopRow; direction: Direction }>()
defineEmits<{ (event: 'open' | 'select' | 'cancel', id: number): void }>()
const counterparty = computed(() => props.direction === 'incoming' ? props.lead.publisher : props.lead.receiver)
</script>
<style scoped>.coop-card-cancel { margin-left: auto; padding: 15rpx 6rpx; font-size: 23rpx; color: #788594; white-space: nowrap; }</style>
<style scoped lang="scss">.coop-lead-card { padding: 28rpx; margin-bottom: 20rpx; border-radius: 16rpx; background: #fff; }.coop-lead-card__head { display: flex; justify-content: space-between; gap: 16rpx; align-items: flex-start; margin-bottom: 24rpx; }.coop-lead-card__customer { min-width: 0; font-size: 31rpx; font-weight: 650; line-height: 1.6; word-break: break-word; }.coop-business-tag { display: inline-block; font-size: 21rpx; padding: 1rpx 10rpx; margin-left: 12rpx; color: #7f8791; font-weight: 400; background: #f2f4f6; border-radius: 5rpx; vertical-align: 3rpx; }.coop-status { flex: none; font-size: 22rpx; line-height: 1.7; padding: 3rpx 10rpx; border-radius: 5rpx; color: #828a95; background: #f2f4f6; }.coop-status.is-selecting,.coop-status.is-assigned,.coop-status.is-unpaid { color: #a76637; background: #fff1e8; }.coop-status.is-following { color: #4d6e97; background: #edf3fa; }.coop-status.is-paid { color: #49735e; background: #edf7f0; }.coop-lead-card__region,.coop-lead-card__shop { margin-top: 19rpx; font-size: 23rpx; line-height: 1.7; color: #7b8490; display: flex; align-items: center; gap: 8rpx; }.coop-lead-card__foot { display: flex; justify-content: space-between; align-items: center; gap: 12rpx; border-top: 1rpx solid #eff1f4; margin-top: 23rpx; padding-top: 20rpx; color: #8f97a1; font-size: 21rpx; }.coop-card-action { margin: 0; font-size: 23rpx; padding: 0 25rpx; height: 64rpx; line-height: 64rpx; background: #fff; color: #4c5662; border: 1rpx solid #dde2e8; border-radius: 36rpx; flex: none; }.coop-card-action::after { border: 0; }.coop-card-action.primary { background: var(--primary-color, #d93833); color: #fff; border-color: transparent; }</style>
