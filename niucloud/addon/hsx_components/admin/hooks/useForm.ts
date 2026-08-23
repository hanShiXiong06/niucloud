import { nextTick, reactive, ref } from 'vue'
import type { FormInstance } from 'element-plus'
import type { AnyRecord } from '../types'
import { deepClone } from '../utils'

export function useForm<T extends AnyRecord>(initialValue: T | (() => T)) {
    const createInitialValue = () => deepClone(typeof initialValue === 'function' ? initialValue() : initialValue)
    const formRef = ref<FormInstance>()
    const model = reactive<T>(createInitialValue())

    const setValues = (values: Partial<T>) => Object.assign(model, deepClone(values))

    const reset = async (values?: Partial<T>) => {
        Object.keys(model).forEach((key) => delete model[key])
        Object.assign(model, createInitialValue(), deepClone(values || {}))
        await nextTick()
        formRef.value?.clearValidate()
    }

    const validate = async () => {
        if (!formRef.value) return true
        return formRef.value.validate().catch(() => false)
    }

    return { formRef, model, setValues, reset, validate }
}
