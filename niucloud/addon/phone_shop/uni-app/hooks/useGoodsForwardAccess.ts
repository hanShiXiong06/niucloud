import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import { getGoodsForwardAccess } from '@/addon/phone_shop/api/forward'

export function useGoodsForwardAccess() {
    const ensureGoodsForwardAccess = async (backUrl = '/addon/phone_shop/pages/goods/category') => {
        const memberStore = useMemberStore()
        if (!memberStore.token) {
            useLogin().setLoginBack({ url: backUrl })
            return false
        }
        const res: any = await getGoodsForwardAccess()
        const access = res.data || {}
        if (Number(access.allowed) === 1) return true
        if (access.status === 'pending') {
            await uni.showModal({ title: '身份审核中', content: access.message || '资料已经提交，审核通过后会及时通知您', showCancel: false, confirmText: '我知道了' })
            return false
        }
        if (!Number(access.can_apply)) {
            await uni.showModal({ title: '同行专属功能', content: '商品素材转发仅向同行会员开放。当前商家暂未开放线上申请，请联系商家协助开通。', showCancel: false, confirmText: '我知道了' })
            return false
        }
        const content = access.status === 'rejected' && access.review_reason
            ? `上次申请未通过：${access.review_reason}\n可补充资料后重新提交。`
            : `完成同行身份资料后，审核通过即可下载并转发商品素材。`
        const modal: any = await uni.showModal({ title: '申请同行身份', content, cancelText: '暂不申请', confirmText: '填写资料' })
        if (modal.confirm) uni.navigateTo({ url: '/addon/phone_shop/pages/member/forward-application' })
        return false
    }
    return { ensureGoodsForwardAccess }
}
