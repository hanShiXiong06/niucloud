import { ref } from 'vue'
import { checkWechatFollow } from '../api/order'

/**
 * 公众号关注引导 Hook
 * 用于下单成功后判断是否需要弹出公众号关注引导弹窗
 */
export function useWechatFollow() {
  const showFollowPopup = ref(false)
  const wechatName = ref('')
  const qrCode = ref('')

  // 本地缓存 key 和过期天数
  const CACHE_KEY = 'recycle_follow_wechat_dismiss'
  const CACHE_DAYS = 7

  /**
   * 检查是否需要弹出关注公众号弹窗
   * 1. 先检查本地缓存（7天内关闭过则不弹）
   * 2. 调接口判断用户是否已关注
   * 3. 未关注且配置了二维码 → 弹窗
   */
  const checkAndShowFollow = async (): Promise<boolean> => {
    try {
      // 1. 先检查本地缓存（7天内关闭过则不弹）
      const cache = uni.getStorageSync(CACHE_KEY)
      if (cache && Date.now() - cache.timestamp < CACHE_DAYS * 86400000) {
        console.log('[WechatFollow] 7天内已关闭过，跳过弹窗')
        return false
      }

      // 2. 调接口判断
      console.log('[WechatFollow] 开始调用接口检查关注状态...')
      const res: any = await checkWechatFollow()
      console.log('[WechatFollow] 接口返回:', JSON.stringify(res))

      if (res.code === 1 && res.data) {
        // 已关注，不弹
        if (res.data.is_follow === 1) {
          console.log('[WechatFollow] 用户已关注，不弹窗')
          return false
        }
        // 没配置二维码，不弹
        if (!res.data.qr_code) {
          console.log('[WechatFollow] 未配置公众号二维码，不弹窗')
          return false
        }

        wechatName.value = res.data.wechat_name || ''
        qrCode.value = res.data.qr_code || ''
        showFollowPopup.value = true
        console.log('[WechatFollow] 显示关注弹窗')
        return true
      }

      console.log('[WechatFollow] 接口返回异常，code:', res.code)
      return false
    } catch (error) {
      console.error('[WechatFollow] 检查公众号关注状态失败：', error)
      return false
    }
  }

  /**
   * 关闭弹窗并记录缓存（7天内不再弹出）
   */
  const dismissFollow = () => {
    showFollowPopup.value = false
    uni.setStorageSync(CACHE_KEY, { timestamp: Date.now() })
  }

  return {
    showFollowPopup,
    wechatName,
    qrCode,
    checkAndShowFollow,
    dismissFollow
  }
}
