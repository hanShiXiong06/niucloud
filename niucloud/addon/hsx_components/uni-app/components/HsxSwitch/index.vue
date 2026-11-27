<script lang="ts">export default { name: 'HsxSwitch' }</script>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useModal } from '../../hooks/useFeedback'
import { resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import type { MaybePromise, MobileResponsiveValue } from '../../types'
const props = withDefaults(defineProps<{ modelValue?: any, activeValue?: any, inactiveValue?: any, loading?: boolean, disabled?: boolean, size?: MobileResponsiveValue<string | number>, confirmText?: string, action?: (next: any, current: any) => MaybePromise<void | boolean> }>(), { modelValue: false, activeValue: true, inactiveValue: false, loading: false, disabled: false, size: () => ({ compact: 24, medium: 26, expanded: 28 }), confirmText: '' })
const emit = defineEmits<{ (event: 'update:modelValue', value: any): void, (event: 'change', value: any): void, (event: 'error', error: unknown): void }>()
const innerLoading = ref(false); const actualLoading = computed(() => props.loading || innerLoading.value); const modal = useModal(); const layout = useAdaptiveContext()
const actualSize = computed(() => resolveAdaptiveValue(props.size, layout.widthClass.value, 24))
async function change(next: any) {
    if (props.disabled || actualLoading.value) return
    try {
        if (props.confirmText && !await modal.confirm(props.confirmText)) return
        if (props.action) { innerLoading.value = true; if (await props.action(next, props.modelValue) === false) return }
        emit('update:modelValue', next); emit('change', next)
    } catch (error) { emit('error', error) } finally { innerLoading.value = false }
}
</script>
<template><u-switch :model-value="modelValue" :active-value="activeValue" :inactive-value="inactiveValue" :loading="actualLoading" :disabled="disabled" :size="actualSize" @update:model-value="change" /></template>
