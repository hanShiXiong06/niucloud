<template>
  <view class="bg-white rounded-lg shadow-sm mx-3 mt-2 mb-3 overflow-hidden">
    <view class="p-3">
      <!-- 进度条 -->
      <view class="rounded px-2 py-3 mb-3">
        <up-steps :current="currentStep">
          <up-steps-item v-if="isCancelled" error title="已取消" desc="订单取消"></up-steps-item>
          <up-steps-item
            v-else
            v-for="item in steps"
            :key="item.name"
            :title="item.name"
          >
            <template #desc>
              <text class="text-[20rpx] text-gray-500 ">{{ item.desc }}</text>
            </template>
          </up-steps-item>
        </up-steps>
      </view>

      <!-- 状态信息行 -->
      <view class="flex items-center justify-between mb-3">
        <text class="text-xs text-gray-500">{{ createTime }}</text>
        <view
          class="status-badge rounded-full py-1 px-2 inline-flex items-center"
          :class="`status-bg-${status}`"
        >
          <up-icon
            :name="status == 7 ? 'rmb-circle' : 'checkbox-mark'"
            size="12"
            color="#fff"
            class="mr-1"
          ></up-icon>
          <text class="text-xs font-medium text-white">{{ statusName }}</text>
        </view>
      </view>

      <!-- 状态说明 -->
      <view class="bg-gray-50 rounded px-3 py-2 text-xs text-gray-700 leading-relaxed">
        {{ statusDescription }}
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  status: number
  statusName: string
  createTime: string
}

const props = defineProps<Props>()

// 步骤配置
const steps = [
  { name: '已下单', desc: '订单已提交' },
  { name: '待质检', desc: '待设备检测' },
  { name: '待确认', desc: '待确认价格' },
  { name: '待打款', desc: '等待转账' },
  { name: '已完成', desc: '交易完成' }
]

// 是否已取消
const isCancelled = computed(() => props.status === 8 || props.status === 9)

// 当前步骤
const currentStep = computed(() => {
  switch (props.status) {
    case 1: return 0  // 已下单
    case 2:
    case 3: return 1  // 待质检/质检中
    case 4:
    case 5: return 2  // 待确认/部分确认
    case 6: return 3  // 待打款
    case 7: return 4  // 已完成
    case 8:
    case 9: return 9  // 已取消/已删除
    default: return 0
  }
})

// 状态说明
const statusDescription = computed(() => {
  switch (props.status) {
    case 1: return '您的订单已提交，等待商家确认'
    case 2: return '您的订单已签收，等待商家质检'
    case 3: return '商家正在质检您的设备，请耐心等待'
    case 4: return '设备已完成质检，等待您确认价格'
    case 5: return '您已确认部分设备价格，等待确认剩余设备'
    case 6: return '价格已确认，等待商家打款'
    case 7: return '交易已完成，感谢您的使用'
    // case 8: return '订单已取消'
    case 9: return '订单已取消'
    default: return '订单状态未知'
  }
})
</script>

<style scoped lang="scss">
/* 状态背景色 */
.status-bg-1 { background: linear-gradient(135deg, #ffa726, #fb8c00); }
.status-bg-2 { background: linear-gradient(135deg, #42a5f5, #1e88e5); }
.status-bg-3 { background: linear-gradient(135deg, #42a5f5, #1e88e5); }
.status-bg-4 { background: linear-gradient(135deg, #66bb6a, #43a047); }
.status-bg-5 { background: linear-gradient(135deg, #66bb6a, #43a047); }
.status-bg-6 { background: linear-gradient(135deg, #ec407a, #d81b60); }
.status-bg-7 { background: linear-gradient(135deg, #12b981);  }
.status-bg-8 { background: linear-gradient(135deg, #ef5350, #e53935); }
.status-bg-9 { background: linear-gradient(135deg, #78909c, #546e7a); }
</style>
