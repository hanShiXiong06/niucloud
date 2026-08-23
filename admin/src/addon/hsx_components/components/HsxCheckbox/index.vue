<script lang="ts">export default { name: 'HsxCheckbox', inheritAttrs: false }</script>
<script setup lang="ts">
import { computed } from 'vue'
import type { SelectOption } from '../../types'
const props = withDefaults(defineProps<{
    modelValue?: any
    options?: SelectOption[]
    label?: string
    trueValue?: any
    falseValue?: any
    type?: 'checkbox' | 'button'
    direction?: 'horizontal' | 'vertical'
    min?: number
    max?: number
    disabled?: boolean
    size?: 'large' | 'default' | 'small'
}>(), {
    modelValue: false, options: () => [], label: '', trueValue: true, falseValue: false,
    type: 'checkbox', direction: 'horizontal', disabled: false, size: 'default'
})
const emit = defineEmits<{ (event: 'update:modelValue', value: any): void, (event: 'change', value: any): void }>()
const grouped = computed(() => props.options.length > 0)
function change(value: any) { emit('update:modelValue', value); emit('change', value) }
</script>
<template>
    <el-checkbox-group v-if="grouped" v-bind="$attrs" class="hsx-checkbox-group" :class="`hsx-checkbox-group--${direction}`" :model-value="modelValue" :min="min" :max="max" :disabled="disabled" :size="size" @update:model-value="change">
        <component :is="type === 'button' ? 'el-checkbox-button' : 'el-checkbox'" v-for="option in options" :key="String(option.value)" :label="option.value" :disabled="option.disabled">
            <slot name="option" :option="option"><span>{{ option.label }}</span><small v-if="option.description">{{ option.description }}</small></slot>
        </component>
    </el-checkbox-group>
    <el-checkbox v-else v-bind="$attrs" :model-value="modelValue" :true-label="trueValue" :false-label="falseValue" :disabled="disabled" :size="size" @update:model-value="change"><slot>{{ label }}</slot></el-checkbox>
</template>
<style scoped>
.hsx-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px 16px; }
.hsx-checkbox-group--vertical { align-items: flex-start; flex-direction: column; }
.hsx-checkbox-group :deep(.el-checkbox), .hsx-checkbox-group :deep(.el-checkbox-button) { margin-right: 0; }
.hsx-checkbox-group small { display: block; margin-top: 2px; color: var(--hsx-text-secondary); font-size: 11px; font-weight: 400; }
</style>

