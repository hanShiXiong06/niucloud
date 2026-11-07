<template>
    <CoopTaskSheet :page="page" :inactive="regionOpen" :show="show && !inventoryStore" :title="lead?.id ? '选择交付门店' : '找门店'" :subtitle="lead?.id ? '根据客户需求与所在地匹配门店' : '选择业务与客户所在地区'" :busy="busy" full @close="$emit('close')">
        <view class="coop-store-picker">
            <view v-if="!lead?.id" class="coop-discovery-business"><view class="coop-discovery-types"><text v-for="business in businesses" :key="business.key" :class="{ active: businessType === business.key }" @tap="changeBusiness(business.key)">{{ business.name }}</text></view><view v-if="business" class="coop-discovery-capability" @tap="chooseCapability"><text>{{ demands.length ? '已选 ' + demands.length + ' 项能力' : '筛选' + business.name + businessDimensionLabel(business) + '（选填）' }}</text><u-icon name="arrow-right" size="12" color="#8b9bb0" /></view></view>
            <view v-if="!lead?.id" class="coop-region-search">
                <view class="coop-region-search__title"><u-icon name="map" size="19" color="var(--primary-color, #d93833)" /><text>客户所在地区</text></view>
                <CoopRegionFields v-model="region" :disabled="busy" @overlay="regionOpen = $event" />
                <view class="coop-region-search-action"><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" text="查找门店" :disabled="!region.province_id || busy || loading" :loading="loading" @click="applyRegion" /></view>
            </view>
            <view v-else class="coop-customer-location"><u-icon name="map" size="17" color="#73849a" /><text>{{ lead.region_text || '客户所在地区' }}</text><text class="coop-location-scope">{{ scopeLabels[actualScope] || '附近门店' }}</text></view>
            <view v-if="hasQuery" class="coop-store-caption"><text>{{ loading ? '正在查找门店…' : reason || '靠谱商家优先，按交付能力为你匹配' }}</text><text v-if="!loading && !error">{{ total }} 家</text></view>
            <CoopState v-if="loading && !rows.length || error" :loading="loading" :error="error" @retry="load()" />
            <template v-else>
                <CoopStoreCard v-for="store in rows" :key="store.id" :store="store" :selectable="!!lead?.id" :selected="Number(selected?.id) === Number(store.id)" @select="choose" @inventory="openInventory($event)" />
                <view v-if="rows.length && rows.length < total" class="coop-store-more" @tap="!loading && load(true)">{{ loading ? '正在查找…' : '查看更多门店' }}</view>
                <CoopState v-if="!rows.length" :title="hasQuery ? '这个地区暂未找到合适门店' : '客户在哪里，就去哪里找店'" :hint="hasQuery ? keyword ? '试试其他门店名称，或清空关键词' : lead?.id ? '已查找客户所在区县、城市和省份；可以稍后再选，或返回取消线索' : '可选择更大地区再查；没有合适门店，也可稍后再选' : '选择一个省份即可查询，也可以继续选到市、区县'" icon="home" />
            </template>
        </view>
        <template v-if="hasQuery" #filters><view class="coop-store-filters"><CooperationSearch v-model="keyword" placeholder="搜索门店或联系人" :show-action="true" action-text="查询" @search="load" @custom="load" @clear="load" /></view></template>
        <template #footer>
            <template v-if="lead?.id"><view class="coop-footer-note" :class="{ 'has-selection': !!selected }">{{ selected ? '已选：' + selected.name : '仅向选中的门店交付，选择前不会发送' }}</view><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" :text="selected ? '确认交付' : '请选择交付门店'" :loading="busy" :disabled="!selected || busy || loading" @click="$emit('assign', selected)" /><text class="coop-later" role="button" @tap="!busy && $emit('close')">稍后选择，返回线索</text></template>
            <u-button v-else :custom-style="cooperationButtonStyle" shape="circle" text="完成，返回" @click="$emit('close')" />
        </template>
    </CoopTaskSheet>
    <CoopStoreInventory :show="show && !!inventoryStore" :store="inventoryStore || {}" @close="inventoryStore = null" />
</template>
<script lang="ts">
// Match Vue's transparent layout in native Mini Programs: an extra custom
// component host otherwise breaks the page's definite-height flex chain.
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import CooperationSearch from './CooperationSearch.vue'
import { openCooperationTask, openCooperationCatalog } from '../../utils/cooperation-navigation'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { enabledBusinesses, businessDimensionLabel, businessSelectionLimit } from '../../utils/cooperation-business'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { cooperationStores, coopError, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopRegionFields from './CoopRegionFields.vue'
import CoopState from './CoopState.vue'
import CoopStoreCard from './CoopStoreCard.vue'
import CoopStoreInventory from './CoopStoreInventory.vue'
const props = defineProps<{ show: boolean; page?: boolean; config?: CoopRow; lead?: CoopRow; profile?: CoopRow; busy?: boolean }>()
const emit = defineEmits<{ (event: 'close'): void; (event: 'assign', row: CoopRow): void }>()
const blankRegion = () => ({ province_id: 0, city_id: 0, district_id: 0 })
const region = ref<CoopRow>(blankRegion()), appliedRegion = ref<CoopRow>(blankRegion())
const regionOpen = ref(false), businessType = ref(''), capabilityChoices = ref<Record<string, CoopRow[]>>({}), businesses = computed(() => enabledBusinesses(props.config || {})), business = computed(() => businesses.value.find(item => item.key === businessType.value)), demands = computed(() => capabilityChoices.value[businessType.value] || [])
const keyword = ref(''), selected = ref<CoopRow | null>(null), inventoryStore = ref<CoopRow | null>(null), rows = ref<CoopRow[]>([]), total = ref(0), reason = ref(''), actualScope = ref(''), loading = ref(false), error = ref('')
const scopeLabels: Record<string, string> = { district: '本区县', city: '同城', province: '同省' }
const page = ref(1)
const hasQuery = computed(() => !!props.lead?.id || !!appliedRegion.value.province_id)
let sequence = 0
async function load(more = false) {
    if (!props.show || !hasQuery.value) return
    const append = more === true, requestedPage = append ? page.value + 1 : 1
    const token = ++sequence; if (!append) { selected.value = null; rows.value = [] }; loading.value = true; error.value = ''
    const query = props.lead?.id ? { scope: 'auto' } : { ...appliedRegion.value, scope: appliedRegion.value.district_id ? 'district' : appliedRegion.value.city_id ? 'city' : 'province' }
    try {
        const response: any = await cooperationStores({ ...query, ...(!props.lead?.id ? { business_type: businessType.value || 'purchase', demand_ids: demands.value.map(item => String(item.id)) } : {}), keyword: keyword.value.trim(), page: requestedPage, limit: 20 }, Number(props.lead?.id || 0))
        if (token !== sequence || !props.show) return
        const payload = response.data || {}, incoming = Array.isArray(payload) ? payload : payload.data || []; rows.value = append ? [...new Map([...rows.value, ...incoming].map(row => [row.id, row])).values()] : incoming; page.value = requestedPage; total.value = Number(payload.total ?? rows.value.length); reason.value = payload.reason || ''; actualScope.value = payload.scope || ''
    } catch (cause) { if (token === sequence && props.show) error.value = coopError(cause, '门店读取失败，请重试') }
    finally { if (token === sequence) loading.value = false }
}
function openInventory(store: CoopRow) { openCooperationTask('inventory', { store_id: store.id }) }
function changeBusiness(key: string) { if (props.busy || loading.value || key === businessType.value) return; businessType.value = key; if (hasQuery.value) void load() }
function chooseCapability() { if (props.busy || !business.value) return; const key = businessType.value; openCooperationCatalog({ title: '筛选' + business.value.name + '能力', business_type: key, max: businessSelectionLimit(business.value), selection_mode: business.value.selection_mode, items: demands.value }, items => { capabilityChoices.value[key] = items; if (hasQuery.value) void load() }) }
function applyRegion() { if (!region.value.province_id || props.busy || loading.value) return; appliedRegion.value = { ...region.value }; void load() }
function choose(store: CoopRow) { if (props.busy) return; if (props.lead?.id) selected.value = Number(selected.value?.id) === Number(store.id) ? null : store; else openInventory(store) }
watch(() => props.show, show => { if (show) { businessType.value = businesses.value[0]?.key || 'purchase'; keyword.value = ''; selected.value = null; inventoryStore.value = null; rows.value = []; total.value = 0; reason.value = ''; error.value = ''; actualScope.value = ''; region.value = blankRegion(); appliedRegion.value = blankRegion(); if (props.lead?.id) void load() } else { sequence++; loading.value = false; inventoryStore.value = null } })
onBeforeUnmount(() => { sequence++ })
defineExpose({ load, applyRegion })
</script>
<style scoped lang="scss">
.coop-store-filters { padding: 12px 14px; }
.coop-discovery-business { margin-bottom: 16px; background: #fff; padding: 12px; border-radius: 8px; }.coop-discovery-types { display: flex; gap: 12px; flex-wrap: wrap; }.coop-discovery-types>text { padding: 9px 5px; color: #7a8ca3; font-size: 14px; border-bottom: 2px solid transparent; }.coop-discovery-types>text.active { color: var(--primary-color,#3478cc); border-bottom-color: var(--primary-color,#3478cc); font-weight: 600; }.coop-discovery-capability { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-top: 12px; min-height: 44px; color: #57799f; font-size: 13px; }
.coop-store-picker { padding: 24rpx; }.coop-region-search { padding: 22rpx 24rpx; background: #fff; margin-bottom: 24rpx; border-radius: 14rpx; }.coop-region-search__title { display: flex; align-items: center; gap: 10rpx; font-size: 28rpx; font-weight: 600; margin-bottom: 24rpx; }.coop-region-search-action { margin-top: 24rpx; }.coop-customer-location { display: flex; align-items: center; gap: 9rpx; padding: 4rpx 4rpx 24rpx; color: #65768c; font-size: 25rpx; line-height: 1.7; }.coop-customer-location>text:nth-child(2) { flex: 1; min-width: 0; word-break: break-word; }.coop-location-scope { color: var(--primary-color, #d93833); background: var(--primary-color-light, #fff0ee); padding: 3rpx 12rpx; border-radius: 6rpx; flex: none; }.coop-store-caption { display: flex; justify-content: space-between; align-items: flex-start; gap: 18rpx; font-size: 23rpx; color: #768293; line-height: 1.7; margin: 24rpx 4rpx; }.coop-store-caption>text:last-child { flex: none; }.coop-later { display: block; text-align: center; color: #727f8e; font-size: 24rpx; padding: 22rpx 0 2rpx; }.coop-footer-note { font-size: 23rpx; color: #7d8998; line-height: 1.6; text-align: center; margin-bottom: 18rpx; }
.coop-store-more { display: flex; align-items: center; justify-content: center; min-height: 44px; padding: 12px 0; box-sizing: border-box; text-align: center; font-size: 13px; color: #788a9f; }
.coop-later { display: flex; align-items: center; justify-content: center; min-height: 44px; box-sizing: border-box; padding: 8px 0 0; font-size: 13px; }
.coop-footer-note { overflow-wrap: anywhere; font-size: 12px; line-height: 1.6; margin-bottom: 8px; }
.coop-footer-note.has-selection { color: #455e7d; }
.coop-discovery-types>text { display: inline-flex; align-items: center; min-height: 44px; padding: 0 6px; box-sizing: border-box; }
.coop-customer-location { padding-bottom: 20rpx; align-items: center; }.coop-location-scope { color: var(--primary-color, #3478cc); background: #eaf2ff; border: 1rpx solid #d4e3f8; border-radius: 8rpx; padding: 4rpx 14rpx; font-size: 22rpx; line-height: 1.6; font-weight: 550; white-space: nowrap; }.coop-store-caption { margin-top: 18rpx; margin-bottom: 18rpx; }</style>
