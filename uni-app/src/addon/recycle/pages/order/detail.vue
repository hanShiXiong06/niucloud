<template>
  <view class="min-h-screen bg-gray-50 pb-20">
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
        :mobile="orderInfo.member?.mobile"
      />

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
          @toggle-select="toggleDeviceSelection(device.id)"
          @confirm="handleDeviceConfirm(device)"
          @negotiate="negotiate"
        />
      </view>

      <!-- 底部批量确认按钮 -->
      <view
        v-if="selectedCount > 0 && orderInfo.status == 5"
        class="fixed left-0 right-0 bottom-0 bg-white bg-opacity-95 p-3 shadow-up backdrop-blur-sm"
      >
        <button
          class="w-full h-11 rounded-full flex items-center justify-center text-white text-sm font-medium"
          style="background: linear-gradient(135deg, #10b981, #059669);"
          @tap="handleConfirmSelected"
        >
          <up-icon name="checkmark-circle" size="16" color="#fff" class="mr-1"></up-icon>
          确认选中设备 ({{ selectedCount }})
        </button>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useOrderDetail } from '../../hooks/useOrderDetail'
import { useDeviceSelection } from '../../hooks/useDeviceSelection'
import { useReturnOrder } from '../../hooks/useReturnOrder'
import OrderStatusProgress from './components/OrderStatusProgress.vue'
import OrderDetailHeader from './components/OrderDetailHeader.vue'
import DeviceBatchToolbar from './components/DeviceBatchToolbar.vue'
import DeviceDetailCard from './components/DeviceDetailCard.vue'

const {
  loading, orderInfo, isEmpty, hasNoDevices, totalPrice,
  loadOrderDetail, confirmDevice, confirmDevices, negotiate
} = useOrderDetail()

const { returnOrderList, hasReturnOrder, loadReturnOrders, goToReturnOrder } = useReturnOrder()

const devicesRef = computed(() => orderInfo.value.devices)

const {
  isAllSelected, selectedCount, isDeviceSelected,
  toggleDeviceSelection, toggleSelectAll, copySelectedIMEIs,
  getSelectedPendingDevices, resetSelection, initSelection
} = useDeviceSelection(devicesRef)

const handleDeviceConfirm = async (device: any) => {
  const success = await confirmDevice(device)
  if (success) resetSelection()
}

const handleConfirmSelected = async () => {
  const pendingDevices = getSelectedPendingDevices()
  const deviceIds = pendingDevices.map(d => d.id)
  const success = await confirmDevices(deviceIds)
  if (success) resetSelection()
}

const goBack = () => {
  uni.navigateBack({
    delta: 1,
    fail: () => {
      uni.navigateTo({ url: '/addon/recycle/pages/order/list' })
    }
  })
}

watch(() => orderInfo.value.id, (newId) => {
  if (newId) loadReturnOrders(newId)
})

onLoad((options?: Record<string, any>) => {
  if (options?.id) loadOrderDetail(options.id)
})

onShow(async () => {
  await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay')
  if (orderInfo.value.devices && orderInfo.value.devices.length > 0) {
    initSelection()
  }
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
.shadow-up {
  box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
}
</style>
