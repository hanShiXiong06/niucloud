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
            <text class="serial-row__label">IMEI</text>
            <text class="serial-row__value">{{ device.user_sn || device.imei }}</text>
            <view class="serial-copy" @tap.stop="handleCopyDeviceCode(device)">
              <up-icon name="file-text" size="11" color="#8b96a9" />
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
          <text v-if="Number(device.final_price || device.initial_price) > 0" class="device-item__price">
            ¥{{ device.final_price || device.initial_price }}
          </text>
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
.device-list {
  overflow: hidden;
  border: 1rpx solid #edf0f4;
  border-radius: 18rpx;
  background: #f8fafc;
}

.device-list__head {
  height: 66rpx;
  padding: 0 20rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.device-list__title-wrap {
  display: flex;
  align-items: center;
  gap: 10rpx;
}

.device-list__title {
  color: #667085;
  font-size: 22rpx;
  font-weight: 600;
}

.device-list__count {
  color: #9aa4b2;
  font-size: 20rpx;
}

.device-list__toggle {
  display: flex;
  align-items: center;
  gap: 6rpx;
  color: var(--recycle-brand);
  font-size: 22rpx;
}

.device-list__body {
  padding: 0 14rpx 14rpx;
}

.device-item {
  min-height: 116rpx;
  padding: 18rpx;
  background: #fff;
  border-radius: 14rpx;
  display: flex;
  align-items: flex-start;
  gap: 16rpx;
  box-sizing: border-box;

  & + .device-item {
    margin-top: 10rpx;
  }
}

.device-item__main {
  min-width: 0;
  flex: 1;
}

.device-item__model {
  display: block;
  color: #172033;
  font-size: 25rpx;
  line-height: 36rpx;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.device-item__remark {
  display: block;
  margin-top: 8rpx;
  color: #8b96a9;
  font-size: 21rpx;
  line-height: 30rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.device-item__side {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 12rpx;
}

.device-item__price {
  color: var(--recycle-price);
  font-size: 27rpx;
  line-height: 36rpx;
  font-weight: 700;
}

.device-item__pending {
  color: #9aa4b2;
  font-size: 21rpx;
}

.serial-row {
  margin-top: 7rpx;
  display: flex;
  align-items: center;
  max-width: 100%;
  gap: 7rpx;
}

.serial-row__label {
  color: #a1a9b6;
  font-size: 19rpx;
}

.serial-row__value {
  min-width: 0;
  color: #6f7b8d;
  font-size: 21rpx;
  line-height: 30rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.serial-copy {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30rpx;
  height: 30rpx;
  flex-shrink: 0;
  border-radius: 8rpx;
  background: #f2f4f7;
}
</style>
