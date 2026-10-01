<template>
  <view class="express-info-section">
    <view class="express-section-title">
      <text>寄件信息</text><text v-if="!pickupAvailable && !checkingPickup" class="express-mode-label">自行寄件</text>
    </view>

    <!-- 快递方式选择 -->
        <view v-if="pickupAvailable || checkingPickup || usePlatformDelivery" class="delivery-mode-toggle">
          <!-- 客户只选择交付方式，承运商与寄件产品由门店固定。 -->
          <view
            :class="['toggle-item', usePlatformDelivery ? 'active' : '', !canUsePlatformDelivery ? 'disabled' : '']"
            @click="handlePickupToggle"
          >
            <view class="mode-check"><up-icon name="checkmark-circle-fill" size="12" color="var(--recycle-brand)" /></view>
            <view class="toggle-item-copy">
              <view class="toggle-item-heading">
                <DeliveryIcon :type="3" />
                <text>上门取件</text>
              </view>
              <text class="toggle-item-description">预约快递员上门</text>
            </view>
          </view>

          <!-- 固定的快递单号选项（始终显示） -->
          <view
            :class="['toggle-item', !usePlatformDelivery ? 'active' : '']"
            @click="handleManualToggle"
          >
            <view class="mode-check"><up-icon name="checkmark-circle-fill" size="12" color="var(--recycle-brand)" /></view>
            <view class="toggle-item-copy">
              <view class="toggle-item-heading">
                <DeliveryIcon :type="1" />
                <text>自行寄件</text>
              </view>
              <text class="toggle-item-description">填写或扫描单号</text>
            </view>
          </view>
        </view>

    <!-- 手动输入快递单号 -->
    <view v-if="!usePlatformDelivery" class="express-number-row">
      <text class="express-number-label">快递单号</text>
      <view class="input-wrapper">
          <u-form-item prop="express_no">
            <u-input
              placeholder="输入或扫描快递单号"
              border="none"
              color="var(--recycle-text-main)"
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
    </view>

    <view v-if="!canUsePlatformDelivery" class="platform-threshold-tip">
      <up-icon name="info-circle" size="14" color="var(--recycle-notice-text)"></up-icon>
      <text>{{ unavailableText }}</text>
    </view>

    <!-- 平台快递下单 -->
    <view v-if="usePlatformDelivery" class="platform-delivery-section">
      <view class="pickup-carrier"><text>门店安排承运商</text><text>{{ carrierName || '待门店确认' }}</text></view>
      <view class="pickup-payment-tip"><text>运费说明：{{ paymentTips || '运费及付款安排请与门店确认，预约服务不代表免费寄件。' }}</text></view>
      <!-- 已选择的地址信息展示（可点击版） -->
      <view
        v-if="platformDeliveryForm.sender_name"
        class="address-card-clickable"
        @click="showAddressPopup = true"
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
              <up-icon name="edit-pen" size="12" color="var(--recycle-brand)"></up-icon>
            </view>
          </view>
          <view class="address-text-compact">
            <up-icon name="map-fill" size="12" color="var(--recycle-text-sub)"></up-icon>
            <text>{{ platformDeliveryForm.area_text }} {{ platformDeliveryForm.detail_address }}</text>
          </view>
        </view>
      </view>

      <!-- 未选择地址 - 显示选择按钮 -->
      <view v-else class="select-address-row" @click="showAddressPopup = true">
        <view class="flex items-center gap-2">
          <up-icon name="map" size="16" color="var(--recycle-brand)"></up-icon>
          <text class="text-sm" style="color: var(--recycle-text-sub);">请选择寄件地址</text>
        </view>
        <up-icon name="arrow-right" size="14" color="#94a3b8"></up-icon>
      </view>

      <view v-if="pickupTimeSupported" class="pickup-time-fields">
        <text class="label">期望取件时段{{ needPickupTime ? '（必选）' : '（选填）' }}</text>
        <picker mode="date" :value="pickupDate" :start="today" @change="changePickupDate">
          <view class="pickup-time-value">{{ pickupDate || '选择取件日期' }}<up-icon name="arrow-right" size="12" /></view>
        </picker>
        <view class="pickup-time-range">
          <picker mode="time" :value="pickupStart" @change="changePickupStart"><view class="pickup-time-value">{{ pickupStart || '开始时间' }}</view></picker>
          <text>至</text>
          <picker mode="time" :value="pickupEnd" @change="changePickupEnd"><view class="pickup-time-value">{{ pickupEnd || '结束时间' }}</view></picker>
        </view>
        <text v-if="!needPickupTime && platformDeliveryForm.pickup_time" class="pickup-clear" @tap="clearPickupTime">清除时段，由快递员联系确认</text>
        <text class="pickup-time-note">这是您的期望时段，是否可约及实际上门安排以预约结果和快递员确认为准。</text>
      </view>
      <text v-else class="pickup-time-note">当前取件服务不支持自选时段，具体时间由门店与快递员确认。</text>
    </view>
  </view>



  <!-- 地址选择弹窗 -->
  <AddressSelectPopup
    :show="showAddressPopup"
    @update:show="showAddressPopup = $event"
    @select="handleAddressSelect"
  />
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { PlatformDeliveryForm } from '../../../types/order'
import AddressSelectPopup from './AddressSelectPopup.vue'
import DeliveryIcon from './DeliveryIcon.vue'

interface Props {
  usePlatformDelivery: boolean
  expressNo: string
  platformDeliveryForm: PlatformDeliveryForm
  needPickupTime: boolean
  pickupTimeSupported: boolean
  pickupAvailable: boolean
  checkingPickup: boolean
  pickupUnavailableReason: string
  carrierName: string
  paymentTips: string
  orderCount?: number
  freeShippingMinCount?: number
  platformDeliveryName?: string
}

const props = withDefaults(defineProps<Props>(), {
  orderCount: 1,
  freeShippingMinCount: 1,
  platformDeliveryName: '门店快递'
})

// 地址选择弹窗显示状态
const showAddressPopup = ref(false)

const pickupDate = ref('')
const pickupStart = ref('')
const pickupEnd = ref('')
const today = (() => {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
})()

const emit = defineEmits<{
  'update:usePlatformDelivery': [value: boolean]
  'update:expressNo': [value: string]
  'update:platformDeliveryForm': [form: PlatformDeliveryForm]
  'select-address': [address?: any]
  'scan-express': []
}>()

const canUsePlatformDelivery = computed(() => {
  return !props.checkingPickup && props.pickupAvailable && Number(props.orderCount || 0) >= Number(props.freeShippingMinCount || 1)
})

const unavailableText = computed(() => {
  if (props.checkingPickup) return '正在确认门店取件服务，请稍候'
  if (!props.pickupAvailable) return props.pickupUnavailableReason || '门店暂未配置可用的上门取件服务，请联系门店或自行寄件'
  return `满${props.freeShippingMinCount}台可预约上门取件；当前可自行寄件并填写运单号。`
})

// 首次确定服务可用时自动选中上门取件，客户手动选择后不擅自切回。
const userChoseMode = ref(false)
watch(canUsePlatformDelivery, (available) => {
  if (!available) emit('update:usePlatformDelivery', false)
  else if (!userChoseMode.value) emit('update:usePlatformDelivery', true)
}, { immediate: true })

const handlePickupToggle = () => {
  if (!canUsePlatformDelivery.value) {
    uni.showToast({
      title: unavailableText.value,
      icon: 'none'
    })
    emit('update:usePlatformDelivery', false)
    return
  }

  userChoseMode.value = true
  emit('update:usePlatformDelivery', true)
}

const handleManualToggle = () => {
  userChoseMode.value = true
  emit('update:usePlatformDelivery', false)
}

const syncPickupTime = () => {
  const hasTime = pickupDate.value || pickupStart.value || pickupEnd.value
  emit('update:platformDeliveryForm', {
    ...props.platformDeliveryForm,
    pickup_time: hasTime ? `${pickupDate.value} ${pickupStart.value}-${pickupEnd.value}` : '',
    pickup_time_required: props.needPickupTime
  })
}
const changePickupDate = (event: any) => { pickupDate.value = event.detail.value; syncPickupTime() }
const changePickupStart = (event: any) => { pickupStart.value = event.detail.value; syncPickupTime() }
const changePickupEnd = (event: any) => { pickupEnd.value = event.detail.value; syncPickupTime() }
const clearPickupTime = () => { pickupDate.value = ''; pickupStart.value = ''; pickupEnd.value = ''; syncPickupTime() }
watch(() => props.platformDeliveryForm.pickup_time, (value) => {
  if (!value) { pickupDate.value = ''; pickupStart.value = ''; pickupEnd.value = '' }
})

const handleExpressNoChange = (value: string) => {
  emit('update:expressNo', value)
}



// 处理地址选择
const handleAddressSelect = (address: any) => {
  emit('select-address', address)
}
</script>

<style scoped lang="scss">
.pickup-carrier { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12rpx 20rpx; margin-bottom: 18rpx; font-size: 26rpx; color: var(--recycle-text-sub); }
.pickup-carrier text:last-child { color: var(--recycle-text-main); font-weight: 600; }
.pickup-payment-tip { padding: 14rpx 18rpx; margin-bottom: 18rpx; border-radius: 10rpx; background: var(--recycle-notice-bg); color: var(--recycle-notice-text); font-size: 24rpx; line-height: 1.6; }
.pickup-time-fields { display: flex; flex-direction: column; gap: 16rpx; margin-top: 18rpx; }
.pickup-time-value { display: flex; align-items: center; justify-content: space-between; padding: 18rpx; border: 1rpx solid var(--recycle-line); border-radius: 12rpx; font-size: 26rpx; }
.pickup-time-range { display: flex; align-items: center; gap: 16rpx; }
.pickup-time-range picker { flex: 1; }
.pickup-time-note { display: block; font-size: 23rpx; line-height: 1.6; color: var(--recycle-text-sub); }
.pickup-clear { color: var(--recycle-brand); font-size: 24rpx; }
.label {
  font-size: 14px;
  color: var(--recycle-text-main);
}

.input-wrapper {
  flex: 1;
  min-width: 0;
}

.express-mode-label { margin-left: auto; font-size: 24rpx; font-weight: 400; color: var(--recycle-text-sub); }
.express-number-row { display: flex; align-items: flex-start; gap: 24rpx; padding: 16rpx 0 8rpx; }
.express-number-label { flex-shrink: 0; width: 112rpx; padding-top: 12rpx; font-size: 26rpx; line-height: 40rpx; color: var(--recycle-text-sub); }
.express-number-row :deep(.u-form-item__body) { padding: 0; }
.express-number-row :deep(.u-input) { min-width: 0; padding: 12rpx 16rpx !important; border-radius: 8rpx; background: var(--recycle-bg-soft); }
.express-number-row :deep(.u-form-item__body__right__message) { margin-left: 0 !important; font-size: 24rpx; line-height: 36rpx; }

.express-info-section {
  margin-top: var(--recycle-order-section-gap, 12px);
  padding: 20px var(--recycle-order-gutter, 24px);
  background: var(--recycle-bg-card);
}

.express-section-title {
  display: flex;
  align-items: center;
  gap: 8rpx;
  min-height: 44rpx;
  margin-bottom: 16px;
  color: var(--recycle-text-main);
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 600;
}

.delivery-mode-toggle {
  display: flex;
  gap: 16rpx;

  .toggle-item {
    position: relative;
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 80px;
    box-sizing: border-box;
    text-align: center;
    padding: 18px 8px 12px;
    border: 1rpx solid var(--recycle-line);
    border-radius: 8rpx;
    font-size: 14px;
    line-height: 20px;
    color: var(--recycle-text-main);
    cursor: pointer;
    transition: color .15s, background-color .15s;

    &.active {
      background: var(--recycle-bg-soft);
      color: var(--recycle-brand);
      border-color: var(--recycle-brand);
      font-weight: 600;
    }

    &:active {
      opacity: .8;
    }

    &.disabled {
      opacity: 0.56;
      color: var(--recycle-text-sub);
    }
  }
}

.toggle-item-copy { display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 0; gap: 4px; }
.toggle-item-heading { display: flex; align-items: center; justify-content: center; gap: 6px; }
.toggle-item-heading text { white-space: nowrap; }
.toggle-item-heading :deep(.delivery-icon) { width: 22px; height: 22px; font-size: 22px; line-height: 22px; }
.toggle-item-description { font-size: 11px; line-height: 16px; font-weight: 400; color: var(--recycle-text-sub); }
.mode-check { position: absolute; top: 5px; right: 5px; width: 12px; height: 12px; visibility: hidden; }
.toggle-item.active .mode-check { visibility: visible; }

.platform-threshold-tip {
  display: flex;
  align-items: flex-start;
  gap: 8rpx;
  padding: 10rpx 0 0;
  font-size: 23rpx;
  color: var(--recycle-text-sub);
  line-height: 1.5;
}

.platform-delivery-section {
  .select-address-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    margin-bottom: 12px;
    background: var(--recycle-bg-soft);
    border: 1px dashed var(--recycle-line);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;

    &:active {
      background: var(--recycle-bg-soft);
      border-color: var(--recycle-brand);
    }
  }

  .address-card-clickable {
    background: var(--recycle-bg-card);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    border: 1px solid var(--recycle-line);
    cursor: pointer;
    transition: all 0.2s;

    &:active {
      background: var(--recycle-bg-soft);
      border-color: var(--recycle-brand);
    }

    .address-compact {
      display: flex;
      flex-direction: column;
      gap: 8px;

      .user-avatar-small {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--recycle-button-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }

      .user-info-compact {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;

        .user-name-compact {
          font-size: 14px;
          font-weight: 500;
          color: var(--recycle-text-main);
        }

        .user-phone-compact {
          font-size: 13px;
          color: var(--recycle-text-sub);
        }
      }

      .edit-btn-small {
        padding: 4px;
        background: var(--recycle-bg-soft);
        border-radius: 4px;

        &:active {
          background: var(--recycle-line);
        }
      }

      .address-text-compact {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        padding-left: 36px;

        text {
          font-size: 13px;
          color: var(--recycle-text-sub);
          line-height: 1.5;
        }
      }
    }
  }
}

.loading-hint,
.no-channel-hint {
  padding: 12px;
  text-align: center;
  font-size: 13px;
  color: var(--recycle-text-sub);
  background: var(--recycle-bg-soft);
  border-radius: 8px;
  margin-bottom: 12px;
}
</style>
