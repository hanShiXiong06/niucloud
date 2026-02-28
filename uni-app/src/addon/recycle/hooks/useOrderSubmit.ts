import type { OrderForm, PlatformDeliveryForm } from '../types/order'
import { createOrder } from '../api/order'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useWechatFollow } from './useWechatFollow'

/**
 * 订单提交逻辑
 */
export function useOrderSubmit() {
  // 公众号关注引导
  const {
    showFollowPopup,
    wechatName,
    qrCode,
    checkAndShowFollow,
    dismissFollow
  } = useWechatFollow()
  /**
   * 验证订单提交前的条件
   */
  const validateBeforeSubmit = (params: {
    isAgreeRecycle: boolean
    currentTab: number
    usePlatformDelivery: boolean
    platformDeliveryForm: PlatformDeliveryForm
  }): { valid: boolean; message?: string } => {
    // 检查协议勾选
    if (!params.isAgreeRecycle) {
      return {
        valid: false,
        message: '请阅读并同意回收服务协议'
      }
    }

    // 如果是邮寄模式且使用平台快递，需要验证平台快递表单
    if (params.currentTab === 0 && params.usePlatformDelivery) {
      if (!params.platformDeliveryForm.sender_name) {
        return {
          valid: false,
          message: '请输入寄件人姓名'
        }
      }
      if (!params.platformDeliveryForm.sender_mobile) {
        return {
          valid: false,
          message: '请输入寄件人手机号'
        }
      }
      if (!params.platformDeliveryForm.province || !params.platformDeliveryForm.city || !params.platformDeliveryForm.district) {
        return {
          valid: false,
          message: '请选择所在地区'
        }
      }
      if (!params.platformDeliveryForm.detail_address) {
        return {
          valid: false,
          message: '请输入详细地址'
        }
      }
    }

    return { valid: true }
  }

  /**
   * 提交订单
   */
  const submitOrder = async (params: {
    form: OrderForm
    phoneList: any[]
    currentTab: number
    usePlatformDelivery: boolean
    platformDeliveryForm: PlatformDeliveryForm
    isAgreeRecycle: boolean
    formRef: any
    onSuccess?: () => void
  }) => {
    // 前置验证
    const validation = validateBeforeSubmit({
      isAgreeRecycle: params.isAgreeRecycle,
      currentTab: params.currentTab,
      usePlatformDelivery: params.usePlatformDelivery,
      platformDeliveryForm: params.platformDeliveryForm
    })

    if (!validation.valid) {
      uni.showToast({
        title: validation.message || '验证失败',
        icon: 'none'
      })
      return
    }

    // 请求订阅相关消息通知
    await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay')

    // 添加设备列表到表单数据
    params.form.devices = params.phoneList

    // 如果使用平台快递，将快递信息添加到订单数据中
    // 使用统一快递服务（use_express + express_config）
    const orderData: any = { ...params.form }
    if (params.currentTab === 0 && params.usePlatformDelivery) {
      orderData.use_express = 1
      orderData.express_config = {
        sender_name: params.platformDeliveryForm.sender_name,
        sender_mobile: params.platformDeliveryForm.sender_mobile,
        sender_province: params.platformDeliveryForm.province,
        sender_city: params.platformDeliveryForm.city,
        sender_district: params.platformDeliveryForm.district,
        sender_address: params.platformDeliveryForm.detail_address,
        pickup_time: params.platformDeliveryForm.pickup_time,
        weight: parseFloat(params.platformDeliveryForm.weight) || 1.0
      }
    }

    // 表单验证
    const valid = await params.formRef.validate()
    if (!valid) {
      uni.showToast({
        title: '请完善表单信息',
        icon: 'none'
      })
      return
    }

    try {
      uni.showLoading({ title: '提交中...' })

      // 创建回收订单（包含平台快递信息）
      const orderRes: any = await createOrder(orderData)

      if (orderRes.code !== 1) {
        uni.showToast({
          title: orderRes.message || '下单失败,请重试!',
          icon: 'none'
        })
        return
      }

      uni.showToast({
        title: '下单成功',
        icon: 'success'
      })

      // 调用成功回调
      if (params.onSuccess) {
        params.onSuccess()
      }

      // 检查是否需要弹出公众号关注引导
      console.log('[OrderSubmit] 下单成功，开始检查公众号关注状态...')
      const needFollowPopup = await checkAndShowFollow()
      console.log('[OrderSubmit] needFollowPopup:', needFollowPopup)
      if (needFollowPopup) {
        // 需要弹窗，由调用方处理弹窗关闭后的跳转
        // 不在此处自动跳转
      } else {
        // 不需要弹窗，直接跳转到订单列表
        setTimeout(() => {
          uni.navigateTo({
            url: '/addon/recycle/pages/order/list'
          })
        }, 1500)
      }

    } catch (error) {
      console.error('提交订单失败：', error)
      uni.showToast({
        title: error.msg || '下单失败,请重试!',
        icon: 'none'
      })
    } finally {
      uni.hideLoading()
    }
  }

  return {
    validateBeforeSubmit,
    submitOrder,
    // 公众号关注引导相关
    showFollowPopup,
    wechatName,
    qrCode,
    dismissFollow
  }
}
