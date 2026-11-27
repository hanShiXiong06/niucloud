<template>
    <view class="collapse-card" :style="cardStyle">
        <view v-if="showMask" class="component-mask" :style="maskStyle"></view>
        <view class="card-content">
            <view v-if="title || subtitle" class="section-head">
                <view class="head-mark" :style="markStyle"></view>
                <view class="head-copy">
                    <view v-if="title" class="section-title" :style="titleStyle">{{ title }}</view>
                    <view v-if="subtitle" class="section-subtitle" :style="textStyle">{{ subtitle }}</view>
                </view>
            </view>

            <view class="collapse-list">
                <view v-for="(item,index) in items" :key="item.id || index" class="collapse-item" :class="{ 'is-open': isOpen(index) }">
                    <view class="collapse-trigger" @click.stop="toggle(index)">
                        <view class="trigger-copy">
                            <view class="item-title" :style="titleStyle">{{ item.title }}</view>
                            <view v-if="item.summary && !isOpen(index)" class="item-summary" :style="textStyle">{{ item.summary }}</view>
                        </view>
                        <view class="arrow-wrap" :style="arrowStyle"><u-icon :name="isOpen(index) ? 'arrow-up' : 'arrow-down'" size="14" :color="accentColor" /></view>
                    </view>
                    <view v-show="isOpen(index)" class="collapse-content" :style="textStyle">{{ item.content }}</view>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { colorWithAlpha, useProjectCenterDiyStyle } from '../useProjectCenterDiyStyle'

const props = defineProps({ component:{type:Object,default:()=>({})}, index:{type:Number,default:0} })
const emit = defineEmits(['update:componentIsShow'])
const diyStore = useDiyStore()
const openIndexes = ref<number[]>([])
const diyComponent = computed<any>(() => diyStore.mode === 'decorate' ? (diyStore.value[props.index] || props.component) : props.component)
const items = computed<any[]>(() => Array.isArray(diyComponent.value?.items) ? diyComponent.value.items : [])
const title = computed(() => String(diyComponent.value?.title || ''))
const subtitle = computed(() => String(diyComponent.value?.subtitle || ''))
const panelColor = computed(() => diyComponent.value?.panelColor || '#FFFFFF')
const titleColor = computed(() => diyComponent.value?.titleColor || '#26334D')
const textColor = computed(() => diyComponent.value?.textColor || '#667085')
const accentColor = computed(() => diyComponent.value?.accentColor || '#315CF5')
const { cardStyle, showMask, maskStyle } = useProjectCenterDiyStyle(diyComponent, panelColor)
const markStyle = computed(() => ({ backgroundColor:accentColor.value }))
const titleStyle = computed(() => ({ color:titleColor.value }))
const textStyle = computed(() => ({ color:textColor.value }))
const arrowStyle = computed(() => ({ backgroundColor:colorWithAlpha(accentColor.value, 0.08) }))

const syncDefaults = () => {
    const defaults = items.value.map((item,index) => Number(item.defaultOpen) === 1 ? index : -1).filter(index => index >= 0)
    openIndexes.value = Number(diyComponent.value?.accordion) === 1 ? defaults.slice(0,1) : defaults
}
const isOpen = (index:number) => openIndexes.value.includes(index)
const toggle = (index:number) => {
    if (isOpen(index)) { openIndexes.value = openIndexes.value.filter(value => value !== index); return }
    openIndexes.value = Number(diyComponent.value?.accordion) === 1 ? [index] : [...openIndexes.value,index]
}

watch(() => items.value.map(item => `${item.id}:${item.defaultOpen}`).join('|'), syncDefaults)
onMounted(() => { syncDefaults(); emit('update:componentIsShow', true) })
</script>

<style lang="scss" scoped>
.collapse-card{overflow:hidden;border:1rpx solid #e8edf4;border-radius:22rpx;box-shadow:0 8rpx 28rpx rgba(34,52,88,.055)}
.component-mask{position:absolute;z-index:0;inset:0;pointer-events:none}.card-content{position:relative;z-index:1}
.section-head{display:flex;align-items:flex-start;gap:16rpx;padding:28rpx 28rpx 20rpx}.head-mark{width:7rpx;height:34rpx;flex:none;margin-top:4rpx;border-radius:8rpx}.head-copy{min-width:0;flex:1}.section-title{font-size:31rpx;font-weight:650;line-height:42rpx}.section-subtitle{margin-top:6rpx;font-size:23rpx;line-height:34rpx}
.collapse-list{padding:0 24rpx 12rpx}.collapse-item{border-top:1rpx solid #edf0f5}.collapse-trigger{display:flex;min-height:104rpx;align-items:center;gap:20rpx;padding:22rpx 4rpx}.trigger-copy{min-width:0;flex:1}.item-title{font-size:28rpx;font-weight:600;line-height:40rpx}.item-summary{max-width:100%;margin-top:6rpx;overflow:hidden;font-size:23rpx;line-height:34rpx;text-overflow:ellipsis;white-space:nowrap}.arrow-wrap{display:flex;width:52rpx;height:52rpx;flex:none;align-items:center;justify-content:center;border-radius:50%}.collapse-content{padding:0 4rpx 26rpx;white-space:pre-wrap;font-size:25rpx;line-height:44rpx;word-break:break-word}.is-open .collapse-trigger{padding-bottom:14rpx}
</style>
