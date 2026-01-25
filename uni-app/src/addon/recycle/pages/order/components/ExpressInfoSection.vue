<template>
  <view class="bg-white rounded-lg p-3 mt-3 border-l-4" style="border-color: #B4C7D6;">
    <view class="flex items-center gap-1 mb-2">
      <up-icon name="car" size="16" color="#5F758A"></up-icon>
      <text class="text-sm font-medium" style="color: #5F758A;">寄件信息</text>
    </view>

    <!-- 快递方式选择 -->
    <up-row customStyle="margin-bottom: 12px">
      <up-col span="12">
        <view class="delivery-mode-toggle">
          <view
            :class="['toggle-item', usePlatformDelivery ? 'active' : '']"
            @click="handlePlatformToggle"
          >
            <view class="flex items-center justify-center gap-1">
              <text>顺丰快递</text>
              <view class="free-tag">
                <text class="free-tag-text">限时包邮</text>
              </view>
            </view>
          </view>
          <view
          class="flex items-center justify-center gap-1"
            :class="['toggle-item', !usePlatformDelivery ? 'active' : '']"
            @click="handleManualToggle"
          >
            <text>快递单号</text>
            <view class="free-tag">
                <text class="free-tag-text">手动输入</text>
              </view>
          </view>
        </view>
      </up-col>
    </up-row>

    <!-- 手动输入快递单号 -->
    <up-row v-if="!usePlatformDelivery" customStyle="margin-bottom: 8px">
      <up-col span="3">
        <view class="label">快递单号</view>
      </up-col>
      <up-col span="9">
        <view class="input-wrapper">
          <u-form-item prop="express_no">
            <u-input
              placeholder="输入或扫描快递单号"
              border="surround"
              clearable
              :modelValue="expressNo"
              @update:modelValue="handleExpressNoChange"
            >
              <template #suffix>
                <up-icon @click="$emit('scan-express')" name="scan" size="24"></up-icon>
              </template>
            </u-input>
          </u-form-item>
        </view>
      </up-col>
    </up-row>

    <!-- 平台快递下单 -->
    <view v-if="usePlatformDelivery" class="platform-delivery-section">
      <!-- 已选择的地址信息展示（可点击版） -->
      <view
        v-if="platformDeliveryForm.sender_name"
        class="address-card-clickable"
        @click="$emit('select-address')"
      >
        <view class="address-compact">
          <view class="flex items-center justify-between">
            <view class="flex items-center gap-2">
              <view class="user-avatar-small">
                <up-icon name="account-fill" size="14" color="#fff"></up-icon>
              </view>
              <view class="user-info-compact">
                <text class="user-name-compact">{{ platformDeliveryForm.sender_name }}</text>
                <text class="user-phone-compact">{{ platformDeliveryForm.sender_mobile }}</text>
              </view>
            </view>
            <view class="edit-btn-small">
              <up-icon name="edit-pen" size="12" color="#3b82f6"></up-icon>
            </view>
          </view>
          <view class="address-text-compact">
            <up-icon name="map-fill" size="12" color="#94a3b8"></up-icon>
            <text>{{ platformDeliveryForm.area_text }} {{ platformDeliveryForm.detail_address }}</text>
          </view>
        </view>
      </view>

      <!-- 未选择地址 - 显示选择按钮 -->
      <view v-else class="select-address-row" @click="$emit('select-address')">
        <view class="flex items-center gap-2">
          <up-icon name="map" size="16" color="#3b82f6"></up-icon>
          <text class="text-sm" style="color: #64748b;">请选择寄件地址</text>
        </view>
        <up-icon name="arrow-right" size="14" color="#94a3b8"></up-icon>
      </view>

      <!-- 预约时间 -->
      <up-row customStyle="margin-bottom: 8px">
        <up-col span="3">
          <view class="label">预约时间</view>
        </up-col>
        <up-col span="9">
          <u-input
            placeholder="请选择预约时间"
            border="surround"
            readonly
            :modelValue="platformDeliveryForm.pickup_time"
            @click="showPickupTimePicker = true"
          >
            <template #suffix>
              <up-icon name="arrow-down" size="16" color="#94a3b8"></up-icon>
            </template>
          </u-input>
        </up-col>
      </up-row>
    </view>
  </view>

  <!-- 预约时间选择器 -->
  <u-picker
    :show="showPickupTimePicker"
    :columns="[pickupTimeOptions]"
    keyName="label"
    @confirm="handlePickupTimeConfirm"
    @cancel="showPickupTimePicker = false"
  ></u-picker>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { PlatformDeliveryForm } from '../../../types/order'

interface Props {
  usePlatformDelivery: boolean
  expressNo: string
  platformDeliveryForm: PlatformDeliveryForm
  pickupTimeOptions: Array<{ label: string; value: string }>
}

const props = defineProps<Props>()

// 预约时间选择器显示状态
const showPickupTimePicker = ref(false)

const emit = defineEmits<{
  'update:usePlatformDelivery': [value: boolean]
  'update:expressNo': [value: string]
  'update:platformDeliveryForm': [form: PlatformDeliveryForm]
  'select-address': []
  'scan-express': []
}>()

const handlePlatformToggle = () => {
  emit('update:usePlatformDelivery', true)
}

const handleManualToggle = () => {
  emit('update:usePlatformDelivery', false)
}

const handleExpressNoChange = (value: string) => {
  emit('update:expressNo', value)
}

const handlePickupTimeConfirm = (e: any) => {
  const selectedOption = e.value[0]
  emit('update:platformDeliveryForm', {
    ...props.platformDeliveryForm,
    pickup_time: selectedOption.value
  })
  showPickupTimePicker.value = false
}
</script>

<style scoped lang="scss">
.label {
  font-size: 14px;
  color: #374151;
}

.input-wrapper {
  width: 100%;
  overflow: hidden;
}

.delivery-mode-toggle {
  display: flex;
  background: #f1f5f9;
  border-radius: 8px;
  padding: 4px;
  gap: 4px;

  .toggle-item {
    flex: 1;
    text-align: center;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s;

    &.active {
      background: #3b82f6;
      color: #fff;
      box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
    }

    &:active {
      transform: scale(0.98);
    }
  }
}

.free-tag {
  display: inline-flex;
  align-items: center;
  padding: 2px 6px;
  background: linear-gradient(135deg, #fab505, #f49d06);
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);

  .free-tag-text {
    font-size: 10px;
    font-weight: 600;
    color: #fff;
    line-height: 1;
  }
}

.toggle-item.active .free-tag {
  background: linear-gradient(135deg, #fef3c7, #fde68a);

  .free-tag-text {
    color: #92400e;
  }
}

.platform-delivery-section {
  .select-address-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    margin-bottom: 12px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;

    &:active {
      background: #f1f5f9;
      border-color: #3b82f6;
    }
  }

  .address-card-clickable {
    background: #fff;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s;

    &:active {
      background: #f8fafc;
      border-color: #3b82f6;
    }

    .address-compact {
      display: flex;
      flex-direction: column;
      gap: 8px;

      .user-avatar-small {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }

      .user-info-compact {
        display: flex;
        align-items: center;
        gap: 8px;

        .user-name-compact {
          font-size: 14px;
          font-weight: 500;
          color: #1e293b;
        }

        .user-phone-compact {
          font-size: 13px;
          color: #64748b;
        }
      }

      .edit-btn-small {
        padding: 4px;
        background: #f1f5f9;
        border-radius: 4px;

        &:active {
          background: #e2e8f0;
        }
      }

      .address-text-compact {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        padding-left: 36px;

        text {
          font-size: 13px;
          color: #64748b;
          line-height: 1.5;
        }
      }
    }
  }
}
</style>
