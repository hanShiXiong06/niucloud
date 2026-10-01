<template>
  <view class="device-batch-toolbar">
    <view class="device-batch-toolbar__left">
      <view class="device-batch-toolbar__select" @tap.stop="$emit('toggle-all')">
        <up-icon :name="isAllSelected ? 'checkmark-circle-fill' : 'checkmark-circle'" size="20" color="var(--recycle-brand)" />
        <text>{{ isAllSelected ? '取消全选' : '全选设备' }}</text>
      </view>
      <view class="device-batch-toolbar__copy" @tap.stop="$emit('copy-imei')">
        <up-icon name="list" size="16" color="var(--recycle-brand)" />
        <text>复制串号</text>
      </view>
    </view>
    <view v-if="selectedCount > 0" class="device-batch-toolbar__count">
      已选择 {{ selectedCount }} 台
    </view>
  </view>
</template>

<script setup lang="ts">
interface Props {
  isAllSelected: boolean
  selectedCount: number
}

defineProps<Props>()

defineEmits<{
  'toggle-all': []
  'copy-imei': []
}>()
</script>

<style scoped lang="scss">
.device-batch-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8rpx 16rpx; min-height: 84rpx; margin-bottom: 16rpx; padding: 0 4rpx; font-size: 24rpx; }
.device-batch-toolbar__left, .device-batch-toolbar__select, .device-batch-toolbar__copy { display: flex; align-items: center; gap: 10rpx; }
.device-batch-toolbar__left { gap: 24rpx; }
.device-batch-toolbar__select, .device-batch-toolbar__copy { min-height: 72rpx; }
.device-batch-toolbar__copy, .device-batch-toolbar__count { color: var(--recycle-brand); }
</style>
