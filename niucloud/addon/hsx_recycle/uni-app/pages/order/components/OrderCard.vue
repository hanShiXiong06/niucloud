<template>
  <view class="order-card">
    <view class="order-header">
      <view class="header-top">
        <view class="order-no-wrap">
          <text class="text-xs text-gray-500">订单号:</text>
          <text class="text-xs font-medium text-gray-800">{{ order.order_no }}</text>
          <up-icon name="cut" size="14" color="#94a3b8" @click="handleCopyOrderNo"></up-icon>
        </view>
        <OrderStatusBadge
          :text="statusInfo.text"
          :color="statusInfo.color"
          :bgColor="statusInfo.bgColor"
        />
      </view>

      <view
        v-if="isMailOrder && order.express_no"
        class="express-row"
        @tap="openExpressTracking"
      >
        <view class="express-main">
          <up-icon name="car" size="14" color="#3b82f6"></up-icon>
          <text class="text-sm font-medium text-gray-800">{{ order.express_no }}</text>
        </view>
        <view class="express-actions">
          <view class="arrow-btn" @tap.stop="openExpressTracking">
            <up-icon name="arrow-right" size="14" color="#3b82f6"></up-icon>
          </view>
        </view>
      </view>

      <view class="meta-row">
        <view class="flex items-center gap-2">
          <text class="text-xs text-gray-400">{{ order.create_at }}</text>
          <view class="delivery-tag" :style="{ color: deliveryColor }">
            <text>{{ order.delivery_type_name }}</text>
          </view>
        </view>
        <text class="text-xs text-gray-500">共 {{ order.count }} 台</text>
      </view>
    </view>

    <view class="order-content">
      <OrderDeviceList
        v-if="order.devices && order.devices.length"
        :devices="order.devices"
        :expanded="expanded"
        @toggle="toggleExpand"
      />
      <view v-else class="empty-devices">
        <text class="text-xs text-gray-500">{{ emptyDeviceTip }}</text>
      </view>

      <view v-if="order.remark" class="mt-2 text-xs text-gray-500">
        <text>备注: {{ order.remark }}</text>
      </view>

      <view v-if="order.cancel_reason" class="mt-2 text-xs text-red-500">
        <text>取消原因: {{ order.cancel_reason }}</text>
      </view>
    </view>

    <OrderActions
      :order="order"
      @view-detail="handleViewDetail"
      @cancel="handleCancel"
      @confirm="handleConfirm"
      @delete="handleDelete"
    />

    <ExpressTrackingModal
      v-model:visible="showExpressModal"
      :expressNo="order.express_no || ''"
      :mobile="order.customer_phone"
    />
  </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { OrderListItem } from '../../../types/order'
import { useOrderStatus } from '../../../hooks/useOrderStatus'
import { useOrderActions } from '../../../hooks/useOrderActions'
import { getExpress } from '../../../api/order'
import OrderStatusBadge from './OrderStatusBadge.vue'
import OrderDeviceList from './OrderDeviceList.vue'
import OrderActions from './OrderActions.vue'
import ExpressTrackingModal from './ExpressTrackingModal.vue'

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
const showExpressModal = ref(false)
const latestExpressMessage = ref('')

const statusInfo = computed(() => getStatusInfo(props.order.status))
const deliveryColor = computed(() => getDeliveryTypeColor(props.order.delivery_type))
const isMailOrder = computed(() => String(props.order.delivery_type) === '1')

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
    const res = await getExpress(props.order.express_no, props.order.customer_phone || '')
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
    if (latestExpressMessage.value) return latestExpressMessage.value
    if (props.order.express_no) return '快递员待揽件，可点击物流号右侧 > 查看物流'
    return '订单已提交，等待平台分配快递员上门'
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
  const success = await cancelOrder(props.order)
  if (success) emit('action-success', 'cancel')
}

const handleConfirm = async () => {
  const success = await confirmOrder(props.order)
  if (success) emit('action-success', 'confirm')
}

const handleDelete = async () => {
  const success = await deleteOrder(props.order)
  if (success) emit('action-success', 'delete')
}
</script>

<style scoped lang="scss">
.order-card {
  background: var(--recycle-bg-card);
  border: 1rpx solid var(--recycle-line);
  border-radius: 16rpx;
  padding: 12px;
  margin: 8px 12px;
  box-shadow: 0 8rpx 20rpx rgba(31, 41, 55, 0.06);
}

.order-header {
  padding-bottom: 8px;
  border-bottom: 1px solid var(--recycle-line);
}

.header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.order-no-wrap {
  display: flex;
  align-items: center;
  gap: 4px;
}

.express-row {
  margin-top: 6px;
  padding: 6px 8px;
  background: var(--recycle-bg-soft);
  border-radius: 10rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.express-main {
  display: flex;
  align-items: center;
  gap: 6px;
}

.express-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.arrow-btn {
  font-size: 24rpx;
  line-height: 1;
  color: var(--recycle-brand);
  font-weight: 700;
}

.meta-row {
  margin-top: 6px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.order-content {
  padding-top: 8px;
}

.empty-devices {
  padding: 12px;
  text-align: center;
  background: var(--recycle-bg-soft);
  border-radius: 12rpx;
  border: 1px dashed var(--recycle-line);
}

.delivery-tag {
  display: inline-flex;
  align-items: center;
  padding: 1px 6px;
  background: var(--recycle-bg-soft);
  border-radius: 8px;
  font-size: 10px;
  font-weight: 500;
}
</style>
