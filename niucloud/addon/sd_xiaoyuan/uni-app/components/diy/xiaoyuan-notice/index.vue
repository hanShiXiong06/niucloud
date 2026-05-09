<template>
    <view :style="warpCss" v-if="diyComponent.isShow">
        <u-notice-bar 
            :text="diyComponent.text || '欢迎使用校园帮'"
            icon="volume"
            :color="diyComponent.textColor || '#ff9500'"
            :bgColor="diyComponent.bgColor || '#fff7e6'"
        ></u-notice-bar>
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
    overflow: hidden;
    
    .notice-scroll-wrapper {
        flex: 1;
        margin-left: 12rpx;
        overflow: hidden;
        position: relative;
        height: 40rpx;
        
        .notice-scroll {
            white-space: nowrap;
            position: absolute;
            left: 0;
            top: 0;
            
            &.scrolling {
                animation: scroll-left 2s linear infinite;
            }
            
            .notice-text {
                font-size: 26rpx;
                display: inline-block;
                line-height: 40rpx;
            }
        }
    }
}

@keyframes scroll-left {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}
</style>
