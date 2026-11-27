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
                    <view :class="getLoginTypeClass('username')" @click="setType('username')">密码登录</view>
                    <view :class="getLoginTypeClass('mobile')" @click="setType('mobile')">手机号登录</view>
                </view>
                <u-form labelPosition="left" :model="formData" errorType='toast' :rules="rules" ref="formRef">
                    <template v-if="type == 'username'">
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border bg-[#f8f8f8] mb-[30rpx]">
                            <u-form-item label="" prop="username" :border-bottom="false">
                                <u-input v-model="formData.username" border="none" maxlength="40" :placeholder="t('usernamePlaceholder')" autocomplete="off" class="!bg-transparent" :disabled="real_name_input || loading" fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                </u-input>
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border bg-[#f8f8f8] mb-[30rpx]">
                            <u-form-item label="" prop="password" :border-bottom="false">
                                <u-input v-model="formData.password" border="none" :password="isPassword" maxlength="40" :placeholder="t('passwordPlaceholder')" autocomplete="new-password" class="!bg-transparent" :disabled="real_name_input || loading" fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]"></u-input>
                            </u-form-item>
                        </view>
                    </template>
                    <template v-if="type == 'mobile'">
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border bg-[#f8f8f8] mb-[30rpx]">
                            <u-form-item label="" prop="mobile" :border-bottom="false">
                                <u-input v-model="formData.mobile" type="number" maxlength="11" border="none" :placeholder="t('mobilePlaceholder')" autocomplete="off" class="!bg-transparent" :disabled="real_name_input || loading" fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                </u-input>
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border bg-[#f8f8f8] mb-[30rpx]">
                            <u-form-item label="" prop="mobile_code" :border-bottom="false">
                                <u-input v-model="formData.mobile_code" type="number" maxlength="4" border="none" class="!bg-transparent" fontSize="26rpx" :disabled="real_name_input || loading" :placeholder="t('codePlaceholder')" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]">
                                    <template #suffix>
                                        <sms-code :mobile="formData.mobile" type="login" v-model="formData.mobile_key" ref="smsCodeRef" @codeSend="handleCodeSend"></sms-code>
                                    </template>
                                </u-input>
                            </u-form-item>
                        </view>
                    </template>
                </u-form>
                <view class="mt-[100rpx]">
                    <button class="w-full h-[80rpx] !bg-[var(--primary-color)] text-[26rpx] rounded-[16rpx] leading-[80rpx] font-500 !text-[#fff] !mx-[0]" :loading="loading" :disabled="loading" @click="handleLogin">{{ loading ? loginStage : authenticated ? '进入工作台' : t('login') }}</button>
                    <view v-if="loginError" class="mt-[24rpx] text-[26rpx] leading-[40rpx] text-[#b45309]" role="alert">{{ loginError }}</view>
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
import { pxToRpx, img } from '@/utils/common'
import { topTabar } from '@/utils/topTabbar'
import Verify from '@/components/verify/verify.vue'
import useSystemStore from '@/stores/system'

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

// 配置没有取到时不能猜测是否需要验证码，也不能静默绕过。
const loginConfig = ref<any>(null)
let configRequest: Promise<any> | null = null
const getLoginConfigFn = () => {
    if (loginConfig.value) return Promise.resolve(loginConfig.value)
    if (!configRequest) {
        configRequest = getLoginConfig().then((res: any) => {
            if (!res?.data || ![0, 1, '0', '1'].includes(res.data.is_site_captcha)) throw new Error('登录配置不完整，请重试')
            loginConfig.value = res.data
            return res.data
        }).finally(() => { configRequest = null })
    }
    return configRequest
}


onMounted(() => {
    // 防止浏览器自动填充
    setTimeout(() => {
        real_name_input.value = false;
    }, 800)
});

const setType = (val: string) => {
    if (loading.value) return
    type.value = val
    loginError.value = ''
    formData.captcha_code = ''
}

const getLoginTypeClass = (value: string) => {
    const base = value === 'username'
        ? 'mr-[60rpx] text-[30rpx] leading-[60rpx] text-center text-[#999]'
        : 'text-[30rpx] leading-[60rpx] text-center text-[#999]'
    return type.value === value ? `${ base } class-select` : base
}

const loading = ref(false)
const authenticated = ref(false)
const loginStage = ref('登录中…')
const loginError = ref('')
const loginFailure = (error: any, fallback: string) => {
    const message = Array.isArray(error) ? error[0]?.message : error?.msg || error?.message
    return typeof message === 'string' && message.length < 120 ? message : fallback
}

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

const openAfterLogin = async () => {
    loginStage.value = '正在进入…'
    const opened = await useLogin().handleLoginBack()
    if (!opened) loginError.value = '已登录，页面暂时未打开。请点击“进入工作台”重试，无需重新输入密码。'
}

const handleLogin = async () => {
    if (loading.value) return
    loading.value = true
    loginStage.value = '正在校验…'
    loginError.value = ''
    try {
        if (authenticated.value && siteStore.token) { await openAfterLogin(); return }
        authenticated.value = false
        if (!formRef.value?.validate) throw new Error('登录表单尚未就绪，请稍后再试')
        const valid = await formRef.value.validate()
        if (valid === false) throw new Error('请检查登录信息后重试')
        if (type.value === 'username') {
            loginStage.value = '正在准备登录…'
            const config = await getLoginConfigFn()
            if (Number(config.is_site_captcha) === 1) {
                if (!verifyRef.value?.show) throw new Error('安全验证尚未就绪，请重试')
                formData.captcha_code = ''
                verifyRef.value.show()
                return
            }
        }
        await loginFn()
    } catch (error) { loginError.value = loginFailure(error, '登录未完成，请检查网络后重试') }
    finally { loading.value = false }
}
// 验证码 - start
const verifyRef = ref<any>(null)
const smsCodeRef = ref<any>(null)

const success = async (params: any) => {
    if (loading.value) return
    formData.captcha_code = params.captchaVerification
    if (type.value === 'username') {
        await loginFn()
    } else if (type.value === 'mobile') {
        try { await smsCodeRef.value?.handleConfirm(formData) }
        catch (error) { loginError.value = loginFailure(error, '验证码发送失败，请重试') }
    }
}
// 验证码 - end

// 手机号登录 - start
const handleCodeSend = () => {
    if (loading.value) return
    loginError.value = ''
    if (!verifyRef.value?.show) { loginError.value = '安全验证尚未就绪，请重试'; return }
    verifyRef.value.show()
}

// 手机号登录 - end



let loginInFlight = false
const loginFn = async () => {
    if (loginInFlight) return
    loginInFlight = true
    loading.value = true
    loginStage.value = '登录中…'
    loginError.value = ''
    try {
        const login = type.value === 'username' ? usernameLogin : mobileLogin
        const res: any = await login({ ...formData })
        if (typeof res?.data?.token !== 'string' || !res.data.token.trim()) throw new Error('登录凭证未返回，请重新登录')
        uni.setStorageSync('siteId', res.data.site_id || 0)
        siteStore.setUserInfo(res.data.userinfo)
        siteStore.setSiteInfo(res.data.site_info)
        siteStore.setToken(res.data.token)
        authenticated.value = true
        // 导航菜单异步刷新，不把菜单请求当成登录是否成功的条件。
        useSystemStore().getSiteNavsFn()
        await openAfterLogin()
    } catch (error) { loginError.value = loginFailure(error, '登录失败，请检查网络后重试') }
    finally {
        loginInFlight = false
        loading.value = false
    }
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
