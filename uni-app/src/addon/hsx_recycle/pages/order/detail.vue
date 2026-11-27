<template>
  <page-meta :page-style="popupPageStyle" />
  <uni-layout name="default">
  <view class="recycle-order-detail-page" :style="themeVars">
    <RecyclePageHeader title="订单详情" />
    <view v-if="loading && isEmpty" class="detail-loading"><up-skeleton rows="4" title :loading="true" :animate="true" /><up-skeleton rows="5" title :loading="true" :animate="true" /></view>
    <view v-else-if="isEmpty" class="detail-empty">
      <up-empty mode="order" :text="loadError ? '订单暂时无法加载' : '没有找到这笔订单'" />
      <text class="detail-empty__message">{{ loadError || '请检查订单是否存在，或返回订单列表。' }}</text>
      <view class="detail-empty__actions"><OrderUiButton @click="goBack">返回列表</OrderUiButton><OrderUiButton v-if="loadError" variant="primary" @click="retryDetail">重新加载</OrderUiButton></view>
    </view>
    <view v-else class="detail-content">
      <view v-if="loadError" class="detail-error" @tap="retryDetail"><text>{{ loadError }}</text><text>重试</text></view>
      <view class="detail-card"><OrderStatusProgress :status="orderInfo.status" :statusName="orderInfo.status_name" /></view>
      <view class="detail-card"><OrderDetailHeader :orderNo="orderInfo.order_no" :deviceCount="orderInfo.devices.length" :totalPrice="totalPrice" :expressNo="orderInfo.express_no" :mobile="orderInfo.member?.mobile || orderInfo.customer_phone || ''" :createTime="orderInfo.create_at" :deliveryName="orderInfo.delivery_type_name" :remark="orderInfo.remark" :cancelReason="orderInfo.cancel_reason" /></view>

      <view class="order-device-section">
        <view class="detail-section-heading"><view><text>设备明细</text><text class="detail-section-count">{{ orderInfo.devices.length }} 台</text></view><OrderUiButton v-if="!hasNoDevices" variant="text" @click="toggleSelectionMode">{{ selectionMode ? '完成' : '批量操作' }}</OrderUiButton></view>
        <view v-if="hasNoDevices" class="order-empty-devices"><up-icon name="clock" size="24" color="var(--recycle-text-sub)" /><text>暂无设备明细</text><text>门店登记设备后，将在这里显示质检和报价信息。</text></view>
        <DeviceBatchToolbar v-if="selectionMode && !hasNoDevices" :isAllSelected="isAllSelected" :selectedCount="selectedCount" @toggle-all="toggleSelectAll" @copy-imei="copySelectedIMEIs" />
        <DeviceDetailCard v-for="(device, index) in orderInfo.devices" :key="device.id" :device="device" :index="index" :isSelected="isDeviceSelected(device.id)" :selectionMode="selectionMode" :busy="decisionBusy || loading" :allowDecision="Number(orderInfo.status) >= 4 && !orderFinished" :showInspectionResult="showInspectionResult" :showInspectionImages="showInspectionImages" :allowRejectSale="submitConfig.allow_user_reject_sale !== 0" :allowApplyConsignment="canApplyConsignment(device)" :allowViewConsignment="canViewConsignment(device)" :useWechatContact="customerServiceEnabled && customerServiceType === 'wechat'" @toggle-select="toggleDeviceSelection(device.id)" @confirm="handleDeviceConfirm(device)" @negotiate="openCustomerService" @reject-sale="handleDeviceRejectSale(device)" @apply-consignment="handleApplyConsignment(device)" @view-consignment="handleViewConsignment(device)" @view-report="openInspectionReport(device)" />
      </view>

      <view v-if="String(orderInfo.delivery_type) === '1'" class="detail-delivery-section"><PickupStatusCard :order-id="Number(orderInfo.id)" :info="orderInfo.pickup" @updated="loadOrderDetail(orderInfo.id)" @contact="contactPickupShop" /></view>
      <view v-if="String(orderInfo.delivery_type) === '3'" class="detail-section">
        <view class="detail-section-heading"><view><DeliveryIcon type="3" /><text>物流车交付</text></view></view>
        <view class="detail-info-row"><text>车辆</text><text>{{ [orderInfo.logistics_name, orderInfo.logistics_vehicle_no].filter(Boolean).join(' · ') || '待补充' }}</text></view>
        <view class="detail-info-row"><text>取货地点</text><text>{{ orderInfo.logistics_pickup_address || '待补充' }}</text></view>
        <view class="detail-info-row"><text>现场联系</text><text>{{ [orderInfo.logistics_contact_name, orderInfo.logistics_contact_mobile].filter(Boolean).join(' · ') || '待补充' }}</text></view>
        <view v-if="orderInfo.logistics_eta_at" class="detail-info-row"><text>预计可取</text><text>{{ formatEta(orderInfo.logistics_eta_at) }}</text></view>
      </view>

      <view v-if="customerServiceEnabled || urgeEnabled || hasReturnOrder" class="detail-section detail-support">
        <view v-if="hasReturnOrder" class="support-row" @tap="goToReturnOrder(orderInfo.id)"><up-icon name="reload" size="20" color="var(--recycle-text-sub)" /><text>退回信息</text><text class="support-row__aside">{{ returnOrderList.length }} 条</text><up-icon name="arrow-right" size="14" color="var(--recycle-text-sub)" /></view>
        <view v-if="customerServiceEnabled" class="support-row">
          <up-icon name="server-man" size="20" color="var(--recycle-text-sub)" /><text>{{ customerServiceConfig.title || '联系客服' }}</text>
          <!-- #ifdef MP-WEIXIN -->
          <OrderUiButton v-if="customerServiceType === 'wechat'" variant="text" openType="contact">联系</OrderUiButton>
          <OrderUiButton v-else variant="text" @click="openCustomerService">联系</OrderUiButton>
          <!-- #endif -->
          <!-- #ifndef MP-WEIXIN -->
          <OrderUiButton variant="text" @click="openCustomerService">联系</OrderUiButton>
          <!-- #endif -->
        </view>
        <view v-if="urgeEnabled" class="support-row"><up-icon name="bell" size="20" color="var(--recycle-text-sub)" /><view class="support-row__copy"><text>催办订单</text><text v-if="urgeOnCooldown" class="support-row__hint">{{ urgeCooldownText }}</text></view><OrderUiButton variant="text" :loading="urging" :disabled="urgeOnCooldown" @click="handleUrgeOrder">{{ urgeOnCooldown ? '已提醒' : '提醒处理' }}</OrderUiButton></view>
      </view>

      <view v-if="selectionMode && selectedActionableCount > 0 && Number(orderInfo.status) >= 4 && !orderFinished" class="order-confirm-bar">
        <text class="order-confirm-bar__summary">可确认 {{ selectedActionableCount }} 台</text>
        <OrderUiButton variant="primary" :loading="decisionBusy" :disabled="loading" @click="handleConfirmSelected">确认选中报价</OrderUiButton>
      </view>
      <CustomerServicePopup :visible="showCustomerServicePopup" :qr-code="customerServiceConfig.qrcode || ''" :title="customerServiceConfig.title" :content="customerServiceConfig.content" @close="showCustomerServicePopup = false" />
      <InspectionReportPopup :visible="showInspectionReport" :device="currentReportDevice" :showResult="showInspectionResult" :showImages="showInspectionImages" @close="closeInspectionReport" />
    </view>
  </view>
  </uni-layout>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { useRecyclePopupPage } from '../../hooks/useRecyclePopupScroll'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useOrderDetail } from '../../hooks/useOrderDetail'
import { useDeviceSelection } from '../../hooks/useDeviceSelection'
import { useReturnOrder } from '../../hooks/useReturnOrder'
import OrderStatusProgress from './components/OrderStatusProgress.vue'
import OrderDetailHeader from './components/OrderDetailHeader.vue'
import PickupStatusCard from './components/PickupStatusCard.vue'
import DeviceBatchToolbar from './components/DeviceBatchToolbar.vue'
import DeviceDetailCard from './components/DeviceDetailCard.vue'
import CustomerServicePopup from './components/CustomerServicePopup.vue'
import InspectionReportPopup from './components/InspectionReportPopup.vue'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import OrderUiButton from './components/OrderUiButton.vue'
import DeliveryIcon from './components/DeliveryIcon.vue'
import { canDecideDevice } from '../../utils/order-presentation'
import { buildRecycleThemeVars } from '../../utils/theme'
import { urgeOrder } from '../../api/order'
import type { OrderDetailDevice } from '../../types/order'

const { popupPageStyle } = useRecyclePopupPage()

const {
  loading, loadError, orderInfo, isEmpty, hasNoDevices, totalPrice,
  submitConfig, loadOrderDetail, confirmDevice, confirmDevices, rejectDeviceSale
} = useOrderDetail()

const { returnOrderList, hasReturnOrder, loadReturnOrders, goToReturnOrder } = useReturnOrder()

const orderId = ref<string | number>('')
const decisionBusy = ref(false)
const selectionMode = ref(false)
const retryDetail = () => { if (orderId.value && !loading.value) loadOrderDetail(orderId.value) }
const toggleSelectionMode = () => {
  selectionMode.value = !selectionMode.value
  resetSelection()
}
const confirmIntent = (content: string) => new Promise<boolean>(resolve => {
  uni.showModal({ title: '确认回收报价', content, confirmText: '确认报价', cancelText: '再看看', success: result => resolve(result.confirm), fail: () => resolve(false) })
})
const devicesRef = computed(() => orderInfo.value.devices)
const formatEta = (value: number) => {
  const date = new Date(Number(value || 0) * 1000)
  const pad = (number: number) => String(number).padStart(2, '0')
  return `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日 ${pad(date.getHours())}:${pad(date.getMinutes())}`
}
const themeVars = computed(() => buildRecycleThemeVars(submitConfig.value?.price_detail_theme?.colors || {}))
const showCustomerServicePopup = ref(false)
const showInspectionReport = ref(false)
const urging = ref(false)
const currentReportDevice = ref<OrderDetailDevice | null>(null)
const customerServiceConfig = computed(() => submitConfig.value?.customer_service || {})
const customerServiceEnabled = computed(() => Number(customerServiceConfig.value?.enabled || 0) === 1)
const contactPickupShop = () => {
  const receiver = orderInfo.value.pickup?.receiver
  const phone = receiver?.mobile || receiver?.phone
  if (phone) {
    uni.makePhoneCall({ phoneNumber: phone })
  } else if (customerServiceEnabled.value) {
    openCustomerService()
  } else {
    uni.showModal({ title: '请联系门店核实', content: '当前页面暂无门店电话，请通过下单门店的现有联系方式确认预约结果。在结果明确前，请勿重复叫件或自行另叫快递。', showCancel: false })
  }
}
const customerServiceType = computed(() => customerServiceConfig.value?.type === 'qrcode' ? 'qrcode' : 'wechat')
const consignmentConfig = computed(() => submitConfig.value?.consignment || {})
const consignmentEntryEnabled = computed(() => {
  return Number(consignmentConfig.value?.enabled || 0) === 1 && Number(consignmentConfig.value?.user_entry_enabled || 0) === 1
})
const consignmentViewEnabled = computed(() => {
  return Number(consignmentConfig.value?.enabled || 0) === 1 && Number(consignmentConfig.value?.user_view_enabled || 0) === 1
})
// 质检结果（检测明细）与质检图片可分别控制，由后台「订单详情页」配置，缺省都显示
const showInspectionResult = computed(() => Number(submitConfig.value?.order_detail?.show_inspection_result ?? 1) !== 0)
const showInspectionImages = computed(() => Number(submitConfig.value?.order_detail?.show_inspection_images ?? 1) !== 0)
// 两者都关闭时，验机报告入口整体隐藏
const showInspectionEntry = computed(() => showInspectionResult.value || showInspectionImages.value)
const workWechatConfig = computed(() => submitConfig.value?.work_wechat || {})
const orderUrgeChannel = computed(() => workWechatConfig.value?.channels?.order_urge || {})
const finishedOrderStatuses = [7, 8, 9, 10]
const orderFinished = computed(() => finishedOrderStatuses.includes(Number(orderInfo.value?.status || 0)))
const urgeEnabled = computed(() => {
  return !orderFinished.value && Number(workWechatConfig.value?.enabled || 0) === 1 && Number(orderUrgeChannel.value?.enabled || 0) === 1
})

// 催办冷却时间：由后台「企业微信群通知 / 订单催办群」配置（小时），缺省 12 小时；0 表示不限制
const urgeCooldownHours = computed(() => {
  const hours = Number(orderUrgeChannel.value?.user_cooldown_hours)
  return Number.isFinite(hours) && hours >= 0 ? hours : 12
})
const urgeCooldownMs = computed(() => urgeCooldownHours.value * 60 * 60 * 1000)
const lastUrgeAt = ref(0)
const urgeStorageKey = (orderId: number | string) => `recycle_order_urge_at_${orderId}`
const loadLastUrgeAt = (orderId: number | string) => {
  lastUrgeAt.value = orderId ? Number(uni.getStorageSync(urgeStorageKey(orderId)) || 0) : 0
}
// 注意：仅依赖 lastUrgeAt 重新计算，时间流逝不会主动刷新，进入页面/点击时会重新核对
const urgeCooldownRemaining = computed(() => {
  if (!lastUrgeAt.value || urgeCooldownMs.value <= 0) return 0
  const remaining = lastUrgeAt.value + urgeCooldownMs.value - Date.now()
  return remaining > 0 ? remaining : 0
})
const urgeOnCooldown = computed(() => urgeCooldownRemaining.value > 0)
const urgeCooldownText = computed(() => {
  if (urgeCooldownRemaining.value <= 0) return ''
  const hours = Math.ceil(urgeCooldownRemaining.value / (60 * 60 * 1000))
  return `${hours}小时后可再次催办`
})

const {
  isAllSelected, selectedCount, isDeviceSelected,
  toggleDeviceSelection, toggleSelectAll, copySelectedIMEIs,
  getSelectedPendingDevices, resetSelection, initSelection
} = useDeviceSelection(devicesRef)
const selectedActionableCount = computed(() => getSelectedPendingDevices().length)

const handleDeviceConfirm = async (device: OrderDetailDevice) => {
  if (decisionBusy.value || loading.value) return
  decisionBusy.value = true
  try {
    if (!await confirmIntent(`确认以 ¥${device.final_price} 回收「${device.model || device.imei}」？`)) return
    const success = await confirmDevice(device)
    if (success) resetSelection()
  } finally { decisionBusy.value = false }
}

const handleDeviceRejectSale = async (device: OrderDetailDevice) => {
  if (decisionBusy.value || loading.value) return
  decisionBusy.value = true
  try {
    const success = await rejectDeviceSale(device)
    if (success) resetSelection()
  } finally { decisionBusy.value = false }
}

const canUserDecideDevice = canDecideDevice

const canApplyConsignment = (device: OrderDetailDevice) => {
  if (!consignmentEntryEnabled.value) return false
  if (!canUserDecideDevice(device)) return false
  if (Number(device.consignment_order_id || 0) > 0) return false
  return true
}

const getConsignmentOrderId = (device: OrderDetailDevice) => {
  return Number(device.consignmentOrder?.id || device.consignment_order_id || 0)
}

const canViewConsignment = (device: OrderDetailDevice) => {
  if (!consignmentViewEnabled.value) return false
  return getConsignmentOrderId(device) > 0
}

const handleApplyConsignment = (device: OrderDetailDevice) => {
  uni.showModal({
    title: '咨询代卖',
    content: `当前设备可申请转入代卖：${device.model || device.imei || ''}。请先联系工作人员确认代卖方案，此操作不会自动转入代卖。`,
    confirmText: customerServiceEnabled.value ? '联系客服' : '知道了',
    cancelText: '取消',
    success: (res) => {
      if (res.confirm && customerServiceEnabled.value) {
        openCustomerService()
      }
    }
  })
}

const handleViewConsignment = (device: OrderDetailDevice) => {
  const consignmentId = getConsignmentOrderId(device)
  if (!consignmentId) {
    uni.showToast({ title: '暂无代卖订单信息', icon: 'none' })
    return
  }
  uni.navigateTo({
    url: `/addon/hsx_recycle/pages/consignment/detail?id=${consignmentId}`
  })
}

const openInspectionReport = (device: OrderDetailDevice) => {
  if (!showInspectionEntry.value) return
  currentReportDevice.value = device
  showInspectionReport.value = true
}

const closeInspectionReport = () => {
  showInspectionReport.value = false
  currentReportDevice.value = null
}

const handleUrgeOrder = async () => {
  if (urging.value || !orderInfo.value.id) return
  if (orderFinished.value) {
    uni.showToast({
      title: '订单已结束，不能再催办',
      icon: 'none'
    })
    return
  }
  // 冷却时间内只能催办一次（以最近一次催办时间为准，实时核对）
  const cooldownMs = urgeCooldownMs.value
  const last = Number(uni.getStorageSync(urgeStorageKey(orderInfo.value.id)) || 0)
  if (cooldownMs > 0 && last && Date.now() - last < cooldownMs) {
    const hours = Math.ceil((last + cooldownMs - Date.now()) / (60 * 60 * 1000))
    lastUrgeAt.value = last
    uni.showToast({
      title: `催办太频繁啦，请${hours}小时后再试`,
      icon: 'none'
    })
    return
  }
  urging.value = true
  try {
    const res = await urgeOrder(Number(orderInfo.value.id))
    const stamp = Date.now()
    uni.setStorageSync(urgeStorageKey(orderInfo.value.id), stamp)
    lastUrgeAt.value = stamp
    uni.showToast({
      title: res?.data?.message || '已提醒工作人员',
      icon: 'none'
    })
  } catch (e: any) {
    uni.showToast({
      title: e?.msg || e?.message || '催办失败，请稍后再试',
      icon: 'none'
    })
  } finally {
    urging.value = false
  }
}

const handleConfirmSelected = async () => {
  if (decisionBusy.value || loading.value) return
  const pendingDevices = getSelectedPendingDevices()
  if (!pendingDevices.length) return
  decisionBusy.value = true
  try {
    const amount = pendingDevices.reduce((sum, device) => sum + Number(device.final_price), 0).toFixed(2)
    if (!await confirmIntent(`确认选中的 ${pendingDevices.length} 台设备报价，合计 ¥${amount}？`)) return
    const success = await confirmDevices(pendingDevices.map(device => device.id))
    if (success) resetSelection()
  } finally { decisionBusy.value = false }
}

const openCustomerService = () => {
  if (!customerServiceEnabled.value) {
    uni.navigateTo({
      url: '/app/pages/member/contact'
    })
    return
  }

  if (customerServiceType.value === 'qrcode' && customerServiceConfig.value?.qrcode) {
    showCustomerServicePopup.value = true
    return
  }

  // #ifdef MP-WEIXIN
  if (customerServiceType.value === 'wechat') return
  // #endif

  uni.navigateTo({
    url: '/app/pages/member/contact'
  })
}

const goBack = () => {
  uni.navigateBack({
    delta: 1,
    fail: () => {
      uni.navigateTo({ url: '/addon/hsx_recycle/pages/order/list' })
    }
  })
}

const goHome = () => {
  uni.reLaunch({ url: '/app/pages/index/index' })
}

watch(() => orderInfo.value.id, (newId) => {
  loadLastUrgeAt(newId || 0)
  if (newId) loadReturnOrders(newId)
})

const getOrderIdFromOptions = (options?: Record<string, any>) => {
  const sceneParams: Record<string, string> = {}
  const scene = options?.scene ? decodeURIComponent(String(options.scene)) : ''
  if (scene) {
    scene.split('&').filter(Boolean).forEach(item => {
      const [key, value = ''] = item.split('=')
      if (key) sceneParams[key] = decodeURIComponent(value)
    })
  }

  return options?.id || options?.order_id || sceneParams.id || sceneParams.order_id
}

onLoad((options?: Record<string, any>) => {
  orderId.value = getOrderIdFromOptions(options)
  if (orderId.value) {
    loadOrderDetail(orderId.value)
    return
  }
  loading.value = false
  goHome()
})

onShow(async () => {
  await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay')
  if (orderInfo.value.devices && orderInfo.value.devices.length > 0) {
    initSelection()
  }
})
</script>

<style scoped lang="scss">
@import './order-ui.scss';
.recycle-order-detail-page { @include recycle-order-page; padding-bottom: calc(120rpx + env(safe-area-inset-bottom)); }
.detail-loading { padding: 32rpx 28rpx; display: flex; flex-direction: column; gap: 48rpx; }
.detail-empty { padding: 120rpx 32rpx; text-align: center; }
.detail-empty__message { display: block; color: var(--recycle-text-sub); font-size: 25rpx; line-height: 40rpx; margin: 24rpx 0; }
.detail-empty__actions { display: flex; justify-content: center; gap: 16rpx; }
.detail-error { display: flex; justify-content: space-between; padding: 20rpx 28rpx; background: var(--recycle-notice-bg); color: var(--recycle-notice-text); font-size: 24rpx; }
.detail-content { padding: var(--recycle-order-section-gap) var(--recycle-order-gutter) 0; }
.detail-card, .detail-section, .detail-delivery-section, .order-device-section { margin-bottom: var(--recycle-order-section-gap); border: 1rpx solid var(--recycle-line); border-radius: 12px; background: var(--recycle-bg-card); }
.detail-card :deep(.order-progress-section) { padding: 16px 12px; border-radius: 12px; }
.detail-card :deep(.order-overview) { margin-bottom: 0; padding: 0 12px; border-radius: 12px; }
.detail-card :deep(.order-overview__amount) { border-top: 0; }
.order-device-section { padding: 0 12px 8rpx; }
.detail-section-heading, .detail-section-heading > view { display: flex; align-items: center; gap: 12rpx; }
.detail-section-heading { min-height: 88rpx; justify-content: space-between; font-size: 29rpx; font-weight: 600; }
.detail-section-count { font-size: 24rpx; font-weight: 400; color: var(--recycle-text-sub); }
.order-empty-devices { display: flex; flex-direction: column; align-items: center; gap: 14rpx; padding: 40rpx 20rpx; text-align: center; color: var(--recycle-text-sub); font-size: 25rpx; line-height: 38rpx; }
.order-empty-devices > text:last-child { font-size: 23rpx; }
.detail-section, .detail-delivery-section { padding: 12px 12px 16px; }
.detail-delivery-section :deep(.pickup-card) { margin: 0; padding: 12rpx 0; border: 0; border-radius: 0; }
.detail-info-row { display: flex; gap: 24rpx; padding: 10rpx 0; font-size: 25rpx; line-height: 38rpx; }
.detail-info-row > text:first-child { width: 120rpx; flex-shrink: 0; color: var(--recycle-text-sub); }
.detail-info-row > text:last-child { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.detail-support { padding-top: 0; padding-bottom: 0; }
.support-row { display: flex; align-items: center; gap: 16rpx; min-height: 96rpx; padding: 8rpx 0; font-size: 26rpx; }
.support-row + .support-row { border-top: 1rpx solid var(--recycle-line); }
.support-row > text:first-of-type, .support-row__copy { flex: 1; }
.support-row__aside, .support-row__hint { color: var(--recycle-text-sub); font-size: 23rpx; }
.support-row__hint { display: block; line-height: 34rpx; }
.order-confirm-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; display: flex; align-items: center; justify-content: space-between; gap: 16rpx; padding: 18rpx var(--recycle-order-gutter) calc(18rpx + env(safe-area-inset-bottom)); background: var(--recycle-toolbar-bg); border-top: 1rpx solid var(--recycle-line); }
.order-confirm-bar__summary { font-size: 25rpx; color: var(--recycle-text-sub); }
</style>
