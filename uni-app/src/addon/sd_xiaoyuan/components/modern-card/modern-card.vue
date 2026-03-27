<template>
  <view
    class="modern-card"
    :class="{ 'card-hover': hover, 'card-shadow': shadow }"
    @touchstart="handleTouchStart"
    @touchend="handleTouchEnd"
    @click="handleClick"
  >
    <slot></slot>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  shadow?: boolean
  clickable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  shadow: true,
  clickable: false
})

const emit = defineEmits<{
  click: []
}>()

const hover = ref(false)

const handleTouchStart = () => {
  if (props.clickable) {
    hover.value = true
  }
}

const handleTouchEnd = () => {
  if (props.clickable) {
    hover.value = false
  }
}

const handleClick = () => {
  if (props.clickable) {
    emit('click')
  }
}
</script>

<style lang="scss" scoped>
.modern-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 32rpx;
  margin: 24rpx;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

  &.card-shadow {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  }

  &.card-hover {
    transform: scale(0.98);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
  }
}
</style>
