<template>
    <u-popup :show="show" mode="bottom" :round="12" :z-index="taskSheetZIndex" :safe-area-inset-bottom="false" :close-on-click-overlay="!busy" @close="close">
        <view class="coop-sheet" :class="{ 'is-full': full }" @touchmove.prevent.stop>
            <CooperationNav v-if="full" :title="title" :subtitle="subtitle" back :disabled="busy" @back="close" />
            <view v-else class="coop-sheet__head"><view><text class="coop-sheet__title">{{ title }}</text><view v-if="subtitle" class="coop-sheet__subtitle">{{ subtitle }}</view></view><button class="coop-icon-button" :disabled="busy" aria-label="关闭当前任务" @tap="close"><u-icon name="close" size="19" color="#7a818b" /></button></view>
            <scroll-view :scroll-y="!inactive" class="coop-sheet__body" @touchmove.stop><slot /></scroll-view>
            <view v-if="$slots.footer" class="coop-sheet__footer"><slot name="footer" /></view>
        </view>
    </u-popup>
</template>
<script setup lang="ts">
import CooperationNav from './CooperationNav.vue'
let taskSheetZIndex = 10090
// #ifdef H5
// Native uni picker/modal layers use 999. The parent already removes its Tabbar.
taskSheetZIndex = 998
// #endif
const props = withDefaults(defineProps<{ show: boolean; title: string; subtitle?: string; busy?: boolean; full?: boolean; inactive?: boolean }>(), { subtitle: '', busy: false, full: false, inactive: false })
const emit = defineEmits<{ (event: 'close'): void }>()
function close() { if (!props.busy) emit('close') }
</script>
<style scoped lang="scss">
.coop-sheet { height: 86vh; max-height: 94vh; display: flex; flex-direction: column; background: #f5f6f8; border-radius: 24rpx 24rpx 0 0; overflow: hidden; }.coop-sheet.is-full { height: 100vh; max-height: 100vh; border-radius: 0; }
/* #ifdef H5 */
.coop-sheet.is-full { height: 100dvh; max-height: 100dvh; }
/* #endif */.coop-sheet__head { flex: none; padding: 26rpx 30rpx; background: #fff; border-bottom: 1rpx solid #eeeff2; display: flex; justify-content: space-between; align-items: flex-start; gap: 24rpx; }.coop-sheet__title { font-size: 32rpx; font-weight: 650; color: #202124; line-height: 1.5; }.coop-sheet__subtitle { margin-top: 7rpx; font-size: 23rpx; color: #7b838d; line-height: 1.6; }.coop-sheet__body { flex: 1; height: 0; min-height: 0; }.coop-sheet__footer { background: #fff; border-top: 1rpx solid #eeeff2; padding: 22rpx 30rpx calc(24rpx + env(safe-area-inset-bottom)); flex: none; }.coop-icon-button { display: flex; align-items: center; justify-content: center; width: 76rpx; height: 76rpx; padding: 0; margin: -12rpx -17rpx -10rpx 0; background: transparent; border: 0; flex: none; }.coop-icon-button::after { border: 0; }
</style>
