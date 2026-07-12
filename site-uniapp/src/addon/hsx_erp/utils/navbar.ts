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

/**
 * 原生导航栏下 ERP 列表工具区的实际高度（rpx）。
 * 小程序页面 viewport 已经从原生导航栏下方开始，严禁再次叠加状态栏/胶囊高度。
 */
export function getErpListHeaderHeightRpx(options: {
    title?: boolean
    search?: boolean
    tabs?: boolean
} = {}) {
    const { title = false, search = true, tabs = true } = options
    const verticalPadding = 32 // 顶部 16 + 底部 16
    const titleHeight = title ? 72 : 0
    const searchHeight = search ? 72 : 0
    const tabsHeight = tabs ? 68 : 0 // margin-top 16 + 标签行约 52
    return verticalPadding + titleHeight + searchHeight + tabsHeight
}
