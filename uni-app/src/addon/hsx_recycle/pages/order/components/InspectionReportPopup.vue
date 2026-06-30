<template>
  <up-popup
    :show="visible"
    mode="bottom"
    round="20"
    :close-on-click-overlay="true"
    @close="handleClose"
  >
    <view class="inspection-popup">
      <view class="popup-handle"></view>

      <view class="report-head">
        <view class="report-title-wrap">
          <view class="report-icon">
            <up-icon name="file-text" size="20" color="#2563eb"></up-icon>
          </view>
          <view class="report-title-main">
            <text class="report-title">验机报告</text>
            <text class="report-subtitle">{{ device?.model || '待识别设备' }}</text>
          </view>
        </view>
        <view class="close-btn" @tap="handleClose">
          <up-icon name="close" size="18" color="#64748b"></up-icon>
        </view>
      </view>

      <scroll-view scroll-y class="report-scroll">
        <view class="report-summary">
          <view class="summary-item">
            <text class="summary-label">设备状态</text>
            <text class="summary-value">{{ device?.status_name || '-' }}</text>
          </view>
          <view class="summary-item">
            <text class="summary-label">最终报价</text>
            <text class="summary-value price">¥{{ finalPrice }}</text>
          </view>
          <view class="summary-item full">
            <text class="summary-label">IMEI</text>
            <text class="summary-value mono">{{ device?.imei || '-' }}</text>
          </view>
          <view v-if="device?.check_at" class="summary-item full">
            <text class="summary-label">检测时间</text>
            <text class="summary-value">{{ formatTime(device.check_at) }}</text>
          </view>
        </view>

        <view class="section report-section" v-if="resultVisible && reportItems.length">
          <view class="report-section-head">
            <text class="section-title">检测明细</text>
            <text class="report-count">{{ styledCount ? `${reportItems.length} 项 / ${styledCount} 项重点` : `${reportItems.length} 项` }}</text>
          </view>
          <view class="report-table">
            <view
              v-for="(item, index) in reportItems"
              :key="`${item.label}-${index}`"
              class="report-row"
            >
              <view class="report-label-cell">
                <text class="report-index">{{ formatIndex(index) }}</text>
                <text class="report-label">{{ item.label }}</text>
              </view>
              <view class="report-result-cell">
                <text
                  v-for="option in getDisplayOptions(item)"
                  :key="`${option.value}-${option.label}`"
                  :class="['result-chip', option.style ? 'result-chip-custom' : '']"
                  :style="toStyle(option.style)"
                >{{ option.label }}</text>
              </view>
            </view>
          </view>
        </view>

        <view class="section" v-else-if="resultVisible">
          <view class="empty-report">
            <up-icon name="info-circle" size="22" color="#94a3b8"></up-icon>
            <text>暂无结构化检测结果</text>
          </view>
        </view>

        <view v-if="device?.remark || device?.price_remark" class="section">
          <view class="section-head">
            <text class="section-title">价格说明</text>
          </view>
          <view class="remark-box" v-if="device?.remark">
            <text class="remark-label">扣费说明</text>
            <text class="remark-content">{{ device.remark }}</text>
          </view>
          <view class="remark-box" v-if="device?.price_remark">
            <text class="remark-label">报价说明</text>
            <text class="remark-content">{{ device.price_remark }}</text>
          </view>
        </view>

        <view v-if="imagesVisible && imageList.length" class="section last">
          <view class="section-head">
            <text class="section-title">验机图片</text>
            <text class="section-count">{{ imageList.length }} 张</text>
          </view>
          <view class="image-grid">
            <view
              v-for="(imageUrl, index) in imageThumbList"
              :key="index"
              class="image-cell"
              @tap="previewImage(index)"
            >
              <image :src="img(imageUrl)" mode="aspectFill" class="report-image" />
            </view>
          </view>
        </view>
      </scroll-view>
    </view>
  </up-popup>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { OrderDetailDevice } from '../../../types/order'
import { img, timeStampTurnTime } from '@/utils/common'

const props = defineProps<{
  visible: boolean
  device?: OrderDetailDevice | null
  showResult?: boolean
  showImages?: boolean
}>()

// 质检结果（检测明细）与质检图片可分别控制，未传时默认显示
const resultVisible = computed(() => props.showResult !== false)
const imagesVisible = computed(() => props.showImages !== false)

const emit = defineEmits<{
  close: []
}>()

interface ReportItem {
  label: string
  value: string
  values: string[]
  labels: string[]
  options: ReportOptionItem[]
  text: string
  style?: ReportStyle
  optionStyles?: Record<string, ReportStyle>
  hasStyle: boolean
}

interface ReportOptionItem {
  value: string
  label: string
  style?: ReportStyle
}

interface ReportStyle {
  text_color?: string
  background_color?: string
  border_color?: string
}

const rawResult = computed(() => props.device?.check_result_seller || props.device?.check_result || '')

const finalPrice = computed(() => {
  const value = Number(props.device?.final_price || 0)
  return value.toFixed(2)
})

const splitReportSegments = (value: string) => {
  return value
    .replace(/\r\n/g, '\n')
    .split(/[;\n；|]+/)
    .map(item => item.trim())
    .filter(Boolean)
}

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

const normalizeStyle = (style: any): ReportStyle | undefined => {
  if (!style || typeof style !== 'object') return undefined
  const normalized = {
    text_color: style.text_color || '',
    background_color: style.background_color || style.bg_color || '',
    border_color: style.border_color || ''
  }
  if (!normalized.text_color && !normalized.background_color && !normalized.border_color) return undefined
  return normalized
}

const checkMeta = computed(() => normalizeInfo(normalizeInfo(props.device?.info).check_meta))

const structuredReportItems = computed<ReportItem[]>(() => {
  const items = Array.isArray(checkMeta.value?.result_items) ? checkMeta.value.result_items : []
  return items
    .map((item: any) => {
      const labels = Array.isArray(item.labels) ? item.labels.map((label: any) => String(label)).filter(Boolean) : []
      const values = Array.isArray(item.values)
        ? item.values.map((value: any) => String(value)).filter(Boolean)
        : (Array.isArray(item.value) ? item.value.map((value: any) => String(value)).filter(Boolean) : [String(item.value || '')].filter(Boolean))
      const text = String(item.text || '').trim()
      const value = labels.length ? labels.join('、') : (text || String(item.value || ''))
      const style = normalizeStyle(item.style)
      const optionStyles = item.option_styles && typeof item.option_styles === 'object' ? item.option_styles : {}
      const normalizedOptionStyles = Object.keys(optionStyles).reduce<Record<string, ReportStyle>>((map, key) => {
        const optionStyle = normalizeStyle(optionStyles[key])
        if (optionStyle) map[key] = optionStyle
        return map
      }, {})
      const optionItemsSource = Array.isArray(item.option_items) ? item.option_items : []
      const options = optionItemsSource.length
        ? optionItemsSource.map((option: any, index: number) => ({
          value: String(option.value ?? values[index] ?? ''),
          label: String(option.label ?? labels[index] ?? option.value ?? ''),
          style: normalizeStyle(option.style)
        })).filter((option: ReportOptionItem) => option.label)
        : labels.map((label: string, index: number) => {
          const optionValue = values[index] || ''
          return {
            value: optionValue,
            label,
            style: normalizedOptionStyles[optionValue]
          }
        })
      return {
        label: item.field_name || item.field_key || '检测项',
        value,
        values,
        labels,
        options,
        text,
        style: options.length > 1 ? undefined : style,
        optionStyles: normalizedOptionStyles,
        hasStyle: !!style || options.some((option: ReportOptionItem) => !!option.style) || Object.keys(normalizedOptionStyles).length > 0
      }
    })
    .filter((item: ReportItem) => item.value || item.text)
})

const parseReportItem = (segment: string): ReportItem => {
  const normalized = segment.replace(/：/g, ':')
  const parts = normalized.split(':')
  const label = (parts.length > 1 ? parts.shift() : '检测项')?.trim() || '检测项'
  const value = (parts.length > 0 ? parts.join(':') : normalized).trim() || '-'

  return { label, value, values: value === '-' ? [] : [value], labels: value === '-' ? [] : [value], options: [], text: normalized, hasStyle: false }
}

const reportItems = computed(() => {
  return structuredReportItems.value.length ? structuredReportItems.value : splitReportSegments(rawResult.value).map(parseReportItem)
})

const styledCount = computed(() => reportItems.value.filter(item => item.hasStyle).length)

const formatIndex = (index: number) => {
  return index + 1 < 10 ? `0${index + 1}` : String(index + 1)
}

const toStyle = (style?: ReportStyle) => {
  if (!style) return ''
  const parts: string[] = []
  if (style.text_color) parts.push(`color:${style.text_color}`)
  if (style.background_color) parts.push(`background-color:${style.background_color}`)
  if (style.border_color || style.background_color) parts.push(`border-color:${style.border_color || style.background_color}`)
  return parts.join(';')
}

const getDisplayOptions = (item: ReportItem): ReportOptionItem[] => {
  if (item.options.length) return item.options
  return [{
    value: item.values[0] || item.value,
    label: item.value || '-',
    style: item.style
  }]
}

const imageList = computed(() => {
  const raw = props.device?.check_images_seller || props.device?.check_images || ''
  return raw.split(',').map(item => item.trim()).filter(Boolean)
})

const imageThumbList = computed(() => {
  const thumbs = props.device?.check_images_seller_thumb_small
  return Array.isArray(thumbs) && thumbs.length ? thumbs : imageList.value
})

const handleClose = () => {
  emit('close')
}

const previewImage = (current: number) => {
  const urls = imageList.value.map(item => img(item))
  uni.previewImage({
    urls,
    current: urls[current]
  })
}

const formatTime = (timestamp: number) => timeStampTurnTime(timestamp)
</script>

<style scoped lang="scss">
.inspection-popup {
  height: 78vh;
  max-height: 1120rpx;
  background: #f6f8fb;
  border-radius: 32rpx 32rpx 0 0;
  overflow: hidden;
}

.popup-handle {
  width: 72rpx;
  height: 8rpx;
  margin: 18rpx auto 8rpx;
  border-radius: 999rpx;
  background: #cbd5e1;
}

.report-head {
  margin: 18rpx 28rpx 22rpx;
  padding: 0 24rpx;
  border-radius: 18rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border-bottom: 1rpx solid #eef2f7;
}

.report-title-wrap {
  display: flex;
  align-items: center;
  min-width: 0;
}

.report-icon {
  width: 64rpx;
  height: 64rpx;
  border-radius: 18rpx;
  background: #eff6ff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-right: 18rpx;
}

.report-title-main {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.report-title {
  margin-bottom: 8rpx;
}

.report-title {
  font-size: 34rpx;
  font-weight: 700;
  color: #0f172a;
}

.report-subtitle {
  max-width: 480rpx;
  font-size: 24rpx;
  color: #64748b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.close-btn {
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.report-scroll {
  height: calc(78vh - 150rpx);
  box-sizing: border-box;
}

.report-summary,
.section,
.report-note {
  margin: 20rpx 24rpx 0;
  background: #fff;
  border-radius: 18rpx;
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.report-summary {
  padding: 24rpx;
  display: flex;
  flex-wrap: wrap;
  margin-left: 24rpx;
  margin-right: 24rpx;
  box-sizing: border-box;
}

.summary-item {
  width: 50%;
  min-width: 0;
  padding: 0 10rpx 20rpx;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
}

.summary-item.full {
  width: 100%;
}

.summary-label {
  margin-bottom: 8rpx;
  font-size: 22rpx;
  color: #94a3b8;
}

.summary-value {
  font-size: 28rpx;
  font-weight: 600;
  color: #0f172a;
  word-break: break-all;
}

.summary-value.price {
  color: #f97316;
}

.summary-value.mono {
  font-family: DIN Alternate, Arial, sans-serif;
}

.section {
  padding: 24rpx;
}

.report-section {
  padding: 24rpx 20rpx 26rpx;
}

.section.last {
  margin-bottom: 20rpx;
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18rpx;
}

.report-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 6rpx 18rpx;
  border-bottom: 1rpx solid #edf2f7;
  margin-bottom: 0;
}

.section-title {
  font-size: 28rpx;
  font-weight: 700;
  color: #0f172a;
}

.section-count,
.report-count {
  font-size: 22rpx;
  color: #94a3b8;
  margin-left: 16rpx;
  flex-shrink: 0;
}

.report-table {
  display: flex;
  flex-direction: column;
  padding: 4rpx 6rpx 0;
  background: #fff;
}

.report-row + .report-row {
  border-top: 1rpx solid #f1f5f9;
}

.report-row {
  padding: 24rpx 0 20rpx;
  background: #fff;
  display: flex;
  align-items: flex-start;
}

.report-label-cell {
  width: 196rpx;
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  padding-right: 18rpx;
  box-sizing: border-box;
}

.report-index {
  width: 38rpx;
  font-size: 20rpx;
  line-height: 34rpx;
  color: #cbd5e1;
  font-weight: 700;
  flex-shrink: 0;
}

.report-label {
  flex: 1;
  font-size: 24rpx;
  line-height: 34rpx;
  font-weight: 600;
  color: #334155;
  word-break: break-all;
}

.report-result-cell {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.result-chip {
  display: inline-flex;
  align-items: center;
  min-height: 42rpx;
  padding: 5rpx 16rpx;
  border: 1rpx solid #dbeafe;
  border-radius: 8rpx;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 22rpx;
  font-weight: 500;
  line-height: 1.25;
  margin: 0 0 10rpx 10rpx;
  word-break: break-all;
}

.result-chip-custom {
  box-shadow: none;
}

.remark-box {
  padding: 18rpx;
  border-radius: 14rpx;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
}

.remark-box + .remark-box {
  margin-top: 14rpx;
}

.remark-label {
  margin-bottom: 8rpx;
  font-size: 22rpx;
  color: #94a3b8;
}

.remark-content {
  font-size: 26rpx;
  line-height: 1.55;
  color: #334155;
}

.image-grid {
  display: flex;
  flex-wrap: wrap;
  margin: -6rpx;
}

.image-cell {
  width: 25%;
  height: 156rpx;
  padding: 6rpx;
  box-sizing: border-box;
}

.report-image {
  width: 100%;
  height: 100%;
  border-radius: 14rpx;
  overflow: hidden;
  background: #f1f5f9;
}

.empty-report {
  min-height: 160rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-report text {
  margin-top: 12rpx;
  font-size: 26rpx;
  color: #94a3b8;
}

.report-note {
  margin-bottom: calc(28rpx + env(safe-area-inset-bottom));
  padding: 20rpx 24rpx;
  background: #eef6ff;
  box-shadow: none;
}

.report-note text {
  font-size: 22rpx;
  line-height: 1.5;
  color: #4b5563;
}
</style>
