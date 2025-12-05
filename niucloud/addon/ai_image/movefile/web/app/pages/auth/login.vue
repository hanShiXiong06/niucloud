<template>
    <div
        class="w-full h-full bg-gradient-to-br from-gray-900 to-gray-800 flex items-center justify-center p-4 overflow-auto">
        <div class="w-full max-w-5xl">
            <!-- 页面标题 -->
            <div class="text-center mb-8">
                <h1
                    class="text-4xl font-bold mb-3 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
                    欢迎登录
                </h1>
                <p class="text-gray-400">登录您的账户，开启AI创作之旅</p>
            </div>

            <!-- 登录卡片容器 -->
            <div class="flex flex-wrap justify-center gap-6">
                <!-- 扫码登录区域 -->
                <div
                    class="w-full lg:w-[calc(50%-12px)] bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl p-8 border border-gray-700/50 shadow-xl">
                    <div class="flex items-center mb-6">
                        <div
                            class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg mr-3 flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z">
                                </path>
                                <path
                                    d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z">
                                </path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-white">微信扫码登录</h2>
                    </div>

                    <div class="flex flex-col items-center py-8">
                        <div class="text-gray-300 text-sm mb-4">打开手机微信,扫描二维码登录</div>
                        <div class="p-4 bg-white rounded-xl shadow-lg relative">
                            <el-image :src="weixinCode.url" class="w-[180px] h-[180px]" />
                            <!-- 加载动画 -->
                            <div v-if="weixinCode.loading"
                                class="absolute inset-0 flex flex-col items-center justify-center bg-white rounded-xl">
                                <div class="qr-loading-spinner"></div>
                                <span class="text-sm text-gray-600 mt-4">加载中...</span>
                            </div>
                            <!-- 过期/失败状态 -->
                            <div v-else-if="weixinCode.pastDue"
                                class="absolute inset-0 flex flex-col items-center justify-center bg-gray-900/80 backdrop-blur-sm rounded-xl">
                                <span class="text-sm text-gray-300 mb-3">{{ weixinCode.pastDueContent }}</span>
                                <button @click="scanLoginFn()"
                                    class="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white text-sm rounded-lg transition-all duration-200">
                                    点击刷新
                                </button>
                            </div>
                        </div>
                        <div class="text-gray-400 text-xs mt-4">扫码后请在手机上确认登录</div>
                    </div>
                </div>

                <!-- 账号登录区域 -->
                <div
                    class="w-full lg:w-[calc(50%-12px)] lg:max-w-[500px] bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl p-8 border border-gray-700/50 shadow-xl">
                    <div class="flex items-center mb-6">
                        <div
                            class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-lg mr-3 flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-white">账号登录</h2>
                    </div>

                    <!-- 登录方式切换 -->
                    <div class="flex gap-4 mb-6" v-if="loginType.length > 1">
                        <button v-for="item in loginType" :key="item.type" @click="type = item.type" :class="[
                            'flex-1 py-3 px-4 rounded-lg font-medium transition-all duration-200',
                            type === item.type
                                ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-lg'
                                : 'bg-gray-700/50 text-gray-300 hover:bg-gray-600/50'
                        ]">
                            {{ item.title }}
                        </button>
                    </div>

                    <el-form :model="formData" ref="formRef" :rules="formRules" :validate-on-rule-change="false">
                        <!-- 用户名登录 -->
                        <div v-show="type == 'username'" class="space-y-4">
                            <el-form-item prop="username">
                                <el-input v-model="formData.username" :placeholder="t('usernamePlaceholder')"
                                    size="large" clearable>
                                    <template #prefix>
                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                    </template>
                                </el-input>
                            </el-form-item>
                            <el-form-item prop="password">
                                <el-input v-model="formData.password" :placeholder="t('passwordPlaceholder')"
                                    type="password" size="large" clearable show-password>
                                    <template #prefix>
                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                    </template>
                                </el-input>
                            </el-form-item>
                        </div>

                        <!-- 手机号登录 -->
                        <div v-show="type == 'mobile'" class="space-y-4">
                            <el-form-item prop="mobile">
                                <el-input v-model="formData.mobile" :placeholder="t('mobilePlaceholder')" size="large"
                                    clearable>
                                    <template #prefix>
                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z">
                                            </path>
                                        </svg>
                                    </template>
                                </el-input>
                            </el-form-item>
                            <el-form-item prop="mobile_code">
                                <el-input v-model="formData.mobile_code" :placeholder="t('codePlaceholder')"
                                    size="large">
                                    <template #prefix>
                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                    </template>
                                    <template #suffix>
                                        <sms-code :mobile="formData.mobile" type="login" v-model="formData.mobile_key"
                                            @click="sendSmsCode" ref="smsCodeRef"></sms-code>
                                    </template>
                                </el-input>
                            </el-form-item>
                        </div>

                        <!-- 登录按钮 -->
                        <el-form-item class="mt-6">
                            <button @click="handleLogin" :disabled="loading"
                                class="w-full bg-gradient-to-r from-purple-600 via-blue-600 to-indigo-600 hover:from-purple-700 hover:via-blue-700 hover:to-indigo-700 py-3.5 px-6 rounded-xl font-semibold text-lg transition-all duration-300 flex items-center justify-center shadow-lg hover:shadow-xl transform hover:scale-[1.02] relative overflow-hidden group disabled:opacity-50 disabled:cursor-not-allowed text-white">
                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/10 to-white/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700">
                                </div>
                                <span class="relative z-10">{{ loading ? t('logining') : '🚀 ' + t('login') }}</span>
                            </button>
                        </el-form-item>

                        <!-- 底部链接 -->
                        <div class="flex justify-between items-center text-sm mt-4">
                            <NuxtLink to="/auth/register"
                                class="text-purple-400 hover:text-purple-300 transition-colors">
                                {{ t('noAccount') }}，{{ t('toRegister') }}
                            </NuxtLink>
                        </div>

                        <!-- 协议 -->
                        <div class="text-xs text-gray-400 text-center mt-6" v-if="configStore.login.agreement_show">
                            {{ t('agreeTips') }}
                            <NuxtLink to="/auth/agreement?key=service" class="text-purple-400 hover:text-purple-300">
                                {{ t('userAgreement') }}
                            </NuxtLink>
                            {{ t('and') }}
                            <NuxtLink to="/auth/agreement?key=privacy" class="text-purple-400 hover:text-purple-300">
                                {{ t('privacyAgreement') }}
                            </NuxtLink>
                        </div>
                    </el-form>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import { FormInstance } from 'element-plus'
import { usernameLogin, mobileLogin, scanlogin, checkscan } from '@/app/api/auth'
import useMemberStore from '@/stores/member'
import useConfigStore from '@/stores/config'
import QRCode from "qrcode";

definePageMeta({
    layout: "container"
});

// 校验二维码
const checkScanFn = (key) => {
    let parameter = { key };

    checkscan(parameter).then((res) => {
        let data = res.data;
        switch (data.status) {
            case 'wait':
                setTimeout(() => {
                    checkScanFn(weixinCode.value.key);
                }, 1000);
                break;

            case 'success':
                if (!data.login_data.token) {
                    useCookie('openId').value = data.login_data.openid
                    navigateTo(`/auth/bind`)
                } else {
                    memberStore.setToken(data.login_data.token)
                    useLogin().handleLoginBack()
                }
                break;
            case 'fail':
                weixinCode.value.pastDueContent = data.fail_reason
                weixinCode.value.pastDue = true;
                break;
        }
    }).catch((res) => {
        weixinCode.value.pastDue = true;
        weixinCode.value.pastDueContent = res.msg;
    })
}

// 扫码登录,微信二维码
const weixinCode = ref({
    url: '',
    key: '',
    loading: true,
    pastDue: false,
    pastDueContent: '二维码生成失败'
})

const scanLoginFn = async () => {
    weixinCode.value.loading = true;
    weixinCode.value.pastDue = false;

    try {
        let data = await (await scanlogin()).data;
        weixinCode.value.key = data.key
        await QRCode.toDataURL(data.url, { errorCorrectionLevel: 'L', margin: 0, width: 100 }).then(url => {
            weixinCode.value.url = url
            weixinCode.value.loading = false;
        });

        setTimeout(() => {
            checkScanFn(weixinCode.value.key);
        }, 1000);
    } catch (error) {
        weixinCode.value.loading = false;
        weixinCode.value.pastDue = true;
        weixinCode.value.pastDueContent = '二维码加载失败';
    }
}
scanLoginFn();

const memberStore = useMemberStore()

const configStore = useConfigStore()
configStore.getLoginConfig()

const loginType = computed(() => {
    const value = []
    configStore.login.is_username && (value.push({ type: 'username', title: t('usernameLogin') }))
    configStore.login.is_mobile && (value.push({ type: 'mobile', title: t('mobileLogin') }))
    type.value = value[0] ? value[0].type : ''
    return value
})

const loading = ref(false)
const type = ref('')
const formData = reactive({
    username: '',
    password: '',
    mobile: '',
    mobile_code: '',
    mobile_key: ''
})
const formRef = ref<FormInstance>()
const formRules = computed(() => {
    return {
        'username': {
            required: type.value == 'username',
            message: t('usernamePlaceholder'),
            trigger: ['blur', 'change'],
        },
        'password': {
            required: type.value == 'username',
            message: t('passwordPlaceholder'),
            trigger: ['blur', 'change']
        },
        'mobile': [
            {
                required: type.value == 'mobile',
                message: t('mobilePlaceholder'),
                trigger: ['blur', 'change'],
            },
            {
                validator(rule: any, value: string, callback: any) {
                    if (type.value != 'mobile') return true
                    else return test.mobile(value)
                },
                message: t('mobileError'),
                trigger: ['blur'],
            }
        ],
        'mobile_code': {
            required: type.value == 'mobile',
            message: t('codePlaceholder'),
            trigger: ['change']
        }
    }
})

const handleLogin = async () => {
    await formRef.value?.validate(async (valid, fields) => {
        if (valid) {
            if (loading.value) return
            loading.value = true

            const login = type.value == 'username' ? usernameLogin : mobileLogin

            login(formData).then(async (res) => {
                await memberStore.setToken(res.data.token)
                useLogin().handleLoginBack()
            }).catch(() => {
                loading.value = false
            })
        }
    })
}

const smsCodeRef = ref<AnyObject | null>(null)
const sendSmsCode = async () => {
    await formRef.value?.validateField('mobile', async (valid, fields) => {
        if (valid) {
            smsCodeRef.value?.send()
        }
    })
}
</script>

<style lang="scss" scoped>
/* 渐变文字动画 */
.bg-clip-text {
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}

@keyframes gradient {
    0% {
        background-position: 0% 50%;
    }

    50% {
        background-position: 100% 50%;
    }

    100% {
        background-position: 0% 50%;
    }
}

/* 表单样式优化 */
:deep(.el-form-item) {
    margin-bottom: 0;

    .el-input__wrapper {
        background: rgba(31, 41, 55, 0.5);
        border: 1px solid rgba(107, 114, 128, 0.3);
        border-radius: 0.75rem;
        padding: 12px 16px;
        box-shadow: none;
        transition: all 0.2s;

        &:hover {
            border-color: rgba(139, 92, 246, 0.5);
            background: rgba(31, 41, 55, 0.7);
        }

        &.is-focus {
            border-color: #8b5cf6;
            background: rgba(31, 41, 55, 0.8);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
    }

    .el-input__inner {
        color: white;

        &::placeholder {
            color: rgba(156, 163, 175, 0.6);
        }
    }

    .el-input__prefix,
    .el-input__suffix {
        display: flex;
        align-items: center;
    }

    &.is-error {
        .el-input__wrapper {
            border-color: #ef4444;

            &.is-focus {
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
            }
        }
    }
}

:deep(.el-form-item__error) {
    color: #fca5a5;
    padding-top: 6px;
    font-size: 12px;
}

/* 卡片悬停效果 */
.bg-gradient-to-br {
    transition: all 0.3s ease;
}

.bg-gradient-to-br:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
}

/* 响应式 */
@media (max-width: 1024px) {
    .lg\:w-\[calc\(50\%-12px\)\] {
        width: 100%;
        max-width: 500px;
    }
}

@media (max-width: 640px) {
    .text-4xl {
        font-size: 2rem;
    }

    .max-w-5xl {
        max-width: 100%;
    }
}

/* 二维码加载动画 */
.qr-loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #e5e7eb;
    border-top-color: #8b5cf6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
