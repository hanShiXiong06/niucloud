<template>
    <view>
        <view class="field" :class="[`field--${layout}`, { 'field--embedded': embedded, 'field--on': displayLabel }]" @click="open">
            <text class="label"><text v-if="required" class="req">*</text>{{ label }}</text>
            <view class="value" :class="{ 'value--ph': !displayLabel }">
                <text class="truncate">{{ displayLabel || placeholder }}</text>
                <text v-if="clearable && displayLabel" class="nc-iconfont nc-icon-guanbiV6xx clear-icon" @click.stop="clear"></text>
                <text class="nc-iconfont nc-icon-youV6xx arrow"></text>
            </view>
        </view>

        <u-popup :show="show" mode="bottom" round="20" :safe-area-inset-bottom="true" @close="close">
            <view class="popup">
                <view class="head">
                    <view class="head-close" @click="close"><u-icon name="arrow-down" color="#64748b" size="18" /></view>
                    <view class="head-title-wrap">
                        <text class="head-title">选择商品型号</text>
                        <text class="head-sub">按品类、品牌和系列快速查找</text>
                    </view>
                    <view class="head-reset" @click="resetWorkspace">重置</view>
                </view>

                <view class="search">
                    <u-icon name="search" color="#94a3b8" size="18" />
                    <input v-model="keyword" class="search-input" placeholder="输入品牌、系列或型号" confirm-type="search" @input="scheduleSearch" @confirm="searchProducts" />
                    <view v-if="keyword" class="search-clear" @click="clearSearch"><u-icon name="close-circle-fill" color="#cbd5e1" size="17" /></view>
                    <text class="search-action" @click="searchProducts">搜索</text>
                </view>

                <template v-if="searchMode">
                    <view class="result-head">
                        <view>
                            <text class="result-title">搜索结果</text>
                            <text class="result-count">{{ searchResults.length }} 个型号</text>
                        </view>
                        <text class="result-back" @click="clearSearch">返回目录</text>
                    </view>
                    <scroll-view scroll-y class="search-results">
                        <view v-if="searchLoading" class="empty"><u-loading-icon size="28" /><text>正在搜索商品目录</text></view>
                        <view v-else-if="!searchResults.length" class="empty"><u-empty mode="search" text="没有匹配的商品型号" /></view>
                        <view
                            v-for="node in searchResults"
                            v-else
                            :key="node.node_key"
                            class="search-product"
                            :class="{ 'search-product--selected': isSelected(node) }"
                            @click="chooseProduct(node)"
                        >
                            <view class="product-copy">
                                <text class="product-name">{{ node.label }}</text>
                                <text class="product-path">{{ node.path_text || [node.category_path, node.brand_name, node.series_name].filter(Boolean).join(' / ') }}</text>
                            </view>
                            <u-icon :name="isSelected(node) ? 'checkmark-circle-fill' : 'arrow-right'" :color="isSelected(node) ? '#2563eb' : '#cbd5e1'" size="18" />
                        </view>
                    </scroll-view>
                </template>

                <template v-else>
                    <scroll-view scroll-x class="category-tabs" :show-scrollbar="false">
                        <view class="category-tabs__inner">
                            <view
                                v-for="node in rootCategories"
                                :key="node.node_key"
                                class="category-tab"
                                :class="{ 'category-tab--active': sameNode(activeRoot, node) }"
                                @click="selectRoot(node)"
                            >{{ node.label }}</view>
                        </view>
                    </scroll-view>

                    <scroll-view v-if="subcategories.length" scroll-x class="subcategory-tabs" :show-scrollbar="false">
                        <view class="subcategory-tabs__inner">
                            <view
                                v-for="node in subcategories"
                                :key="node.node_key"
                                class="subcategory-tab"
                                :class="{ 'subcategory-tab--active': sameNode(activeCategory, node) }"
                                @click="selectCategory(node)"
                            >{{ node.label }}</view>
                        </view>
                    </scroll-view>

                    <view class="workspace">
                        <scroll-view scroll-y class="brand-rail" :show-scrollbar="false">
                            <view v-if="workspaceLoading && !brands.length" class="brand-loading"><u-loading-icon size="22" /></view>
                            <view
                                v-for="node in brands"
                                :key="node.node_key"
                                class="brand-item"
                                :class="{ 'brand-item--active': sameNode(activeBrand, node) }"
                                @click="selectBrand(node)"
                            >
                                <text class="brand-name">{{ node.label }}</text>
                                <text class="brand-count">{{ node.product_count || 0 }}</text>
                            </view>
                        </scroll-view>

                        <view class="product-panel">
                            <scroll-view v-if="seriesNodes.length" scroll-x class="series-tabs" :show-scrollbar="false">
                                <view class="series-tabs__inner">
                                    <view
                                        v-for="node in seriesNodes"
                                        :key="node.node_key"
                                        class="series-tab"
                                        :class="{ 'series-tab--active': sameNode(activeSeries, node) }"
                                        @click="selectSeries(node)"
                                    >{{ node.label }}</view>
                                </view>
                            </scroll-view>

                            <view class="panel-context">
                                <view class="panel-title-wrap">
                                    <text class="panel-title">{{ activeSeries?.label || activeBrand?.label || '商品型号' }}</text>
                                    <text v-if="products.length" class="panel-count">{{ products.length }} 个</text>
                                </view>
                                <text v-if="activeCategory?.label" class="panel-path">{{ activeCategory.label }} · {{ activeBrand?.label || '全部品牌' }}</text>
                            </view>

                            <scroll-view scroll-y class="product-list" :show-scrollbar="false">
                                <view v-if="workspaceLoading" class="empty empty--workspace"><u-loading-icon size="28" /><text>正在加载型号</text></view>
                                <view v-else-if="!products.length" class="empty empty--workspace"><u-empty mode="data" text="当前目录暂无型号" /></view>
                                <view
                                    v-for="node in products"
                                    v-else
                                    :key="node.node_key"
                                    class="product-card"
                                    :class="{ 'product-card--selected': isSelected(node) }"
                                    @click="chooseProduct(node)"
                                >
                                    <text class="product-name">{{ node.label }}</text>
                                    <view v-if="isSelected(node)" class="selected-mark"><u-icon name="checkmark" color="#fff" size="13" /></view>
                                </view>
                            </scroll-view>
                        </view>
                    </view>
                </template>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { getMobileErpGoodsCatalogHierarchy } from '@/addon/hsx_erp/api/erp'

const props = defineProps({
    modelValue: { type: [Number, String], default: '' },
    selectedLabel: { type: String, default: '' },
    label: { type: String, default: '商品型号' },
    placeholder: { type: String, default: '请选择商品型号' },
    required: { type: Boolean, default: true },
    layout: { type: String, default: 'horizontal' },
    embedded: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'change', 'clear'])

const CACHE_TTL = 60 * 1000
const show = ref(false)
const keyword = ref('')
const searchMode = ref(false)
const searchLoading = ref(false)
const workspaceLoading = ref(false)
const workspaceReady = ref(false)
const rootCategories = ref<any[]>([])
const subcategories = ref<any[]>([])
const brands = ref<any[]>([])
const seriesNodes = ref<any[]>([])
const products = ref<any[]>([])
const searchResults = ref<any[]>([])
const activeRoot = ref<any | null>(null)
const activeCategory = ref<any | null>(null)
const activeBrand = ref<any | null>(null)
const activeSeries = ref<any | null>(null)
const selectedName = ref('')
const branchCache = new Map<string, { at: number, rows: any[] }>()
let navigationSequence = 0
let selectedSequence = 0
let searchSequence = 0
let searchTimer: ReturnType<typeof setTimeout> | null = null

const displayLabel = computed(() => selectedName.value || props.selectedLabel || '')

watch(() => props.modelValue, value => {
    if (!value) {
        selectedName.value = ''
        return
    }
    if (props.selectedLabel) selectedName.value = props.selectedLabel
    else resolveSelected(value)
}, { immediate: true })
watch(() => props.selectedLabel, value => {
    if (value && props.modelValue) selectedName.value = value
})

function cacheKey(params: Record<string, any>) {
    if (params.keyword || params.site_product_id) return ''
    return [params.node_type, params.category_path, params.brand_name, params.series_name].join('|')
}

async function fetchRows(params: Record<string, any>): Promise<any[]> {
    const key = cacheKey(params)
    const cached = key ? branchCache.get(key) : null
    if (cached && Date.now() - cached.at < CACHE_TTL) return cached.rows
    const res: any = await getMobileErpGoodsCatalogHierarchy(params)
    const rows = Array.isArray(res?.data?.list) ? res.data.list : []
    if (key) branchCache.set(key, { at: Date.now(), rows })
    return rows
}

function childParams(node: any) {
    return {
        node_type: node?.node_type || 'root',
        category_path: node?.category_path || '',
        brand_name: node?.brand_name || '',
        series_name: node?.series_name || '',
        limit: 300,
    }
}

async function loadWorkspace(force = false) {
    const sequence = ++navigationSequence
    let completed = false
    workspaceLoading.value = true
    workspaceReady.value = false
    try {
        if (force) branchCache.clear()
        const roots = await fetchRows({ node_type: 'root', limit: 200 })
        if (sequence !== navigationSequence) return
        rootCategories.value = roots
        if (roots.length) await selectRootInternal(roots[0], sequence)
        else clearWorkspaceNodes()
        if (sequence === navigationSequence) completed = true
    } catch (error: any) {
        if (sequence === navigationSequence) {
            clearWorkspaceNodes()
            uni.showToast({ title: error?.message || '商品目录加载失败', icon: 'none' })
        }
    } finally {
        if (sequence === navigationSequence) {
            workspaceLoading.value = false
            workspaceReady.value = completed
        }
    }
}

async function selectRoot(node: any) {
    const sequence = ++navigationSequence
    workspaceLoading.value = true
    try {
        await selectRootInternal(node, sequence)
    } catch (error: any) {
        if (sequence === navigationSequence) {
            workspaceReady.value = false
            uni.showToast({ title: error?.message || '品类加载失败', icon: 'none' })
        }
    } finally {
        if (sequence === navigationSequence) workspaceLoading.value = false
    }
}

async function selectRootInternal(node: any, sequence: number) {
    activeRoot.value = node
    activeCategory.value = node
    activeBrand.value = null
    activeSeries.value = null
    subcategories.value = []
    brands.value = []
    seriesNodes.value = []
    products.value = []
    const children = await fetchRows(childParams(node))
    if (sequence !== navigationSequence) return
    const categoryChildren = children.filter(item => item.node_type === 'category')
    if (categoryChildren.length) {
        subcategories.value = categoryChildren
        await selectCategoryInternal(categoryChildren[0], sequence)
    } else {
        brands.value = children.filter(item => item.node_type === 'brand')
        if (brands.value.length) await selectBrandInternal(brands.value[0], sequence)
    }
}

async function selectCategory(node: any) {
    const sequence = ++navigationSequence
    workspaceLoading.value = true
    try {
        await selectCategoryInternal(node, sequence)
    } catch (error: any) {
        if (sequence === navigationSequence) {
            workspaceReady.value = false
            uni.showToast({ title: error?.message || '子品类加载失败', icon: 'none' })
        }
    } finally {
        if (sequence === navigationSequence) workspaceLoading.value = false
    }
}

async function selectCategoryInternal(node: any, sequence: number) {
    activeCategory.value = node
    activeBrand.value = null
    activeSeries.value = null
    brands.value = []
    seriesNodes.value = []
    products.value = []
    const rows = await fetchRows(childParams(node))
    if (sequence !== navigationSequence) return
    brands.value = rows.filter(item => item.node_type === 'brand')
    if (brands.value.length) await selectBrandInternal(brands.value[0], sequence)
}

async function selectBrand(node: any) {
    const sequence = ++navigationSequence
    workspaceLoading.value = true
    try {
        await selectBrandInternal(node, sequence)
    } catch (error: any) {
        if (sequence === navigationSequence) {
            workspaceReady.value = false
            uni.showToast({ title: error?.message || '品牌加载失败', icon: 'none' })
        }
    } finally {
        if (sequence === navigationSequence) workspaceLoading.value = false
    }
}

async function selectBrandInternal(node: any, sequence: number) {
    activeBrand.value = node
    activeSeries.value = null
    seriesNodes.value = []
    products.value = []
    const rows = await fetchRows(childParams(node))
    if (sequence !== navigationSequence) return
    seriesNodes.value = rows.filter(item => item.node_type === 'series')
    if (seriesNodes.value.length) await selectSeriesInternal(seriesNodes.value[0], sequence)
}

async function selectSeries(node: any) {
    const sequence = ++navigationSequence
    workspaceLoading.value = true
    try {
        await selectSeriesInternal(node, sequence)
    } catch (error: any) {
        if (sequence === navigationSequence) {
            workspaceReady.value = false
            uni.showToast({ title: error?.message || '型号加载失败', icon: 'none' })
        }
    } finally {
        if (sequence === navigationSequence) workspaceLoading.value = false
    }
}

async function selectSeriesInternal(node: any, sequence: number) {
    activeSeries.value = node
    products.value = []
    const rows = await fetchRows(childParams(node))
    if (sequence !== navigationSequence) return
    products.value = rows.filter(item => item.node_type === 'product')
}

function clearWorkspaceNodes() {
    rootCategories.value = []
    subcategories.value = []
    brands.value = []
    seriesNodes.value = []
    products.value = []
    activeRoot.value = null
    activeCategory.value = null
    activeBrand.value = null
    activeSeries.value = null
}

function open() {
    show.value = true
    keyword.value = ''
    searchMode.value = false
    if (!workspaceReady.value) loadWorkspace()
}

function close() {
    if (workspaceLoading.value) workspaceReady.value = false
    navigationSequence++
    searchSequence++
    workspaceLoading.value = false
    searchLoading.value = false
    show.value = false
}

function resetWorkspace() {
    keyword.value = ''
    searchMode.value = false
    loadWorkspace(true)
}

function clearSearch() {
    if (searchTimer) {
        clearTimeout(searchTimer)
        searchTimer = null
    }
    searchSequence++
    keyword.value = ''
    searchMode.value = false
    searchLoading.value = false
    searchResults.value = []
}

async function searchProducts() {
    if (searchTimer) {
        clearTimeout(searchTimer)
        searchTimer = null
    }
    const value = keyword.value.trim()
    if (!value) return clearSearch()
    const sequence = ++searchSequence
    searchMode.value = true
    searchLoading.value = true
    try {
        const rows = await fetchRows({ keyword: value, limit: 80 })
        if (sequence !== searchSequence) return
        searchResults.value = rows.filter(item => item.node_type === 'product')
    } catch (error: any) {
        if (sequence === searchSequence) {
            searchResults.value = []
            uni.showToast({ title: error?.message || '商品目录搜索失败', icon: 'none' })
        }
    } finally {
        if (sequence === searchSequence) searchLoading.value = false
    }
}

function scheduleSearch() {
    if (searchTimer) clearTimeout(searchTimer)
    if (!keyword.value.trim()) return clearSearch()
    searchTimer = setTimeout(searchProducts, 320)
}

async function resolveSelected(value: number | string) {
    const sequence = ++selectedSequence
    try {
        const rows = await fetchRows({ site_product_id: value, limit: 20 })
        if (sequence !== selectedSequence || Number(props.modelValue || 0) !== Number(value)) return
        const row = rows[0]
        if (row) selectedName.value = row.label || row.product_name || ''
    } catch {
        // 反显失败不阻断表单，继续使用外部 selectedLabel。
    }
}

function chooseProduct(node: any) {
    selectedName.value = node.label || ''
    const payload = {
        ...node,
        catalog_product_id: Number(node.site_product_id || 0),
        product_name: node.label || '',
        category_name: String(node.category_path || '').split('/').filter(Boolean).pop() || '',
    }
    emit('update:modelValue', payload.catalog_product_id)
    emit('change', payload)
    close()
}

function clear() {
    selectedSequence++
    selectedName.value = ''
    emit('update:modelValue', '')
    emit('change', { catalog_product_id: 0, site_product_id: 0, product_name: '', category_name: '', category_path: '', brand_name: '', series_name: '' })
    emit('clear')
}

function sameNode(left: any, right: any) {
    return !!left && !!right && String(left.node_key || '') === String(right.node_key || '')
}

function isSelected(node: any) {
    return Number(node.site_product_id || 0) === Number(props.modelValue || 0)
}

onBeforeUnmount(() => {
    navigationSequence++
    selectedSequence++
    searchSequence++
    if (searchTimer) clearTimeout(searchTimer)
})
</script>

<style lang="scss" scoped>
.field { width: 100%; min-height: 88rpx; display: flex; align-items: center; box-sizing: border-box; }
.field--vertical { align-items: stretch; flex-direction: column; gap: 12rpx; }
.field--embedded.field--horizontal { min-height: 92rpx; gap: 16rpx; border-bottom: 2rpx solid #f3f4f6; }
.label { width: 170rpx; flex-shrink: 0; color: #334155; font-size: 28rpx; }
.field--vertical .label { width: auto; font-size: 26rpx; font-weight: 600; }
.field--embedded .label { font-weight: 600; }
.field--embedded.field--horizontal .label { width: 150rpx; font-size: 26rpx; }
.req { margin-right: 6rpx; color: #ef4444; }
.value { flex: 1; min-width: 0; display: flex; align-items: center; justify-content: flex-end; overflow: hidden; color: #0f172a; font-size: 28rpx; }
.field--vertical .value, .field--embedded .value { min-height: 72rpx; padding: 0 18rpx; justify-content: space-between; background: #f8fafc; border: 2rpx solid transparent; border-radius: 12rpx; box-sizing: border-box; }
.field--on .value { background: #f8fbff; border-color: #3b6ef5; }
.value--ph { color: #c4c8cf; }
.truncate { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.clear-icon { padding: 10rpx; color: #94a3b8; font-size: 26rpx; }
.arrow { margin-left: 8rpx; color: #c4c8cf; font-size: 24rpx; }

.popup { height: 90vh; display: flex; flex-direction: column; overflow: hidden; background: #fff; }
.head { flex-shrink: 0; padding: 24rpx 28rpx 14rpx; display: flex; align-items: center; justify-content: space-between; }
.head-close { width: 76rpx; height: 56rpx; display: flex; align-items: center; }
.head-title-wrap { display: flex; flex-direction: column; align-items: center; }
.head-title { color: #0f172a; font-size: 30rpx; font-weight: 700; }
.head-sub { margin-top: 4rpx; color: #94a3b8; font-size: 20rpx; }
.head-reset { width: 76rpx; color: #2563eb; font-size: 24rpx; text-align: right; }
.search { flex-shrink: 0; height: 72rpx; margin: 4rpx 26rpx 14rpx; padding: 0 20rpx; display: flex; align-items: center; gap: 12rpx; background: #f5f7fa; border-radius: 36rpx; }
.search-input { flex: 1; min-width: 0; color: #334155; font-size: 26rpx; }
.search-clear { padding: 6rpx; }
.search-action { padding-left: 16rpx; border-left: 2rpx solid #e2e8f0; color: #2563eb; font-size: 25rpx; font-weight: 600; }

.category-tabs, .subcategory-tabs { flex-shrink: 0; width: 100%; white-space: nowrap; }
.category-tabs { border-bottom: 2rpx solid #e5e7eb; }
.category-tabs__inner, .subcategory-tabs__inner, .series-tabs__inner { display: inline-flex; align-items: center; }
.category-tabs__inner { min-width: 100%; padding: 0 12rpx; box-sizing: border-box; }
.category-tab { position: relative; padding: 20rpx 22rpx 18rpx; color: #475569; font-size: 27rpx; font-weight: 600; }
.category-tab--active { color: #2563eb; }
.category-tab--active::after { position: absolute; right: 24rpx; bottom: 0; left: 24rpx; height: 5rpx; background: #2563eb; border-radius: 6rpx 6rpx 0 0; content: ''; }
.subcategory-tabs { background: #fff; border-bottom: 2rpx solid #eef2f7; }
.subcategory-tabs__inner { padding: 12rpx 18rpx; gap: 12rpx; }
.subcategory-tab { padding: 10rpx 20rpx; color: #64748b; font-size: 24rpx; background: #f8fafc; border: 2rpx solid transparent; border-radius: 10rpx; }
.subcategory-tab--active { color: #2563eb; background: #eff6ff; border-color: #93c5fd; font-weight: 600; }

.workspace { flex: 1; min-height: 0; display: flex; overflow: hidden; background: #fff; }
.brand-rail { width: 184rpx; height: 100%; flex-shrink: 0; background: #f7f8fa; border-right: 2rpx solid #eef2f7; }
.brand-loading { height: 160rpx; display: flex; align-items: center; justify-content: center; }
.brand-item { position: relative; min-height: 92rpx; padding: 18rpx 14rpx 16rpx 26rpx; display: flex; flex-direction: column; justify-content: center; box-sizing: border-box; }
.brand-item--active { background: #fff; }
.brand-item--active::before { position: absolute; top: 20rpx; bottom: 20rpx; left: 0; width: 6rpx; background: #2563eb; border-radius: 0 6rpx 6rpx 0; content: ''; }
.brand-name { overflow: hidden; color: #334155; font-size: 26rpx; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.brand-item--active .brand-name { color: #0f172a; }
.brand-count {position: absolute; top: 20%; right: 16rpx; transform: translateY(-50%);  margin-top: 4rpx; color: #a0aec0; font-size: 19rpx; }
.product-panel { flex: 1; min-width: 0; min-height: 0; display: flex; flex-direction: column; overflow: hidden; }
.series-tabs { flex-shrink: 0; width: 100%; white-space: nowrap; border-bottom: 2rpx solid #f1f5f9; }
.series-tabs__inner { padding: 14rpx 18rpx; gap: 12rpx; }
.series-tab { padding: 10rpx 18rpx; color: #64748b; font-size: 23rpx; background: #f8fafc; border: 2rpx solid transparent; border-radius: 9rpx; }
.series-tab--active { color: #2563eb; background: #fff; border-color: #60a5fa; font-weight: 600; }
.panel-context { flex-shrink: 0; padding: 20rpx 22rpx 12rpx; }
.panel-title-wrap { display: flex; align-items: center; gap: 10rpx; }
.panel-title { color: #0f172a; font-size: 29rpx; font-weight: 700; }
.panel-count { padding: 3rpx 9rpx; color: #2563eb; font-size: 19rpx; background: #eff6ff; border-radius: 12rpx; }
.panel-path { display: block; margin-top: 6rpx; color: #94a3b8; font-size: 21rpx; }
.product-list { flex: 1; min-height: 0; height: 0; padding: 0 20rpx 28rpx; box-sizing: border-box; }
.product-card { position: relative; min-height: 88rpx; margin-bottom: 12rpx; padding: 20rpx 48rpx 20rpx 22rpx; display: flex; align-items: center; background: #f8fafc; border: 2rpx solid transparent; border-radius: 12rpx; box-sizing: border-box; }
.product-card--selected { background: #eff6ff; border-color: #60a5fa; }
.product-name { overflow: hidden; color: #1e293b; font-size: 26rpx; font-weight: 500; text-overflow: ellipsis; white-space: normal; }
.selected-mark { position: absolute; top: 50%; right: 16rpx; width: 34rpx; height: 34rpx; display: flex; align-items: center; justify-content: center; background: #2563eb; border-radius: 50%; transform: translateY(-50%); }

.result-head { flex-shrink: 0; padding: 12rpx 28rpx 16rpx; display: flex; align-items: center; justify-content: space-between; border-bottom: 2rpx solid #eef2f7; }
.result-title { color: #0f172a; font-size: 28rpx; font-weight: 700; }
.result-count { margin-left: 12rpx; color: #94a3b8; font-size: 22rpx; }
.result-back { color: #2563eb; font-size: 24rpx; }
.search-results { flex: 1; min-height: 0; padding: 0 26rpx 30rpx; box-sizing: border-box; }
.search-product { min-height: 106rpx; padding: 18rpx 4rpx; display: flex; align-items: center; gap: 16rpx; border-bottom: 2rpx solid #f1f5f9; box-sizing: border-box; }
.search-product--selected { background: #f8fbff; }
.product-copy { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.product-path { margin-top: 7rpx; overflow: hidden; color: #94a3b8; font-size: 21rpx; text-overflow: ellipsis; white-space: nowrap; }
.empty { height: 400rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18rpx; color: #94a3b8; font-size: 24rpx; }
.empty--workspace { height: 330rpx; }
</style>
