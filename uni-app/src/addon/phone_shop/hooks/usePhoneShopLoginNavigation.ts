import { nextTick } from 'vue'
import { useLogin } from '@/hooks/useLogin'
import useMemberStore from '@/stores/member'
import { redirect } from '@/utils/common'
import { getNeedLoginPages } from '@/utils/pages'

const HOME = '/addon/phone_shop/pages/index'
const CONTEXT_KEY = 'phone_shop:goods-login-return'
const RETURN_FLAG = '_phone_shop_auth'
const GOODS_PAGES = ['list', 'category', 'detail'].map(name => `/addon/phone_shop/pages/goods/${name}`)
type Target = { url: string, param?: Record<string, any> }

const routeOf = (page: any) => '/' + String(page?.route || '').replace(/^\//, '')
const isAuthPage = (page: any) => routeOf(page).startsWith('/app/pages/auth/')
const siteKey = () => String(uni.getStorageSync('wap_site_id') || '')

const cleanTarget = (target: Target): Target => {
    if (!GOODS_PAGES.includes(target.url)) throw new Error('商品返回地址无效，请返回商城首页重试')
    const param = { ...target.param }
    delete param[RETURN_FLAG]
    return { url: target.url, param }
}

/** 等页面栈真实更新后再执行下一次导航，避免 H5 navigateBack.success 提前触发。 */
function backTo(delta: number, expectedRoute: string): Promise<void> {
    return new Promise((resolve, reject) => {
        let done = false
        let removeGuard: (() => void) | undefined
        const finish = (failed = false) => {
            if (done) return
            done = true
            clearTimeout(timer)
            removeGuard?.()
            if (!failed && routeOf(getCurrentPages().slice(-1)[0]) === expectedRoute) resolve()
            else reject(new Error('返回页面失败，请重试'))
        }
        const timer = setTimeout(() => finish(true), 3000)
        // #ifdef H5
        const router = getApp().$router
        removeGuard = router.afterEach(() => { void nextTick(() => finish()) })
        uni.navigateBack({ delta, fail: () => finish(true) })
        // #endif
        // #ifndef H5
        uni.navigateBack({ delta, success: () => { void nextTick(() => finish()) }, fail: () => finish(true) })
        // #endif
    })
}

const goHome = () => new Promise<void>((resolve, reject) => {
    redirect({
        url: HOME, mode: 'reLaunch',
        success: () => { void nextTick(resolve) },
        fail: () => reject(new Error('暂时无法返回商城首页，请重试')),
    })
})

/** 在打开原生登录页之前，先留下真正可返回的来源页；不修改公共登录流程。 */
export async function enterPhoneShopLogin(target: Target): Promise<void> {
    const destination = cleanTarget(target)
    const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`
    uni.setStorageSync(CONTEXT_KEY, { id, destination, site: siteKey(), expires: Date.now() + 3600000 })
    const pages = getCurrentPages()
    const protectedPages = getNeedLoginPages()
    let previous = pages.length - 2
    while (previous >= 0 && (isAuthPage(pages[previous])
        || GOODS_PAGES.includes(routeOf(pages[previous]))
        || protectedPages.includes(routeOf(pages[previous])))) previous--

    try {
        if (previous >= 0) await backTo(pages.length - 1 - previous, routeOf(pages[previous]))
        else await goHome() // tabbar/reLaunch 的根页面没有上一页，先建立商城首页。
        if (uni.getStorageSync(CONTEXT_KEY)?.id !== id) return
        // 仍由框架决定账号、手机、微信授权、绑定手机号等登录方式。
        useLogin().setLoginBack({
            url: destination.url,
            param: { ...destination.param, [RETURN_FLAG]: id },
        })
    } catch (error) {
        if (uni.getStorageSync(CONTEXT_KEY)?.id === id) uni.removeStorageSync(CONTEXT_KEY)
        throw error
    }
}

/** 登录完成后只清理本插件发起的登录层，不改动其他业务的登录返回。 */
export async function finishPhoneShopLogin(target: Target): Promise<boolean> {
    if (!useMemberStore().token) return false
    const context = uni.getStorageSync(CONTEXT_KEY)
    if (!context || context.site !== siteKey() || context.expires < Date.now()) {
        if (context) uni.removeStorageSync(CONTEXT_KEY)
        return false
    }
    if (context.destination?.url !== target.url) return false
    // switchTab 会丢弃 query，但同时会清空登录栈；这种情况无需额外导航。
    const pages = getCurrentPages()
    if (!isAuthPage(pages[pages.length - 2])) {
        uni.removeStorageSync(CONTEXT_KEY)
        return false
    }
    if (target.param?.[RETURN_FLAG] !== context.id) return false

    let firstAuth = pages.length - 2
    while (firstAuth > 0 && isAuthPage(pages[firstAuth - 1])) firstAuth--
    const destination = cleanTarget(context.destination)
    await backTo(pages.length - 1 - firstAuth, routeOf(pages[firstAuth]))
    uni.removeStorageSync(CONTEXT_KEY)
    redirect({ url: destination.url, param: destination.param, mode: 'redirectTo', fail: () => {
        uni.showToast({ title: '返回商品页失败，请从商城重新进入', icon: 'none' })
        void goHome().catch(() => {})
    } })
    return true
}
