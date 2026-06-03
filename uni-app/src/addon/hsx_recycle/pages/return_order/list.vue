<template>
  <view class="min-h-screen bg-gray-50">
    <!-- 状态筛选标签 -->
    <up-sticky bgColor="#fff" class="!top-0 !z-10">
      <scroll-view scroll-x class="bg-white border-b border-gray-100">
        <view class="flex h-11">
          <view
            v-for="tab in statusTabs"
            :key="tab.value"
            class="flex items-center justify-center px-4 relative flex-shrink-0"
            :class="currentStatus === tab.value ? 'text-blue-500 font-bold' : 'text-gray-500'"
            @tap="handleStatusChange(tab.value)"
          >
            <text class="text-sm">{{ tab.text }}</text>
            <view
              v-if="currentStatus === tab.value"
              class="absolute bottom-0 left-1/2 -translate-x-1/2 w-5 h-0.5 rounded bg-blue-500"
            ></view>
          </view>
        </view>
      </scroll-view>
    </up-sticky>

    <!-- 退货订单列表 -->
    <mescroll-body
      ref="mescrollRef"
      @down="mescrollDown"
      @up="mescrollUp"
      :up="upOption"
      :down="downOption"
    >
      <view class="px-3 pt-3">
        <view
          v-for="item in orderList"
          :key="item.id"
          class="bg-white rounded-lg shadow-sm mb-3 overflow-hidden"
          @tap="goToDetail(item.id)"
        >
          <!-- 订单头部 -->
          <view class="flex items-center justify-between px-4 py-3 border-b border-gray-50">
            <view class="flex items-center gap-1 text-sm text-gray-500">
              <up-icon name="order" size="14" color="#94a3b8"></up-icon>
              <text>{{ item.order_no }}</text>
            </view>
            <view
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              :style="{ color: getStatusColor(item.status), background: getStatusBg(item.status) }"
            >
              {{ item.status_name || getStatusText(item.status) }}
            </view>
          </view>

          <!-- 订单内容 -->
          <view class="px-4 py-3 space-y-2">
            <view class="flex items-center justify-between">
              <view class="flex items-center gap-1 text-sm text-gray-700">
                <up-icon name="gift" size="14" color="#64748b"></up-icon>
                <text>退回设备：{{ getDeviceCount(item) }}台</text>
              </view>
              <text class="text-xs text-gray-400">{{ item.create_at }}</text>
            </view>

            <!-- 快递信息 -->
            <view v-if="item.express_company" class="flex items-center gap-1 text-xs text-gray-400">
              <up-icon name="car" size="12" color="#94a3b8"></up-icon>
              <text>{{ item.express_company }}</text>
              <text v-if="item.express_no">：{{ item.express_no }}</text>
            </view>

            <!-- 备注 -->
            <view v-if="item.remark" class="text-xs text-gray-400 truncate">
              备注：{{ item.remark }}
            </view>
          </view>

          <!-- 底部操作 -->
          <view class="flex items-center justify-end gap-2 px-4 py-2.5 border-t border-gray-50">
            <view
              class="text-xs px-3 py-1 rounded-full border border-gray-200 text-gray-500 bg-white"
              @tap.stop="goToDetail(item.id)"
            >
              查看详情
            </view>
          </view>
        </view>
      </view>

      <!-- 空状态 -->
      <view v-if="orderList.length === 0 && !loading" class="flex items-center justify-center" style="padding-top: 200rpx;">
        <up-empty
          mode="data"
          icon="http://cdn.uviewui.com/uview/empty/data.png"
          text="暂无退货订单"
          textColor="#999999"
          textSize="15"
        ></up-empty>
      </view>
    </mescroll-body>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
import { getReturnOrderList } from '../../api/return_order'
import { getReturnOrderStatusInfo, RETURN_ORDER_STATUS } from '../../utils/theme'

// ============ 状态筛选 ============
const statusTabs = [
  { text: '全部', value: -1 },
  { text: '待处理', value: 0 },
  { text: '退货中', value: 1 },
  { text: '已完成', value: 2 },
  { text: '已取消', value: 3 },
]

const currentStatus = ref(-1)
const orderId = ref<number | null>(null)

// ============ 列表数据 ============
const orderList = ref<any[]>([])
const loading = ref(false)
const mescrollRef = ref<any>(null)

// mescroll 配置
const upOption = {
  auto: true,
  page: { num: 0, size: 10 },
  noMoreSize: 3,
  empty: { tip: '暂无退货订单' }
}

const downOption = {
  auto: false,
  textInOffset: '下拉刷新',
  textOutOffset: '释放更新',
  textLoading: '加载中...'
}

// ============ 状态辅助 ============
const getStatusColor = (status: number) => getReturnOrderStatusInfo(status).color
const getStatusBg = (status: number) => getReturnOrderStatusInfo(status).bgColor
const getStatusText = (status: number) => getReturnOrderStatusInfo(status).text
const getDeviceCount = (item: any) => (item.return_devices || item.returnDevices || []).length

// ============ 列表操作 ============
const mescrollDown = (mescroll: any) => {
  mescroll.resetUpScroll()
}

const mescrollUp = async (mescroll: any) => {
  try {
    loading.value = true
    const params: any = {
      page: mescroll.num,
      limit: mescroll.size
    }

    // 状态筛选
    if (currentStatus.value !== -1) {
      params.status = currentStatus.value
    }

    // 按原订单ID筛选
    if (orderId.value) {
      params.order_id = orderId.value
    }

    const res: any = await getReturnOrderList(params)

    if (res.code === 1) {
      const list = res.data?.data || res.data?.list || []

      if (mescroll.num === 1) {
        orderList.value = []
      }

      orderList.value = [...orderList.value, ...list]
      mescroll.endSuccess(list.length)

      if (list.length < mescroll.size) {
        mescroll.endUpScroll(false)
      }
    } else {
      mescroll.endErr()
      uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
    }
  } catch (error) {
    console.error('加载退货订单列表失败：', error)
    mescroll.endErr()
    uni.showToast({ title: '加载失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

const refreshList = () => {
  if (mescrollRef.value?.mescroll) {
    mescrollRef.value.mescroll.resetUpScroll()
  }
}

// ============ 导航 ============
const goToDetail = (id: number) => {
  uni.navigateTo({
    url: `/addon/hsx_recycle/pages/return_order/detail?id=${id}`
  })
}

const handleStatusChange = (status: number) => {
  if (currentStatus.value === status) return
  currentStatus.value = status
  refreshList()
}

// ============ 页面加载 ============
onLoad((options?: Record<string, any>) => {
  if (options?.order_id) {
    orderId.value = Number(options.order_id)
  }
})
</script>
