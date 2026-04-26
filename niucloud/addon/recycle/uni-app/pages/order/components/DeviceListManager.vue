<template>
  <view>
    <!-- 设备列表标题和操作按钮 -->
    <view class="flex justify-between items-center mb-2">
      <text class="text-sm font-medium">设备列表</text>
      <view class="flex gap-2">
        <!-- 批量添加按钮 -->
        <view
          v-if="showBatchButton"
          :id="'batch-add-btn'"
          class="batch-add-button"
          @click="handleBatchAdd"
        >
          <view class="flex items-center gap-1 px-2 py-1 rounded text-xs">
            <up-icon name="plus" size="14" color="#fff"></up-icon>
            <text>添加设备</text>
          </view>
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
        <view class="flex flex-col gap-1 flex-1">
          <text class="text-sm">
            <text class="font-medium mr-1" style="color: #4f46e5;">{{ index + 1 }}</text>
            串号: {{ item.imei }}
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
          <text class="dialog-title">添加设备信息</text>
        </view>

        <view class="dialog-content" :id="'dialog-content'">
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

          <!-- 串号输入（后6位） -->
          <view :id="'input-imei'" class="form-item">
            <view class="form-label">
              <text>串号后6位</text>
              <text class="required">*</text>
            </view>
            <view class="input-wrapper">
              <input
                v-model="newDevice.imei"
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
              <text>定价</text>
            </view>
            <view class="input-wrapper">
              <input
                v-model="newDevice.initial_price"
                placeholder="请输入定价"
                type="digit"
                class="custom-input"
              />
            </view>
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

    <!-- 新手引导遮罩 -->
    <view v-if="showGuide" class="guide-overlay" @click.stop>
      <!-- 镂空高亮区域 -->
      <view class="guide-spotlight" :style="spotlightStyle"></view>

      <!-- 引导提示 -->
      <view class="guide-tooltip" :style="tooltipStyle">
        <view class="guide-header">
          <view class="guide-step-badge">步骤 {{ guideStep }}/3</view>
          <text class="guide-skip-btn" @click="skipGuide">跳过</text>
        </view>
        <view class="guide-title">{{ currentGuide.title }}</view>
        <view class="guide-desc">{{ currentGuide.desc }}</view>

        <!-- 操作按钮 -->
        <view class="guide-actions">
          <view v-if="guideStep > 1" class="guide-btn guide-btn-secondary" @click="prevStep">
            上一步
          </view>
          <view class="guide-btn guide-btn-primary" @click="nextStep">
            {{ guideStep < 3 ? '下一步' : '完成' }}
          </view>
        </view>

        <view v-if="guideStep > 1" class="guide-arrow" :class="currentGuide.arrowClass"></view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed, nextTick } from 'vue'
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
  model: '',
  initial_price: ''
})

// 引导相关
const showGuide = ref(false)
const guideStep = ref(1)
const spotlightRect = ref({ top: 0, left: 0, width: 0, height: 0 })
const GUIDE_STORAGE_KEY = 'recycle_device_guide_shown_v2'

// 引导配置
const guides = [
  {
    step: 1,
    title: '第一步：点击添加设备',
    desc: '点击右上角的"添加设备"按钮，开始添加设备信息',
    targetId: 'batch-add-btn',
    arrowClass: 'arrow-top-right'
  },
  {
    step: 2,
    title: '第二步：填写设备信息',
    desc: '依次填写设备名称、串号后6位和定价，所有字段都是必填项',
    targetId: 'dialog-content',
    arrowClass: 'arrow-top'
  },
  {
    step: 3,
    title: '第三步：确认添加',
    desc: '检查信息无误后，点击"确定"按钮完成添加',
    targetId: 'confirm-btn',
    arrowClass: 'arrow-bottom'
  }
]

const currentGuide = computed(() => {
  return guides.find(g => g.step === guideStep.value) || guides[0]
})

// 镂空高亮样式
const spotlightStyle = computed(() => {
  const rect = spotlightRect.value
  return {
    top: `${rect.top - 8}px`,
    left: `${rect.left - 8}px`,
    width: `${rect.width + 16}px`,
    height: `${rect.height + 16}px`,
    borderRadius: guideStep.value === 1 ? '8px' : '12px'
  }
})

// 提示框位置
const tooltipStyle = computed(() => {
  const rect = spotlightRect.value
  const step = guideStep.value

  if (step === 1) {
    return {
      top: `${rect.top + rect.height + 20}px`,
      right: '20px'
    }
  } else if (step >= 2 && step <= 4) {
    return {
      top: `${rect.top + rect.height + 20}px`,
      left: '50%',
      transform: 'translateX(-50%)'
    }
  } else {
    return {
      bottom: `${window.innerHeight - rect.top + 20}px`,
      left: '50%',
      transform: 'translateX(-50%)'
    }
  }
})

watch(() => props.count, (newVal) => {
  localCount.value = newVal
})

// 监听设备数量变化，自动更新数量
watch(() => props.devices.length, (newLength) => {
  if (newLength > 0) {
    localCount.value = newLength
    emit('update:count', newLength)
  }
})

onMounted(() => {
  const hasShownGuide = uni.getStorageSync(GUIDE_STORAGE_KEY)
  if (!hasShownGuide) {
    showGuide.value = true
    nextTick(() => {
      updateSpotlight()
    })
  }
})

const updateSpotlight = () => {
  const targetId = currentGuide.value.targetId

  // 使用 uni.createSelectorQuery 获取元素位置
  const query = uni.createSelectorQuery()
  query.select(`#${targetId}`).boundingClientRect((data: any) => {
    if (data) {
      spotlightRect.value = {
        top: data.top,
        left: data.left,
        width: data.width,
        height: data.height
      }
    }
  }).exec()
}

const handleCountChange = (value: number) => {
  // 如果有设备，不允许手动修改数量
  if (props.devices.length === 0) {
    emit('update:count', value)
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

  if (showGuide.value && guideStep.value === 1) {
    setTimeout(() => {
      guideStep.value = 2
      updateSpotlight()
    }, 300)
  }
}

const closeAddDialog = () => {
  if (showGuide.value && guideStep.value > 1) {
    uni.showToast({
      title: '请完成引导步骤',
      icon: 'none'
    })
    return
  }

  showAddDialog.value = false
  newDevice.value = {
    imei: '',
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

  if (!newDevice.value.imei?.trim()) {
    uni.showToast({
      title: '请输入串号后6位',
      icon: 'none'
    })
    return
  }

  if (newDevice.value.imei.length !== 6) {
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
    model: '',
    initial_price: ''
  }

  // 完成引导
  if (showGuide.value) {
    skipGuide()
  }

  uni.showToast({
    title: '添加成功',
    icon: 'success'
  })
}

const skipGuide = () => {
  showGuide.value = false
  guideStep.value = 1
  uni.setStorageSync(GUIDE_STORAGE_KEY, true)

  if (showAddDialog.value) {
    showAddDialog.value = false
    newDevice.value = {
      imei: '',
      model: '',
      initial_price: ''
    }
  }
}

const nextStep = () => {
  if (guideStep.value === 1) {
    // 第一步：打开弹窗
    if (!showAddDialog.value) {
      handleBatchAdd()
    } else {
      guideStep.value = 2
      nextTick(() => {
        updateSpotlight()
      })
    }
  } else if (guideStep.value < 3) {
    // 第2步：直接进入第3步
    guideStep.value++
    nextTick(() => {
      updateSpotlight()
    })
  } else {
    // 第3步：完成引导
    skipGuide()
  }
}

const prevStep = () => {
  if (guideStep.value > 1) {
    guideStep.value--
    nextTick(() => {
      updateSpotlight()
    })
  }
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

.batch-add-button {
  background: var(--primary-color);
  color: #fff;
  border-radius: 4px;
}

.add-dialog {
  width: 600rpx;
  background: #fff;
  border-radius: 10px;
  overflow: hidden;
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32rpx;
  border-bottom: 1px solid #f0f0f0;
}

.dialog-title {
  font-size: 18px;
  font-weight: 600;
  color: #333;
}

.dialog-content {
  padding: 32rpx;
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

// 引导样式
.guide-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  z-index: 9998;
}

.guide-spotlight {
  position: fixed;
  background: #fff;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.75);
  z-index: 9999;
  transition: all 0.3s ease;
  pointer-events: none;
}

.guide-tooltip {
  position: fixed;
  background: #fff;
  border-radius: 12rpx;
  padding: 32rpx;
  width: 560rpx;
  box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.3);
  z-index: 10000;
}

.guide-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16rpx;
}

.guide-step-badge {
  background: var(--primary-color);
  color: #fff;
  padding: 6rpx 16rpx;
  border-radius: 20rpx;
  font-size: 12px;
  font-weight: 500;
}

.guide-skip-btn {
  color: #999;
  font-size: 14px;
}

.guide-title {
  font-size: 18px;
  font-weight: 600;
  color: #333;
  margin-bottom: 12rpx;
}

.guide-desc {
  font-size: 14px;
  color: #666;
  line-height: 1.6;
  margin-bottom: 24rpx;
}

.guide-actions {
  display: flex;
  gap: 16rpx;
  justify-content: flex-end;
}

.guide-btn {
  padding: 16rpx 32rpx;
  border-radius: 8rpx;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.guide-btn-secondary {
  background: #f5f5f5;
  color: #666;
}

.guide-btn-primary {
  background: var(--primary-color);
  color: #fff;
}

.guide-arrow {
  position: absolute;
  width: 0;
  height: 0;
  border: 12rpx solid transparent;
}

.arrow-top {
  top: -24rpx;
  left: 50%;
  transform: translateX(-50%);
  border-bottom-color: #fff;
}

.arrow-top-right {
  top: -24rpx;
  right: 40rpx;
  border-bottom-color: #fff;
}

.arrow-bottom {
  bottom: -24rpx;
  left: 50%;
  transform: translateX(-50%);
  border-top-color: #fff;
}
</style>
