<template>
  <view class="express-info-section">
    <view class="express-section-title">
      <up-icon name="car" size="16" color="var(--recycle-brand)"></up-icon>
      <text>寄件信息</text>
    </view>

    <!-- 快递方式选择 -->
    <up-row customStyle="margin-bottom: 12px">
      <up-col span="12">
        <view class="delivery-mode-toggle">
          <!-- 动态渲染平台快递渠道 -->
          <view
            v-for="channel in channels"
            :key="channel.value"
            :class="['toggle-item', usePlatformDelivery && currentChannelValue === channel.value ? 'active' : '', !canUsePlatformDelivery ? 'disabled' : '']"
            @click="handleChannelClick(channel)"
          >
            <view class="flex items-center justify-center gap-1">
              <text>{{ platformDeliveryDisplayName }}</text>
              <view class="free-tag">
                <text class="free-tag-text">{{ platformDeliveryTag }}</text>
              </view>
            </view>
          </view>

          <!-- 固定的快递单号选项（始终显示） -->
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

    <view v-if="!canUsePlatformDelivery" class="platform-threshold-tip">
      <up-icon name="info-circle" size="14" color="var(--recycle-notice-text)"></up-icon>
      <text>满 {{ freeShippingMinCount }} 台可使用{{ platformDeliveryName }}包邮；当前可手动填写快递单号。</text>
    </view>

    <!-- 平台快递下单 -->
    <view v-if="usePlatformDelivery" class="platform-delivery-section">
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
import { useReceivingChannels, type ChannelItem } from '../../../hooks/useReceivingChannels'

interface Props {
  usePlatformDelivery: boolean
  expressNo: string
  platformDeliveryForm: PlatformDeliveryForm
  pickupTimeOptions: Array<{ label: string; value: string }>
  needPickupTime: boolean
  orderCount?: number
  freeShippingMinCount?: number
  platformDeliveryName?: string
}

const props = withDefaults(defineProps<Props>(), {
  orderCount: 1,
  freeShippingMinCount: 1,
  platformDeliveryName: '京东快递'
})

// 预约时间选择器显示状态
const showPickupTimePicker = ref(false)

// 地址选择弹窗显示状态
const showAddressPopup = ref(false)

// 当前选择的平台快递渠道
const selectedChannelValue = ref('')

// 使用收货渠道 hook
const {
  channels,
  loading,
  defaultChannelValue,
  getDefaultPlatformDeliveryState,
  isPlatformChannel
} = useReceivingChannels()

const emit = defineEmits<{
  'update:usePlatformDelivery': [value: boolean]
  'update:expressNo': [value: string]
  'update:platformDeliveryForm': [form: PlatformDeliveryForm]
  'select-address': [address?: any]
  'scan-express': []
}>()

// 监听渠道加载完成后设置默认值
watch(
  () => loading.value,
  (isLoading) => {
    if (!isLoading) {
      // 渠道加载完成，设置默认状态
      selectedChannelValue.value = defaultChannelValue.value
      const defaultState = getDefaultPlatformDeliveryState()
      emit('update:usePlatformDelivery', defaultState && canUsePlatformDelivery.value)
    }
  },
  { immediate: true }
)

// 计算当前选中的渠道 value
const currentChannelValue = computed(() => {
  return props.usePlatformDelivery ? (selectedChannelValue.value || defaultChannelValue.value) : 'manual'
})

const canUsePlatformDelivery = computed(() => {
  return Number(props.orderCount || 0) >= Number(props.freeShippingMinCount || 1)
})

const platformDeliveryTag = computed(() => {
  return Number(props.freeShippingMinCount || 1) > 1 ? `满${props.freeShippingMinCount}台包邮` : '包邮'
})

const platformDeliveryDisplayName = computed(() => {
  return String(props.platformDeliveryName || '京东快递').trim() || '京东快递'
})

// 处理渠道点击
const handleChannelClick = (channel: ChannelItem) => {
  if (!canUsePlatformDelivery.value) {
    uni.showToast({
      title: `满 ${props.freeShippingMinCount} 台可用${platformDeliveryDisplayName.value}包邮`,
      icon: 'none'
    })
    emit('update:usePlatformDelivery', false)
    return
  }

  if (isPlatformChannel(channel.value)) {
    selectedChannelValue.value = channel.value
    emit('update:usePlatformDelivery', true)
  }
}

const handleManualToggle = () => {
  emit('update:usePlatformDelivery', false)
}

const handleExpressNoChange = (value: string) => {
  emit('update:expressNo', value)
}



// 处理地址选择
const handleAddressSelect = (address: any) => {
  emit('select-address', address)
}
</script>

<style scoped lang="scss">
.label {
  font-size: 14px;
  color: var(--recycle-text-main);
}

.input-wrapper {
  width: 100%;
  overflow: hidden;
}

.express-info-section {
  margin-top: 20rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: var(--recycle-bg-card);
  border: 1rpx solid var(--recycle-line);
  border-left: 6rpx solid var(--recycle-brand);
  box-shadow: 0 8rpx 20rpx rgba(31, 41, 55, 0.06);
}

.express-section-title {
  display: flex;
  align-items: center;
  gap: 8rpx;
  margin-bottom: 18rpx;
  color: var(--recycle-brand);
  font-size: 28rpx;
  line-height: 38rpx;
  font-weight: 800;
}

.delivery-mode-toggle {
  display: flex;
  background: var(--recycle-bg-soft);
  border-radius: 8px;
  padding: 4px;
  gap: 4px;

  .toggle-item {
    flex: 1;
    text-align: center;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    color: var(--recycle-text-sub);
    cursor: pointer;
    transition: all 0.3s;

    &.active {
      background: var(--recycle-button-bg);
      color: var(--recycle-button-text);
      box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
    }

    &:active {
      transform: scale(0.98);
    }

    &.disabled {
      opacity: 0.56;
      background: var(--recycle-line);
      color: var(--recycle-text-sub);
    }
  }
}

.free-tag {
  display: inline-flex;
  align-items: center;
  padding: 2px 6px;
  background: var(--recycle-notice-text);
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);

  .free-tag-text {
    font-size: 10px;
    font-weight: 600;
    color: var(--recycle-button-text);
    line-height: 1;
  }
}

.toggle-item.active .free-tag {
  background: var(--recycle-notice-bg);

  .free-tag-text {
    color: var(--recycle-notice-text);
  }
}

.platform-threshold-tip {
  display: flex;
  align-items: center;
  gap: 8rpx;
  padding: 16rpx 18rpx;
  margin-bottom: 16rpx;
  background: var(--recycle-notice-bg);
  border: 1px solid rgba(245, 158, 11, 0.24);
  border-radius: 12rpx;
  font-size: 12px;
  color: var(--recycle-notice-text);
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
