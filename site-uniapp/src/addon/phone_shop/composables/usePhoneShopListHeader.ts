import { computed, ref } from 'vue'
import { pxToRpx } from '@/utils/common'

/**
 * 商家端商城列表页统一导航尺寸。
 * 小程序端需要避开胶囊，列表起点必须与自定义导航真实高度保持一致。
 */
export function usePhoneShopListHeader() {
    const isMp = ref(false)

    // #ifdef MP
    isMp.value = true
    // #endif

    const metrics = (() => {
        const systemInfo = uni.getSystemInfoSync()
        let menuButtonInfo: any = null
        try {
            // #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
            menuButtonInfo = uni.getMenuButtonBoundingClientRect()
            // #endif
        } catch (error) {}

        const statusTopPx = Number(menuButtonInfo?.top ?? systemInfo.statusBarHeight ?? 0)
        const contentHeightPx = Number(menuButtonInfo?.height || 32)
        const bottomGapPx = 8
        const windowWidth = Number(systemInfo.windowWidth || 375)
        const rightInsetPx = menuButtonInfo?.left
            ? Math.max(12, windowWidth - Number(menuButtonInfo.left) + 8)
            : 12

        return {
            statusTopPx,
            contentHeightPx,
            bottomGapPx,
            rightInsetPx,
            navbarHeightPx: statusTopPx + contentHeightPx + bottomGapPx,
        }
    })()

    const customNavStyle = [
        `height:${ metrics.navbarHeightPx }px`,
        `padding-top:${ metrics.statusTopPx }px`,
        `padding-bottom:${ metrics.bottomGapPx }px`,
        `padding-right:${ metrics.rightInsetPx }px`,
    ].join(';') + ';'

    const listTop = computed(() => isMp.value
        ? `${ pxToRpx(metrics.navbarHeightPx) + 88 }rpx`
        : '174rpx')
    const canGoBack = computed(() => getCurrentPages().length > 1)

    function handleNavAction() {
        if (canGoBack.value) {
            uni.navigateBack({
                delta: 1,
                fail: () => uni.reLaunch({ url: '/app/pages/index/index' }),
            })
            return
        }
        uni.reLaunch({ url: '/app/pages/index/index' })
    }

    return { isMp, customNavStyle, listTop, canGoBack, handleNavAction }
}
