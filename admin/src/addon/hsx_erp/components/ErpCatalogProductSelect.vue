<template>
    <el-cascader
        v-model="cascaderValue"
        :options="cascaderOptions"
        :props="cascaderProps"
        :placeholder="resolvingSelected ? '正在读取商品型号…' : placeholder"
        :disabled="disabled"
        :clearable="clearable"
        :show-all-levels="true"
        filterable
        :debounce="300"
        :before-filter="beforeRemoteFilter"
        :filter-method="remoteFilterMethod"
        separator=" / "
        popper-class="erp-catalog-cascader-popper"
        class="w-full"
        @change="handleChange"
        @visible-change="handleVisibleChange"
    >
        <template #default="{ data }">
            <span class="catalog-node" :title="data.label">
                <span class="catalog-node__label">{{ data.label }}</span>
                <span v-if="!data.leaf && data.raw?.product_count" class="catalog-node__count">{{ data.raw.product_count }}</span>
            </span>
        </template>
        <template #empty>
            <div class="catalog-empty">没有找到匹配的商品型号</div>
        </template>
    </el-cascader>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { getErpGoodsCatalogHierarchy } from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{
    modelValue?: number | string
    placeholder?: string
    clearable?: boolean
    disabled?: boolean
}>(), {
    modelValue: '',
    placeholder: '请选择商品目录型号',
    clearable: true,
    disabled: false
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | string): void
    (e: 'change', value: any | null): void
}>()

const CACHE_TTL = 60 * 1000
const cascaderValue = ref<string[]>([])
const cascaderOptions = ref<any[]>([])
const selectedNode = ref<any | null>(null)
const resolvingSelected = ref(false)
const nodeCache = new Map<string, any>()
const branchCache = new Map<string, { at: number, rows: any[] }>()
let selectedSequence = 0
let searchSequence = 0
let searchOptionsActive = false

const cascaderProps = {
    lazy: true,
    emitPath: true,
    checkStrictly: false,
    value: 'value',
    label: 'label',
    leaf: 'leaf',
    lazyLoad: loadCascaderChildren
}

watch(() => props.modelValue, value => {
    if (!value) {
        selectedSequence++
        selectedNode.value = null
        cascaderValue.value = []
        return
    }
    if (Number(selectedNode.value?.site_product_id || 0) !== Number(value)) resolveSelected(value)
}, { immediate: true })

function categoryValue(path: string) { return `category:${path}` }
function brandValue(path: string, brand: string) { return `brand:${path}|${brand}` }
function seriesValue(path: string, brand: string, series: string) { return `series:${path}|${brand}|${series}` }
function productValue(id: number | string) { return `product:${id}` }

function categorySegments(path: string): string[] {
    const parts = String(path || '').split('/').map(item => item.trim()).filter(Boolean)
    return parts.length ? parts : ['']
}

function productPathValues(row: any): string[] {
    const categoryPath = String(row.category_path || '')
    const values: string[] = []
    const segments = categorySegments(categoryPath)
    if (segments.length === 1 && segments[0] === '') values.push(categoryValue(''))
    else segments.forEach((_item, index) => values.push(categoryValue(segments.slice(0, index + 1).join('/'))))
    values.push(brandValue(categoryPath, String(row.brand_name || '')))
    values.push(seriesValue(categoryPath, String(row.brand_name || ''), String(row.series_name || '')))
    values.push(productValue(row.site_product_id))
    return values
}

function optionValue(raw: any): string {
    if (raw.node_type === 'category') return categoryValue(String(raw.category_path || ''))
    if (raw.node_type === 'brand') return brandValue(String(raw.category_path || ''), String(raw.brand_name || ''))
    if (raw.node_type === 'series') return seriesValue(String(raw.category_path || ''), String(raw.brand_name || ''), String(raw.series_name || ''))
    return productValue(raw.site_product_id)
}

function toCascaderOption(raw: any) {
    const value = optionValue(raw)
    nodeCache.set(value, raw)
    return { value, label: raw.label || raw.product_name || '未命名', leaf: raw.node_type === 'product', raw }
}

function requestParams(node: any): Record<string, any> {
    if (!node || Number(node.level || 0) === 0) return { node_type: 'root', limit: 200 }
    const raw = node.data?.raw || {}
    return {
        node_type: raw.node_type || 'root',
        category_path: raw.category_path || '',
        brand_name: raw.brand_name || '',
        series_name: raw.series_name || '',
        limit: 200
    }
}

function branchKey(params: Record<string, any>) {
    return [params.node_type, params.category_path, params.brand_name, params.series_name].join('|')
}

async function loadCascaderChildren(node: any, resolve: (rows: any[]) => void) {
    const params = requestParams(node)
    const key = branchKey(params)
    const cached = branchCache.get(key)
    if (cached && Date.now() - cached.at < CACHE_TTL) return resolve(cached.rows.map(toCascaderOption))
    try {
        const res: any = await getErpGoodsCatalogHierarchy(params)
        const rows = Array.isArray(res?.data?.list) ? res.data.list : []
        branchCache.set(key, { at: Date.now(), rows })
        resolve(rows.map(toCascaderOption))
    } catch {
        resolve([])
    }
}

function getOrCreate(list: any[], value: string, label: string, raw: any, leaf = false) {
    let node = list.find(item => item.value === value)
    if (!node) {
        node = { value, label, leaf, raw, children: leaf ? undefined : [] }
        list.push(node)
    }
    nodeCache.set(value, raw)
    return node
}

function buildSearchOptions(rows: any[]): any[] {
    const roots: any[] = []
    rows.forEach(row => {
        const categoryPath = String(row.category_path || '')
        const segments = categorySegments(categoryPath)
        let children = roots
        if (segments.length === 1 && segments[0] === '') {
            const raw = { node_type: 'category', category_path: '', product_count: 0 }
            children = getOrCreate(children, categoryValue(''), '未分类', raw).children
        } else {
            segments.forEach((label, index) => {
                const path = segments.slice(0, index + 1).join('/')
                const raw = { node_type: 'category', category_path: path, product_count: 0 }
                children = getOrCreate(children, categoryValue(path), label, raw).children
            })
        }
        const brand = String(row.brand_name || '')
        const brandRaw = { node_type: 'brand', category_path: categoryPath, brand_name: brand }
        children = getOrCreate(children, brandValue(categoryPath, brand), brand || '未设置品牌', brandRaw).children
        const series = String(row.series_name || '')
        const seriesRaw = { node_type: 'series', category_path: categoryPath, brand_name: brand, series_name: series }
        children = getOrCreate(children, seriesValue(categoryPath, brand, series), series || '未分系列', seriesRaw).children
        const productRaw = { ...row, node_type: 'product', label: row.label || row.product_name || '未命名', search_match: true }
        getOrCreate(children, productValue(row.site_product_id), productRaw.label, productRaw, true)
    })
    return roots
}

async function beforeRemoteFilter(keyword: string) {
    const value = String(keyword || '').trim()
    if (!value) return false
    const sequence = ++searchSequence
    try {
        const res: any = await getErpGoodsCatalogHierarchy({ keyword: value, limit: 50 })
        if (sequence !== searchSequence) return false
        const rows = Array.isArray(res?.data?.list) ? res.data.list : []
        searchOptionsActive = true
        cascaderOptions.value = buildSearchOptions(rows)
        await nextTick()
        return true
    } catch {
        return false
    }
}

function remoteFilterMethod(node: any) {
    return node?.data?.raw?.search_match === true
}

function handleChange(path: string[] | string | number | null) {
    const values = Array.isArray(path) ? path : []
    if (!values.length) {
        selectedNode.value = null
        emit('update:modelValue', '')
        emit('change', null)
        return
    }
    const raw = nodeCache.get(String(values[values.length - 1]))
    if (raw?.node_type !== 'product') return
    selectedNode.value = raw
    const id = Number(raw.site_product_id || 0)
    emit('update:modelValue', id)
    emit('change', raw)
}

function handleVisibleChange(visible: boolean) {
    if (!visible && searchOptionsActive) {
        searchOptionsActive = false
        searchSequence++
        cascaderOptions.value = []
    }
}

async function resolveSelected(value: number | string) {
    const sequence = ++selectedSequence
    resolvingSelected.value = true
    try {
        const res: any = await getErpGoodsCatalogHierarchy({ site_product_id: value, limit: 20 })
        if (sequence !== selectedSequence) return
        const row = res?.data?.list?.[0]
        if (!row) return
        const raw = { ...row, node_type: 'product' }
        selectedNode.value = raw
        nodeCache.set(productValue(raw.site_product_id), raw)
        cascaderValue.value = productPathValues(raw)
    } catch {
        if (sequence === selectedSequence) selectedNode.value = null
    } finally {
        if (sequence === selectedSequence) resolvingSelected.value = false
    }
}

function refresh() {
    branchCache.clear()
    nodeCache.clear()
    cascaderOptions.value = []
    if (props.modelValue) resolveSelected(props.modelValue)
}

defineExpose({ refresh })
onBeforeUnmount(() => { selectedSequence++; searchSequence++ })
</script>

<style scoped>
.catalog-node { width: 100%; min-width: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.catalog-node__label { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.catalog-node__count { flex-shrink: 0; color: var(--el-text-color-placeholder); font-size: 11px;  margin-right: 4px; }
.catalog-empty { padding: 18px; color: var(--el-text-color-secondary); text-align: center; }
</style>

<style>
.erp-catalog-cascader-popper .el-cascader-menu { width: 150px; min-width: 150px; }
.erp-catalog-cascader-popper .el-cascader-node { padding: 0 10px; }
</style>
