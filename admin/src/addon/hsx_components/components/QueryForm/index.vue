<script lang="ts">
export default { name: 'QueryForm', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import HsxButton from '../HsxButton/index.vue'
import ProForm from '../ProForm/index.vue'
import type { AnyRecord, ProFormField } from '../../types'
import { deepClone, isDeepEqual, removeEmptyValues } from '../../utils'

const props = withDefaults(
    defineProps<{
        modelValue?: AnyRecord
        schema: ProFormField[]
        initialValues?: AnyRecord
        columns?: number
        gutter?: number
        labelWidth?: string | number
        collapseCount?: number
        defaultCollapsed?: boolean
        loading?: boolean
        disabled?: boolean
        showReset?: boolean
        searchText?: string
        resetText?: string
        validateBeforeSearch?: boolean
    }>(),
    {
        modelValue: () => ({}),
        initialValues: () => ({}),
        columns: 4,
        gutter: 16,
        labelWidth: 90,
        collapseCount: 4,
        defaultCollapsed: true,
        loading: false,
        disabled: false,
        showReset: true,
        searchText: '查询',
        resetText: '重置',
        validateBeforeSearch: false
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: AnyRecord): void
    (event: 'search', value: AnyRecord): void
    (event: 'reset', value: AnyRecord): void
    (event: 'collapse-change', collapsed: boolean): void
}>()

const formRef = ref<InstanceType<typeof ProForm>>()
const innerModel = ref<AnyRecord>(deepClone(props.modelValue))
const collapsed = ref(props.defaultCollapsed)
let syncing = false

const collapsible = computed(() => props.schema.length > props.collapseCount)
const visibleSchema = computed(() =>
    collapsed.value && collapsible.value ? props.schema.slice(0, props.collapseCount) : props.schema
)

watch(
    () => props.modelValue,
    (value) => {
        if (isDeepEqual(value, innerModel.value)) return
        syncing = true
        innerModel.value = deepClone(value || {})
        void nextTick(() => (syncing = false))
    },
    { deep: true }
)

watch(
    innerModel,
    (value) => {
        if (!syncing && !isDeepEqual(value, props.modelValue)) emit('update:modelValue', deepClone(value))
    },
    { deep: true }
)

async function search() {
    if (props.validateBeforeSearch && !(await formRef.value?.validate())) return
    const value = removeEmptyValues(deepClone(innerModel.value))
    emit('search', value)
    return value
}

async function reset() {
    innerModel.value = deepClone(props.initialValues)
    await nextTick()
    await formRef.value?.resetFields(innerModel.value)
    const value = deepClone(innerModel.value)
    emit('reset', value)
    emit('search', removeEmptyValues(value))
    return value
}

function toggleCollapsed() {
    collapsed.value = !collapsed.value
    emit('collapse-change', collapsed.value)
}

function handleEnter(event: KeyboardEvent) {
    if ((event.target as HTMLElement)?.tagName === 'TEXTAREA') return
    void search()
}

defineExpose({ formRef, model: innerModel, collapsed, search, reset, toggleCollapsed })
</script>

<template>
    <section class="hsx-query-form" @keyup.enter="handleEnter">
        <ProForm
            ref="formRef"
            v-model="innerModel"
            v-bind="$attrs"
            :schema="visibleSchema"
            :columns="columns"
            :gutter="gutter"
            :label-width="labelWidth"
            :disabled="disabled"
        >
            <template v-for="(_, name) in $slots" #[name]="slotProps">
                <slot :name="name" v-bind="slotProps || {}" />
            </template>
        </ProForm>

        <div class="hsx-query-form__actions">
            <HsxButton type="primary" :loading="loading" @click="search">{{ searchText }}</HsxButton>
            <el-button v-if="showReset" :disabled="loading" @click="reset">{{ resetText }}</el-button>
            <el-button v-if="collapsible" link type="primary" @click="toggleCollapsed">
                {{ collapsed ? '展开' : '收起' }}
                <el-icon class="hsx-query-form__arrow" :class="{ 'is-expanded': !collapsed }">
                    <ArrowDown />
                </el-icon>
            </el-button>
            <slot name="actions" :model="innerModel" :search="search" :reset="reset" />
        </div>
    </section>
</template>

<style scoped lang="scss">
.hsx-query-form {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.hsx-query-form__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.hsx-query-form__arrow {
    margin-left: 4px;
    transition: transform 0.2s ease;
}

.hsx-query-form__arrow.is-expanded {
    transform: rotate(180deg);
}
</style>
