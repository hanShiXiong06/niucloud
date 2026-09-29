import type { OrderForm, PlatformDeliveryForm } from '../types/order'
import { createOrder } from '../api/order'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useWechatFollow } from './useWechatFollow'
import { ref } from 'vue'
import { normalizePickup, validatePickupTime } from '../utils/pickup'

/**
 * 订单提交逻辑
 */
export function useOrderSubmit() {
  const submitting = ref(false)
  const submissionUncertain = ref(false)
  // 公众号关注引导
  const {
    showFollowPopup,
    wechatName,
    qrCode,
    title: followTitle,
    content: followContent,
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
    logisticsVehicleForm: Record<string, string>
  }): { valid: boolean; message?: string } => {
    // 检查协议勾选
    if (!params.isAgreeRecycle) {
      return {
        valid: false,
        message: '请阅读并同意回收服务协议'
      }
    }

    if (params.currentTab === 2) {
      const required: Array<[string, string]> = [
        ['logistics_name', '请填写物流名称'],
        ['logistics_vehicle_no', '请填写车牌号'],
        ['logistics_contact_name', '请填写联系人'],
        ['logistics_contact_mobile', '请填写联系电话'],
        ['logistics_pickup_address', '请填写取货地点']
      ]
      for (const [key, message] of required) {
        if (!String(params.logisticsVehicleForm[key] || '').trim()) return { valid: false, message }
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
      const timeError = validatePickupTime(params.platformDeliveryForm.pickup_time, params.platformDeliveryForm.pickup_time_required)
      if (timeError) return { valid: false, message: timeError }
    }

    return { valid: true }
  }

  /**
   * 提交订单
   */
  const executeSubmit = async (params: {
    form: OrderForm
    phoneList: any[]
    currentTab: number
    usePlatformDelivery: boolean
    platformDeliveryForm: PlatformDeliveryForm
    logisticsVehicleForm: Record<string, string>
    isAgreeRecycle: boolean
    formRef: any
    onSuccess?: () => void
  }) => {
    // 前置验证
    const validation = validateBeforeSubmit({
      isAgreeRecycle: params.isAgreeRecycle,
      currentTab: params.currentTab,
      usePlatformDelivery: params.usePlatformDelivery,
      platformDeliveryForm: params.platformDeliveryForm,
      logisticsVehicleForm: params.logisticsVehicleForm
    })

    if (!validation.valid) {
      uni.showToast({
        title: validation.message || '验证失败',
        icon: 'none'
      })
      return
    }

    // 添加设备列表到表单数据
    params.form.devices = params.phoneList

    // 如果使用平台快递，将快递信息添加到订单数据中
    // 使用统一快递服务（use_express + express_config）
    const orderData: any = { ...params.form }
    if (params.currentTab === 0 && params.usePlatformDelivery) {
      orderData.use_express = 1
      orderData.express_no = ''
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
    if (params.currentTab === 2) Object.assign(orderData, params.logisticsVehicleForm)

    // 表单验证
    const valid = await params.formRef.validate().catch(() => false)
    if (!valid) {
      uni.showToast({
        title: '请完善表单信息',
        icon: 'none'
      })
      return
    }

    // 取件通知单独申请，避免与其他消息合并超过平台一次订阅的数量上限。
    // 订阅未授权不影响创建订单，进度始终可在订单详情中查看。
    try {
      await useSubscribeMessage().request(params.currentTab === 0 && params.usePlatformDelivery
        ? 'hsx_recycle_pickup_update'
        : 'recycle_order_sign,recycle_order_agree,recycle_order_pay')
    } catch (_) { /* 用户可在详情中再次订阅 */ }

    let createdOrderId = 0
    let orderCreated = false
    try {
      uni.showLoading({ title: '提交中...' })

      // 创建回收订单（包含平台快递信息）
      const orderRes: any = await createOrder(orderData)

      if (orderRes.code !== 1) {
        uni.showToast({
          title: orderRes.msg || orderRes.message || '订单未提交，请检查信息',
          icon: 'none'
        })
        return
      }
      orderCreated = true
      createdOrderId = Number(orderRes.data?.id || orderRes.data?.order_id || 0)

      // 调用成功回调
      if (params.onSuccess) {
        params.onSuccess()
      }

      if (params.currentTab === 0 && params.usePlatformDelivery) {
        const pickup = normalizePickup(orderRes.data?.pickup || { state: 'unknown' })
        uni.hideLoading()
        await new Promise<void>(resolve => {
          uni.showModal({
            title: '回收订单已提交',
            content: `${pickup.title}。${pickup.message}`,
            showCancel: false,
            confirmText: '查看订单',
            complete: () => {
              uni.navigateTo({ url: createdOrderId > 0 ? `/addon/hsx_recycle/pages/order/detail?id=${createdOrderId}` : '/addon/hsx_recycle/pages/order/list' })
              resolve()
            }
          })
        })
        return
      }

      uni.showToast({ title: '回收订单已提交', icon: 'success' })

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
            url: '/addon/hsx_recycle/pages/order/list'
          })
        }, 1500)
      }

    } catch (error: any) {
      if (orderCreated) {
        // 关注引导等附加操作失败，不否定已经确认创建的回收订单。
        uni.navigateTo({ url: createdOrderId > 0 ? `/addon/hsx_recycle/pages/order/detail?id=${createdOrderId}` : '/addon/hsx_recycle/pages/order/list' })
        return
      }
      if ([0, 400].includes(Number(error?.code))) {
        uni.showToast({ title: error?.msg || '订单未提交，请检查填写信息', icon: 'none' })
        return
      }
      // 网络中断不代表服务端未建单，也不代表快递预约明确失败。
      submissionUncertain.value = true
      uni.hideLoading()
      uni.showModal({
        title: '提交结果待核实',
        content: '暂时无法确认订单提交结果，请先到订单列表核实或联系门店，不要重复下单或另叫快递。',
        confirmText: '查看订单',
        showCancel: false,
        success: () => uni.navigateTo({ url: '/addon/hsx_recycle/pages/order/list' })
      })
    } finally {
      uni.hideLoading()
    }
  }

  const submitOrder = async (params: Parameters<typeof executeSubmit>[0]) => {
    if (submitting.value) return
    if (submissionUncertain.value) {
      uni.showModal({ title: '请先核实上次提交结果', content: '请到订单列表或联系门店确认，避免重复创建回收订单和取件预约。', showCancel: false, confirmText: '查看订单', success: () => uni.navigateTo({ url: '/addon/hsx_recycle/pages/order/list' }) })
      return
    }
    submitting.value = true
    try { await executeSubmit(params) } finally { submitting.value = false }
  }

  return {
    validateBeforeSubmit,
    submitOrder,
    submitting,
    // 公众号关注引导相关
    showFollowPopup,
    wechatName,
    qrCode,
    followTitle,
    followContent,
    dismissFollow
  }
}
