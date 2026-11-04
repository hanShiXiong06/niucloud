<template>
    <CoopTaskSheet :show="show" :title="title" :inactive="true" full @close="cancel">
        <view class="catalog-picker">
            <view class="catalog-search">
                <CooperationSearch v-model="keyword" :placeholder="selectedView ? '搜索已选型号' : '搜索品牌或型号'" :show-action="false" compact />
                <button class="catalog-selected-toggle" :class="{ active: selectedView }" @tap="toggleSelectedView">{{ selectedView ? '按分类选' : `已选 ${chosen.length}` }}</button>
            </view>
            <CoopState v-if="loading || loadError" :loading="loading" :error="loadError" @retry="load" />
            <template v-else>
                <scroll-view v-if="!query && !selectedView" scroll-x class="catalog-categories" :show-scrollbar="false">
                    <view class="catalog-category-tabs" role="tablist" aria-label="设备品类">
                        <view v-for="item in categories" :key="item.key" role="tab" :aria-selected="activeCategory === item.key" :class="{ active: activeCategory === item.key }" @tap="selectCategory(item.key)">
                            <text>{{ item.name }}</text><text class="catalog-category-count">{{ categoryCount(item.key) }} / {{ item.count }}</text>
                        </view>
                    </view>
                </scroll-view>
                <view class="catalog-scope"><text>{{ scopeLabel }}</text><text>{{ visibleRows.length }} 款</text></view>
                <view class="catalog-bulk">
                    <view v-if="!selectedView" class="catalog-select-all" role="checkbox" :aria-label="query ? '全选当前搜索结果' : '全选当前范围'" :aria-checked="rangeState.indeterminate ? 'mixed' : rangeState.checked" :aria-disabled="!visibleRows.length" @tap="batch('toggle_all')">
                        <view class="catalog-check" :class="{ checked: rangeState.checked, partial: rangeState.indeterminate }"><text v-if="rangeState.checked">✓</text><text v-else-if="rangeState.indeterminate">−</text></view><text>全选当前{{ query ? '结果' : '范围' }}</text>
                    </view>
                    <text v-else class="catalog-selected-label">已选 {{ chosen.length }} / {{ max }} 款</text>
                    <view class="catalog-bulk-tools"><button v-if="!selectedView" :disabled="!visibleRows.length" @tap="batch('invert')">反选</button><button :disabled="!rangeState.count" @tap="batch('clear')">{{ selectedView && !query ? '清空已选' : '清空' }}</button></view>
                </view>
                <view class="catalog-columns">
                    <scroll-view v-if="!query && !selectedView" scroll-y class="catalog-brands" :show-scrollbar="false">
                        <view class="catalog-brand" role="button" :aria-pressed="!activeBrand" :class="{ active: !activeBrand }" @tap="activeBrand = ''"><text>全部品牌</text><text>{{ categoryCount(activeCategory) }} / {{ categoryRows.length }}</text></view>
                        <view v-for="item in activeGroup?.brands || []" :key="item.key" class="catalog-brand" role="button" :aria-label="`查看${item.name}型号`" :aria-pressed="activeBrand === item.key" :class="{ active: activeBrand === item.key }" @tap="activeBrand = item.key"><text>{{ item.name }}</text><text>{{ brandCount(item.key) }} / {{ item.count }}</text></view>
                    </scroll-view>
                    <scroll-view :key="modelPaneKey" scroll-y class="catalog-models" :show-scrollbar="false">
                        <view v-for="item in visibleRows" :key="item.id" class="catalog-model" role="checkbox" :aria-label="item.title" :aria-checked="selectedIds.has(item.id)" @tap="toggle(item)">
                            <view class="catalog-model-identity"><text class="catalog-model-title">{{ item.title }}</text><text v-if="query || selectedView" class="catalog-model-path">{{ item.category }} · {{ item.brand }}</text></view>
                            <view class="catalog-check" :class="{ checked: selectedIds.has(item.id) }"><text v-if="selectedIds.has(item.id)">✓</text></view>
                        </view>
                        <view v-if="!visibleRows.length" class="catalog-empty"><u-icon name="search" size="31" color="#90a2ba" /><text>{{ selectedView && !query ? '还没有选择交付型号' : query ? '没有匹配的型号' : '当前分类暂无型号' }}</text><button v-if="query" @tap="keyword = ''">清除搜索</button><button v-else-if="selectedView" @tap="toggleSelectedView">去分类选择</button></view>
                    </scroll-view>
                </view>
            </template>
        </view>
        <template #footer>
            <view v-if="selectionError" class="catalog-error" role="alert">{{ selectionError }}</view>
            <view class="catalog-save-hint">确认后回填资料，保存门店资料后生效</view>
            <view class="catalog-footer"><view class="catalog-cancel"><u-button shape="circle" text="取消" @click="cancel" /></view><view class="catalog-confirm"><u-button type="primary" shape="circle" :text="`确认选择（${chosen.length}）`" :disabled="loading || !!loadError" @click="confirm" /></view></view>
        </template>
    </CoopTaskSheet>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import CooperationSearch from './CooperationSearch.vue'
import { interactionFeedback } from '../../utils/interaction-feedback'
import { cooperationCatalog, coopError, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopState from './CoopState.vue'
import { normalizeCatalog, catalogCategories, visibleModels, selectionState, applySelection, type CatalogModel, type CatalogSelectionMode } from '../../utils/catalog-selection'

const props = withDefaults(defineProps<{ show: boolean; modelValue: CoopRow[]; title?: string; subtitle?: string; max?: number; initialView?: 'browse' | 'selected' }>(), { title: '选择交付型号', subtitle: '', max: 100, initialView: 'browse' })
const emit = defineEmits<{ (event: 'close'): void; (event: 'update:modelValue', value: CoopRow[]): void }>()
const rows = ref<CatalogModel[]>([]), chosen = ref<CatalogModel[]>([]), keyword = ref(''), activeCategory = ref(''), activeBrand = ref(''), selectedView = ref(false), loading = ref(false), loadError = ref(''), selectionError = ref('')
let sequence = 0
const query = computed(() => keyword.value.trim()), categories = computed(() => catalogCategories(rows.value)), activeGroup = computed(() => categories.value.find(item => item.key === activeCategory.value))
const selectedIds = computed(() => new Set(chosen.value.map(item => item.id))), categoryRows = computed(() => visibleModels(rows.value, { category: activeCategory.value }))
const visibleRows = computed(() => selectedView.value ? visibleModels(chosen.value, { keyword: query.value }) : query.value ? visibleModels(rows.value, { keyword: query.value }) : visibleModels(rows.value, { category: activeCategory.value, brand: activeBrand.value }))
const rangeState = computed(() => selectionState(chosen.value, visibleRows.value))
const modelPaneKey = computed(() => JSON.stringify([selectedView.value, activeCategory.value, activeBrand.value, query.value]))
const scopeLabel = computed(() => selectedView.value ? query.value ? '已选型号 · 当前搜索结果' : '全部已选型号' : query.value ? '全部分类 · 当前搜索结果' : `${activeCategory.value || '全部品类'} / ${activeBrand.value || '全部品牌'}`)
function categoryCount(category: string) { return selectionState(chosen.value, visibleModels(rows.value, { category })).count }
function brandCount(brand: string) { return selectionState(chosen.value, visibleModels(rows.value, { category: activeCategory.value, brand })).count }
function selectCategory(category: string) { activeCategory.value = category; activeBrand.value = ''; selectionError.value = '' }
function toggleSelectedView() { selectedView.value = !selectedView.value; keyword.value = ''; selectionError.value = '' }
function updateSelection(range: CatalogModel[], mode: CatalogSelectionMode) { if (loading.value || loadError.value) return; const result = applySelection(chosen.value, range, mode, props.max); selectionError.value = result.error || ''; if (!result.error) { if (JSON.stringify(chosen.value.map(item => item.id)) !== JSON.stringify(result.items.map(item => item.id))) interactionFeedback('select'); chosen.value = result.items } }
function batch(mode: CatalogSelectionMode) { updateSelection(visibleRows.value, mode) }
function toggle(item: CatalogModel) { updateSelection([item], selectedIds.value.has(item.id) ? 'clear' : 'select_all') }
async function load() {
    if (!props.show) return
    const token = ++sequence; loading.value = true; loadError.value = ''
    try {
        const response: any = await cooperationCatalog('', { browse: 1 }), data = response.data
        const items = Array.isArray(data) ? data : Array.isArray(data?.items) ? data.items : Array.isArray(data?.data) ? data.data : null
        if (!items) throw new Error('型号目录返回异常，请重新加载')
        if (!props.show || token !== sequence) return
        rows.value = normalizeCatalog(items)
        const canonical = new Map(rows.value.map(item => [item.id, item]))
        chosen.value = normalizeCatalog(chosen.value.map(item => ({ ...item, ...(canonical.get(item.id) || {}) })))
        if (!categories.value.some(item => item.key === activeCategory.value)) activeCategory.value = categories.value[0]?.key || ''
        if (!activeGroup.value?.brands.some(item => item.key === activeBrand.value)) activeBrand.value = ''
    } catch (cause) { if (props.show && token === sequence) loadError.value = coopError(cause, '型号读取失败，请重试') }
    finally { if (token === sequence) loading.value = false }
}
function cancel() { emit('close') }
function confirm() { if (loading.value || loadError.value) return; if (props.max > 0 && chosen.value.length > props.max) { selectionError.value = `最多选择 ${props.max} 个型号，请先移除多余选择`; return }; interactionFeedback('confirm'); emit('update:modelValue', chosen.value.map(item => ({ ...item }))); emit('close') }
watch(() => props.show, show => { if (show) { chosen.value = applySelection(props.modelValue, [], 'clear', props.max).items; keyword.value = ''; activeCategory.value = ''; activeBrand.value = ''; selectedView.value = props.initialView === 'selected'; selectionError.value = ''; void load() } else { sequence++; loading.value = false; loadError.value = '' } })
onBeforeUnmount(() => { sequence++ })
</script>
<style scoped lang="scss">
.catalog-picker{height:100%;min-height:0;display:flex;flex-direction:column;background:#fff;color:#293b54}.catalog-search{display:flex;align-items:center;gap:10px;padding:12px 14px 10px;flex:none}.catalog-selected-toggle{flex:none;min-width:68px;padding:0 9px;height:36px;line-height:36px;margin:0;border:1px solid #e3eaf4;background:#fff;color:#55739c;border-radius:7px;font-size:12px}.catalog-selected-toggle.active{color:var(--primary-color,#3478cc);background:#edf4ff}.catalog-selected-toggle::after,.catalog-bulk-tools button::after,.catalog-empty button::after{border:0}.catalog-categories{flex:none;white-space:nowrap;background:#fff;border-bottom:1px solid #edf1f6}.catalog-category-tabs{display:inline-flex;gap:24px;min-width:100%;padding:0 16px;box-sizing:border-box}.catalog-category-tabs>view{display:flex;gap:5px;align-items:center;min-height:48px;border-bottom:3px solid transparent;box-sizing:border-box;color:#7a899c;font-size:14px}.catalog-category-tabs>view.active{font-weight:650;color:var(--primary-color,#3478cc);border-bottom-color:var(--primary-color,#3478cc)}.catalog-category-count{font-size:10px;font-weight:400;color:#8a9bb2}.catalog-scope{flex:none;display:flex;gap:12px;justify-content:space-between;padding:11px 15px 3px;color:#7d8fa6;font-size:11px;line-height:18px}.catalog-scope>text:first-child{min-width:0;overflow-wrap:anywhere}.catalog-scope>text:last-child{flex:none}.catalog-bulk{display:flex;align-items:center;justify-content:space-between;gap:8px;flex:none;padding:0 8px 0 15px;border-bottom:1px solid #edf1f6;min-height:44px}.catalog-select-all{display:flex;align-items:center;gap:8px;min-height:44px;font-size:13px}.catalog-check{flex:none;display:flex;align-items:center;justify-content:center;width:19px;height:19px;border:1px solid #c4d0df;box-sizing:border-box;border-radius:5px;color:#fff;background:#fff;font-size:13px;line-height:1}.catalog-check.checked,.catalog-check.partial{background:var(--primary-color,#3478cc);border-color:var(--primary-color,#3478cc)}.catalog-check.partial{font-size:17px}.catalog-selected-label{font-size:13px}.catalog-bulk-tools{display:flex;flex:none}.catalog-bulk-tools button{font-size:12px;min-width:44px;height:44px;padding:0 7px;margin:0;line-height:44px;background:transparent;border:0;color:#59789f}.catalog-bulk-tools button[disabled]{color:#b3becc;background:transparent}.catalog-columns{display:flex;flex:1;min-height:0}.catalog-brands{width:94px;flex:0 0 94px;background:#f5f7fb;height:100%}.catalog-brand{display:flex;flex-direction:column;gap:4px;padding:14px 12px;min-height:62px;box-sizing:border-box;color:#687c96;border-left:3px solid transparent;font-size:13px;line-height:18px;overflow-wrap:anywhere}.catalog-brand>text:last-child{font-size:10px;color:#8a9bb1}.catalog-brand.active{background:#fff;border-left-color:var(--primary-color,#3478cc);color:var(--primary-color,#3478cc);font-weight:600}.catalog-models{flex:1;width:0;min-width:0;height:100%;background:#fff}.catalog-model{display:flex;align-items:center;gap:10px;min-height:60px;margin:0 14px;padding:13px 0;border-bottom:1px solid #edf1f6;box-sizing:border-box}.catalog-model-identity{flex:1;min-width:0;display:flex;flex-direction:column;gap:5px}.catalog-model-title{font-size:14px;line-height:21px;overflow-wrap:anywhere;color:#2c3f59}.catalog-model-path{font-size:11px;line-height:17px;color:#8a9bb0}.catalog-empty{min-height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;padding:20px;text-align:center;color:#8a9bb0;font-size:13px}.catalog-empty button{background:transparent;border:0;color:var(--primary-color,#3478cc);font-size:13px;min-height:44px;line-height:44px}.catalog-save-hint{font-size:11px;line-height:18px;text-align:center;color:#8190a5;padding-bottom:8px}.catalog-footer{display:flex;gap:12px;width:100%;align-items:center}.catalog-cancel{flex:0 0 28%;min-width:0}.catalog-confirm{flex:1;min-width:0}.catalog-error{font-size:12px;color:#ba6b53;line-height:19px;margin-bottom:8px}@media(max-width:340px){.catalog-brands{width:82px;flex-basis:82px}.catalog-search{padding-left:12px;padding-right:12px}.catalog-category-tabs{gap:18px}.catalog-model{margin:0 11px}}
</style>
