<script lang="ts">
export default { name: 'HsxForm' }
</script>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import type { AnyRecord, MobileFormField, MobileOption, MobileResponsiveValue } from '../../types'
import { resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import {
    deepClone,
    getPathValue,
    isDeepEqual,
    mobileOptionDependencies,
    mobileOptionDependencySignature,
    resolveMobileOptions,
    resolveMobileSchemaValue,
    setPathValue
} from '../../utils'
import HsxCascader from '../HsxCascader/index.vue'
import HsxCheckbox from '../HsxCheckbox/index.vue'
import HsxSelect from '../HsxSelect/index.vue'
import HsxSwitch from '../HsxSwitch/index.vue'
import HsxUpload from '../HsxUpload/index.vue'

const props = withDefaults(
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
    (event: 'options-load', prop: string, options: MobileOption[]): void
    (event: 'options-error', prop: string, error: unknown): void
}>()

const formRef = ref<any>()
const innerModel = reactive<AnyRecord>({})
const layout = useAdaptiveContext()
const resolvedLabelPosition = computed<'left' | 'top'>(() => {
    if (props.labelPosition !== 'auto') return props.labelPosition
    return layout.isCompact.value ? 'top' : 'left'
})
const resolvedLabelWidth = computed(() => resolvedLabelPosition.value === 'top'
    ? '100%'
    : resolveAdaptiveValue(props.labelWidth, layout.widthClass.value, 96))
let syncing = false

interface AsyncOptionState {
    options: MobileOption[]
    loading: boolean
    loaded: boolean
    error?: unknown
    signature?: string
    version: number
}

const asyncOptionStates = reactive<Record<string, AsyncOptionState>>({})

const rules = computed(() =>
    props.schema.reduce((result, field) => {
        if (!isRendered(field)) return result
        const fieldRules = field.rules ? (Array.isArray(field.rules) ? [...field.rules] : [field.rules]) : []
        if (isRequired(field) && !fieldRules.some((rule) => Boolean(rule.required))) {
            fieldRules.unshift({
                required: true,
                message: field.requiredMessage || `${field.label}不能为空`,
                trigger: ['blur', 'change']
            })
        }
        if (fieldRules.length) result[field.prop] = fieldRules
        return result
    }, {} as AnyRecord)
)

function replaceModel(source: AnyRecord) {
    const nextModel = deepClone(source || {})
    props.schema.forEach((field) => {
        if (getPathValue(nextModel, field.prop) === undefined && field.defaultValue !== undefined) {
            setPathValue(nextModel, field.prop, deepClone(field.defaultValue))
        }
    })
    if (isDeepEqual(innerModel, nextModel)) return
    syncing = true
    Object.keys(innerModel).forEach((key) => delete innerModel[key])
    Object.assign(innerModel, nextModel)
    void nextTick(() => (syncing = false))
}

watch(() => props.modelValue, replaceModel, { immediate: true, deep: true })
watch(
    innerModel,
    (value) => {
        if (!syncing && !isDeepEqual(value, props.modelValue)) emit('update:modelValue', deepClone(value))
    },
    { deep: true }
)

function isVisible(field: MobileFormField) {
    return resolveMobileSchemaValue(field.visible, innerModel, true)
}

function hasPermission(field: MobileFormField) {
    if (!field.permission) return true
    return props.permissionChecker ? props.permissionChecker(field.permission) : true
}

function isRendered(field: MobileFormField) {
    return hasPermission(field) && isVisible(field)
}

function isDisabled(field: MobileFormField) {
    if (props.disabled) return true
    return resolveMobileSchemaValue(field.disabled, innerModel, false)
}

function isRequired(field: MobileFormField) {
    return isRendered(field) && resolveMobileSchemaValue(field.required, innerModel, false)
}

function fieldValue(field: MobileFormField) {
    return getPathValue(innerModel, field.prop)
}

function fieldProps(field: MobileFormField): AnyRecord {
    return resolveMobileSchemaValue(field.props, innerModel, {})
}

function fieldTip(field: MobileFormField): string {
    return resolveMobileSchemaValue(field.tip, innerModel, '')
}

function setValue(field: MobileFormField, value: any) {
    setPathValue(innerModel, field.prop, value)
    field.change?.(value, innerModel)
    emit('change', field.prop, value, deepClone(innerModel))
}

function optionState(field: MobileFormField): AsyncOptionState {
    if (!asyncOptionStates[field.prop]) {
        asyncOptionStates[field.prop] = { options: [], loading: false, loaded: false, version: 0 }
    }
    return asyncOptionStates[field.prop]
}

function fieldOptions(field: MobileFormField): MobileOption[] {
    const state = asyncOptionStates[field.prop]
    return field.optionsLoader && state?.loaded ? state.options : resolveMobileOptions(field, innerModel)
}

function fieldOptionsLoading(field: MobileFormField) {
    return Boolean(asyncOptionStates[field.prop]?.loading)
}

function fieldOptionsError(field: MobileFormField) {
    return asyncOptionStates[field.prop]?.error
}

async function loadFieldOptions(field: MobileFormField, force = false): Promise<MobileOption[]> {
    if (!field.optionsLoader || !isRendered(field)) return fieldOptions(field)
    const state = optionState(field)
    const signature = mobileOptionDependencySignature(field, innerModel)
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
            dependencies: deepClone(mobileOptionDependencies(field, innerModel))
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

async function validate(): Promise<boolean> {
    if (!formRef.value?.validate) return true
    return formRef.value.validate().then(() => true).catch(() => false)
}

async function resetFields(value: AnyRecord = {}) {
    replaceModel(value)
    await nextTick()
    formRef.value?.clearValidate?.()
}

function getValues() {
    return deepClone(innerModel)
}

defineExpose({ formRef, model: innerModel, validate, resetFields, getValues, reloadOptions })
</script>

<template>
    <u-form ref="formRef" class="hsx-form" :model="innerModel" :rules="rules" :label-position="resolvedLabelPosition">
        <template v-for="field in schema" :key="field.prop">
            <u-form-item
                v-if="isRendered(field)"
                :label="field.label"
                :prop="field.prop"
                :label-width="resolvedLabelWidth"
                :border-bottom="borderBottom"
                :required="isRequired(field)"
            >
                <view class="hsx-form__field" :class="`hsx-form__field--${field.component || 'input'}`">
                    <slot
                        v-if="field.component === 'slot' || field.slot"
                        :name="field.slot || `field-${field.prop}`"
                        :field="field"
                        :model="innerModel"
                        :value="fieldValue(field)"
                        :set-value="(value: any) => setValue(field, value)"
                    />

                    <u-input
                        v-else-if="!field.component || field.component === 'input' || field.component === 'number'"
                        :model-value="fieldValue(field)"
                        :type="field.component === 'number' ? 'number' : (fieldProps(field).type || 'text')"
                        :maxlength="fieldProps(field).maxlength"
                        :clearable="fieldProps(field).clearable"
                        :input-align="fieldProps(field).inputAlign"
                        :placeholder="field.placeholder || `请输入${field.label}`"
                        :disabled="isDisabled(field)"
                        border="none"
                        @update:model-value="setValue(field, $event)"
                    />

                    <u-textarea
                        v-else-if="field.component === 'textarea'"
                        :model-value="fieldValue(field)"
                        :maxlength="fieldProps(field).maxlength"
                        :count="fieldProps(field).count"
                        :placeholder="field.placeholder || `请输入${field.label}`"
                        :disabled="isDisabled(field)"
                        border="none"
                        @update:model-value="setValue(field, $event)"
                    />

                    <HsxSelect
                    v-else-if="field.component === 'select'"
                        :model-value="fieldValue(field)"
                        :options="fieldOptions(field)"
                        :title="fieldProps(field).title || field.label"
                        :placeholder="fieldOptionsLoading(field) ? '加载中...' : (field.placeholder || `请选择${field.label}`)"
                        :clearable="fieldProps(field).clearable !== false"
                        :searchable="fieldProps(field).searchable || fieldOptions(field).length > 10"
                        :z-index="fieldProps(field).zIndex || 10220"
                        :disabled="isDisabled(field) || fieldOptionsLoading(field)"
                        @update:model-value="setValue(field, $event)"
                    />

                    <picker
                        v-else-if="field.component === 'date'"
                        class="hsx-form__picker"
                        mode="date"
                        :disabled="isDisabled(field)"
                        :value="fieldValue(field)"
                        @change="setValue(field, $event.detail.value)"
                    >
                        <view class="hsx-form__picker-content">
                            <text class="hsx-form__picker-text" :class="{ 'hsx-form__placeholder': !fieldValue(field) }">
                                {{ fieldValue(field) || field.placeholder || `请选择${field.label}` }}
                            </text>
                            <view class="hsx-form__picker-icon"><u-icon name="arrow-right" color="#b5b8bf" size="16" /></view>
                        </view>
                    </picker>

                    <HsxCascader
                    v-else-if="field.component === 'cascader'"
                    :model-value="fieldValue(field)"
                    :options="fieldOptions(field) as any"
                    :fetch-options="fieldProps(field).fetchOptions"
                    :resolve-path="fieldProps(field).resolvePath"
                    :label-key="fieldProps(field).labelKey"
                    :value-key="fieldProps(field).valueKey"
                    :children-key="fieldProps(field).childrenKey"
                    :leaf-key="fieldProps(field).leafKey"
                    :title="fieldProps(field).title || field.label"
                    :placeholder="field.placeholder || `请选择${field.label}`"
                    :separator="fieldProps(field).separator"
                    :max-level="fieldProps(field).maxLevel"
                    :clearable="fieldProps(field).clearable !== false"
                    :show-all-levels="fieldProps(field).showAllLevels === true"
                    :searchable="fieldProps(field).searchable !== false"
                    :z-index="fieldProps(field).zIndex || 10240"
                    :disabled="isDisabled(field) || fieldOptionsLoading(field)"
                    @update:model-value="setValue(field, $event)"
                />

                    <HsxUpload
                    v-else-if="field.component === 'upload'"
                    v-bind="fieldProps(field)"
                    :model-value="fieldValue(field) || []"
                    :disabled="isDisabled(field)"
                    @update:model-value="setValue(field, $event)"
                />

                    <HsxSwitch
                    v-else-if="field.component === 'switch'"
                    v-bind="fieldProps(field)"
                    :model-value="fieldValue(field)"
                    :disabled="isDisabled(field)"
                    @update:model-value="setValue(field, $event)"
                />

                    <HsxCheckbox
                    v-else-if="field.component === 'checkbox'"
                    v-bind="fieldProps(field)"
                    :model-value="fieldValue(field) || []"
                    :options="fieldOptions(field)"
                    :disabled="isDisabled(field)"
                    @update:model-value="setValue(field, $event)"
                />

                    <u-radio-group
                    v-else-if="field.component === 'radio'"
                    :model-value="fieldValue(field)"
                    :placement="fieldProps(field).placement"
                    :disabled="isDisabled(field)"
                    @update:model-value="setValue(field, $event)"
                >
                    <u-radio
                        v-for="option in fieldOptions(field)"
                        :key="String(option.value)"
                        :name="option.value"
                        :label="option.label"
                        :disabled="option.disabled"
                    />
                    </u-radio-group>

                    <text v-if="fieldTip(field)" class="hsx-form__tip">{{ fieldTip(field) }}</text>
                    <text v-if="fieldOptionsError(field)" class="hsx-form__tip hsx-form__tip--error">选项加载失败，请重试</text>
                </view>
            </u-form-item>
        </template>
    </u-form>
</template>

<style scoped lang="scss">
.hsx-form__picker {
    display: block;
    width: 100%;
    min-width: 0;
}

.hsx-form__picker-content {
    display: flex;
    width: 100%;
    min-width: 0;
    min-height: var(--hsx-mobile-control-height, 40px);
    box-sizing: border-box;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    color: var(--hsx-mobile-text-primary, #303133);
    font-size: var(--hsx-mobile-font-control, 14px);
}

.hsx-form__picker-text {
    display: block;
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hsx-form__picker-icon {
    display: flex;
    flex: none;
    align-items: center;
    justify-content: center;
}

.hsx-form__field {
    display: flex;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    flex-direction: column;
    align-items: stretch;
}

.hsx-form__field--switch {
    align-items: flex-start;
}

.hsx-form :deep(.u-form-item__body__right),
.hsx-form :deep(.u-form-item__body__right__content),
.hsx-form :deep(.u-form-item__body__right__content__slot) {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.hsx-form :deep(.u-form-item__body__right__content__slot) {
    align-items: stretch;
}

.hsx-form :deep(.u-form-item__body__left),
.hsx-form :deep(.u-form-item__body__left__content),
.hsx-form :deep(.u-form-item__body__left__content__label) {
    color: var(--hsx-mobile-text-primary, #303133) !important;
}

.hsx-form :deep(.u-line) {
    border-color: var(--hsx-mobile-border, #e5e7eb) !important;
}

.hsx-form__placeholder {
    color: var(--hsx-mobile-text-secondary, #c0c4cc);
}

.hsx-form__tip {
    display: block;
    width: 100%;
    margin-top: 4px;
    color: var(--hsx-mobile-text-secondary, #909399);
    font-size: var(--hsx-mobile-font-caption, 12px);
}

.hsx-form__tip--error {
    color: var(--hsx-mobile-danger, #f56c6c);
}
</style>
