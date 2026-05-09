<template>
  <up-popup
    :show="visible"
    mode="bottom"
    round="20"
    @close="handleClose"
  >
    <view class="express-modal">
      <!-- 头部 -->
      <view class="modal-header">
        <text class="header-title">物流信息</text>
        <up-icon name="close" size="20" @click="handleClose"></up-icon>
      </view>

      <!-- 快递单号 -->
      <view class="express-info">
        <view class="flex items-center justify-between mb-2">
          <text class="text-sm text-gray-600">快递单号</text>
          <view class="flex items-center gap-1" @tap="handleCopyExpressNo">
            <text class="text-sm font-medium text-gray-800">{{ expressNo }}</text>
            <up-icon name="cut" size="14" color="#94a3b8"></up-icon>
          </view>
        </view>
      </view>

      <!-- 加载状态 -->
      <view v-if="loading" class="loading-container">
        <up-loading-icon mode="circle" size="40"></up-loading-icon>
        <text class="text-sm text-gray-500 mt-2">加载中...</text>
      </view>

      <!-- 物流时间线 -->
      <view v-else-if="trackingList.length > 0" class="tracking-timeline">
        <up-steps
          direction="column"
          :current="0"
          activeColor="#2979ff"
        >
          <up-steps-item
            v-for="(item, index) in trackingList"
            :key="index"
            :title="item.time"
          >
            <template #desc>
              <view class="context-line">
                <template
                  v-for="(segment, segIndex) in parseContextSegments(item.context)"
                  :key="`${index}-${segIndex}`"
                >
                  <text
                    v-if="segment.isPhone"
                    class="phone-btn"
                    @tap="handleCallPhone(segment.phone)"
                  >
                    {{ segment.text }}
                  </text>
                  <text v-else class="context-text">{{ segment.text }}</text>
                </template>
              </view>
            </template>
          </up-steps-item>
        </up-steps>
      </view>

      <!-- 空状态 - 物流信息更新中 -->
      <view v-else class="empty-tracking">
        <up-empty
          mode="data"
          icon="http://cdn.uviewui.com/uview/empty/data.png"
          text="物流信息更新中"
          textColor="#999999"
          textSize="14"
        >
          <template #bottom>
            <text class="text-xs text-gray-500 mt-2">请耐心等待快递员上门</text>
          </template>
        </up-empty>
      </view>
    </view>
  </up-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getExpress } from '../../../api/order'

interface Props {
  visible: boolean
  expressNo: string
  mobile?: string
}

interface TrackingItem {
  time: string
  context: string
}

interface ContextSegment {
  text: string
  isPhone: boolean
  phone: string
}

interface LogisticsTraceRawItem {
  time?: number | string
  timeDesc?: string
  desc?: string
  context?: string
  [key: string]: unknown
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:visible': [value: boolean]
}>()

const loading = ref(false)
const trackingList = ref<TrackingItem[]>([])
const mobilePhoneReg = /1[3-9]\d{9}/g

const isRecord = (value: unknown): value is Record<string, unknown> => {
  return typeof value === 'object' && value !== null
}

const formatTimestamp = (value: number | string): string => {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric <= 0) return ''

  const date = new Date(numeric)
  if (Number.isNaN(date.getTime())) return ''

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')
  const second = String(date.getSeconds()).padStart(2, '0')
  return `${year}-${month}-${day} ${hour}:${minute}:${second}`
}

const extractRawTrackingList = (payload: unknown): LogisticsTraceRawItem[] => {
  if (!isRecord(payload)) return []

  const level1 = payload.data
  const level2 = isRecord(level1) ? level1.data : undefined
  const level3 = isRecord(level2) ? level2.data : undefined

  const candidates: unknown[] = [
    isRecord(payload) ? payload.list : undefined,
    isRecord(level1) ? level1.list : undefined,
    isRecord(level2) ? level2.list : undefined,
    isRecord(level3) ? level3.list : undefined,
    isRecord(payload) ? payload.logisticsTraceDetailList : undefined,
    isRecord(level1) ? level1.logisticsTraceDetailList : undefined,
    isRecord(level2) ? level2.logisticsTraceDetailList : undefined,
    isRecord(level3) ? level3.logisticsTraceDetailList : undefined
  ]

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) {
      return candidate.filter(isRecord) as LogisticsTraceRawItem[]
    }
  }

  return []
}

const normalizeTrackingList = (rawList: LogisticsTraceRawItem[]): TrackingItem[] => {
  return rawList
    .map((item) => {
      const timeText =
        (typeof item.timeDesc === 'string' && item.timeDesc.trim()) ||
        (typeof item.time === 'string' && item.time.trim()) ||
        (typeof item.time === 'number' ? formatTimestamp(item.time) : '')

      const contextText =
        (typeof item.desc === 'string' && item.desc.trim()) ||
        (typeof item.context === 'string' && item.context.trim()) ||
        ''

      return {
        time: timeText || '--',
        context: contextText || '--'
      }
    })
    .filter(item => item.time !== '--' || item.context !== '--')
}

const parseContextSegments = (context: string): ContextSegment[] => {
  const content = (context || '').trim()
  if (!content) {
    return [{ text: '--', isPhone: false, phone: '' }]
  }

  const segments: ContextSegment[] = []
  let lastIndex = 0
  mobilePhoneReg.lastIndex = 0

  let match: RegExpExecArray | null = null
  while ((match = mobilePhoneReg.exec(content)) !== null) {
    const phone = match[0]
    const start = match.index

    if (start > lastIndex) {
      segments.push({
        text: content.slice(lastIndex, start),
        isPhone: false,
        phone: ''
      })
    }

    segments.push({
      text: phone,
      isPhone: true,
      phone
    })

    lastIndex = start + phone.length
  }

  if (lastIndex < content.length) {
    segments.push({
      text: content.slice(lastIndex),
      isPhone: false,
      phone: ''
    })
  }

  return segments.length ? segments : [{ text: content, isPhone: false, phone: '' }]
}

// 监听弹窗显示，加载物流信息
watch(() => props.visible, async (newVal) => {
  if (newVal && props.expressNo) {
    await loadExpressInfo()
  }
})

// 加载物流信息
const loadExpressInfo = async () => {
  try {
    loading.value = true
    trackingList.value = []
    const res = await getExpress(props.expressNo, props.mobile || '')

    if (res.code === 1) {
      const rawList = extractRawTrackingList(res)
      trackingList.value = normalizeTrackingList(rawList)
    } else {
      trackingList.value = []
    }
  } catch (error) {
    console.error('获取物流信息失败:', error)
    trackingList.value = []
  } finally {
    loading.value = false
  }
}

// 关闭弹窗
const handleClose = () => {
  emit('update:visible', false)
}

// 复制快递单号
const handleCopyExpressNo = () => {
  uni.setClipboardData({
    data: props.expressNo,
    success: () => {
      uni.showToast({
        title: '已复制快递单号',
        icon: 'success'
      })
    }
  })
}

const handleCallPhone = (phone: string) => {
  const phoneNumber = (phone || '').trim()
  if (!phoneNumber) return

  uni.makePhoneCall({
    phoneNumber,
    fail: () => {
      uni.showToast({
        title: '拨号失败',
        icon: 'none'
      })
    }
  })
}
</script>

<style scoped lang="scss">
.express-modal {
  padding: 20rpx;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx;
  border-bottom: 1rpx solid #f0f0f0;
  margin-bottom: 20rpx;
}

.header-title {
  font-size: 32rpx;
  font-weight: 600;
  color: #333;
}

.express-info {
  background: #f8f9fa;
  border-radius: 12rpx;
  padding: 20rpx;
  margin-bottom: 20rpx;
}

.mobile-right {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.quick-call-btn {
  font-size: 22rpx;
  color: #2563eb;
  padding: 4rpx 12rpx;
  border-radius: 999rpx;
  background: #eff6ff;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80rpx 0;
}

.tracking-timeline {
  padding: 20rpx;
  min-height: 400rpx;
}

.context-line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  line-height: 1.5;
}

.context-text {
  font-size: 26rpx;
  color: #374151;
}

.phone-btn {
  margin: 0 4rpx;
  font-size: 26rpx;
  color: #2563eb;
  padding: 2rpx 10rpx;
  border-radius: 999rpx;
  background: rgba(59, 130, 246, 0.12);
}

.empty-tracking {
  padding: 40rpx 0;
  min-height: 400rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
