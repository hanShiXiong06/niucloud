<template>
    <!-- #ifdef MP-WEIXIN -->
    <view v-if="capability?.enabled" class="arrival-entry" @click="show = true">
        <u-icon name="bell" size="16" color="var(--primary-color)" /><text>上新提醒</text>
    </view>
    <u-popup :show="show" mode="bottom" :round="20" :safeAreaInsetBottom="true" @close="show = false">
        <view class="arrival-panel">
            <view class="arrival-header"><text>有新货，微信提醒您</text><view class="arrival-close" @click="show = false"><u-icon name="close" size="18" /></view></view>
            <view class="arrival-desc">商家完成导入并上架后，将本批新货合成一条通知，点开即可查看。</view>
            <view class="arrival-desc">开启后优先按批次提醒，原分类/筛选偏好保留，不再逐台重复通知。取消本批提醒后恢复原订阅。</view>
            <view class="arrival-note">一次授权通常对应一条微信通知。已保存订阅偏好，不代表有永久通知额度。</view>
            <button class="arrival-button" :disabled="busy || preparing" :loading="busy" @click="subscribe">{{ memberStore.token ? (subscribed ? '再次授权上新提醒' : '订阅上新提醒') : '登录后订阅' }}</button>
            <view class="arrival-actions"><text @click="show = false">暂时不用</text><text v-if="subscribed" @click="cancel">取消上新订阅</text></view>
        </view>
    </u-popup>
    <!-- #endif -->
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import useMemberStore from '@/stores/member'
import { addGoodsSubscription, cancelGoodsSubscription, getGoodsSubscriptionStatus } from '@/addon/phone_shop/api/goods'
import { useGoodsSubscriptionNotice } from '@/addon/phone_shop/hooks/useGoodsSubscriptionNotice'
import { enterPhoneShopLogin } from '@/addon/phone_shop/hooks/usePhoneShopLoginNavigation'

const props = defineProps<{ backUrl: string; backParams?: Record<string, any> }>()
const memberStore = useMemberStore()
const { capability, preparing, prepare, requestAuthorization, explainAuthorization } = useGoodsSubscriptionNotice()
const show = ref(false)
const busy = ref(false)
const subscribed = ref(false)
const rule = { arrival_notice: 1 }
let generation = 0
const load = async () => {
    // #ifdef MP-WEIXIN
    const version = ++generation
    const token = memberStore.token
    await prepare()
    if (version !== generation || token !== memberStore.token) return
    subscribed.value = false
    if (token) {
        try {
            const res: any = await getGoodsSubscriptionStatus(rule)
            if (version !== generation || token !== memberStore.token) return
            subscribed.value = Boolean(res.data?.subscribed)
        } catch { return }
    }
    if (!capability.value?.enabled) return
    const key = `phone_shop:arrival-prompt:${uni.getStorageSync('wap_site_id')}:${memberStore.info?.member_id || 'guest'}`
    const now = new Date()
    const day = `${now.getFullYear()}-${now.getMonth() + 1}-${now.getDate()}`
    if (uni.getStorageSync(key) !== day) {
        uni.setStorageSync(key, day)
        show.value = true
    }
    // #endif
}

const subscribe = async () => {
    if (busy.value || preparing.value) return
    if (!memberStore.token) {
        show.value = false
        await enterPhoneShopLogin({ url: props.backUrl, param: props.backParams || {} }).catch(() => uni.showToast({ title: '登录页面打开失败，请重试', icon: 'none' }))
        return
    }
    busy.value = true
    try {
        // 必须由本次按钮点击直接调用，不能放进 onLoad / onShow 自动触发。
        const authorization = await requestAuthorization()
        if (authorization.status !== 'accepted') { explainAuthorization(authorization); return }
        await addGoodsSubscription({ rule, name: '本站批量上新提醒', authorization })
        subscribed.value = true
        show.value = false
    } catch { /* 请求层已展示失败原因，不假装保存成功。 */ }
    finally { busy.value = false }
}
const cancel = async () => {
    if (busy.value) return
    busy.value = true
    try { await cancelGoodsSubscription({ rule }); subscribed.value = false; show.value = false }
    catch { /* 请求层显示错误，保留原订阅状态。 */ }
    finally { busy.value = false }
}
onMounted(load)
watch(() => memberStore.token, load)
onBeforeUnmount(() => { generation++ })
</script>

<style scoped lang="scss">
.arrival-entry{position:fixed;right:24rpx;bottom:calc(138rpx + env(safe-area-inset-bottom));z-index:110;display:flex;align-items:center;gap:8rpx;padding:15rpx 22rpx;border-radius:40rpx;background:#fff;color:var(--primary-color);font-size:24rpx;box-shadow:0 4rpx 20rpx rgba(15,23,42,.12)}
.arrival-panel{padding:32rpx;box-sizing:border-box;background:#fff;border-radius:24rpx 24rpx 0 0;color:#1e293b}
.arrival-header{display:flex;align-items:center;justify-content:space-between;font-size:32rpx;font-weight:600}
.arrival-close{padding:12rpx}
.arrival-desc{margin-top:18rpx;font-size:27rpx;line-height:1.7;color:#475569}
.arrival-note{margin:20rpx 0 28rpx;padding:18rpx;border-radius:12rpx;background:#f8fafc;color:#64748b;font-size:23rpx;line-height:1.6}
.arrival-button{background:var(--primary-color);color:#fff;border-radius:14rpx;font-size:28rpx}
.arrival-actions{display:flex;justify-content:center;gap:44rpx;margin-top:24rpx;padding:10rpx;color:#64748b;font-size:24rpx}
</style>
