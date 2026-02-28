<template>
  <uni-popup ref="popupRef" type="center" background-color="#fff" :mask-click="true">
    <view class="imei-modal">
      <view class="modal-header">
        <text class="modal-title">{{ mode === 'single' ? '添加设备' : '批量添加设备' }}</text>
        <view class="close-btn" @click="handleClose">
          <up-icon name="close" size="16" color="#64748b"></up-icon>
        </view>
      </view>

      <view class="modal-body">
        <view class="form-group">
          <!-- 单台模式 -->
          <view v-if="mode === 'single'" class="input-row">
            <view class="input-group">
              <view class="input-wrapper flex-1">
                <u-input
                  v-model="singleInput.imei"
                  size="14"
                  placeholder="输入IMEI/SN码"
                  border="surround"
                  clearable
                ></u-input>
                <view class="scan-btn" @click="handleSingleScan">
                  <up-icon name="scan" size="16" color="#3b82f6"></up-icon>
                </view>
              </view>
              <view v-if="enablePricing" class="input-wrapper">
                <u-input
                  v-model="singleInput.initial_price"
                  type="number"
                  size="14"
                  placeholder="输入定价"
                  border="surround"
                  clearable
                ></u-input>
              </view>
            </view>
          </view>

          <!-- 批量模式 -->
          <view v-else class="input-row">
            <view v-for="(input, index) in inputList" :key="index" class="input-group">
              <view class="input-wrapper flex-1">
                <u-input
                  v-model="input.imei"
                  size="14"
                  placeholder="输入IMEI/SN码"
                  border="surround"
                  clearable
                ></u-input>
                <view class="scan-btn" @click="() => handleScan(index)">
                  <up-icon name="scan" size="16" color="#3b82f6"></up-icon>
                </view>
              </view>
              <view v-if="enablePricing" class="input-wrapper">
                <u-input
                  v-model="input.initial_price"
                  type="number"
                  size="14"
                  placeholder="输入定价"
                  border="surround"
                  clearable
                ></u-input>
              </view>
              <view v-if="index > 0" class="remove-btn" @click="() => removeInput(index)">
                <up-icon name="trash" size="16" color="#ef4444"></up-icon>
              </view>
            </view>
          </view>

          <!-- 批量模式：添加输入框按钮 -->
          <view v-if="mode === 'batch'" class="add-input-btn" @click="addInput">
            <up-icon name="plus" size="16" color="#3b82f6"></up-icon>
            <up-text size="14" text="添加输入框"></up-text>
          </view>

          <view class="tip-text">
            <up-icon name="info-circle" size="14" color="#64748b"></up-icon>
            <text>如果输入 SN 过长，可以直接只输入后 6 位</text>
          </view>
          <view class="tip-text">
            <up-icon name="info-circle" size="12" color="#64748b"></up-icon>
            <text>苹果设备在拨号界面输入*#06#可快速获取 SN 条形码</text>
          </view>
        </view>

        <!-- 批量模式：已添加的设备列表 -->
        <view v-if="mode === 'batch' && tempDeviceList.length > 0" class="device-list">
          <view class="list-header">
            <text>已添加设备</text>
            <text class="count">({{ tempDeviceList.length }})</text>
          </view>
          <view v-for="(item, index) in tempDeviceList" :key="index" class="device-item">
            <view class="device-info">
              <text class="imei">IMEI: {{ item.imei }}</text>
              <text v-if="item.initial_price" class="price">¥{{ item.initial_price }}</text>
            </view>
            <view class="delete-btn" @click="removeDevice(index)">
              <up-icon name="trash" size="16" color="#ef4444"></up-icon>
            </view>
          </view>
        </view>
      </view>

      <view class="modal-footer">
        <view class="left">
          <u-switch size="18" v-model="localEnablePricing" activeColor="#3b82f6"></u-switch>
          <text>启用定价</text>
        </view>
        <view class="right">
          <view class="btn cancel" @click="handleClose">取消</view>
          <view class="btn confirm" @click="handleConfirm">确定</view>
        </view>
      </view>
    </view>
  </uni-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Device } from '../../../types/order'

interface Props {
  visible: boolean
  mode?: 'single' | 'batch'
  enablePricing?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  mode: 'batch',
  enablePricing: false
})

const emit = defineEmits<{
  'update:visible': [value: boolean]
  confirm: [devices: Device[]]
}>()

const popupRef = ref(null)
const localEnablePricing = ref(props.enablePricing)

// 单台模式输入
const singleInput = ref<{ imei: string; initial_price: string }>({
  imei: '',
  initial_price: ''
})

// 批量模式输入列表
const inputList = ref<Array<{ imei: string; initial_price: string }>>([
  { imei: '', initial_price: '' }
])

// 批量模式临时设备列表
const tempDeviceList = ref<Device[]>([])

watch(() => props.visible, (newVal) => {
  if (newVal) {
    (popupRef.value as any)?.open()
    // 重置数据
    singleInput.value = { imei: '', initial_price: '' }
    inputList.value = [{ imei: '', initial_price: '' }]
    tempDeviceList.value = []
  } else {
    (popupRef.value as any)?.close()
  }
})

watch(() => props.enablePricing, (newVal) => {
  localEnablePricing.value = newVal
})

// 添加输入框（批量模式）
const addInput = () => {
  inputList.value.push({ imei: '', initial_price: '' })
}

// 移除输入框（批量模式）
const removeInput = (index: number) => {
  inputList.value.splice(index, 1)
}

// 处理扫码（单台模式）
const handleSingleScan = () => {
  uni.scanCode({
    onlyFromCamera: true,
    success: (res) => {
      singleInput.value.imei = res.result
    },
    fail: () => {
      uni.showToast({
        title: '扫码失败',
        icon: 'none'
      })
    }
  })
}

// 处理扫码（批量模式）
const handleScan = (index: number) => {
  uni.scanCode({
    onlyFromCamera: true,
    success: (res) => {
      inputList.value[index].imei = res.result
    },
    fail: () => {
      uni.showToast({
        title: '扫码失败',
        icon: 'none'
      })
    }
  })
}

// 添加设备到临时列表（批量模式）
const addDevices = () => {
  const validInputs = inputList.value.filter(input => input.imei.trim())

  if (validInputs.length === 0) {
    uni.showToast({
      title: '请输入IMEI码',
      icon: 'none'
    })
    return
  }

  for (const input of validInputs) {
    // 检查是否重复
    if (tempDeviceList.value.some(item => item.imei === input.imei)) {
      uni.showToast({
        title: `IMEI码 ${input.imei} 已存在`,
        icon: 'none'
      })
      continue
    }

    const device: Device = {
      imei: input.imei,
      initial_price: localEnablePricing.value ? input.initial_price : undefined
    }

    tempDeviceList.value.push(device)
  }

  // 清空输入
  inputList.value = [{ imei: '', initial_price: '' }]
}

// 从临时列表删除设备（批量模式）
const removeDevice = (index: number) => {
  tempDeviceList.value.splice(index, 1)
}

// 确认添加
const handleConfirm = () => {
  if (props.mode === 'single') {
    // 单台模式
    if (!singleInput.value.imei.trim()) {
      uni.showToast({
        title: '请输入IMEI码',
        icon: 'none'
      })
      return
    }

    const device: Device = {
      imei: singleInput.value.imei,
      initial_price: localEnablePricing.value ? singleInput.value.initial_price : undefined
    }

    emit('confirm', [device])
  } else {
    // 批量模式：先添加当前输入框中的设备
    addDevices()

    if (tempDeviceList.value.length === 0) {
      uni.showToast({
        title: '请添加至少一个设备',
        icon: 'none'
      })
      return
    }

    emit('confirm', tempDeviceList.value)
  }

  handleClose()
}

// 关闭弹窗
const handleClose = () => {
  emit('update:visible', false)
}
</script>

<style scoped lang="scss">
.imei-modal {
  width: 320px;
  background: #fff;
  border-radius: 8px;
  overflow: hidden;

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 16px 12px;
    border-bottom: 1px solid #e5e7eb;

    .modal-title {
      font-size: 16px;
      font-weight: 500;
      color: #1f2937;
    }

    .close-btn {
      padding: 4px;
      border-radius: 4px;
      cursor: pointer;

      &:active {
        background: #f1f5f9;
      }
    }
  }

  .modal-body {
    padding: 16px;
    max-height: 60vh;
    overflow-y: auto;

    .form-group {
      margin-bottom: 16px;

      .input-row {
        display: flex;
        flex-direction: column;
        gap: 12px;

        .input-group {
          display: flex;
          gap: 8px;
          align-items: flex-start;

          .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            flex: 1;

            .scan-btn {
              position: absolute;
              right: 8px;
              padding: 4px;
              border-radius: 4px;
              cursor: pointer;

              &:active {
                background: #f1f5f9;
              }
            }
          }

          .input-wrapper:nth-child(1) {
            flex: 2;
          }

          .remove-btn {
            margin-top: 8px;
            padding: 4px;
            border-radius: 4px;
            cursor: pointer;

            &:active {
              background: #fee2e2;
            }
          }
        }
      }

      .add-input-btn {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 8px;
        padding: 6px 12px;
        border-radius: 6px;
        background: #f1f5f9;
        cursor: pointer;

        text {
          font-size: 14px;
          color: #3b82f6;
        }

        &:active {
          background: #e2e8f0;
        }
      }

      .tip-text {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 8px;

        text {
          font-size: 12px;
          color: #64748b;
        }
      }
    }

    .device-list {
      border-top: 1px solid #e5e7eb;
      padding-top: 16px;

      .list-header {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 8px;
        font-size: 14px;
        color: #1f2937;

        .count {
          color: #64748b;
        }
      }

      .device-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        background: #f8fafc;
        border-radius: 4px;
        margin-bottom: 8px;

        .device-info {
          display: flex;
          flex-direction: column;
          gap: 2px;

          .imei {
            font-size: 14px;
            color: #1f2937;
          }

          .price {
            font-size: 12px;
            color: #3b82f6;
          }
        }

        .delete-btn {
          padding: 4px;
          border-radius: 4px;
          cursor: pointer;

          &:active {
            background: #fee2e2;
          }
        }
      }
    }
  }

  .modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-top: 1px solid #e5e7eb;

    .left {
      display: flex;
      align-items: center;
      gap: 8px;

      text {
        font-size: 14px;
        color: #64748b;
      }
    }

    .right {
      display: flex;
      gap: 8px;

      .btn {
        padding: 6px 16px;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;

        &.cancel {
          color: #64748b;
          background: #f1f5f9;

          &:active {
            background: #e2e8f0;
          }
        }

        &.confirm {
          color: #fff;
          background: #3b82f6;

          &:active {
            background: #2563eb;
          }
        }
      }
    }
  }
}
</style>
