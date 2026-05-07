import { ref, computed } from 'vue'
import type { OrderDetailInfo, OrderDetailDevice } from '../types/order'
import { getOrderDetail, deviceConfirm, deviceAllConfirm, deviceConfirmHandle, getOrderSubmitConfig } from '../api/order'
import { getRecycleUserAddressInfo } from '../api/return_order'
import { getPaymentList } from '../api/payment'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'

/**
 * 订单详情管理
 * 处理订单详情数据加载、设备确认等操作
 */
export function useOrderDetail() {
  // 加载状态
  const loading = ref(true)

  // 订单详情
  const orderInfo = ref<OrderDetailInfo>({
    id: 0,
    order_no: '',
    status: 1,
    status_name: '',
    delivery_type: '2',
    delivery_type_name: '自送',
    create_at: '',
    devices: []
  })
  const submitConfig = ref<any>({
    allow_user_reject_sale: 1
  })

  // 是否为空状态（订单不存在）
  const isEmpty = computed(() => {
    return orderInfo.value.id === 0
  })

  // 是否没有设备（订单存在但设备列表为空）
  const hasNoDevices = computed(() => {
    return orderInfo.value.id > 0 && (!orderInfo.value.devices || orderInfo.value.devices.length === 0)
  })

  // 计算总价
  const totalPrice = computed(() => {
    return orderInfo.value.devices
      .reduce((total, device) => {
        return total + parseFloat(String(device.final_price || 0))
      }, 0)
      .toFixed(2)
  })

  const normalizePositiveNumber = (value: any, fallback = 1) => {
    const count = Number(value)
    return Number.isFinite(count) && count > 0 ? Math.min(5, Math.floor(count)) : fallback
  }

  const checkPayoutProfile = async () => {
    const configRes = await getOrderSubmitConfig()
    const profile = {
      enabled: configRes?.data?.profile?.enabled === 0 ? 0 : 1,
      payment_required: configRes?.data?.profile?.payment_required === 0 ? 0 : 1,
      payment_min_count: normalizePositiveNumber(configRes?.data?.profile?.payment_min_count, 1),
      id_card_required: configRes?.data?.profile?.id_card_required === 0 ? 0 : 1
    }

    if (!profile.enabled) return true

    const [paymentRes, addressRes] = await Promise.all([
      profile.payment_required ? getPaymentList() : Promise.resolve({ code: 1, data: [] }),
      getRecycleUserAddressInfo()
    ])
    const paymentList = Array.isArray(paymentRes?.data) ? paymentRes.data : []
    const addressInfo = addressRes?.data || {}
    const missing: string[] = []

    if (!addressInfo.name || !addressInfo.mobile) missing.push('个人资料')
    if (profile.id_card_required && (!addressInfo.id_card || !addressInfo.card_pic)) missing.push('身份证信息')
    if (profile.payment_required && paymentList.length < profile.payment_min_count) missing.push(`${profile.payment_min_count} 种收款方式`)

    if (!missing.length) return true

    uni.showModal({
      title: '提示',
      content: `请先完善${missing.join('、')}`,
      confirmText: '去添加',
      success: (res) => {
        if (res.confirm) {
          setTimeout(() => {
            uni.navigateTo({
              url: '/addon/recycle/pages/payment/index'
            })
          }, 100)
        }
      }
    })

    return false
  }

  const loadSubmitConfig = async () => {
    try {
      const res = await getOrderSubmitConfig()
      submitConfig.value = {
        ...submitConfig.value,
        ...(res?.data || {}),
        allow_user_reject_sale: res?.data?.allow_user_reject_sale === 0 ? 0 : 1
      }
    } catch (error) {
      submitConfig.value.allow_user_reject_sale = 1
    }
  }

  // 获取订单详情
  const loadOrderDetail = async (id: string | number) => {
    try {
      loading.value = true
      const numericId = typeof id === 'string' ? parseInt(id) : id
      const res = await getOrderDetail(numericId)

      if (res.code === 1) {
        orderInfo.value = res.data
      }
      await loadSubmitConfig()
    } catch (error) {
      console.error('获取订单详情失败:', error)
    } finally {
      loading.value = false
    }
  }

  const rejectDeviceSale = async (device: OrderDetailDevice): Promise<boolean> => {
    if (submitConfig.value.allow_user_reject_sale === 0) {
      uni.showToast({
        title: '请联系管理员处理',
        icon: 'none'
      })
      return false
    }
    if (orderInfo.value.status < 4 || ![3, 4, 7, 8].includes(Number(device.status))) {
      uni.showToast({
        title: '当前设备暂不能拒绝出售',
        icon: 'none'
      })
      return false
    }

    return new Promise((resolve) => {
      uni.showModal({
        title: '拒绝出售',
        content: '拒绝后该设备将进入退回处理，请确认是否继续。',
        confirmText: '确认拒绝',
        confirmColor: '#ef4444',
        success: async (modalRes) => {
          if (!modalRes.confirm) {
            resolve(false)
            return
          }
          try {
            loading.value = true
            const res = await deviceConfirmHandle(device.id, {
              is_sell: false,
              remark: '用户拒绝出售'
            })
            if (res.code === 1) {
              uni.showToast({
                title: '已拒绝出售',
                icon: 'success'
              })
              await loadOrderDetail(orderInfo.value.id)
              resolve(true)
              return
            }
            uni.showToast({
              title: res.msg || '操作失败',
              icon: 'none'
            })
            resolve(false)
          } catch (error) {
            console.error('拒绝出售失败:', error)
            uni.showToast({
              title: '操作失败',
              icon: 'none'
            })
            resolve(false)
          } finally {
            loading.value = false
          }
        }
      })
    })
  }

  // 确认单个设备
  const confirmDevice = async (device: OrderDetailDevice): Promise<boolean> => {
    // 检查订单状态
    if (orderInfo.value.status < 4) {
      uni.showToast({
        title: '请等待全部设备质检完成后再确认',
        icon: 'none'
      })
      return false
    }

    try {
      loading.value = true

      // 请求订阅消息
      await useSubscribeMessage().request('recycle_order_pay')

      if (!await checkPayoutProfile()) {
        loading.value = false
        return false
      }

      // 执行确认操作
      const res = await deviceConfirm(device.id)
      if (res.code === 1) {
        uni.showToast({
          title: '确认成功',
          icon: 'success'
        })
        await loadOrderDetail(orderInfo.value.id)
        return true
      } else {
        uni.showToast({
          title: res.msg || '操作失败',
          icon: 'none'
        })
        return false
      }
    } catch (error) {
      console.error('确认设备失败:', error)
      uni.showToast({
        title: '操作失败',
        icon: 'none'
      })
      return false
    } finally {
      loading.value = false
    }
  }

  // 批量确认设备
  const confirmDevices = async (deviceIds: number[]): Promise<boolean> => {
    if (deviceIds.length === 0) {
      uni.showToast({
        title: '请先选择设备',
        icon: 'none'
      })
      return false
    }

    try {
      loading.value = true

      // 请求订阅消息
      await useSubscribeMessage().request('recycle_order_pay')

      // 只确认已经完成质检/定价并等待用户处理的设备
      const devicesToConfirm = orderInfo.value.devices.filter(
        device => deviceIds.includes(device.id) && [3, 4, 7, 8].includes(Number(device.status))
      )

      if (devicesToConfirm.length === 0) {
        uni.showToast({
          title: '所选设备中没有需要确认的设备',
          icon: 'none'
        })
        return false
      }

      // 批量确认
      const deviceIdsToConfirm = devicesToConfirm.map(device => device.id)
      const res = await deviceAllConfirm(deviceIdsToConfirm)

      if (res.code === 1) {
        uni.showToast({
          title: '批量确认成功',
          icon: 'success'
        })
        await loadOrderDetail(orderInfo.value.id)
        return true
      } else {
        uni.showToast({
          title: res.msg || '操作失败',
          icon: 'none'
        })
        return false
      }
    } catch (error) {
      console.error('批量确认失败:', error)
      uni.showToast({
        title: '操作失败',
        icon: 'none'
      })
      return false
    } finally {
      loading.value = false
    }
  }

  // 议价（跳转客服）
  const negotiate = () => {
    uni.navigateTo({
      url: '/app/pages/member/contact'
    })
  }

  return {
    loading,
    orderInfo,
    submitConfig,
    isEmpty,
    hasNoDevices,
    totalPrice,
    loadOrderDetail,
    confirmDevice,
    confirmDevices,
    rejectDeviceSale,
    negotiate
  }
}
