import { defineStore } from 'pinia'
import { setToken, removeToken, redirect } from '@/utils/common'
import { bindMobile as bindMobileApi } from '@/app/api/member'
import { getSiteInfo as getSiteInfoApi,usernameLogin, mobileLogin, logout } from '@/app/api/auth'

interface User {
    token: string | null
    siteInfo: any | null
    userInfo:  any | null
    siteId: number | null
}

const useUserStore = defineStore('user', {
    state: (): User => {
        return {
            token: uni.getStorageSync(import.meta.env.VITE_REQUEST_STORAGE_TOKEN_KEY),
            siteInfo: null,
            userInfo: uni.getStorageSync('userinfo') || {},
            siteId: null
        }
    },
    actions: {
        setToken(token: string, callback: any = null) {
            this.token = token
            setToken(token)
            this.getSiteInfo()
            if (callback) callback()
        },
        setUserInfo(data: any) {
            this.userInfo = data
            uni.setStorageSync('userinfo', data);
        },
        setSiteInfo(data: any) {
            this.siteInfo = data
            uni.setStorageSync('siteInfo', data);
        },
        getSiteInfo(callback: any = null) {
            getSiteInfoApi().then((res: any) => {
                this.siteInfo = res.data
                uni.setStorageSync('siteId', res.data.site_id || 0);
                uni.setStorageSync('siteInfo', res.data);
                if (callback) callback();
            }).catch(() => {
                // this.logout()
            })
        },
        logout() {
            if (!this.token) return
            this.token = ''
            this.userInfo = {}
            this.siteInfo = {}
            logout().then(() => {
                removeToken()
                uni.removeStorageSync('siteId');
                uni.removeStorageSync('siteInfo');
                uni.removeStorageSync('userinfo');
                uni.removeStorageSync('site_openid');
                uni.removeStorageSync('site_unionid');
                uni.removeStorageSync('site_pid');
                uni.removeStorageSync('loginBack');
                uni.removeStorageSync('statItemList');
                redirect({ url: '/app/pages/auth/login', mode: 'reLaunch' })
            }).catch(() => {
                removeToken()
                uni.removeStorageSync('siteId');
                uni.removeStorageSync('siteInfo');
                uni.removeStorageSync('userinfo');
                uni.removeStorageSync('site_openid');
                uni.removeStorageSync('site_unionid');
                uni.removeStorageSync('site_pid');
                uni.removeStorageSync('loginBack');
                uni.removeStorageSync('statItemList');
                redirect({ url: '/app/pages/auth/login', mode: 'reLaunch' })
            })
        },
        // 一键绑定手机号
        bindMobile(e: any) {
            if (e.detail.errMsg == 'getPhoneNumber:ok') {
                uni.showLoading({ title: '' })
                bindMobileApi({
                    mobile_code: e.detail.code
                }).then((res: any) => {
                    uni.hideLoading()
                    this.getSiteInfo()
                }).catch(() => {
                    setTimeout(() => {
                        uni.hideLoading()
                    }, 2000);
                })

            }

            if (e.detail.errno == 104) {
                let msg = '用户未授权隐私权限';
                uni.showToast({ title: msg, icon: 'none' })
            }
            if (e.detail.errMsg == "getPhoneNumber:fail user deny") {
                let msg = '用户拒绝获取手机号码';
                uni.showToast({ title: msg, icon: 'none' })
            }

        }
    }
})

export default useUserStore
