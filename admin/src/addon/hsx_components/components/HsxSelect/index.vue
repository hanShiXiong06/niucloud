<script lang="ts">
export default { name: 'HsxSelect', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { SelectOption } from '../../types'

type RemoteResult = SelectOption[] | { list: SelectOption[]; total?: number; hasMore?: boolean }

const props = withDefaults(
    defineProps<{
        modelValue?: string | number | boolean | Array<string | number | boolean> | null
        options?: SelectOption[]
        fetchOptions?: (keyword: string, page: number, limit: number) => Promise<RemoteResult>
        resolveValues?: (values: Array<string | number | boolean>) => Promise<SelectOption[]>
        pageSize?: number
        labelKey?: string
        valueKey?: string
        autoLoad?: boolean
        loading?: boolean
    }>(),
    {
        options: () => [],
        pageSize: 20,
        labelKey: 'label',
        valueKey: 'value',
        autoLoad: true,
        loading: false
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: any): void
    (event: 'change', value: any): void
    (event: 'load', options: SelectOption[]): void
}>()

const remoteLoading = ref(false)
const page = ref(1)
const keyword = ref('')
const hasMore = ref(true)
const optionCache = ref(new Map<string | number | boolean, SelectOption>())
let requestVersion = 0
let remoteTimer: ReturnType<typeof setTimeout> | undefined

const mergedOptions = computed(() => Array.from(optionCache.value.values()))
const actualLoading = computed(() => props.loading || remoteLoading.value)
const selectValue = computed(() => props.modelValue === null ? undefined : props.modelValue)

function optionValue(option: SelectOption) {
    return option[props.valueKey] as string | number | boolean
}

function mergeOptions(options: SelectOption[], replace = false) {
    const cache = replace ? new Map<string | number | boolean, SelectOption>() : new Map(optionCache.value)
    options.forEach((option) => cache.set(optionValue(option), option))
    optionCache.value = cache
    emit('load', mergedOptions.value)
}

async function loadOptions(reset = false) {
    if (!props.fetchOptions || remoteLoading.value || (!reset && !hasMore.value)) return
    if (reset) {
        page.value = 1
        hasMore.value = true
    }
    const version = ++requestVersion
    remoteLoading.value = true
    try {
        const result = await props.fetchOptions(keyword.value, page.value, props.pageSize)
        if (version !== requestVersion) return
        const list = Array.isArray(result) ? result : result.list
        const total = Array.isArray(result) ? undefined : result.total
        const remoteHasMore = Array.isArray(result) ? undefined : result.hasMore
        mergeOptions(list, reset)
        hasMore.value = remoteHasMore ?? (total !== undefined ? mergedOptions.value.length < total : list.length >= props.pageSize)
        if (hasMore.value) page.value += 1
    } finally {
        if (version === requestVersion) remoteLoading.value = false
    }
}

function remoteSearch(value: string) {
    keyword.value = value
    if (remoteTimer) clearTimeout(remoteTimer)
    remoteTimer = setTimeout(() => void loadOptions(true), 300)
}

function handlePopupScroll(event: Event) {
    const target = event.target as HTMLElement | null
    if (!target || target.scrollHeight - target.scrollTop - target.clientHeight > 24) return
    void loadOptions()
}

function handleVisibleChange(visible: boolean) {
    if (visible && props.autoLoad && !mergedOptions.value.length) void loadOptions(true)
}

async function resolveSelectedOptions() {
    if (!props.resolveValues || props.modelValue === undefined || props.modelValue === null) return
    const values = Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue]
    const missing = values.filter((value) => !optionCache.value.has(value))
    if (missing.length) mergeOptions(await props.resolveValues(missing))
}

watch(
    () => props.options,
    (options) => mergeOptions(options),
    { immediate: true, deep: true }
)
watch(() => props.modelValue, () => void resolveSelectedOptions(), { immediate: true, deep: true })

defineExpose({ reload: () => loadOptions(true), loadMore: loadOptions, options: mergedOptions })
</script>

<template>
    <el-select
        v-bind="$attrs"
        :model-value="selectValue"
        :loading="actualLoading"
        :filterable="Boolean(fetchOptions) || $attrs.filterable === true"
        :remote="Boolean(fetchOptions)"
        :remote-method="remoteSearch"
        @update:model-value="(value: any) => emit('update:modelValue', value)"
        @change="(value: any) => emit('change', value)"
        @visible-change="handleVisibleChange"
        @popup-scroll="handlePopupScroll"
    >
        <el-option
            v-for="option in mergedOptions"
            :key="String(optionValue(option))"
            :label="option[labelKey]"
            :value="optionValue(option)"
            :disabled="option.disabled"
        />
        <template v-for="(_, name) in $slots" #[name]="slotProps">
            <slot :name="name" v-bind="slotProps || {}" />
        </template>
    </el-select>
</template>
