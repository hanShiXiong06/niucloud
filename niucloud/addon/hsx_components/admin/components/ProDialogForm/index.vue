<script lang="ts">
export default { name: 'ProDialogForm', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Component } from 'vue'
import HsxDialog from '../HsxDialog/index.vue'
import ProForm from '../ProForm/index.vue'
import type { AnyRecord, DialogMode, DialogModeText, ProFormField, SelectOption } from '../../types'
import type { PermissionChecker } from '../../tokens'
import { deepClone, isDeepEqual } from '../../utils'

const props = withDefaults(
    defineProps<{
        modelValue: boolean
        mode?: DialogMode
        title?: string
        modeText?: DialogModeText
        formData?: AnyRecord
        schema: ProFormField[]
        columns?: number
        width?: string | number
        fullscreen?: boolean
        submit?: (data: AnyRecord, mode: DialogMode) => Promise<any> | any
        beforeSubmit?: (data: AnyRecord, mode: DialogMode) => Promise<AnyRecord> | AnyRecord
        closeOnSuccess?: boolean
        components?: Record<string, Component>
        permissionChecker?: PermissionChecker
    }>(),
    {
        mode: 'create',
        title: '',
        modeText: () => ({ create: '新增', edit: '编辑', view: '查看' }),
        formData: () => ({}),
        columns: 2,
        width: '760px',
        fullscreen: false,
        closeOnSuccess: true
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void
    (event: 'update:formData', value: AnyRecord): void
    (event: 'submit', data: AnyRecord, mode: DialogMode): void
    (event: 'success', result: any, data: AnyRecord): void
    (event: 'error', error: unknown): void
    (event: 'options-load', prop: string, options: SelectOption[]): void
    (event: 'options-error', prop: string, error: unknown): void
}>()

const formRef = ref<InstanceType<typeof ProForm>>()
const innerData = ref<AnyRecord>(deepClone(props.formData))
const submitting = ref(false)

const dialogTitle = computed(() => props.title || props.modeText[props.mode] || '')
const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

watch(
    () => [props.modelValue, props.formData] as const,
    ([visible]) => {
        if (visible && !isDeepEqual(innerData.value, props.formData)) {
            innerData.value = deepClone(props.formData)
        }
    },
    { deep: true }
)
watch(
    innerData,
    (value) => {
        if (!isDeepEqual(value, props.formData)) emit('update:formData', deepClone(value))
    },
    { deep: true }
)

async function handleSubmit() {
    if (props.mode === 'view') {
        visible.value = false
        return
    }
    const valid = await formRef.value?.validate()
    if (valid === false) return

    submitting.value = true
    try {
        const originalData = formRef.value?.getValues() || deepClone(innerData.value)
        const data = props.beforeSubmit ? await props.beforeSubmit(originalData, props.mode) : originalData
        emit('submit', data, props.mode)
        const result = props.submit ? await props.submit(data, props.mode) : undefined
        emit('success', result, data)
        if (props.closeOnSuccess) visible.value = false
    } catch (error) {
        emit('error', error)
    } finally {
        submitting.value = false
    }
}

defineExpose({ formRef, submit: handleSubmit, loading: submitting })
</script>

<template>
    <HsxDialog
        v-model="visible"
        v-bind="$attrs"
        :title="dialogTitle"
        :width="width"
        :fullscreen="fullscreen"
        :show-footer="true"
        :confirm-text="mode === 'view' ? '关闭' : '保存'"
        :confirm-loading="submitting"
        @confirm="handleSubmit"
    >
        <ProForm
            ref="formRef"
            v-model="innerData"
            :schema="schema"
            :columns="columns"
            :readonly="mode === 'view'"
            :components="components"
            :permission-checker="permissionChecker"
            @options-load="(prop, options) => emit('options-load', prop, options)"
            @options-error="(prop, error) => emit('options-error', prop, error)"
        >
            <template v-for="(_, name) in $slots" #[name]="slotProps">
                <slot :name="name" v-bind="slotProps || {}" />
            </template>
        </ProForm>
        <template #footer="slotProps">
            <slot name="footer" v-bind="slotProps">
                <el-button data-testid="pro-dialog-cancel" @click="visible = false">取消</el-button>
                <el-button data-testid="pro-dialog-submit" type="primary" :loading="submitting" @click="handleSubmit">
                    {{ mode === 'view' ? '关闭' : '保存' }}
                </el-button>
            </slot>
        </template>
    </HsxDialog>
</template>
