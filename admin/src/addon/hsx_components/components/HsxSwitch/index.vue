<script lang="ts">export default { name: 'HsxSwitch', inheritAttrs: false }</script>
<script setup lang="ts">
import { computed, ref } from 'vue'
import type { MaybePromise } from '../../types'
const props = withDefaults(defineProps<{
    modelValue?: any
    activeValue?: any
    inactiveValue?: any
    activeText?: string
    inactiveText?: string
    loading?: boolean
    disabled?: boolean
    debounce?: number
    beforeChange?: (next: any, current: any) => MaybePromise<boolean>
    confirm?: (next: any, current: any) => MaybePromise<boolean>
    action?: (next: any, current: any) => MaybePromise<void | boolean>
}>(), { modelValue: false, activeValue: true, inactiveValue: false, activeText: '', inactiveText: '', loading: false, disabled: false, debounce: 300 })
const emit = defineEmits<{ (event: 'update:modelValue', value: any): void, (event: 'change', value: any): void, (event: 'error', error: unknown): void }>()
const innerLoading = ref(false)
const actualLoading = computed(() => props.loading || innerLoading.value)
let lastChangeAt = 0
async function handleUpdate(next: any) {
    const now = Date.now()
    if (props.disabled || actualLoading.value || now - lastChangeAt < props.debounce) return
    lastChangeAt = now
    try {
        if (props.beforeChange && !await props.beforeChange(next, props.modelValue)) return
        if (props.confirm && !await props.confirm(next, props.modelValue)) return
        if (props.action) {
            innerLoading.value = true
            if (await props.action(next, props.modelValue) === false) return
        }
        emit('update:modelValue', next)
        emit('change', next)
    } catch (error) { emit('error', error) } finally { innerLoading.value = false }
}
</script>
<template>
    <el-switch v-bind="$attrs" :model-value="modelValue" :active-value="activeValue" :inactive-value="inactiveValue" :active-text="activeText" :inactive-text="inactiveText" :loading="actualLoading" :disabled="disabled" @update:model-value="handleUpdate">
        <template v-for="(_, name) in $slots" #[name]="slotProps"><slot :name="name" v-bind="slotProps || {}" /></template>
    </el-switch>
</template>

