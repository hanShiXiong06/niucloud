import { redirect } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'
import useMemberStore from '@/stores/member'
import { needsGoodsDetailLogin } from '@/addon/phone_shop/utils/goods-card'

export function useGoodsDetailNavigation() {
    const memberStore = useMemberStore()
    const login = useLogin()

    const openGoodsDetail = (goodsId: string | number, config: any) => {
        const target = { url: '/addon/phone_shop/pages/goods/detail', param: { goods_id: goodsId } }
        if (needsGoodsDetailLogin(config, memberStore.token)) {
            login.setLoginBack(target)
            return false
        }
        redirect(target)
        return true
    }

    return { openGoodsDetail }
}
