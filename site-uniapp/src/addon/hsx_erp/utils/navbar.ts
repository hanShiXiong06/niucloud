/** ERP 自有导航栏尺寸计算，保证插件可在未安装其他业务插件时独立编译。 */
export function getErpNavbarMetrics() {
    const systemInfo = uni.getSystemInfoSync()
    const menuButtonInfo = (() => {
        try {
            // #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
            return uni.getMenuButtonBoundingClientRect()
            // #endif
        } catch (error) {
            return null
        }
        return null
    })()

    const statusTopPx = Number(menuButtonInfo?.top ?? systemInfo.statusBarHeight ?? 0)
    const contentHeightPx = Number(menuButtonInfo?.height ?? 44)
    const capsuleWidthPx = Number(menuButtonInfo?.width ?? 87)
    const bottomGapPx = 8

    return {
        statusTopPx,
        contentHeightPx,
        bottomGapPx,
        navbarHeightPx: statusTopPx + contentHeightPx + bottomGapPx,
        sideWidthRpx: Math.max(96, Math.ceil(capsuleWidthPx * 2 + 30))
    }
}
