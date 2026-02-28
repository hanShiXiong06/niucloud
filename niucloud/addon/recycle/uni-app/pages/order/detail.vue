<template>
  <view class="min-h-screen bg-gray-50 pb-20">
    <!-- 骨架屏加载状态 -->
    <view v-if="loading" class="skeleton-container">
      <!-- 状态卡片骨架 -->
      <view class="bg-white rounded-lg shadow-sm mx-3 mt-2 mb-3 p-3">
        <view class="skeleton-line h-10 w-full mb-3"></view>
        <view class="skeleton-line h-4 w-1/3 mb-2"></view>
        <view class="skeleton-line h-12 w-full"></view>
      </view>

      <!-- 设备列表骨架 -->
      <view class="mx-3">
        <view class="skeleton-line h-6 w-1/4 mb-2"></view>
        <view v-for="i in 3" :key="i" class="bg-white rounded-lg shadow-sm p-3 mb-2">
          <view class="skeleton-line h-4 w-3/4 mb-2"></view>
          <view class="skeleton-line h-4 w-1/2 mb-2"></view>
          <view class="skeleton-line h-4 w-2/3"></view>
        </view>
      </view>
    </view>

    <!-- 空状态 -->
    <view v-else-if="!loading && isEmpty" class="empty-container">
      <up-empty
        mode="data"
        icon="http://cdn.uviewui.com/uview/empty/data.png"
        text="暂无订单信息"
        textColor="#999999"
        textSize="15"
      >
        <template #bottom>
          <view class="empty-bottom">
            <text class="empty-desc">请耐心等待订单处理</text>
            <up-button
              type="primary"
              shape="round"
              size="normal"
              @click="goBack"
            >
              <up-icon name="arrow-left" size="16" color="#fff" class="mr-1"></up-icon>
              返回订单列表
            </up-button>
          </view>
        </template>
      </up-empty>
    </view>

    <!-- 实际内容 -->
    <view v-else>
      <!-- 订单状态进度 -->
      <OrderStatusProgress
        :status="orderInfo.status"
        :statusName="orderInfo.status_name"
        :createTime="orderInfo.create_at"
      />

      <!-- 订单头部信息 -->
      <OrderDetailHeader
        :orderNo="orderInfo.order_no"
        :deviceCount="orderInfo.devices.length"
        :totalPrice="totalPrice"
        :expressNo="orderInfo.express_no"
        :mobile="orderInfo.member?.mobile"
      />

      <!-- 查看退货信息按钮 -->
      <view v-if="returnOrderList.length > 0" class="mx-3 mt-3">
        <view
          class="bg-white rounded-lg shadow-sm p-3 flex items-center justify-between"
          @tap="goToReturnOrderDetail"
        >
          <view class="flex items-center">
            <up-icon name="order" size="20" color="#ef4444" class="mr-2"></up-icon>
            <text class="text-sm font-medium text-gray-800">查看退货信息</text>
            <text class="text-xs text-gray-400 ml-2">({{ returnOrderList.length }}条退货记录)</text>
          </view>
          <up-icon name="arrow-right" size="16" color="#9ca3af"></up-icon>
        </view>
      </view>

      <!-- 无设备提示 -->
      <view v-if="hasNoDevices" class="mx-3 mt-4">
        <view class="bg-white rounded-lg shadow-sm p-4 text-center ">
          <view class="flex justify-center">
            <up-icon name="clock" size="40" color="#94a3b8" class="mb-2"></up-icon>
      
          </view>             
          <text class="block text-sm text-gray-600 mb-1">订单等待更新</text>
        </view>
      </view>

      <!-- 设备列表 -->
      <view v-else class="mx-3">
        <view class="flex justify-between items-center mb-2">
          <text class="text-base font-bold text-gray-800">设备列表</text>
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
        class="fixed left-0 right-0 bottom-0 bg-white bg-opacity-95 p-2 shadow-up backdrop-blur-sm"
      >
        <button
          class="w-full h-10 rounded-full bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center text-white text-sm font-medium"
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
import { computed, ref, watch } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useOrderDetail } from '../../hooks/useOrderDetail'
import { useDeviceSelection } from '../../hooks/useDeviceSelection'
import { getReturnOrderByOrderId } from '../../api/return_order'
import OrderStatusProgress from './components/OrderStatusProgress.vue'
import OrderDetailHeader from './components/OrderDetailHeader.vue'
import DeviceBatchToolbar from './components/DeviceBatchToolbar.vue'
import DeviceDetailCard from './components/DeviceDetailCard.vue'

// 订单详情管理
const {
  loading,
  orderInfo,
  isEmpty,
  hasNoDevices,
  totalPrice,
  loadOrderDetail,
  confirmDevice,
  confirmDevices,
  negotiate
} = useOrderDetail()

// 退货订单列表
const returnOrderList = ref<any[]>([])

// 加载退货订单数据
const loadReturnOrders = async (orderId: number | string) => {
  try {
    const res = await getReturnOrderByOrderId(Number(orderId))
    const data = res.data || []
    returnOrderList.value = Array.isArray(data) ? data : []
  } catch (e) {
    returnOrderList.value = []
  }
}

// 跳转退货订单详情
const goToReturnOrderDetail = () => {
  if (returnOrderList.value.length === 1) {
    // 只有一条退货记录，直接跳转详情
    uni.navigateTo({
      url: `/addon/recycle/pages/return_order/detail?id=${returnOrderList.value[0].id}`
    })
  } else if (returnOrderList.value.length > 1) {
    // 多条退货记录，跳转列表（带order_id过滤）
    uni.navigateTo({
      url: `/addon/recycle/pages/return_order/list?order_id=${orderInfo.value.id}`
    })
  }
}

// 创建devices的computed ref
const devicesRef = computed(() => orderInfo.value.devices)

// 设备选择管理
const {
  isAllSelected,
  selectedCount,
  isDeviceSelected,
  toggleDeviceSelection,
  toggleSelectAll,
  copySelectedIMEIs,
  getSelectedPendingDevices,
  resetSelection,
  initSelection
} = useDeviceSelection(devicesRef)

// 确认单个设备
const handleDeviceConfirm = async (device: any) => {
  const success = await confirmDevice(device)
  if (success) {
    resetSelection()
  }
}

// 确认选中的设备
const handleConfirmSelected = async () => {
  const pendingDevices = getSelectedPendingDevices()
  const deviceIds = pendingDevices.map(d => d.id)
  const success = await confirmDevices(deviceIds)
  if (success) {
    resetSelection()
  }
}

// 返回上一页
const goBack = () => {
  uni.navigateBack({
    delta: 1,
    fail: () => {
      uni.navigateTo({
        url: '/addon/recycle/pages/order/list'
      })
    }
  })
}

// 当订单数据加载完成后，查询退货订单
watch(() => orderInfo.value.id, (newId) => {
  if (newId) {
    loadReturnOrders(newId)
  }
})

// 页面加载
onLoad((options?: Record<string, any>) => {
  if (options?.id) {
    loadOrderDetail(options.id)
  }
})

// 页面显示时请求订阅消息
onShow(async () => {
  await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay')
  // 初始化选择状态
  if (orderInfo.value.devices && orderInfo.value.devices.length > 0) {
    initSelection()
  }
})
</script>

<style lang="scss">
/* 骨架屏样式 */
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
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

@keyframes skeleton-fade-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

/* 空状态样式 */
.empty-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40rpx;
}

.empty-content {
  text-align: center;
  animation: empty-fade-in 0.5s ease-in-out;
}

.empty-icon {
  margin-bottom: 40rpx;
  opacity: 0.5;
}

.empty-title {
  display: block;
  font-size: 36rpx;
  font-weight: 600;
  color: #333;
  margin-bottom: 20rpx;
}

.empty-desc {
  display: block;
  font-size: 28rpx;
  color: #999;
  margin-bottom: 60rpx;
  line-height: 1.6;
}

.empty-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 24rpx 48rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 50rpx;
  font-size: 28rpx;
  font-weight: 500;
  border: none;
  box-shadow: 0 8rpx 24rpx rgba(102, 126, 234, 0.3);
  transition: all 0.3s ease;

  &:active {
    transform: scale(0.95);
    box-shadow: 0 4rpx 12rpx rgba(102, 126, 234, 0.3);
  }
}

@keyframes empty-fade-in {
  from {
    opacity: 0;
    transform: translateY(20rpx);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* 底部阴影 */
.shadow-up {
  box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
}
</style>

