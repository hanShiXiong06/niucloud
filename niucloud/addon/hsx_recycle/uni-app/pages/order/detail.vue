<template>
  <view class="recycle-order-detail-page" :style="themeVars">
    <RecyclePageHeader title="订单详情" :subtitle="orderInfo.order_no || '查看设备质检和确认状态'" />
    <!-- 骨架屏加载状态 -->
    <view v-if="loading" class="skeleton-container">
      <view class="mx-3 mt-2 rounded-lg overflow-hidden">
        <view class="skeleton-line h-24 w-full mb-0"></view>
        <view class="bg-white p-3">
          <view class="skeleton-line h-3 w-full mb-2"></view>
          <view class="skeleton-line h-3 w-2/3"></view>
        </view>
      </view>
      <view class="bg-white rounded-lg shadow-sm mx-3 mt-3 p-4">
        <view class="skeleton-line h-4 w-1/4 mb-3"></view>
        <view class="skeleton-line h-3 w-full mb-2"></view>
        <view class="skeleton-line h-3 w-3/4 mb-2"></view>
        <view class="skeleton-line h-3 w-1/2"></view>
      </view>
      <view class="mx-3 mt-3">
        <view class="skeleton-line h-4 w-1/4 mb-2"></view>
        <view v-for="i in 2" :key="i" class="bg-white rounded-lg shadow-sm p-3 mb-2">
          <view class="skeleton-line h-3 w-3/4 mb-2"></view>
          <view class="skeleton-line h-3 w-1/2 mb-2"></view>
          <view class="skeleton-line h-3 w-2/3"></view>
        </view>
      </view>
    </view>

    <!-- 空状态 -->
    <view v-else-if="!loading && isEmpty" class="min-h-screen flex items-center justify-center px-10">
      <up-empty
        mode="data"
        icon="http://cdn.uviewui.com/uview/empty/data.png"
        text="暂无订单信息"
        textColor="#999999"
        textSize="15"
      >
        <template #bottom>
          <view class="flex flex-col items-center mt-4">
            <text class="text-sm text-gray-400 mb-4">请耐心等待订单处理</text>
            <up-button type="primary" shape="round" size="normal" @click="goBack">
              返回订单列表
            </up-button>
          </view>
        </template>
      </up-empty>
    </view>

    <!-- 实际内容 -->
    <view v-else>
      <!-- 订单状态（渐变横幅 + 进度条） -->
      <OrderStatusProgress
        :status="orderInfo.status"
        :statusName="orderInfo.status_name"
        :createTime="orderInfo.create_at"
      />

      <!-- 订单信息卡片 -->
      <OrderDetailHeader
        :orderNo="orderInfo.order_no"
        :deviceCount="orderInfo.devices.length"
        :totalPrice="totalPrice"
        :expressNo="orderInfo.express_no"
        :mobile="orderInfo.member?.mobile || orderInfo.customer_phone || ''"
      />

      <!-- 客服入口 -->
      <view v-if="customerServiceEnabled" class="mx-3 mb-3">
        <!-- #ifdef MP-WEIXIN -->
        <button
          v-if="customerServiceType === 'wechat'"
          class="customer-service-card"
          open-type="contact"
        >
          <view class="customer-service-icon">
            <up-icon name="server-man" size="18" color="var(--recycle-brand)"></up-icon>
          </view>
          <view class="customer-service-copy">
            <text class="customer-service-title">{{ customerServiceConfig.title || '联系客服' }}</text>
            <text class="customer-service-desc">{{ customerServiceConfig.content || '如需议价或咨询订单进度，请联系客服处理' }}</text>
          </view>
          <up-icon name="arrow-right" size="16" color="#cbd5e1"></up-icon>
        </button>
        <view v-else class="customer-service-card" @tap="openCustomerService">
          <view class="customer-service-icon">
            <up-icon name="server-man" size="18" color="var(--recycle-brand)"></up-icon>
          </view>
          <view class="customer-service-copy">
            <text class="customer-service-title">{{ customerServiceConfig.title || '联系客服' }}</text>
            <text class="customer-service-desc">{{ customerServiceConfig.content || '长按识别二维码添加工作人员' }}</text>
          </view>
          <up-icon name="arrow-right" size="16" color="#cbd5e1"></up-icon>
        </view>
        <!-- #endif -->
        <!-- #ifndef MP-WEIXIN -->
        <view class="customer-service-card" @tap="openCustomerService">
          <view class="customer-service-icon">
            <up-icon name="server-man" size="18" color="var(--recycle-brand)"></up-icon>
          </view>
          <view class="customer-service-copy">
            <text class="customer-service-title">{{ customerServiceConfig.title || '联系客服' }}</text>
            <text class="customer-service-desc">{{ customerServiceConfig.content || '如需议价或咨询订单进度，请联系客服处理' }}</text>
          </view>
          <up-icon name="arrow-right" size="16" color="#cbd5e1"></up-icon>
        </view>
        <!-- #endif -->
      </view>

      <!-- 催办入口 -->
      <view v-if="urgeEnabled" class="mx-3 mb-3">
        <button class="urge-card" :disabled="urging || urgeOnCooldown" @tap="handleUrgeOrder">
          <view class="urge-icon">
            <up-icon name="bell" size="18" color="#f97316"></up-icon>
          </view>
          <view class="urge-copy">
            <text class="urge-title">催一下</text>
            <text class="urge-desc">{{ urgeOnCooldown ? urgeCooldownText : '提醒工作人员尽快处理当前订单' }}</text>
          </view>
          <view class="urge-action">
            <text>{{ urging ? '发送中' : (urgeOnCooldown ? '稍后再试' : '发送提醒') }}</text>
          </view>
        </button>
      </view>

      <!-- 退货信息入口 -->
      <view v-if="hasReturnOrder" class="mx-3 mb-3">
        <view
          class="bg-white rounded-lg shadow-sm p-3 flex items-center justify-between active:bg-gray-50"
          @tap="goToReturnOrder(orderInfo.id)"
        >
          <view class="flex items-center gap-2">
            <view class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-center">
              <up-icon name="order" size="16" color="#ef4444"></up-icon>
            </view>
            <view>
              <text class="text-sm font-medium text-gray-800">查看退货信息</text>
              <text class="text-xs text-gray-400 ml-1">({{ returnOrderList.length }}条)</text>
            </view>
          </view>
          <up-icon name="arrow-right" size="16" color="#cbd5e1"></up-icon>
        </view>
      </view>

      <!-- 无设备提示 -->
      <view v-if="hasNoDevices" class="mx-3 mt-1">
        <view class="bg-white rounded-lg shadow-sm p-6 flex flex-col items-center">
          <up-icon name="clock" size="40" color="#cbd5e1" class="mb-2"></up-icon>
          <text class="text-sm text-gray-500">订单等待更新</text>
        </view>
      </view>

      <!-- 设备列表 -->
      <view v-else class="mx-3">
        <view class="flex items-center gap-1.5 mb-2">
          <view class="w-1 h-4 rounded bg-orange-500"></view>
          <text class="text-base font-bold text-gray-800">设备列表</text>
          <text class="text-xs text-gray-400">({{ orderInfo.devices.length }}台)</text>
        </view>

        <!-- 批量操作工具栏 -->
        <DeviceBatchToolbar
          v-if="orderInfo.devices && orderInfo.devices.length > 0"
          :isAllSelected="isAllSelected"
          :selectedCount="selectedCount"
          @toggle-all="toggleSelectAll"
          @copy-imei="copySelectedIMEIs"
        />

        <!-- 设备卡片列表 -->
        <DeviceDetailCard
          v-for="(device, index) in orderInfo.devices"
          :key="device.id"
          :device="device"
          :index="index"
          :isSelected="isDeviceSelected(device.id)"
          :showInspectionResult="showInspectionResult"
          :showInspectionImages="showInspectionImages"
          :allowRejectSale="submitConfig.allow_user_reject_sale !== 0"
          :allowApplyConsignment="canApplyConsignment(device)"
          :allowViewConsignment="canViewConsignment(device)"
          :useWechatContact="customerServiceEnabled && customerServiceType === 'wechat'"
          @toggle-select="toggleDeviceSelection(device.id)"
          @confirm="handleDeviceConfirm(device)"
          @negotiate="openCustomerService"
          @reject-sale="handleDeviceRejectSale(device)"
          @apply-consignment="handleApplyConsignment(device)"
          @view-consignment="handleViewConsignment(device)"
          @view-report="openInspectionReport(device)"
        />
      </view>

      <!-- 底部批量确认按钮 -->
      <view
        v-if="selectedActionableCount > 0 && orderInfo.status == 5"
        class="fixed left-0 right-0 bottom-0 bg-white bg-opacity-95 p-3 shadow-up backdrop-blur-sm"
      >
        <button
          class="w-full h-11 rounded-full flex items-center justify-center text-white text-sm font-medium"
          style="background: linear-gradient(135deg, #10b981, #059669);"
          @tap="handleConfirmSelected"
        >
          <up-icon name="checkmark-circle" size="16" color="#fff" class="mr-1"></up-icon>
          确认选中设备 ({{ selectedActionableCount }})
        </button>
      </view>

      <CustomerServicePopup
        :visible="showCustomerServicePopup"
        :qr-code="customerServiceConfig.qrcode || ''"
        :title="customerServiceConfig.title"
        :content="customerServiceConfig.content"
        @close="showCustomerServicePopup = false"
      />
      <InspectionReportPopup
        :visible="showInspectionReport"
        :device="currentReportDevice"
        :showResult="showInspectionResult"
        :showImages="showInspectionImages"
        @close="closeInspectionReport"
      />
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useOrderDetail } from '../../hooks/useOrderDetail'
import { useDeviceSelection } from '../../hooks/useDeviceSelection'
import { useReturnOrder } from '../../hooks/useReturnOrder'
import OrderStatusProgress from './components/OrderStatusProgress.vue'
import OrderDetailHeader from './components/OrderDetailHeader.vue'
import DeviceBatchToolbar from './components/DeviceBatchToolbar.vue'
import DeviceDetailCard from './components/DeviceDetailCard.vue'
import CustomerServicePopup from './components/CustomerServicePopup.vue'
import InspectionReportPopup from './components/InspectionReportPopup.vue'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import { buildRecycleThemeVars } from '../../utils/theme'
import { urgeOrder } from '../../api/order'
import type { OrderDetailDevice } from '../../types/order'

const {
  loading, orderInfo, isEmpty, hasNoDevices, totalPrice,
  submitConfig, loadOrderDetail, confirmDevice, confirmDevices, rejectDeviceSale
} = useOrderDetail()

const { returnOrderList, hasReturnOrder, loadReturnOrders, goToReturnOrder } = useReturnOrder()

const devicesRef = computed(() => orderInfo.value.devices)
const themeVars = computed(() => buildRecycleThemeVars(submitConfig.value?.price_detail_theme?.colors || {}))
const showCustomerServicePopup = ref(false)
const showInspectionReport = ref(false)
const urging = ref(false)
const currentReportDevice = ref<OrderDetailDevice | null>(null)
const customerServiceConfig = computed(() => submitConfig.value?.customer_service || {})
const customerServiceEnabled = computed(() => Number(customerServiceConfig.value?.enabled || 0) === 1)
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

const handleDeviceConfirm = async (device: any) => {
  const success = await confirmDevice(device)
  if (success) resetSelection()
}

const handleDeviceRejectSale = async (device: any) => {
  const success = await rejectDeviceSale(device)
  if (success) resetSelection()
}

const hasDeviceFinalPrice = (device: OrderDetailDevice) => {
  const price = Number(device.final_price || 0)
  return Number.isFinite(price) && price > 0
}

const canUserDecideDevice = (device: OrderDetailDevice) => {
  return hasDeviceFinalPrice(device) && [4, 7].includes(Number(device.status))
}

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
    title: '申请代卖',
    content: `当前设备可申请转入代卖：${device.model || device.imei || ''}。用户端申请接口尚未接入，请联系工作人员确认代卖方案。`,
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
  const pendingDevices = getSelectedPendingDevices()
  const deviceIds = pendingDevices.map(d => d.id)
  const success = await confirmDevices(deviceIds)
  if (success) resetSelection()
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
  const orderId = getOrderIdFromOptions(options)
  if (orderId) {
    loadOrderDetail(orderId)
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

<style lang="scss">
.recycle-order-detail-page {
  min-height: 100vh;
  padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
  background: var(--recycle-bg-main);
  color: var(--recycle-text-main);
}

.skeleton-container {
  animation: skeleton-fade-in 0.3s ease-in-out;
}
.skeleton-line {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: skeleton-loading 1.5s ease-in-out infinite;
  border-radius: 8rpx;
}
@keyframes skeleton-loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
@keyframes skeleton-fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}
.shadow-up {
  box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
}

.customer-service-card {
  width: 100%;
  margin: 0;
  border: 0;
  padding: 24rpx;
  border-radius: 16rpx;
  background: var(--recycle-bg-card);
  box-shadow: 0 2rpx 10rpx rgba(15, 23, 42, 0.06);
  display: flex;
  align-items: center;
  gap: 20rpx;
  text-align: left;
  line-height: 1;
  box-sizing: border-box;
}

.customer-service-card::after {
  border: 0;
}

.urge-card {
  width: 100%;
  margin: 0;
  border: 0;
  padding: 24rpx;
  border-radius: 16rpx;
  background: var(--recycle-bg-card);
  box-shadow: 0 2rpx 10rpx rgba(15, 23, 42, 0.06);
  display: flex;
  align-items: center;
  gap: 20rpx;
  text-align: left;
  line-height: 1;
  box-sizing: border-box;
}

.urge-card::after {
  border: 0;
}

.urge-card[disabled] {
  opacity: 0.72;
}

.urge-icon {
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
  background: rgba(249, 115, 22, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.urge-copy {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 10rpx;
}

.urge-title {
  font-size: 28rpx;
  font-weight: 600;
  color: var(--recycle-text-main);
}

.urge-desc {
  font-size: 24rpx;
  color: var(--recycle-text-sub);
  line-height: 1.4;
}

.urge-action {
  padding: 12rpx 18rpx;
  border-radius: 999rpx;
  background: rgba(249, 115, 22, 0.12);
  color: #f97316;
  font-size: 24rpx;
  flex-shrink: 0;
}

.customer-service-icon {
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
  background: rgba(34, 197, 94, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.customer-service-copy {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 10rpx;
}

.customer-service-title {
  font-size: 28rpx;
  font-weight: 600;
  color: var(--recycle-text-main);
}

.customer-service-desc {
  font-size: 24rpx;
  color: var(--recycle-text-sub);
  line-height: 1.4;
}
</style>
