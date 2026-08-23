<script lang="ts">
export default { name: 'HsxSchemaForm', inheritAttrs: false }
</script>

<script setup lang="ts">
import { ref } from 'vue'
import HsxForm from '../HsxForm/index.vue'
import type { AnyRecord, MobileFormField, MobileResponsiveValue } from '../../types'

withDefaults(
    defineProps<{
        modelValue?: AnyRecord
        schema: MobileFormField[]
        labelPosition?: 'auto' | 'left' | 'top'
        labelWidth?: MobileResponsiveValue<string | number>
        disabled?: boolean
        borderBottom?: boolean
        permissionChecker?: (permission: string | string[]) => boolean
    }>(),
    {
        modelValue: () => ({}),
        labelPosition: 'auto',
        labelWidth: () => ({ compact: 0, medium: 96, expanded: 112 }),
        disabled: false,
        borderBottom: true
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: AnyRecord): void
    (event: 'change', prop: string, value: any, model: AnyRecord): void
    (event: 'options-load', prop: string, options: any[]): void
    (event: 'options-error', prop: string, error: unknown): void
}>()

const formRef = ref<any>()

function handleChange(prop: string, value: any, model: AnyRecord) {
    emit('change', prop, value, model)
}

function handleOptionsLoad(prop: string, options: any[]) {
    emit('options-load', prop, options)
}

function handleOptionsError(prop: string, error: unknown) {
    emit('options-error', prop, error)
}

defineExpose({
    formRef,
    validate: () => formRef.value?.validate?.(),
    resetFields: (value?: AnyRecord) => formRef.value?.resetFields?.(value),
    getValues: () => formRef.value?.getValues?.(),
    reloadOptions: (prop?: string) => formRef.value?.reloadOptions?.(prop)
})
</script>

<template>
    <HsxForm
        ref="formRef"
        v-bind="$attrs"
        :model-value="modelValue"
        :schema="schema"
        :label-position="labelPosition"
        :label-width="labelWidth"
        :disabled="disabled"
        :border-bottom="borderBottom"
        :permission-checker="permissionChecker"
        @update:model-value="emit('update:modelValue', $event)"
        @change="handleChange"
        @options-load="handleOptionsLoad"
        @options-error="handleOptionsError"
    />
</template>
