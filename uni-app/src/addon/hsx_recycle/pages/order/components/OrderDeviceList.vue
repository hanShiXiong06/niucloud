<template>
  <view class="device-list">
    <!-- 设备列表头部 -->
    <view class="flex items-center justify-between mb-2">
      <text class="text-xs text-gray-600">设备列表</text>
      <view
        v-if="devices.length > 2"
        class="flex items-center gap-1 text-xs text-blue"
        @click="$emit('toggle')"
      >
        <text>{{ expanded ? '收起' : '展开' }}</text>
        <up-icon :name="expanded ? 'arrow-up' : 'arrow-down'" size="12"></up-icon>
      </view>
    </view>

    <!-- 设备列表 - 紧凑布局 -->
    <view class="space-y-2">
      <view
        v-for="(device, index) in displayDevices"
        :key="device.id"
        class="device-item relative"
      >
        <!-- 设备信息行 -->
        <view class="flex items-start justify-between ">
          <view class="flex-1 mr-2 ">
            <!-- 设备名称和状态 -->
            <view class="flex items-center gap-1 mb-1 ">
              <text class="text-xs font-medium text-gray-800 line-clamp-1">{{ device.model }}</text>
              <OrderStatusBadge
               class="absolute top-[-12rpx] right-0 "
                v-if="device.status_name"
                :text="device.status_name"
                :color="getDeviceStatusInfo(device.status).color"
                :bgColor="getDeviceStatusInfo(device.status).bgColor"
              />
            </view>
            <!-- 串号 -->
            <text class="text-xs text-gray-400">{{ device.user_sn || device.imei }}</text>
          </view>
          <!-- 价格 -->
          <view class="text-right mt-5">
            <text class="text-sm font-bold text-primary">¥{{ device.final_price || device.initial_price }}</text>
          </view>
        </view>

        <!-- 备注 -->
        <view v-if="device.remark" class="mt-1">
          <text class="text-xs text-gray-400">{{ device.remark }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { OrderDevice } from '../../../types/order'
import { useOrderStatus } from '../../../hooks/useOrderStatus'
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
</script>

<style scoped lang="scss">
.device-list {
  padding: 8px;
  background: #f8fafc;
  border-radius: 6px;
}

.device-item {
  padding: 8px;
  background: #fff;
  border-radius: 4px;
  border: 1px solid #e2e8f0;

  & + .device-item {
    margin-top: 6px;
  }
}

.line-clamp-1 {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
