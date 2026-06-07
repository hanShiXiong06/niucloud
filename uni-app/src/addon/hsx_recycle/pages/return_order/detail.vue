<template>
  <view class="min-h-screen bg-gray-50 pb-6">
    <!-- 加载中 -->
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
        <view class="skeleton-line h-3 w-3/4"></view>
      </view>
    </view>

    <!-- 订单内容 -->
    <view v-else-if="orderDetail.id">
      <!-- 渐变横幅 + 步骤进度条 -->
      <view class="mx-3 mt-2 mb-3 rounded-lg overflow-hidden shadow-sm">
        <!-- 渐变横幅 -->
        <view class="px-4 py-4 text-white" :style="{ background: statusGradient }">
          <view class="flex items-center justify-between mb-1">
            <text class="text-lg font-bold">{{ statusInfo.text }}</text>
            <text class="text-xs opacity-80">{{ orderDetail.create_at }}</text>
          </view>
          <text class="text-sm opacity-90">{{ statusDesc }}</text>
        </view>

        <!-- 步骤进度条 -->
        <view class="bg-white px-3 py-3">
          <view v-if="orderDetail.status === 3" class="flex items-center justify-center py-1">
            <view class="flex items-center gap-1 text-sm text-gray-400">
              <up-icon name="close-circle" size="16" color="#9ca3af"></up-icon>
              <text>退货已取消</text>
            </view>
          </view>
          <view v-else class="flex items-center justify-between relative">
            <!-- 底线（灰） -->
            <view class="progress-track absolute top-3 h-0.5 bg-gray-200 z-0"></view>
            <!-- 进度线（彩色） -->
            <view
              class="progress-track absolute top-3 h-0.5 z-1"
              :style="{ background: statusInfo.color, transform: `scaleX(${progressRatio})`, transformOrigin: 'left center' }"
            ></view>
            <view
              v-for="(step, index) in returnSteps"
              :key="index"
              class="flex flex-col items-center z-10 flex-1"
            >
              <view
                class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mb-1"
                :class="index <= currentStep ? 'text-white shadow-sm' : 'bg-gray-200 text-gray-400'"
                :style="index <= currentStep ? { background: statusInfo.color } : {}"
              >
                <up-icon v-if="index < currentStep" name="checkmark" size="12" color="#fff"></up-icon>
                <text v-else-if="index === currentStep && isReturnCompleted">✓</text>
                <text v-else>{{ index + 1 }}</text>
              </view>
              <text
                :class="index <= currentStep ? 'text-xs font-medium text-gray-700' : 'text-xs text-gray-400'"
                style="font-size: 20rpx;"
              >{{ step }}</text>
            </view>
          </view>
        </view>
      </view>

      <!-- 订单信息卡片 -->
      <view class="bg-white rounded-lg shadow-sm mx-3 mb-3 overflow-hidden">
        <view class="flex items-center gap-1.5 px-4 pt-3 pb-2">
          <view class="w-1 h-4 rounded" :style="{ background: statusInfo.color }"></view>
          <text class="text-base font-bold text-gray-800">订单信息</text>
        </view>
        <view class="px-4 pb-3 space-y-2.5">
          <view class="flex items-center justify-between">
            <text class="text-sm text-gray-400">退货单号</text>
            <view class="flex items-center gap-1.5">
              <text class="text-sm text-gray-800 font-medium">{{ orderDetail.order_no }}</text>
              <view
                class="w-5 h-5 flex items-center justify-center rounded bg-gray-100"
                @tap="handleCopyOrderNo"
              >
                <up-icon name="file-text" size="12" color="#94a3b8"></up-icon>
              </view>
            </view>
          </view>
          <view class="flex items-center justify-between">
            <text class="text-sm text-gray-400">创建时间</text>
            <text class="text-sm text-gray-800">{{ orderDetail.create_at }}</text>
          </view>
          <view v-if="orderDetail.over_at" class="flex items-center justify-between">
            <text class="text-sm text-gray-400">完成时间</text>
            <text class="text-sm text-gray-800">{{ orderDetail.over_at }}</text>
          </view>
          <view v-if="orderDetail.remark" class="flex items-center justify-between">
            <text class="text-sm text-gray-400">备注</text>
            <text class="text-sm text-gray-800 text-right flex-1 ml-4">{{ orderDetail.remark }}</text>
          </view>
          <view v-if="orderDetail.comment" class="flex items-center justify-between">
            <text class="text-sm text-gray-400">备注信息</text>
            <text class="text-sm text-gray-800 text-right flex-1 ml-4">{{ orderDetail.comment }}</text>
          </view>
        </view>
      </view>

      <!-- 快递信息卡片 -->
      <view v-if="orderDetail.express_company || orderDetail.express_no" class="bg-white rounded-lg shadow-sm mx-3 mb-3 overflow-hidden">
        <view class="flex items-center gap-1.5 px-4 pt-3 pb-2">
          <view class="w-1 h-4 rounded bg-blue-500"></view>
          <text class="text-base font-bold text-gray-800">快递信息</text>
        </view>
        <view class="px-4 pb-3 space-y-2.5">
          <view v-if="orderDetail.express_company" class="flex items-center justify-between">
            <text class="text-sm text-gray-400">快递公司</text>
            <text class="text-sm text-gray-800">{{ orderDetail.express_company }}</text>
          </view>
          <view v-if="orderDetail.express_no" class="flex items-center justify-between">
            <text class="text-sm text-gray-400">快递单号</text>
            <view class="flex items-center gap-1.5">
              <text class="text-sm text-gray-800 font-medium">{{ orderDetail.express_no }}</text>
              <view
                class="w-5 h-5 flex items-center justify-center rounded bg-gray-100"
                @tap="handleCopyExpressNo"
              >
                <up-icon name="file-text" size="12" color="#94a3b8"></up-icon>
              </view>
            </view>
          </view>
          <view v-if="orderDetail.return_address" class="flex justify-between">
            <text class="text-sm text-gray-400 flex-shrink-0">退回地址</text>
            <text class="text-sm text-gray-800 text-right flex-1 ml-4">{{ orderDetail.return_address }}</text>
          </view>
          <!-- 查看物流按钮 -->
          <view v-if="orderDetail.express_no" class="flex justify-end pt-1">
            <view
              class="flex items-center gap-1 text-sm text-blue-500 bg-blue-50 px-3 py-1.5 rounded-full border border-blue-100 active:bg-blue-100"
              @tap="showExpressTracking = true"
            >
              <up-icon name="car" size="14" color="#3b82f6"></up-icon>
              <text>查看物流</text>
            </view>
          </view>
        </view>
      </view>

      <!-- 退回设备卡片 -->
      <view class="mx-3 mb-3">
        <view class="flex items-center gap-1.5 mb-2">
          <view class="w-1 h-4 rounded bg-orange-500"></view>
          <text class="text-base font-bold text-gray-800">退回设备</text>
          <text class="text-xs text-gray-400">({{ deviceList.length }}台)</text>
        </view>

        <view
          v-for="(item, index) in deviceList"
          :key="index"
          class="bg-white rounded-lg shadow-sm overflow-hidden mb-2"
        >
          <!-- 设备头部 -->
          <view class="flex items-center justify-between px-3 py-2.5 border-b border-gray-50">
            <text class="text-sm font-medium text-gray-700">
              <text class="text-blue-500">{{ index + 1 }}.</text>
              {{ item.device?.model || '未知设备' }}
            </text>
            <view
              class="text-xs px-2 py-0.5 rounded-full"
              :style="{ color: getDeviceColor(item), background: getDeviceBg(item) }"
            >
              {{ item.status_name || getDeviceText(item) }}
            </view>
          </view>

          <!-- 设备信息 -->
          <view v-if="item.device" class="px-3 py-2.5 space-y-1.5">
            <view class="flex items-center justify-between text-xs">
              <view class="flex items-center gap-1">
                <text class="text-gray-400">IMEI</text>
                <text class="text-gray-700">{{ item.device.imei || '-' }}</text>
              </view>
              <view
                v-if="item.device.imei"
                class="w-4 h-4 flex items-center justify-center rounded bg-gray-100"
                @tap="copyIMEI(item.device.imei)"
              >
                <up-icon name="file-text" size="10" color="#94a3b8"></up-icon>
              </view>
            </view>
            <view v-if="item.device.final_price" class="flex items-center text-xs">
              <text class="text-gray-400 mr-1">价格</text>
              <text class="font-bold" style="color: #ff6b00;">¥{{ Number(item.device.final_price).toFixed(2) }}</text>
            </view>
          </view>
          <view v-else class="px-3 py-4 text-center text-xs text-gray-400">设备信息不存在</view>

          <!-- 退货备注 -->
          <view v-if="item.remark" class="px-3 pb-2.5 text-xs text-gray-400">
            备注：{{ item.remark }}
          </view>
        </view>
      </view>
    </view>

    <!-- 空状态 -->
    <view v-else class="min-h-screen flex items-center justify-center px-10">
      <up-empty mode="data" icon="http://cdn.uviewui.com/uview/empty/data.png" text="退货订单不存在" textColor="#999999" textSize="15"></up-empty>
    </view>

    <!-- 物流查询弹窗 -->
    <ExpressTrackingModal
      :visible="showExpressTracking"
      :expressNo="orderDetail.express_no || ''"
      :mobile="orderDetail.member_mobile || ''"
      @update:visible="showExpressTracking = $event"
    />
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getReturnOrderDetail } from '../../api/return_order'
import { getReturnOrderStatusInfo, getDeviceStatusInfo, RETURN_ORDER_STATUS } from '../../utils/theme'
import { copyOrderNo, copyExpressNo, copyIMEI } from '../../utils/clipboard'
import ExpressTrackingModal from '../order/components/ExpressTrackingModal.vue'

const loading = ref(true)
const orderDetail = ref<any>({})
const deviceList = ref<any[]>([])
const showExpressTracking = ref(false)

// 退货步骤
const returnSteps = ['待处理', '退货中', '已完成']

// 状态信息
const statusInfo = computed(() => getReturnOrderStatusInfo(orderDetail.value.status))
const statusGradient = computed(() => {
  const info = RETURN_ORDER_STATUS[orderDetail.value.status as keyof typeof RETURN_ORDER_STATUS]
  return info?.gradient || 'linear-gradient(135deg, #90a4ae, #607d8b)'
})
const statusDesc = computed(() => {
  const map: Record<number, string> = {
    0: '商家正在处理您的退货申请',
    1: '商家已确认退货，设备退回中',
    2: '退货已完成',
    3: '退货已取消'
  }
  return map[orderDetail.value.status] || ''
})

const currentStep = computed(() => {
  switch (orderDetail.value.status) {
    case 0: return 0
    case 1: return 1
    case 2: return 2
    default: return 0
  }
})

const isReturnCompleted = computed(() => orderDetail.value.status === 2)

// 进度比例 0~1，用于 scaleX
const progressRatio = computed(() => {
  const total = returnSteps.length - 1
  return Math.min(currentStep.value / total, 1)
})

// 设备状态辅助
const getDeviceColor = (item: any) => getDeviceStatusInfo(item.device?.status ?? 0).color
const getDeviceBg = (item: any) => getDeviceStatusInfo(item.device?.status ?? 0).bgColor
const getDeviceText = (item: any) => getDeviceStatusInfo(item.device?.status ?? 0).text

const handleCopyOrderNo = () => copyOrderNo(orderDetail.value.order_no)
const handleCopyExpressNo = () => copyExpressNo(orderDetail.value.express_no)

const loadDetail = async (id: number | string) => {
  loading.value = true
  try {
    const res = await getReturnOrderDetail(Number(id))
    if (res.code === 1 && res.data) {
      orderDetail.value = res.data
      deviceList.value = res.data.return_devices || res.data.returnDevices || []
    } else {
      orderDetail.value = {}
      deviceList.value = []
    }
  } catch (error) {
    console.error('获取退货订单详情失败:', error)
    orderDetail.value = {}
    deviceList.value = []
  } finally {
    loading.value = false
  }
}

onLoad((options?: Record<string, any>) => {
  if (options?.id) loadDetail(options.id)
})
</script>

<style lang="scss">
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
.progress-track {
  left: 16%;
  right: 16%;
}
</style>
