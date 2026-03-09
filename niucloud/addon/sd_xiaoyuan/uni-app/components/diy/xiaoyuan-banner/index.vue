<template>
    <view :style="warpCss">
        <view class="diy-xiaoyuan-banner">
            <view 
                class="banner-item" 
                v-for="(item, idx) in bannerList" 
                :key="idx" 
                @click="toLink(item.url)"
                :style="{ background: item.bgColor }"
                :class="'banner-' + Number(idx) % 6"
            >
                <view class="banner-info">
                    <text class="banner-title">{{ item.title }}</text>
                    <text class="banner-desc">{{ item.desc }}</text>
                </view>
                <view class="banner-icon">
                    <u-icon :name="item.icon" size="18" color="rgba(255,255,255,0.85)"></u-icon>
                </view>
            </view>
            <view class="clear"></view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { redirect } from '@/utils/common'
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

const bannerList = computed(() => {
    return (diyComponent.value.list || []).filter((item: any) => item.isShow !== false)
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

const toLink = (url: string) => {
    if (diyStore.mode == 'decorate') return
    if (url) redirect({ url })
}
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-banner {
    padding: 0;
    
    .banner-item {
        float: left;
        width: calc(33.33% - 8rpx);
        margin-right: 12rpx;
        margin-bottom: 12rpx;
        height: 100rpx;
        border-radius: 16rpx;
        padding: 0 12rpx;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
        
        &:nth-child(3n) {
            margin-right: 0;
        }
        
        &::after {
            content: '';
            position: absolute;
            right: -20rpx;
            bottom: -20rpx;
            width: 80rpx;
            height: 80rpx;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
        }
        
        .banner-info {
            z-index: 1;
            flex: 1;
            overflow: hidden;
            
            .banner-title {
                display: block;
                font-size: 22rpx;
                font-weight: bold;
                color: #fff;
                margin-bottom: 4rpx;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .banner-desc {
                display: block;
                font-size: 18rpx;
                color: rgba(255, 255, 255, 0.85);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }
        
        .banner-icon {
            z-index: 1;
            opacity: 0.8;
            flex-shrink: 0;
            width: 36rpx;
            height: 36rpx;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    }
    
    .clear {
        clear: both;
    }
}
</style>
