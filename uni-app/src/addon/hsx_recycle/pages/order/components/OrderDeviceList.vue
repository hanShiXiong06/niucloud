<template>
  <view class="device-list">
    <view class="device-list__head">
      <view class="device-list__title-wrap">
        <text class="device-list__title">设备明细</text>
        <text class="device-list__count">{{ devices.length }} 台</text>
      </view>
      <view
        v-if="devices.length > 2"
        class="device-list__toggle"
        @tap="$emit('toggle')"
      >
        <text>{{ expanded ? '收起' : '展开' }}</text>
        <up-icon :name="expanded ? 'arrow-up' : 'arrow-down'" size="12" color="var(--recycle-brand)" />
      </view>
    </view>

    <view class="device-list__body">
      <view
        v-for="device in displayDevices"
        :key="device.id"
        class="device-item"
      >
        <view class="device-item__main">
          <text class="device-item__model">{{ device.model || '待识别设备' }}</text>
          <view
            v-if="device.user_sn || device.imei"
            class="serial-row"
            @longpress="handleCopyDeviceCode(device)"
          >
            <text class="serial-row__label">{{ device.user_sn ? '用户串号' : 'IMEI' }}</text>
            <text class="serial-row__value">{{ device.user_sn || device.imei }}</text>
            <view class="serial-copy" @tap.stop="handleCopyDeviceCode(device)">
              <text>复制</text>
            </view>
          </view>
          <text v-if="device.remark" class="device-item__remark">{{ device.remark }}</text>
        </view>
        <view class="device-item__side">
          <OrderStatusBadge
            v-if="device.status_name"
            :text="device.status_name"
            :color="getDeviceStatusInfo(device.status).color"
            :bgColor="getDeviceStatusInfo(device.status).bgColor"
          />
          <text v-if="Number(device.final_price) > 0" class="device-item__price">¥{{ device.final_price }}</text>
          <text v-else-if="Number(device.initial_price) > 0" class="device-item__estimate">预估 ¥{{ device.initial_price }}</text>
          <text v-else class="device-item__pending">待定价</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { OrderDevice } from '../../../types/order'
import { useOrderStatus } from '../../../hooks/useOrderStatus'
import { copyIMEI } from '../../../utils/clipboard'
import OrderStatusBadge from './OrderStatusBadge.vue'

interface Props {
  devices: OrderDevice[]
  expanded?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  expanded: false
})

defineEmits<{
  'toggle': []
}>()

const { getDeviceStatusInfo } = useOrderStatus()

// 显示的设备列表（展开时显示全部，收起时显示前2个）
const displayDevices = computed(() => {
  if (props.expanded || props.devices.length <= 2) {
    return props.devices
  }
  return props.devices.slice(0, 2)
})

const handleCopyDeviceCode = (device: OrderDevice) => {
  copyIMEI(device.user_sn || device.imei)
}
</script>

<style scoped lang="scss">
.device-list__head, .device-list__title-wrap, .device-list__toggle { display: flex; align-items: center; gap: 10rpx; }
.device-list__head { justify-content: space-between; min-height: 52rpx; font-size: 24rpx; color: var(--recycle-text-sub); }
.device-list__toggle { min-height: 56rpx; color: var(--recycle-brand); }
.device-item { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 12rpx; padding: 18rpx 0; }
.device-item + .device-item { border-top: 1rpx solid var(--recycle-line); }
.device-item__main { flex: 1; min-width: 260rpx; }
.device-item__model { display: block; font-size: 28rpx; font-weight: 600; line-height: 40rpx; overflow-wrap: anywhere; }
.device-item__remark { display: block; margin-top: 6rpx; font-size: 23rpx; line-height: 34rpx; color: var(--recycle-text-sub); }
.device-item__side { display: flex; flex-direction: column; align-items: flex-end; gap: 8rpx; margin-left: auto; max-width: 100%; }
.device-item__price { font-size: 30rpx; line-height: 42rpx; font-weight: 600; color: var(--recycle-price); overflow-wrap: anywhere; }
.device-item__estimate, .device-item__pending { color: var(--recycle-text-sub); font-size: 23rpx; line-height: 34rpx; }
.serial-row { display: flex; align-items: center; gap: 8rpx; color: var(--recycle-text-sub); font-size: 22rpx; line-height: 34rpx; }
.serial-row__label { flex-shrink: 0; }
.serial-row__value { min-width: 0; overflow-wrap: anywhere; }
.serial-copy { display: flex; align-items: center; min-height: 60rpx; padding: 0 8rpx; color: var(--recycle-brand); flex-shrink: 0; }
</style>
