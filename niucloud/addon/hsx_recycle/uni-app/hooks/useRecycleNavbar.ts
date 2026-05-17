export function getRecycleNavbarMetrics() {
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
  const capsuleHeightPx = Number(menuButtonInfo?.height ?? 44)
  const capsuleWidthPx = Number(menuButtonInfo?.width ?? 87)
  const bottomGapPx = 8
  const contentHeightPx = capsuleHeightPx
  const navbarHeightPx = statusTopPx + contentHeightPx + bottomGapPx
  const sideWidthRpx = Math.max(96, Math.ceil(capsuleWidthPx * 2 + 30))

  return {
    statusTopPx,
    contentHeightPx,
    bottomGapPx,
    navbarHeightPx,
    sideWidthRpx
  }
}
