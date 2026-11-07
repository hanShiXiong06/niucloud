import { useLogin } from '@/hooks/useLogin'
import { getToken, currRoute } from '@/utils/common'
import { getNeedLoginPages } from '@/utils/pages'

const loginBack = useLogin()
let pendingLogin: ReturnType<typeof setTimeout> | undefined

/**
 * 检测是否需要登录
 */
export function checkNeedLogin(route: AnyObject) {
    if (pendingLogin) clearTimeout(pendingLogin)
    pendingLogin = undefined
    const pages = getNeedLoginPages()

    if (pages.includes(route.path) && !getToken()) {
        const target = route.path
        pendingLogin = setTimeout(() => {
            pendingLogin = undefined
            // A cancelled/backed-out page must not launch its old login timer.
            if (getToken() || '/' + String(currRoute() || '').replace(/^\/+/, '') !== target) return
            loginBack.setLoginBack({ url: route.path, param: route.query || {} })
        }, 100)
    }
}
