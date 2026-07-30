<template>
    <el-cascader
        v-model="selectedPath"
        :options="options"
        :props="cascaderProps"
        clearable
        filterable
        :disabled="disabled"
        class="w-full"
        :placeholder="placeholder"
        @change="handleChange"
        @visible-change="visible => visible && ensureRootOptions()"
    />
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getErpGoodsCatalogHierarchy } from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{
    modelValue?: string
    placeholder?: string
    disabled?: boolean
}>(), {
    modelValue: '',
    placeholder: '选择 ERP 末级分类',
    disabled: false,
})
const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void
    (event: 'change', value: { category_name: string; category_path: string } | null): void
}>()

const options = ref<any[]>([])
const selectedPath = ref<string[]>([])
const rootOptions = ref<any[]>([])
let rootLoading: Promise<void> | null = null
const cascaderProps = {
    lazy: true,
    emitPath: true,
    checkStrictly: false,
    value: 'value',
    label: 'label',
    leaf: 'leaf',
    lazyLoad: loadChildren,
}

watch(() => props.modelValue, value => {
    const path = String(value || '').trim()
    selectedPath.value = path
        ? path.split('/').filter(Boolean).map((_name, index, list) => list.slice(0, index + 1).join('/'))
        : []
    options.value = path ? mergeSelectedBranch(rootOptions.value, path) : [...rootOptions.value]
    void ensureRootOptions()
}, { immediate: true })

async function ensureRootOptions(force = false) {
    if (!force && rootOptions.value.length) {
        options.value = props.modelValue
            ? mergeSelectedBranch(rootOptions.value, String(props.modelValue))
            : [...rootOptions.value]
        return
    }
    if (rootLoading) return rootLoading
    rootLoading = (async () => {
        try {
            const res: any = await getErpGoodsCatalogHierarchy({ node_type: 'root', category_only: 1, limit: 200 })
            const rows = Array.isArray(res?.data?.list) ? res.data.list : []
            rootOptions.value = rows.map(toOption)
            options.value = props.modelValue
                ? mergeSelectedBranch(rootOptions.value, String(props.modelValue))
                : [...rootOptions.value]
        } finally {
            rootLoading = null
        }
    })()
    return rootLoading
}

async function loadChildren(node: any, resolve: (rows: any[]) => void) {
    const raw = node?.data?.raw || {}
    const params = Number(node?.level || 0) === 0
        ? { node_type: 'root', category_only: 1, limit: 200 }
        : { node_type: 'category', category_path: raw.category_path || node.value || '', category_only: 1, limit: 200 }
    try {
        const res: any = await getErpGoodsCatalogHierarchy(params)
        const rows = Array.isArray(res?.data?.list) ? res.data.list : []
        resolve(rows.map(toOption))
    } catch {
        resolve([])
    }
}

function toOption(row: any) {
    return {
        value: String(row.category_path || ''),
        label: String(row.label || '未命名分类'),
        leaf: Number(row.is_leaf || 0) === 1,
        raw: row,
    }
}

function buildSelectedTree(path: string) {
    const names = path.split('/').filter(Boolean)
    const roots: any[] = []
    let children = roots
    names.forEach((name, index) => {
        const categoryPath = names.slice(0, index + 1).join('/')
        const option = {
            value: categoryPath,
            label: name,
            leaf: index === names.length - 1,
            raw: { node_type: 'category', category_path: categoryPath, label: name },
            children: index === names.length - 1 ? undefined : [],
        }
        children.push(option)
        children = option.children || []
    })
    return roots
}

function mergeSelectedBranch(roots: any[], path: string) {
    const selectedTree = buildSelectedTree(path)
    if (!roots.length) return selectedTree
    const selectedRoot = selectedTree[0]
    let matched = false
    const merged = roots.map(root => {
        if (!selectedRoot || root.value !== selectedRoot.value) return root
        matched = true
        return selectedRoot.children?.length ? { ...root, leaf: false, children: selectedRoot.children } : root
    })
    if (!matched && selectedRoot) merged.unshift(selectedRoot)
    return merged
}

function handleChange(values: string[]) {
    const categoryPath = Array.isArray(values) && values.length ? String(values[values.length - 1]) : ''
    const categoryName = categoryPath.split('/').filter(Boolean).pop() || ''
    emit('update:modelValue', categoryPath)
    emit('change', categoryPath ? { category_name: categoryName, category_path: categoryPath } : null)
    if (!categoryPath) options.value = [...rootOptions.value]
}
</script>
