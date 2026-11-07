<template>
    <view v-if="page && show" class="coop-sheet is-full is-page">
        <CooperationNav :title="title" :subtitle="subtitle" back :disabled="busy" @back="close" />
        <view v-if="$slots.filters" class="coop-sheet__filters"><slot name="filters" /></view>
        <view v-if="fixedBody" class="coop-sheet__body coop-sheet__body--fixed" @touchmove.stop><slot /></view>
        <scroll-view v-else :scroll-y="!inactive" :scroll-into-view="scrollIntoView" :scroll-with-animation="true" class="coop-sheet__body" @touchmove.stop><slot /></scroll-view>
        <view v-if="$slots.footer" class="coop-sheet__footer"><slot name="footer" /></view>
        <view v-else class="coop-sheet__safe-end" aria-hidden="true" />
    </view>
    <view v-else-if="!page" class="coop-popup-mount"><u-popup :show="show" mode="bottom" :round="12" :z-index="taskSheetZIndex" :safe-area-inset-bottom="false" :close-on-click-overlay="!busy" @close="close">
        <view class="coop-sheet" :class="{ 'is-full': full }" @touchmove.prevent.stop>
            <CooperationNav v-if="full" :title="title" :subtitle="subtitle" back :disabled="busy" @back="close" />
            <view v-else class="coop-sheet__head"><view><text class="coop-sheet__title">{{ title }}</text><view v-if="subtitle" class="coop-sheet__subtitle">{{ subtitle }}</view></view><button class="coop-icon-button" :disabled="busy" aria-label="关闭当前任务" @tap="close"><u-icon name="close" size="19" color="#7a818b" /></button></view>
            <view v-if="$slots.filters" class="coop-sheet__filters"><slot name="filters" /></view>
            <view v-if="fixedBody" class="coop-sheet__body coop-sheet__body--fixed" @touchmove.stop><slot /></view>
            <scroll-view v-else :scroll-y="!inactive" :scroll-into-view="scrollIntoView" :scroll-with-animation="true" class="coop-sheet__body" @touchmove.stop><slot /></scroll-view>
            <view v-if="$slots.footer" class="coop-sheet__footer"><slot name="footer" /></view>
            <view v-else class="coop-sheet__safe-end" aria-hidden="true" />
        </view>
    </u-popup></view>
</template>
<script lang="ts">
// Match Vue's transparent layout in native Mini Programs: an extra custom
// component host otherwise breaks the page's definite-height flex chain.
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import CooperationNav from './CooperationNav.vue'
let taskSheetZIndex = 10090
// #ifdef H5
// Native uni picker/modal layers use 999. The parent already removes its Tabbar.
taskSheetZIndex = 998
// #endif
const props = withDefaults(defineProps<{ show: boolean; title: string; subtitle?: string; busy?: boolean; full?: boolean; inactive?: boolean; page?: boolean; fixedBody?: boolean; scrollIntoView?: string }>(), { subtitle: '', busy: false, full: false, inactive: false, page: false, fixedBody: false, scrollIntoView: '' })
const emit = defineEmits<{ (event: 'close'): void }>()
function close() { if (!props.busy) emit('close') }
</script>
<style scoped lang="scss">
/* Popup roots remain mounted for their native transition but must never share
   the flex height of the active page, including nested, closed inventory sheets. */
.coop-popup-mount { flex: none; height: 0; min-height: 0; width: 100%; overflow: visible; }
.coop-sheet { width:100%;height:86vh;max-height:94vh;min-height:0;display:flex;flex-direction:column;background:#f5f6f8;border-radius:24rpx 24rpx 0 0;overflow:hidden;box-sizing:border-box; }
.coop-sheet.is-full { height:100vh;max-height:100vh;border-radius:0; }
.coop-sheet__filters { flex: none; background: #fff; border-bottom: 1rpx solid #e8edf4; }
/* #ifdef H5 */
@supports(height:100dvh) {
    .coop-sheet { height:86dvh;max-height:94dvh; }
    .coop-sheet.is-full { height:100dvh;max-height:100dvh; }
}
/* #endif */
/* A routed sheet lives inside the task area's allocated flex height. It must
   not claim a second viewport below framework navigation or other siblings. */
.coop-sheet.is-page { height:100%;max-height:100%;flex:1 1 0;min-height:0;border-radius:0; }
.coop-sheet__head { flex:none;padding:12px 16px;gap:12px;background:#fff;border-bottom:1rpx solid #eeeff2;display:flex;justify-content:space-between;align-items:flex-start; }
.coop-sheet__head>view { flex: 1; min-width: 0; }
.coop-sheet__title { font-size:17px;font-weight:650;color:#202124;line-height:1.5;overflow-wrap:break-word; }
.coop-sheet__subtitle { font-size:12px;margin-top:4px;color:#7b838d;line-height:1.6;overflow-wrap:break-word; }
.coop-sheet__body { flex:1 1 0;height:0;min-height:0;min-width:0;width:100%;position:relative;box-sizing:border-box; }
.coop-sheet__body--fixed { display:flex;flex-direction:column;overflow:hidden; }
.coop-sheet__footer { flex:none;min-width:0;background:#fff;border-top:1rpx solid #eeeff2;padding:12px 16px;padding:12px 16px calc(12px + env(safe-area-inset-bottom));box-sizing:border-box; }
.coop-sheet__safe-end { flex:none;height:0;height:env(safe-area-inset-bottom); }
.coop-icon-button { display:flex;align-items:center;justify-content:center;width:44px;height:44px;padding:0;margin:-4px -8px -4px 0;background:transparent;border:0;flex:none; }
.coop-icon-button::after { border:0; }
</style>
