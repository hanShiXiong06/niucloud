<template>
    <CoopTaskSheet :show="show && !inventoryStore" :title="lead?.id ? '选择交付门店' : '找交付门店'" :subtitle="lead?.id ? '根据客户所在地，帮你找到合适门店' : '选择客户所在地区，省、市、区县均可查询'" :busy="busy" full @close="$emit('close')">
        <view class="coop-store-picker">
            <view v-if="!lead?.id" class="coop-region-search">
                <view class="coop-region-search__title"><u-icon name="map" size="19" color="var(--primary-color, #d93833)" /><text>客户所在地区</text></view>
                <CoopRegionFields v-model="region" :disabled="busy" />
                <view class="coop-region-search-action"><u-button type="primary" shape="circle" size="small" text="查找门店" :disabled="!region.province_id || busy || loading" :loading="loading" @click="applyRegion" /></view>
            </view>
            <view v-else class="coop-customer-location"><u-icon name="map" size="17" color="#73849a" /><text>{{ lead.region_text || '客户所在地区' }}</text><text class="coop-location-scope">{{ scopeLabels[actualScope] || '附近门店' }}</text></view>
            <CooperationSearch v-if="hasQuery" v-model="keyword" placeholder="搜索门店或联系人" :show-action="true" action-text="查询" @search="load" @custom="load" @clear="load" />
            <view v-if="hasQuery" class="coop-store-caption"><text>{{ loading ? '正在查找门店…' : reason || '靠谱商家优先，按交付能力为你匹配' }}</text><text v-if="!loading && !error">{{ total }} 家</text></view>
            <CoopState v-if="loading && !rows.length || error" :loading="loading" :error="error" @retry="load()" />
            <template v-else>
                <CoopStoreCard v-for="store in rows" :key="store.id" :store="store" :selectable="!!lead?.id" :selected="Number(selected?.id) === Number(store.id)" @select="choose" @inventory="inventoryStore = $event" />
                <view v-if="rows.length && rows.length < total" class="coop-store-more" @tap="!loading && load(true)">{{ loading ? '正在查找…' : '查看更多门店' }}</view>
                <CoopState v-if="!rows.length" :title="hasQuery ? '这个地区暂未找到合适门店' : '客户在哪里，就去哪里找店'" :hint="hasQuery ? keyword ? '试试其他门店名称，或清空关键词' : lead?.id ? '已查找客户所在区县、城市和省份；可以稍后再选，或返回取消线索' : '可选择更大地区再查；没有合适门店，也可稍后再选' : '选择一个省份即可查询，也可以继续选到市、区县'" icon="home" />
            </template>
        </view>
        <template #footer>
            <template v-if="lead?.id"><view class="coop-footer-note">只交付给你选中的门店，未选择前不会发送</view><u-button type="primary" shape="circle" :text="selected ? `交付给${selected.name}` : '请选择交付门店'" :loading="busy" :disabled="!selected || busy || loading" @click="$emit('assign', selected)" /><text class="coop-later" @tap="!busy && $emit('close')">稍后选择，返回线索</text></template>
            <u-button v-else shape="circle" text="完成，返回" @click="$emit('close')" />
        </template>
    </CoopTaskSheet>
    <CoopStoreInventory :show="show && !!inventoryStore" :store="inventoryStore || {}" @close="inventoryStore = null" />
</template>
<script setup lang="ts">
import CooperationSearch from './CooperationSearch.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { cooperationStores, coopError, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopRegionFields from './CoopRegionFields.vue'
import CoopState from './CoopState.vue'
import CoopStoreCard from './CoopStoreCard.vue'
import CoopStoreInventory from './CoopStoreInventory.vue'
const props = defineProps<{ show: boolean; lead?: CoopRow; profile?: CoopRow; busy?: boolean }>()
const emit = defineEmits<{ (event: 'close'): void; (event: 'assign', row: CoopRow): void }>()
const blankRegion = () => ({ province_id: 0, city_id: 0, district_id: 0 })
const region = ref<CoopRow>(blankRegion()), appliedRegion = ref<CoopRow>(blankRegion())
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
        const response: any = await cooperationStores({ ...query, keyword: keyword.value.trim(), page: requestedPage, limit: 20 }, Number(props.lead?.id || 0))
        if (token !== sequence || !props.show) return
        const payload = response.data || {}, incoming = Array.isArray(payload) ? payload : payload.data || []; rows.value = append ? [...new Map([...rows.value, ...incoming].map(row => [row.id, row])).values()] : incoming; page.value = requestedPage; total.value = Number(payload.total ?? rows.value.length); reason.value = payload.reason || ''; actualScope.value = payload.scope || ''
    } catch (cause) { if (token === sequence && props.show) error.value = coopError(cause, '门店读取失败，请重试') }
    finally { if (token === sequence) loading.value = false }
}
function applyRegion() { if (!region.value.province_id || props.busy || loading.value) return; appliedRegion.value = { ...region.value }; void load() }
function choose(store: CoopRow) { if (props.busy) return; if (props.lead?.id) selected.value = Number(selected.value?.id) === Number(store.id) ? null : store; else inventoryStore.value = store }
watch(() => props.show, show => { if (show) { keyword.value = ''; selected.value = null; inventoryStore.value = null; rows.value = []; total.value = 0; reason.value = ''; error.value = ''; actualScope.value = ''; region.value = blankRegion(); appliedRegion.value = blankRegion(); if (props.lead?.id) void load() } else { sequence++; loading.value = false; inventoryStore.value = null } })
onBeforeUnmount(() => { sequence++ })
defineExpose({ load, applyRegion })
</script>
<style scoped lang="scss">
.coop-store-picker { padding: 24rpx; }.coop-region-search { padding: 22rpx 24rpx; background: #fff; margin-bottom: 24rpx; border-radius: 14rpx; }.coop-region-search__title { display: flex; align-items: center; gap: 10rpx; font-size: 28rpx; font-weight: 600; margin-bottom: 24rpx; }.coop-region-search-action { margin-top: 24rpx; }.coop-customer-location { display: flex; align-items: center; gap: 9rpx; padding: 4rpx 4rpx 24rpx; color: #65768c; font-size: 25rpx; line-height: 1.7; }.coop-customer-location>text:nth-child(2) { flex: 1; min-width: 0; word-break: break-word; }.coop-location-scope { color: var(--primary-color, #d93833); background: var(--primary-color-light, #fff0ee); padding: 3rpx 12rpx; border-radius: 6rpx; flex: none; }.coop-store-caption { display: flex; justify-content: space-between; align-items: flex-start; gap: 18rpx; font-size: 23rpx; color: #768293; line-height: 1.7; margin: 24rpx 4rpx; }.coop-store-caption>text:last-child { flex: none; }.coop-later { display: block; text-align: center; color: #727f8e; font-size: 24rpx; padding: 22rpx 0 2rpx; }.coop-footer-note { font-size: 23rpx; color: #7d8998; line-height: 1.6; text-align: center; margin-bottom: 18rpx; }
.coop-store-more { padding: 30rpx 0; text-align: center; font-size: 23rpx; color: #788a9f; }
.coop-customer-location { padding-bottom: 20rpx; align-items: center; }.coop-location-scope { color: var(--primary-color, #3478cc); background: #eaf2ff; border: 1rpx solid #d4e3f8; border-radius: 8rpx; padding: 4rpx 14rpx; font-size: 22rpx; line-height: 1.6; font-weight: 550; white-space: nowrap; }.coop-store-caption { margin-top: 18rpx; margin-bottom: 18rpx; }</style>
