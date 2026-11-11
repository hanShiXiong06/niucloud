<template>
    <u-popup :show="show" :round="10" @close="handleClose" :closeable="true" bgColor="#fff" zIndex="10081" :closeOnClickOverlay="false">
        <view class="flex flex-col h-[65vh] popup-common" v-if="payInfo" @touchmove.prevent.stop>
            <view class="head">
                <view class="title">{{ t('pay.payTitle') }}</view>
                <view class="flex items-end justify-center w-full text-xl font-bold py-[20rpx] price-font">
                    <view class="text-base mr-[4rpx]">{{ t('currency') }}</view>
                    {{ moneyFormat(payInfo.money) }}
                </view>
            </view>
            <scroll-view scroll-y="true" class="flex-1 pt-[20rpx]">
                <view class="flex text-[28rpx] px-[36rpx] py-[20rpx] mb-[10rpx]">
                    <view class="text-[var(--text-color-light6)]">{{ t('pay.orderInfo') }}</view>
                    <view class="text-right flex-1 pl-[30rpx] truncate">{{ payInfo.body }}</view>
                </view>
                <view class="mx-[var(--popup-sidebar-m)] px-[30rpx] bg-white rounded-[20rpx] bg-[var(--temp-bg)]">
                    <template v-if="payInfo.pay_type_list.length">
                        <view class="pay-item py-[30rpx] flex items-center border-0 border-b border-solid border-[#eee]" v-for="(item, index) in payInfo.pay_type_list" :key="item.key" @click="!loading && !requiresOrderCheck && (type = item.key)">
                            <u-image :src="img(item.icon)" width="50rpx" height="50rpx"></u-image>
                            <view class="flex-1 px-[20rpx] text-[28rpx] font-500">{{ item.name }}</view>
                            <u-icon name="checkbox-mark" color="var(--primary-color)" v-if="item.key == type"></u-icon>
                        </view>
                    </template>
                    <view class="py-[30rpx] text-center text-[24rpx] text-gray-subtitle" v-else>{{ t('pay.notHavePayType') }}</view>
                </view>
                <view v-if="paymentFeedback" class="mx-[var(--popup-sidebar-m)] mt-[20rpx] px-[30rpx] text-[26rpx] leading-[40rpx] text-[var(--text-color-light6)]" role="status">{{ paymentFeedback }}</view>
            </scroll-view>
            <view class="btn-wrap">
                <button class="primary-btn-bg bg-[var(--primary-color)] btn" hover-class="none" :loading="loading" @click="confirmPay">{{ requiresOrderCheck ? '返回订单核对' : t('pay.confirmPay') }}</button>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/locale'
import { getPayInfo, pay } from '@/app/api/pay'
import { img, redirect, isWeixinBrowser, moneyFormat } from '@/utils/common'
import wechat from '@/utils/wechat'
import { preparePaymentClientRequest, executePaymentClientAction, VirtualPaymentClientError, type VirtualPaymentBridge } from '@/utils/virtual-payment'

const prop = defineProps({
    ignorePay: {
        type: Array,
        default () {
            return []
        }
    },
    reformat: {
        type: String,
        default: ''
    }
})

// #ifdef H5
if (isWeixinBrowser()) wechat.init();
// #endif

const show = ref(false)
const loading = ref(false)

const payInfo = ref<AnyObject | null>(null)
const type = ref('')
const requiresOrderCheck = ref(false)
const paymentFeedback = ref('')
let paymentAttempt = 0

const rememberPayment = () => {
    uni.setStorageSync('paymenting', {
        trade_type: payInfo.value?.trade_type,
        trade_id: payInfo.value?.trade_id
    })
}

/**
 * 确认支付
 */
const virtualPaymentBridge = (): VirtualPaymentBridge | undefined => {
    // #ifdef MP-WEIXIN
    if (typeof wx !== 'undefined' && typeof (wx as any).requestVirtualPayment === 'function') {
        return {
            login: options => uni.login({ provider: 'weixin', ...options }),
            requestVirtualPayment: options => (wx as any).requestVirtualPayment(options),
            canIUse: name => typeof wx.canIUse !== 'function' || wx.canIUse(name)
        }
    }
    // #endif
    return undefined
}

const confirmPay = async () => {
    // Client cancellation is not proof of an unpaid/closed server order.
    // Let the owning page reconcile it; do not repeat native payment here.
    if (requiresOrderCheck.value) { handleClose(); return }
    if (uni.$u.test.isEmpty(type.value)) {
        uni.showToast({ title: t('pay.notHavePayType'), icon: 'none' })
        return
    }
    if (type.value == 'friendspay') {
        redirect({
            url: '/app/pages/friendspay/share',
            param: {
                id: payInfo.value?.trade_id,
                type: payInfo.value?.trade_type,
            },
            mode: 'redirectTo'
        })
        return false
    }
    if (loading.value) return
    loading.value = true

    const attempt = ++paymentAttempt
    const tradeType = payInfo.value?.trade_type
    const tradeId = payInfo.value?.trade_id
    const selectedType = type.value
    const clientAction = payInfo.value?.pay_type_list?.find((item: any) => item.key === selectedType)?.client_action
    let clientParams: { login_code?: string }
    try {
        clientParams = await preparePaymentClientRequest(clientAction, virtualPaymentBridge())
    } catch (error) {
        if (attempt !== paymentAttempt) return
        loading.value = false
        uni.showToast({ title: error instanceof VirtualPaymentClientError ? error.message : '付款准备未完成，请重新点击付款', icon: 'none' })
        return
    }
    if (attempt !== paymentAttempt || !show.value) return

    pay({
        trade_type: tradeType,
        trade_id: tradeId,
        type: selectedType,
        openid: uni.getStorageSync('openid') || '',
        ...clientParams
    }).then(async (res: any) => {
        if (attempt !== paymentAttempt || !show.value) return
        if (clientAction || (res.data && typeof res.data === 'object' && res.data.client_action)) {
            await executePaymentClientAction(clientAction, res.data, virtualPaymentBridge())
            if (attempt !== paymentAttempt || !show.value) return
            rememberPayment()
            loading.value = false
            toPayResult()
            return
        }
        switch (selectedType) {
            case 'wechatpay':
                // #ifndef H5
                uni.requestPayment({
                    provider: 'wxpay',
                    ...res.data,
                    success: (res: any) => {
                        toPayResult()
                    },
                    fail: (res: any) => {
                        loading.value = false
                    }
                })
                // #endif
                // #ifdef H5
                if (isWeixinBrowser()) {
                    res.data.timestamp = res.data.timeStamp
                    delete res.data.timeStamp
                    wechat.pay({
                        ...res.data,
                        success: () => {
                            toPayResult()
                        },
                        cancel: () => {
                            loading.value = false
                        }
                    })
                } else {
                    uni.setStorageSync('paymenting', {
                        trade_type: payInfo.value?.trade_type,
                        trade_id: payInfo.value?.trade_id
                    })
                    location.href = res.data.h5_url
                }
                // #endif
                break;
            case 'alipay':
                // #ifdef H5
                if (isWeixinBrowser()) {
                    redirect({
                        url: '/app/pages/pay/browser',
                        param: {
                            trade_type: payInfo.value?.trade_type,
                            trade_id: payInfo.value?.trade_id,
                            alipay: encodeURIComponent(res.data.url)
                        },
                        mode: 'redirectTo'
                    })
                } else {
                    uni.setStorageSync('paymenting', {
                        trade_type: payInfo.value?.trade_type,
                        trade_id: payInfo.value?.trade_id
                    })
                    location.href = res.data.url
                }
                // #endif
                // #ifndef H5
                uni.requestPayment({
                    provider: 'alipay',
                    orderInfo: res.data,
                    success: (res: any) => {
                        toPayResult()
                    },
                    fail: (res: any) => {
                        loading.value = false
                    }
                })
                // #endif
                break;
            default:
                rememberPayment()
                if (res.data.jsapi) {
                    const jsapi = res.data.jsapi
                    // #ifdef MP-WEIXIN
                    uni.requestPayment({
                        provider: 'wxpay',
                        ...jsapi,
                        success: () => {
                            toPayResult()
                        },
                        fail: () => {
                            loading.value = false
                        }
                    })
                    return undefined
                    // #endif

                    // #ifdef H5
                    if (!isWeixinBrowser()) {
                        loading.value = false
                        uni.showToast({ title: 'JS支付请在微信内打开', icon: 'none' })
                        return undefined
                    }
                    const h5Jsapi = {
                        ...jsapi,
                        timestamp: jsapi.timeStamp
                    }
                    delete h5Jsapi.timeStamp
                    wechat.pay({
                        ...h5Jsapi,
                        success: () => {
                            toPayResult()
                        },
                        cancel: () => {
                            loading.value = false
                        }
                    })
                    return undefined
                    // #endif

                    loading.value = false
                    uni.showToast({ title: '当前终端不支持JS支付', icon: 'none' })
                    return undefined
                }
                if (res.data.mini_program) {
                    // #ifdef MP-WEIXIN
                    const miniProgram = res.data.mini_program
                    uni.navigateToMiniProgram({
                        appId: miniProgram.appId,
                        path: miniProgram.path || 'pages/index/index',
                        extraData: miniProgram.extraData || {},
                        envVersion: miniProgram.envVersion || 'release',
                        success: () => {
                            loading.value = false
                            show.value = false
                        },
                        fail: () => {
                            loading.value = false
                        }
                    })
                    return
                    // #endif
                }
                if (res.data.url) {
                    // #ifdef H5
                    window.location.href = res.data.url
                    return
                    // #endif

                    // #ifdef MP
                    redirect({
                        url: '/app/pages/webview/index',
                        param: { src: encodeURIComponent(res.data.url) }
                    })
                    return
                    // #endif

                    // #ifdef APP-PLUS
                    redirect({
                        url: '/app/pages/webview/index',
                        param: { src: encodeURIComponent(res.data.url) }
                    })
                    return
                    // #endif
                }
                if (res.data === true) {
                    toPayResult()
                } else {
                    loading.value = false
                    uni.showToast({ title: '未取得可识别的付款参数，请刷新订单后重试', icon: 'none' })
                }
                break;
        }
    }).catch((error: unknown) => {
        if (attempt !== paymentAttempt) return
        loading.value = false
        if (clientAction) {
            requiresOrderCheck.value = true
            paymentFeedback.value = error instanceof VirtualPaymentClientError && error.code === 'cancelled'
                ? '已退出微信付款，原订单结果尚待核对。请返回订单查看，不要重复付款。'
                : '本次付款结果尚未确认，请返回订单核对。系统不会自动切换付款方式。'
        }
        if (error instanceof VirtualPaymentClientError && error.code !== 'cancelled') {
            uni.showToast({ title: error.message, icon: 'none' })
        }
    })
}

/**
 * 检测是否是支付后返回
 */
uni.$on('checkIsReturnAfterPayment', () => {
    const data = uni.getStorageSync('paymenting')
    if (uni.getStorageSync('paymenting')) {

        redirect({
            url: '/app/pages/pay/result',
            param: {
                trade_type: data.trade_type,
                trade_id: data.trade_id
            },
            mode: 'redirectTo',
            success() {
                uni.removeStorageSync('paymenting')
            }
        })
    }
})
const repeat = ref(false)
const open = (tradeType: string, tradeId: number, payReturn: string = '', scene: string = '') => {

    if (repeat.value || loading.value) return
    const attempt = ++paymentAttempt
    loading.value = false
    repeat.value = true
    show.value = false
    payInfo.value = null
    type.value = ''
    requiresOrderCheck.value = false
    paymentFeedback.value = ''

    // 设置支付后跳转页面
    uni.setStorageSync('payReturn', encodeURIComponent(payReturn))

    // 帮人付款时过滤帮付选项
    const obj: any = {}
    if (scene) {
        obj.scene = scene
    }
    getPayInfo(tradeType, tradeId, obj).then((res: any) => {
        if (attempt !== paymentAttempt) return
        let { data } = res

        if (!data || typeof data !== 'object' || Array.isArray(data) || uni.$u.test.isEmpty(data)) {
            uni.showToast({ title: t('pay.notObtainedInfo'), icon: 'none' })
            return
        }
        if (data.money == 0) {
            payInfo.value = { ...data, pay_type_list: [] }
            toPayResult()
            return
        }
        if (!Array.isArray(data.pay_type_list) || data.pay_type_list.some((item: any) => !item || typeof item !== 'object' || typeof item.key !== 'string' || !item.key)) {
            uni.showToast({ title: t('pay.notObtainedInfo'), icon: 'none' })
            return
        }
        payInfo.value = data
        if(prop.ignorePay) {
            data.pay_type_list = data.pay_type_list.filter((item: any) => {
                return !prop.ignorePay.includes(item.key)
            })
        }
        type.value = data.pay_type_list[0] ? data.pay_type_list[0].key : ''
        show.value = true
    }).catch(() => {}).finally(() => {
        if (attempt === paymentAttempt) repeat.value = false
    })
}

const emits = defineEmits(['close', 'confirm'])

const toPayResult = () => {
    emits('confirm')
    // console.log(prop.reformat)
    if(prop.reformat){
        redirect({
            url: prop.reformat,
            mode: 'redirectTo'
        })
        return
    }

    redirect({
        url: '/app/pages/pay/result',
        param: { trade_type: payInfo.value?.trade_type, trade_id: payInfo.value?.trade_id },
        mode: 'redirectTo'
    })
}


const handleClose = () => {
    paymentAttempt++
    loading.value = false
    repeat.value = false
    uni.removeStorageSync('paymenting')
    show.value = false
    emits('close')
}

defineExpose({
    open,
    payInfo
})
</script>

<style lang="scss" scoped>
.pay-item:last-child {
    border: none;
}
</style>
