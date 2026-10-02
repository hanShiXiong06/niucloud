<template>
  <up-popup :show="show" mode="bottom" :round="8" :z-index="zIndex" :safeAreaInsetBottom="false"
    :closeOnClickOverlay="closeOnOverlay && !busy" :overlayStyle="{ touchAction: 'none' }" bgColor="var(--recycle-bg-card)" @close="close" @open="$emit('open')">
    <view class="order-task-popup" :style="{ height }" @touchmove.stop>
      <view class="order-task-popup__header" @touchmove.stop.prevent>
        <view class="order-task-popup__heading">
          <text class="order-task-popup__title">{{ title }}</text>
          <text v-if="subtitle" class="order-task-popup__subtitle">{{ subtitle }}</text>
        </view>
        <button class="order-task-popup__close" aria-label="关闭弹窗" :disabled="busy" @tap="close">
          <up-icon name="close" size="20" color="var(--recycle-text-sub)" />
        </button>
      </view>
      <scroll-view v-if="scrollable" scroll-y :show-scrollbar="false" class="order-task-popup__scroll" @touchmove.stop>
        <slot />
      </scroll-view>
      <view v-else class="order-task-popup__body"><slot /></view>
      <view v-if="$slots.primary || $slots.secondary || $slots.footer" class="order-task-popup__footer" @touchmove.stop.prevent>
        <view v-if="$slots.primary || $slots.secondary" class="order-task-popup__actions">
          <view v-if="$slots.secondary" class="order-task-popup__secondary"><slot name="secondary" /></view>
          <view v-if="$slots.primary" class="order-task-popup__primary"><slot name="primary" /></view>
        </view>
        <view v-else class="order-task-popup__single-action"><slot name="footer" /></view>
      </view>
    </view>
  </up-popup>
</template>

<script setup lang="ts">
import { useRecyclePopupLock } from '../../../hooks/useRecyclePopupScroll'

const props = withDefaults(defineProps<{
  show: boolean
  title: string
  subtitle?: string
  height?: string
  scrollable?: boolean
  busy?: boolean
  closeOnOverlay?: boolean
  zIndex?: number
}>(), { height: '78vh', scrollable: true, busy: false, closeOnOverlay: true, zIndex: 10070 })
const emit = defineEmits<{ close: []; open: [] }>()
useRecyclePopupLock(() => props.show)
const close = () => { if (!props.busy) emit('close') }
</script>

<style scoped lang="scss">
.order-task-popup { display: flex; flex-direction: column; max-height: 88vh; padding-bottom: env(safe-area-inset-bottom); box-sizing: border-box; color: var(--recycle-text-main); background: var(--recycle-bg-card); overflow: hidden; }
.order-task-popup__header { display: flex; align-items: center; gap: 12px; flex-shrink: 0; padding: 16px 16px 12px; border-bottom: 1rpx solid var(--recycle-line); }
.order-task-popup__heading { flex: 1; min-width: 0; }
.order-task-popup__title { display: block; font-size: 17px; line-height: 24px; font-weight: 600; overflow-wrap: anywhere; }
.order-task-popup__subtitle { display: block; margin-top: 4px; font-size: 12px; line-height: 18px; color: var(--recycle-text-sub); overflow-wrap: anywhere; }
.order-task-popup__close { display: flex; align-items: center; justify-content: center; flex: 0 0 40px; width: 40px; height: 40px; padding: 0; margin: 0; border: 0; border-radius: 4px; background: var(--recycle-bg-soft); }
.order-task-popup__close::after { border: 0; }
.order-task-popup__close[disabled] { opacity: .45; }
.order-task-popup__scroll { flex: 1; height: 0; min-height: 0; overscroll-behavior: contain; }
.order-task-popup__body { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
.order-task-popup__body :deep(scroll-view), .order-task-popup__body :deep(uni-scroll-view) { overscroll-behavior: contain; }
.order-task-popup__footer { flex-shrink: 0; padding: 12px 16px; border-top: 1rpx solid var(--recycle-line); background: var(--recycle-bg-card); }
// 使用原生 view 承担布局，避免小程序自定义组件 / slot 包装影响按钮宽度。
.order-task-popup__actions { display: flex; align-items: stretch; width: 100%; gap: 12px; }
.order-task-popup__secondary { flex: 1; min-width: 0; }
.order-task-popup__primary { flex: 2; min-width: 0; }
.order-task-popup__single-action { width: 100%; }
</style>
