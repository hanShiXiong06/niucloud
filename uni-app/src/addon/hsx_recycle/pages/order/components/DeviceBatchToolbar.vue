<template>
  <view class="device-batch-toolbar">
    <view class="device-batch-toolbar__left">
      <view class="device-batch-toolbar__select" @tap.stop="$emit('toggle-all')">
        <view class="device-batch-toolbar__check" :class="{ active: isAllSelected }">
          <up-icon v-if="isAllSelected" name="checkmark" size="11" color="#fff" />
        </view>
        <text>{{ isAllSelected ? '取消全选' : '全选设备' }}</text>
      </view>
      <view class="device-batch-toolbar__copy" @tap.stop="$emit('copy-imei')">
        <up-icon name="file-text" size="13" color="var(--recycle-brand)" />
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
.device-batch-toolbar {
  min-height: 72rpx;
  margin-bottom: 14rpx;
  padding: 0 20rpx;
  border: 1rpx solid #e9edf2;
  border-radius: 16rpx;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
}

.device-batch-toolbar__left,
.device-batch-toolbar__select,
.device-batch-toolbar__copy {
  display: flex;
  align-items: center;
}

.device-batch-toolbar__left {
  gap: 24rpx;
}

.device-batch-toolbar__select,
.device-batch-toolbar__copy {
  gap: 9rpx;
  color: #667085;
  font-size: 22rpx;
}

.device-batch-toolbar__copy {
  color: var(--recycle-brand);
}

.device-batch-toolbar__check {
  width: 30rpx;
  height: 30rpx;
  border: 2rpx solid #cfd5de;
  border-radius: 7rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.device-batch-toolbar__check.active {
  border-color: var(--recycle-brand);
  background: var(--recycle-brand);
}

.device-batch-toolbar__count {
  flex-shrink: 0;
  color: var(--recycle-brand);
  font-size: 21rpx;
  font-weight: 600;
}
</style>
