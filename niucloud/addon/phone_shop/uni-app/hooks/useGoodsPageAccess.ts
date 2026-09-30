import { computed, ref } from 'vue'
import { onHide, onUnload } from '@dcloudio/uni-app'
import useMemberStore from '@/stores/member'
import { enterPhoneShopLogin, finishPhoneShopLogin } from '@/addon/phone_shop/hooks/usePhoneShopLoginNavigation'
import request from '@/utils/request'

export type GoodsPageAccessStatus = 'checking' | 'ready' | 'login' | 'error'

/** 商品页共用入口门禁：先取得最新配置并验证会话，再展示/加载商品。 */
export function useGoodsPageAccess(getTarget: () => {url: string, param?: Record<string, any>}) {
    const memberStore = useMemberStore()
    const config = ref<any>(null)
    const status = ref<GoodsPageAccessStatus>('checking')
    const message = ref('')
    // 返回页面重新检查时仅隐藏内容，允许通过后保留原滚动位置。
    const hasContent = ref(false)
    let verifiedToken: string | null = null
    let generation = 0
    let pending: Promise<boolean> | null = null

    const ready = computed(() => status.value === 'ready' && (
        Number(config.value?.detail_login_required) !== 1 ||
        Boolean(memberStore.token && memberStore.token === verifiedToken)
    ))

    const check = (): Promise<boolean> => {
        if (pending) return pending
        const current = ++generation
        const isCurrent = () => current === generation
        status.value = 'checking'
        message.value = ''
        if (verifiedToken !== memberStore.token) hasContent.value = false

        const requireLogin = () => {
            if (!isCurrent()) return false
            status.value = 'login'
            hasContent.value = false
            message.value = '登录后即可查看商品，登录成功后将返回当前页面。'
            void enterPhoneShopLogin(getTarget()).catch((error: any) => {
                uni.showToast({ title: error?.message || '打开登录页失败，请重试', icon: 'none' })
                if (isCurrent()) { status.value = 'error'; message.value = '打开登录页失败，请重试' }
            })
            return false
        }

        const task = (async() => {
            try {
                if (await finishPhoneShopLogin(getTarget()) || !isCurrent()) return false
                const response: any = await request.get('phone_shop/goods/category/config', {}, {showErrorMessage: false})
                if (!isCurrent()) return false
                const value = response?.data
                if (!value || Array.isArray(value) || ![0, 1, '0', '1'].includes(value.detail_login_required)) {
                    throw new Error('商品访问配置不完整，请联系商家检查配置')
                }
                config.value = value
                const token = memberStore.token
                if (Number(value.detail_login_required) === 1) {
                    if (!token) return requireLogin()
                    let member: any
                    try {
                        // 不能仅凭本地残留 token 放行；复用框架现有的会员校验接口。
                        member = await request.get('member/member', {}, {showErrorMessage: false})
                    } catch (error: any) {
                        if (!isCurrent()) return false
                        if (Number(error?.code) === 401) return requireLogin()
                        throw new Error('暂时无法验证登录状态，请检查网络后重试')
                    }
                    if (!isCurrent()) return false
                    if (token !== memberStore.token) throw new Error('登录状态发生变化，请重试')
                    if (!(Number(member?.data?.member_id) > 0)) return requireLogin()
                }
                verifiedToken = token
                hasContent.value = true
                status.value = 'ready'
                return true
            } catch (error: any) {
                if (!isCurrent()) return false
                status.value = 'error'
                hasContent.value = false
                message.value = error instanceof Error ? error.message : '商品访问配置加载失败，请检查网络后重试'
                return false
            }
        })()
        pending = task
        void task.finally(() => { if (pending === task) pending = null })
        return task
    }

    const ensure = () => pending || (ready.value ? Promise.resolve(true) : check())
    const cancel = () => {
        generation++
        pending = null
        status.value = 'checking'
    }
    // 离开页面后，旧请求不得突然跳登录或打开当前已隐藏的商品内容。
    onHide(cancel)
    onUnload(cancel)

    return {config, status, message, ready, hasContent, check, ensure}
}
