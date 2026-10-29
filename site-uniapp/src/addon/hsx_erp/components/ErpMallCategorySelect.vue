<template>
    <view class="mall-category">
        <view class="mall-category__entry" @click="open">
            <text class="mall-category__label">商城分类 *</text>
            <text class="mall-category__value" :class="{ placeholder: !selected }">{{ selected || '请选择商城分类' }}</text>
            <u-icon name="arrow-right" size="14" color="#94a3b8" />
        </view>
        <u-popup :show="show" mode="bottom" round="20" :z-index="10100" :safe-area-inset-bottom="true" @close="close">
            <view v-if="show" class="category-picker" @touchmove.stop.prevent="noop">
                <view class="category-picker__head">
                    <view class="category-picker__heading"><text class="category-picker__title">选择商城分类</text><text class="category-picker__subtitle">逐级筛选，或直接搜索具体型号</text></view>
                    <view class="category-picker__close" role="button" aria-label="关闭分类选择" @click="close"><u-icon name="close" size="21" color="#64748b" /></view>
                </view>
                <view class="category-picker__search-row">
                    <view class="category-picker__search-box">
                        <u-icon name="search" size="18" color="#94a3b8" />
                        <input v-model="keyword" class="category-picker__search-input" placeholder="搜索型号、品牌或系列" confirm-type="search" :maxlength="120" @confirm="submitSearch" />
                        <view v-if="keyword" class="category-picker__search-clear" role="button" aria-label="清空搜索" @click="clearSearch"><u-icon name="close-circle-fill" size="17" color="#94a3b8" /></view>
                    </view>
                    <view class="category-picker__search-action" role="button" @click="submitSearch">搜索</view>
                </view>
                <view v-if="invalidSelected" class="category-picker__warning">原分类已停用、删除或资料异常，请重新选择。</view>
                <view v-else-if="incompleteCatalog" class="category-picker__warning">部分分类资料异常已隐藏，请联系商城管理员核对。</view>
                <view v-if="searching" class="category-picker__scope">
                    <view class="category-picker__scope-item" :class="{ 'is-active': searchScope === 'all' }" @click="setSearchScope('all')">全部分类</view>
                    <view v-if="activeParentId" class="category-picker__scope-item" :class="{ 'is-active': searchScope === 'branch' }" @click="setSearchScope('branch')">仅当前分支</view>
                    <text class="category-picker__count">{{ searchResults.length }} 个结果</text>
                </view>
                <scroll-view v-else scroll-x :show-scrollbar="false" class="category-picker__breadcrumb" @touchmove.stop="noop">
                    <view class="category-picker__breadcrumb-inner">
                        <text class="category-picker__crumb" :class="{ 'is-current': !activeParentId }" @click="navigateTo(0)">全部分类</text>
                        <template v-for="node in breadcrumbTrail" :key="node.id"><text class="category-picker__crumb-separator">/</text><text class="category-picker__crumb" :class="{ 'is-current': node.id === activeParentId }" @click="navigateTo(node.id)">{{ node.name }}</text></template>
                    </view>
                </scroll-view>
                <view class="category-picker__body">
                    <view v-if="loading" class="category-picker__empty"><text class="category-picker__empty-title">正在加载商城分类…</text><text>请稍候，加载后即可选择</text></view>
                    <view v-else-if="error" class="category-picker__empty"><text class="category-picker__empty-title">分类暂时加载失败</text><text>{{ error }}</text><view class="category-picker__retry" role="button" @click="load(false)">重新加载</view></view>
                    <view v-else-if="!options.length" class="category-picker__empty"><text class="category-picker__empty-title">商城还没有可选分类</text><text>请先在商城创建并启用分类，再刷新。</text><view class="category-picker__retry" @click="load(false)">刷新分类</view></view>
                    <scroll-view v-else-if="searching" :key="searchListKey" scroll-y :show-scrollbar="false" class="category-picker__results" @scrolltolower="loadMore" @touchmove.stop="noop">
                        <view v-if="searchScope === 'branch'" class="category-picker__scope-note">在 {{ currentBranchName }} 中搜索</view>
                        <view v-for="item in visibleSearchResults" :id="'mall-category-leaf-' + item.value" :key="item.value" class="category-picker__result" :class="{ 'is-selected': Number(modelValue) === item.value }" @click="choose(item)">
                            <view class="category-picker__result-copy"><text class="category-picker__result-title">{{ item.names[item.names.length - 1] }}</text><text class="category-picker__result-path">{{ item.names.slice(0, -1).join(' / ') || '商城分类' }}</text></view>
                            <view v-if="Number(modelValue) === item.value" class="category-picker__selected-mark"><u-icon name="checkmark-circle-fill" color="#2563eb" size="19" /><text>已选</text></view>
                        </view>
                        <view v-if="!searchResults.length" class="category-picker__empty category-picker__empty--search"><text class="category-picker__empty-title">没有找到匹配分类</text><text>试试简短型号，如“Mate80 Pro”或“iPhone16”。</text><view v-if="searchScope === 'branch'" class="category-picker__retry" @click="setSearchScope('all')">改为搜索全部分类</view><view v-else class="category-picker__retry" @click="clearSearch">清空搜索，按层级选择</view></view>
                        <view v-else class="category-picker__more" :class="{ 'is-clickable': hasMore }" @click="loadMore">{{ hasMore ? '继续加载（已显示 ' + visibleSearchResults.length + ' / ' + searchResults.length + '）' : '共 ' + searchResults.length + ' 个匹配分类' }}</view>
                    </scroll-view>
                    <view v-else class="category-picker__workspace">
                        <scroll-view v-if="activeRootId" scroll-y :show-scrollbar="false" :scroll-into-view="'mall-category-root-' + activeRootId" class="category-picker__roots" @touchmove.stop="noop">
                            <view v-for="node in rootNodes" :id="'mall-category-root-' + node.id" :key="node.id" class="category-picker__root" :class="{ 'is-active': activeRootId === node.id }" @click="selectRoot(node.id)"><text>{{ node.name }}</text><text class="category-picker__root-count">{{ node.leafCount }}</text></view>
                        </scroll-view>
                        <view class="category-picker__panel">
                            <view class="category-picker__context"><text>{{ activeParent?.name || '全部分类' }}</text><text class="category-picker__context-count">{{ activeParent ? activeParent.leafCount + ' 个末级分类' : rootNodes.length + ' 个分类分组' }}</text></view>
                            <scroll-view :key="'browse-' + activeParentId" scroll-y :show-scrollbar="false" :scroll-into-view="scrollTarget" class="category-picker__tree-list" @scrolltolower="loadMore" @touchmove.stop="noop">
                                <view class="category-picker__nodes" :class="{ 'category-picker__nodes--grid': !activeParentId }">
                                    <view v-for="node in visibleBrowseRows" :id="'mall-category-leaf-' + node.id" :key="node.id" class="category-picker__node" :class="{ 'is-selected': Number(modelValue) === node.id && !!node.option }" @click="enterNode(node)">
                                        <view class="category-picker__node-copy"><text class="category-picker__node-name">{{ node.name }}</text><text v-if="!node.option" class="category-picker__node-count">{{ node.leafCount }} 个分类</text></view>
                                        <view v-if="Number(modelValue) === node.id && node.option" class="category-picker__selected-mark"><u-icon name="checkmark-circle-fill" color="#2563eb" size="19" /><text>已选</text></view><u-icon v-else-if="node.children.length > 0" name="arrow-right" size="13" color="#cbd5e1" />
                                    </view>
                                </view>
                                <view v-if="hasMore" class="category-picker__more is-clickable" @click="loadMore">继续加载（已显示 {{ visibleBrowseRows.length }} / {{ browseRows.length }}）</view><view v-else class="category-picker__more">{{ activeParentId ? '点击具体分类即可选中' : '先选择分类分组，再逐级缩小范围' }}</view>
                            </scroll-view>
                        </view>
                    </view>
                </view>
                <view class="category-picker__foot">
                    <view class="category-picker__current"><text class="category-picker__current-label">当前选择</text><text class="category-picker__current-value">{{ selected || '尚未选择' }}</text></view>
                    <view class="category-picker__foot-actions"><text v-if="selectedOption && !loading && !error" class="category-picker__locate" @click="locateSelected">定位已选</text><text class="category-picker__refresh" :class="{ 'is-disabled': loading }" @click="load(false)">{{ loading ? '加载中…' : '刷新' }}</text></view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { getErpListingCatalog } from '@/addon/hsx_erp/api/erp'
import { buildMallCategoryIndex, getMallCategoryBrowseRows, getMallCategoryParentPath, getMallCategoryTrail, normalizeCategorySearch, searchMallCategories, type MallCategoryNode, type MallCategoryOption } from '@/addon/hsx_erp/hooks/mallCategoryPicker'

const props = defineProps<{ modelValue: number; selectedLabel?: string }>()
const emit = defineEmits(['update:modelValue', 'change'])
const PAGE_SIZE = 40
const show = ref(false)
const loading = ref(false)
const error = ref('')
const loaded = ref(false)
const incompleteCatalog = ref(false)
const keyword = ref('')
const searchScope = ref<'all' | 'branch'>('all')
const options = ref<MallCategoryOption[]>([])
const activeRootId = ref(0)
const activeParentId = ref(0)
const visibleCount = ref(PAGE_SIZE)
const scrollTarget = ref('')
let requestSeq = 0
let navigationSeq = 0
let disposed = false

const categoryIndex = computed(() => buildMallCategoryIndex(options.value))
const rootNodes = computed(() => categoryIndex.value.roots.map(id => categoryIndex.value.nodes.get(id)!))
const activeParent = computed(() => categoryIndex.value.nodes.get(activeParentId.value))
const breadcrumbTrail = computed(() => getMallCategoryTrail(categoryIndex.value, activeParentId.value))
const selectedOption = computed(() => categoryIndex.value.nodes.get(Number(props.modelValue))?.option)
const selected = computed(() => selectedOption.value?.label || props.selectedLabel || '')
const invalidSelected = computed(() => loaded.value && Number(props.modelValue) > 0 && !selectedOption.value)
const searching = computed(() => normalizeCategorySearch(keyword.value) !== '')
const currentBranchName = computed(() => activeParent.value?.names.join(' / ') || '全部分类')
const searchResults = computed(() => searchMallCategories(categoryIndex.value, keyword.value, searchScope.value === 'branch' ? activeParentId.value : 0))
const browseRows = computed(() => activeParent.value?.option ? [activeParent.value] : getMallCategoryBrowseRows(categoryIndex.value, activeParentId.value))
const visibleSearchResults = computed(() => searchResults.value.slice(0, visibleCount.value))
const visibleBrowseRows = computed(() => browseRows.value.slice(0, visibleCount.value))
const hasMore = computed(() => visibleCount.value < (searching.value ? searchResults.value.length : browseRows.value.length))
const searchListKey = computed(() => 'search-' + normalizeCategorySearch(keyword.value) + '-' + searchScope.value + '-' + activeParentId.value)

function noop() {}
function resetList() { visibleCount.value = PAGE_SIZE; scrollTarget.value = '' }
function close() { show.value = false; requestSeq++; loading.value = false }
function navigateTo(id: number) {
    if (id !== 0 && !categoryIndex.value.nodes.has(id)) return
    navigationSeq++
    activeParentId.value = id
    activeRootId.value = categoryIndex.value.nodes.get(id)?.path[0] || 0
    resetList()
}
function selectRoot(id: number) { if (categoryIndex.value.roots.includes(id)) navigateTo(id) }
function enterNode(node: MallCategoryNode) {
    if (loading.value || error.value) return
    const current = categoryIndex.value.nodes.get(Number(node?.id))
    if (!current) return
    if (current.option) choose(current.option)
    else navigateTo(current.id)
}
function choose(item: MallCategoryOption) {
    if (!show.value || loading.value || error.value) return
    const option = categoryIndex.value.nodes.get(Number(item?.value))?.option
    if (!option) return
    emit('update:modelValue', option.value)
    emit('change', option)
    close()
}
async function locateSelected() {
    const item = selectedOption.value
    if (!item) { navigateTo(0); return }
    keyword.value = ''; searchScope.value = 'all'
    const parentPath = getMallCategoryParentPath(categoryIndex.value, item.value)
    navigateTo(parentPath[parentPath.length - 1] || item.value)
    const navigation = navigationSeq
    // 搜索清空会触发 watch 重置页数，先等它完成，再展开到已选项所在页。
    await nextTick()
    if (disposed || !show.value || navigation !== navigationSeq || selectedOption.value?.value !== item.value || searching.value) return
    const position = browseRows.value.findIndex(row => row.id === item.value)
    visibleCount.value = Math.max(PAGE_SIZE, Math.ceil((position + 1) / PAGE_SIZE) * PAGE_SIZE)
    await nextTick()
    if (!disposed && show.value && navigation === navigationSeq && selectedOption.value?.value === item.value && !searching.value) scrollTarget.value = 'mall-category-leaf-' + item.value
}
function loadMore() { if (hasMore.value) visibleCount.value += PAGE_SIZE }
function clearSearch() { keyword.value = ''; resetList() }
function setSearchScope(scope: 'all' | 'branch') { searchScope.value = scope === 'branch' && activeParentId.value ? 'branch' : 'all'; resetList() }
function submitSearch() { resetList(); if (typeof uni !== 'undefined' && typeof uni.hideKeyboard === 'function') uni.hideKeyboard() }

async function load(relocate = false) {
    if (loading.value) return
    const sequence = ++requestSeq
    loading.value = true; error.value = ''
    try {
        const response: any = await getErpListingCatalog()
        if (disposed || sequence !== requestSeq) return
        const source = response?.data?.options
        if (!Array.isArray(source)) throw new Error('分类数据不完整，请确认 ERP 与商城后端均已更新')
        const normalized = buildMallCategoryIndex(source)
        if (source.length > 0 && normalized.options.length === 0) throw new Error('商城分类资料异常，请联系商城管理员核对')
        options.value = normalized.options
        incompleteCatalog.value = normalized.options.length < source.length
        loaded.value = true
        resetList()
        if (relocate && selectedOption.value) await locateSelected()
        else if (activeParentId.value && !categoryIndex.value.nodes.has(activeParentId.value)) navigateTo(0)
    } catch (e: any) {
        if (!disposed && sequence === requestSeq) error.value = e?.message || e?.msg || '商城分类加载失败，请稍后重试'
    } finally { if (!disposed && sequence === requestSeq) loading.value = false }
}
function open() {
    show.value = true; keyword.value = ''; searchScope.value = 'all'; resetList()
    if (selectedOption.value) locateSelected()
    else navigateTo(0)
    void load(true)
}
watch(() => normalizeCategorySearch(keyword.value) + '|' + searchScope.value, resetList)
onBeforeUnmount(() => { disposed = true; requestSeq++ })
defineExpose({ open, close })
</script>

<style scoped lang="scss">
.mall-category{margin:20rpx 0;padding:20rpx;border:1rpx solid #e2e8f0;border-radius:16rpx;background:#fff;min-width:0}
.mall-category__entry{display:flex;align-items:center;gap:16rpx;min-height:60rpx}.mall-category__label{flex-shrink:0;font-size:28rpx;color:#334155;font-weight:600}.mall-category__value{flex:1;min-width:0;text-align:right;font-size:26rpx;color:#0f172a;line-height:1.5;word-break:break-word}.placeholder{color:#94a3b8}.mall-category__hint{display:block;margin-top:10rpx;font-size:23rpx;color:#64748b}
.category-picker{display:flex;flex-direction:column;height:82vh;max-height:calc(100vh - 96rpx);overflow:hidden;box-sizing:border-box;background:#fff;border-radius:24rpx 24rpx 0 0;color:#0f172a}
.category-picker__head{display:flex;align-items:center;justify-content:space-between;flex-shrink:0;padding:24rpx 20rpx 12rpx 28rpx}.category-picker__heading{display:flex;flex-direction:column;gap:6rpx;min-width:0}.category-picker__title{font-size:17px;font-weight:650;line-height:1.4}.category-picker__subtitle{font-size:12px;line-height:1.5;color:#64748b}.category-picker__close{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:88rpx;height:88rpx}
.category-picker__search-row{display:flex;align-items:center;gap:12rpx;padding:0 24rpx 20rpx;flex-shrink:0}.category-picker__search-box{display:flex;align-items:center;gap:14rpx;flex:1;min-width:0;height:80rpx;padding:0 20rpx;border-radius:12rpx;background:#f1f5f9}.category-picker__search-input{flex:1;min-width:0;height:80rpx;font-size:14px;color:#0f172a}.category-picker__search-clear{display:flex;align-items:center;justify-content:center;min-width:52rpx;height:80rpx}.category-picker__search-action{display:flex;align-items:center;justify-content:center;min-width:88rpx;height:88rpx;color:#2563eb;font-size:14px;font-weight:500}
.category-picker__warning{flex-shrink:0;margin:0 24rpx 12rpx;padding:12rpx 16rpx;background:#fffbeb;color:#92400e;font-size:12px;line-height:1.5;border-radius:8rpx}
.category-picker__breadcrumb{flex-shrink:0;white-space:nowrap;border-top:1rpx solid #f1f5f9;border-bottom:1rpx solid #e2e8f0}.category-picker__breadcrumb-inner{display:inline-flex;align-items:center;gap:16rpx;min-height:84rpx;padding:0 28rpx}.category-picker__crumb{font-size:13px;line-height:1.5;color:#2563eb;padding:18rpx 0}.category-picker__crumb.is-current{font-weight:600;color:#0f172a}.category-picker__crumb-separator{color:#cbd5e1;font-size:12px}
.category-picker__scope{display:flex;align-items:center;gap:12rpx;padding:0 24rpx 16rpx;flex-shrink:0;border-bottom:1rpx solid #e2e8f0}.category-picker__scope-item{padding:12rpx 18rpx;border:1rpx solid #e2e8f0;border-radius:8rpx;font-size:12px;color:#475569}.category-picker__scope-item.is-active{background:#eff6ff;border-color:#bfdbfe;color:#2563eb;font-weight:600}.category-picker__count{margin-left:auto;font-size:12px;color:#64748b}.category-picker__scope-note{padding:14rpx 4rpx;font-size:12px;color:#64748b}
.category-picker__body{display:flex;flex:1;min-height:0;overflow:hidden}.category-picker__workspace{display:flex;flex:1;min-width:0;min-height:0}.category-picker__roots{width:172rpx;flex-shrink:0;height:100%;background:#f5f7fa}.category-picker__root{position:relative;display:flex;flex-direction:column;justify-content:center;gap:4rpx;min-height:108rpx;padding:16rpx 20rpx;box-sizing:border-box;font-size:14px;line-height:1.45;word-break:break-word;color:#475569}.category-picker__root.is-active{color:#2563eb;background:#fff;font-weight:600}.category-picker__root.is-active::before{position:absolute;content:'';left:0;top:30%;height:40%;width:6rpx;border-radius:0 4rpx 4rpx 0;background:#2563eb}.category-picker__root-count{font-size:11px;line-height:1.4;font-weight:400;color:#64748b}
.category-picker__panel{display:flex;flex-direction:column;flex:1;min-height:0;min-width:0}.category-picker__context{display:flex;align-items:baseline;flex-wrap:wrap;gap:8rpx 16rpx;padding:20rpx 24rpx 10rpx;font-size:14px;font-weight:600;line-height:1.5;flex-shrink:0;word-break:break-word}.category-picker__context-count{font-size:12px;color:#64748b;font-weight:400}.category-picker__tree-list{flex:1;min-height:0;height:100%;box-sizing:border-box}.category-picker__nodes{padding:0 24rpx}.category-picker__nodes--grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16rpx;padding:16rpx 24rpx}.category-picker__node{display:flex;align-items:center;gap:12rpx;min-height:100rpx;padding:20rpx 0;box-sizing:border-box;border-bottom:1rpx solid #eef2f6}.category-picker__nodes--grid .category-picker__node{padding:20rpx;min-height:112rpx;border:1rpx solid #e2e8f0;border-radius:12rpx}.category-picker__node-copy{flex:1;min-width:0}.category-picker__node-name{display:block;font-size:14px;line-height:1.5;word-break:break-word}.category-picker__node-count{display:block;margin-top:5rpx;color:#64748b;font-size:12px}.category-picker__node.is-selected .category-picker__node-name{color:#2563eb;font-weight:600}.category-picker__node.is-selected{background:#f5f9ff}
.category-picker__results{flex:1;min-width:0;min-height:0;height:100%;padding:0 24rpx;box-sizing:border-box}.category-picker__result{display:flex;align-items:center;gap:16rpx;min-height:116rpx;padding:20rpx 4rpx;border-bottom:1rpx solid #eef2f6;box-sizing:border-box}.category-picker__result-copy{flex:1;min-width:0}.category-picker__result-title{display:block;font-size:15px;line-height:1.5;word-break:break-word}.category-picker__result-path{display:block;margin-top:5rpx;font-size:12px;color:#64748b;line-height:1.5;word-break:break-word}.category-picker__result.is-selected .category-picker__result-title{color:#2563eb;font-weight:600}.category-picker__selected-mark{display:flex;align-items:center;gap:6rpx;flex-shrink:0;font-size:12px;color:#2563eb}
.category-picker__empty{display:flex;flex:1;min-width:0;flex-direction:column;align-items:center;justify-content:center;gap:14rpx;padding:40rpx 32rpx;text-align:center;color:#64748b;font-size:13px;line-height:1.6;box-sizing:border-box}.category-picker__empty-title{color:#334155;font-size:15px;font-weight:600}.category-picker__empty--search{min-height:320rpx}.category-picker__retry{margin-top:14rpx;min-height:80rpx;display:flex;align-items:center;justify-content:center;padding:0 24rpx;border:1rpx solid #cbd5e1;border-radius:10rpx;color:#2563eb;font-size:14px}.category-picker__more{text-align:center;padding:24rpx 16rpx;font-size:12px;line-height:1.5;color:#64748b}.category-picker__more.is-clickable{color:#2563eb}
.category-picker__foot{display:flex;align-items:center;gap:16rpx;padding:18rpx 24rpx;border-top:1rpx solid #e2e8f0;background:#fff;flex-shrink:0;min-height:100rpx;box-sizing:border-box}.category-picker__current{flex:1;min-width:0}.category-picker__current-label{display:block;font-size:11px;color:#64748b;margin-bottom:4rpx}.category-picker__current-value{display:block;font-size:12px;line-height:1.5;max-height:72rpx;overflow:hidden;word-break:break-word;color:#334155}.category-picker__foot-actions{display:flex;align-items:center;gap:20rpx;flex-shrink:0;font-size:12px}.category-picker__locate,.category-picker__refresh{padding:20rpx 0;color:#2563eb}.category-picker__refresh{color:#64748b}.is-disabled{opacity:.5}
.category-picker__search-input{border:0;outline:0;padding:0;background:transparent;box-sizing:border-box}
.category-picker__close,.category-picker__search-action{min-width:44px;min-height:44px}
.category-picker__scope-item{display:flex;align-items:center;min-height:44px;box-sizing:border-box}
.category-picker__retry{min-height:44px}.category-picker__locate,.category-picker__refresh{display:flex;align-items:center;min-height:44px;box-sizing:border-box}
</style>
