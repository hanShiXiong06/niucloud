<template>
    <view :style="warpCss" v-if="diyComponent.isShow">
        <view class="diy-xiaoyuan-notice" :style="{ background: diyComponent.bgColor || '#fff7e6' }">
            <u-icon name="volume" size="16" :color="diyComponent.textColor || '#ff9500'"></u-icon>
            <text class="notice-text" :style="{ color: diyComponent.textColor || '#ff9500' }">{{ diyComponent.text || '欢迎使用校园帮' }}</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
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
    return style
})
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-notice {
    display: flex;
    align-items: center;
    padding: 16rpx 24rpx;
    border-radius: 8rpx;
    
    .notice-text {
        margin-left: 12rpx;
        font-size: 26rpx;
        flex: 1;
    }
}
</style>
