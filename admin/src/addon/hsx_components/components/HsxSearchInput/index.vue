<script lang="ts">
export default { name: 'HsxSearchInput', inheritAttrs: false }
</script>

<script setup lang="ts">
import { onBeforeUnmount } from 'vue'
import { Search } from '@element-plus/icons-vue'
import HsxInput from '../HsxInput/index.vue'

const props = withDefaults(
    defineProps<{
        modelValue?: string | number | null
        placeholder?: string
        debounce?: number
        autoSearch?: boolean
        searchOnClear?: boolean
        loading?: boolean
        disabled?: boolean
        clearable?: boolean
        buttonText?: string
        showButton?: boolean
    }>(),
    {
        modelValue: '',
        placeholder: '请输入关键词',
        debounce: 400,
        autoSearch: false,
        searchOnClear: true,
        loading: false,
        disabled: false,
        clearable: true,
        buttonText: '搜索',
        showButton: true
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: string | number): void
    (event: 'search', value: string | number, trigger: 'enter' | 'button' | 'clear' | 'debounce'): void
    (event: 'clear'): void
}>()

let timer: ReturnType<typeof setTimeout> | undefined

function cancelTimer() {
    if (timer) clearTimeout(timer)
    timer = undefined
}

function triggerSearch(trigger: 'enter' | 'button' | 'clear' | 'debounce') {
    cancelTimer()
    emit('search', props.modelValue ?? '', trigger)
}

function handleUpdate(value: string | number) {
    emit('update:modelValue', value)
    if (!props.autoSearch) return
    cancelTimer()
    timer = setTimeout(() => emit('search', value, 'debounce'), props.debounce)
}

function handleClear() {
    cancelTimer()
    emit('clear')
    if (props.searchOnClear) emit('search', '', 'clear')
}

onBeforeUnmount(cancelTimer)
</script>

<template>
    <HsxInput
        v-bind="$attrs"
        :model-value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :clearable="clearable"
        @update:model-value="handleUpdate"
        @search="triggerSearch('enter')"
        @clear="handleClear"
    >
        <template v-if="!showButton" #prefix><el-icon><Search /></el-icon></template>
        <template v-if="showButton" #append>
            <el-button :icon="Search" :loading="loading" :disabled="disabled" @click="triggerSearch('button')">
                {{ buttonText }}
            </el-button>
        </template>
    </HsxInput>
</template>
