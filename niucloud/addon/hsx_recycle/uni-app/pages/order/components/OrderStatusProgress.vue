<template>
  <view class="order-progress-section">
    <view class="status-summary">
      <up-icon :name="progress.icon" size="30" :color="statusInfo.color" />
      <view class="status-copy">
        <text class="status-title">{{ statusName || statusInfo.text }}</text>
        <text class="status-description">{{ progress.description }}</text>
      </view>
    </view>
    <up-steps v-if="!progress.ended && progress.step >= 0" :current="progress.step" activeColor="var(--recycle-brand)" inactiveColor="var(--recycle-text-sub)" dot>
      <up-steps-item v-for="step in steps" :key="step" :title="step" />
    </up-steps>
  </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { getOrderStatusInfo } from '../../../utils/theme'
import { orderProgress } from '../../../utils/order-presentation'
const props = defineProps<{ status: number | string; statusName: string; createTime?: string }>()
const steps = ['下单', '质检', '报价', '打款', '完成']
const progress = computed(() => orderProgress(props.status))
const statusInfo = computed(() => getOrderStatusInfo(props.status))
</script>
<style scoped lang="scss">
.order-progress-section { padding: 24px var(--recycle-order-gutter, 24px) 20px; background: var(--recycle-bg-card); }
.status-summary { display: flex; align-items: flex-start; gap: 18rpx; margin-bottom: 28rpx; }
.status-copy { min-width: 0; flex: 1; }
.status-title { display: block; font-size: 36rpx; font-weight: 600; line-height: 48rpx; }
.status-description { display: block; margin-top: 8rpx; font-size: 25rpx; line-height: 38rpx; color: var(--recycle-text-sub); }
</style>
