<template>
    <view v-if="items.length" class="income-board" :style="cardStyle">
        <view v-if="showMask" class="component-mask" :style="maskStyle"></view>
        <view class="board-content">
            <view class="board-head">
                <view class="head-copy">
                    <view class="title-row"><view class="head-mark" :style="{ backgroundColor: accentColor }"></view><text class="board-title" :style="{ color: titleColor }">{{ title }}</text></view>
                    <view v-if="subtitle || displayDate" class="board-subtitle" :style="{ color: textColor }">
                        <text v-if="displayDate">{{ displayDate }}</text><text v-if="displayDate && subtitle"> · </text><text>{{ subtitle }}</text>
                    </view>
                </view>
                <view v-if="badgeText" class="board-badge" :style="badgeStyle"><view class="badge-dot"></view><text>{{ badgeText }}</text></view>
            </view>

            <swiper v-if="pages.length > 1" class="board-swiper" :class="{ 'page-scroll-priority': !manualSwipe }" :style="boardHeightStyle" vertical circular
                :autoplay="autoplay" :interval="interval" :duration="460" :disable-touch="!manualSwipe">
                <swiper-item v-for="(page,pageIndex) in pages" :key="pageIndex">
                    <view class="board-page"><view v-for="item in page" :key="`${pageIndex}-${item.rank_no}`" class="board-row">
                        <view class="rank-badge" :class="rankClass(item.rank_no)" :style="rankStyle(item.rank_no)">{{ rankText(item.rank_no) }}</view>
                        <text class="store-name" :style="{ color: titleColor }">{{ item.store_name }}</text>
                        <text class="income-amount" :style="{ color: amountColor }">¥{{ amountText(item.income_amount) }}</text>
                    </view></view>
                </swiper-item>
            </swiper>
            <view v-else class="board-page" :style="boardHeightStyle">
                <view v-for="item in pages[0] || []" :key="item.rank_no" class="board-row">
                    <view class="rank-badge" :class="rankClass(item.rank_no)" :style="rankStyle(item.rank_no)">{{ rankText(item.rank_no) }}</view>
                    <text class="store-name" :style="{ color: titleColor }">{{ item.store_name }}</text>
                    <text class="income-amount" :style="{ color: amountColor }">¥{{ amountText(item.income_amount) }}</text>
                </view>
            </view>

            <view v-if="showDisclaimer && disclaimer" class="board-disclaimer" :style="{ color: textColor }">
                <u-icon name="info-circle" :color="textColor" size="13" /><text>{{ disclaimer }}</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { colorWithAlpha, useProjectCenterDiyStyle } from '../useProjectCenterDiyStyle'

const props = defineProps({ component: { type: Object, default: () => ({}) }, index: { type: Number, default: 0 } })
const emit = defineEmits(['update:componentIsShow'])
const diyStore = useDiyStore()
const diyComponent = computed<any>(() => diyStore.mode === 'decorate' ? (diyStore.value[props.index] || props.component) : props.component)
const previewItems = computed<any[]>(() => Array.isArray(diyComponent.value?.previewItems) ? diyComponent.value.previewItems : [])
const runtimeItems = computed<any[]>(() => Array.isArray(diyComponent.value?.runtimeBoard) ? diyComponent.value.runtimeBoard : [])
const sourceItems = computed<any[]>(() => diyStore.mode === 'decorate' ? previewItems.value : runtimeItems.value)
const items = computed(() => sourceItems.value.map((item, index) => ({
    rank_no: Math.max(1, Number(item?.rank_no || index + 1)),
    store_name: String(item?.store_name || '').trim(),
    income_amount: Number(item?.income_amount || 0)
})).filter(item => item.store_name))
const visibleRows = computed(() => [1, 3, 5].includes(Number(diyComponent.value?.visibleRows)) ? Number(diyComponent.value.visibleRows) : 3)
const pages = computed(() => {
    const result: any[][] = []
    for (let index = 0; index < items.value.length; index += visibleRows.value) result.push(items.value.slice(index, index + visibleRows.value))
    return result
})
const title = computed(() => String(diyComponent.value?.title || '门店收益榜'))
const subtitle = computed(() => String(diyComponent.value?.subtitle || ''))
const badgeText = computed(() => String(diyComponent.value?.badgeText || ''))
const disclaimer = computed(() => String(diyComponent.value?.disclaimer || ''))
const autoplay = computed(() => Number(diyComponent.value?.autoplay) === 1 && pages.value.length > 1)
const manualSwipe = computed(() => Number(diyComponent.value?.manualSwipe) === 1)
const interval = computed(() => Math.min(8000, Math.max(1500, Number(diyComponent.value?.interval || 2600))))
const showDisclaimer = computed(() => Number(diyComponent.value?.showDisclaimer) === 1)
const displayDate = computed(() => {
    if (Number(diyComponent.value?.showDate) !== 1) return ''
    const value = String(diyComponent.value?.runtimeDate || (diyStore.mode === 'decorate' ? '20260818' : ''))
    return /^\d{8}$/.test(value) ? `${value.slice(0, 4)}-${value.slice(4, 6)}-${value.slice(6)}` : ''
})
const panelColor = computed(() => diyComponent.value?.panelColor || '#FFFFFF')
const titleColor = computed(() => diyComponent.value?.titleColor || '#26334D')
const textColor = computed(() => diyComponent.value?.textColor || '#667085')
const accentColor = computed(() => diyComponent.value?.accentColor || '#315CF5')
const amountColor = computed(() => diyComponent.value?.amountColor || '#F04438')
const { cardStyle, showMask, maskStyle } = useProjectCenterDiyStyle(diyComponent, panelColor)
const boardHeightStyle = computed(() => ({ height: `${Math.max(1, Math.min(visibleRows.value, items.value.length)) * 82}rpx` }))
const badgeStyle = computed(() => ({ color: accentColor.value, backgroundColor: colorWithAlpha(accentColor.value, .08) }))

const rankText = (rank: number) => `TOP ${String(rank).padStart(2, '0')}`
const rankClass = (rank: number) => rank <= 3 ? `rank-${rank}` : ''
const rankStyle = (rank: number) => rank > 3 ? { color: accentColor.value, backgroundColor: colorWithAlpha(accentColor.value, .07) } : {}
const amountText = (amount: number) => Number.isFinite(Number(amount)) ? Number(amount).toFixed(2) : '0.00'
const syncVisible = () => emit('update:componentIsShow', items.value.length > 0)

watch(() => items.value.length, syncVisible)
onMounted(syncVisible)
</script>

<style lang="scss" scoped>
.income-board{position:relative;overflow:hidden;border:1rpx solid rgba(152,162,179,.18);border-radius:24rpx;box-shadow:0 10rpx 30rpx rgba(34,52,88,.065)}
.component-mask{position:absolute;z-index:0;inset:0;pointer-events:none}.board-content{position:relative;z-index:1;padding:24rpx 23rpx 19rpx}.board-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16rpx;padding:0 1rpx 16rpx;border-bottom:1rpx solid rgba(152,162,179,.16)}.head-copy{min-width:0;flex:1}.title-row{display:flex;align-items:center;gap:11rpx}.head-mark{width:7rpx;height:29rpx;flex:none;border-radius:8rpx}.board-title{overflow:hidden;font-size:29rpx;font-weight:750;line-height:39rpx;text-overflow:ellipsis;white-space:nowrap}.board-subtitle{margin-top:4rpx;overflow:hidden;font-size:20rpx;line-height:29rpx;text-overflow:ellipsis;white-space:nowrap}.board-badge{display:flex;max-width:150rpx;height:38rpx;flex:none;align-items:center;gap:6rpx;overflow:hidden;padding:0 12rpx;border-radius:20rpx;font-size:18rpx;font-weight:650;white-space:nowrap}.board-badge text{overflow:hidden;text-overflow:ellipsis}.badge-dot{width:8rpx;height:8rpx;flex:none;border-radius:50%;background:currentColor;box-shadow:0 0 0 4rpx rgba(49,92,245,.08)}
.board-swiper,.board-page{width:100%}.board-swiper.page-scroll-priority{pointer-events:none;touch-action:pan-y}.board-page{overflow:hidden}.board-row{display:flex;height:82rpx;align-items:center;gap:12rpx;border-bottom:1rpx solid rgba(152,162,179,.12)}.board-row:last-child{border-bottom:0}.rank-badge{display:flex;width:84rpx;height:36rpx;flex:none;align-items:center;justify-content:center;border-radius:11rpx;font-size:18rpx;font-weight:750}.rank-1{background:linear-gradient(135deg,#fff3d6,#ffe2a3);color:#b54708}.rank-2{background:linear-gradient(135deg,#f2f4f7,#e4e7ec);color:#475467}.rank-3{background:linear-gradient(135deg,#fff1eb,#fed7c3);color:#c4320a}.store-name{min-width:0;flex:1;overflow:hidden;font-size:24rpx;font-weight:600;text-overflow:ellipsis;white-space:nowrap}.income-amount{flex:none;font-size:26rpx;font-weight:750;font-variant-numeric:tabular-nums;letter-spacing:-.3rpx}.board-disclaimer{display:flex;align-items:flex-start;gap:8rpx;margin-top:14rpx;padding:11rpx 13rpx;border-radius:12rpx;background:rgba(248,250,252,.82);font-size:18rpx;line-height:27rpx}.board-disclaimer text{min-width:0;flex:1}
</style>
