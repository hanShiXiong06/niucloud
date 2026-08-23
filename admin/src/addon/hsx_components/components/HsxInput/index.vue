<script lang="ts">
export default { name: 'HsxInput', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
    defineProps<{
        modelValue?: string | number | null
        numeric?: boolean
        allowNegative?: boolean
        decimalPlaces?: number
        trimOnBlur?: boolean
        enterSearch?: boolean
    }>(),
    {
        modelValue: '',
        numeric: false,
        allowNegative: false,
        decimalPlaces: 2,
        trimOnBlur: true,
        enterSearch: true
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: string | number): void
    (event: 'input', value: string | number): void
    (event: 'change', value: string | number): void
    (event: 'blur', value: FocusEvent): void
    (event: 'search', value: string | number): void
}>()

const value = computed(() => props.modelValue ?? '')

function normalizeNumeric(input: string): string {
    let result = input.replace(props.allowNegative ? /[^\d.-]/g : /[^\d.]/g, '')
    if (props.allowNegative) {
        result = result.replace(/(?!^)-/g, '')
    }
    const [integer = '', ...decimals] = result.split('.')
    if (!decimals.length) return integer
    return `${integer}.${decimals.join('').slice(0, props.decimalPlaces)}`
}

function updateValue(input: string | number) {
    const nextValue = props.numeric ? normalizeNumeric(String(input)) : input
    emit('update:modelValue', nextValue)
    emit('input', nextValue)
}

function handleBlur(event: FocusEvent) {
    let nextValue = String(props.modelValue ?? '')
    if (props.trimOnBlur && !props.numeric) nextValue = nextValue.trim()
    if (props.numeric && nextValue !== '' && nextValue !== '-' && nextValue !== '.') {
        const parsed = Number(nextValue)
        if (Number.isFinite(parsed)) nextValue = String(parsed)
    }
    if (nextValue !== props.modelValue) emit('update:modelValue', nextValue)
    emit('change', nextValue)
    emit('blur', event)
}

function handleKeyup(event: KeyboardEvent) {
    if (props.enterSearch && event.key === 'Enter') emit('search', props.modelValue ?? '')
}
</script>

<template>
    <el-input
        v-bind="$attrs"
        :model-value="value"
        @update:model-value="updateValue"
        @blur="handleBlur"
        @keyup="handleKeyup"
    >
        <template v-for="(_, name) in $slots" #[name]="slotProps">
            <slot :name="name" v-bind="slotProps || {}" />
        </template>
    </el-input>
</template>
