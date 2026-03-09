<template>
    <view :style="warpCss">
        <view class="diy-xiaoyuan-menu-grid">
            <view 
                class="menu-item" 
                v-for="(item, idx) in menuList" 
                :key="idx" 
                @click="toLink(item.url)"
                :style="{ width: `${100 / (diyComponent.column || 5)}%` }"
            >
                <view class="menu-icon" :style="{ background: item.bgColor }">
                    <u-icon :name="item.icon" size="22" :color="item.iconColor || '#333'"></u-icon>
                </view>
                <text class="menu-name">{{ item.name }}</text>
            </view>
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

const menuList = computed(() => {
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
    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;'
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;'
    return style
})

const toLink = (url: string) => {
    if (diyStore.mode == 'decorate') return
    if (url) redirect({ url })
}
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-menu-grid {
    display: flex;
    flex-wrap: wrap;
    padding: 20rpx 0;
    
    .menu-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12rpx 0;
        
        .menu-icon {
            width: 80rpx;
            height: 80rpx;
            border-radius: 20rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8rpx;
        }
        
        .menu-name {
            font-size: 22rpx;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            text-align: center;
        }
    }
}
</style>
