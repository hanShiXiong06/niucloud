import { ref } from 'vue'
import type { OrderForm } from '../types/order'
import useMemberStore from '@/stores/member'

/**
 * 订单表单管理
 */
export function useOrderForm(currentTab: any) {
  const formRef = ref(null)

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
      required: (rule: any, value: string, callback: Function) => {
        if (form.value.delivery_type === 1 && !value) {
          callback(new Error('快递单号不能为空'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
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
