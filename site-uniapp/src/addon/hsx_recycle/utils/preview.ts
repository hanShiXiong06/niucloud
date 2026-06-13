/**
 * 图片预览——统一走官方 uni.previewImage（原生大图预览，支持双指缩放、长按保存、左右滑动）。
 * 替代此前自造的 ImagePreviewOverlay（swiper 手搓），减少自造 UI 组件。
 *
 * @param urls    图片地址数组（全尺寸图，非缩略图）
 * @param current 当前索引（number）或当前图片 url（string）
 *
 * @example previewImages(group.items.map(i => i.url), index)
 */
export const previewImages = (urls: Array<string | undefined | null>, current: number | string = 0) => {
  const list = (urls || []).filter(Boolean) as string[]
  if (!list.length) return

  const currentIndex = typeof current === 'number'
    ? Math.max(0, Math.min(current, list.length - 1))
    : Math.max(0, list.indexOf(String(current)))

  uni.previewImage({
    urls: list,
    current: currentIndex,
    indicator: 'number',
    loop: true
  })
}
