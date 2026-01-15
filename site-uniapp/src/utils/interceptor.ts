import { language } from '@/locale'
import { checkNeedLogin } from '@/utils/auth'
import { redirect, getToken, currRoute, setThemeColor } from '@/utils/common'


/**
 * 页面跳转拦截器
 */
export const redirectInterceptor = (route: { path: string, query: object }) => {
    route.path = `/${ route.path }`

    // 检测当前访问的是系统（app）还是插件
    // setThemeColor(route.path)

    // #ifdef MP
    route.path.indexOf('addon') != -1 && language.loadAllLocaleMessages('addon', uni.getLocale())
    // #endif

    // 校验是否需要登录
    checkNeedLogin(route)

}

/**
 * 应用初始化拦截器
 */
export const launchInterceptor = () => {
    const launch = uni.getLaunchOptionsSync()
    launch.path = `/${ launch.path }`

    // 检测当前访问的是系统（app）还是插件
    // setThemeColor(launch.path);

    // 加载语言包
    language.loadAllLocaleMessages('app', uni.getLocale())

    // #ifdef H5
    language.loadAllLocaleMessages('addon', uni.getLocale())
    // #endif

    // 校验是否需要登录
    checkNeedLogin(launch)

    // 存储分享会员id
    if (launch.query && launch.query.mid) {
        uni.setStorageSync('pid', launch.query.mid)
    }
}

