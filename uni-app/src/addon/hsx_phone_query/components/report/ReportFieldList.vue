<template>
  <view class="field-card">
    <view class="section-title">查询明细</view>
    <view class="field-row" v-for="field in fields" :key="field.key">
      <text class="label">{{ field.label }}</text>
      <image
        v-if="field.type === 'image'"
        class="field-image"
        :src="field.value"
        mode="aspectFit"
        @tap="$emit('preview', field.value)"
      />
      <text v-else class="value">{{ displayFieldValue(field) }}</text>
    </view>
    <view class="empty" v-if="!fields.length">暂无更多明细</view>
  </view>
</template>

<script setup lang="ts">
import { displayFieldValue } from './report-format'

defineProps<{
  fields: any[]
}>()

defineEmits(['preview'])
</script>

<style lang="scss" scoped>
.field-card {
  margin: 0 24rpx 24rpx;
  padding: 8rpx 26rpx;
  background: #fff;
  border-radius: 14rpx;
  box-shadow: 0 10rpx 28rpx rgba(17, 24, 39, 0.05);
}

.section-title {
  padding: 22rpx 0;
  font-size: 30rpx;
  font-weight: 700;
  color: #111827;
}

.field-row {
  display: flex;
  justify-content: space-between;
  gap: 28rpx;
  padding: 22rpx 0;
  border-top: 1rpx solid #eef1f5;
}

.label {
  flex: 0 0 180rpx;
  font-size: 25rpx;
  color: #667085;
  line-height: 36rpx;
}

.value {
  flex: 1;
  min-width: 0;
  text-align: right;
  font-size: 26rpx;
  color: #1d2939;
  line-height: 38rpx;
  word-break: break-word;
}

.field-image {
  width: 220rpx;
  height: 160rpx;
  border-radius: 10rpx;
  background: #f8fafc;
}

.empty {
  padding: 30rpx 0 38rpx;
  text-align: center;
  color: #98a2b3;
  font-size: 24rpx;
  border-top: 1rpx solid #eef1f5;
}
</style>
