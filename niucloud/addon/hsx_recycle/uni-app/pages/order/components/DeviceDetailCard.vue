<template>
  <view class="bg-white rounded-lg shadow-sm overflow-hidden mb-2">
    <!-- 设备头部 -->
    <view class="p-3 flex items-start relative">
      <!-- 选择框 -->
      <view class="mr-2 mt-0.5" @tap.stop="$emit('toggle-select')">
        <up-checkbox :checked="isSelected" shape="square"></up-checkbox>
      </view>

      <!-- 设备信息 -->
      <view class="flex-1 pr-16">
        <!-- 设备名称 -->
        <view class="mb-1">
          <text class="text-sm font-medium text-gray-800">
            <text class="text-blue-500">{{ index + 1 }}.</text>
            {{ device.model || '待识别' }}
          </text>
        </view>

        <!-- IMEI -->
        <view class="flex items-center mb-1.5" @longpress="handleCopyIMEI">
          <text class="text-xs text-gray-500">IMEI: {{ device.imei }}</text>
          <view
            class="ml-1 w-4 h-4 flex items-center justify-center rounded bg-gray-100"
            @tap.stop="handleCopyIMEI"
          >
            <up-icon name="file-text" size="10" color="#94a3b8"></up-icon>
          </view>
        </view>

        <!-- 价格信息 -->
        <view class="flex items-center gap-3 text-xs">
          <view v-if="device.initial_price && device.initial_price !== '0.00'" class="flex items-center gap-0.5">
            <text class="text-gray-400">预估</text>
            <text class="text-orange-500 font-medium">¥{{ device.initial_price }}</text>
          </view>
          <view v-if="device.final_price && device.final_price !== '0.00'" class="flex items-center gap-0.5">
            <text class="text-gray-400">最终</text>
            <text class="font-bold" style="color: #ff6b00;">¥{{ device.final_price }}</text>
          </view>
          <text v-if="!device.final_price || device.final_price === '0.00'" class="text-gray-400">
            待定价
          </text>
        </view>
      </view>

      <!-- 状态标签 -->
      <view
        class="absolute top-0 right-0 text-xs py-1 px-2 rounded-bl-lg"
        :style="{ color: statusColor, background: statusBg }"
      >
        {{ device.status_name }}
      </view>
    </view>

    <view v-if="hasCostAdjustment" class="px-3 pb-2">
      <view class="cost-adjust-notice">
        <view class="cost-adjust-notice__main">
          <text class="cost-adjust-notice__title">商家已调整最终回收成本</text>
          <text class="cost-adjust-notice__desc">
            当前成本 ¥{{ formatMoney(device.final_price) }}，累计调整 {{ formatSignedMoney(device.cost_adjust_amount) }}。如涉及差额，请以商家沟通结果为准。
          </text>
        </view>
      </view>
    </view>

    <!-- 验机报告入口 -->
    <view v-if="inspectionVisible" class="px-3 pb-2">
      <view class="inspection-entry" @tap.stop="$emit('view-report')">
        <view class="inspection-icon">
          <up-icon name="file-text" size="16" color="#2563eb"></up-icon>
        </view>
        <view class="inspection-main">
          <view class="inspection-title-row">
            <text class="inspection-title">验机报告</text>
            <text v-if="styledBadgeCount" class="inspection-warning">{{ styledBadgeCount }}项标识</text>
            <text v-else class="inspection-normal">已生成</text>
          </view>
          <text class="inspection-desc">{{ reportSummary }}</text>
        </view>
        <up-icon name="arrow-right" size="16" color="#cbd5e1"></up-icon>
      </view>
    </view>

    <!-- 代卖信息入口 -->
    <view v-if="allowViewConsignment" class="px-3 pb-2">
      <view class="consignment-entry" @tap.stop="$emit('view-consignment')">
        <view class="consignment-icon">
          <up-icon name="order" size="16" color="#4f46e5"></up-icon>
        </view>
        <view class="consignment-main">
          <view class="consignment-title-row">
            <text class="consignment-title">已转代卖</text>
            <text class="consignment-status">{{ consignmentStatusText }}</text>
          </view>
          <text class="consignment-desc">{{ consignmentSummary }}</text>
        </view>
        <up-icon name="arrow-right" size="16" color="#cbd5e1"></up-icon>
      </view>
    </view>

    <!-- 操作按钮 -->
    <view v-if="showActions" class="action-grid px-3 py-2 border-t border-gray-50">
      <!-- #ifdef MP-WEIXIN -->
      <button
        v-if="canUserDecide && useWechatContact"
        class="action-btn"
        style="background: linear-gradient(135deg, #14b8a6, #0d9488);"
        open-type="contact"
      >
        <up-icon name="chat-fill" size="13" color="#fff" class="mr-1"></up-icon>
        议价
      </button>
      <button
        v-else-if="canUserDecide"
        class="action-btn"
        style="background: linear-gradient(135deg, #14b8a6, #0d9488);"
        @tap.stop="$emit('negotiate')"
      >
        <up-icon name="chat-fill" size="13" color="#fff" class="mr-1"></up-icon>
        议价
      </button>
      <!-- #endif -->
      <!-- #ifndef MP-WEIXIN -->
      <button
        v-if="canUserDecide"
        class="action-btn"
        style="background: linear-gradient(135deg, #14b8a6, #0d9488);"
        @tap.stop="$emit('negotiate')"
      >
        <up-icon name="chat-fill" size="13" color="#fff" class="mr-1"></up-icon>
        议价
      </button>
      <!-- #endif -->
      <button
        v-if="allowApplyConsignment && canUserDecide"
        class="action-btn"
        style="background: linear-gradient(135deg, #6366f1, #2563eb);"
        @tap.stop="$emit('apply-consignment')"
      >
        <up-icon name="order" size="13" color="#fff" class="mr-1"></up-icon>
        申请代卖
      </button>
      <button
        v-if="allowViewConsignment"
        class="action-btn"
        style="background: linear-gradient(135deg, #4f46e5, #2563eb);"
        @tap.stop="$emit('view-consignment')"
      >
        <up-icon name="order" size="13" color="#fff" class="mr-1"></up-icon>
        查看代卖
      </button>
      <button
        v-if="allowRejectSale && canUserDecide"
        class="action-btn"
        style="background: linear-gradient(135deg, #f97316, #ef4444);"
        @tap.stop="$emit('reject-sale')"
      >
        <up-icon name="close" size="13" color="#fff" class="mr-1"></up-icon>
        拒绝出售
      </button>
      <button
        v-if="canUserDecide"
        class="action-btn"
        style="background: linear-gradient(135deg, #f472b6, #ec4899);"
        @tap.stop="$emit('confirm')"
      >
        <up-icon name="checkmark" size="13" color="#fff" class="mr-1"></up-icon>
        确认价格
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { OrderDetailDevice } from '../../../types/order'
import { copyIMEI } from '../../../utils/clipboard'
import { getDeviceStatusInfo } from '../../../utils/theme'

interface Props {
  device: OrderDetailDevice
  index: number
  isSelected: boolean
  allowRejectSale?: boolean
  allowApplyConsignment?: boolean
  allowViewConsignment?: boolean
  useWechatContact?: boolean
  showInspectionResult?: boolean
  showInspectionImages?: boolean
}

const props = defineProps<Props>()

defineEmits<{
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

// 质检结果：优先取 check_result_seller，fallback 到 check_result
const checkResult = computed(() => {
  return props.device.check_result_seller || props.device.check_result || ''
})

const finalPrice = computed(() => Number(props.device.final_price || 0))
const hasFinalPrice = computed(() => Number.isFinite(finalPrice.value) && finalPrice.value > 0)
const canUserDecide = computed(() => hasFinalPrice.value && [4, 7].includes(Number(props.device.status)))
const showActions = computed(() => canUserDecide.value || props.allowViewConsignment)
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
  copyIMEI(props.device.imei)
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
.action-grid {
  display: flex;
  flex-wrap: wrap;
  padding-left: 18rpx;
  padding-right: 18rpx;
  box-sizing: border-box;
}

.action-btn {
  width: 48%;
  height: 64rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 24rpx;
  line-height: 1;
  margin: 7rpx 1%;
  padding: 0 14rpx;
  box-sizing: border-box;
}

.action-btn::after {
  border: 0;
}

.inspection-entry {
  padding: 18rpx;
  border-radius: 16rpx;
  background: #f8fafc;
  border: 1rpx solid #eef2f7;
  display: flex;
  align-items: center;
}

.inspection-icon {
  width: 52rpx;
  height: 52rpx;
  border-radius: 14rpx;
  background: #eff6ff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-right: 16rpx;
}

.inspection-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  margin-right: 12rpx;
}

.inspection-title-row {
  display: flex;
  align-items: center;
  margin-bottom: 8rpx;
}

.inspection-title {
  font-size: 26rpx;
  font-weight: 600;
  color: #0f172a;
}

.inspection-normal,
.inspection-warning {
  padding: 3rpx 10rpx;
  border-radius: 999rpx;
  font-size: 20rpx;
  margin-left: 12rpx;
}

.inspection-normal {
  color: #059669;
  background: #dcfce7;
}

.inspection-warning {
  color: #ea580c;
  background: #ffedd5;
}

.inspection-desc {
  font-size: 22rpx;
  color: #64748b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.consignment-entry {
  padding: 18rpx;
  border-radius: 16rpx;
  background: #f8f7ff;
  border: 1rpx solid #e5e7ff;
  display: flex;
  align-items: center;
}

.consignment-icon {
  width: 52rpx;
  height: 52rpx;
  border-radius: 14rpx;
  background: #eef2ff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-right: 16rpx;
}

.consignment-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  margin-right: 12rpx;
}

.consignment-title-row {
  display: flex;
  align-items: center;
  margin-bottom: 8rpx;
}

.consignment-title {
  font-size: 26rpx;
  font-weight: 600;
  color: #1e1b4b;
}

.consignment-status {
  padding: 3rpx 10rpx;
  border-radius: 999rpx;
  font-size: 20rpx;
  color: #4f46e5;
  background: #e0e7ff;
  margin-left: 12rpx;
}

.consignment-desc {
  font-size: 22rpx;
  color: #64748b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cost-adjust-notice {
  margin-top: 14rpx;
  padding: 16rpx;
  border-radius: 14rpx;
  background: #fffbeb;
  border: 1rpx solid #fde68a;
}

.cost-adjust-notice__main {
  display: flex;
  flex-direction: column;
}

.cost-adjust-notice__title {
  font-size: 23rpx;
  font-weight: 600;
  color: #92400e;
}

.cost-adjust-notice__desc {
  margin-top: 6rpx;
  font-size: 21rpx;
  line-height: 1.5;
  color: #a16207;
}
</style>
