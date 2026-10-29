<template>
    <view class="listing-layout-entry">
        <view class="listing-layout-entry__button" role="button" @click="open"><text aria-hidden="true">☷</text><text>调整顺序</text></view>
        <u-popup :show="show" mode="bottom" round="20" :z-index="10180" :safe-area-inset-bottom="true" @close="close">
            <view v-if="show" class="listing-layout" @touchmove.stop.prevent="noop">
                <view class="listing-layout__head"><view><text class="listing-layout__title">调整显示顺序</text><text class="listing-layout__hint">仅保存在本设备，不影响他人</text></view><view class="listing-layout__close" role="button" aria-label="关闭排序" @click="close"><u-icon name="close" size="21" color="#64748b" /></view></view>
                <text class="listing-layout__help">长按左侧句柄拖动，也可以点击上下箭头。</text>
                <scroll-view :scroll-y="!dragId" class="listing-layout__rows" :show-scrollbar="false" @touchmove.stop="noop">
                    <view v-for="(id, index) in order" :key="id" class="listing-layout__row" :class="{ 'is-dragging': dragId === id, 'is-target': !!dragId && targetIndex === index && dragId !== id }">
                        <view class="listing-layout__handle" role="button" :aria-label="'长按拖动' + labels[id]" @touchstart.stop="touchStart" @longpress.stop.prevent="event => startDrag(id, event)" @touchmove.stop.prevent="touchMove" @touchend.stop.prevent="finishDrag" @touchcancel.stop.prevent="cancelDrag">⠿</view>
                        <text class="listing-layout__name">{{ labels[id] }}</text>
                        <view class="listing-layout__arrow" :class="{ 'is-disabled': index === 0 }" role="button" :aria-label="'上移' + labels[id]" @click="move(id, -1)"><u-icon name="arrow-up" size="16" :color="index === 0 ? '#cbd5e1' : '#475569'" /></view>
                        <view class="listing-layout__arrow" :class="{ 'is-disabled': index === order.length - 1 }" role="button" :aria-label="'下移' + labels[id]" @click="move(id, 1)"><u-icon name="arrow-down" size="16" :color="index === order.length - 1 ? '#cbd5e1' : '#475569'" /></view>
                    </view>
                </scroll-view>
                <view class="listing-layout__foot"><text class="listing-layout__reset" @click="reset">恢复默认</text><view class="listing-layout__done" role="button" @click="close">完成</view></view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { defaultListingOrder, listingSectionLabels, moveListingSection, normalizeListingOrder } from '@/addon/hsx_erp/hooks/useErpListingLayout'
const props = defineProps<{ modelValue: string[] }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: string[]): void }>()
const show = ref(false), dragId = ref(''), targetIndex = ref(-1)
const order = computed(() => normalizeListingOrder(props.modelValue))
const labels = listingSectionLabels
const ROW_HEIGHT = 56
let startY = 0, startIndex = -1
const pointY = (event: any): number | null => {
    const point = event?.touches?.[0] || event?.changedTouches?.[0]
    const y = point?.clientY ?? point?.pageY
    return typeof y === 'number' && Number.isFinite(y) ? y : null
}
function noop() {}
function open() { cancelDrag(); show.value = true }
function close() { cancelDrag(); show.value = false }
function move(id: string, delta: number) {
    if (dragId.value) return
    const index = order.value.indexOf(id as any)
    if (index < 0 || index + delta < 0 || index + delta >= order.value.length) return
    emit('update:modelValue', moveListingSection(order.value, id, index + delta))
}
function reset() { cancelDrag(); emit('update:modelValue', [...defaultListingOrder]) }
function touchStart(event: any) { const y = pointY(event); if (y !== null) startY = y }
function startDrag(id: string, event?: any) {
    const index = order.value.indexOf(id as any)
    if (!show.value || index < 0) return
    const y = pointY(event); if (y !== null) startY = y
    startIndex = index; targetIndex.value = index; dragId.value = id
}
function touchMove(event: any) {
    if (!show.value || !dragId.value) return
    const y = pointY(event); if (y === null) return
    targetIndex.value = Math.max(0, Math.min(order.value.length - 1, startIndex + Math.round((y - startY) / ROW_HEIGHT)))
}
function finishDrag() {
    if (show.value && dragId.value && targetIndex.value >= 0) emit('update:modelValue', moveListingSection(order.value, dragId.value, targetIndex.value))
    cancelDrag()
}
function cancelDrag() { dragId.value = ''; targetIndex.value = -1; startIndex = -1 }
</script>

<style scoped lang="scss">
.listing-layout-entry{min-width:0}.listing-layout-entry__button{display:flex;align-items:center;justify-content:flex-end;gap:8rpx;min-height:72rpx;font-size:24rpx;color:#2563eb}.listing-layout{display:flex;flex-direction:column;max-height:calc(100vh - 80rpx);overflow:hidden;background:#fff;border-radius:24rpx 24rpx 0 0;color:#0f172a;box-sizing:border-box}.listing-layout__head{display:flex;align-items:center;justify-content:space-between;gap:20rpx;padding:20rpx 16rpx 4rpx 28rpx;flex-shrink:0}.listing-layout__title{display:block;font-size:17px;font-weight:650;line-height:1.5}.listing-layout__hint{display:block;margin-top:6rpx;font-size:12px;line-height:1.5;color:#64748b}.listing-layout__close{display:flex;align-items:center;justify-content:center;width:84rpx;height:84rpx;flex-shrink:0}.listing-layout__help{display:block;margin:8rpx 28rpx 12rpx;color:#64748b;font-size:12px;line-height:1.7;flex-shrink:0}.listing-layout__rows{height:280px;min-height:0;flex-shrink:1;overscroll-behavior:contain;box-sizing:border-box}.listing-layout__row{display:flex;align-items:center;gap:12rpx;height:56px;min-height:56px;box-sizing:border-box;margin:0 28rpx;border-top:1rpx solid #edf1f5}.listing-layout__row.is-dragging{background:#eff6ff;color:#1d4ed8}.listing-layout__row.is-target{border-top:2px solid #2563eb;background:#f8fafc}.listing-layout__handle{display:flex;align-items:center;justify-content:center;width:44px;height:44px;color:#64748b;font-size:26px;flex-shrink:0;touch-action:none}.listing-layout__name{flex:1;min-width:0;font-size:14px;line-height:1.5}.listing-layout__arrow{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:44px;height:44px}.listing-layout__arrow.is-disabled{opacity:.6}.listing-layout__foot{display:flex;align-items:center;gap:24rpx;padding:18rpx 28rpx;border-top:1rpx solid #e2e8f0;background:#fff;flex-shrink:0}.listing-layout__reset{display:flex;align-items:center;justify-content:center;min-height:44px;padding:0 12rpx;font-size:13px;color:#64748b}.listing-layout__done{display:flex;align-items:center;justify-content:center;flex:1;min-height:44px;border-radius:14rpx;background:#2563eb;color:#fff;font-size:14px;font-weight:600}
</style>

