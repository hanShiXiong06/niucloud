<template>
  <view>
    <!-- 设备列表 -->
    <view v-if="devices.length > 0" class="space-y-2 mb-3">
      <view
        v-for="(item, index) in devices"
        :key="index"
        class="flex justify-between items-center p-2 rounded"
        style="background: rgba(241, 237, 237, 0.8); border-left: 3px solid #D1C2C2;"
      >
        <view class="flex flex-col gap-1 flex-1">
          <text class="text-sm">
            <text class="font-medium mr-1" style="color: #4f46e5;">{{ index + 1 }}</text>
            用户串号: {{ item.user_sn || item.imei }}
          </text>
          <text v-if="item.model" class="text-xs text-gray-600">
            名称: {{ item.model }}
          </text>
          <text v-if="item.initial_price" class="text-xs text-gray-600">
            定价: ¥{{ item.initial_price }}
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
          :min="devices.length || 1"
          :max="99"
          :disabled="devices.length > 0"
          @change="handleCountChange"
        ></u-number-box>
        <text v-if="devices.length > 0" class="text-xs text-gray-500 mt-1">
          已自动设置为设备数量
        </text>
      </up-col>
    </up-row>

    <!-- 批量添加设备弹窗 -->
    <up-popup
      :show="showAddDialog"
      mode="center"
      :round="10"
      :closeOnClickOverlay="false"
      @close="closeAddDialog"
    >
      <view class="add-dialog">
        <view class="dialog-header">
          <view>
            <text class="dialog-title">添加回收设备</text>
            <text class="dialog-subtitle">用于用户下单前登记，门店签收时再录入完整 IMEI/SN。</text>
          </view>
        </view>

        <view class="dialog-content" :id="'dialog-content'">
          <view class="dialog-tip">
            <view class="dialog-tip__badge">提示</view>
            <text class="dialog-tip__text">设备名称和用户串号后 6 位必填。</text>
          </view>

          <!-- 设备名称输入 -->
          <view :id="'input-model'" class="form-item">
            <view class="form-label">
              <text>设备名称</text>
              <text class="required">*</text>
            </view>
            <view class="input-wrapper">
              <input
                v-model="newDevice.model"
                placeholder="如：iPhone 13 Pro"
                class="custom-input"
              />
            </view>
          </view>

          <!-- 用户串号输入（后6位） -->
          <view :id="'input-imei'" class="form-item">
            <view class="form-label">
              <text>用户串号后6位</text>
              <text class="required">*</text>
            </view>
            <view class="input-wrapper">
              <input
                v-model="newDevice.user_sn"
                placeholder="输入后6位（字母或数字）"
                maxlength="6"
                type="text"
                class="custom-input"
              />
            </view>
            <text class="form-hint">仅需输入IMEI/SN的后6位，可以是字母或数字</text>
          </view>

          <!-- 定价输入 -->
          <view :id="'input-price'" class="form-item">
            <view class="form-label">
              <text>预估价（选填）</text>
            </view>
            <view class="input-wrapper">
              <input
                v-model="newDevice.initial_price"
                placeholder="不确定可以留空"
                type="digit"
                class="custom-input"
              />
            </view>
            <text class="form-hint">这里只做下单预估，不会影响门店最终质检报价</text>
          </view>
        </view>

        <view class="dialog-footer">
          <view class="dialog-button cancel" @click="closeAddDialog">
            取消
          </view>
          <view
            :id="'confirm-btn'"
            class="dialog-button confirm"
            @click="confirmAdd"
          >
            <text>确定</text>
          </view>
        </view>
      </view>
    </up-popup>
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
const showAddDialog = ref(false)
const newDevice = ref<Device>({
  imei: '',
  user_sn: '',
  model: '',
  initial_price: ''
})

const normalizeCount = (value: any) => {
  const rawValue = typeof value === 'object' && value !== null ? value.value : value
  const count = Number(rawValue)
  return Number.isFinite(count) && count > 0 ? count : 1
}

watch(() => props.count, (newVal) => {
  localCount.value = normalizeCount(newVal)
})

// 监听设备数量变化，自动更新数量
watch(() => props.devices.length, (newLength) => {
  if (newLength > 0) {
    localCount.value = newLength
    emit('update:count', newLength)
  }
})

const handleCountChange = (value: any) => {
  const count = normalizeCount(value)
  localCount.value = count

  // 如果有设备，不允许手动修改数量
  if (props.devices.length === 0) {
    emit('update:count', count)
  }
}

const handleRemove = (index: number) => {
  const newDevices = [...props.devices]
  newDevices.splice(index, 1)
  emit('update:devices', newDevices)

  // 自动更新数量
  const newCount = newDevices.length || 1
  emit('update:count', newCount)
}

const handleBatchAdd = () => {
  showAddDialog.value = true
}

defineExpose({
  openAddDialog: handleBatchAdd
})

const closeAddDialog = () => {
  showAddDialog.value = false
  newDevice.value = {
    imei: '',
    user_sn: '',
    model: '',
    initial_price: ''
  }
}

const confirmAdd = () => {
  // 验证必填项
  if (!newDevice.value.model?.trim()) {
    uni.showToast({
      title: '请输入设备名称',
      icon: 'none'
    })
    return
  }

  if (!newDevice.value.user_sn?.trim()) {
    uni.showToast({
      title: '请输入串号后6位',
      icon: 'none'
    })
    return
  }

  if (newDevice.value.user_sn.length !== 6) {
    uni.showToast({
      title: '串号必须是6位',
      icon: 'none'
    })
    return
  }

  // if (!newDevice.value.initial_price?.trim()) {
  //   uni.showToast({
  //     title: '请输入定价',
  //     icon: 'none'
  //   })
  //   return
  // }

  // 添加设备
  const newDevices = [...props.devices, { ...newDevice.value }]
  emit('update:devices', newDevices)

  // 自动更新数量
  emit('update:count', newDevices.length)

  // 关闭弹窗
  showAddDialog.value = false
  newDevice.value = {
    imei: '',
    user_sn: '',
    model: '',
    initial_price: ''
  }

  uni.showToast({
    title: '添加成功',
    icon: 'success'
  })
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

.add-dialog {
  width: 640rpx;
  max-height: 86vh;
  background: #fff;
  border-radius: 20rpx;
  overflow: hidden;
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding: 32rpx 32rpx 24rpx;
  border-bottom: 1px solid #f0f0f0;
}

.dialog-title {
  display: block;
  font-size: 18px;
  font-weight: 600;
  color: #333;
  line-height: 1.4;
}

.dialog-subtitle {
  display: block;
  margin-top: 6rpx;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.5;
}

.dialog-content {
  padding: 32rpx;
  max-height: 58vh;
  overflow-y: auto;
}

.dialog-tip {
  display: flex;
  align-items: flex-start;
  gap: 12rpx;
  padding: 18rpx 20rpx;
  margin-bottom: 28rpx;
  background: #f8fafc;
  border-radius: 12rpx;
}

.dialog-tip__badge {
  flex-shrink: 0;
  padding: 4rpx 10rpx;
  background: rgba(79, 70, 229, 0.1);
  color: var(--primary-color);
  border-radius: 999rpx;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
}

.dialog-tip__text {
  flex: 1;
  min-width: 0;
  font-size: 12px;
  color: #4b5563;
  line-height: 1.5;
}

.form-item {
  margin-bottom: 24rpx;
}

.form-label {
  display: flex;
  align-items: center;
  margin-bottom: 12rpx;
  font-size: 14px;
  color: #333;
}

.required {
  color: #ef4444;
  margin-left: 4rpx;
}

.form-hint {
  display: block;
  margin-top: 8rpx;
  font-size: 12px;
  color: #999;
}

.input-wrapper {
  width: 100%;
}

.custom-input {
  width: 100%;
  height: 80rpx;
  padding: 0 24rpx;
  border: 1px solid #e5e5e5;
  border-radius: 8rpx;
  font-size: 14px;
  background: #fff;
  box-sizing: border-box;
}

.custom-input:focus {
  border-color: var(--primary-color);
  outline: none;
}

.dialog-footer {
  display: flex;
  border-top: 1px solid #f0f0f0;
}

.dialog-button {
  flex: 1;
  height: 100rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.dialog-button.cancel {
  color: #666;
  border-right: 1px solid #f0f0f0;
}

.dialog-button.confirm {
  color: var(--primary-color);
  font-weight: 600;
}
</style>
