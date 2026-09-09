import { ref } from 'vue'
import { getGoodsSubscriptionCapability } from '@/addon/phone_shop/api/goods'

export interface GoodsSubscriptionAuthorization {
    status: 'accepted' | 'rejected' | 'not_configured' | 'unsupported' | 'not_ready'
    template_id?: string
}

/**
 * 商品上新/调价订阅的渠道授权。
 *
 * 进入页面先预取模板；点击处理器内直接调起授权，不把网络请求插在用户手势之前。
 */
export function useGoodsSubscriptionNotice() {
    const capability = ref<any>(null)
    const preparing = ref(false)
    let pending: Promise<void> | null = null
    const prepare = (): Promise<void> => {
        if (pending) return pending
        preparing.value = true
        pending = getGoodsSubscriptionCapability().then((res: any) => {
            capability.value = res.data || null
        }).catch(() => {
            capability.value = null
        }).finally(() => { preparing.value = false; pending = null })
        return pending!
    }

    const requestAuthorization = (): Promise<GoodsSubscriptionAuthorization> => {
        // #ifdef MP-WEIXIN
        if (!capability.value) return Promise.resolve({ status: 'not_ready' })
        if (!capability.value.enabled || !capability.value.template_id) return Promise.resolve({ status: 'not_configured' })
        const id = String(capability.value.template_id)
        return new Promise<GoodsSubscriptionAuthorization>((resolve) => {
            try {
                uni.requestSubscribeMessage({
                    tmplIds: [id],
                    success: (result: Record<string, string>) => {
                        resolve({ status: result?.[id] === 'accept' ? 'accepted' : 'rejected', template_id: id })
                    },
                    fail: () => resolve({ status: 'rejected', template_id: id })
                })
            } catch { resolve({ status: 'rejected', template_id: id }) }
        })
        // #endif

        // #ifndef MP-WEIXIN
        return Promise.resolve({ status: 'unsupported' })
        // #endif
    }

    const explainAuthorization = (authorization: GoodsSubscriptionAuthorization) => {
        if (authorization.status !== 'accepted') {
            const messages = {
                rejected: '未允许微信提醒，不影响浏览商品。需要时可以再次订阅。',
                not_configured: '商家尚未开通微信上新提醒，请联系商家。',
                unsupported: '微信订阅提醒需要在本站销售小程序中开启，网页不能代替小程序授权。',
                not_ready: '订阅信息尚未准备好，请稍后重试。'
            }
            uni.showModal({
                title: '微信提醒未开启',
                content: messages[authorization.status],
                showCancel: false,
                confirmText: '知道了'
            })
        }
    }

    return { capability, preparing, prepare, requestAuthorization, explainAuthorization }
}
