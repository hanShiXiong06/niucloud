<script lang="ts">
export default { name: 'ProForm', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, inject, nextTick, reactive, ref, toRaw, watch } from 'vue'
import type { Component } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'
import HsxInput from '../HsxInput/index.vue'
import HsxCheckbox from '../HsxCheckbox/index.vue'
import HsxSelect from '../HsxSelect/index.vue'
import HsxCascader from '../HsxCascader/index.vue'
import HsxDatePicker from '../HsxDatePicker/index.vue'
import HsxUpload from '../HsxUpload/index.vue'
import HsxSwitch from '../HsxSwitch/index.vue'
import type { AnyRecord, ProFormField, SelectOption } from '../../types'
import { HSX_FORM_COMPONENTS, HSX_PERMISSION_CHECKER, type PermissionChecker } from '../../tokens'
import {
    deepClone,
    evaluateSchemaRule,
    getPathValue,
    isDeepEqual,
    optionDependencies,
    optionDependencySignature,
    resolveSchemaOptions,
    resolveSchemaValue,
    setPathValue
} from '../../utils'

const props = withDefaults(
    defineProps<{
        modelValue?: AnyRecord
        schema: ProFormField[]
        rules?: FormRules
        columns?: number
        gutter?: number
        labelWidth?: string | number
        disabled?: boolean
        readonly?: boolean
        inline?: boolean
        showMessage?: boolean
        validateOnRuleChange?: boolean
        components?: Record<string, Component>
        permissionChecker?: PermissionChecker
    }>(),
    {
        modelValue: () => ({}),
        rules: () => ({}),
        columns: 2,
        gutter: 20,
        labelWidth: 100,
        disabled: false,
        readonly: false,
        inline: false,
        showMessage: true,
        validateOnRuleChange: false
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: AnyRecord): void
    (event: 'change', prop: string, value: any, model: AnyRecord): void
    (event: 'options-load', prop: string, options: SelectOption[]): void
    (event: 'options-error', prop: string, error: unknown): void
}>()

const formRef = ref<FormInstance>()
const innerModel = reactive<AnyRecord>({})
const injectedPermissionChecker = inject(HSX_PERMISSION_CHECKER, undefined)
const injectedComponents = inject(HSX_FORM_COMPONENTS, {})
let syncingFromOutside = false

interface AsyncOptionState {
    options: SelectOption[]
    loading: boolean
    loaded: boolean
    error?: unknown
    signature?: string
    version: number
}

const asyncOptionStates = reactive<Record<string, AsyncOptionState>>({})

const mergedRules = computed<FormRules>(() => {
    const fieldRules = props.schema.reduce((result, field) => {
        if (!isRendered(field)) return result
        const rules = field.rules ? (Array.isArray(field.rules) ? [...field.rules] : [field.rules]) : []
        if (isRequired(field) && !rules.some((rule) => Boolean(rule.required))) {
            rules.unshift({
                required: true,
                message: field.requiredMessage || `${inputPlaceholder(field).replace('请输入', '').replace('请选择', '')}不能为空`,
                trigger: ['change', 'blur']
            })
        }
        if (rules.length) result[field.prop] = rules
        return result
    }, {} as FormRules)
    return { ...fieldRules, ...props.rules }
})

function createModel(source: AnyRecord = props.modelValue): AnyRecord {
    const result = deepClone(source || {})
    props.schema.forEach((field) => {
        if (getPathValue(result, field.prop) === undefined && field.defaultValue !== undefined) {
            setPathValue(result, field.prop, deepClone(field.defaultValue))
        }
    })
    return result
}

function replaceModel(source: AnyRecord) {
    const nextModel = createModel(source)
    if (isDeepEqual(innerModel, nextModel)) return
    syncingFromOutside = true
    Object.keys(innerModel).forEach((key) => delete innerModel[key])
    Object.assign(innerModel, nextModel)
    void nextTick(() => {
        syncingFromOutside = false
    })
}

watch(() => props.modelValue, replaceModel, { immediate: true, deep: true })
watch(
    innerModel,
    (value) => {
        if (!syncingFromOutside && !isDeepEqual(value, props.modelValue)) {
            emit('update:modelValue', deepClone(value))
        }
    },
    { deep: true }
)

function isVisible(field: ProFormField): boolean {
    return evaluateSchemaRule(field.visible, innerModel, true)
}

function hasPermission(field: ProFormField): boolean {
    if (!field.permission) return true
    const checker = props.permissionChecker || injectedPermissionChecker
    return checker ? checker(field.permission) : true
}

function isRendered(field: ProFormField): boolean {
    return hasPermission(field) && isVisible(field)
}

function isDisabled(field: ProFormField): boolean {
    if (props.disabled || props.readonly) return true
    return evaluateSchemaRule(field.disabled, innerModel, false)
}

function isRequired(field: ProFormField): boolean {
    return isRendered(field) && evaluateSchemaRule(field.required, innerModel, false)
}

function fieldSpan(field: ProFormField): number {
    if (props.inline) return 24
    return field.span || Math.max(1, Math.floor(24 / props.columns))
}

function fieldValue(field: ProFormField) {
    return getPathValue(innerModel, field.prop)
}

function fieldProps(field: ProFormField): AnyRecord {
    return resolveSchemaValue(field.props, innerModel, {})
}

function fieldTip(field: ProFormField): string {
    return resolveSchemaValue(field.tip, innerModel, '')
}

function componentForField(field: ProFormField): string | Component | undefined {
    if (!field.componentKey) return field.component
    const component = props.components?.[field.componentKey]
        || injectedComponents[field.componentKey]
        || field.component
        || field.componentKey
    return typeof component === 'object' ? toRaw(component) : component
}

function optionState(field: ProFormField): AsyncOptionState {
    if (!asyncOptionStates[field.prop]) {
        asyncOptionStates[field.prop] = { options: [], loading: false, loaded: false, version: 0 }
    }
    return asyncOptionStates[field.prop]
}

function fieldOptions(field: ProFormField): SelectOption[] {
    const state = asyncOptionStates[field.prop]
    return field.optionsLoader && state?.loaded ? state.options : resolveSchemaOptions(field, innerModel)
}

function fieldOptionsLoading(field: ProFormField): boolean {
    return Boolean(asyncOptionStates[field.prop]?.loading)
}

function fieldOptionsError(field: ProFormField): unknown {
    return asyncOptionStates[field.prop]?.error
}

async function loadFieldOptions(field: ProFormField, force = false): Promise<SelectOption[]> {
    if (!field.optionsLoader || !isRendered(field)) return fieldOptions(field)
    const state = optionState(field)
    const signature = optionDependencySignature(field, innerModel)
    if (!force && state.signature === signature) return state.options

    const version = state.version + 1
    state.version = version
    state.signature = signature
    state.loading = true
    state.error = undefined
    try {
        const options = await field.optionsLoader({
            model: deepClone(innerModel),
            field,
            dependencies: deepClone(optionDependencies(field, innerModel))
        })
        if (state.version !== version) return state.options
        state.options = deepClone(options || [])
        state.loaded = true
        emit('options-load', field.prop, deepClone(state.options))
        return state.options
    } catch (error) {
        if (state.version === version) {
            state.error = error
            emit('options-error', field.prop, error)
        }
        return state.options
    } finally {
        if (state.version === version) state.loading = false
    }
}

function reloadOptions(prop?: string) {
    const fields = prop ? props.schema.filter((field) => field.prop === prop) : props.schema
    return Promise.all(fields.filter((field) => field.optionsLoader).map((field) => loadFieldOptions(field, true)))
}

watch(
    () => [props.schema, innerModel] as const,
    () => {
        props.schema.forEach((field) => {
            if (field.optionsLoader) void loadFieldOptions(field)
        })
    },
    { immediate: true, deep: true, flush: 'post' }
)

function setFieldValue(field: ProFormField, value: any) {
    setPathValue(innerModel, field.prop, value)
    field.change?.(value, innerModel)
    emit('change', field.prop, value, deepClone(innerModel))
}

function inputPlaceholder(field: ProFormField) {
    if (field.placeholder) return field.placeholder
    return ['select', 'cascader', 'date', 'datetime', 'date-range', 'datetime-range', 'radio', 'checkbox'].includes(
        String(field.component)
    )
        ? `请选择${field.label || ''}`
        : `请输入${field.label || ''}`
}

function dateType(field: ProFormField): 'date' | 'datetime' | 'daterange' | 'datetimerange' {
    const map: Record<string, 'date' | 'datetime' | 'daterange' | 'datetimerange'> = {
        date: 'date',
        datetime: 'datetime',
        'date-range': 'daterange',
        'datetime-range': 'datetimerange'
    }
    return map[String(field.component)] || 'date'
}

async function validate(): Promise<boolean> {
    if (!formRef.value) return true
    return formRef.value.validate().then(() => true).catch(() => false)
}

function validateField(props?: string | string[]) {
    return formRef.value?.validateField(props)
}

async function resetFields(nextValue?: AnyRecord) {
    replaceModel(nextValue || {})
    await nextTick()
    formRef.value?.clearValidate()
}

function clearValidate(props?: string | string[]) {
    formRef.value?.clearValidate(props)
}

function getValues<T extends AnyRecord = AnyRecord>(): T {
    return deepClone(innerModel) as T
}

function setValues(values: AnyRecord) {
    Object.entries(deepClone(values)).forEach(([path, value]) => setPathValue(innerModel, path, value))
}

defineExpose({
    formRef,
    model: innerModel,
    validate,
    validateField,
    resetFields,
    clearValidate,
    getValues,
    setValues,
    reloadOptions
})
</script>

<template>
    <el-form
        ref="formRef"
        v-bind="$attrs"
        :model="innerModel"
        :rules="mergedRules"
        :label-width="labelWidth"
        :disabled="disabled"
        :inline="inline"
        :show-message="showMessage"
        :validate-on-rule-change="validateOnRuleChange"
    >
        <el-row :gutter="inline ? 0 : gutter">
            <template v-for="field in schema" :key="field.prop">
                <el-col v-if="isRendered(field)" :span="fieldSpan(field)">
                    <el-form-item
                        :prop="field.prop"
                        :label="field.label"
                        :label-width="field.labelWidth"
                        :required="isRequired(field)"
                    >
                        <slot
                            v-if="field.component === 'slot' || field.slot"
                            :name="field.slot || `field-${field.prop}`"
                            :field="field"
                            :model="innerModel"
                            :value="fieldValue(field)"
                            :set-value="(value: any) => setFieldValue(field, value)"
                        />

                        <HsxInput
                            v-else-if="(!field.component && !field.componentKey) || field.component === 'input' || field.component === 'textarea'"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :type="field.component === 'textarea' ? 'textarea' : (fieldProps(field).type || 'text')"
                            :placeholder="inputPlaceholder(field)"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <el-input-number
                            v-else-if="field.component === 'input-number'"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :placeholder="inputPlaceholder(field)"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <HsxSelect
                            v-else-if="field.component === 'select'"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :options="fieldOptions(field)"
                            :loading="fieldOptionsLoading(field)"
                            :placeholder="inputPlaceholder(field)"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <HsxCascader
                            v-else-if="field.component === 'cascader'"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :options="fieldOptions(field) as any"
                            :loading="fieldOptionsLoading(field)"
                            :placeholder="inputPlaceholder(field)"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <HsxDatePicker
                            v-else-if="['date', 'datetime', 'date-range', 'datetime-range'].includes(String(field.component))"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :type="dateType(field)"
                            :placeholder="inputPlaceholder(field)"
                            :start-placeholder="fieldProps(field).startPlaceholder || '开始时间'"
                            :end-placeholder="fieldProps(field).endPlaceholder || '结束时间'"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <HsxUpload
                            v-else-if="field.component === 'upload'"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field) || []"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <HsxSwitch
                            v-else-if="field.component === 'switch'"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <el-radio-group
                            v-else-if="field.component === 'radio'"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        >
                            <el-radio v-for="option in fieldOptions(field)" :key="String(option.value)" :label="option.value">
                                {{ option.label }}
                            </el-radio>
                        </el-radio-group>

                        <HsxCheckbox
                            v-else-if="field.component === 'checkbox'"
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :options="fieldOptions(field)"
                            :disabled="isDisabled(field)"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <component
                            :is="componentForField(field)"
                            v-else
                            v-bind="fieldProps(field)"
                            :model-value="fieldValue(field)"
                            :disabled="isDisabled(field)"
                            :field="field"
                            :model="innerModel"
                            @update:model-value="setFieldValue(field, $event)"
                        />

                        <span v-if="fieldTip(field)" class="hsx-pro-form__tip">{{ fieldTip(field) }}</span>
                        <span v-if="fieldOptionsError(field)" class="hsx-pro-form__tip hsx-pro-form__tip--error">
                            选项加载失败，可调用 reloadOptions('{{ field.prop }}') 重试
                        </span>
                    </el-form-item>
                </el-col>
            </template>
        </el-row>
    </el-form>
</template>

<style scoped>
.hsx-pro-form__tip {
    display: block;
    width: 100%;
    margin-top: 4px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 18px;
}

.hsx-pro-form__tip--error {
    color: var(--el-color-danger);
}

:deep(.el-select),
:deep(.el-date-editor.el-input),
:deep(.el-date-editor.el-input__wrapper),
:deep(.el-input-number) {
    width: 100%;
}
</style>
