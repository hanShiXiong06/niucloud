<template>
  <view class="device-card">
    <view class="image-box" v-if="showImage && report.image" @tap="$emit('preview', report.image)">
      <image class="device-image" :src="report.image" mode="aspectFit" />
    </view>
    <view class="image-box placeholder" v-else>
      <text>{{ initials }}</text>
    </view>

    <view class="content">
      <view class="code-row" @tap="$emit('copy', report.queryCode)">
        <text class="label">查询码</text>
        <text class="code">{{ report.displayCode }}</text>
      </view>

      <view class="summary-grid">
        <view class="summary-item" v-for="item in report.summary" :key="item.label">
          <text class="summary-label">{{ item.label }}</text>
          <text class="summary-value">{{ item.value }}</text>
        </view>
      </view>

      <view class="status-row" v-if="report.statusTags.length">
        <view class="status-tag" v-for="item in report.statusTags" :key="item.label" :class="item.tone">
          <text>{{ item.label }}</text>
          <text class="status-value">{{ item.value }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  report: any
  showImage: boolean
}>()

defineEmits(['copy', 'preview'])

const initials = computed(() => {
  return String(props.report?.title || '设备').slice(0, 2)
})
</script>

<style lang="scss" scoped>
.device-card {
  display: flex;
  gap: 24rpx;
  margin: 24rpx;
  padding: 26rpx;
  background: #fff;
  border-radius: 14rpx;
  box-shadow: 0 10rpx 28rpx rgba(17, 24, 39, 0.06);
}

.image-box {
  width: 170rpx;
  height: 170rpx;
  flex: 0 0 auto;
  border-radius: 12rpx;
  background: #f3f6fa;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.device-image {
  width: 150rpx;
  height: 150rpx;
}

.placeholder {
  color: #667085;
  font-size: 34rpx;
  font-weight: 600;
}

.content {
  min-width: 0;
  flex: 1;
}

.code-row {
  margin-bottom: 18rpx;
}

.label,
.summary-label {
  display: block;
  font-size: 22rpx;
  color: #8a94a6;
}

.code {
  display: block;
  margin-top: 6rpx;
  font-size: 30rpx;
  color: #111827;
  font-family: monospace;
  word-break: break-all;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16rpx;
}

.summary-value {
  display: block;
  margin-top: 4rpx;
  font-size: 24rpx;
  color: #344054;
  line-height: 32rpx;
  word-break: break-word;
}

.status-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10rpx;
  margin-top: 18rpx;
}

.status-tag {
  display: flex;
  gap: 8rpx;
  max-width: 100%;
  padding: 8rpx 12rpx;
  border-radius: 8rpx;
  font-size: 22rpx;
  background: #f2f4f7;
  color: #475467;
}

.status-value {
  font-weight: 600;
  word-break: break-word;
}

.success {
  background: #ecfdf3;
  color: #027a48;
}

.warning {
  background: #fffaeb;
  color: #b54708;
}

.danger {
  background: #fef3f2;
  color: #b42318;
}
</style>
