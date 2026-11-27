<template>
  <view
    :class="[
      'bg-white rounded-lg p-4 mb-3',
      clickable ? 'cursor-pointer' : ''
    ]"
    @click="handleClick"
  >
    <!-- 已选择地址 -->
    <view v-if="name" class="relative">
      <view class="flex items-center gap-2 mb-3">
        <up-icon name="account" size="16" color="#3b82f6"></up-icon>
        <text class="text-sm font-medium">{{ name }}</text>
        <up-icon name="phone" size="16" color="#3b82f6"></up-icon>
        <text class="text-sm font-medium">{{ mobile }}</text>
      </view>
      <view class="flex items-center gap-2">
        <up-icon name="map" size="16" color="#3b82f6"></up-icon>
        <text class="text-sm text-gray-600 flex-1">{{ areaText }} {{ detailAddress }}</text>
      </view>
      <view v-if="showEditIcon" class="absolute top-0 right-0 p-1 bg-blue-50 rounded">
        <up-icon name="edit-pen" size="14" color="#3b82f6"></up-icon>
      </view>
    </view>

    <!-- 未选择地址 -->
    <view v-else class="flex items-center justify-between py-2">
      <view class="flex items-center gap-2">
        <up-icon name="map" size="18" color="#fff"></up-icon>
        <text class="text-white font-medium">{{ emptyText }}</text>
      </view>
      <up-icon name="arrow-right" size="16" color="#fff"></up-icon>
    </view>
  </view>
</template>

<script setup lang="ts">
interface Props {
  name?: string
  mobile?: string
  areaText?: string
  detailAddress?: string
  clickable?: boolean
  showEditIcon?: boolean
  emptyText?: string
}

withDefaults(defineProps<Props>(), {
  clickable: true,
  showEditIcon: true,
  emptyText: '选择寄件地址'
})

const emit = defineEmits<{
  click: []
}>()

const handleClick = () => {
  emit('click')
}
</script>

<style scoped lang="scss">
.cursor-pointer {
  cursor: pointer;
  transition: all 0.3s;

  &:active {
    transform: scale(0.98);
    background: #f8fafc;
  }
}
</style>
