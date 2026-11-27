import { ref } from 'vue'
import type { OrderForm } from '../types/order'
import useMemberStore from '@/stores/member'

/**
 * 订单表单管理
 */
export function useOrderForm(currentTab: any, isPlatformDelivery: () => boolean = () => false) {
  const formRef = ref<any>(null)

  const form = ref<OrderForm>({
    count: 1,
    express_no: '',
    customer_name: '',
    customer_phone: '',
    telphone: useMemberStore()?.info?.mobile || '',
    comment: '',
    delivery_type: (currentTab.value + 1), // 1-邮寄 2-自送
    devices: []
  })

  // 校验规则
  const rules = {
    express_no: {
      validator: (rule: any, value: string, callback: Function) => {
        const needsExpressNo = Number(currentTab.value) === 0 && !isPlatformDelivery()
        if (needsExpressNo && !String(value ?? '').trim()) {
          callback(new Error('请输入或扫描快递单号'))
        } else {
          callback()
        }
      },
      message: '请输入或扫描快递单号',
      trigger: ['blur', 'change']
    }
  }

  // 重置表单
  const resetForm = () => {
    if (formRef.value) {
      (formRef.value as any).resetFields()
    }
    form.value = {
      count: 1,
      express_no: '',
      customer_name: '',
      customer_phone: '',
      telphone: useMemberStore()?.info?.mobile || '',
      comment: '',
      delivery_type: (currentTab.value + 1),
      devices: []
    }
  }

  // 验证表单
  const validateForm = async (): Promise<boolean> => {
    if (!formRef.value) return false
    try {
      await (formRef.value as any).validate()
      return true
    } catch (error) {
      return false
    }
  }

  return {
    form,
    rules,
    formRef,
    resetForm,
    validateForm
  }
}
