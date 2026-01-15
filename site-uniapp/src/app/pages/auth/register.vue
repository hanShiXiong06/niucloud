<template>
    <view class="w-screen h-screen flex flex-col" :style="themeColor()">
        <view class="w-screen h-screen" :style="warpStyle">
            <!-- #ifdef MP-WEIXIN -->
            <view :style="{'height': headerHeight }">
                <top-tabbar :data="param" :scrollBool="topTabarObj.getScrollBool()" class="top-header" />
            </view>
            <!-- #endif -->
            <view class="mx-[40rpx] pt-[180rpx]">
                <view class="text-[60rpx] font-500 mb-[60rpx]">商家账号申请</view>
                <u-form labelPosition="left" :model="formData" errorType='toast' :rules="rules" ref="formRef">
                    <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                        <u-form-item label="" prop="username" :border-bottom="false">
                            <u-input v-model="formData.username" border="none" maxlength="40" placeholder="请输入商家账号" autocomplete="off" class="!bg-transparent" :disabled="real_name_input" fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                <template #prefix>
                                        <text class="nc-iconfont nc-icon-a-wodeV6xx-36 text-[40rpx]"></text>
                                </template>
                            </u-input>
                        </u-form-item>
                    </view>
                    <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                        <u-form-item label="" prop="mobile" :border-bottom="false">
                            <u-input v-model="formData.mobile" type="number" maxlength="11" border="none" placeholder="请输入手机号" autocomplete="off" class="!bg-transparent" :disabled="real_name_input" fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                <template #prefix>
                                    <text class="nc-iconfont nc-icon-dianhuaV6xx text-[40rpx]"></text>
                                </template>
                            </u-input>
                        </u-form-item>
                    </view>
                    <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                        <u-form-item label="" prop="mobile_code" :border-bottom="false">
                            <u-input v-model="formData.mobile_code" type="number" maxlength="4" border="none" class="!bg-transparent" fontSize="26rpx" :disabled="real_name_input" placeholder="请输入验证码" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                    <template #prefix>
                                    <text class="nc-iconfont nc-icon-yanzhengmaV6xx text-[40rpx]"></text>
                                </template>
                                <template #suffix>
                                    <sms-code :mobile="formData.mobile" type="login" v-model="formData.mobile_key" ref="smsCodeRef"></sms-code>
                                </template>
                            </u-input>
                        </u-form-item>
                    </view>
                </u-form>
                <view class="mt-[200rpx]">
                    <button class="w-full h-[80rpx] !bg-[var(--primary-color)] text-[26rpx] rounded-[16rpx] leading-[80rpx] font-500 !text-[#fff] !mx-[0]" loadingText="申请中" @click="handleLogin">申请</button>
                </view>
                <view class="mt-[50rpx] text-[28rpx] text-center">
                    <text class="text-[#999]">已有账号？</text>
                    <text class="text-primary" @click="redirect({url: '/app/pages/auth/login'})">立即登录</text>
                </view>
            </view>
        </view>
        <!-- 验证组件 -->
        <verify @success="success" mode="pop" captchaType="blockPuzzle" :imgSize="{ width: '300px', height: '155px' }" ref="verifyRef"></verify>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { pxToRpx, img, redirect, setToken } from '@/utils/common'
import { topTabar } from '@/utils/topTabbar'
import {  getLoginConfig, siteRegister } from '@/app/api/auth'
import useUserStore from '@/stores/user'
import Verify from '@/components/verifition/verify.vue'

/********* 自定义头部 - start ***********/
const topTabarObj = topTabar()
const param = topTabarObj.setTopTabbarParam({ title: '', topStatusBar: { bgColor: '#fff', textColor: '#333' } })
/********* 自定义头部 - end ***********/

let menuButtonInfo: any = {};
// 如果是小程序，获取右上角胶囊的尺寸信息，避免导航栏右侧内容与胶囊重叠(支付宝小程序非本API，尚未兼容)
// #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
menuButtonInfo = uni.getMenuButtonBoundingClientRect();
// #endif

const headerHeight = computed(() => {
    return Object.keys(menuButtonInfo).length ? pxToRpx(Number(menuButtonInfo.height)) + pxToRpx(menuButtonInfo.top) + pxToRpx(8) + 'rpx' : 'auto'
})

const warpStyle = computed(() => {
    var style = '';
    style += 'background-image:url(' + img('/addon/wuxinggou/login_bg.png') + ');';
    style += 'background-size: 100%;';
    style += 'background-position: top;';
    style += 'background-repeat: no-repeat;';
    return style
})
const real_name_input = ref(true);
const siteStore = useUserStore()

const formData = reactive({
    username: '',
    password: '000000',
    mobile: '',
    mobile_code: '',
    mobile_key: '',
    captcha_code: '',
})

// 获取登录配置信息
const loginConfig = ref<any>({
    is_site_captcha: '',
})
const getLoginConfigFn = async () => {
    const data = await (await getLoginConfig()).data
    loginConfig.value = data

}
getLoginConfigFn()

onMounted(() => {
    // 防止浏览器自动填充
    setTimeout(() => {
        real_name_input.value = false;
    }, 800)
});

const loading = ref(false)

const rules = computed(() => {
    return {
        'username': {
            type: 'string',
            required: true,
            message: '请输入商家账号',
            trigger: ['blur', 'change'],
        },
        'mobile': [
            {
                type: 'string',
                required: true,
                message: '请输入手机号',
                trigger: ['blur', 'change'],
            },
            {
                validator(rule: any, value: any) {
                    return uni.$u.test.mobile(value)
                },
                message: '请输入正确的手机号',
                trigger: ['change', 'blur'],
            }
        ],
        'mobile_code': {
            type: 'string',
            required: true,
            message: '请输入验证码',
            trigger: ['blur', 'change']
        }
    }
})

const formRef: any = ref(null)

const handleLogin = () =>{
    formRef.value.validate().then(() => {
        if (loading.value) return
        
        if (parseInt(loginConfig.value.is_site_captcha)) { 
            verifyRef.value.show()
        } else { 
            loginFn() 
        }
    })
}

// 验证码 - start
const verifyRef = ref<any>(null)
const smsCodeRef = ref<any>(null)

const success = (params: any) => {
    formData.captcha_code = params.captchaVerification
   
    loginFn()
}
// 验证码 - end

const loginFn = () => { 
    loading.value = true

    siteRegister(formData).then((res: any) => {
        uni.setStorageSync('siteId', res.data.site_id || 0);
        setToken(res.data.token)
        siteStore.token = res.data.token
        redirect({url: '/addon/mall/pages/card/add', mode: 'reLaunch'})
    }).catch(() => {
        loading.value = false
    })
}
</script>

<style lang="scss" scoped>
:deep(.u-input) {
    background-color: transparent !important;
}

:deep(.u-checkbox) {
    margin: 0 !important;
}

:deep(.u-form-item) {
    flex: 1;

    .u-line {
        display: none;
    }
}

.footer {
    margin-top: 200rpx;
    padding-bottom: calc(151rpx + constant(safe-area-inset-bottom));
    padding-bottom: calc(151rpx + env(safe-area-inset-bottom));
}

</style>