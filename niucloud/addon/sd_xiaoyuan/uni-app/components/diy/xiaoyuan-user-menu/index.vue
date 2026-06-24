<template>
    <view class="diy-xiaoyuan-user-menu" v-if="showBlock">
        <view class="menu-section" :style="sectionStyle">
            <view class="section-title" v-if="diyComponent.title">{{ diyComponent.title }}</view>
            <view class="menu-grid">
                <view
                    class="menu-item"
                    v-for="(item, idx) in visibleList"
                    :key="idx"
                    :style="{ flex: `0 0 ${colPercent}` }"
                    @click="onItemClick(item)"
                >
                    <view class="menu-icon-bg" :style="{ background: item.bgColor || item.bg || '#eee' }">
                        <image v-if="item.imageUrl" class="menu-img" :src="img(item.imageUrl)" mode="aspectFit" />
                        <u-icon v-else :name="item.icon" size="28" :color="item.iconColor || item.color || '#fff'"></u-icon>
                    </view>
                    <text class="menu-label">{{ item.name }}</text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { redirect, img } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import { useFeatureCheck } from '../../../composables/useFeatureCheck'
import { getRunnerInfo } from '../../../api/runner'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()
const memberStore = useMemberStore()
const { config, loadConfig } = useFeatureCheck()

const runtimeList = ref<any[]>([])
const configReady = ref(false)

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index] || props.component
    }
    return props.component
})

const colNum = computed(() => {
    const n = parseInt(String(diyComponent.value.column || 5), 10)
    return n >= 3 && n <= 5 ? n : 5
})

const colPercent = computed(() => `${100 / colNum.value}%`)

const sectionStyle = computed(() => {
    let style = ''
    if (diyComponent.value.componentStartBgColor) {
        if (diyComponent.value.componentEndBgColor && diyComponent.value.componentStartBgColor !== diyComponent.value.componentEndBgColor) {
            style += `background:linear-gradient(${diyComponent.value.componentGradientAngle || 'to bottom'},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`
        } else {
            style += `background-color:${diyComponent.value.componentStartBgColor};`
        }
    }
    const r = diyComponent.value.topRounded || diyComponent.value.bottomRounded || 12
    if (r) {
        style += `border-radius:${r * 2}rpx;`
    }
    return style
})

const isFeatureEnabled = (item: any) => {
    const key = (item.featureKey || item.key || '').trim()
    if (!key) {
        return true
    }
    if (!config.value) {
        return false
    }
    const v = config.value[key]
    return v !== 0 && v !== '0' && v !== false
}

const filterMenuItem = (item: any) => {
    if (item.isShow === false) {
        return false
    }
    if (diyStore.mode == 'decorate') {
        return true
    }
    return isFeatureEnabled(item)
}

const visibleList = computed(() => {
    const src = diyStore.mode == 'decorate' ? (diyComponent.value.list || []) : runtimeList.value
    return src.filter(filterMenuItem)
})

const showBlock = computed(() => {
    if (diyStore.mode == 'decorate') {
        return true
    }
    return visibleList.value.length > 0
})

const itemUrl = (item: any) => {
    if (item.link && item.link.url) return item.link.url
    return item.url || ''
}

const syncRunnerItem = async () => {
    const list = JSON.parse(JSON.stringify(diyComponent.value.list || []))
    const idx = list.findIndex((i: any) => i.runnerSlot)
    if (idx < 0) {
        runtimeList.value = list
        return
    }
    try {
        const res: any = await getRunnerInfo()
        if (res.code === 1 && res.data && res.data.id) {
            list[idx].name = list[idx].runnerNameOk || '接单员主页'
            list[idx].url = '/addon/sd_xiaoyuan/pages/runner/index'
            if (list[idx].link) list[idx].link.url = '/addon/sd_xiaoyuan/pages/runner/index'
        } else {
            list[idx].name = list[idx].runnerNameApply || '成为接单员'
            list[idx].url = '/addon/sd_xiaoyuan/pages/runner/apply'
            if (list[idx].link) list[idx].link.url = '/addon/sd_xiaoyuan/pages/runner/apply'
        }
    } catch (e) {
        list[idx].name = list[idx].runnerNameApply || '成为接单员'
        list[idx].url = '/addon/sd_xiaoyuan/pages/runner/apply'
        if (list[idx].link) list[idx].link.url = '/addon/sd_xiaoyuan/pages/runner/apply'
    }
    runtimeList.value = list
}

const refreshMenus = async () => {
    await loadConfig()
    configReady.value = !!config.value
    runtimeList.value = JSON.parse(JSON.stringify(diyComponent.value.list || []))
    if (diyStore.mode != 'decorate') {
        await syncRunnerItem()
    }
}

const onItemClick = (item: any) => {
    if (diyStore.mode == 'decorate') return
    const url = itemUrl(item)
    if (!url) return
    if (!memberStore.token) {
        useLogin().setLoginBack({ url: '/addon/sd_xiaoyuan/pages/user/index' })
        return
    }
    if (item.link && item.link.name) {
        redirect({ url: item.link.url, mode: item.link.mode })
    } else {
        redirect({ url })
    }
}

watch(config, () => {
    if (diyStore.mode != 'decorate' && config.value) {
        syncRunnerItem()
    }
})

onMounted(() => {
    refreshMenus()
})

onShow(() => {
    if (diyStore.mode != 'decorate') {
        refreshMenus()
    }
})
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-user-menu {
    width: 100%;
    box-sizing: border-box;
    .menu-section {
        background: #fff;
        border-radius: 24rpx;
        padding: 30rpx 20rpx 10rpx;
        box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);
    }
    .section-title {
        font-size: 30rpx;
        font-weight: 900;
        color: #333;
        margin-bottom: 24rpx;
        padding-left: 20rpx;
        border-left: 8rpx solid #c0fe95;
        line-height: 1;
    }
    .menu-grid {
        display: flex;
        flex-wrap: wrap;
        width: 100%;
    }
    .menu-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        box-sizing: border-box;
        padding: 16rpx 0 24rpx;
        .menu-icon-bg {
            width: 72rpx;
            height: 72rpx;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10rpx;
            .menu-img {
                width: 44rpx;
                height: 44rpx;
            }
        }
        .menu-label {
            font-size: 24rpx;
            color: #333;
            text-align: center;
            width: 100%;
            padding: 0 6rpx;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.2;
        }
    }
}
</style>
