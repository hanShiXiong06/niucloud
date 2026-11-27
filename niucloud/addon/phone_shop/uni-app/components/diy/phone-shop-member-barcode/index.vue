<template>
    <view class="identity-entry" :style="warpCss" @click="openBarcode">
        <view class="identity-entry__icon"><u-icon name="scan" color="#2563eb" size="24" /></view>
        <view class="identity-entry__body">
            <text class="identity-entry__title">{{ diyComponent.title || '我的身份码' }}</text>
            <text class="identity-entry__desc">{{ diyComponent.desc || '出入库时出示，业务员扫码快速识别' }}</text>
        </view>
        <u-icon name="arrow-right" color="#94a3b8" size="17" />
    </view>

    <u-popup :show="visible" mode="center" :round="20" :safeAreaInsetBottom="false" @close="closeBarcode">
        <view class="barcode-dialog">
            <view class="barcode-dialog__head">
                <view>
                    <text class="barcode-dialog__title">会员身份码</text>
                    <text class="barcode-dialog__tip">请将条码对准工作人员的扫码设备</text>
                </view>
                <view class="barcode-dialog__close" @click="closeBarcode"><u-icon name="close" color="#64748b" size="20" /></view>
            </view>
            <view class="barcode-box">
                <view class="barcode-quiet"></view>
                <view v-for="(bar, index) in bars" :key="index" :style="barStyle(bar, index)"></view>
                <view class="barcode-quiet"></view>
            </view>
            <text class="barcode-value">{{ memberIdText }}</text>
            <view class="brightness-tip"><u-icon name="sunny" color="#f59e0b" size="16" /><text>已临时调亮屏幕，关闭后自动恢复</text></view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import useMemberStore from '@/stores/member'
import useDiyStore from '@/app/stores/diy'
import { img } from '@/utils/common'

const props = defineProps(['component', 'index', 'global'])
const memberStore = useMemberStore()
const diyStore = useDiyStore()
const visible = ref(false)
const previousBrightness = ref<number | null>(null)

const diyComponent = computed(() => diyStore.mode === 'decorate' ? diyStore.value[props.index] : props.component)
const memberIdText = computed(() => String(diyStore.mode === 'decorate' ? '100005000123' : (memberStore.info?.member_id || '')))
// 组件外间距由 DIY 公共渲染层统一写入 pageStyle，这里只处理组件自身样式。
// 如果在组件内再次换算 margin，会让装修面板里的“10”被重复应用两次。
const warpCss = computed(() => {
    const component = diyComponent.value || {}
    let style = 'position:relative;overflow:hidden;'

    if (component.componentStartBgColor) {
        if (component.componentEndBgColor) {
            style += `background:linear-gradient(${component.componentGradientAngle || 'to bottom'},${component.componentStartBgColor},${component.componentEndBgColor});`
        } else {
            style += `background-color:${component.componentStartBgColor};`
        }
    }
    if (component.bgUrl) {
        style += `background-image:url(${img(component.bgUrl)});background-size:100%;background-repeat:no-repeat;`
    }
    if (component.topRounded) {
        style += `border-top-left-radius:${component.topRounded * 2}rpx;border-top-right-radius:${component.topRounded * 2}rpx;`
    }
    if (component.bottomRounded) {
        style += `border-bottom-left-radius:${component.bottomRounded * 2}rpx;border-bottom-right-radius:${component.bottomRounded * 2}rpx;`
    }

    return style
})

// Code 128-B：无需图片和网络，会员 ID 改变时立即生成可扫条码。
const patterns = [
    '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'
]
const bars = computed(() => {
    const text = memberIdText.value.replace(/[^\x20-\x7E]/g, '')
    if (!text) return []
    const values = Array.from(text).map(char => char.charCodeAt(0) - 32)
    const checksum = (104 + values.reduce((sum, value, index) => sum + value * (index + 1), 0)) % 103
    return [104, ...values, checksum, 106].flatMap(value => patterns[value].split('').map(Number))
})
const barStyle = (width: number, index: number) => ({
    width: `${width * 2}rpx`,
    height: '150rpx',
    backgroundColor: index % 2 === 0 ? '#0f172a' : 'transparent',
    flexShrink: 0
})

const brighten = () => {
    uni.getScreenBrightness({
        success: res => {
            previousBrightness.value = Number(res.value)
            uni.setScreenBrightness({ value: 1 })
        }
    })
    uni.setKeepScreenOn({ keepScreenOn: true })
}
const restoreBrightness = () => {
    if (previousBrightness.value !== null) uni.setScreenBrightness({ value: previousBrightness.value })
    uni.setKeepScreenOn({ keepScreenOn: false })
    previousBrightness.value = null
}
const openBarcode = () => {
    if (diyStore.mode !== 'decorate' && !memberIdText.value) {
        uni.showToast({ title: '请先登录会员账号', icon: 'none' })
        return
    }
    visible.value = true
    brighten()
}
const closeBarcode = () => {
    visible.value = false
    restoreBrightness()
}
onBeforeUnmount(restoreBrightness)
</script>

<style lang="scss" scoped>
.identity-entry { display:flex; align-items:center; gap:22rpx; padding:28rpx; box-sizing:border-box; box-shadow:0 8rpx 28rpx rgba(15,23,42,.06); }
.identity-entry__icon { width:76rpx; height:76rpx; border-radius:22rpx; background:#eff6ff; display:flex; align-items:center; justify-content:center; }
.identity-entry__body { flex:1; min-width:0; display:flex; flex-direction:column; gap:7rpx; }
.identity-entry__title { font-size:30rpx; font-weight:600; color:#0f172a; }
.identity-entry__desc { font-size:23rpx; color:#64748b; }
.barcode-dialog { width:650rpx; padding:34rpx 30rpx 30rpx; box-sizing:border-box; background:#fff; }
.barcode-dialog__head { display:flex; justify-content:space-between; align-items:flex-start; }
.barcode-dialog__title,.barcode-dialog__tip { display:block; }
.barcode-dialog__title { font-size:34rpx; font-weight:700; color:#0f172a; }
.barcode-dialog__tip { margin-top:8rpx; font-size:23rpx; color:#64748b; }
.barcode-dialog__close { width:60rpx; height:60rpx; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; }
.barcode-box { width:100%; margin-top:36rpx; min-height:190rpx; padding:20rpx 0; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#fff; }
.barcode-quiet { width:20rpx; height:150rpx; flex:none; }
.barcode-value { display:block; text-align:center; font-size:28rpx; font-weight:600; letter-spacing:5rpx; color:#0f172a; }
.brightness-tip { margin-top:28rpx; padding:18rpx 22rpx; border-radius:16rpx; background:#fffbeb; display:flex; align-items:center; justify-content:center; gap:10rpx; font-size:22rpx; color:#92400e; }
</style>
