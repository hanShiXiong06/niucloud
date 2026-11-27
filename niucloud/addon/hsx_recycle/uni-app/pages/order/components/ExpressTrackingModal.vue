<template>
  <OrderTaskPopup
    :show="visible"
    title="物流信息"
    @close="handleClose"
  >
    <view class="express-modal">
      <!-- 快递单号 -->
      <view class="express-info">
        <view class="waybill-row">
          <text class="waybill-label">快递单号</text>
          <button class="waybill-copy" aria-label="复制快递单号" @tap="handleCopyExpressNo">
            <text class="waybill-number">{{ expressNo }}</text>
            <text class="nc-iconfont nc-icon-fuzhiV6xx" />
          </button>
        </view>
      </view>

      <!-- 加载状态 -->
      <view v-if="loading" class="loading-container">
        <up-loading-icon mode="circle" size="40"></up-loading-icon>
        <text class="text-sm text-gray-500 mt-2">加载中...</text>
      </view>

      <view v-else-if="loadFailed" class="empty-tracking">
        <text>物流查询失败，请稍后重试</text>
        <OrderUiButton @click="loadExpressInfo">重新查询</OrderUiButton>
      </view>

      <!-- 物流时间线 -->
      <view v-else-if="trackingList.length > 0" class="tracking-timeline">
        <up-steps
          direction="column"
          :current="0"
          activeColor="var(--recycle-brand)"
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

      <!-- 空状态 - 暂未查询到物流信息 -->
      <view v-else class="empty-tracking">
        <up-empty
          mode="data"
          text="暂未查询到物流信息"
          textColor="var(--recycle-text-sub)"
          textSize="14"
        >
          <template #bottom>
            <view class="empty-tracking-tip">
              <text class="text-xs text-gray-500">可能是手机号（后4位）不匹配，或快递尚未揽件。</text>
              <text class="text-xs text-gray-500">如长时间查不到，请凭快递单号到快递官方渠道查询。</text>
            </view>
          </template>
        </up-empty>
      </view>
    </view>
  </OrderTaskPopup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import OrderTaskPopup from './OrderTaskPopup.vue'
import OrderUiButton from './OrderUiButton.vue'
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
const loadFailed = ref(false)
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
  if (loading.value) return
  try {
    loading.value = true
    loadFailed.value = false
    trackingList.value = []
    const res = await getExpress(props.expressNo, props.mobile || '')

    if (res.code === 1) {
      const rawList = extractRawTrackingList(res)
      trackingList.value = normalizeTrackingList(rawList)
    } else {
      trackingList.value = []
      loadFailed.value = true
    }
  } catch (error) {
    console.error('获取物流信息失败:', error)
    trackingList.value = []
    loadFailed.value = true
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
  padding: 16px;
}

.waybill-row { display: flex; align-items: center; gap: 12px; }
.waybill-label { flex-shrink: 0; font-size: 13px; color: var(--recycle-text-sub); }
.waybill-copy { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex: 1; min-width: 0; min-height: 40px; margin: 0; padding: 0; background: transparent; border: 0; color: var(--recycle-text-main); font-size: 14px; line-height: 20px; }
.waybill-copy::after { border: 0; }
.waybill-copy .nc-iconfont { flex-shrink: 0; font-size: 18px; }
.waybill-number { overflow-wrap: anywhere; text-align: right; }

.express-info {
  background: var(--recycle-bg-soft);
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
  color: var(--recycle-brand);
  padding: 4rpx 12rpx;
  border-radius: 999rpx;
  background: var(--recycle-bg-soft);
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
  color: var(--recycle-text-main);
}

.phone-btn {
  margin: 0 4rpx;
  font-size: 26rpx;
  color: var(--recycle-brand);
  padding: 2rpx 10rpx;
  border-radius: 999rpx;
  background: var(--recycle-bg-soft);
}

.empty-tracking {
  padding: 40rpx 0;
  min-height: 400rpx;
  display: flex;
  flex-direction: column;
  gap: 16px;
  align-items: center;
  justify-content: center;
}

.empty-tracking-tip {
  margin-top: 12rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6rpx;
  padding: 0 40rpx;
  text-align: center;
  line-height: 1.5;
}
</style>
