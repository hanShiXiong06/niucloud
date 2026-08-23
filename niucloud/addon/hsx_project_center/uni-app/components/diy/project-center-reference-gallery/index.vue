<template>
    <view class="gallery-card" :style="cardStyle">
        <view v-if="showMask" class="component-mask" :style="maskStyle"></view>
        <view class="card-content">
            <view v-if="title || subtitle" class="section-head">
                <view class="head-mark" :style="markStyle"></view>
                <view class="head-copy"><view v-if="title" class="section-title" :style="titleStyle">{{ title }}</view><view v-if="subtitle" class="section-subtitle" :style="textStyle">{{ subtitle }}</view></view>
            </view>
            <view class="gallery-grid">
                <view v-for="(item,index) in items" :key="item.id || index" class="gallery-item" :style="itemStyle" @click.stop="preview(item)">
                    <view class="image-wrap" :style="imageWrapStyle">
                        <image v-if="item.imageUrl" class="reference-image" :src="img(item.imageUrl)" :mode="imageMode" />
                        <view v-else class="image-placeholder"><u-icon name="photo" size="30" color="#A7B1C2" /><text>上传参考图</text></view>
                        <view class="type-badge" :class="`type-${item.type || 'neutral'}`">{{ typeText(item.type) }}</view>
                        <view v-if="item.imageUrl" class="zoom-badge"><u-icon name="search" size="12" color="#FFFFFF" /><text>查看</text></view>
                    </view>
                    <view class="item-copy"><view class="item-title" :style="titleStyle">{{ item.title }}</view><view v-if="item.description" class="item-description" :style="textStyle">{{ item.description }}</view></view>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { img } from '@/utils/common'
import { useProjectCenterDiyStyle } from '../useProjectCenterDiyStyle'

const props = defineProps({ component:{type:Object,default:()=>({})}, index:{type:Number,default:0} })
const emit = defineEmits(['update:componentIsShow'])
const diyStore = useDiyStore()
const diyComponent = computed<any>(() => diyStore.mode === 'decorate' ? (diyStore.value[props.index] || props.component) : props.component)
const items = computed<any[]>(() => Array.isArray(diyComponent.value?.items) ? diyComponent.value.items : [])
const title = computed(() => String(diyComponent.value?.title || ''))
const subtitle = computed(() => String(diyComponent.value?.subtitle || ''))
const columns = computed(() => Math.min(3,Math.max(1,Number(diyComponent.value?.columns || 2))))
const imageMode = computed<any>(() => diyComponent.value?.imageMode === 'aspectFit' ? 'aspectFit' : 'aspectFill')
const panelColor = computed(() => diyComponent.value?.panelColor || '#FFFFFF')
const titleColor = computed(() => diyComponent.value?.titleColor || '#26334D')
const textColor = computed(() => diyComponent.value?.textColor || '#667085')
const accentColor = computed(() => diyComponent.value?.accentColor || '#12B76A')
const { cardStyle, showMask, maskStyle } = useProjectCenterDiyStyle(diyComponent, panelColor)
const markStyle = computed(() => ({ backgroundColor:accentColor.value }))
const titleStyle = computed(() => ({ color:titleColor.value }))
const textStyle = computed(() => ({ color:textColor.value }))
const itemStyle = computed(() => ({ width:columns.value === 1 ? '100%' : columns.value === 2 ? 'calc(50% - 8rpx)' : 'calc(33.333% - 11rpx)' }))
const imageWrapStyle = computed(() => ({ height:columns.value === 1 ? '340rpx' : columns.value === 2 ? '230rpx' : '160rpx' }))
const typeText = (type:string) => type === 'correct' ? '正确示例' : type === 'wrong' ? '错误示例' : '参考'
const preview = (item:any) => {
    if (!item.imageUrl || diyStore.mode === 'decorate') return
    const urls = items.value.filter(value => value.imageUrl).map(value => img(value.imageUrl))
    uni.previewImage({ current:img(item.imageUrl), urls })
}
onMounted(() => emit('update:componentIsShow', true))
</script>

<style lang="scss" scoped>
.gallery-card{overflow:hidden;border:1rpx solid #e8edf4;border-radius:22rpx;box-shadow:0 8rpx 28rpx rgba(34,52,88,.055)}.component-mask{position:absolute;z-index:0;inset:0;pointer-events:none}.card-content{position:relative;z-index:1}.section-head{display:flex;align-items:flex-start;gap:16rpx;padding:28rpx 28rpx 22rpx}.head-mark{width:7rpx;height:34rpx;flex:none;margin-top:4rpx;border-radius:8rpx}.head-copy{min-width:0;flex:1}.section-title{font-size:31rpx;font-weight:650;line-height:42rpx}.section-subtitle{margin-top:6rpx;font-size:23rpx;line-height:34rpx}.gallery-grid{display:flex;flex-wrap:wrap;gap:16rpx;padding:0 24rpx 26rpx}.gallery-item{min-width:0;overflow:hidden;border:1rpx solid #edf0f5;border-radius:18rpx;background:#fff}.image-wrap{position:relative;overflow:hidden;background:#f4f6fa}.reference-image{display:block;width:100%;height:100%}.image-placeholder{display:flex;height:100%;align-items:center;justify-content:center;flex-direction:column;gap:10rpx;color:#98a2b3;font-size:22rpx}.type-badge{position:absolute;top:12rpx;left:12rpx;padding:7rpx 12rpx;border-radius:20rpx;background:rgba(52,64,84,.86);color:#fff;font-size:19rpx;line-height:1}.type-correct{background:rgba(18,183,106,.92)}.type-wrong{background:rgba(240,68,56,.92)}.zoom-badge{position:absolute;right:10rpx;bottom:10rpx;display:flex;align-items:center;gap:4rpx;padding:7rpx 10rpx;border-radius:20rpx;background:rgba(16,24,40,.62);color:#fff;font-size:18rpx}.item-copy{padding:18rpx}.item-title{overflow:hidden;font-size:25rpx;font-weight:600;line-height:36rpx;text-overflow:ellipsis;white-space:nowrap}.item-description{display:-webkit-box;margin-top:6rpx;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;font-size:21rpx;line-height:31rpx}
</style>
