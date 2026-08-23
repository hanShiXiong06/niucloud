<script lang="ts">
export default { name: 'HsxCascader', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { Loading } from '@element-plus/icons-vue'
import type {
    AnyRecord,
    HsxCascaderLoadContext,
    HsxCascaderOption,
    HsxCascaderOptionsResult,
    HsxCascaderValue
} from '../../types'
import { deepClone } from '../../utils'

const props = withDefaults(
    defineProps<{
        modelValue?: HsxCascaderValue
        options?: HsxCascaderOption[]
        cascaderProps?: AnyRecord
        fetchOptions?: (parent: HsxCascaderOption | null, context: HsxCascaderLoadContext) => Promise<HsxCascaderOption[]>
        searchOptions?: (keyword: string) => Promise<HsxCascaderOptionsResult>
        resolvePaths?: (value: HsxCascaderValue) => Promise<HsxCascaderOptionsResult>
        beforeFilter?: (keyword: string) => boolean | Promise<boolean>
        filterMethod?: (node: AnyRecord, keyword: string) => boolean
        labelKey?: string
        valueKey?: string
        childrenKey?: string
        leafKey?: string
        emitPath?: boolean
        multiple?: boolean
        checkStrictly?: boolean
        filterable?: boolean
        clearable?: boolean
        showAllLevels?: boolean
        cacheSearch?: boolean
        loading?: boolean
    }>(),
    {
        modelValue: null,
        options: () => [],
        cascaderProps: () => ({}),
        labelKey: 'label',
        valueKey: 'value',
        childrenKey: 'children',
        leafKey: 'leaf',
        emitPath: true,
        multiple: false,
        checkStrictly: false,
        filterable: true,
        clearable: true,
        showAllLevels: false,
        cacheSearch: true,
        loading: false
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: HsxCascaderValue): void
    (event: 'change', value: HsxCascaderValue, selected: HsxCascaderOption[][]): void
    (event: 'load', options: HsxCascaderOption[], parent: HsxCascaderOption | null): void
    (event: 'search', keyword: string, options: HsxCascaderOption[]): void
    (event: 'visible-change', visible: boolean): void
    (event: 'error', error: unknown, stage: 'load' | 'search' | 'resolve'): void
}>()

const cascaderRef = ref<any>()
const baseOptions = ref<HsxCascaderOption[]>(deepClone(props.options))
const remoteOptions = ref<HsxCascaderOption[] | null>(null)
const loadingCount = ref(0)
const searchCache = new Map<string, HsxCascaderOption[]>()
let resolvedValueKey = ''
let rootLoaded = props.options.length > 0
let rootLoadingPromise: Promise<HsxCascaderOption[]> | null = null

const actualLoading = computed(() => props.loading || loadingCount.value > 0)
const cascaderValue = computed(() => props.modelValue === null ? undefined : props.modelValue)
const displayOptions = computed(() => remoteOptions.value || baseOptions.value)
const mergedCascaderProps = computed(() => ({
    value: props.valueKey,
    label: props.labelKey,
    children: props.childrenKey,
    leaf: props.leafKey,
    emitPath: props.emitPath,
    multiple: props.multiple,
    checkStrictly: props.checkStrictly,
    lazy: Boolean(props.fetchOptions),
    lazyLoad: handleLazyLoad,
    ...props.cascaderProps
}))

watch(
    () => props.options,
    (options) => {
        baseOptions.value = deepClone(options || [])
        rootLoaded = Boolean(options?.length)
        resolvedValueKey = ''
        void resolveSelectedPaths()
    },
    { deep: true }
)

watch(
    () => props.modelValue,
    () => void resolveSelectedPaths(),
    { immediate: true, deep: true }
)

function optionValue(option: HsxCascaderOption) {
    return option[props.valueKey]
}

function optionChildren(option: HsxCascaderOption): HsxCascaderOption[] {
    return (option[props.childrenKey] as HsxCascaderOption[]) || []
}

function pathsToTree(paths: HsxCascaderOption[][]): HsxCascaderOption[] {
    const roots: HsxCascaderOption[] = []
    paths.forEach((path) => {
        let target = roots
        path.forEach((source, index) => {
            const value = optionValue(source)
            let current = target.find((item) => optionValue(item) === value)
            if (!current) {
                current = { ...deepClone(source) }
                if (index < path.length - 1) current[props.childrenKey] = []
                target.push(current)
            }
            if (index < path.length - 1) {
                if (!Array.isArray(current[props.childrenKey])) current[props.childrenKey] = []
                target = current[props.childrenKey] as HsxCascaderOption[]
            }
        })
    })
    return roots
}

function normalizeOptions(result: HsxCascaderOptionsResult): HsxCascaderOption[] {
    if (!result.length) return []
    return Array.isArray(result[0])
        ? pathsToTree(result as HsxCascaderOption[][])
        : deepClone(result as HsxCascaderOption[])
}

function mergeTrees(target: HsxCascaderOption[], incoming: HsxCascaderOption[]): HsxCascaderOption[] {
    const output = deepClone(target)
    incoming.forEach((source) => {
        const value = optionValue(source)
        const current = output.find((item) => optionValue(item) === value)
        if (!current) {
            output.push(deepClone(source))
            return
        }
        const currentChildren = optionChildren(current)
        const sourceChildren = optionChildren(source)
        Object.assign(current, deepClone(source))
        if (currentChildren.length || sourceChildren.length) {
            current[props.childrenKey] = mergeTrees(currentChildren, sourceChildren)
        }
    })
    return output
}

function valueKey(value: HsxCascaderValue) {
    try {
        return JSON.stringify(value)
    } catch {
        return String(value)
    }
}

async function withLoading<T>(task: () => Promise<T>): Promise<T> {
    loadingCount.value += 1
    try {
        return await task()
    } finally {
        loadingCount.value = Math.max(0, loadingCount.value - 1)
    }
}

async function loadRootOptions(): Promise<HsxCascaderOption[]> {
    if (!props.fetchOptions || rootLoaded) return deepClone(baseOptions.value)
    if (rootLoadingPromise) return rootLoadingPromise

    rootLoadingPromise = withLoading(() => props.fetchOptions!(null, {
        level: 0,
        keyword: '',
        path: []
    }))
        .then((options) => {
            baseOptions.value = mergeTrees(baseOptions.value, options)
            rootLoaded = true
            emit('load', options, null)
            return deepClone(baseOptions.value)
        })
        .catch((error) => {
            emit('error', error, 'load')
            throw error
        })
        .finally(() => {
            rootLoadingPromise = null
        })

    return rootLoadingPromise
}

async function handleLazyLoad(node: AnyRecord, resolve: (options: HsxCascaderOption[]) => void) {
    if (!props.fetchOptions) return resolve([])
    const parent = node.level === 0 ? null : (node.data as HsxCascaderOption)
    const path = (node.pathNodes || []).map((item: AnyRecord) => item.data).filter(Boolean)
    try {
        if (!parent) {
            resolve(await loadRootOptions())
            return
        }
        const options = await withLoading(() => props.fetchOptions!(parent, {
            level: Number(node.level || 0),
            keyword: '',
            path
        }))
        resolve(deepClone(options))
        emit('load', options, parent)
    } catch (error) {
        resolve([])
        if (parent) emit('error', error, 'load')
    }
}

async function handleBeforeFilter(keyword: string) {
    const allowed = props.beforeFilter ? await props.beforeFilter(keyword) : true
    if (allowed === false) return false
    if (!props.searchOptions) return true
    if (!keyword.trim()) {
        remoteOptions.value = null
        return true
    }

    try {
        let options = props.cacheSearch ? searchCache.get(keyword) : undefined
        if (!options) {
            const result = await withLoading(() => props.searchOptions!(keyword))
            options = normalizeOptions(result)
            if (props.cacheSearch) searchCache.set(keyword, deepClone(options))
        }
        remoteOptions.value = deepClone(options)
        await nextTick()
        emit('search', keyword, options)
        return true
    } catch (error) {
        emit('error', error, 'search')
        return false
    }
}

function handleFilter(node: AnyRecord, keyword: string) {
    if (props.filterMethod) return props.filterMethod(node, keyword)
    const text = [...(node.pathLabels || []), node.text].filter(Boolean).join(' / ').toLowerCase()
    return text.includes(keyword.trim().toLowerCase())
}

async function resolveSelectedPaths(force = false) {
    if (!props.resolvePaths || props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return
    const key = valueKey(props.modelValue)
    if (!force && key === resolvedValueKey) return
    try {
        const result = await withLoading(() => props.resolvePaths!(props.modelValue))
        const resolved = normalizeOptions(result)
        baseOptions.value = mergeTrees(baseOptions.value, resolved)
        resolvedValueKey = key
    } catch (error) {
        emit('error', error, 'resolve')
    }
}

function selectedPaths(): HsxCascaderOption[][] {
    const checked = cascaderRef.value?.getCheckedNodes?.() || []
    return checked.map((node: AnyRecord) => (node.pathNodes || []).map((item: AnyRecord) => item.data))
}

function handleUpdate(value: unknown) {
    emit('update:modelValue', value as HsxCascaderValue)
}

function handleChange(value: unknown) {
    emit('change', value as HsxCascaderValue, selectedPaths())
}

function handleVisibleChange(visible: boolean) {
    if (!visible) remoteOptions.value = null
    if (visible) {
        void loadRootOptions().catch(() => undefined)
        void resolveSelectedPaths()
    }
    emit('visible-change', visible)
}

function reload() {
    baseOptions.value = deepClone(props.options || [])
    remoteOptions.value = null
    rootLoaded = props.options.length > 0
    resolvedValueKey = ''
    return loadRootOptions().then(() => resolveSelectedPaths(true))
}

function clearSearchCache() {
    searchCache.clear()
}

defineExpose({
    cascaderRef,
    options: displayOptions,
    loading: actualLoading,
    reload,
    clearSearchCache,
    resolveSelectedPaths,
    getCheckedNodes: (...args: any[]) => cascaderRef.value?.getCheckedNodes?.(...args)
})
</script>

<template>
    <div class="hsx-cascader">
        <el-cascader
            ref="cascaderRef"
            v-bind="$attrs"
            :model-value="cascaderValue"
            :options="displayOptions"
            :props="mergedCascaderProps"
            :filterable="filterable"
            :clearable="clearable"
            :show-all-levels="showAllLevels"
            :before-filter="handleBeforeFilter"
            :filter-method="handleFilter"
            @update:model-value="handleUpdate"
            @change="handleChange"
            @visible-change="handleVisibleChange"
        >
            <template v-for="(_, name) in $slots" #[name]="slotProps">
                <slot :name="name" v-bind="slotProps || {}" />
            </template>
        </el-cascader>
        <el-icon v-if="actualLoading" class="hsx-cascader__loading"><Loading /></el-icon>
    </div>
</template>

<style scoped>
.hsx-cascader { position: relative; display: inline-flex; width: 100%; align-items: center; }
.hsx-cascader :deep(.el-cascader) { width: 100%; }
.hsx-cascader__loading { position: absolute; z-index: 2; right: 30px; color: var(--el-color-primary); animation: hsx-cascader-rotate 1s linear infinite; pointer-events: none; }
@keyframes hsx-cascader-rotate { to { transform: rotate(360deg); } }
</style>
