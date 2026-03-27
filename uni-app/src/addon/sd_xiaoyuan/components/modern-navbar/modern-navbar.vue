<template>
  <view class="modern-navbar" :class="{ 'navbar-scrolled': scrolled }" :style="navbarStyle">
    <!-- 状态栏占位 -->
    <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>

    <!-- 导航栏内容 -->
    <view class="navbar-content" :style="{ height: navBarHeight + 'px' }">
      <!-- 左侧 -->
      <view class="navbar-left">
        <slot name="left">
          <view v-if="showBack" class="back-btn" @click="handleBack">
            <text class="back-icon">←</text>
          </view>
        </slot>
      </view>

      <!-- 中间标题 -->
      <view class="navbar-center">
        <slot name="center">
          <text class="navbar-title" :class="{ 'title-show': scrolled }">{{ title }}</text>
        </slot>
      </view>

      <!-- 右侧 -->
      <view class="navbar-right" :style="{ paddingRight: menuButtonRight + 'px' }">
        <slot name="right"></slot>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

interface Props {
  title?: string
  showBack?: boolean
  transparent?: boolean
  background?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  showBack: true,
  transparent: false,
  background: ''
})

const emit = defineEmits<{
  back: []
}>()

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const menuButtonRight = ref(0)
const scrolled = ref(false)

onMounted(() => {
  const sysInfo = uni.getSystemInfoSync()
  statusBarHeight.value = sysInfo.statusBarHeight || 0

  // #ifdef MP-WEIXIN
  const menuButton = uni.getMenuButtonBoundingClientRect()
  menuButtonRight.value = sysInfo.windowWidth - menuButton.left
  navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
  // #endif

  // #ifdef H5
  navBarHeight.value = 44
  // #endif
})

const navbarStyle = computed(() => {
  if (props.transparent && !scrolled.value) {
    return { background: 'transparent' }
  }
  // 使用后台配置的主题色或自定义背景
  return {
    background: props.background || 'var(--primary-gradient, linear-gradient(135deg, #1890ff 0%, #096dd9 100%))'
  }
})

const handleBack = () => {
  emit('back')
  uni.navigateBack()
}

// 暴露方法给父组件
defineExpose({
  setScrolled: (val: boolean) => { scrolled.value = val }
})
</script>

<style lang="scss" scoped>
.modern-navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 999;
  transition: all 0.3s ease;

  &.navbar-scrolled {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  }
}

.navbar-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 32rpx;
}

.navbar-left,
.navbar-right {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  min-width: 80rpx;
}

.navbar-center {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  overflow: hidden;
}

.back-btn {
  width: 60rpx;
  height: 60rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  backdrop-filter: blur(10px);
  transition: all 0.2s ease;

  &:active {
    transform: scale(0.95);
    background: rgba(255, 255, 255, 0.3);
  }

  .back-icon {
    font-size: 36rpx;
    color: #fff;
    font-weight: 500;
  }
}

.navbar-title {
  font-size: 32rpx;
  font-weight: 600;
  color: #fff;
  opacity: 0;
  transform: translateY(-10rpx);
  transition: all 0.3s ease;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;

  &.title-show {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
