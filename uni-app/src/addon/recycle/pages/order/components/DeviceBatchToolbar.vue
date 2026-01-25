<template>
  <view class="bg-white rounded px-3 py-2 mb-2 shadow-sm flex items-center justify-between">
    <!-- 左侧操作 -->
    <view class="flex items-center gap-3">
      <!-- 全选 -->
      <view class="flex items-center" @tap.stop="$emit('toggle-all')">
        <up-checkbox :checked="isAllSelected" shape="square"></up-checkbox>
        <text class="ml-1 text-xs">{{ isAllSelected ? '取消全选' : '全选' }}</text>
      </view>

      <!-- 复制IMEI -->
      <view class="flex items-center" @tap.stop="$emit('copy-imei')">
        <up-icon name="cut" size="14" color="#2979ff"></up-icon>
        <text class="ml-1 text-xs text-blue-500">复制IMEI</text>
      </view>
    </view>

    <!-- 右侧选中数量 -->
    <view v-if="selectedCount > 0" class="text-xs text-blue-500 font-medium">
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
// 使用 Windi CSS，无需额外样式
</style>
