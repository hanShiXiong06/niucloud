<template>
    <view :style="warpCss">
        <view class="diy-xiaoyuan-menu-grid">
            <view 
                class="menu-item" 
                v-for="(item, idx) in menuList" 
                :key="idx" 
                @click="toLink(item.link, item.url)"
                :style="{ width: `${100 / (diyComponent.column || 5)}%` }"
            >
                <view class="menu-icon" :style="{ background: item.bgColor }">
                    <image v-if="item.imageUrl" class="menu-image" :src="img(item.imageUrl)" mode="aspectFit" />
                    <u-icon v-else :name="item.icon" size="22" :color="item.iconColor || '#333'"></u-icon>
                </view>
                <text class="menu-name">{{ item.name || '菜单' }}</text>
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
        return diyStore.value[props.index] || props.component || { list: [], column: 5 }
    }
    return props.component || { list: [], column: 5 }
})

const menuList = computed(() => {
    const list = (diyComponent.value.list || []).filter((item: any) => item)
    if (!config.value) {
        return list.filter((item: any) => item.isShow !== false)
    }
    
    return list.filter((item: any) => {
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
            '兼职招聘': 'enable_parttime',
            '约伴组局': 'enable_companion',
            '代排队': 'enable_queue',
            '代上课': 'enable_class',
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
        
        const featureKey = featureMap[item.name]
        if (featureKey) {
            // 如果配置中没有这个开关字段，默认显示
            if (config.value[featureKey] === undefined) return true
            // 如果有开关字段，根据开关值决定
            return config.value[featureKey] !== 0
        }
        
        // 没有对应开关的菜单项，默认显示
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
    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;'
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;'
    return style
})

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
            width: 68rpx;
            height: 68rpx;
            border-radius: 17rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8rpx;

            .menu-image {
                width: 44rpx;
                height: 44rpx;
            }
        }
        
        .menu-name {
            font-size: 22rpx;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            text-align: center;
            line-height: 1.2;
        }
    }
}
</style>
