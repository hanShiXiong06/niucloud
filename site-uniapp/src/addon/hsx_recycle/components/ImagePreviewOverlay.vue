<template>
    <view v-if="visible" class="image-preview-overlay" @tap="handleClose">
        <swiper
            class="image-preview-swiper"
            :current="currentIndex"
            :circular="urls.length > 1"
            @change="handleChange"
        >
            <swiper-item v-for="(url, index) in urls" :key="`${ url }-${ index }`">
                <view class="image-preview-slide">
                    <image
                        class="image-preview-img"
                        :src="url"
                        mode="aspectFit"
                        @tap.stop
                    />
                </view>
            </swiper-item>
        </swiper>

        <view class="image-preview-top">
            <view class="image-preview-counter">{{ currentIndex + 1 }} / {{ urls.length }}</view>
            <view class="image-preview-close" @tap.stop="handleClose">
                <text class="nc-iconfont nc-icon-guanbiV6xx1 image-preview-close__icon"></text>
            </view>
        </view>

        <view v-if="urls.length > 1" class="image-preview-bottom">
            <view class="image-preview-tip">左右滑动查看图片</view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
    visible: boolean
    urls: string[]
    current?: number
}

const props = withDefaults(defineProps<Props>(), {
    current: 0
})

const emit = defineEmits(['update:visible', 'change', 'close'])
const currentIndex = ref(0)

watch(() => props.visible, (value) => {
    if (!value) return
    const maxIndex = Math.max(0, (props.urls || []).length - 1)
    currentIndex.value = Math.min(Math.max(0, Number(props.current || 0)), maxIndex)
})

watch(() => props.current, (value) => {
    if (!props.visible) return
    const maxIndex = Math.max(0, (props.urls || []).length - 1)
    currentIndex.value = Math.min(Math.max(0, Number(value || 0)), maxIndex)
})

const handleChange = (event: any) => {
    currentIndex.value = Number(event?.detail?.current || 0)
    emit('change', currentIndex.value)
}

const handleClose = () => {
    emit('update:visible', false)
    emit('close')
}
</script>

<style scoped lang="scss">
.image-preview-overlay {
    position: fixed;
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    z-index: 2147483647;
    background: rgba(0, 0, 0, 0.96);
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-preview-swiper {
    width: 100vw;
    height: 100vh;
}

.image-preview-slide {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 96rpx 28rpx;
    box-sizing: border-box;
}

.image-preview-img {
    width: 100%;
    height: 100%;
}

.image-preview-top {
    position: fixed;
    left: 0;
    right: 0;
    top: 0;
    z-index: 2147483647;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: calc(28rpx + env(safe-area-inset-top)) 32rpx 20rpx;
    box-sizing: border-box;
    pointer-events: none;
}

.image-preview-counter {
    padding: 8rpx 20rpx;
    border-radius: 999rpx;
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    font-size: 24rpx;
    pointer-events: auto;
}

.image-preview-close {
    width: 64rpx;
    height: 64rpx;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.14);
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: auto;
}

.image-preview-close__icon {
    color: #fff;
    font-size: 34rpx;
}

.image-preview-bottom {
    position: fixed;
    left: 0;
    right: 0;
    bottom: calc(36rpx + env(safe-area-inset-bottom));
    z-index: 2147483647;
    display: flex;
    justify-content: center;
    pointer-events: none;
}

.image-preview-tip {
    padding: 8rpx 20rpx;
    border-radius: 999rpx;
    background: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.82);
    font-size: 22rpx;
}
</style>
