import { redirect, isWeixinBrowser, urlDeconstruction, currRoute } from '@/utils/common'
import { weappLogin, updateWeappOpenid, updateWechatOpenid,wechatUser, wechatUserLogin } from '@/app/api/auth'
import { getWechatAuthCode } from '@/app/api/system'
import useUserStore from '@/stores/user'

export function useLogin() {
    /**
     * 设置登录返回页
     */
    const setLoginBack = (data: redirectOptions) => {
        uni.setStorage({ key: 'loginBack', data })
        setTimeout(() => {
            redirect({ url: '/app/pages/auth/login', mode: 'redirectTo' })
        })
    }

    /**
     * 执行登录后跳转
     */
    const handleLoginBack = () => {
        uni.getStorage({
            key: 'loginBack',
            success: (res: any) => {
                res ? redirect(
                    {
                        ...res.data,
                        mode: 'redirectTo'
                    }
                ) : redirect({ url: '/app/pages/index/index', mode: 'switchTab' })
            },
            fail: (res) => {
                redirect({ url: '/app/pages/index/index', mode: 'switchTab' })
            }
        })
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
