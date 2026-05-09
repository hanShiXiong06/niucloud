<template>
    <view :style="warpCss">
        <view class="diy-xiaoyuan-banner">
            <view 
                class="banner-item" 
                v-for="(item, idx) in bannerList" 
                :key="idx" 
                @click="toLink(item.link, item.url)"
                :style="bannerStyle(item)"
                :class="'banner-' + Number(idx) % 6"
            >
                <image v-if="item.imageUrl" class="banner-bg-image" :src="img(item.imageUrl)" mode="aspectFill" />
                <view class="banner-info">
                    <text class="banner-title">{{ item.title }}</text>
                    <text class="banner-desc">{{ item.desc }}</text>
                </view>
                <view class="banner-icon">
                    <u-icon :name="item.icon" size="18" color="rgba(255,255,255,0.85)"></u-icon>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { redirect, img } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'
import { useFeatureCheck } from '../../../composables/useFeatureCheck'
import { isPublishEntryPath, ensureCanPublish } from '../../../composables/usePublishAuth'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()
const { config, loadConfig } = useFeatureCheck()

onMounted(() => {
    loadConfig()
})

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index]
    } else {
        return props.component
    }
})

const bannerList = computed(() => {
    // 配置未加载时，返回空数组，确保不显示任何banner
    if (!config.value) return []
    
    return (diyComponent.value.list || []).filter((item: any) => {
        if (item.isShow === false) return false
        
        // 根据功能开关过滤
        const featureMap: Record<string, string> = {
            '帮我买': 'enable_buy',
            '帮我送': 'enable_send',
            '代取快递': 'enable_express',
            '帮打印': 'enable_print',
            '扔垃圾': 'enable_trash',
            '帮搬运': 'enable_carry',
            '代清洁': 'enable_clean',
            '帮帮忙': 'enable_help',
            '游戏陪练': 'enable_game',
            '代排队': 'enable_queue',
            '代占座': 'enable_seat',
            '房屋租赁': 'enable_house',
            '课程表': 'enable_schedule',
            '拼单好饭': 'enable_group',
            '闲置市场': 'enable_secondhand',
            '失物招领': 'enable_lost_found',
            '校园树洞': 'enable_community',
            '表白墙': 'enable_confession',
            '每日签到': 'enable_sign',
            '积分商城': 'enable_points_mall'
        }
        
        const featureKey = featureMap[item.title]
        if (featureKey) {
            return config.value[featureKey] !== 0
        }
        
        return true
    })
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

const bannerStyle = (item: any) => {
    if (item.imageUrl) return {}
    return { background: item.bgColor }
}

const toLink = async (link: any, url?: string) => {
    if (diyStore.mode == 'decorate') return
    let target = ''
    if (link && link.url) target = String(link.url)
    else if (url) target = String(url)
    if (target && isPublishEntryPath(target)) {
        const ok = await ensureCanPublish(target)
        if (!ok) return
    }
    if (link && link.name) {
        redirect({ url: link.url, mode: link.mode })
        return
    }
    if (url) redirect({ url })
}
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-banner {
    display: flex;
    flex-wrap: wrap;
    gap: 12rpx;
    padding: 0;
    
    .banner-item {
        flex: 1;
        min-width: calc(33.33% - 8rpx);
        height: 100rpx;
        border-radius: 16rpx;
        padding: 0 12rpx;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;

        .banner-bg-image {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
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
}
</style>
