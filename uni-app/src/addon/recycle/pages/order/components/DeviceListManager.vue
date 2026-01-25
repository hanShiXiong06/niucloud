<template>
  <view>
    <!-- 设备列表标题和操作按钮 -->
    <view class="flex justify-between items-center mb-2">
      <text class="text-sm font-medium">设备列表</text>
      <view class="flex gap-2">
        <!-- 添加单台设备按钮 -->
        <view
          v-if="showAddButton"
          class="flex items-center gap-1 px-2 py-1 rounded text-xs"
          style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;"
          @click="$emit('add-single-device')"
        >
          <up-icon name="scan" size="14" color="#3b82f6"></up-icon>
          <text>添加设备</text>
        </view>

        <!-- 批量添加按钮 -->
        <view
          v-if="showBatchButton"
          class="flex items-center gap-1 px-2 py-1 rounded text-xs"
          style="background: var(--primary-color); color: #fff;"
          @click="$emit('add-device')"
        >
          <up-icon name="plus" size="14" color="#fff"></up-icon>
          <text>批量添加</text>
        </view>
      </view>
    </view>

    <!-- 设备列表 -->
    <view v-if="devices.length > 0" class="space-y-2 mb-3">
      <view
        v-for="(item, index) in devices"
        :key="index"
        class="flex justify-between items-center p-2 rounded"
        style="background: rgba(241, 237, 237, 0.8); border-left: 3px solid #D1C2C2;"
      >
        <view class="flex flex-col gap-1">
          <text class="text-sm">
            <text class="font-medium mr-1" style="color: #4f46e5;">{{ index + 1 }}</text>
            IMEI: {{ item.imei }}
          </text>
          <text v-if="item.initial_price" class="text-xs text-gray-600">
            定价: {{ item.initial_price }}
          </text>
        </view>
        <view
          v-if="showDeleteButton"
          class="p-1"
          @click="handleRemove(index)"
        >
          <up-icon name="trash" size="14" color="#ef4444"></up-icon>
        </view>
      </view>
    </view>

    <!-- 数量输入 -->
    <up-row customStyle="margin-bottom: 8px">
      <up-col span="3">
        <view class="label">数量</view>
      </up-col>
      <up-col span="9">
        <u-number-box
          v-model="localCount"
          :min="1"
          :max="99"
          @change="handleCountChange"
        ></u-number-box>
      </up-col>
    </up-row>
  </view>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Device } from '../../../types/order'

interface Props {
  devices: Device[]
  count: number
  showAddButton?: boolean
  showBatchButton?: boolean
  showDeleteButton?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showAddButton: true,
  showBatchButton: true,
  showDeleteButton: true
})

const emit = defineEmits<{
  'update:devices': [devices: Device[]]
  'update:count': [count: number]
  'add-single-device': []
  'add-device': []
}>()

const localCount = ref(props.count)

watch(() => props.count, (newVal) => {
  localCount.value = newVal
})

const handleCountChange = (value: number) => {
  emit('update:count', value)
}

const handleRemove = (index: number) => {
  const newDevices = [...props.devices]
  newDevices.splice(index, 1)
  emit('update:devices', newDevices)
  emit('update:count', newDevices.length)
}
</script>

<style scoped lang="scss">
.label {
  font-size: 14px;
  color: #374151;
}

.space-y-2 > view:not(:last-child) {
  margin-bottom: 0.5rem;
}
</style>
