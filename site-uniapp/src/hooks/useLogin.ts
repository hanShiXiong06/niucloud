import { redirect, isWeixinBrowser, urlDeconstruction, currRoute } from '@/utils/common'
import { weappLogin, updateWeappOpenid, updateWechatOpenid,wechatUser, wechatUserLogin } from '@/app/api/auth'
import { getWechatAuthCode } from '@/app/api/system'
import useUserStore from '@/stores/user'
import { getAppPages, getSubPackagesPages, getTabbarPages } from '@/utils/pages'

export function useLogin() {
    /**
     * 设置登录返回页
     */
    const setLoginBack = (data: redirectOptions) => {
        // 先保存再离开，避免异步写入晚于登录页读取。
        uni.setStorageSync('loginBack', data)
        setTimeout(() => {
            redirect({ url: '/app/pages/auth/login', mode: 'redirectTo' })
        })
    }

    /**
     * 执行登录后跳转
     */
    const handleLoginBack = async (): Promise<boolean> => {
        const home = '/app/pages/index/index'
        const cached = uni.getStorageSync('loginBack')
        const rawUrl = typeof cached?.url === 'string' ? cached.url.trim() : ''
        const path = rawUrl.split(/[?#]/)[0]
        const knownPages = [...getAppPages(), ...getSubPackagesPages()]
        const valid = path !== '/app/pages/auth/login' && knownPages.includes(path)
        const tab = getTabbarPages().includes(path)
        const params = cached?.param && typeof cached.param === 'object' && !Array.isArray(cached.param) ? cached.param : {}
        const query = Object.keys(params).map(key => encodeURIComponent(key) + '=' + encodeURIComponent(String(params[key] ?? ''))).join('&')
        const url = rawUrl.split('#')[0]
        const target = valid ? {
            url: tab ? path : url + (query ? (url.includes('?') ? '&' : '?') + query : ''),
            mode: tab ? 'switchTab' : 'redirectTo'
        } : { url: home, mode: 'switchTab' }

        const navigate = (options: any, relaunch = false) => new Promise<boolean>((resolve) => {
            let settled = false
            const finish = (ok: boolean) => {
                if (settled) return
                settled = true
                clearTimeout(timer)
                resolve(ok)
            }
            // 某些客户端拦截跳转后没有回调，也不能让登录按钮一直锁住。
            const timer = setTimeout(() => finish(false), 8000)
            const callbacks = { success: () => finish(true), fail: () => finish(false) }
            try {
                if (relaunch) uni.reLaunch({ url: home, ...callbacks })
                else redirect({ ...options, ...callbacks })
            } catch (_) { finish(false) }
        })

        let opened = await navigate(target)
        if (!opened && target.url !== home) opened = await navigate({ url: home, mode: 'switchTab' })
        if (!opened) opened = await navigate({}, true)
        if (opened) {
            // 成功后消费返回地址；不要影响其他登录流程刚保存的新地址。
            if (JSON.stringify(uni.getStorageSync('loginBack')) === JSON.stringify(cached)) uni.removeStorageSync('loginBack')
        } else {
            uni.showToast({ title: '已登录，但页面打开失败，请点击“进入工作台”重试', icon: 'none', duration: 3000 })
        }
        return opened
    }

    /**
     * 授权登录
     * @param params { code, backFlag, successCallback }
     */
    const authLogin = (params: any) => {
        let obj: any = {
            code: params.code
        };

        // #ifdef MP-WEIXIN
        uni.getStorageSync('site_pid') && (Object.assign(obj, { pid: uni.getStorageSync('site_pid') }))
        weappLogin(obj).then((res: any) => {
            if (res.data.token) {
                useUserStore().setToken(res.data.token, () => {
                    const siteInfo: any = useUserStore().siteInfo; // 站点信息
                    if (params.successCallback) params.successCallback(res.data)

                })
            } else {
                // 强制获取昵称和头像，先存储起来
                uni.setStorageSync('site_openid', res.data.openid)
                uni.setStorageSync('site_unionid', res.data.unionid)
            }
        }).catch((err) => {
            uni.showToast({ title: err.msg, icon: 'none' })
            if (params.successCallback) params.successCallback()
        })
        // #endif

        // #ifdef H5
        uni.getStorageSync('site_pid') && (Object.assign(obj, { pid: uni.getStorageSync('site_pid') }))
        wechatUser(obj).then((user_res: any) => {
            if (user_res.data) {
                wechatUserLogin(user_res.data).then((res: any) => {
                    if (res.data.token) {
                        uni.removeStorageSync('site_member_lock')
                        useUserStore().setToken(res.data.token, () => {
                            const siteInfo = useUserStore().siteInfo
                            siteInfo && siteInfo.wx_openid && uni.setStorageSync('site_openid', siteInfo.wx_openid)
                

                            let loginBack = uni.getStorageSync('loginBack');
                            if (loginBack && loginBack.url && currRoute() == 'app/pages/auth/login') {
                                handleLoginBack(); // 跳转到上一个页面
                            }
                        })
                    } else {
                        // 强制获取昵称和头像，先存储起来
                        uni.setStorageSync('site_openid', res.data.openid)
                        uni.setStorageSync('site_unionid', res.data.unionid)
                    }
                }).catch((err) => {
                    uni.setStorageSync('site_member_lock', true)
                })
            }
        }).catch((err) => {
            if (err.msg == -1) {
                getAuthCode({ scopes: 'snsapi_userinfo' })
            } else {
                uni.showToast({ title: err.msg, icon: 'none' })
            }
        })
        // #endif
    }
    /**
     * 登录普通账号后修改openid
     * @param code
     * @param callback
     */
    const updateOpenid = (code: string | null, callback: any = null) => {
        let obj: any = {
            code
        };

        // #ifdef MP-WEIXIN
        updateWeappOpenid(obj).then((res) => {
            useUserStore().getSiteInfo(()=>{})
        })
        // #endif

        // #ifdef H5
        updateWechatOpenid(obj).then((res) => {
            useUserStore().getSiteInfo(()=>{})
        })
        // #endif
    }
    /**
     * 获取授权码
     * @param params { scopes, updateFlag, backFlag, successCallback }
     */
    const getAuthCode = (params: any = {}) => {
        params.scopes = params.scopes || 'snsapi_base'; // 公众号用

        // 微信小程序用
        params.updateFlag = params.updateFlag || false; // updateFlag：更新openid
        params.successCallback = params.successCallback || null;

        // #ifdef MP-WEIXIN
        wx.login({
            success(res: any) {
                if (res.code) {
                    params.updateFlag ? updateOpenid(res.code) : authLogin({
                        code: res.code,
                        successCallback: params.successCallback
                    })
                } else {
                    console.log('登录失败！' + res.errMsg)
                }
            }
        })
        // #endif

        // #ifdef H5

        let url = `${ location.origin }${ location.pathname }`

        // 如果当前在登录中间页，那么要跳转到首页
        let query: any = urlDeconstruction(location.href).query
        query.code && (delete query.code)
        Object.keys(query).length && (url += uni.$u.queryParams(query))
        
        getWechatAuthCode({
            url,
            scopes: params.scopes
        }).then((res: any) => {
            location.href = res.data.url
        })

        // #endif
    }

    return {
        setLoginBack,
        handleLoginBack,
        authLogin,
        updateOpenid,
        getAuthCode
    }
}
