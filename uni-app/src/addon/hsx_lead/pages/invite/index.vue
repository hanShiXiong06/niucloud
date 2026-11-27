<template>
    <view class="promotion-entry" :style="themeColor()">
        <view class="promotion-entry__card">
            <text class="promotion-entry__title">欢迎加入商户协作</text>
            <text class="promotion-entry__hint">{{ loggedIn ? '查看我的门店与协作信息' : '登录或注册，开启门店协作' }}</text>
            <u-button type="primary" shape="circle" :custom-style="cooperationButtonStyle" :text="loggedIn ? '进入我的页面' : '登录 / 注册'" @click="enter" />
            <button class="promotion-entry__browse" @tap="browse">先逛逛</button>
        </view>
    </view>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getToken, redirect } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'
import { cooperationInviteParams } from '../../utils/cooperation-invite'
import { cooperationButtonStyle } from '../../utils/mobile-ui'

const loggedIn = ref(!!getToken())
let inviter = 0
const login = useLogin()
const returnTarget = () => ({
    url: '/addon/hsx_lead/pages/profile/index',
    param: inviter ? { mid: inviter, promotion_invite: 1 } : {},
    mode: 'redirectTo' as const
})
function enter() {
    loggedIn.value = !!getToken()
    if (loggedIn.value) redirect(returnTarget())
    else login.setLoginBack(returnTarget())
}
function browse() { redirect({ url: '/addon/hsx_lead/pages/index', mode: 'reLaunch' }) }
onLoad(raw => {
    const options = cooperationInviteParams(raw)
    const value = String(options.mid ?? '')
    const id = /^\d+$/.test(value) ? Number(value) : 0
    inviter = Number.isSafeInteger(id) && id > 0 && id <= 4294967295 ? id : 0
    // Warm H5 navigation does not run the launch interceptor again. Use the
    // framework's registration attribution key, never a staff subject code.
    if (inviter) uni.setStorageSync('pid', String(inviter))
    enter()
})
// Cancelling the framework login returns here. Do not auto-open it again.
onShow(() => { loggedIn.value = !!getToken() })
</script>
<style scoped lang="scss">
.promotion-entry { min-height:100vh;box-sizing:border-box;padding:64rpx 32rpx;background:#f6f8fb; }
.promotion-entry__card { padding:56rpx 32rpx 24rpx;border-radius:24rpx;background:#fff; }
.promotion-entry__title { display:block;color:#202b3d;font-size:38rpx;font-weight:600; }
.promotion-entry__hint { display:block;margin:20rpx 0 48rpx;color:#78879b;font-size:28rpx; }
.promotion-entry__browse { margin-top:20rpx;background:transparent;color:#73849b;font-size:28rpx;line-height:88rpx; }
.promotion-entry__browse::after { border:0; }
</style>
