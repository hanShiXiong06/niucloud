<template>
    <view class="entry-page">
        <view class="entry-card">
            <view v-if="loading" class="entry-state">
                <view class="entry-icon entry-icon--loading"></view>
                <view class="entry-title">正在打开工作任务</view>
                <view class="entry-desc">正在核对通知所属站点和您的管理权限…</view>
            </view>

            <view v-else class="entry-state">
                <view class="entry-icon entry-icon--error">!</view>
                <view class="entry-title">暂时无法打开</view>
                <view class="entry-desc">{{ errorMessage }}</view>
                <button class="entry-button" @click="retry">重新校验</button>
                <button class="entry-home" @click="goHome">返回工作台</button>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getSiteInfo } from '@/app/api/auth'
import { resolveWecomEntry } from '@/addon/hsx_wecom/api'
import { getToken, redirect } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'
import useUserStore from '@/stores/user'

const loading = ref(true)
const errorMessage = ref('通知入口无效，请从企业微信中的最新通知重新进入。')
const siteId = ref(0)
const ticket = ref('')
const userStore = useUserStore()

const decodeTicket = (value: unknown) => {
    const raw = String(value || '').trim()
    if (!raw) return ''
    try {
        return decodeURIComponent(raw)
    } catch (_) {
        return raw
    }
}

const switchSiteContext = (value: number) => {
    uni.setStorageSync('siteId', value)
    uni.removeStorageSync('siteInfo')
    userStore.$patch({ siteId: value, siteInfo: null })
}

const openEntry = async () => {
    loading.value = true
    if (siteId.value <= 0 || !ticket.value) {
        loading.value = false
        errorMessage.value = '通知缺少站点信息，请从企业微信中的最新通知重新进入。'
        return
    }

    // 必须先切站点，后续 adminapi 请求头才会携带通知中的 site_id。
    switchSiteContext(siteId.value)

    if (!getToken()) {
        useLogin().setLoginBack({
            url: '/app/pages/wecom/entry',
            param: { site_id: siteId.value, ticket: ticket.value }
        })
        return
    }

    try {
        const result: any = await resolveWecomEntry(ticket.value)
        const targetSiteId = Number(result?.data?.site_id || 0)
        const targetPath = String(result?.data?.miniapp_path || '').replace(/^\/+/, '')
        if (targetSiteId !== siteId.value || !targetPath) {
            throw new Error('通知入口返回的站点或业务页面无效')
        }

        // 清掉旧站点资料并读取目标站点资料，避免页面标题、权限和菜单短暂串站。
        const siteResult: any = await getSiteInfo()
        userStore.setSiteInfo(siteResult.data)
        userStore.$patch({ siteId: targetSiteId })

        redirect({ url: `/${ targetPath }`, mode: 'reLaunch' })
    } catch (error: any) {
        loading.value = false
        errorMessage.value = String(error?.msg || error?.message || '通知已失效，或您没有该站点的管理权限。')
    }
}

const retry = () => openEntry()
const goHome = () => redirect({ url: '/app/pages/index/index', mode: 'reLaunch' })

onLoad((options: any) => {
    siteId.value = Number(options?.site_id || 0)
    ticket.value = decodeTicket(options?.ticket)
    void openEntry()
})
</script>

<style lang="scss" scoped>
.entry-page {
    min-height: 100vh;
    padding: 180rpx 36rpx 60rpx;
    box-sizing: border-box;
    background: #f5f7fa;
}

.entry-card {
    padding: 72rpx 42rpx 52rpx;
    border-radius: 28rpx;
    background: #fff;
    box-shadow: 0 18rpx 60rpx rgba(31, 41, 55, 0.08);
}

.entry-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.entry-icon {
    width: 82rpx;
    height: 82rpx;
    margin-bottom: 36rpx;
    border-radius: 50%;
}

.entry-icon--loading {
    box-sizing: border-box;
    border: 8rpx solid #dbeafe;
    border-top-color: #1677ff;
    animation: entry-spin 0.85s linear infinite;
}

.entry-icon--error {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 48rpx;
    font-weight: 700;
    background: #f59e0b;
}

.entry-title {
    color: #111827;
    font-size: 36rpx;
    font-weight: 600;
    line-height: 1.4;
}

.entry-desc {
    margin-top: 18rpx;
    color: #6b7280;
    font-size: 27rpx;
    line-height: 1.7;
}

.entry-button,
.entry-home {
    width: 100%;
    height: 86rpx;
    margin-top: 44rpx;
    border: 0;
    border-radius: 16rpx;
    color: #fff;
    font-size: 30rpx;
    line-height: 86rpx;
    background: #1677ff;
}

.entry-button::after,
.entry-home::after {
    border: 0;
}

.entry-home {
    margin-top: 18rpx;
    color: #4b5563;
    background: #f3f4f6;
}

@keyframes entry-spin {
    to { transform: rotate(360deg); }
}
</style>
