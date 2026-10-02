<template>
  <!-- 沿用商城提货时间的底部弹窗 / 左侧日期 / 右侧时段交互，数据由回收后端提供。 -->
  <u-popup :show="show" mode="bottom" :round="16" :overlayStyle="{ touchAction: 'none' }" closeable @close="emit('update:show', false)">
    <view class="pickup-picker" @touchmove.stop>
      <view class="picker-title">选择上门取件时间</view>
      <view v-if="days.length" class="picker-body">
        <scroll-view scroll-y class="picker-dates">
          <view v-for="day in days" :key="day.date" class="picker-date"
            :class="{ active: activeDate === day.date }" @tap="activeDate = day.date">
            <text class="date-name">{{ day.label }}</text><text class="date-detail">{{ day.date_text }}</text>
          </view>
        </scroll-view>
        <scroll-view scroll-y class="picker-slots">
          <view v-for="slot in activeDay?.slots || []" :key="slot.value" class="picker-slot"
            :class="{ selected: modelValue === slot.value }" @tap="select(slot)">
            <view><text class="slot-label">{{ slot.label }}</text><text v-if="slot.hint" class="slot-hint">{{ slot.hint }}</text></view>
            <up-icon v-if="modelValue === slot.value" name="checkmark" size="19" color="var(--primary-color, #2563eb)" />
          </view>
        </scroll-view>
      </view>
      <view v-else class="picker-empty">暂无可预约时段，请联系门店确认。</view>
      <view class="picker-note">北京时间 · 实际上门时间以快递员联系为准</view>
    </view>
  </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { PickupTimeDay, PickupTimeSlot } from '../../../types/order'
import { useRecyclePopupLock } from '../../../hooks/useRecyclePopupScroll'

const props = defineProps<{ show: boolean; modelValue: string; days: PickupTimeDay[] }>()
useRecyclePopupLock(() => props.show)
const emit = defineEmits<{ 'update:show': [value: boolean]; select: [slot: PickupTimeSlot] }>()
const activeDate = ref('')
const activeDay = computed(() => props.days.find(day => day.date === activeDate.value) || props.days[0])
watch(() => [props.show, props.days] as const, () => {
  if (props.show) activeDate.value = props.days.find(day => day.slots.some(slot => slot.value === props.modelValue))?.date || props.days[0]?.date || ''
}, { immediate: true })
const select = (slot: PickupTimeSlot) => {
  emit('select', slot)
  emit('update:show', false)
}
</script>

<style scoped lang="scss">
.pickup-picker { background: #fff; color: #1f2937; border-radius: 16rpx 16rpx 0 0; overflow: hidden; }
.picker-title { padding: 30rpx 78rpx; font-size: 30rpx; font-weight: 600; text-align: center; border-bottom: 1rpx solid #edf0f3; }
.picker-body { display: flex; height: 550rpx; }
.picker-dates { flex-shrink: 0; width: 220rpx; height: 100%; background: #f6f7f9; overscroll-behavior: contain; }
.picker-date { display: flex; flex-direction: column; gap: 8rpx; padding: 26rpx 28rpx; border-left: 6rpx solid transparent; }
.picker-date.active { border-left-color: var(--primary-color, #2563eb); background: #fff; color: var(--primary-color, #2563eb); }
.date-name { font-size: 28rpx; font-weight: 500; }
.date-detail { font-size: 23rpx; color: #808895; }
.picker-slots { flex: 1; min-width: 0; width: 0; height: 100%; overscroll-behavior: contain; }
.picker-slot { min-height: 92rpx; display: flex; align-items: center; justify-content: space-between; gap: 12rpx; margin: 0 28rpx; padding: 12rpx 0; box-sizing: border-box; border-bottom: 1rpx solid #edf0f3; }
.slot-label { display: block; font-size: 28rpx; }
.slot-hint { display: block; margin-top: 6rpx; color: #89919e; font-size: 22rpx; }
.picker-slot.selected { color: var(--primary-color, #2563eb); font-weight: 500; }
.picker-note { padding: 20rpx 24rpx; color: #89919e; font-size: 23rpx; text-align: center; border-top: 1rpx solid #edf0f3; }
.picker-empty { padding: 120rpx 30rpx; color: #89919e; font-size: 26rpx; text-align: center; }
</style>
