import { getWeappTemplateId } from '@/app/api/system'

export type GoodsSubscriptionAuthorization = 'accepted' | 'rejected' | 'not_configured' | 'automatic'

/**
 * 商品上新/调价订阅的渠道授权。
 *
 * 小程序需要在用户点击订阅时主动申请模板消息；公众号、短信和站内渠道没有
 * 对应的前端授权弹窗，仍由后端牛云 NoticeService 根据站点配置自动发送。
 */
export function useGoodsSubscriptionNotice() {
    const requestAuthorization = async(): Promise<GoodsSubscriptionAuthorization> => {
        // #ifdef MP-WEIXIN
        try {
            const response: any = await getWeappTemplateId('phone_shop_goods_match')
            const templateIds = Array.isArray(response?.data)
                ? response.data.map((id: unknown) => String(id || '').trim()).filter(Boolean)
                : []
            if (!templateIds.length) return 'not_configured'

            return await new Promise<GoodsSubscriptionAuthorization>((resolve) => {
                uni.requestSubscribeMessage({
                    tmplIds: templateIds,
                    success: (result: Record<string, string>) => {
                        const accepted = templateIds.some(id => result?.[id] === 'accept')
                        resolve(accepted ? 'accepted' : 'rejected')
                    },
                    fail: () => resolve('rejected')
                })
            })
        } catch (error) {
            console.warn('[phone_shop] 获取商品订阅消息模板失败', error)
            return 'not_configured'
        }
        // #endif

        // #ifndef MP-WEIXIN
        return 'automatic'
        // #endif
    }

    const explainAuthorization = (status: GoodsSubscriptionAuthorization) => {
        if (status === 'not_configured') {
            uni.showModal({
                title: '订阅已保存',
                content: '商家暂未配置小程序订阅消息模板；公众号、短信或站内通知仍按商家已开启的渠道发送。',
                showCancel: false,
                confirmText: '知道了'
            })
        } else if (status === 'rejected') {
            uni.showModal({
                title: '订阅已保存',
                content: '你暂未授权小程序消息，后续仍可通过公众号、短信或站内通知接收提醒。',
                showCancel: false,
                confirmText: '知道了'
            })
        }
    }

    return { requestAuthorization, explainAuthorization }
}
