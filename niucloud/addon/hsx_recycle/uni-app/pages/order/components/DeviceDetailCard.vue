<template>
  <view class="device-detail-card" :class="{ 'device-detail-card--selected': isSelected }">
    <view class="device-heading">
      <view v-if="selectionMode" class="device-select">
        <up-checkbox-group><up-checkbox :checked="isSelected" activeColor="var(--recycle-brand)" @change="$emit('toggle-select')" /></up-checkbox-group>
      </view>
      <view class="device-heading__copy">
        <text class="device-model">{{ device.model || '待识别设备' }}</text>
        <text v-if="deviceSpec" class="device-spec">{{ deviceSpec }}</text>
      </view>
      <OrderStatusBadge :text="device.status_name || getDeviceStatusInfo(device.status).text" :color="statusColor" :bgColor="statusBg" />
    </view>
    <view class="device-serial"><text>{{ device.user_sn ? '用户串号' : 'IMEI' }}</text><text selectable>{{ device.user_sn || device.imei || '暂未登记' }}</text><OrderUiButton v-if="device.user_sn || device.imei" variant="text" @click="handleCopyIMEI">复制</OrderUiButton></view>
    <view class="device-price">
      <view><text class="price-label">预估价</text><text class="price-value">{{ Number(device.initial_price) > 0 ? '¥' + formatMoney(device.initial_price) : '未报价' }}</text></view>
      <view class="device-price__final"><text class="price-label">最终报价</text><text class="price-value">{{ hasFinalPrice ? '¥' + formatMoney(device.final_price) : '待定价' }}</text></view>
    </view>
    <view v-if="hasCostAdjustment" class="cost-adjust-notice"><text>回收价格有调整</text><text>当前 ¥{{ formatMoney(device.final_price) }}，累计调整 {{ formatSignedMoney(device.cost_adjust_amount) }}。差额处理以商家沟通结果为准。</text></view>
    <view v-if="inspectionVisible" class="device-entry" @tap.stop="$emit('view-report')">
      <up-icon name="file-text" size="19" color="var(--recycle-brand)" />
      <view class="device-entry__copy"><text>验机报告</text><text class="device-entry__summary">{{ reportSummary }}</text></view>
      <text v-if="styledBadgeCount" class="inspection-warning">{{ styledBadgeCount }} 项标识</text>
      <up-icon name="arrow-right" size="14" color="var(--recycle-text-sub)" />
    </view>
    <view v-if="allowViewConsignment" class="device-entry" @tap.stop="$emit('view-consignment')">
      <up-icon name="order" size="19" color="var(--recycle-brand)" />
      <view class="device-entry__copy"><text>代卖进度 · {{ consignmentStatusText }}</text><text class="device-entry__summary">{{ consignmentSummary }}</text></view>
      <up-icon name="arrow-right" size="14" color="var(--recycle-text-sub)" />
    </view>
    <view v-if="canUserDecide" class="device-actions">
      <OrderUiButton v-if="moreActions.length" variant="text" icon="more-dot-fill" :disabled="busy" @click="showMore = true">更多</OrderUiButton>
      <!-- #ifdef MP-WEIXIN -->
      <OrderUiButton v-if="useWechatContact" :disabled="busy" openType="contact">联系议价</OrderUiButton>
      <OrderUiButton v-else :disabled="busy" @click="$emit('negotiate')">联系议价</OrderUiButton>
      <!-- #endif -->
      <!-- #ifndef MP-WEIXIN -->
      <OrderUiButton :disabled="busy" @click="$emit('negotiate')">联系议价</OrderUiButton>
      <!-- #endif -->
      <OrderUiButton variant="primary" :disabled="busy" @click="$emit('confirm')">确认报价</OrderUiButton>
    </view>
    <up-action-sheet :show="showMore" :actions="moreActions" title="设备操作" cancelText="取消" :closeOnClickAction="true" @close="showMore = false" @select="handleMoreAction" />
  </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { OrderDetailDevice } from '../../../types/order'
import OrderUiButton from './OrderUiButton.vue'
import OrderStatusBadge from './OrderStatusBadge.vue'
import { canDecideDevice } from '../../../utils/order-presentation'
import { copyIMEI } from '../../../utils/clipboard'
import { getDeviceStatusInfo } from '../../../utils/theme'

interface Props {
  device: OrderDetailDevice
  index: number
  isSelected: boolean
  selectionMode?: boolean
  busy?: boolean
  allowDecision?: boolean
  allowRejectSale?: boolean
  allowApplyConsignment?: boolean
  allowViewConsignment?: boolean
  useWechatContact?: boolean
  showInspectionResult?: boolean
  showInspectionImages?: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'toggle-select': []
  'confirm': []
  'negotiate': []
  'reject-sale': []
  'apply-consignment': []
  'view-consignment': []
  'view-report': []
}>()

const statusColor = computed(() => getDeviceStatusInfo(props.device.status).color)
const statusBg = computed(() => getDeviceStatusInfo(props.device.status).bgColor)
const deviceSpec = computed(() => (props.device.check_summary || [])
  .filter(item => ['capacity', 'color'].includes(item.field_key) && item.resolved !== false && item.label)
  .map(item => item.label + (item.unit && !item.label.endsWith(item.unit) ? item.unit : ''))
  .join(' · '))

// 质检结果：优先取 check_result_seller，fallback 到 check_result
const checkResult = computed(() => {
  return props.device.check_result_seller || props.device.check_result || ''
})

const finalPrice = computed(() => Number(props.device.final_price || 0))
const hasFinalPrice = computed(() => Number.isFinite(finalPrice.value) && finalPrice.value > 0)
const canUserDecide = computed(() => props.allowDecision !== false && canDecideDevice(props.device))
const showMore = ref(false)
const moreActions = computed(() => {
  if (!canUserDecide.value) return []
  const actions: Array<{ name: string; action: 'apply-consignment' | 'reject-sale'; color?: string }> = []
  if (props.allowApplyConsignment) actions.push({ name: '咨询代卖', action: 'apply-consignment' })
  if (props.allowRejectSale) actions.push({ name: '拒绝出售', action: 'reject-sale', color: '#dc2626' })
  return actions
})
const handleMoreAction = (item: { action: 'apply-consignment' | 'reject-sale' }) => {
  showMore.value = false
  if (props.busy || !moreActions.value.some(action => action.action === item.action)) return
  if (item.action === 'reject-sale') emit('reject-sale')
  else emit('apply-consignment')
}
const hasCostAdjustment = computed(() => Number(props.device.cost_adjust_count || 0) > 0)

const imageCount = computed(() => {
  const raw = props.device.check_images_seller || props.device.check_images
  if (!raw) return 0
  return raw.split(',').map(s => s.trim()).filter(Boolean).length
})

const normalizeInfo = (value: any) => {
  if (!value) return {}
  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value)
      return parsed && typeof parsed === 'object' ? parsed : {}
    } catch (error) {
      return {}
    }
  }
  return typeof value === 'object' ? value : {}
}

const structuredResultItems = computed(() => {
  const meta = normalizeInfo(normalizeInfo(props.device.info).check_meta)
  return Array.isArray(meta.result_items) ? meta.result_items : []
})

const reportSegments = computed(() => {
  if (structuredResultItems.value.length) return structuredResultItems.value.map((item: any) => item.text || item.field_name || '').filter(Boolean)
  if (!checkResult.value) return []
  return checkResult.value
    .replace(/\r\n/g, '\n')
    .split(/[;\n；|]+/)
    .map(item => item.trim())
    .filter(Boolean)
})

const warningPattern = /(划痕|损坏|异常|故障|维修|更换|进水|有锁|账号锁|不可|坏|严重|漏液|破|裂|老化|低于|不通过|缺失|失灵|暗病|花屏|烧屏|磕碰|变形|拆修|无面容|面容异常|指纹异常)/i
const normalPattern = /^(无|正常|良好|通过|完好|无划痕|无异常|未发现异常)$/

const styledCount = computed(() => {
  if (structuredResultItems.value.length) {
    return structuredResultItems.value.filter((item: any) => {
      const style = item?.style || {}
      const optionStyles = item?.option_styles || {}
      return !!(style.text_color || style.background_color || style.border_color || Object.keys(optionStyles).length)
    }).length
  }
  return reportSegments.value.filter(item => warningPattern.test(item) && !normalPattern.test(item)).length
})

const hasInspectionReport = computed(() => {
  return !!checkResult.value || !!props.device.remark || !!props.device.price_remark || !!props.device.check_at || imageCount.value > 0
})
// 质检结果 / 质检图片 可分别控制，未传时默认显示
const showResult = computed(() => props.showInspectionResult !== false)
const showImages = computed(() => props.showInspectionImages !== false)
// 两者都关闭时隐藏验机报告入口
const inspectionVisible = computed(() => (showResult.value || showImages.value) && hasInspectionReport.value)
// 入口右上角“N项标识”仅在显示质检结果时有意义
const styledBadgeCount = computed(() => (showResult.value ? styledCount.value : 0))

const reportSummary = computed(() => {
  const parts: string[] = []
  if (showResult.value && reportSegments.value.length) parts.push(`${reportSegments.value.length}项检测`)
  if (showImages.value && imageCount.value) parts.push(`${imageCount.value}张图片`)
  if (props.device.price_remark || props.device.remark) parts.push('含价格说明')
  return parts.length ? parts.join(' · ') : '查看检测明细'
})

const consignmentStatusText = computed(() => {
  return props.device.consignmentOrder?.status_name || '代卖中'
})

const consignmentSummary = computed(() => {
  const no = props.device.consignmentOrder?.consignment_no
  return no ? `代卖单号：${no}` : '查看代卖进度、成交与结算信息'
})

const handleCopyIMEI = () => {
  copyIMEI(props.device.user_sn || props.device.imei)
}

const formatMoney = (value: any) => {
  const num = Number(value || 0)
  return Number.isFinite(num) ? num.toFixed(2) : '0.00'
}

const formatSignedMoney = (value: any) => {
  const num = Number(value || 0)
  if (!Number.isFinite(num) || num === 0) return '¥0.00'
  return `${num > 0 ? '+' : '-'}¥${Math.abs(num).toFixed(2)}`
}

</script>

<style scoped lang="scss">
.device-detail-card { padding: 24rpx; margin-bottom: 16rpx; border: 1rpx solid var(--recycle-line); border-radius: 16rpx; background: var(--recycle-bg-card); }
.device-detail-card--selected { border-color: var(--recycle-brand); }
.device-heading { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 12rpx; }
.device-select { padding-top: 4rpx; }
.device-heading__copy { flex: 1; min-width: 220rpx; }
.device-model { display: block; font-size: 30rpx; line-height: 42rpx; font-weight: 600; overflow-wrap: anywhere; }
.device-spec { display: block; font-size: 24rpx; line-height: 36rpx; color: var(--recycle-text-sub); margin-top: 4rpx; }
.device-serial { display: flex; align-items: center; gap: 10rpx; font-size: 23rpx; line-height: 34rpx; color: var(--recycle-text-sub); padding: 4rpx 0; }
.device-serial > text:first-child { flex-shrink: 0; }
.device-serial > text:nth-child(2) { min-width: 0; overflow-wrap: anywhere; }
.device-price { display: flex; gap: 24rpx; padding: 16rpx 0 24rpx; }
.device-price > view { min-width: 0; flex: 1; }
.device-price__final { text-align: right; }
.price-label { display: block; font-size: 23rpx; line-height: 34rpx; color: var(--recycle-text-sub); }
.price-value { display: block; margin-top: 6rpx; font-size: 28rpx; line-height: 42rpx; overflow-wrap: anywhere; }
.device-price__final .price-value { color: var(--recycle-price); font-size: 36rpx; font-weight: 600; }
.device-entry { display: flex; align-items: center; gap: 12rpx; padding: 20rpx 0; border-top: 1rpx solid var(--recycle-line); }
.device-entry__copy { flex: 1; min-width: 0; font-size: 26rpx; line-height: 38rpx; }
.device-entry__summary { display: block; color: var(--recycle-text-sub); font-size: 23rpx; line-height: 34rpx; overflow-wrap: anywhere; }
.inspection-warning { font-size: 22rpx; color: #b45309; flex-shrink: 0; }
.device-actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 12rpx; border-top: 1rpx solid var(--recycle-line); padding-top: 20rpx; }
.cost-adjust-notice { padding: 16rpx; margin-bottom: 20rpx; border-radius: 8rpx; background: var(--recycle-notice-bg); color: var(--recycle-notice-text); font-size: 23rpx; line-height: 36rpx; }
.cost-adjust-notice > text { display: block; }
</style>
