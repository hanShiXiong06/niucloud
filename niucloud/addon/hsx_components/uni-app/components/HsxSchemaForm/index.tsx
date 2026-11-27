import { defineComponent, h, ref, type PropType } from 'vue'
import HsxForm from '../HsxForm/index.vue'
import type { AnyRecord, MobileFormField, MobileResponsiveValue } from '../../types'

/**
 * HsxForm 的 TSX 入口。表单能力仍由同一个 Schema 内核实现，避免 Template/TSX 两套逻辑分叉。
 */
export default defineComponent({
    name: 'HsxSchemaForm',
    inheritAttrs: false,
    props: {
        modelValue: { type: Object as PropType<AnyRecord>, default: () => ({}) },
        schema: { type: Array as PropType<MobileFormField[]>, required: true },
        labelPosition: { type: String as PropType<'auto' | 'left' | 'top'>, default: 'auto' },
        labelWidth: {
            type: [String, Number, Object] as PropType<MobileResponsiveValue<string | number>>,
            default: () => ({ compact: 0, medium: 96, expanded: 112 })
        },
        disabled: { type: Boolean, default: false },
        borderBottom: { type: Boolean, default: true },
        permissionChecker: Function as PropType<(permission: string | string[]) => boolean>
    },
    emits: ['update:modelValue', 'change', 'options-load', 'options-error'],
    setup(props, { attrs, emit, slots, expose }) {
        const formRef = ref<any>()
        expose({
            formRef,
            validate: () => formRef.value?.validate?.(),
            resetFields: (value?: AnyRecord) => formRef.value?.resetFields?.(value),
            getValues: () => formRef.value?.getValues?.(),
            reloadOptions: (prop?: string) => formRef.value?.reloadOptions?.(prop)
        })

        return () => h(HsxForm as any, {
            ...attrs,
            ref: formRef,
            modelValue: props.modelValue,
            schema: props.schema,
            labelPosition: props.labelPosition,
            labelWidth: props.labelWidth,
            disabled: props.disabled,
            borderBottom: props.borderBottom,
            permissionChecker: props.permissionChecker,
            'onUpdate:modelValue': (value: AnyRecord) => emit('update:modelValue', value),
            onChange: (...args: any[]) => emit('change', ...args),
            onOptionsLoad: (...args: any[]) => emit('options-load', ...args),
            onOptionsError: (...args: any[]) => emit('options-error', ...args)
        }, slots)
    }
})
