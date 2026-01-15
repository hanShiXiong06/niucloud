<template>
    <view class="w-screen h-screen flex flex-col" :style="themeColor()" >
        <view class="w-screen h-screen" :style="warpStyle">
            <!-- #ifdef MP-WEIXIN -->
            <view :style="{'height':headerHeight}">
                <top-tabbar :data="param" :scrollBool="topTabarObj.getScrollBool()" class="top-header" />
            </view>
            <!-- #endif -->
            <view class="mx-[40rpx] pt-[100rpx]">
                <view class="mb-[80rpx] flex whitespace-nowrap  box-border">
                    <view class="mr-[60rpx] text-[30rpx] leading-[60rpx] text-center text-[#999]" :class="{'class-select': type == 'username'}" @click="setType('username')">密码登录</view>
                    <view class="text-[30rpx] leading-[60rpx] text-center text-[#999]" :class="{'class-select': type == 'mobile'}" @click="setType('mobile')">手机号登录</view>
                </view>
                <u-form labelPosition="left" :model="formData" errorType='toast' :rules="rules" ref="formRef">
                    <template v-if="type == 'username'">
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border bg-[#f8f8f8] mb-[30rpx]">
                            <u-form-item label="" prop="username" :border-bottom="false">
                                <u-input v-model="formData.username" border="none" maxlength="40" :placeholder="t('usernamePlaceholder')" autocomplete="off" class="!bg-transparent" :disabled="real_name_input" fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                </u-input>
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border bg-[#f8f8f8] mb-[30rpx]">
                            <u-form-item label="" prop="password" :border-bottom="false">
                                <u-input v-model="formData.password" border="none" :password="isPassword" maxlength="40" :placeholder="t('passwordPlaceholder')" autocomplete="new-password" class="!bg-transparent" :disabled="real_name_input" fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]"></u-input>
                            </u-form-item>
                        </view>
                    </template>
                    <template v-if="type == 'mobile'">
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border bg-[#f8f8f8] mb-[30rpx]">
                            <u-form-item label="" prop="mobile" :border-bottom="false">
                                <u-input v-model="formData.mobile" type="number" maxlength="11" border="none" :placeholder="t('mobilePlaceholder')" autocomplete="off" class="!bg-transparent" :disabled="real_name_input" fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                </u-input>
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border bg-[#f8f8f8] mb-[30rpx]">
                            <u-form-item label="" prop="mobile_code" :border-bottom="false">
                                <u-input v-model="formData.mobile_code" type="number" maxlength="4" border="none" class="!bg-transparent" fontSize="26rpx" :disabled="real_name_input" :placeholder="t('codePlaceholder')" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                    <template #suffix>
                                        <sms-code :mobile="formData.mobile" type="login" v-model="formData.mobile_key" ref="smsCodeRef" @codeSend="handleCodeSend"></sms-code>
                                    </template>
                                </u-input>
                            </u-form-item>
                        </view>
                    </template>
                </u-form>
                <view class="mt-[100rpx]">
                    <button class="w-full h-[80rpx] !bg-[var(--primary-color)] text-[26rpx] rounded-[16rpx] leading-[80rpx] font-500 !text-[#fff] !mx-[0]" :loadingText="t('logining')" @click="handleLogin">{{ t('login') }}</button>
                </view>
            </view>
        </view>
        <!-- 验证组件 -->
        <verify @success="success" mode="pop" captchaType="blockPuzzle" :imgSize="{ width: '300px', height: '155px' }" ref="verifyRef"></verify>
    </view>
</template>
<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { usernameLogin, mobileLogin, getLoginConfig } from '@/app/api/auth'
import useUserStore from '@/stores/user'
import { useLogin } from '@/hooks/useLogin'
import { t } from '@/locale'
import { pxToRpx, img, redirect } from '@/utils/common'
import { topTabar } from '@/utils/topTabbar'
import Verify from '@/components/verifition/verify.vue'
import useSystemStore from '@/stores/system'
import { useSendSms } from '@/hooks/useSendSms'

let menuButtonInfo: any = {};
// 如果是小程序，获取右上角胶囊的尺寸信息，避免导航栏右侧内容与胶囊重叠(支付宝小程序非本API，尚未兼容)
// #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
menuButtonInfo = uni.getMenuButtonBoundingClientRect();
// #endif
/********* 自定义头部 - start ***********/
const topTabarObj = topTabar()
const param = topTabarObj.setTopTabbarParam({ title: '', topStatusBar: { bgColor: '#fff', textColor: '#333' } })
/********* 自定义头部 - end ***********/
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
const type = ref('username')

const isPassword = ref(true)



const formData = reactive({
    username: '',
    password: '',
    mobile: '',
    mobile_code: '',
    mobile_key: '',
    captcha_code: '',
})

const smsRef: any = ref(null)
const sendSms = useSendSms(smsRef)

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

const setType = (val:any) => {
    type.value = val
}

const loading = ref(false)

const rules = computed(() => {
    return {
        'username': {
            type: 'string',
            required: type.value == 'username',
            message: t('usernamePlaceholder'),
            trigger: ['blur', 'change'],
        },
        'password': {
            type: 'string',
            required: type.value == 'username',
            message: t('passwordPlaceholder'),
            trigger: ['blur', 'change']
        },
        'mobile': [
            {
                type: 'string',
                required: type.value == 'mobile',
                message: t('mobilePlaceholder'),
                trigger: ['blur', 'change'],
            },
            {
                validator(rule: any, value: any) {
                    if (type.value != 'mobile') return true
                    else return uni.$u.test.mobile(value)
                },
                message: t('mobileError'),
                trigger: ['change', 'blur'],
            }
        ],
        'mobile_code': {
            type: 'string',
            required: type.value == 'mobile',
            message: t('codePlaceholder'),
            trigger: ['blur', 'change']
        }
    }
})

const formRef: any = ref(null)

const handleLogin = () => {
    formRef.value.validate().then(() => {
        if (loading.value) return
        
        if(type.value == 'username'){
            if (parseInt(loginConfig.value.is_site_captcha)) { 
                verifyRef.value.show()
            } else { 
                loginFn() 
            }
        } else if(type.value == 'mobile'){
            loginFn() 
        }
    })
}
// 验证码 - start
const verifyRef = ref<any>(null)
const smsCodeRef = ref<any>(null)

const success = (params: any) => {
    formData.captcha_code = params.captchaVerification
   
    if(type.value == 'username'){
        loginFn()
    }  else if(type.value == 'mobile'){
        smsCodeRef.value.handleConfirm(formData)
    } 

}
// 验证码 - end

// 手机号登录 - start
const handleCodeSend = () =>{
    verifyRef.value.show()

}

// 手机号登录 - end



const loginFn = () => { 
    loading.value = true
    const login = type.value == 'username' ? usernameLogin : mobileLogin

    login(formData).then((res: any) => {
        uni.setStorageSync('siteId', res.data.site_id || 0);
        siteStore.setUserInfo(res.data.userinfo)
        siteStore.setSiteInfo(res.data.site_info)
        siteStore.setToken(res.data.token)
        useSystemStore().getSiteNavsFn()
        useLogin().handleLoginBack()
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

.class-select{
    position: relative;
    font-weight: 500;
    font-size: 34rpx;
    color: #333;
    &::before{
        content: "";
        position: absolute;
        bottom: -10rpx;
        width: 40rpx;
        height:6rpx;
        border-radius: 4rpx;
        background-color: var(--primary-color);
        left: 50%;
        transform: translateX(-50%);
    }
}

.footer {
    margin-top: 200rpx;
    padding-bottom: calc(151rpx + constant(safe-area-inset-bottom));
    padding-bottom: calc(151rpx + env(safe-area-inset-bottom));
}
</style>
