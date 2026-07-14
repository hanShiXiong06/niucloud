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
                    <text class="head-btn" @click="close">取消</text>
                    <view class="head-title-wrap">
                        <text class="head-title">选择商品型号</text>
                        <text class="head-sub">品类 · 品牌 · 系列 · 型号</text>
                    </view>
                    <text class="head-btn head-btn--ghost">取消</text>
                </view>

                <view class="search">
                    <u-icon name="search" color="#94a3b8" size="18" />
                    <input v-model="keyword" class="search-input" placeholder="搜索品类、品牌、系列或型号" confirm-type="search" @input="scheduleSearch" @confirm="searchProducts" />
                    <view v-if="keyword" class="search-clear" @click="keyword = ''; resetTree()"><u-icon name="close-circle-fill" color="#cbd5e1" size="17" /></view>
                    <text class="search-action" @click="searchProducts">搜索</text>
                </view>

                <scroll-view v-if="!searchMode && breadcrumbs.length" scroll-x class="crumbs" :show-scrollbar="false">
                    <view class="crumbs-inner">
                        <view class="crumb" @click="resetTree">全部</view>
                        <template v-for="(item, index) in breadcrumbs" :key="item.node_key">
                            <u-icon name="arrow-right" color="#cbd5e1" size="12" />
                            <view class="crumb" :class="{ 'crumb--active': index === breadcrumbs.length - 1 }" @click="backTo(index)">{{ item.label }}</view>
                        </template>
                    </view>
                </scroll-view>

                <view class="level-tip">
                    <text>{{ searchMode ? `搜索结果 ${nodes.length} 项` : levelTitle }}</text>
                    <text v-if="!searchMode" class="level-step">{{ levelStep }}</text>
                </view>

                <scroll-view scroll-y class="list">
                    <view v-if="loading" class="empty"><u-loading-icon size="28" /><text>正在加载商品目录</text></view>
                    <view v-else-if="!nodes.length" class="empty"><u-empty mode="search" text="没有匹配的商品型号" /></view>
                    <view v-for="node in nodes" v-else :key="node.node_key" class="node" :class="{ 'node--selected': Number(node.site_product_id) === Number(modelValue) }" @click="chooseNode(node)">
                        <view class="node-icon" :class="`node-icon--${node.node_type}`">
                            <u-icon :name="node.node_type === 'product' ? 'phone' : 'folder'" :color="node.node_type === 'product' ? '#2563eb' : '#64748b'" size="18" />
                        </view>
                        <view class="node-main">
                            <text class="node-name">{{ node.label }}</text>
                            <text class="node-desc">{{ nodeDescription(node) }}</text>
                        </view>
                        <u-icon v-if="node.node_type === 'product' && Number(node.site_product_id) === Number(modelValue)" name="checkbox-mark" color="#2563eb" size="20" />
                        <view v-else-if="node.node_type !== 'product'" class="node-count">
                            <text>{{ node.product_count || 0 }}</text><u-icon name="arrow-right" color="#94a3b8" size="14" />
                        </view>
                    </view>
                </scroll-view>
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

const show = ref(false)
const loading = ref(false)
const keyword = ref('')
const searchMode = ref(false)
const nodes = ref<any[]>([])
const breadcrumbs = ref<any[]>([])
const selectedName = ref('')
const branchCache = new Map<string, { at: number, rows: any[] }>()
const CACHE_TTL = 60 * 1000
let loadSequence = 0
let selectedSequence = 0
let searchTimer: ReturnType<typeof setTimeout> | null = null

const displayLabel = computed(() => selectedName.value || props.selectedLabel || '')
const currentNode = computed(() => breadcrumbs.value[breadcrumbs.value.length - 1] || null)
const levelTitle = computed(() => {
    const type = currentNode.value?.node_type || 'root'
    return ({ root: '选择一级品类', category: '选择子品类或品牌', brand: '选择系列', series: '选择型号' } as any)[type] || '选择商品型号'
})
const levelStep = computed(() => `${Math.min(5, breadcrumbs.value.length + 1)}/5`)

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

function requestParams() {
    const node = currentNode.value
    if (!node) return { node_type: 'root', limit: 200 }
    return {
        node_type: node.node_type,
        category_path: node.category_path || '',
        brand_name: node.brand_name || '',
        series_name: node.series_name || '',
        limit: 200,
    }
}

function branchKey(params: Record<string, any>) {
    if (params.keyword || params.site_product_id) return ''
    return [params.node_type, params.category_path, params.brand_name, params.series_name].join('|')
}

async function load(params: Record<string, any>) {
    const sequence = ++loadSequence
    const key = branchKey(params)
    const cached = key ? branchCache.get(key) : null
    if (cached && Date.now() - cached.at < CACHE_TTL) {
        nodes.value = cached.rows
        loading.value = false
        return
    }
    loading.value = true
    try {
        const res: any = await getMobileErpGoodsCatalogHierarchy(params)
        if (sequence !== loadSequence) return
        const rows = Array.isArray(res?.data?.list) ? res.data.list : []
        if (key) branchCache.set(key, { at: Date.now(), rows })
        nodes.value = rows
    } catch (error: any) {
        if (sequence !== loadSequence) return
        nodes.value = []
        uni.showToast({ title: error?.message || '商品目录加载失败', icon: 'none' })
    } finally {
        if (sequence === loadSequence) loading.value = false
    }
}

async function resolveSelected(value: number | string) {
    const sequence = ++selectedSequence
    try {
        const res: any = await getMobileErpGoodsCatalogHierarchy({ site_product_id: value, limit: 20 })
        if (sequence !== selectedSequence || Number(props.modelValue || 0) !== Number(value)) return
        const row = res?.data?.list?.[0]
        if (row) selectedName.value = row.label || row.product_name || ''
    } catch {
        // 反显失败不阻断表单，保留外部传入的 selectedLabel。
    }
}

function open() {
    show.value = true
    keyword.value = ''
    resetTree()
}
function close() { show.value = false }
function resetTree() {
    if (searchTimer) {
        clearTimeout(searchTimer)
        searchTimer = null
    }
    searchMode.value = false
    breadcrumbs.value = []
    load({ node_type: 'root', limit: 200 })
}
function chooseNode(node: any) {
    if (node.node_type === 'product') {
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
        return
    }
    searchMode.value = false
    breadcrumbs.value.push(node)
    load(requestParams())
}
function backTo(index: number) {
    breadcrumbs.value = breadcrumbs.value.slice(0, index + 1)
    searchMode.value = false
    load(requestParams())
}
function searchProducts() {
    if (searchTimer) {
        clearTimeout(searchTimer)
        searchTimer = null
    }
    const value = keyword.value.trim()
    if (!value) return resetTree()
    searchMode.value = true
    load({ keyword: value, limit: 50 })
}
function scheduleSearch() {
    if (searchTimer) clearTimeout(searchTimer)
    if (!keyword.value.trim()) return
    searchMode.value = true
    searchTimer = setTimeout(searchProducts, 320)
}
function clear() {
    selectedSequence++
    selectedName.value = ''
    emit('update:modelValue', '')
    emit('change', { catalog_product_id: 0, site_product_id: 0, product_name: '', category_name: '', category_path: '', brand_name: '', series_name: '' })
    emit('clear')
}
function nodeDescription(node: any) {
    if (node.node_type === 'product') return node.path_text || [node.category_path, node.brand_name, node.series_name].filter(Boolean).join(' / ')
    return `${node.product_count || 0} 个型号`
}

onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer) })
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
.popup { height: 78vh; display: flex; flex-direction: column; background: #fff; }
.head { padding: 24rpx 30rpx 16rpx; display: flex; align-items: center; justify-content: space-between; }
.head-btn { width: 80rpx; color: #64748b; font-size: 27rpx; }
.head-btn--ghost { opacity: 0; }
.head-title-wrap { display: flex; flex-direction: column; align-items: center; }
.head-title { color: #0f172a; font-size: 30rpx; font-weight: 700; }
.head-sub { margin-top: 4rpx; color: #94a3b8; font-size: 20rpx; }
.search { height: 68rpx; margin: 4rpx 30rpx 14rpx; padding: 0 20rpx; display: flex; align-items: center; gap: 12rpx; background: #f5f7fa; border-radius: 34rpx; }
.search-input { flex: 1; min-width: 0; color: #334155; font-size: 26rpx; }
.search-clear { padding: 6rpx; }
.search-action { padding-left: 16rpx; border-left: 2rpx solid #e2e8f0; color: #2563eb; font-size: 25rpx; font-weight: 600; }
.crumbs { flex-shrink: 0; width: 100%; white-space: nowrap; border-bottom: 2rpx solid #f1f5f9; }
.crumbs-inner { min-height: 68rpx; padding: 0 30rpx; display: inline-flex; align-items: center; gap: 8rpx; }
.crumb { color: #64748b; font-size: 24rpx; }
.crumb--active { color: #2563eb; font-weight: 600; }
.level-tip { min-height: 64rpx; padding: 0 30rpx; display: flex; align-items: center; justify-content: space-between; color: #334155; font-size: 25rpx; font-weight: 600; }
.level-step { color: #94a3b8; font-size: 22rpx; font-weight: 400; }
.list { flex: 1; padding: 0 30rpx 30rpx; box-sizing: border-box; }
.node { min-height: 102rpx; padding: 18rpx 4rpx; display: flex; align-items: center; gap: 18rpx; border-bottom: 2rpx solid #f1f5f9; box-sizing: border-box; }
.node--selected { background: #f8fbff; }
.node-icon { width: 64rpx; height: 64rpx; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border-radius: 16rpx; }
.node-icon--product { background: #eff6ff; }
.node-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.node-name { overflow: hidden; color: #0f172a; font-size: 28rpx; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.node-desc { margin-top: 6rpx; overflow: hidden; color: #94a3b8; font-size: 22rpx; text-overflow: ellipsis; white-space: nowrap; }
.node-count { display: flex; align-items: center; gap: 8rpx; color: #94a3b8; font-size: 22rpx; }
.empty { height: 400rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18rpx; color: #94a3b8; font-size: 24rpx; }
</style>
