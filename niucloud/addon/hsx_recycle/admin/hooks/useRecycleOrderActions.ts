import { ElLoading, ElMessage, ElMessageBox } from 'element-plus'
import type { Ref } from 'vue'
import { useRouter } from 'vue-router'
import { canOpenLocalPayment } from '@/addon/hsx_recycle/utils/payment-scope'
import {
  deleteRecycleOrder,
  getCapitalAccountOptions,
  getMerchantPayInfo,
  pushOrderNotify,
  updateRecycleOrder
} from '@/addon/hsx_recycle/api/recycle_order'

interface OrderActionItem {
  key: string
  value?: string
}

interface UseRecycleOrderActionsOptions {
  list: Ref<any[]>
  pagination: Ref<{ page: number }>
  getList: (page?: number) => Promise<void>
  currentOrderId: Ref<number | string>
  currentDevices: Ref<any[]>
  orderDialogVisible: Ref<boolean>
  checkDeviceLogVisible: Ref<boolean>
  priceDeviceLogVisible: Ref<boolean>
  paymentDialogVisible: Ref<boolean>
  paymentInfo: Ref<any[]>
  paymentMode: Ref<'order' | 'device'>
  selectedPayTypeIndex: Ref<number>
  orderDetailVisible: Ref<boolean>
  orderDetail: Ref<any>
}

const isDialogCanceled = (error: any) => {
  return (
    error === 'cancel' ||
    error === 'close' ||
    error?.action === 'cancel' ||
    error?.action === 'close'
  )
}

export function useRecycleOrderActions(options: UseRecycleOrderActionsOptions) {
  const router = useRouter()
  const {
    list,
    pagination,
    getList,
    currentOrderId,
    currentDevices,
    orderDialogVisible,
    checkDeviceLogVisible,
    priceDeviceLogVisible,
    paymentDialogVisible,
    paymentInfo,
    paymentMode,
    selectedPayTypeIndex,
    orderDetailVisible,
    orderDetail
  } = options

  const refreshCurrentPage = async () => {
    await getList(pagination.value.page)
  }

  const openOrderDeviceDialog = (row: any, targetVisible: Ref<boolean>) => {
    currentOrderId.value = row.id
    currentDevices.value = row.devices || []
    targetVisible.value = true
  }

  const buildPaymentOrderSummary = (row: any) => {
    const order = list.value.find((item) => item.id === row.id)
    const devices = order?.devices && Array.isArray(order.devices) ? order.devices : []

    const returnedDeviceCount = devices.filter((device) => device.status === 6).length
    const checkedDeviceCount = devices.filter((device) => device.status >= 4).length
    const confirmedDeviceCount = devices.filter((device) => device.status >= 5).length
    const successDeviceCount = devices.filter((device) => device.status == 5).length
    const finalPrice = devices
      .filter((device) => device.status == 5)
      .reduce((sum, device) => sum + +device.final_price, 0)

    return {
      order_id: order ? order.id : row.id,
      delivery_type: order ? order.delivery_type_text : '',
      device_count: devices.length,
      confirmed_count: confirmedDeviceCount,
      checked_count: checkedDeviceCount,
      success_count: successDeviceCount,
      returned_count: returnedDeviceCount,
      price_per_device: order ? order.price_per_device : 0,
      total_amount: order ? finalPrice : 0,
      customer_name: order?.member?.nickname || '',
      customer_mobile: order?.member?.mobile || '',
      delivery_type_name: order ? order.delivery_type_name : '',
      devices: devices.map((device) => ({
        id: device.id,
        model: device.model,
        imei: device.imei,
        user_sn: device.user_sn,
        final_price: device.final_price || 0,
        status: device.status,
        status_name: device.status_name || '',
        pay_status: Number(device.pay_status || 0),
        pay_status_name: device.pay_status_name || (Number(device.pay_status || 0) === 1 ? '已打款' : '未打款'),
        confirm_status: Number(device.confirm_status || 0),
        confirm_status_name: device.confirm_status_name || (Number(device.confirm_status || 0) === 1 ? '已确认' : '待客户确认'),
        can_pay: Boolean(device.can_pay),
        pay_disabled_reason: device.pay_disabled_reason || device.disabled_reason || '',
        pay_amount: device.pay_amount || 0,
        pay_time: device.pay_time || 0,
        pay_no: device.pay_no || ''
      }))
    }
  }

  const handlePushNotify = async (row: any) => {
    let loading: ReturnType<typeof ElLoading.service> | null = null
    try {
      const count = Number(row.flow_summary?.pending_confirm || 0)
      const content = count > 0
        ? `当前订单有 ${count} 台设备待客户确认，确定要推送订单处理提醒吗？`
        : '确定要推送订单处理提醒给用户吗？'
      await ElMessageBox.confirm(content, '推送通知', {
        confirmButtonText: '确定推送',
        cancelButtonText: '取消',
        type: 'info'
      })

      loading = ElLoading.service({
        lock: true,
        text: '正在发送通知...',
        background: 'rgba(0, 0, 0, 0.7)'
      })

      await pushOrderNotify(row.id)
      ElMessage.success('推送通知已发送')
    } catch (error: any) {
      if (!isDialogCanceled(error)) {
        console.error('推送通知失败：', error)
        ElMessage.error(error.message || '推送通知失败')
      }
    } finally {
      loading?.close()
    }
  }

  const actionHandlers: Record<
    string,
    (row: any, action: OrderActionItem) => Promise<void>
  > = {
    order_cancel: async (row: any) => {
      try {
        const { value: reason } = await ElMessageBox.prompt(
          '请输入取消原因',
          '取消订单',
          {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            inputPlaceholder: '请输入取消原因',
            inputValidator: (value) => {
              if (!value || !value.trim()) {
                return '取消原因不能为空'
              }
              return true
            }
          }
        )

        const cancelReason = reason.trim()
        await updateRecycleOrder(row.id, {
          action: 'order_cancel',
          cancel_reason: cancelReason,
          reason: cancelReason
        })

        ElMessage.success('订单已取消')
        await refreshCurrentPage()
      } catch (error) {
        if (!isDialogCanceled(error)) {
          throw error
        }
      }
    },
    order_delete: async (row: any) => {
      await ElMessageBox.confirm('确定要删除该订单吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
      try {
        await deleteRecycleOrder(row.id)
        ElMessage.success('订单已删除')
        await refreshCurrentPage()
      } catch {
        // 后端错误由请求拦截器统一提示，此处不再重复
      }
    },
    order_complete: async (row: any) => {
      await ElMessageBox.confirm('确定要完成该订单吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
      await updateRecycleOrder(row.id, { action: 'order_complete' })
      ElMessage.success('操作成功，订单已完成')
      await refreshCurrentPage()
    },
    order_sign: async (row: any) => {
      openOrderDeviceDialog(row, orderDialogVisible)
    },
    order_check: async (row: any) => {
      openOrderDeviceDialog(row, checkDeviceLogVisible)
    },
    order_price: async (row: any) => {
      openOrderDeviceDialog(row, priceDeviceLogVisible)
    },
    order_payment: async (row: any) => {
      let capability: any
      try {
        capability = await getCapitalAccountOptions(row.id)
      } catch (error) {
        ElMessage.error('付款归属查询失败，尚未执行付款。请刷新后重试，不能自动改为回收端付款。')
        return
      }
      const paymentOwner = capability.data?.payment_owner || 'unknown'
      if (paymentOwner === 'self_erp') {
        try {
          await ElMessageBox.confirm(
            capability.data?.message || '本订单由 ERP 结算，请到 ERP 应付款处理。切换联动方式后，实际已入 ERP 的设备仍由 ERP 处理。',
            '本订单由 ERP 结算',
            {
              confirmButtonText: '前往 ERP 应付款',
              cancelButtonText: '知道了',
              type: 'info'
            }
          )
        } catch (error) {
          if (isDialogCanceled(error)) return
          throw error
        }
        await router.push(capability.data?.payment_path || '/site/hsx_erp/payable')
        return
      }
      // 混合订单必须明确记录为按设备模式，不能用站点默认值替换历史订单模式。
      const orderPaymentMode = row.flow_mode || row.payment_mode || 'order'
      if (!canOpenLocalPayment(capability.data || {}, orderPaymentMode)) {
        await ElMessageBox.alert(
          `${capability.data?.message || (paymentOwner === 'mixed' ? '本订单包含不同结算归属的设备。' : '暂时无法确认本订单的付款归属。')} 请先到设备明细逐台核对归属；整单模式不能直接付款，也不会被自动改为按设备模式。本次未执行付款。`,
          paymentOwner === 'mixed' ? '请按设备核对结算归属' : '付款归属待核对',
          { confirmButtonText: '查看设备明细', type: 'warning' }
        )
        openOrderDeviceDialog(row, orderDialogVisible)
        return
      }
      currentOrderId.value = row.id
      paymentInfo.value = []
      paymentDialogVisible.value = true

      const res = await getMerchantPayInfo(row.member_id)
      const orderSummary = buildPaymentOrderSummary(row)
      if (res.data && Array.isArray(res.data) && res.data.length > 0) {
        const paymentInfoWithOrder = res.data.map((item) => ({
          ...item,
          payment_mode: row.flow_mode || row.payment_mode || paymentMode.value || 'order',
          device_payment_summary: row.device_payment_summary || null,
          order_summary: orderSummary
        }))
        paymentInfo.value = paymentInfoWithOrder
        selectedPayTypeIndex.value = paymentInfoWithOrder.length > 0 ? 0 : -1
        return
      }
      paymentInfo.value = [{
        pay_type: '自定义',
        account: '',
        qrcode_image: '',
        payment_mode: row.flow_mode || row.payment_mode || paymentMode.value || 'order',
        device_payment_summary: row.device_payment_summary || null,
        order_summary: orderSummary
      }]
      selectedPayTypeIndex.value = 0
    },
    order_detail: async (row: any) => {
      const order = list.value.find((item) => item.id === row.id)
      if (order) {
        orderDetail.value = order
        orderDetailVisible.value = true
        return
      }
      ElMessage.warning('未找到订单信息')
    },
    order_push_notify: async (row: any) => {
      await handlePushNotify(row)
    }
  }

  const handleDefaultAction = async (row: any, action: OrderActionItem) => {
    await updateRecycleOrder(row.id, { action: action.key })
    ElMessage.success('操作成功')
    await getList()
  }

  const handleAction = async (row: any, action: OrderActionItem) => {
    try {
      const handler = actionHandlers[action.key] || handleDefaultAction
      await handler(row, action)
    } catch (error: any) {
      console.error('操作失败：', error)
      ElMessage.error(error.message || '操作失败')
    }
  }

  return {
    handleAction
  }
}
