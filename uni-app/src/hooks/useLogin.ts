import { redirect, isWeixinBrowser, urlDeconstruction, currRoute, getToken } from '@/utils/common'
import { getAppPages, getSubPackagesPages, getNeedLoginPages, getTabbarPages, getFirstPage } from '@/utils/pages'
import {
    weappLogin,
    updateWeappOpenid,
    updateWechatOpenid,
    wechatUser,
    wechatUserLogin, 
    updateWechatOpenidByH5,
    wxappLogin
} from '@/app/api/auth'
import { getWechatAuthCode } from '@/app/api/system'
import useMemberStore from '@/stores/member'
import useConfigStore from '@/stores/config'
import useSystemStore from '@/stores/system'
import { ref } from 'vue'

// All hook instances share one navigation flow, not another user's business data.
let loginNavigationEpoch = 0
let loginNavigationCancelled = false
let loginRedirectTimer: ReturnType<typeof setTimeout> | undefined
const loginRoutePath = (value: unknown) => typeof value === 'string' ? '/' + value.replace(/^\/+/, '').split(/[?#]/)[0] : ''
const isAuthenticationRoute = (value: string) => /^\/app\/pages\/auth(?:\/|$)/.test(value)

export function useLogin() {
    let flowEpoch = loginNavigationEpoch
    const isCurrentLogin = (expectedEpoch = flowEpoch) => !loginNavigationCancelled && expectedEpoch === loginNavigationEpoch
    const beginLogin = () => {
        flowEpoch = ++loginNavigationEpoch
        loginNavigationCancelled = false
        if (loginRedirectTimer) clearTimeout(loginRedirectTimer)
        loginRedirectTimer = undefined
        return flowEpoch
    }
    /** Cancellation is not a successful login and must never reopen a protected page. */
    const cancelLogin = () => {
        loginNavigationEpoch++
        loginNavigationCancelled = true
        if (loginRedirectTimer) clearTimeout(loginRedirectTimer)
        loginRedirectTimer = undefined
        uni.removeStorageSync('loginBack')
        const registered = new Set([...getAppPages(), ...getSubPackagesPages()].map(loginRoutePath))
        const protectedPages = new Set(getNeedLoginPages().map(loginRoutePath))
        const publicPage = (path: string) => registered.has(path) && !protectedPages.has(path) && !isAuthenticationRoute(path)
        const fallback = () => {
            const path = [getFirstPage(), ...getTabbarPages(), ...registered].map(loginRoutePath).find(publicPage)
            if (path) redirect({ url: path, mode: 'reLaunch', fail: () => uni.showToast({ title: '页面未打开，请稍后重试', icon: 'none' }) })
            else uni.showToast({ title: '暂无可浏览页面，请联系商家', icon: 'none' })
        }
        const stack = getCurrentPages()
        for (let index = stack.length - 2; index >= 0; index--) {
            const page = stack[index] as any
            if (!publicPage(loginRoutePath(page?.route || page?.$page?.route || page?.$page?.fullPath))) continue
            uni.navigateBack({ delta: stack.length - 1 - index, fail: fallback })
            return
        }
        fallback()
    }
    /**
     * 设置登录返回页
     */
    const setLoginBack = (data: redirectOptions) => {
        beginLogin()
        const scheduledEpoch = flowEpoch
        uni.setStorageSync('loginBack', data)
        loginRedirectTimer = setTimeout(() => {
            loginRedirectTimer = undefined
            if (loginNavigationCancelled || scheduledEpoch !== loginNavigationEpoch || getToken() || isAuthenticationRoute(loginRoutePath(currRoute()))) return
            const config = useConfigStore()
            const systemStore = useSystemStore()

            // #ifdef MP
            if (!uni.getStorageSync('autoLoginLock')  && config.login.is_bind_mobile) {
                uni.setStorageSync('isBindMobile', true) // 强制绑定手机号标识
            }
            // #endif

            // #ifdef H5
            if (!uni.getStorageSync('autoLoginLock') && isWeixinBrowser() && config.login.is_bind_mobile) {
                uni.setStorageSync('isBindMobile', true) // 强制绑定手机号标识
            }
            // #endif

            // 如果只开启了账号密码登录，就不需要跳转到登录中间页了，直接进入普通账号密码登录页面

            // #ifdef MP
            if (config.login.is_username && !config.login.is_mobile && !config.login.is_auth_register) {
                redirect({ url: '/app/pages/auth/login', param: { type: 'username' } })
            } else if (systemStore.initStatus == 'finish' && !config.login.is_username && !config.login.is_mobile && !config.login.is_auth_register) {
                uni.showToast({ title: '商家未开启登录注册', icon: 'none' })
            } else {
                redirect({ url: '/app/pages/auth/index' })
            }
            // #endif

            // #ifdef H5
            if (isWeixinBrowser()) {
                // 微信浏览器
                if (config.login.is_username && !config.login.is_mobile && !config.login.is_auth_register) {
                    redirect({ url: '/app/pages/auth/login', param: { type: 'username' } })
                } else if (systemStore.initStatus == 'finish' && !config.login.is_username && !config.login.is_mobile && !config.login.is_auth_register) {
                    uni.showToast({ title: '商家未开启登录注册', icon: 'none' })
                } else {
                    redirect({ url: '/app/pages/auth/index' })
                }
            } else {
                // 普通浏览器
                if (config.login.is_username && !config.login.is_mobile) {
                    redirect({ url: '/app/pages/auth/login', param: { type: 'username' } })
                } else if (systemStore.initStatus == 'finish' && !config.login.is_username && !config.login.is_mobile) {
                    uni.showToast({ title: '商家未开启登录注册', icon: 'none' })
                } else {
                    redirect({ url: '/app/pages/auth/index' })
                }
            }
            // #endif
            
            // #ifdef APP-PLUS
            if (config.login.is_username && !config.login.is_mobile) {
                redirect({ url: '/app/pages/auth/login', param: { type: 'username' } })
            } else if (systemStore.initStatus == 'finish' && !config.login.is_username && !config.login.is_mobile) {
                uni.showToast({ title: '商家未开启登录注册', icon: 'none' })
            } else {
                redirect({ url: '/app/pages/auth/index' })
            }
            // #endif
        })
    }

    /**
     * 执行登录后跳转
     */
    const ler = ref(0)
    const handleLoginBack = (e) => {
        if (loginNavigationCancelled || flowEpoch !== loginNavigationEpoch) return
        const navigationEpoch = flowEpoch
          ler.value = e
        uni.removeStorageSync('autoLoginLock')
        uni.getStorage({
            key: 'loginBack',
            success: (res: any) => {
                if (loginNavigationCancelled || navigationEpoch !== loginNavigationEpoch) return
                if(ler.value == 1){
                    redirect({
                        url:'/addon/home_service/technician/pages/member/index',
                        mode: 'redirectTo'
                    })
                }else if(ler.value == 2){
                    redirect({
                        url:'/addon/home_service/store/pages/member/index',
                        mode: 'redirectTo'
                    })
                }else{
                      res ? redirect(
                    {
                        ...res.data,
                        mode: 'redirectTo'
                    }
                ) : redirect({ url: '/app/pages/index/index', mode: 'switchTab' })
                }
              
            },
            fail: (res) => {
                if (loginNavigationCancelled || navigationEpoch !== loginNavigationEpoch) return
                  console.log(ler.value)
                if(ler.value==1){
                    redirect({ url:'/addon/home_service/technician/pages/member/index', mode: 'switchTab' })

                }else  if(ler.value==2){
                    redirect({ url:'/addon/home_service/store/pages/member/index', mode: 'switchTab' })
                }else{
                    redirect({ url:'/app/pages/index/index', mode: 'switchTab' })

                }
            }
        })
    }

    /**
     * 授权登录
     * @param params { code, backFlag, successCallback }
     */
    const authLogin = (params: any) => {
        const requestEpoch = flowEpoch
        const requestCurrent = () => !loginNavigationCancelled && requestEpoch === loginNavigationEpoch
        let obj: any = {
            code: params.code,
            nickname: params.nickname,
            headimg: params.headimg,
            mobile: params.mobile,
            mobile_code: params.mobile_code
        };
        uni.getStorageSync('pid') && (Object.assign(obj, { pid: uni.getStorageSync('pid') }))

        // #ifdef MP-WEIXIN
        weappLogin(obj).then((res: any) => {
            if (!requestCurrent()) return
            if (res.data.token) {
                useMemberStore().setToken(res.data.token, () => {
                    if (!requestCurrent()) return
                    const config = useConfigStore()
                    const memberInfo: any = useMemberStore().info

                    // 老用户不控制强制绑定手机号
                    if (uni.getStorageSync('wap_member_not_control_mobile')) {
                        uni.removeStorageSync('wap_member_not_control_mobile');
                    }

                    if (memberInfo) {
                        memberInfo.weapp_openid && uni.setStorageSync('openid', memberInfo.weapp_openid)
                        if (memberInfo.mobile) {
                            uni.setStorageSync('wap_member_mobile', memberInfo.mobile) // 存储会员手机号，防止重复请求微信获取手机号接口
                        } else {
                            uni.setStorageSync('wap_member_not_control_mobile', true) // 老用户不控制强制绑定手机号
                        }
                    }

                    // todo 已注册的会员不受影响
                    // 开启绑定手机号标识
                    if (uni.getStorageSync('isBindMobile')) {
                        uni.removeStorageSync('isBindMobile');
                    }
                    //
                    // if (config.login.is_bind_mobile && memberInfo && !memberInfo.mobile) {
                    //     uni.setStorageSync('isBindMobile', true)
                    // }

                    if (params.successCallback) params.successCallback(res.data)

                    if (params.backFlag) handleLoginBack() // 一键登录返回
                })
            } else {
                // 强制获取昵称和头像，先存储起来
                uni.setStorageSync('openid', res.data.openid)
                uni.setStorageSync('unionid', res.data.unionid)
            }
        }).catch((err) => {
            if (!requestCurrent()) return
            uni.showToast({ title: err.msg, icon: 'none' })
            if (params.successCallback) params.successCallback()
        })
        // #endif

        // #ifdef H5
        wechatUser(obj).then((user_res: any) => {
            if (!requestCurrent()) return
            if (user_res.data) {
                wechatUserLogin(user_res.data).then((res: any) => {
                    if (!requestCurrent()) return
                    const config = useConfigStore()
                    if (res.data.token) {
                        uni.removeStorageSync('member_lock')
                        useMemberStore().setToken(res.data.token, () => {
                            if (!requestCurrent()) return
                            const memberInfo = useMemberStore().info
                            memberInfo && memberInfo.wx_openid && uni.setStorageSync('openid', memberInfo.wx_openid)
                            // todo 已注册的会员不受影响
                            // 开启绑定手机号标识
                            if (uni.getStorageSync('isBindMobile')) {
                                uni.removeStorageSync('isBindMobile');
                            }
                            // if (config.login.is_bind_mobile && memberInfo && !memberInfo.mobile) {
                            //     uni.setStorageSync('isBindMobile', true)
                            // }

                            // 老用户不控制强制绑定手机号
                            if (uni.getStorageSync('wap_member_not_control_mobile')) {
                                uni.removeStorageSync('wap_member_not_control_mobile');
                            }

                            if (memberInfo) {
                                if (memberInfo.mobile) {
                                    uni.setStorageSync('wap_member_mobile', memberInfo.mobile) // 存储会员手机号，防止重复请求微信获取手机号接口
                                } else {
                                    uni.setStorageSync('wap_member_not_control_mobile', true) // 老用户不控制强制绑定手机号
                                }
                            }

                            let loginBack = uni.getStorageSync('loginBack');
                            if (loginBack && loginBack.url && currRoute() == 'app/pages/auth/index') {
                                handleLoginBack(); // 跳转到上一个页面
                            }
                        })
                    } else {
                        // 强制获取昵称和头像，先存储起来
                        uni.setStorageSync('openid', res.data.openid)
                        uni.setStorageSync('unionid', res.data.unionid)
                        if(res.data.nickname){
                            uni.setStorageSync('nickname', res.data.nickname)
                        }
                        if(res.data.avatar){
                            uni.setStorageSync('avatar', res.data.avatar)
                        }

                        // 开启绑定手机号标识
                        if (uni.getStorageSync('isBindMobile')) {
                            uni.removeStorageSync('isBindMobile');
                        }

                        if (config.login.is_bind_mobile) {
                            uni.setStorageSync('isBindMobile', true)
                        }
                    }
                }).catch((err) => {
                    if (!requestCurrent()) return
                    uni.setStorageSync('member_lock', true)
                })
            }
        }).catch((err) => {
            if (!requestCurrent()) return
            if (err.code == -1) {
                getAuthCode({ scopes: 'snsapi_userinfo' })
            } else {
                uni.showToast({ title: err.msg, icon: 'none' })
            }
        })
        // #endif
        
        // #ifdef APP-PLUS
        wxappLogin(obj).then((res) => {
            if (!requestCurrent()) return
            if (res.data.token) {
                useMemberStore().setToken(res.data.token, () => {
                    handleLoginBack();
                })
            } else {
                uni.setStorageSync('openid', res.data.openid)
                uni.setStorageSync('unionid', res.data.unionid)
                if(res.data.nickname) uni.setStorageSync('nickname', res.data.nickname)
                if(res.data.avatar) uni.setStorageSync('avatar', res.data.avatar)
            }
            
            if (params.successCallback) params.successCallback(res.data)
        }).catch((err) => {
            if (!requestCurrent()) return
            uni.showToast({ title: err.msg, icon: 'none' })
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
            useMemberStore().getMemberInfo(() => {
                const memberInfo = useMemberStore().info
                memberInfo && memberInfo.weapp_openid && uni.setStorageSync('openid', memberInfo.weapp_openid)
            })
        })
        // #endif

        // #ifdef H5
        updateWechatOpenid(obj).then((res) => {
            useMemberStore().getMemberInfo(() => {
                const memberInfo = useMemberStore().info
                memberInfo && memberInfo.wx_openid && uni.setStorageSync('openid', memberInfo.wx_openid)
                if (callback) callback();
            })
        })
        // #endif
    }
    /**
     * 获取授权码
     * @param params { scopes, updateFlag, backFlag, successCallback }
     */
    const getAuthCode = (params: any = {}) => {
        const requestEpoch = flowEpoch
        const requestCurrent = () => !loginNavigationCancelled && requestEpoch === loginNavigationEpoch
        params.scopes = params.scopes || 'snsapi_base'; // 公众号用

        // 微信小程序用
        params.updateFlag = params.updateFlag || false; // updateFlag：更新openid
        params.backFlag = params.backFlag || false; // backFlag 控制一键登录返回
        params.successCallback = params.successCallback || null;
        params.nickname = params.nickname || '';
        params.headimg = params.headimg || '';
        params.mobile = params.mobile || '';
        params.mobile_code = params.mobile_code || '';

        // #ifdef MP-WEIXIN
        wx.login({
            success(res: any) {
                if (!requestCurrent()) return
                if (res.code) {
                    params.updateFlag ? updateOpenid(res.code) : authLogin({
                        code: res.code,
                        nickname: params.nickname,
                        headimg: params.headimg,
                        mobile: params.mobile,
                        mobile_code: params.mobile_code,
                        backFlag: params.backFlag,
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
        if (url.indexOf('app/pages/auth/index') != -1) {
            let loginBack = uni.getStorageSync('loginBack');
            if (loginBack) {
                url = url.replace('app/pages/auth/index', loginBack.url.substr(1))
                if (loginBack.param) {
                    if (url.indexOf('?') != -1) {
                        for (let key in loginBack.param) {
                            url += `&${ key }=${ loginBack.param[key] }`;
                        }
                    } else {
                        let count = 0;
                        for (let key in loginBack.param) {
                            if (count == 0) {
                                url += `?${ key }=${ loginBack.param[key] }`;
                            } else {
                                url += `&${ key }=${ loginBack.param[key] }`;
                            }
                            count++;
                        }
                    }
                }
            } else {
                url = url.replace('app/pages/auth/index', 'app/pages/index/index')
            }

        } else {
            let query: any = urlDeconstruction(location.href).query
            query.code && (delete query.code)
            Object.keys(query).length && (url += uni.$u.queryParams(query))
        }
        // // 强制授权加强制绑定手机号定义URL
        // if(uni.getStorageSync('mandatory_Mobile')){
        //     url = `${ location.origin }${ location.pathname }`
        // }
        getWechatAuthCode({
            url,
            scopes: params.scopes
        }).then((res: any) => {
            if (!requestCurrent()) return
            uni.setStorageSync('wechat_login_back', true) // 微信公众号手动授权登录回调标识
            // uni.setStorageSync('mandatory_Mobile', true) // 微信授权登录+强制绑定手机号标识 
            // location.replace(res.data.url);
            location.href = res.data.url
        })

        // #endif
        
        // #ifdef APP-PLUS
        uni.login({
        	"provider": "weixin",
        	"onlyAuthorize": true, // 微信登录仅请求授权认证
        	success: function(event){
                if (!requestCurrent()) return
        		const { code } = event
                authLogin({ code })
        	},
        	fail: function (err) {
                uni.showToast({ title: err.msg, icon: 'none' })
            }
        })
        // #endif
    }

    const updateWechatOpenidForH5 = (wx_openid: any) => {
        if(!wx_openid) return;
        updateWechatOpenidByH5({ wx_openid }).then(res => {
            const memberInfo = useMemberStore().info
            memberInfo && memberInfo.wx_openid && uni.setStorageSync('openid', memberInfo.wx_openid)
        })
    }

    return {
        beginLogin,
        cancelLogin,
        isCurrentLogin,
        getLoginEpoch: () => flowEpoch,
        isLoginCancelled: () => loginNavigationCancelled,
        setLoginBack,
        handleLoginBack,
        authLogin,
        updateOpenid,
        getAuthCode,
        updateWechatOpenidForH5
    }
}
