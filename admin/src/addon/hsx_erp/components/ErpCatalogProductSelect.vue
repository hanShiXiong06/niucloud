<template>
    <div class="catalog-select-shell">
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
            placement="bottom-start"
            :fallback-placements="['top-start']"
            :teleported="true"
            :persistent="false"
            :popper-options="catalogPopperOptions"
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
                <div class="catalog-empty">
                    <span v-if="rootLoading">正在加载商品目录…</span>
                    <template v-else-if="loadError">
                        <span>{{ loadError }}</span>
                        <el-button type="primary" link @click.stop="ensureRootOptions(true)">重新加载</el-button>
                    </template>
                    <span v-else>没有找到匹配的商品型号</span>
                </div>
            </template>
        </el-cascader>
        <div v-if="!dropdownVisible && selectedDisplayText" class="catalog-select-display" :title="selectedDisplayText">
            {{ selectedDisplayText }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { getErpGoodsCatalogHierarchy } from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{
    modelValue?: number | string
    categoryPath?: string
    placeholder?: string
    clearable?: boolean
    disabled?: boolean
}>(), {
    modelValue: '',
    categoryPath: '',
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
const rootLoading = ref(false)
const loadError = ref('')
const rootOptionsLoaded = ref(false)
const dropdownVisible = ref(false)
const nodeCache = new Map<string, any>()
const branchCache = new Map<string, { at: number, rows: any[] }>()
let selectedSequence = 0
let searchSequence = 0
let searchOptionsActive = false

const selectedDisplayText = computed(() => {
    const raw = selectedNode.value || {}
    const parts = [
        raw.category_path || props.categoryPath || '',
        raw.node_type === 'brand' || raw.node_type === 'series' || raw.node_type === 'product' ? raw.brand_name : '',
        raw.node_type === 'series' || raw.node_type === 'product' ? raw.series_name : '',
        raw.node_type === 'product' ? (raw.product_name || raw.label) : ''
    ]
    return parts.map(item => String(item || '').trim()).filter(Boolean).join(' / ')
})

const catalogPopperOptions = {
    strategy: 'fixed',
    modifiers: [
        {
            name: 'computeStyles',
            options: {
                adaptive: false,
                gpuAcceleration: false
            }
        },
        {
            name: 'offset',
            options: {
                offset: [0, 6]
            }
        },
        {
            name: 'flip',
            options: {
                fallbackPlacements: ['top-start'],
                padding: 12
            }
        },
        {
            name: 'preventOverflow',
            options: {
                boundary: 'viewport',
                padding: 12,
                altAxis: true
            }
        }
    ]
}

const cascaderProps = {
    lazy: true,
    emitPath: true,
    // 分类本身也是有效选择：商家可先选分类，再手动填写商品名称；
    // 继续展开到型号时，仍可自动带入标准目录型号。
    // checkStrictly: true,
    value: 'value',
    label: 'label',
    leaf: 'leaf',
    lazyLoad: loadCascaderChildren,
    // 多级目录使用 hover 时，指针经过下一列会连续触发加载并让 Popper 反复重算位置。
    // 点击展开既稳定，也能避免误触发大量分支请求。
    expandTrigger: 'hover' as const,
}

watch([() => props.modelValue, () => props.categoryPath], ([value, categoryPath]) => {
    if (!value) {
        selectedSequence++
        if (categoryPath) {
            resolveCategorySelection(categoryPath)
        } else {
            selectedNode.value = null
            cascaderValue.value = []
        }
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

function resolveCategorySelection(path: string) {
    const categoryPath = String(path || '').trim()
    const segments = categorySegments(categoryPath)
    if (!categoryPath || (segments.length === 1 && segments[0] === '')) return
    const roots: any[] = []
    let children = roots
    segments.forEach((label, index) => {
        const currentPath = segments.slice(0, index + 1).join('/')
        const raw = {
            node_type: 'category',
            category_path: currentPath,
            label,
            product_count: 0
        }
        children = getOrCreate(children, categoryValue(currentPath), label, raw).children
        selectedNode.value = raw
    })
    cascaderOptions.value = roots
    cascaderValue.value = segments.map((_label, index) => categoryValue(segments.slice(0, index + 1).join('/')))
    rootOptionsLoaded.value = false
    nextTick(() => ensureRootOptions())
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
        loadError.value = ''
        resolve(rows.map(toCascaderOption))
    } catch (error: any) {
        loadError.value = String(error?.msg || error?.message || '商品目录加载失败')
        resolve([])
    }
}

async function ensureRootOptions(force = false) {
    if (rootLoading.value || searchOptionsActive) return
    if (!force && rootOptionsLoaded.value) return
    const params = { node_type: 'root', limit: 200 }
    const key = branchKey(params)
    rootLoading.value = true
    loadError.value = ''
    try {
        const cached = !force ? branchCache.get(key) : null
        let rows = cached && Date.now() - cached.at < CACHE_TTL ? cached.rows : []
        if (!rows.length) {
            const res: any = await getErpGoodsCatalogHierarchy(params)
            rows = Array.isArray(res?.data?.list) ? res.data.list : []
            branchCache.set(key, { at: Date.now(), rows })
        }
        const rootOptions = rows.map(toCascaderOption)
        const selectedBranches = new Map(cascaderOptions.value.map((option: any) => [option.value, option]))
        cascaderOptions.value = rootOptions.map((option: any) => {
            const selectedBranch: any = selectedBranches.get(option.value)
            return selectedBranch?.children?.length
                ? { ...option, children: selectedBranch.children }
                : option
        })
        rootOptionsLoaded.value = true
        if (!rows.length) loadError.value = '当前站点还没有可用的商品目录'
    } catch (error: any) {
        loadError.value = String(error?.msg || error?.message || '商品目录加载失败，请重试')
        cascaderOptions.value = []
    } finally {
        rootLoading.value = false
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
    if (!raw) return
    if (raw.node_type !== 'product') {
        selectedNode.value = raw
        emit('change', raw)
        return
    }
    selectedNode.value = raw
    const id = Number(raw.site_product_id || 0)
    emit('update:modelValue', id)
    emit('change', raw)
}

function handleVisibleChange(visible: boolean) {
    dropdownVisible.value = visible
    if (visible && !searchOptionsActive) {
        ensureRootOptions()
        return
    }
    if (!visible && searchOptionsActive) {
        searchOptionsActive = false
        searchSequence++
        cascaderOptions.value = []
        rootOptionsLoaded.value = false
        if (props.modelValue) {
            resolveSelected(props.modelValue)
            return
        }
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
        // 懒加载 Cascader 仅设置 value 不足以回显文字，必须把当前产品的完整
        // “品类 / 品牌 / 系列 / 型号”节点链补入 options。
        cascaderOptions.value = buildSearchOptions([raw])
        cascaderValue.value = productPathValues(raw)
        rootOptionsLoaded.value = false
        await nextTick()
        ensureRootOptions()
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
    rootOptionsLoaded.value = false
    loadError.value = ''
    ensureRootOptions(true)
    if (props.modelValue) resolveSelected(props.modelValue)
}

defineExpose({ refresh })
onBeforeUnmount(() => { selectedSequence++; searchSequence++ })
</script>

<style scoped>
.catalog-select-shell { position: relative; width: 100%; }
.catalog-select-display {
    position: absolute;
    z-index: 1;
    top: 1px;
    right: 34px;
    bottom: 1px;
    left: 12px;
    display: flex;
    min-width: 0;
    align-items: center;
    overflow: hidden;
    background: var(--el-fill-color-blank);
    color: var(--el-text-color-regular);
    font-size: var(--el-font-size-base);
    line-height: 1;
    pointer-events: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.catalog-node { width: 100%; min-width: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.catalog-node__label { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.catalog-node__count { flex-shrink: 0; color: var(--el-text-color-placeholder); font-size: 11px;  margin-right: 4px; }
.catalog-empty { display: flex; min-width: 220px; align-items: center; justify-content: center; flex-direction: column; gap: 4px; padding: 18px; color: var(--el-text-color-secondary); text-align: center; }
</style>

<style>
.erp-catalog-cascader-popper {
    max-width: calc(100vw - 24px);
}
.erp-catalog-cascader-popper .el-cascader-panel {
    max-width: calc(100vw - 24px);
    overflow-x: auto;
    overscroll-behavior-x: contain;
}
.erp-catalog-cascader-popper .el-cascader-menu {
    width: 176px;
    min-width: 160px;
}
.erp-catalog-cascader-popper .el-cascader-menu:last-child {
    width: 240px;
}
.erp-catalog-cascader-popper .el-cascader-node { padding: 0 10px; }
</style>
