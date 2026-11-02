<template>
    <section>
        <LeadSectionHeader title="全店库存快照" description="按型号查看各门店的库存参考；不会修改商户 ERP 的价格或库存。"><el-button :icon="Refresh" :loading="loading" @click="load">刷新快照</el-button></LeadSectionHeader>
        <el-alert title="默认仅查看在售且有货的设备。下架、无货及状态待核实的设备不参与库存匹配；快照不会锁货，接单前仍需核对实物。" type="info" :closable="false" show-icon class="lead-banner" />
        <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="lead-error-banner" />
        <div class="lead-card lead-table-card">
            <LeadFilterBar :query="query" :time-fields="timeFields" :reset-values="resetValues" :extra-active-count="Number(regionActive) + Number(!!query.merchant_id) + Number(query.inventory_scope !== 'sellable')" default-time-field="fetched_at" :total="total" :loading="loading" label="库存快照" search-label="商品或规格" placeholder="搜索型号、规格或商品名称" @apply="applyFilters" @reload="load">
                <template #filters>
                    <el-popover v-model:visible="inventoryScopeOpened" trigger="click" placement="bottom-start" :width="280" popper-class="hsx-lead-filter-popper" @show="beginInventoryScope">
                        <template #reference><el-button class="lead-filter-bar__chip" :class="{ 'is-active': query.inventory_scope !== 'all' }" :aria-expanded="inventoryScopeOpened" aria-haspopup="dialog" aria-label="筛选库存范围"><span>{{ inventoryScopeLabel }}</span><el-icon><ArrowDown /></el-icon></el-button></template>
                        <section role="dialog" aria-label="库存范围筛选" class="lead-inventory-scope-filter" @keydown.esc.stop.prevent="inventoryScopeOpened = false">
                            <header><strong>库存范围</strong><small>其他状态仅供核对，不计入库存匹配。</small></header>
                            <el-radio-group v-model="inventoryScopeDraft" class="lead-inventory-scope-options" aria-label="库存范围选项"><el-radio v-for="option in inventoryScopeOptions" :key="option.value" :value="option.value">{{ option.label }}</el-radio></el-radio-group>
                            <footer><el-button @click="inventoryScopeOpened = false">取消</el-button><el-button type="primary" @click="applyInventoryScope">应用</el-button></footer>
                        </section>
                    </el-popover>
                    <el-popover v-model:visible="regionOpened" trigger="click" placement="bottom-start" :width="360" popper-class="hsx-lead-filter-popper" @show="beginRegion">
                        <template #reference><el-button class="lead-filter-bar__chip" :class="{ 'is-active': regionActive }" :aria-expanded="regionOpened" aria-haspopup="dialog" aria-label="按门店地区筛选"><span>地区</span><strong v-if="regionActive">{{ regionSummary }}</strong><el-icon><ArrowDown /></el-icon></el-button></template>
                        <section role="dialog" aria-label="门店地区筛选" class="lead-inventory-region-filter" @keydown.esc.stop.prevent="regionOpened = false">
                            <header><strong>按门店所在地筛选</strong><small>可只选省份或城市，不必选到区县。</small></header>
                            <RegionSelect :model-value="regionDraft" :teleported="false" @update:model-value="value => Object.assign(regionDraft, value)" />
                            <p>地区来自商户登记的地址，不使用 ERP 的地区编号。</p>
                            <footer><el-button link @click="clearRegion">不限地区</el-button><span /><el-button @click="regionOpened = false">取消</el-button><el-button type="primary" @click="applyRegion">应用</el-button></footer>
                        </section>
                    </el-popover>
                    <el-popover v-model:visible="merchantOpened" trigger="click" placement="bottom-start" :width="360" popper-class="hsx-lead-filter-popper" @show="beginMerchants">
                        <template #reference><el-button class="lead-filter-bar__chip" :class="{ 'is-active': !!query.merchant_id }" :aria-expanded="merchantOpened" aria-haspopup="dialog" aria-label="筛选所属门店"><span>所属门店</span><strong v-if="query.merchant_id">{{ query.merchant_label || '已选择' }}</strong><el-icon><ArrowDown /></el-icon></el-button></template>
                        <section role="dialog" aria-label="所属门店筛选" class="lead-inventory-merchant-filter" @keydown.esc.stop.prevent="merchantOpened = false">
                            <header><strong>选择所属门店</strong><small>{{ regionActive ? regionSummary : '当前不限地区' }}</small></header>
                            <el-input v-model="merchantKeyword" clearable :prefix-icon="Search" aria-label="搜索门店名称、联系人或手机号" placeholder="搜索门店名称、联系人或手机号" @input="scheduleMerchantSearch" @keydown.enter.stop.prevent="searchMerchantsNow" />
                            <el-radio-group v-model="merchantDraft" class="lead-inventory-merchant-options" aria-label="门店选项">
                                <el-radio value="" @change="merchantSelection = {}">全部门店</el-radio>
                                <el-radio v-for="item in merchants" :key="item.id" :value="item.id" @change="merchantSelection = item"><span class="lead-inventory-merchant-option"><strong>{{ item.name }}</strong><small>{{ [item.contact_name, item.mobile].filter(Boolean).join(' · ') }}</small><small>{{ item.region_text || region(item) }}</small></span></el-radio>
                            </el-radio-group>
                            <p v-if="merchantError" role="alert" class="lead-error">{{ merchantError }} <el-button link type="primary" @click="loadMerchantOptions(true)">重试</el-button></p>
                            <p v-else-if="merchantLoading">正在查询门店…</p>
                            <p v-else-if="!merchants.length">当前地区没有匹配门店，可调整地区或搜索词。</p>
                            <div v-if="merchantTotal" class="lead-inventory-merchant-pages"><small>已显示 {{ merchants.length }} / {{ merchantTotal }} 家</small><el-button v-if="merchants.length < merchantTotal" link type="primary" :loading="merchantLoading" @click="loadMerchantOptions(false)">加载更多</el-button></div>
                            <footer><el-button @click="merchantOpened = false">取消</el-button><el-button type="primary" :disabled="merchantLoading" @click="applyMerchant">应用</el-button></footer>
                        </section>
                    </el-popover>
                </template>
            </LeadFilterBar>
            <HsxTable :columns="columns" :data="rows" :loading="loading" :empty-text="error ? '列表未加载，请查看上方提示后重试' : canExpandSearch ? '在售有货范围内没有匹配资料' : filtered ? '没有符合当前筛选的库存，可清空筛选后查看在售有货设备' : '暂无在售且有货的快照，可切换库存范围核对，或更新商户 ERP 库存'">
                <template #product="{ row }"><div class="lead-cell"><strong>{{ row.model || row.name || '未识别型号' }}</strong><small>{{ [row.memory, row.color, row.grade].filter(Boolean).join(' · ') || '规格未返回' }}</small></div></template>
                <template #price="{ row }"><span :class="row.price !== null && row.price !== undefined ? 'lead-money' : 'lead-muted'">{{ row.price === null || row.price === undefined || row.price === '' ? '—' : money(row.price) }}</span></template>
                <template #merchant="{ row }"><div class="lead-cell"><strong>{{ row.merchant_name }}</strong><small>{{ row.region_text || region(row) }}</small></div></template>
                <template #availability="{ row }"><div class="lead-cell"><el-tag :type="inventoryTone(row)" effect="plain">{{ row.sale_status_label || '状态待核实' }}</el-tag><small>{{ row.stock_status_label || '库存待核实' }}</small></div></template>
                <template #updated="{ row }"><div class="lead-cell"><LeadTimestamp :value="row.last_sync_at || row.source_updated_at || row.fetched_at" /><el-tag v-if="row.is_stale" type="warning" size="small">数据已过期</el-tag></div></template>
                <template #actions="{ row }"><el-button link type="primary" @click="detail = row; drawer = true">查看快照</el-button></template>
            </HsxTable>
            <div v-if="canExpandSearch" class="lead-inventory-search-fallback" role="status">
                <span>可查询全部已同步资料；待核实记录不代表可售，也不参与匹配。</span>
                <el-button link type="primary" @click="expandSearch">在全部来源资料中查找（含待核实）</el-button>
            </div>
            <div class="lead-pagebar"><HsxPagination layout="sizes, prev, pager, next, jumper" v-model:current-page="query.page" v-model:page-size="query.limit" :total="total" @change="load" /></div>
        </div>
        <HsxDrawer v-model="drawer" title="商品快照" subtitle="仅展示最近一次同步结果，实际库存以门店确认结果为准。" size="sm">
            <el-descriptions :column="1" border><el-descriptions-item label="型号">{{ detail.model || detail.name || '未返回' }}</el-descriptions-item><el-descriptions-item label="品牌">{{ detail.brand || '未返回' }}</el-descriptions-item><el-descriptions-item label="规格">{{ [detail.memory, detail.color, detail.grade].filter(Boolean).join(' · ') || '未返回' }}</el-descriptions-item><el-descriptions-item label="快照价格">{{ detail.price == null || detail.price === '' ? '—' : money(detail.price) }}</el-descriptions-item><el-descriptions-item label="上架状态">{{ detail.sale_status_label || '状态待核实' }}</el-descriptions-item><el-descriptions-item label="库存状态">{{ detail.stock_status_label || '库存待核实' }}</el-descriptions-item><el-descriptions-item label="快照数量">{{ detail.quantity ?? '未返回' }}</el-descriptions-item><el-descriptions-item label="所属门店">{{ detail.merchant_name }}</el-descriptions-item><el-descriptions-item label="门店地区">{{ detail.region_text || region(detail) }}</el-descriptions-item><el-descriptions-item label="同步时间">{{ time(detail.last_sync_at || detail.source_updated_at || detail.fetched_at) }}</el-descriptions-item><el-descriptions-item label="实物核对">接单前仍需门店确认，不会自动锁货</el-descriptions-item></el-descriptions>
            <el-alert v-if="detail.is_stale" title="该快照已过期，请先同步最新库存。" type="warning" :closable="false" style="margin-top: 18px" />
        </HsxDrawer>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ArrowDown, Refresh, Search } from '@element-plus/icons-vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import LeadSectionHeader from './LeadSectionHeader.vue'
import LeadTimestamp from './LeadTimestamp.vue'
import LeadFilterBar from './LeadFilterBar.vue'
import RegionSelect from './RegionSelect.vue'
import { hasListFilters } from '../../composables/filtering'
import * as api from '../../api'
import { errorText, money, region, time, useLeadList, type Row } from '../../composables/useLeadUi'
const resetValues = { merchant_id: '', merchant_label: '', province_id: '', city_id: '', district_id: '', province_name: '', city_name: '', district_name: '', inventory_scope: 'sellable' }
const { rows, total, loading, error, query, load, search } = useLeadList(api.getInventory, { ...resetValues, time_field: 'fetched_at', start_time: 0, end_time: 0 })
const merchants = ref<Row[]>([]), merchantLoading = ref(false), merchantError = ref(''), merchantTotal = ref(0), merchantPage = ref(0), merchantKeyword = ref(''), merchantDraft = ref<string | number>(''), merchantSelection = ref<Row>({}), merchantOpened = ref(false), regionOpened = ref(false), regionDraft = reactive<Row>({}), drawer = ref(false), detail = ref<Row>({})
const inventoryScopeOptions = [{ value: 'sellable', label: '在售有货' }, { value: 'all', label: '全部快照（含不可售）' }, { value: 'off_shelf', label: '已下架' }, { value: 'sold_out', label: '无可售库存' }, { value: 'unverified', label: '状态或库存待核实' }]
const inventoryScopeOpened = ref(false), inventoryScopeDraft = ref('sellable')
const inventoryScopeLabel = computed(() => inventoryScopeOptions.find(option => option.value === query.inventory_scope)?.label || '在售有货')
const inventoryTone = (row: Row) => row.is_sellable === true ? 'success' : row.sale_status === 'unknown' ? 'warning' : 'info'
let merchantRevision = 0, disposed = false, merchantSearchTimer: ReturnType<typeof setTimeout> | undefined
const regionFields = ['province_id', 'city_id', 'district_id']
const regionActive = computed(() => Number(query.province_id) > 0)
const regionSummary = computed(() => [query.province_name, query.city_name, query.district_name].filter(Boolean).join(' / ') || '已选择地区')
const timeFields = [{ value: 'fetched_at', label: '快照采集时间' }, { value: 'last_sync_at', label: '完整同步时间' }]
const filtered = computed(() => hasListFilters(query, ['merchant_id', ...regionFields]) || query.inventory_scope !== 'sellable')
const canExpandSearch = computed(() => query.inventory_scope === 'sellable' && !!String(query.keyword || '').trim() && !loading.value && !error.value && total.value === 0)
function expandSearch() { if (!canExpandSearch.value) return; return applyFilters({ inventory_scope: 'all' }) }
function beginInventoryScope() { regionOpened.value = false; merchantOpened.value = false; inventoryScopeDraft.value = query.inventory_scope || 'sellable' }
function applyInventoryScope() { inventoryScopeOpened.value = false; return applyFilters({ inventory_scope: inventoryScopeOptions.some(option => option.value === inventoryScopeDraft.value) ? inventoryScopeDraft.value : 'sellable' }) }
function scopeKey(value: Row = query) { return regionFields.map(key => Number(value[key] || 0)).join(',') }
function applyFilters(values: Row) {
    if (regionFields.some(key => key in values && Number(values[key] || 0) !== Number(query[key] || 0))) {
        values = { ...values, merchant_id: '', merchant_label: '' }; merchantRevision++; merchantOpened.value = false; merchants.value = []; merchantTotal.value = 0; merchantPage.value = 0; merchantDraft.value = ''; merchantSelection.value = {}; merchantLoading.value = false
        if (merchantSearchTimer) clearTimeout(merchantSearchTimer)
    }
    Object.assign(query, values); return search()
}
function beginRegion() {
    merchantOpened.value = false; inventoryScopeOpened.value = false
    Object.assign(regionDraft, Object.fromEntries(regionFields.map(key => [key, Number(query[key] || 0)])), { province_name: query.province_name, city_name: query.city_name, district_name: query.district_name })
}
function applyRegion() { regionOpened.value = false; return applyFilters({ ...regionDraft, ...Object.fromEntries(regionFields.map(key => [key, Number(regionDraft[key] || 0) || ''])) }) }
function clearRegion() { regionOpened.value = false; return applyFilters({ province_id: '', city_id: '', district_id: '', province_name: '', city_name: '', district_name: '' }) }
function beginMerchants() {
    regionOpened.value = false; inventoryScopeOpened.value = false; merchantDraft.value = query.merchant_id || ''; merchantSelection.value = query.merchant_id ? { id: query.merchant_id, name: query.merchant_label } : {}; merchantKeyword.value = ''
    void loadMerchantOptions(true)
}
async function loadMerchantOptions(reset = true) {
    if (!reset && merchantLoading.value) return
    const revision = ++merchantRevision, expectedScope = scopeKey(), page = reset ? 1 : merchantPage.value + 1
    merchantLoading.value = true; merchantError.value = ''
    if (reset) { merchants.value = []; merchantPage.value = 0; merchantTotal.value = 0 }
    try {
        const result = await api.getMerchants({ picker: 1, page, limit: 20, keyword: merchantKeyword.value.trim(), ...Object.fromEntries(regionFields.map(key => [key, query[key] || ''])) })
        if (disposed || revision !== merchantRevision || expectedScope !== scopeKey()) return
        const data = result.data || {}, items: Row[] = Array.isArray(data.data) ? data.data : []
        const combined = reset ? items : [...merchants.value, ...items]
        merchants.value = Array.from(new Map(combined.map(item => [String(item.id), item])).values()); merchantTotal.value = Number(data.total || 0); merchantPage.value = Number(data.current_page || page)
    } catch (cause) { if (!disposed && revision === merchantRevision) merchantError.value = errorText(cause, '门店筛选加载失败，仍可搜索商品。') }
    finally { if (!disposed && revision === merchantRevision) merchantLoading.value = false }
}
function scheduleMerchantSearch() { if (merchantSearchTimer) clearTimeout(merchantSearchTimer); merchantRevision++; merchantLoading.value = true; merchants.value = []; merchantPage.value = 0; merchantTotal.value = 0; merchantError.value = ''; merchantSearchTimer = setTimeout(() => { void loadMerchantOptions(true) }, 250) }
function searchMerchantsNow() { if (merchantSearchTimer) clearTimeout(merchantSearchTimer); return loadMerchantOptions(true) }
function applyMerchant() {
    const selected = merchants.value.find(item => String(item.id) === String(merchantDraft.value)) || merchantSelection.value
    merchantOpened.value = false; return applyFilters({ merchant_id: merchantDraft.value || '', merchant_label: merchantDraft.value ? selected.name || '已选择门店' : '' })
}
const columns = [ { prop: 'model', label: '商品 / 规格', slot: 'product', minWidth: 220 }, { prop: 'price', label: '快照价格', slot: 'price', width: 130 }, { prop: 'merchant_name', label: '所属门店', slot: 'merchant', minWidth: 200 }, { prop: 'availability', label: '销售 / 库存', slot: 'availability', width: 150 }, { prop: 'last_sync_at', label: '快照时间', slot: 'updated', minWidth: 170 }, { prop: 'actions', label: '操作', slot: 'actions', width: 100, fixed: 'right' as const } ]
onMounted(() => { void load() })
onBeforeUnmount(() => { disposed = true; merchantRevision++; if (merchantSearchTimer) clearTimeout(merchantSearchTimer) })
</script>
<style scoped>
.lead-inventory-search-fallback { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 6px 12px; padding: 0 0 14px; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.6; }.lead-inventory-search-fallback .el-button { margin: 0; white-space: normal; }
.lead-filter-bar__chip.el-button { margin: 0; height: 34px; padding: 0 11px; gap: 6px; border-radius: 7px; border-color: var(--hsx-border-color, #dde4ed); background: var(--hsx-bg-surface, #fff); }.lead-filter-bar__chip :deep(>span) { gap: 6px; }.lead-filter-bar__chip strong { max-width: 140px; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }.lead-filter-bar__chip.is-active { color: var(--el-color-primary); background: var(--el-color-primary-light-9); border-color: var(--el-color-primary-light-7); }
.lead-inventory-region-filter header,.lead-inventory-merchant-filter header,.lead-inventory-scope-filter header { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
.lead-inventory-scope-filter small { color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.6; }.lead-inventory-scope-options { display: flex; flex-direction: column; align-items: stretch; width: 100%; }.lead-inventory-scope-options :deep(.el-radio) { min-height: 36px; margin: 0; }.lead-inventory-scope-filter footer { display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid var(--el-border-color-lighter); padding-top: 12px; margin-top: 12px; }.lead-inventory-scope-filter footer .el-button + .el-button { margin-left: 0; }
.lead-inventory-region-filter small,.lead-inventory-region-filter p,.lead-inventory-merchant-filter small,.lead-inventory-merchant-filter p { color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.6; }
.lead-inventory-region-filter :deep(.lead-region) { display: grid; gap: 10px; }.lead-inventory-region-filter :deep(.el-select) { width: 100%; }
.lead-inventory-region-filter footer,.lead-inventory-merchant-filter footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--el-border-color-lighter); }
.lead-inventory-region-filter footer span { flex: 1; }.lead-inventory-region-filter footer .el-button + .el-button,.lead-inventory-merchant-filter footer .el-button + .el-button { margin-left: 0; }
.lead-inventory-merchant-options { display: flex; flex-direction: column; align-items: stretch; width: 100%; max-height: 320px; overflow-y: auto; margin-top: 10px; }
.lead-inventory-merchant-options :deep(.el-radio) { height: auto; min-height: 36px; margin: 0; padding: 8px; border-radius: 6px; }.lead-inventory-merchant-options :deep(.el-radio.is-checked) { background: var(--el-color-primary-light-9); }.lead-inventory-merchant-options :deep(.el-radio__label) { white-space: normal; line-height: 1.5; }
.lead-inventory-merchant-option { display: flex; flex-direction: column; gap: 2px; }.lead-inventory-merchant-option strong { font-weight: 500; }
.lead-inventory-merchant-pages { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding-top: 8px; }
</style>
