<template>
  <page-meta :page-style="popupPageStyle" />
  <uni-layout name="default">
  <view class="recycle-order-page" :style="themeVars">
    <RecyclePageHeader title="立即下单" />
    <view class="delivery-sticky" :style="{ top: `${navbarHeight}px` }">
      <DeliveryModeToggle
        v-model="currentTab"
        :tabs="deliveryTabs"
      />
    </view>
    <view class="order-page-content">
      <OrderNoticeBar
        :enabled="orderSubmitConfig.notice.enabled"
        :title="orderSubmitConfig.notice.title"
        :content="orderSubmitConfig.notice.content"
        :url="orderSubmitConfig.notice.url"
        :link-text="orderSubmitConfig.notice.link_text"
        return-url="/addon/hsx_recycle/pages/order/order"
      />

      <u-form :model="form" :rules="rules" ref="formRef" label-position="left">
      <!-- 出货信息 -->
      <view class="order-section shipment-section">
        <view class="shipment-header">
          <view class="shipment-title">
            <text>回收设备</text>
          </view>
          <OrderUiButton v-if="orderSubmitConfig.device_add_enabled" icon="plus" @click="openInlineDeviceAdd">{{ phoneList.length ? '继续添加' : '添加设备' }}</OrderUiButton>
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
        <view class="order-note-toggle" @tap="showRemark = !showRemark"><up-icon name="edit-pen" size="16" color="var(--recycle-text-sub)" /><text>{{ showRemark ? '收起备注' : (form.comment ? '查看备注' : '添加备注（选填）') }}</text><up-icon :name="showRemark ? 'arrow-up' : 'arrow-down'" size="12" color="var(--recycle-text-sub)" /></view>
        <view v-if="showRemark" class="order-note-row">
          <text class="order-note-label">备注</text>
          <view class="order-note-input">
            <up-textarea autoHeight border="none" v-model="form.comment" placeholder="选填，补充设备或交付说明"></up-textarea>
          </view>
        </view>
      </view>

      <!-- 寄件信息 -->
      <ExpressInfoSection
        v-if="currentTab === 0"
        :use-platform-delivery="enablePlatformDelivery"
        :express-no="form.express_no"
        :platform-delivery-form="platformDeliveryForm"
        :need-pickup-time="needPickupTime"
        :pickup-time-supported="pickupTimeSupported"
        :pickup-time-options="pickupTimeOptions"
        :refresh-pickup-times="detectProvider"
        :pickup-available="pickupAvailable"
        :checking-pickup="checkingPickup"
        :pickup-unavailable-reason="pickupUnavailableReason"
        :carrier-name="carrierName"
        :payment-tips="paymentTips"
        :order-count="deviceCount"
        :free-shipping-min-count="orderSubmitConfig.platform_delivery.free_shipping_min_count"
        :platform-delivery-name="orderSubmitConfig.platform_delivery.display_name"
        @update:use-platform-delivery="handlePlatformDeliveryChange"
        @update:express-no="form.express_no = $event"
        @update:platform-delivery-form="platformDeliveryForm = $event"
        @select-address="fillAddressFromSelected"
        @scan-express="scanCode"
      />

      <LogisticsVehicleSection v-if="currentTab === 2" v-model="logisticsVehicleForm" :arrival-hint="logisticsArrivalHint" />

      <!-- 商家信息 -->
      <ShopInfoCard
        :shop-info="shopInfo"
        :delivery-mode="currentTab"
        @copy="copyShopInfo"
        @open-location="openLocation"
      />
      </u-form>
    </view>

    <view class="submit-bar">
      <AgreementCheckbox v-model="isAgreeRecycle" agreement-text="我已阅读并同意" agreement-key="recycle_service" agreement-title="回收服务协议" />
      <view class="submit-bar__action">
        <view class="submit-bar__summary"><text>回收数量</text><text class="submit-bar__count">{{ deviceCount }} <text>台</text></text></view>
        <OrderUiButton variant="primary" :loading="preparingSubmit || submitting" loadingText="正在提交" @click="handleSubmitOrder">提交回收订单</OrderUiButton>
      </view>
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

    <tabbar addon="hsx_recycle" />
  </view>
  </uni-layout>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { onReady, onShow } from '@dcloudio/uni-app'
import { getRecycleNavbarMetrics } from '../../hooks/useRecycleNavbar'
import { useRecyclePopupPage } from '../../hooks/useRecyclePopupScroll'
import { getPaymentList } from '@/addon/hsx_recycle/api/payment'
import { getRecycleUserAddressInfo } from '@/addon/hsx_recycle/api/return_order'
import { checkExpressEnabled } from '@/addon/hsx_recycle/api/express'
import { getOrderSubmitConfig } from '@/addon/hsx_recycle/api/order'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import { buildRecycleThemeVars } from '../../utils/theme'

// 导入组件
import OrderUiButton from './components/OrderUiButton.vue'
import DeliveryModeToggle from './components/DeliveryModeToggle.vue'
import DeviceListManager from './components/DeviceListManager.vue'
import DeviceInputModal from './components/DeviceInputModal.vue'
import ExpressInfoSection from './components/ExpressInfoSection.vue'
import LogisticsVehicleSection from './components/LogisticsVehicleSection.vue'
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
const showRemark = ref(false)
const TAB_CACHE_KEY = 'recycle_order_current_tab'
const { currentTab, switchTab } = useTabCache(TAB_CACHE_KEY, 0)

const orderSubmitConfig = ref({
  device_add_enabled: 1,
  notice: {
    enabled: 0,
    title: '下单提示',
    content: '',
    url: '',
    link_text: '查看详情'
  },
  default_count: 1,
  delivery_modes: {
    mail: 1,
    self: 1,
    logistics_vehicle: 0
  },
  logistics_vehicle: {
    arrival_mode: 'half_day',
    morning_cutoff: '12:00',
    same_day_time: '16:00',
    next_day_time: '09:00'
  },
  profile: {
    enabled: 1,
    payment_required: 1,
    payment_min_count: 1,
    id_card_required: 1
  },
  platform_delivery: {
    display_name: '门店快递',
    free_shipping_min_count: 1
  },
  price_detail_theme: {
    colors: {}
  }
})

const { popupPageStyle } = useRecyclePopupPage()
const navbarHeight = getRecycleNavbarMetrics().navbarHeightPx
const themeVars = computed(() => buildRecycleThemeVars(orderSubmitConfig.value.price_detail_theme?.colors || {}))

const deliveryTabs = computed(() => {
  const tabs: Array<{ label: string; value: number }> = []
  if (orderSubmitConfig.value.delivery_modes.mail) tabs.push({ label: '邮寄到店', value: 0 })
  if (orderSubmitConfig.value.delivery_modes.self) tabs.push({ label: '自送到店', value: 1 })
  if (orderSubmitConfig.value.delivery_modes.logistics_vehicle) tabs.push({ label: '物流车', value: 2 })
  return tabs
})
const logisticsArrivalHint = computed(() => {
  const config = orderSubmitConfig.value.logistics_vehicle
  if (config.arrival_mode === 'next_day') return `预计次日 ${config.next_day_time} 可取货`
  return `${config.morning_cutoff} 前提交，预计当天 ${config.same_day_time} 可取；之后为次日 ${config.next_day_time}`
})

const normalizePositiveNumber = (value: any, fallback = 1) => {
  const count = Number(value)
  return Number.isFinite(count) && count > 0 ? Math.min(99, Math.floor(count)) : fallback
}

// 表单管理
const { form, rules, formRef, resetForm } = useOrderForm(currentTab, () => enablePlatformDelivery.value)
const LOGISTICS_CACHE_KEY = 'hsx_recycle_logistics_vehicle_form'
const emptyLogisticsVehicleForm = () => ({
  logistics_name: '',
  logistics_vehicle_no: '',
  logistics_contact_name: '',
  logistics_contact_mobile: '',
  logistics_pickup_address: ''
})
const logisticsVehicleForm = ref<Record<string, string>>(emptyLogisticsVehicleForm())
const restoreLogisticsVehicleForm = () => {
  if (Object.values(logisticsVehicleForm.value).some(value => String(value || '').trim())) return
  const cached = uni.getStorageSync(LOGISTICS_CACHE_KEY)
  if (cached && typeof cached === 'object') logisticsVehicleForm.value = { ...emptyLogisticsVehicleForm(), ...cached }
}

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
  pickupTimeSupported,
  pickupTimeOptions,
  applyPickupPolicy,
  detectProvider,
  pickupAvailable,
  checkingPickup,
  pickupUnavailableReason,
  carrierName,
  paymentTips,
  fillAddressFromSelected,
  handlePlatformDeliveryToggle,
  resetPlatformDeliveryForm
} = usePlatformDelivery()

// 商家信息管理
const { shopInfo, fetchShopInfo, copyShopInfo, openLocation } = useShopInfo()

// 订单提交
const { submitOrder, submitting, showFollowPopup, wechatName, qrCode, followTitle, followContent, dismissFollow } = useOrderSubmit()
const preparingSubmit = ref(false)

// 协议勾选
const isAgreeRecycle = ref(false)

// 设备弹窗状态
const showDeviceModal = ref(false)
const deviceModalMode = ref<'single' | 'batch'>('batch')
const deviceListManagerRef = ref<InstanceType<typeof DeviceListManager> | null>(null)

// 监听 Tab 切换
watch(currentTab, (newVal) => {
  form.value.delivery_type = Number(newVal) + 1
  switchTab(newVal)

  // 切换到自送时清空快递单号
  if (newVal !== 0) {
    form.value.express_no = ''
  }
})

watch([currentTab, enablePlatformDelivery], () => {
  formRef.value?.clearValidate('express_no')
})

onReady(() => {
  // 小程序通过组件方法注册含函数的校验规则。
  formRef.value?.setRules(rules)
})

const normalizeOrderSubmitConfig = (data: any = {}) => {
  const mail = data.delivery_modes?.mail ? 1 : 0
  const self = data.delivery_modes?.self ? 1 : 0
  const logisticsVehicle = data.delivery_modes?.logistics_vehicle ? 1 : 0
  orderSubmitConfig.value = {
    device_add_enabled: data.device_add_enabled ? 1 : 0,
    notice: {
      enabled: data.notice?.enabled ? 1 : 0,
      title: data.notice?.title || '下单提示',
      content: data.notice?.content || '',
      url: data.notice?.url || '',
      link_text: data.notice?.link_text || '查看详情'
    },
    default_count: normalizePositiveNumber(data.default_count, 1),
    delivery_modes: {
      mail: mail || self || logisticsVehicle ? mail : 1,
      self: mail || self || logisticsVehicle ? self : 1,
      logistics_vehicle: logisticsVehicle
    },
    logistics_vehicle: {
      arrival_mode: data.logistics_vehicle?.arrival_mode === 'next_day' ? 'next_day' : 'half_day',
      morning_cutoff: data.logistics_vehicle?.morning_cutoff || '12:00',
      same_day_time: data.logistics_vehicle?.same_day_time || '16:00',
      next_day_time: data.logistics_vehicle?.next_day_time || '09:00'
    },
    profile: {
      enabled: data.profile?.enabled === 0 ? 0 : 1,
      payment_required: data.profile?.payment_required === 0 ? 0 : 1,
      payment_min_count: Math.min(5, normalizePositiveNumber(data.profile?.payment_min_count, 1)),
      id_card_required: data.profile?.id_card_required === 0 ? 0 : 1
    },
    platform_delivery: {
      display_name: data.platform_delivery?.display_name || '门店快递',
      free_shipping_min_count: normalizePositiveNumber(data.platform_delivery?.free_shipping_min_count, 1)
    },
    price_detail_theme: data.price_detail_theme || { colors: {} }
  }
}

const applyAvailableDeliveryMode = () => {
  const modes = orderSubmitConfig.value.delivery_modes
  const enabledTabs = [
    modes.mail ? 0 : -1,
    modes.self ? 1 : -1,
    modes.logistics_vehicle ? 2 : -1
  ].filter(value => value >= 0)
  if (!enabledTabs.includes(currentTab.value)) currentTab.value = enabledTabs[0] ?? 0
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

// 默认台数仅初始化一次；领取任务、补收款资料等页面返回时保留客户已填台数。
let defaultCountApplied = false
const applyDefaultCount = () => {
  if (defaultCountApplied) return
  defaultCountApplied = true
  if (phoneList.value.length > 0) return

  form.value.count = normalizePositiveNumber(orderSubmitConfig.value.default_count, 1)
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
  if (value && !pickupAvailable.value) return
  if (value && !canUsePlatformDelivery.value) {
    uni.showToast({
      title: `满${orderSubmitConfig.value.platform_delivery.free_shipping_min_count}台可预约上门取件`,
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
    pickup_enabled?: boolean
    provider?: string
    provider_name?: string
    has_shop_address?: boolean
    memo?: string
    prompt?: string
    unavailable_reason?: string
    payment_tips?: string
    pickup_time?: string
    pickup_time_text?: string
    pickup_time_changed?: boolean
    pickup_time_options?: import('../../types/order').PickupTimeDay[]
    pickup_time_supported?: boolean
    pickup_time_required?: boolean
  }
}

const showPlatformDeliveryMemoConfirm = (content: string): Promise<boolean> => {
  return new Promise(resolve => {
    uni.showModal({
      title: '确认上门取件安排',
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
      title: `满${orderSubmitConfig.value.platform_delivery.free_shipping_min_count}台可预约上门取件`,
      icon: 'none'
    })
    return false
  }

  try {
    const res = await checkExpressEnabled(platformDeliveryForm.value.pickup_time_selected ? platformDeliveryForm.value.pickup_time : '') as ExpressCheckResponse
    if (res.code !== 1 || !res.data) {
      uni.showToast({ title: '暂未确认取件服务，请稍后重试', icon: 'none' })
      return false
    }

    if (!(res.data.pickup_enabled ?? res.data.enabled)) {
      uni.showToast({
        title: res.data.unavailable_reason || '门店暂未开启上门取件',
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
    if (applyPickupPolicy(res.data)) {
      uni.showToast({ title: '原取件时段已不可用，请确认页面上的新时段后再下单', icon: 'none', duration: 3000 })
      return false
    }
    if (pickupTimeSupported.value && !platformDeliveryForm.value.pickup_time) {
      uni.showToast({ title: '暂未取得取件时段，请刷新后重试或联系门店', icon: 'none' })
      return false
    }
    const paymentMessage = paymentTips.value || '运费由谁承担，请先联系门店确认。'
    const timeMessage = platformDeliveryForm.value.pickup_time_text
      ? `预约时段：${platformDeliveryForm.value.pickup_time_text}，实际上门时间以快递员联系为准。` : ''
    return await showPlatformDeliveryMemoConfirm([memo, `运费说明：${paymentMessage}`, timeMessage].filter(Boolean).join('\n\n'))
  } catch (error) {
    console.error('获取平台快递提示信息失败：', error)
    uni.showToast({ title: '暂未确认取件服务，请稍后重试或联系门店', icon: 'none' })
    return false
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
const prepareAndSubmitOrder = async () => {
  const modeKey = currentTab.value === 0 ? 'mail' : (currentTab.value === 1 ? 'self' : 'logistics_vehicle')
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
    logisticsVehicleForm: logisticsVehicleForm.value,
    isAgreeRecycle: isAgreeRecycle.value,
    formRef: formRef.value,
    onSuccess: () => {
      if (currentTab.value === 2) uni.setStorageSync(LOGISTICS_CACHE_KEY, logisticsVehicleForm.value)
      // 清空表单
      resetForm()
      showRemark.value = false
      phoneList.value = []
      resetPlatformDeliveryForm()
      isAgreeRecycle.value = false
      applyDefaultCount()
    }
  })
}

const handleSubmitOrder = async () => {
  if (preparingSubmit.value || submitting.value) return
  preparingSubmit.value = true
  try {
    await prepareAndSubmitOrder()
  } finally {
    preparingSubmit.value = false
  }
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
              url: '/addon/hsx_recycle/pages/payment/index'
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
    url: '/addon/hsx_recycle/pages/order/list'
  })
}

// 页面显示时的处理
onShow(async () => {
  restoreLogisticsVehicleForm()
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
@import './order-ui.scss';
.recycle-order-page { @include recycle-order-page; }
.order-page-content {
  --recycle-order-gutter: 16px;
  padding: 0 16px calc(240rpx + 50px + env(safe-area-inset-bottom));
  box-sizing: border-box;
}
.delivery-sticky { position: sticky; z-index: 98; padding: 0 var(--recycle-order-gutter); margin-bottom: var(--recycle-order-section-gap); background: var(--recycle-bg-card); border-bottom: 1rpx solid var(--recycle-line); }
.order-section { padding: 20px var(--recycle-order-gutter) 16px; background: var(--recycle-bg-card); }
.shipment-header { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; min-height: 76rpx; margin-bottom: 16px; }
.shipment-title { min-width: 0; font-size: 30rpx; line-height: 42rpx; font-weight: 600; }
.order-note-toggle { display: flex; align-items: center; gap: 10rpx; min-height: 72rpx; color: var(--recycle-text-sub); font-size: 24rpx; }
.order-note-row { display: flex; align-items: flex-start; gap: 20rpx; padding-top: 4rpx; }
.order-note-label { flex-shrink: 0; width: 112rpx; padding-top: 12rpx; color: var(--recycle-text-sub); font-size: 26rpx; line-height: 40rpx; }
.order-note-input { flex: 1; min-width: 0; }
.order-note-input :deep(.u-textarea) { padding: 16rpx; border-radius: 8rpx; background: var(--recycle-bg-soft); }
.order-note-input :deep(.u-textarea__field) { color: var(--recycle-text-main); font-size: 26rpx; line-height: 38rpx; }
.submit-bar { position: fixed; left: 0; right: 0; bottom: calc(50px + env(safe-area-inset-bottom)); z-index: 90; padding: 12rpx var(--recycle-order-gutter) 20rpx; background: var(--recycle-toolbar-bg); border-top: 1rpx solid var(--recycle-line); }
.submit-bar__action { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; padding-top: 8rpx; }
.submit-bar__summary { display: flex; align-items: baseline; gap: 14rpx; font-size: 24rpx; color: var(--recycle-text-sub); }
.submit-bar__count { color: var(--recycle-text-main); font-size: 34rpx; font-weight: 600; }
.submit-bar__count > text { font-size: 24rpx; font-weight: 400; }
.submit-bar__action :deep(.u-button) { min-height: 84rpx !important; padding-left: 30rpx !important; padding-right: 30rpx !important; }
.submit-bar :deep(.agreement-checkbox) { background: transparent; }
.submit-bar :deep(.agreement-checkbox__content) { font-size: 23rpx; line-height: 34rpx; margin-left: 8rpx; }
.submit-bar :deep(.agreement-checkbox__text), .submit-bar :deep(.agreement-checkbox__link) { padding: 6rpx 0; font-weight: 400; }
:deep(.u-form) { background: transparent; }
:deep(.u-button--primary) { background: var(--recycle-button-bg); border-color: var(--recycle-button-bg); color: var(--recycle-button-text); }
</style>
