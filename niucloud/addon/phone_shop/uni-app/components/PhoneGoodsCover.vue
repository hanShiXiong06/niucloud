<template>
    <view class="phone-goods-cover" :class="`phone-goods-cover--${variant}`">
        <image class="phone-goods-cover__image" :src="coverSrc" mode="aspectFill" @error="useFallback" />
        <view v-if="gradeText || recentTagText" class="phone-goods-cover__tags">
            <view v-if="gradeText" class="phone-goods-cover__grade">{{ gradeText }}</view>
            <view v-if="recentTagText" class="phone-goods-cover__recent" :class="'phone-goods-cover__recent--' + recentTag">{{ recentTagText }}</view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { img } from '@/utils/common'

const props = withDefaults(defineProps<{
    src?: string
    grade?: string
    variant?: 'row' | 'grid'
    recentTag?: 'new' | 'updated' | ''
}>(), {
    src: '',
    grade: '',
    variant: 'row',
    recentTag: ''
})

const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

const coverSrc = computed(() => img(failed.value || !props.src ? 'static/resource/images/diy/shop_default.jpg' : props.src))
const gradeText = computed(() => String(props.grade || '').trim())
const recentTagText = computed(() => props.recentTag === 'new' ? '最新上架' : props.recentTag === 'updated' ? '最近调价' : '')
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
    height: 190rpx;
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

.phone-goods-cover__tags {
    position: absolute;
    z-index: 2;
    top: 10rpx;
    left: 10rpx;
    right: 10rpx;
    display: flex;
    align-items: center;
    gap: 6rpx;
    min-width: 0;
}

.phone-goods-cover__grade {
    min-width: 0;
    height: 38rpx;
    padding: 0 8rpx;
    overflow: hidden;
    box-sizing: border-box;
    border: 1rpx solid rgba(255, 255, 255, .76);
    border-radius: 8rpx;
    color: #fff;
    background: rgba(31, 41, 55, .82);
    box-shadow: 0 3rpx 9rpx rgba(15, 23, 42, .12);
    font-size: 20rpx;
    font-weight: 600;
    line-height: 38rpx;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.phone-goods-cover__recent {
    flex-shrink: 0;
    height: 38rpx;
    padding: 0 8rpx;
    box-sizing: border-box;
    border-radius: 8rpx;
    color: #fff;
    font-size: 20rpx;
    font-weight: 600;
    line-height: 38rpx;
    white-space: nowrap;
    background: #047857;
}

.phone-goods-cover__recent--updated { background: #b45309; }
</style>
