import { ref } from 'vue'
import type { Ref } from 'vue'
import { ElLoading, ElMessage, ElMessageBox } from 'element-plus'
import { useSubmit, confirmDanger } from '@/utils/useSubmit'
import {
  batchRecycleDevices as apiBatchRecycleDevices,
  batchReturnDevices as apiBatchReturnDevices,
  confirmPrice,
  getDevice,
  updateDevice
} from '@/addon/hsx_recycle/api/recycle_order'

interface UseRecycleDeviceActionsOptions {
  list: Ref<any[]>
  pagination: Ref<{ page: number }>
  getList: (page?: number) => Promise<void>
  checkDeviceLogVisible: Ref<boolean>
  priceDeviceLogVisible: Ref<boolean>
}

const isDialogCanceled = (error: any) => {
  return (
    error === 'cancel' ||
    error === 'close' ||
    error?.action === 'cancel' ||
    error?.action === 'close'
  )
}

export function useRecycleDeviceActions(options: UseRecycleDeviceActionsOptions) {
  const { list, pagination, getList, checkDeviceLogVisible, priceDeviceLogVisible } = options

  const mapDeviceToCheckForm = (data: any) => {
    return {
      id: data?.id,
      imei: data?.imei || '',
      model: data?.model || '',
      initial_price: data?.initial_price,
      check_result: data?.check_result || '',
      check_result_seller: data?.check_result_seller || '',
      check_result_buyer: data?.check_result_buyer || '',
      check_meta: data?.check_meta ?? data?.info?.check_meta,
      check_template_id: data?.check_template_id || data?.info?.check_meta?.template_id || '',
      check_images: data?.check_images_seller || data?.check_images || '',
      check_images_seller: data?.check_images_seller || data?.check_images || '',
      check_images_buyer: data?.check_images_buyer || '',
      final_price: data?.final_price || '',
      sell_price: data?.sell_price || '',
      remark: data?.remark || '',
      status: data?.status,
      check_status: data?.check_status,
      info: data?.info,
      system_version: data?.system_version || '',
      warranty_info: data?.warranty_info || '',
      capacity: data?.capacity || '',
      color: data?.color || '',
      refurbishment_required: Number(data?.refurbishment_required || 0),
      refurbishment_assignee_uid: Number(data?.refurbishment_assignee_uid || 0),
      refurbishment_assignee_name: data?.refurbishment_assignee_name || '',
      refurbishment_reason: data?.refurbishment_reason || '',
      refurbishment_items: data?.refurbishment_items || [],
      refurbishment_estimated_cost: data?.refurbishment_estimated_cost || 0
    }
  }

  const buildCheckSubmitPayload = (formData: any) => {
    const sellerCheckImages = formData.check_images_seller || formData.check_images || ''

    return {
      check_result: formData.check_result || '',
      check_result_seller: formData.check_result_seller || '',
      check_result_buyer: formData.check_result_buyer || '',
      check_images: sellerCheckImages,
      check_images_seller: sellerCheckImages,
      check_images_buyer: formData.check_images_buyer || '',
      remark: formData.remark,
      final_price: formData.final_price,
      sell_price: formData.sell_price,
      imei: formData.imei,
      check_template_id: formData.check_template_id || formData.info?.check_meta?.template_id || 0,
      info: formData.info,
      model: formData.model,
      system_version: formData.system_version || '',
      warranty_info: formData.warranty_info || '',
      capacity: formData.capacity || '',
      color: formData.color || ''
    }
  }

  const checkDeviceLogForm = ref({
    id: 0,
    imei: '',
    model: '',
    initial_price: '',
    check_result: '',
    check_result_seller: '',
    check_result_buyer: '',
    check_meta: undefined as any,
    check_template_id: '',
    check_images: '',
    check_images_seller: '',
    check_images_buyer: '',
    remark: '',
    status: 0,
    final_price: '',
    sell_price: '',
    check_status: 0,
    info: undefined as any,
    system_version: '',
    warranty_info: '',
    capacity: '',
    color: '',
    refurbishment_required: 0,
    refurbishment_assignee_uid: 0,
    refurbishment_assignee_name: '',
    refurbishment_reason: '',
    refurbishment_items: [] as any,
    refurbishment_estimated_cost: 0
  })

  const selectedDevices = ref<Record<string | number, any[]>>({})

  // 关键写操作的提交守卫：防重复提交 + 加载态。loading 暴露给弹窗按钮绑定 :loading。
  const checkSubmit = useSubmit()
  const priceSubmit = useSubmit()
  const recycleSubmit = useSubmit()

  const checkDevice = async (row: any) => {
    const orderId = row.order_id
    const order = list.value.find((item) => item.id === orderId)

    if (order && order.status === 1) {
      ElMessage.warning('请先签收订单后再进行质检')
      return
    }

    checkDeviceLogVisible.value = true
    const res = await getDevice(row.id)
    if (res.code === 1) {
      checkDeviceLogForm.value = mapDeviceToCheckForm(res.data)
    }
  }

  const priceDevice = async (row: any) => {
    try {
      const res = await getDevice(row.id)
      if (res.code === 1) {
        checkDeviceLogForm.value = {
          ...mapDeviceToCheckForm(res.data),
          before_price: res.data.before_price || ''
        } as any
        priceDeviceLogVisible.value = true
      }
    } catch (error) {
      console.error('获取设备信息失败:', error)
      ElMessage.error('获取设备信息失败')
    }
  }

  const submitDeviceCheck = (formData: any) => {
    if (!formData.check_result_seller) {
      ElMessage.warning('请填写质检结果')
      return
    }
    return checkSubmit.run(async () => {
      await updateDevice(formData.id, {
        ...buildCheckSubmitPayload(formData),
        check_status: 1,
        action: 'check',
      })
      checkDeviceLogVisible.value = false
      await getList(pagination.value.page)
    }, { success: '质检信息提交成功' })
  }

  const handleCheckDeviceSaveDraft = async (formData: any) => {
    const loading = ElLoading.service({
      lock: true,
      text: '正在暂存...',
      background: 'rgba(0, 0, 0, 0.7)'
    })

    try {
      await updateDevice(formData.id, {
        ...buildCheckSubmitPayload(formData),
        action: 'save_draft'
      })

      ElMessage.success('质检信息已暂存')
      checkDeviceLogVisible.value = false
      await getList(pagination.value.page)
    } catch (error: any) {
      console.error('暂存质检信息失败：', error)
      ElMessage.error(error.response?.data?.message || '暂存失败，请重试')
    } finally {
      loading.close()
    }
  }

  const submitDevicePrice = (formData: any) => {
    if (!formData || !formData.final_price || formData.final_price <= 0) {
      ElMessage.warning('请输入有效的价格')
      return
    }
    return priceSubmit.run(async () => {
      await confirmPrice(formData.id, formData)
      priceDeviceLogVisible.value = false
      await getList(pagination.value.page)
    }, { success: '定价信息提交成功' })
  }

  const handleDeviceSelectionChange = (val: any[], orderId: string | number) => {
    selectedDevices.value[orderId] = val
  }

  const getSelectedDeviceIds = (orderId: string | number) => {
    const selected = selectedDevices.value[orderId]
    if (!Array.isArray(selected)) return []
    return selected.map((device: any) => device.id).filter(Boolean)
  }

  const batchRecycleDevice = async (deviceId: number | string) => {
    if (!deviceId) {
      ElMessage.warning('请选择设备')
      return
    }
    // 确认回收会推进设备进入后续入库流程，属状态变更，先二次确认
    const ok = await confirmDanger('确认将该设备标记为「已回收」？确认后将进入后续入库流程。', {
      title: '确认回收',
      confirmText: '确认回收'
    })
    if (!ok) return

    return recycleSubmit.run(async () => {
      await apiBatchRecycleDevices({ ids: deviceId + '', remark: '管理员确认' })
      await getList(pagination.value.page)
    }, { success: '已确认回收' })
  }

  const batchReturnDevice = async (deviceId: number | string) => {
    if (!deviceId) {
      ElMessage.warning('请选择设备')
      return false
    }

    let loading: ReturnType<typeof ElLoading.service> | null = null
    try {
      const { value: remark } = await ElMessageBox.prompt(
        '确定要拒绝回收该设备吗？将创建退货订单处理该设备。\n请输入拒绝回收原因：',
        '拒绝回收确认',
        {
          confirmButtonText: '确定拒绝',
          cancelButtonText: '取消',
          type: 'warning',
          inputPlaceholder: '请输入拒绝回收原因'
        }
      )

      loading = ElLoading.service({
        lock: true,
        text: '正在处理...',
        background: 'rgba(0, 0, 0, 0.7)'
      })

      await apiBatchReturnDevices({
        ids: deviceId + '',
        remark: remark || '商户拒绝回收'
      })

      ElMessage.success('拒绝成功，已创建退货订单')
      await getList(pagination.value.page)
      return true
    } catch (error) {
      if (isDialogCanceled(error)) return false
      console.error('拒绝失败:', error)
      return false
    } finally {
      loading?.close()
    }
  }

  const batchRecycleDevices = async (orderId: string | number) => {
    const selectedDeviceIds = getSelectedDeviceIds(orderId)
    if (selectedDeviceIds.length === 0) {
      ElMessage.warning('请选择设备')
      return
    }

    let remark = ''
    try {
      const res = await ElMessageBox.prompt(
        `确认将已选 ${selectedDeviceIds.length} 台设备标记为「已回收」？确认后将进入后续入库流程。`,
        '批量确认回收',
        {
          confirmButtonText: '确认回收',
          cancelButtonText: '取消',
          type: 'warning',
          inputPlaceholder: '可填写操作备注（可选）'
        }
      )
      remark = res.value || ''
    } catch (error) {
      if (isDialogCanceled(error)) return // 用户取消
      throw error
    }

    // 提交守卫：防止连点造成重复确认
    return recycleSubmit.run(async () => {
      await apiBatchRecycleDevices({
        ids: selectedDeviceIds.join(','),
        remark
      })
      await getList(pagination.value.page)
    }, { success: '批量确认成功' })
  }

  const batchReturnDevices = async (orderId: string | number) => {
    let loading: ReturnType<typeof ElLoading.service> | null = null
    try {
      const selectedDeviceIds = getSelectedDeviceIds(orderId)
      if (selectedDeviceIds.length === 0) {
        ElMessage.warning('请选择设备')
        return
      }

      const { value: remark } = await ElMessageBox.prompt(
        `确定要拒绝回收已选择的 ${selectedDeviceIds.length} 台设备吗？将创建退货订单处理这些设备。\n请输入拒绝回收原因：`,
        '批量拒绝回收确认',
        {
          confirmButtonText: '确定拒绝',
          cancelButtonText: '取消',
          type: 'warning',
          inputPlaceholder: '请输入拒绝回收原因'
        }
      )

      loading = ElLoading.service({
        lock: true,
        text: '正在处理...',
        background: 'rgba(0, 0, 0, 0.7)'
      })

      await apiBatchReturnDevices({
        ids: selectedDeviceIds.join(','),
        remark: remark || '商户批量拒绝回收'
      })

      ElMessage.success('批量拒绝成功，已创建退货订单')
      await getList()
    } catch (error: any) {
      if (isDialogCanceled(error)) return
      console.error('批量拒绝失败:', error)
      ElMessage.error('批量拒绝失败: ' + (error.message || '未知错误'))
    } finally {
      loading?.close()
    }
  }

  return {
    checkDeviceLogForm,
    selectedDevices,
    checkDevice,
    priceDevice,
    submitDeviceCheck,
    handleCheckDeviceSaveDraft,
    submitDevicePrice,
    handleDeviceSelectionChange,
    batchRecycleDevice,
    batchReturnDevice,
    batchRecycleDevices,
    batchReturnDevices,
    // 提交进行中状态，供弹窗确认按钮绑定 :loading，实现可见的防重复点击
    checkSubmitting: checkSubmit.loading,
    priceSubmitting: priceSubmit.loading
  }
}
