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
        <view v-if="mobile" class="flex items-center justify-between">
          <text class="text-sm text-gray-600">手机号</text>
          <text class="text-sm text-gray-800">{{ mobile }}</text>
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
              <text class="text-sm text-gray-700">{{ item.context }}</text>
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

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:visible': [value: boolean]
}>()

const loading = ref(false)
const trackingList = ref<TrackingItem[]>([])

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
    const res = await getExpress(props.expressNo, props.mobile || '')

    if (res.code === 1 && res.data && res.data.list) {
      trackingList.value = res.data.list
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

.empty-tracking {
  padding: 40rpx 0;
  min-height: 400rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
