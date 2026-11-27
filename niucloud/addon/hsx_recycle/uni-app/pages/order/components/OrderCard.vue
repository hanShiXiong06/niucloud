<template>
  <view class="order-card">
    <view class="order-header" @tap="handleViewDetail">
      <view class="order-delivery"><DeliveryIcon :type="order.delivery_type" /><text>{{ order.delivery_type_name }}</text><text class="order-count">{{ order.count }} 台</text></view>
      <OrderStatusBadge :text="order.status_name || statusInfo.text" :color="statusInfo.color" :bgColor="statusInfo.bgColor" />
    </view>
    <text class="order-time">{{ order.create_at }}</text>
    <OrderDeviceList v-if="order.devices && order.devices.length" :devices="order.devices" :expanded="expanded" @toggle="toggleExpand" />
    <view v-else-if="!(isMailOrder && order.pickup)" class="empty-devices" @tap="handleViewDetail"><text>{{ emptyDeviceTip }}</text><up-icon name="arrow-right" size="13" color="var(--recycle-text-sub)" /></view>
    <view v-if="isMailOrder && order.express_no" class="express-row" @tap="openExpressTracking">
      <DeliveryIcon type="1" /><text class="express-number">{{ order.express_no }}</text><text class="express-link">查物流</text><up-icon name="arrow-right" size="12" color="var(--recycle-text-sub)" />
    </view>
    <view v-if="isLogisticsVehicle" class="vehicle-summary">
      <text>{{ [order.logistics_name, order.logistics_vehicle_no].filter(Boolean).join(' · ') || '物流车辆待补充' }}</text>
      <text>{{ order.logistics_pickup_address || '取货地点待补充' }}</text>
      <text v-if="order.logistics_eta_at">预计 {{ formatEta(order.logistics_eta_at) }} 可取</text>
    </view>
    <PickupStatusCard v-if="isMailOrder && order.pickup" :order-id="Number(order.id)" :info="order.pickup" compact @view-detail="handleViewDetail" />
    <view v-if="order.cancel_reason" class="order-note order-note--danger"><text>取消原因：{{ order.cancel_reason }}</text></view>
    <view class="order-meta-toggle" @tap="showMetadata = !showMetadata"><text>{{ showMetadata ? '收起订单资料' : '订单资料' }}</text><up-icon :name="showMetadata ? 'arrow-up' : 'arrow-down'" size="12" color="var(--recycle-text-sub)" /></view>
    <view v-if="showMetadata" class="order-metadata">
      <view class="order-no"><text selectable>{{ order.order_no }}</text><OrderUiButton variant="text" @click="handleCopyOrderNo">复制</OrderUiButton></view>
      <text v-if="order.remark">备注：{{ order.remark }}</text>
    </view>
    <OrderActions :order="order" :busy="actionBusy" @view-detail="handleViewDetail" @cancel="handleCancel" @confirm="handleConfirm" @delete="handleDelete" />
    <ExpressTrackingModal v-model:visible="showExpressModal" :expressNo="order.express_no || ''" :mobile="expressMobile" />
  </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { OrderListItem } from '../../../types/order'
import { useOrderStatus } from '../../../hooks/useOrderStatus'
import { useOrderActions } from '../../../hooks/useOrderActions'
import { getExpress } from '../../../api/order'
import DeliveryIcon from './DeliveryIcon.vue'
import OrderUiButton from './OrderUiButton.vue'
import OrderStatusBadge from './OrderStatusBadge.vue'
import OrderDeviceList from './OrderDeviceList.vue'
import OrderActions from './OrderActions.vue'
import ExpressTrackingModal from './ExpressTrackingModal.vue'
import PickupStatusCard from './PickupStatusCard.vue'
import { normalizePickup } from '../../../utils/pickup'

interface Props {
  order: OrderListItem
}

interface ExpressSummaryPayload {
  theLastMessage?: string
  logisticsStatusDesc?: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'action-success': [action: string]
}>()

const { getStatusInfo, getDeliveryTypeColor } = useOrderStatus()
const { cancelOrder, confirmOrder, deleteOrder, viewOrderDetail, copyOrderNo, copyExpressNo } = useOrderActions()

const expanded = ref(false)
const showMetadata = ref(false)
const actionBusy = ref(false)
const showExpressModal = ref(false)
const latestExpressMessage = ref('')

const statusInfo = computed(() => getStatusInfo(props.order.status))
const deliveryColor = computed(() => getDeliveryTypeColor(props.order.delivery_type))
const isMailOrder = computed(() => String(props.order.delivery_type) === '1')
const isLogisticsVehicle = computed(() => String(props.order.delivery_type) === '3')
const formatEta = (value: number) => {
  const date = new Date(Number(value || 0) * 1000)
  const pad = (number: number) => String(number).padStart(2, '0')
  return `${date.getMonth() + 1}月${date.getDate()}日 ${pad(date.getHours())}:${pad(date.getMinutes())}`
}
// 快递查询手机号：优先用客户登录手机号（member.mobile），兜底下单填写的 customer_phone
const expressMobile = computed(() => props.order.member?.mobile || props.order.customer_phone || '')

const isRecord = (value: unknown): value is Record<string, unknown> => {
  return typeof value === 'object' && value !== null
}

const extractExpressSummary = (response: unknown): ExpressSummaryPayload => {
  if (!isRecord(response)) return {}

  const level1 = response.data
  const level2 = isRecord(level1) ? level1.data : undefined
  const level3 = isRecord(level2) ? level2.data : undefined

  const candidates: unknown[] = [response, level1, level2, level3]

  for (const candidate of candidates) {
    if (!isRecord(candidate)) continue

    const theLastMessage =
      typeof candidate.theLastMessage === 'string' ? candidate.theLastMessage.trim() : ''
    const logisticsStatusDesc =
      typeof candidate.logisticsStatusDesc === 'string' ? candidate.logisticsStatusDesc.trim() : ''

    if (theLastMessage || logisticsStatusDesc) {
      return {
        theLastMessage,
        logisticsStatusDesc
      }
    }
  }

  return {}
}

const loadExpressSummary = async () => {
  if (!isMailOrder.value || Number(props.order.status) !== 1 || !props.order.express_no) {
    latestExpressMessage.value = ''
    return
  }

  try {
    const res = await getExpress(props.order.express_no, expressMobile.value)
    if (res.code !== 1) {
      latestExpressMessage.value = ''
      return
    }

    const summary = extractExpressSummary(res)
    latestExpressMessage.value = summary.theLastMessage || summary.logisticsStatusDesc || ''
  } catch (error) {
    latestExpressMessage.value = ''
    console.error('获取物流摘要失败:', error)
  }
}

watch(
  () => [props.order.delivery_type, props.order.express_no, props.order.status],
  () => {
    loadExpressSummary()
  },
  { immediate: true }
)

const emptyDeviceTip = computed(() => {
  const status = Number(props.order.status)
  const isMail = isMailOrder.value

  // 仅“刚下单”阶段显示物流信息
  if (isMail && status === 1) {
    if (props.order.pickup) return normalizePickup(props.order.pickup).message
    if (latestExpressMessage.value) return latestExpressMessage.value
    if (props.order.express_no) return '已登记运单，可点击物流号右侧 > 查看实际物流进度'
    return '回收订单已提交，取件安排尚未确认，请联系门店核实'
  }

  // 有进度后不再展示物流动态，改为订单进度提示
  switch (status) {
    case 1:
      return '订单已提交，请按预约时间到店交付设备'
    case 2:
    case 3:
      return '商家已收件，正在质检设备'
    case 4:
    case 5:
      return '设备已质检，请确认报价'
    case 6:
      return '价格已确认，等待商家打款'
    case 7:
      return '订单已完成'
    case 8:
      return '订单已关闭'
    case 9:
      return '订单已取消'
    default:
      return '订单处理中，请稍后刷新'
  }
})

const toggleExpand = () => {
  expanded.value = !expanded.value
}

const handleCopyOrderNo = () => {
  copyOrderNo(props.order.order_no)
}

const handleCopyExpressNo = () => {
  if (!props.order.express_no) return
  copyExpressNo(props.order.express_no)
}

const openExpressTracking = () => {
  if (!props.order.express_no) return
  showExpressModal.value = true
}

const handleViewDetail = () => {
  viewOrderDetail(props.order)
}

const handleCancel = async () => {
  if (actionBusy.value) return
  actionBusy.value = true
  try {
    const success = await cancelOrder(props.order)
    if (success) emit('action-success', 'cancel')
  } finally { actionBusy.value = false }
}

const handleConfirm = async () => {
  if (actionBusy.value) return
  actionBusy.value = true
  try {
    const success = await confirmOrder(props.order)
    if (success) emit('action-success', 'confirm')
  } finally { actionBusy.value = false }
}

const handleDelete = async () => {
  if (actionBusy.value) return
  actionBusy.value = true
  try {
    const success = await deleteOrder(props.order)
    if (success) emit('action-success', 'delete')
  } finally { actionBusy.value = false }
}
</script>

<style scoped lang="scss">
.order-card { padding: 32rpx; margin: 0 12px var(--recycle-order-section-gap, 12px); border: 1rpx solid var(--recycle-line); border-radius: 16rpx; background: var(--recycle-bg-card); }
.order-header, .order-delivery { display: flex; align-items: center; gap: 12rpx; }
.order-header { justify-content: space-between; flex-wrap: wrap; }
.order-delivery { color: var(--recycle-text-main); font-size: 26rpx; font-weight: 500; }
.order-count { font-size: 24rpx; color: var(--recycle-text-sub); font-weight: 400; }
.order-time { display: block; margin: 8rpx 0 16rpx; font-size: 22rpx; line-height: 34rpx; color: var(--recycle-text-sub); }
.empty-devices { display: flex; align-items: center; gap: 16rpx; padding: 12rpx 0 20rpx; color: var(--recycle-text-sub); font-size: 25rpx; line-height: 38rpx; }
.empty-devices > text { flex: 1; min-width: 0; }
.express-row { display: flex; align-items: center; gap: 10rpx; padding: 16rpx 0; border-top: 1rpx solid var(--recycle-line); color: var(--recycle-text-sub); font-size: 24rpx; }
.express-number { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.express-link { color: var(--recycle-brand); flex-shrink: 0; }
.vehicle-summary { display: flex; flex-direction: column; gap: 6rpx; padding: 16rpx 0; font-size: 24rpx; line-height: 36rpx; color: var(--recycle-text-sub); }
.order-note { font-size: 24rpx; line-height: 36rpx; overflow-wrap: anywhere; padding: 12rpx 0; }
.order-note--danger { color: #b91c1c; }
.order-meta-toggle { display: flex; align-items: center; gap: 8rpx; min-height: 60rpx; font-size: 23rpx; color: var(--recycle-text-sub); }
.order-metadata { font-size: 24rpx; line-height: 36rpx; color: var(--recycle-text-sub); }
.order-no { display: flex; align-items: center; gap: 12rpx; }
.order-no > text { min-width: 0; flex: 1; overflow-wrap: anywhere; }
</style>
