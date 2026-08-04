<template>
    <view class="phone-goods-cover" :class="`phone-goods-cover--${variant}`">
        <image class="phone-goods-cover__image" :src="coverSrc" mode="aspectFit" @error="useFallback" />
        <view v-if="gradeText" class="phone-goods-cover__grade">{{ gradeText }}</view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { img } from '@/utils/common'

const props = withDefaults(defineProps<{
    src?: string
    grade?: string
    variant?: 'row' | 'grid'
}>(), {
    src: '',
    grade: '',
    variant: 'row'
})

const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

const coverSrc = computed(() => img(failed.value || !props.src ? 'static/resource/images/diy/shop_default.jpg' : props.src))
const gradeText = computed(() => String(props.grade || '').trim())
const useFallback = () => { failed.value = true }
</script>

<style lang="scss" scoped>
.phone-goods-cover {
    position: relative;
    flex-shrink: 0;
    overflow: hidden;
    box-sizing: border-box;
    background: #f7f8fa;
}

.phone-goods-cover--row {
    width: 190rpx;
    height: 230rpx;
    border-radius: var(--rounded-mid);
}

.phone-goods-cover--grid {
    width: 100%;
    height: 344rpx;
    border-radius: var(--rounded-mid) var(--rounded-mid) 0 0;
}

.phone-goods-cover__image {
    display: block;
    width: 100%;
    height: 100%;
}

.phone-goods-cover__grade {
    position: absolute;
    z-index: 2;
    top: 10rpx;
    left: 10rpx;
    max-width: calc(100% - 20rpx);
    height: 38rpx;
    padding: 0 12rpx;
    overflow: hidden;
    box-sizing: border-box;
    border: 1rpx solid rgba(255, 255, 255, .76);
    border-radius: 8rpx;
    color: #fff;
    background: rgba(31, 41, 55, .82);
    box-shadow: 0 3rpx 9rpx rgba(15, 23, 42, .12);
    font-size: 21rpx;
    font-weight: 600;
    line-height: 38rpx;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
