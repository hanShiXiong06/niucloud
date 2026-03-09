<template>
    <view :style="warpCss" v-if="diyComponent.isShow">
        <view class="diy-xiaoyuan-promo-card" :style="{ background: diyComponent.bgColor || 'linear-gradient(135deg, #c0fe95, #d1ff7c)' }" @click="toLink(diyComponent.url)">
            <view class="promo-left">
                <view class="promo-title">{{ diyComponent.title || '校园帮互助实名认证' }}</view>
                <view class="promo-subtitle">{{ diyComponent.subtitle || '安全可靠，快速认证' }}</view>
                <view class="go-btn">{{ diyComponent.btnText || 'GO' }}</view>
            </view>
            <view class="promo-right">
                <u-icon name="account-fill" size="60" color="#fff"></u-icon>
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

const warpCss = computed(() => {
    let style = ''
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
.diy-xiaoyuan-promo-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30rpx;
    border-radius: 24rpx;
    
    .promo-left {
        .promo-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 8rpx;
        }
        
        .promo-subtitle {
            font-size: 24rpx;
            color: #666;
            margin-bottom: 16rpx;
        }
        
        .go-btn {
            display: inline-block;
            padding: 8rpx 24rpx;
            background: #333;
            color: #fff;
            font-size: 24rpx;
            border-radius: 20rpx;
        }
    }
    
    .promo-right {
        opacity: 0.8;
    }
}
</style>
