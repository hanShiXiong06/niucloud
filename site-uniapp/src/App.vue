<script setup lang="ts">
import { onLaunch, onShow, onHide } from '@dcloudio/uni-app'
import { launchInterceptor } from '@/utils/interceptor'
import { getToken, isWeixinBrowser, currRoute, deepClone, setThemeColor } from '@/utils/common'
import useUserStore from '@/stores/user'
import useSystemStore from '@/stores/system'
import { useLogin } from '@/hooks/useLogin'

onLaunch((data: any) => {

    
    // 添加初始化拦截器
    launchInterceptor()

    // #ifdef H5
    uni.getSystemInfoSync().platform == 'ios' && (uni.setStorageSync('initUrl', location.href))

    // 传输给后台数据
    window.parent.postMessage(JSON.stringify({
        type: 'appOnLaunch',
        message: '初始化加载完成'
    }), '*');

    // 监听父页面发来的消息
    window.addEventListener('message', event => {
        try {
            let data = {
                type: ''
            };
            if (typeof event.data == 'string') {
                data = JSON.parse(event.data)
            } else if (typeof event.data == 'object') {
                data = event.data
            }
            if (data.type && data.type == 'appOnReady') {
                window.parent.postMessage(JSON.stringify({
                    type: 'appOnReady',
                    message: '加载完成'
                }), '*');
            }
        } catch (e) {
            console.log('uni-app App.vue 接受数据错误', e)
        }
    }, false);

    try {
        uni.hideTabBar() // 隐藏tabbar
    } catch (e) {

    }
 
    // #endif

    // #ifdef MP
    const updateManager = uni.getUpdateManager();
    updateManager.onCheckForUpdate(function (res) {
        // 请求完新版本信息的回调
    });

    updateManager.onUpdateReady(function (res) {
        uni.showModal({
            title: '更新提示',
            content: '新版本已经准备好，是否重启应用？',
            success(res) {
                if (res.confirm) {
                    // 新的版本已经下载好，调用 applyUpdate 应用新版本并重启
                    updateManager.applyUpdate();
                }
            }
        });
    });

    updateManager.onUpdateFailed(function (res) {
        // 新的版本下载失败
    });
    // #endif

    // 获取初始化数据信息
    useSystemStore().getWebsiteInfo()
   

    useSystemStore().getInitFn(async() => {

        let url: any = currRoute()
        // 设置主色调
        // setThemeColor(url)

        // 判断账号锁定后在登录注册页面不进行请求三方登录注册,防止在页面出不去 member_lock 为账号锁定标识
        if (uni.getStorageSync('site_member_lock') && ([ 'app/pages/auth/login'].indexOf(url) != -1)) {
            return false
        }

        // 判断是否已登录
        if (getToken()) {

            useSystemStore().getSiteNavsFn() // 获取导航

            const userStore: any = useUserStore()

            await userStore.setToken(getToken(), () => {
                if (!uni.getStorageSync('site_openid')) {
                    const siteInfo = useUserStore().siteInfo
                    const login = useLogin()

                    // #ifdef MP-WEIXIN
                    if (siteInfo && siteInfo.weapp_openid) {
                        uni.setStorageSync('site_openid', siteInfo.weapp_openid) // 授权登录后存储openid
                    } else {
                        // login.getAuthCode({ updateFlag: true }) // 更新openid
                    }
                    // #endif

                    // #ifdef H5
                    if (isWeixinBrowser()) {
                        if (siteInfo && siteInfo.wx_openid) {
                            uni.setStorageSync('site_openid', siteInfo.wx_openid)
                        } else {
                            if (data.query.code) {
                            // 检测身份是否合法（当前登录的账号是不是我的），openid有效后才能更新登录
                                login.updateOpenid(data.query.code, () => {
                                    login.authLogin({ code: data.query.code })
                                })
                            } else {
                                // 强制获取用户信息
                                login.getAuthCode({ scopes: 'snsapi_userinfo' })
                            }
                        }
                    }
                    // #endif

                }
            })

        }

        if (!getToken()) {

            const login = useLogin()

            // #ifdef MP
            // 第三方平台自动注册登录
            // login.getAuthCode()
            // #endif

            // #ifdef H5
            if (isWeixinBrowser()) {
                data.query.code ? login.authLogin(data.query.code) : login.getAuthCode('snsapi_userinfo')
            }
            // #endif
        }
    })

})

onShow( async () => {
    const userStore: any = useUserStore()
    if (getToken()) {
        await  userStore.getSiteInfo()
    }
})

onHide(() => {
})
</script>

<style>
uni-page-head {
    display: none !important;
}

.uni-modal__bd,
.uni-modal__bd div,
.uni-modal__bd text {
    white-space: pre-line;
}
</style>
