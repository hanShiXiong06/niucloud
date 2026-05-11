<template>
    <view :style="warpCss">
        <view class="diy-xiaoyuan-search" @click="toSearch">
            <u-icon name="search" size="16" color="#999"></u-icon>
            <text class="search-text">{{ diyComponent.text || '搜索任务/服务' }}</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { img, redirect } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index]
    } else {
        return props.component
    }
})

const warpCss = computed(() => {
    let style = ''
    if (diyComponent.value.componentStartBgColor) {
        if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) {
            style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`
        } else {
            style += 'background-color:' + diyComponent.value.componentStartBgColor + ';'
        }
    }
    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;'
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;'
    return style
})

const toSearch = () => {
    if (diyStore.mode == 'decorate') return
    redirect({ url: '/addon/sd_xiaoyuan/pages/search/index' })
}
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-search {
    display: flex;
    align-items: center;
    background: #f5f5f5;
    border-radius: 32rpx;
    padding: 16rpx 24rpx;
    
    .search-text {
        margin-left: 12rpx;
        font-size: 26rpx;
        color: #999;
    }
}
</style>
