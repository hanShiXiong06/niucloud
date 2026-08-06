<template>
  <view class="order-card">
    <view class="order-header">
      <view class="header-top">
        <view class="order-title-wrap">
          <view class="order-title-wrap__icon">
            <up-icon name="order" size="17" color="var(--recycle-brand)" />
          </view>
          <view class="order-title-wrap__copy">
            <text class="order-title">回收订单</text>
            <text class="order-time">{{ order.create_at }}</text>
          </view>
        </view>
        <OrderStatusBadge
          :text="statusInfo.text"
          :color="statusInfo.color"
          :bgColor="statusInfo.bgColor"
        />
      </view>

      <view class="order-no-wrap" @longpress="handleCopyOrderNo">
        <text class="order-no-wrap__label">订单号</text>
        <text class="order-no-wrap__value">{{ order.order_no }}</text>
        <view class="order-no-wrap__copy" @tap.stop="handleCopyOrderNo">
          <up-icon name="file-text" size="12" color="#8b96a9" />
        </view>
      </view>

      <view class="meta-row">
        <view class="delivery-tag" :style="{ color: deliveryColor }">
          <view class="delivery-tag__dot" :style="{ backgroundColor: deliveryColor }" />
          <text>{{ order.delivery_type_name }}</text>
        </view>
        <text class="meta-row__count">共 {{ order.count }} 台设备</text>
      </view>

      <view
        v-if="isMailOrder && order.express_no"
        class="express-row"
        @tap="openExpressTracking"
      >
        <view class="express-main">
          <view class="express-main__icon">
            <up-icon name="car" size="15" color="var(--recycle-brand)" />
          </view>
          <view class="express-main__copy">
            <text class="express-main__label">物流单号</text>
            <text class="express-main__value">{{ order.express_no }}</text>
          </view>
        </view>
        <view class="express-actions">
          <view class="express-copy" @tap.stop="handleCopyExpressNo">
            <up-icon name="file-text" size="12" color="#8b96a9" />
          </view>
          <up-icon name="arrow-right" size="14" color="#aab2bf" />
        </view>
      </view>
      <view v-if="isLogisticsVehicle" class="vehicle-summary">
        <view class="vehicle-summary__main">
          <up-icon name="car" size="15" color="var(--recycle-brand)" />
          <text>{{ [order.logistics_name, order.logistics_vehicle_no].filter(Boolean).join(' · ') || '物流车辆待补充' }}</text>
        </view>
        <text class="vehicle-summary__address">{{ order.logistics_pickup_address || '取货地点待补充' }}</text>
        <text v-if="order.logistics_eta_at" class="vehicle-summary__time">预计 {{ formatEta(order.logistics_eta_at) }} 可取</text>
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

      <view v-if="order.remark" class="order-note">
        <text class="order-note__label">备注</text>
        <text class="order-note__content">{{ order.remark }}</text>
      </view>

      <view v-if="order.cancel_reason" class="order-note order-note--danger">
        <text class="order-note__label">取消原因</text>
        <text class="order-note__content">{{ order.cancel_reason }}</text>
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
      :mobile="expressMobile"
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
  padding: 24rpx;
  margin: 0 24rpx 18rpx;
  border: 1rpx solid #e9edf2;
  border-radius: 24rpx;
  background: #fff;
  box-shadow: 0 8rpx 24rpx rgba(31, 41, 55, 0.045);
}

.order-header {
  padding-bottom: 20rpx;
  border-bottom: 1rpx solid #edf0f4;
}

.header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.order-title-wrap {
  display: flex;
  align-items: center;
  gap: 14rpx;
}

.order-title-wrap__icon {
  width: 58rpx;
  height: 58rpx;
  border-radius: 16rpx;
  background: rgba(59, 130, 246, 0.09);
  display: flex;
  align-items: center;
  justify-content: center;
}

.order-title-wrap__copy {
  display: flex;
  flex-direction: column;
}

.order-title {
  color: #172033;
  font-size: 27rpx;
  line-height: 36rpx;
  font-weight: 700;
}

.order-time {
  margin-top: 2rpx;
  color: #9aa4b2;
  font-size: 20rpx;
  line-height: 28rpx;
}

.order-no-wrap {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 10rpx;
}

.order-no-wrap__label {
  color: #9aa4b2;
  font-size: 21rpx;
}

.order-no-wrap__value {
  min-width: 0;
  color: #596579;
  font-size: 22rpx;
  line-height: 32rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.order-no-wrap__copy,
.express-copy {
  width: 36rpx;
  height: 36rpx;
  border-radius: 9rpx;
  background: #f2f4f7;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.express-row {
  margin-top: 16rpx;
  padding: 16rpx;
  background: #f8fafc;
  border: 1rpx solid #edf0f4;
  border-radius: 16rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.express-main {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.express-main__icon {
  width: 48rpx;
  height: 48rpx;
  border-radius: 14rpx;
  background: #edf5ff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.express-main__copy {
  display: flex;
  flex-direction: column;
}

.express-main__label {
  color: #9aa4b2;
  font-size: 19rpx;
  line-height: 26rpx;
}

.express-main__value {
  margin-top: 2rpx;
  color: #4f5c70;
  font-size: 22rpx;
  line-height: 30rpx;
  font-weight: 600;
}

.express-actions {
  display: inline-flex;
  align-items: center;
  gap: 12rpx;
}

.vehicle-summary {
  margin-top: 16rpx;
  padding: 16rpx 18rpx;
  border: 1rpx solid #e4ebf5;
  border-radius: 14rpx;
  background: #f8fbff;
}

.vehicle-summary__main { display: flex; align-items: center; gap: 10rpx; color: #3f4d61; font-size: 23rpx; font-weight: 600; }
.vehicle-summary__address, .vehicle-summary__time { display: block; margin-top: 7rpx; padding-left: 40rpx; color: #7c8798; font-size: 21rpx; line-height: 30rpx; }
.vehicle-summary__time { color: #b45309; }

.meta-row {
  margin-top: 14rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.meta-row__count {
  color: #7c8798;
  font-size: 21rpx;
}

.order-content {
  padding-top: 20rpx;
}

.empty-devices {
  padding: 24rpx;
  text-align: center;
  background: #f8fafc;
  border-radius: 16rpx;
  border: 1rpx dashed #dce2e9;
  color: #8b96a9;
  font-size: 22rpx;
}

.delivery-tag {
  display: inline-flex;
  align-items: center;
  gap: 8rpx;
  font-size: 21rpx;
  line-height: 30rpx;
  font-weight: 600;
}

.delivery-tag__dot {
  width: 10rpx;
  height: 10rpx;
  border-radius: 50%;
}

.order-note {
  margin-top: 14rpx;
  padding: 14rpx 16rpx;
  border-radius: 12rpx;
  background: #f8fafc;
  display: flex;
  align-items: flex-start;
  gap: 12rpx;
  font-size: 21rpx;
  line-height: 32rpx;
}

.order-note__label {
  flex-shrink: 0;
  color: #9aa4b2;
}

.order-note__content {
  color: #687589;
}

.order-note--danger {
  background: #fff5f5;

  .order-note__label,
  .order-note__content {
    color: #d15b5b;
  }
}
</style>
