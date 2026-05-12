<template>
  <view class="recycle-order-page" :style="themeVars">
    <RecyclePageHeader title="立即下单" subtitle="提交设备信息并选择交付方式" />
    <view class="order-page-content">
      <view class="delivery-sticky">
        <DeliveryModeToggle
          v-model="currentTab"
          :tabs="deliveryTabs"
          @to-order-list="toOrderList"
        />
      </view>

      <OrderNoticeBar
        :enabled="orderSubmitConfig.notice.enabled"
        :title="orderSubmitConfig.notice.title"
        :content="orderSubmitConfig.notice.content"
      />

      <u-form :model="form" :rules="rules" ref="formRef" label-position="left">
      <!-- 出货信息 -->
      <view class="order-section shipment-section">
        <view class="shipment-header">
          <view class="shipment-title">
            <up-icon name="info-circle" size="16" color="var(--recycle-brand)"></up-icon>
            <text>出货信息</text>
          </view>
          <view v-if="orderSubmitConfig.device_add_enabled" class="shipment-add-button" @click="openInlineDeviceAdd">
            <up-icon name="plus" size="14" color="#fff"></up-icon>
            <text>{{ phoneList.length ? '继续添加' : '添加设备' }}</text>
          </view>
        </view>

        <!-- 设备列表管理 -->
        <DeviceListManager
          ref="deviceListManagerRef"
          :devices="phoneList"
          :count="deviceCount"
          :show-add-button="true"
          :show-batch-button="false"
          :show-delete-button="true"
          @update:devices="phoneList = $event"
          @update:count="deviceCount = $event"
          @add-single-device="openSingleDeviceModal"
          @add-device="openBatchDeviceModal"
        />

        <!-- 备注 -->
        <up-row>
          <up-col span="3">
            <view class="label">备注</view>
          </up-col>
          <up-col span="9">
            <view class="input-wrapper">
              <up-textarea autoHeight v-model="form.comment" placeholder="请输入备注信息"></up-textarea>
            </view>
          </up-col>
        </up-row>
      </view>

      <!-- 寄件信息 -->
      <ExpressInfoSection
        v-if="currentTab === 0"
        :use-platform-delivery="enablePlatformDelivery"
        :express-no="form.express_no"
        :platform-delivery-form="platformDeliveryForm"
        :pickup-time-options="pickupTimeOptions"
        :need-pickup-time="needPickupTime"
        :order-count="deviceCount"
        :free-shipping-min-count="orderSubmitConfig.platform_delivery.free_shipping_min_count"
        :platform-delivery-name="orderSubmitConfig.platform_delivery.display_name"
        @update:use-platform-delivery="handlePlatformDeliveryChange"
        @update:express-no="form.express_no = $event"
        @update:platform-delivery-form="platformDeliveryForm = $event"
        @select-address="fillAddressFromSelected"
        @scan-express="scanCode"
      />

      <!-- 商家信息 -->
      <ShopInfoCard
        :shop-info="shopInfo"
        @copy="copyShopInfo"
        @open-location="openLocation"
      />
      <view class="order-section agreement-section">
       
      <!-- 回收协议 -->
        <AgreementCheckbox
          v-model="isAgreeRecycle"
          agreement-text="我已阅读并同意"
          agreement-key="recycle_service"
          agreement-title="回收服务协议"
        />

      </view>
      </u-form>
    </view>

    <view class="submit-bar">
      <view class="submit-bar__meta">
        <text class="submit-bar__title">{{ currentTab === 0 ? '邮寄到店' : '自送到店' }}</text>
        <text class="submit-bar__desc">共 {{ deviceCount }} 台设备</text>
      </view>
      <view class="submit-bar__button" @click="handleSubmitOrder">确认发货</view>
    </view>

    <!-- 设备输入弹窗 -->
    <DeviceInputModal
      :visible="showDeviceModal"
      :mode="deviceModalMode"
      :enable-pricing="true"
      @update:visible="showDeviceModal = $event"
      @confirm="handleDeviceConfirm"
    />

    <!-- 公众号关注引导弹窗 -->
    <FollowOfficialAccountPopup
      :visible="showFollowPopup"
      :wechat-name="wechatName"
      :qr-code="qrCode"
      :title="followTitle"
      :content="followContent"
      @close="handleFollowPopupClose"
    />

    <tabbar addon="recycle" />
  </view>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getPaymentList } from '@/addon/recycle/api/payment'
import { getRecycleUserAddressInfo } from '@/addon/recycle/api/return_order'
import { checkExpressEnabled } from '@/addon/recycle/api/express'
import { getOrderSubmitConfig } from '@/addon/recycle/api/order'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import { buildRecycleThemeVars } from '../../utils/theme'

// 导入组件
import DeliveryModeToggle from './components/DeliveryModeToggle.vue'
import DeviceListManager from './components/DeviceListManager.vue'
import DeviceInputModal from './components/DeviceInputModal.vue'
import ExpressInfoSection from './components/ExpressInfoSection.vue'
import ShopInfoCard from './components/ShopInfoCard.vue'
import AgreementCheckbox from './components/AgreementCheckbox.vue'
import FollowOfficialAccountPopup from './components/FollowOfficialAccountPopup.vue'
import OrderNoticeBar from './components/OrderNoticeBar.vue'

// 导入 composables
import { useTabCache } from '../../hooks/useTabCache'
import { useOrderForm } from '../../hooks/useOrderForm'
import { useDeviceManagement } from '../../hooks/useDeviceManagement'
import { usePlatformDelivery } from '../../hooks/usePlatformDelivery'
import { useShopInfo } from '../../hooks/useShopInfo'
import { useOrderSubmit } from '../../hooks/useOrderSubmit'

// Tab 缓存管理
const TAB_CACHE_KEY = 'recycle_order_current_tab'
const { currentTab, switchTab } = useTabCache(TAB_CACHE_KEY, 0)

const orderSubmitConfig = ref({
  device_add_enabled: 1,
  notice: {
    enabled: 0,
    title: '下单提示',
    content: ''
  },
  default_count: 1,
  delivery_modes: {
    mail: 1,
    self: 1
  },
  profile: {
    enabled: 1,
    payment_required: 1,
    payment_min_count: 1,
    id_card_required: 1
  },
  platform_delivery: {
    display_name: '京东快递',
    free_shipping_min_count: 1,
    provider: '',
    provider_name: '',
    provider_options: [] as Array<{
      provider: string
      provider_name: string
      is_default: number
      support_quote?: boolean
      support_cancel?: boolean
      support_track?: boolean
    }>
  },
  price_detail_theme: {
    colors: {}
  }
})

const themeVars = computed(() => buildRecycleThemeVars(orderSubmitConfig.value.price_detail_theme?.colors || {}))

const deliveryTabs = computed(() => {
  const tabs: Array<{ label: string; value: number }> = []
  if (orderSubmitConfig.value.delivery_modes.mail) tabs.push({ label: '邮寄到店', value: 0 })
  if (orderSubmitConfig.value.delivery_modes.self) tabs.push({ label: '自送到店', value: 1 })
  return tabs
})

const normalizePositiveNumber = (value: any, fallback = 1) => {
  const count = Number(value)
  return Number.isFinite(count) && count > 0 ? Math.min(99, Math.floor(count)) : fallback
}

// 表单管理
const { form, rules, formRef, resetForm } = useOrderForm(currentTab)

// 计算属性确保 count 是数字类型
const deviceCount = computed({
  get: () => form.value.count,
  set: (val: any) => {
    const rawValue = typeof val === 'object' && val !== null ? val.value : val
    const count = Number(rawValue)
    form.value.count = Number.isFinite(count) && count > 0 ? count : 1
  }
})

// 设备管理
const { phoneList, addDevices, scanIMEI } = useDeviceManagement()

// 平台快递管理
const {
  enablePlatformDelivery,
  needPickupTime,
  platformDeliveryForm,
  pickupTimeOptions,
  fillAddressFromSelected,
  handlePlatformDeliveryToggle,
  resetPlatformDeliveryForm
} = usePlatformDelivery()

// 商家信息管理
const { shopInfo, fetchShopInfo, copyShopInfo, openLocation } = useShopInfo()

// 订单提交
const { submitOrder, showFollowPopup, wechatName, qrCode, followTitle, followContent, dismissFollow } = useOrderSubmit()

// 协议勾选
const isAgreeRecycle = ref(false)

// 设备弹窗状态
const showDeviceModal = ref(false)
const deviceModalMode = ref<'single' | 'batch'>('batch')
const deviceListManagerRef = ref<InstanceType<typeof DeviceListManager> | null>(null)

// 监听 Tab 切换
watch(currentTab, (newVal) => {
  form.value.delivery_type = newVal === 0 ? 1 : 2
  switchTab(newVal)

  // 切换到自送时清空快递单号
  if (newVal === 1) {
    form.value.express_no = ''
  }
})

const normalizeOrderSubmitConfig = (data: any = {}) => {
  const mail = data.delivery_modes?.mail ? 1 : 0
  const self = data.delivery_modes?.self ? 1 : 0
  orderSubmitConfig.value = {
    device_add_enabled: data.device_add_enabled ? 1 : 0,
    notice: {
      enabled: data.notice?.enabled ? 1 : 0,
      title: data.notice?.title || '下单提示',
      content: data.notice?.content || ''
    },
    default_count: normalizePositiveNumber(data.default_count, 1),
    delivery_modes: {
      mail: mail || self ? mail : 1,
      self: mail || self ? self : 1
    },
    profile: {
      enabled: data.profile?.enabled === 0 ? 0 : 1,
      payment_required: data.profile?.payment_required === 0 ? 0 : 1,
      payment_min_count: Math.min(5, normalizePositiveNumber(data.profile?.payment_min_count, 1)),
      id_card_required: data.profile?.id_card_required === 0 ? 0 : 1
    },
    platform_delivery: {
      display_name: data.platform_delivery?.display_name || '京东快递',
      free_shipping_min_count: normalizePositiveNumber(data.platform_delivery?.free_shipping_min_count, 1),
      provider: data.platform_delivery?.provider || '',
      provider_name: data.platform_delivery?.provider_name || '',
      provider_options: Array.isArray(data.platform_delivery?.provider_options) ? data.platform_delivery.provider_options : []
    },
    price_detail_theme: data.price_detail_theme || { colors: {} }
  }
}

const applyAvailableDeliveryMode = () => {
  const modes = orderSubmitConfig.value.delivery_modes
  if (!modes.mail && currentTab.value === 0) {
    currentTab.value = 1
    return
  }
  if (!modes.self && currentTab.value === 1) {
    currentTab.value = 0
  }
}

const loadOrderSubmitConfig = async () => {
  try {
    const res = await getOrderSubmitConfig()
    normalizeOrderSubmitConfig(res.data || {})
  } catch (error) {
    console.error('获取下单配置失败：', error)
    normalizeOrderSubmitConfig()
  }
  applyAvailableDeliveryMode()
  applyDefaultCount()
}

const applyDefaultCount = () => {
  if (phoneList.value.length > 0) return

  form.value.count = normalizePositiveNumber(orderSubmitConfig.value.default_count, 1)
}

// 跳转到订单列表
const toOrderList = () => {
  uni.navigateTo({
    url: '/addon/recycle/pages/order/list'
  })
}

// 打开单台设备添加弹窗
const openSingleDeviceModal = () => {
  deviceModalMode.value = 'single'
  showDeviceModal.value = true
}

// 打开批量设备添加弹窗
const openBatchDeviceModal = () => {
  deviceModalMode.value = 'batch'
  showDeviceModal.value = true
}

const openInlineDeviceAdd = () => {
  if (!orderSubmitConfig.value.device_add_enabled) return

  deviceListManagerRef.value?.openAddDialog()
}

// 处理设备确认添加
const handleDeviceConfirm = (devices: any[]) => {
  addDevices(devices)
  form.value.count = phoneList.value.length
}

// 扫描快递单号
const scanCode = () => {
  uni.scanCode({
    onlyFromCamera: true,
    success: res => {
      if (res.errMsg === 'scanCode:ok') {
        form.value.express_no = res.result
      } else {
        uni.showToast({ title: res.errMsg, icon: 'none' })
      }
    }
  })
}

// 处理平台快递切换
const handlePlatformDeliveryChange = async (value: boolean) => {
  if (value && !canUsePlatformDelivery.value) {
    uni.showToast({
      title: `满 ${orderSubmitConfig.value.platform_delivery.free_shipping_min_count} 台可用${orderSubmitConfig.value.platform_delivery.display_name}包邮`,
      icon: 'none'
    })
    enablePlatformDelivery.value = false
    return
  }

  enablePlatformDelivery.value = value
  if (value) {
    await handlePlatformDeliveryToggle()
  }
}

interface ExpressCheckResponse {
  code: number
  msg?: string
  data?: {
    enabled?: boolean
    provider?: string
    provider_name?: string
    has_shop_address?: boolean
    memo?: string
    prompt?: string
  }
}

const showPlatformDeliveryMemoConfirm = (content: string): Promise<boolean> => {
  return new Promise(resolve => {
    uni.showModal({
      title: '平台快递提示',
      content,
      confirmText: '继续下单',
      cancelText: '我再看看',
      success: (res) => resolve(!!res.confirm),
      fail: () => resolve(false)
    })
  })
}

const shouldContinueWithPlatformPrompt = async (): Promise<boolean> => {
  // 仅在邮寄模式且启用平台快递时提示
  if (currentTab.value !== 0 || !enablePlatformDelivery.value) return true

  if (!canUsePlatformDelivery.value) {
    uni.showToast({
      title: `满 ${orderSubmitConfig.value.platform_delivery.free_shipping_min_count} 台可用${orderSubmitConfig.value.platform_delivery.display_name}包邮`,
      icon: 'none'
    })
    return false
  }

  try {
    const res = await checkExpressEnabled() as ExpressCheckResponse
    if (res.code !== 1 || !res.data) return true

    if (!res.data.enabled) {
      uni.showToast({
        title: '平台快递未启用',
        icon: 'none'
      })
      return false
    }

    if (!res.data.has_shop_address) {
      uni.showToast({
        title: '商家收货地址未配置',
        icon: 'none'
      })
      return false
    }

    const memo = String(res.data.prompt || res.data.memo || '').trim()

    if (!memo) return true
    return await showPlatformDeliveryMemoConfirm(memo)
  } catch (error) {
    console.error('获取平台快递提示信息失败：', error)
    // 获取提示失败时不阻断下单流程
    return true
  }
}

const canUsePlatformDelivery = computed(() => {
  return Number(deviceCount.value || 0) >= Number(orderSubmitConfig.value.platform_delivery.free_shipping_min_count || 1)
})

watch(canUsePlatformDelivery, (canUse) => {
  if (!canUse && enablePlatformDelivery.value) {
    enablePlatformDelivery.value = false
  }
})

// 提交订单
const handleSubmitOrder = async () => {
  const modeKey = currentTab.value === 0 ? 'mail' : 'self'
  if (!orderSubmitConfig.value.delivery_modes[modeKey]) {
    uni.showToast({
      title: '当前下单方式未开启',
      icon: 'none'
    })
    applyAvailableDeliveryMode()
    return
  }

  const profileReady = await checkPaymentInfo()
  if (!profileReady) return

  const canSubmit = await shouldContinueWithPlatformPrompt()
  if (!canSubmit) return

  await submitOrder({
    form: form.value,
    phoneList: phoneList.value,
    currentTab: currentTab.value,
    usePlatformDelivery: enablePlatformDelivery.value,
    platformDeliveryForm: platformDeliveryForm.value,
    isAgreeRecycle: isAgreeRecycle.value,
    formRef: formRef.value,
    onSuccess: () => {
      // 清空表单
      resetForm()
      phoneList.value = []
      resetPlatformDeliveryForm()
      isAgreeRecycle.value = false
      applyDefaultCount()
    }
  })
}

// 检查收款信息
const checkPaymentInfo = async (): Promise<boolean> => {
  const profile = orderSubmitConfig.value.profile
  if (!profile.enabled) return true

  try {
    const [paymentRes, addressRes] = await Promise.all([
      profile.payment_required ? getPaymentList() : Promise.resolve({ code: 1, data: [] }),
      getRecycleUserAddressInfo()
    ])

    const paymentList = Array.isArray(paymentRes?.data) ? paymentRes.data : []
    const addressInfo = addressRes?.data || {}
    const missing: string[] = []

    if (!addressInfo.name || !addressInfo.mobile) {
      missing.push('个人资料')
    }

    if (profile.id_card_required && (!addressInfo.id_card || !addressInfo.card_pic)) {
      missing.push('身份证信息')
    }

    if (profile.payment_required && paymentList.length < profile.payment_min_count) {
      missing.push(`${profile.payment_min_count} 种收款方式`)
    }

    if (missing.length) {
      uni.showModal({
        title: '提示（重要）',
        content: `请完善${missing.join('、')}，以便回收完成后及时打款。`,
        confirmText: '立即设置',
        cancelText: '稍后设置',
        success: function(res) {
          if (res.confirm) {
            uni.navigateTo({
              url: '/addon/recycle/pages/payment/index'
            })
          } else {
            uni.showToast({
              title: '请记得及时完善资料，避免影响回收款到账',
              icon: 'none',
              duration: 3000
            })
          }
        }
      })
      return false
    }
  } catch (error) {
    console.error('获取收款信息失败：', error)
  }

  return true
}

// 关闭公众号关注弹窗后跳转订单列表
const handleFollowPopupClose = () => {
  dismissFollow()
  uni.navigateTo({
    url: '/addon/recycle/pages/order/list'
  })
}

// 页面显示时的处理
onShow(async () => {
  await loadOrderSubmitConfig()

  // 请求订阅相关消息通知
  await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay')

  // 检查收款信息
  await checkPaymentInfo()
})

// 页面挂载时获取商家信息
fetchShopInfo()
</script>

<style scoped lang="scss">
.recycle-order-page {
  min-height: 100vh;
  background: var(--recycle-bg-main);
  color: var(--recycle-text-main);
}

.order-page-content {
  padding: 20rpx 20rpx calc(20rpx + env(safe-area-inset-bottom));
}

.delivery-sticky {
  position: sticky;
  top: 0;
  z-index: 60;
  padding-top: 12rpx;
  margin: -12rpx -4rpx 16rpx;
  background: var(--recycle-bg-main);
}

.order-section {
  margin-bottom: 20rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: var(--recycle-bg-card);
  border: 1rpx solid var(--recycle-line);
  box-shadow: 0 8rpx 20rpx rgba(31, 41, 55, 0.06);
}

.shipment-section {
  border-left: 6rpx solid var(--recycle-brand);
}

.agreement-section {
  margin-top: 28rpx;
  padding: 24rpx 22rpx;
}

.shipment-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20rpx;
  margin-bottom: 20rpx;
}

.shipment-title {
  display: flex;
  align-items: center;
  gap: 8rpx;
  min-width: 0;
  color: var(--recycle-brand);
  font-size: 28rpx;
  line-height: 38rpx;
  font-weight: 800;
}

:deep(.u-button--primary) {
  background: var(--recycle-button-bg) !important;
  border-color: var(--recycle-button-bg) !important;
  color: var(--recycle-button-text) !important;
}

.submit-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: calc(50px + env(safe-area-inset-bottom));
  z-index: 9998;
  padding: 18rpx 20rpx;
  background: var(--recycle-toolbar-bg);
  border-top: 1rpx solid var(--recycle-line);
  box-shadow: 0 -8rpx 22rpx rgba(31, 41, 55, 0.08);
  display: flex;
  align-items: center;
  gap: 20rpx;
}

.submit-bar__meta {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.submit-bar__title {
  font-size: 28rpx;
  line-height: 38rpx;
  font-weight: 800;
  color: var(--recycle-text-main);
}

.submit-bar__desc {
  margin-top: 2rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: var(--recycle-text-sub);
}

.submit-bar__button {
  flex-shrink: 0;
  min-width: 230rpx;
  height: 78rpx;
  line-height: 78rpx;
  text-align: center;
  border-radius: 39rpx;
  background: var(--recycle-button-bg);
  color: var(--recycle-button-text);
  font-size: 28rpx;
  font-weight: 800;
}

:deep(.u-form) {
  color: var(--recycle-text-main);
}

.shipment-add-button {
  flex-shrink: 0;
  min-width: 144rpx;
  height: 58rpx;
  padding: 0 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
  background: var(--recycle-button-bg);
  color: var(--recycle-button-text);
  border-radius: 999rpx;
  font-size: 12px;
  font-weight: 500;
}

.label {
  font-size: 14px;
  color: var(--recycle-text-main);
}

.input-wrapper {
  width: 100%;
  overflow: hidden;
}

:deep(.up-button--primary),
:deep(.u-button--primary) {
  background: var(--recycle-button-bg) !important;
  border: none !important;
  color: var(--recycle-button-text) !important;
}
</style>
