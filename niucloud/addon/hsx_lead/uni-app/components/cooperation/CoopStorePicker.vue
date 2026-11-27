<template>
    <CoopTaskSheet
        :page="page"
        :inactive="regionOpen"
        :show="show && !inventoryStore"
        :title="isReplacement ? '重新选择门店' : lead?.id ? '选择交付门店' : '找门店'"
        :subtitle="lead?.id ? '根据客户需求与所在地匹配门店' : '选择业务与客户所在地区'"
        :busy="busy"
        full
        @close="$emit('close')"
    >
        <view class="coop-store-picker">
            <view v-if="isReplacement" class="coop-replacement-current"
                ><text>当前交付：{{ lead?.receiver?.name || '原门店' }}</text
                ><text>确认新门店前保留原交付，原门店仍可接单。</text></view
            >
            <view v-if="submissionError" class="coop-assignment-error" role="alert"
                ><text>{{ submissionError }}</text
                ><button :disabled="busy || loading" @tap="$emit('reload')">刷新当前交付</button></view
            >
            <view v-if="!lead?.id" class="coop-discovery-business"
                ><view class="coop-discovery-types"
                    ><text
                        v-for="business in businesses"
                        :key="business.key"
                        :class="{ active: businessType === business.key }"
                        @tap="changeBusiness(business.key)"
                        >{{ business.name }}</text
                    ></view
                ><view v-if="business" class="coop-discovery-capability" @tap="chooseCapability"
                    ><text
                        >{{ demands.length ? '已选 ' + demands.length + ' 项能力' : '筛选' + business.name + businessDimensionLabel(business) + '（选填）' }}</text
                    ><u-icon name="arrow-right" size="12" color="#8b9bb0" /></view
            ></view>
            <view v-if="!lead?.id" class="coop-region-search">
                <view class="coop-region-search__title"
                    ><u-icon name="map" size="19" color="var(--primary-color, #d93833)" /><text
                        >客户所在地区</text
                    ></view
                >
                <CoopRegionFields v-model="region" :disabled="busy" @overlay="regionOpen = $event" />
                <view class="coop-region-search-action"
                    ><u-button
                        :custom-style="cooperationButtonStyle"
                        type="primary"
                        shape="circle"
                        text="查找门店"
                        :disabled="!region.province_id || busy || loading"
                        :loading="loading"
                        @click="applyRegion"
                /></view>
            </view>
            <view v-else class="coop-customer-location"
                ><u-icon name="map" size="17" color="#73849a" /><text>{{ lead.region_text || '客户所在地区' }}</text
                ><text class="coop-location-scope">{{ scopeLabels[actualScope] || '附近门店' }}</text></view
            >
            <view v-if="hasQuery" class="coop-store-caption"
                ><text>{{ loading ? '正在查找门店…' : reason || '靠谱商家优先，按交付能力为你匹配' }}</text
                ><text v-if="!loading && !error">{{ total }} 家</text></view
            >
            <CoopState v-if="loading && !rows.length || error" :loading="loading" :error="error" @retry="load()" />
            <template v-else>
                <view v-for="store in rows" :key="store.id">
                    <CoopStoreCard
                        :store="store"
                        :selectable="!!lead?.id"
                        :selected="Number(selected?.id) === Number(store.id)"
                        @select="choose"
                        @inventory="openInventory($event)"
                    />
                    <view
                        v-if="lead?.id && store.is_internal === true && Number(selected?.id) === Number(store.id)"
                        class="coop-internal-members"
                    >
                        <view class="coop-internal-members__heading"
                            ><text>选择接收成员</text><text>店内转交不收协作费</text></view
                        >
                        <button
                            v-for="member in internalAssignees"
                            :key="member.member_id"
                            class="coop-internal-member"
                            :class="{ 'is-selected': selectedAssigneeId === member.member_id }"
                            :disabled="busy || loading || assignmentPending"
                            :aria-pressed="selectedAssigneeId === member.member_id"
                            @tap.stop="chooseAssignee(member.member_id)"
                        >
                            <view
                                class="coop-internal-member__avatar"
                                aria-hidden="true"
                                >{{ member.name.slice(0, 1) }}</view
                            >
                            <view class="coop-internal-member__copy"
                                ><text>{{ member.name }}</text
                                ><text>{{ member.role_label || '本店成员' }}</text></view
                            >
                            <u-icon
                                :name="selectedAssigneeId === member.member_id ? 'checkmark-circle-fill' : 'checkmark-circle'"
                                size="21"
                                :color="selectedAssigneeId === member.member_id ? 'var(--primary-color,#3478cc)' : '#b8c2cf'"
                            />
                        </button>
                        <text v-if="!internalAssignees.length" class="coop-internal-members__empty"
                            >暂无其他可接收成员，请选择其他门店。</text
                        >
                    </view>
                </view>
                <view
                    v-if="rows.length && rows.length < total"
                    class="coop-store-more"
                    @tap="!loading && load(true)"
                    >{{ loading ? '正在查找…' : '查看更多门店' }}</view
                >
                <CoopState
                    v-if="!rows.length"
                    :title="hasQuery ? '这个地区暂未找到合适门店' : '客户在哪里，就去哪里找店'"
                    :hint="hasQuery ? keyword ? '试试其他门店名称，或清空关键词' : lead?.id ? '已查找客户所在区县、城市和省份；可以稍后再选，或返回取消线索' : '可选择更大地区再查；没有合适门店，也可稍后再选' : '选择一个省份即可查询，也可以继续选到市、区县'"
                    icon="home"
                />
            </template>
        </view>
        <template v-if="hasQuery" #filters
            ><view class="coop-store-filters"
                ><CooperationSearch
                    v-model="keyword"
                    placeholder="搜索门店或联系人"
                    :show-action="true"
                    action-text="查询"
                    @search="searchStores"
                    @custom="searchStores"
                    @clear="clearSearch" /></view
        ></template>
        <template #footer>
            <template v-if="lead?.id"
                ><view
                    class="coop-footer-note"
                    :class="{ 'has-selection': !!selected }"
                    >{{ unchangedRecipient ? '这是当前接收方，请选择其他门店或本店成员' : isInternal ? selectedAssignee ? '交给本店成员：' + selectedAssignee.name : '请选一位本店成员接收这条需求' : selected ? '已选：' + selected.name : '仅向选中的门店交付，选择前不会发送' }}</view
                ><u-button
                    :custom-style="cooperationButtonStyle"
                    type="primary"
                    shape="circle"
                    :text="isReplacement ? selected ? '确认更换门店' : '请选择新的交付门店' : isInternal ? internalButtonText : selected ? '确认交付' : '请选择交付门店'"
                    :loading="busy"
                    :disabled="!canAssign || busy || loading || assignmentPending"
                    @click="confirmAssignment"
                /><text
                    class="coop-later"
                    role="button"
                    @tap="!busy && !assignmentPending && $emit('close')"
                    >{{ isReplacement ? '暂不更换，保留原门店' : '稍后选择，返回线索' }}</text
                ></template
            >
            <u-button
                v-else
                :custom-style="cooperationButtonStyle"
                shape="circle"
                text="完成，返回"
                @click="$emit('close')"
            />
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
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { cooperationStores, coopError, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopRegionFields from './CoopRegionFields.vue'
import CoopState from './CoopState.vue'
import CoopStoreCard from './CoopStoreCard.vue'
import CoopStoreInventory from './CoopStoreInventory.vue'
const props = defineProps<{ show: boolean; page?: boolean; config?: CoopRow; lead?: CoopRow; profile?: CoopRow; busy?: boolean; submissionError?: string }>()
const emit = defineEmits<{ (event: 'close' | 'reload'): void; (event: 'assign', row: CoopRow): void }>()
const blankRegion = () => ({ province_id: 0, city_id: 0, district_id: 0 })
const region = ref<CoopRow>(blankRegion()), appliedRegion = ref<CoopRow>(blankRegion())
const regionOpen = ref(false), businessType = ref(''), capabilityChoices = ref<Record<string, CoopRow[]>>({}), businesses = computed(() => enabledBusinesses(props.config || {})), business = computed(() => businesses.value.find(item => item.key === businessType.value)), demands = computed(() => capabilityChoices.value[businessType.value] || [])
const keyword = ref(''), selected = ref<CoopRow | null>(null), inventoryStore = ref<CoopRow | null>(null), rows = ref<CoopRow[]>([]), total = ref(0), reason = ref(''), actualScope = ref(''), loading = ref(false), error = ref('')
const selectedAssigneeId = ref(0), assignmentPending = ref(false)
// Selection always resolves against the latest rows, not a stale card object.
const currentSelection = computed(() => rows.value.find(row => Number(row.id) === Number(selected.value?.id)) || null)
const isInternal = computed(() => currentSelection.value?.is_internal === true)
const internalAssignees = computed(() => {
    const source = isInternal.value && Array.isArray(currentSelection.value?.internal_assignees) ? currentSelection.value!.internal_assignees : []
    const seen = new Set<number>(), result: { member_id: number; name: string; role_label: string }[] = []
    for (const row of source) {
        const id = Number(row?.member_id)
        if (!Number.isSafeInteger(id) || id <= 0 || seen.has(id) || typeof row?.name !== 'string' || !row.name.trim()) continue
        seen.add(id); result.push({ member_id: id, name: row.name.trim(), role_label: typeof row.role_label === 'string' ? row.role_label : '' })
    }
    return result
})
const selectedAssignee = computed(() => internalAssignees.value.find(row => row.member_id === selectedAssigneeId.value))
const isReplacement = computed(() => props.lead?.status === 'assigned' && props.lead?.can_reassign === true)
const unchangedRecipient = computed(() => isReplacement.value && Number(currentSelection.value?.id) === Number(props.lead?.receiver_id) && (!isInternal.value || selectedAssigneeId.value === Number(props.lead?.internal_assignee?.member_id || 0)))
const canAssign = computed(() => !props.submissionError && !!props.lead?.id && (props.lead.can_assign === true || isReplacement.value) && !!currentSelection.value && !unchangedRecipient.value && (!isInternal.value || !!selectedAssignee.value))
const internalButtonText = computed(() => selectedAssignee.value ? '交给' + Array.from(selectedAssignee.value.name).slice(0, 12).join('') + (Array.from(selectedAssignee.value.name).length > 12 ? '…' : '') : '请选择接收成员')
const scopeLabels: Record<string, string> = { district: '本区县', city: '同城', province: '同省' }
const page = ref(1)
const hasQuery = computed(() => !!props.lead?.id || !!appliedRegion.value.province_id)
let sequence = 0, lastRequestedKeyword = ''
function clearSelection() { selected.value = null; selectedAssigneeId.value = 0; assignmentPending.value = false }
async function load(more = false) {
    if (!props.show || !hasQuery.value) return
    const append = more === true, requestedPage = append ? page.value + 1 : 1
    const token = ++sequence; if (!append) { clearSelection(); rows.value = [] }; loading.value = true; error.value = ''; lastRequestedKeyword = keyword.value.trim()
    const query = props.lead?.id ? { scope: 'auto' } : { ...appliedRegion.value, scope: appliedRegion.value.district_id ? 'district' : appliedRegion.value.city_id ? 'city' : 'province' }
    try {
        const response: any = await cooperationStores({ ...query, ...(!props.lead?.id ? { business_type: businessType.value || 'purchase', demand_ids: demands.value.map(item => String(item.id)) } : {}), keyword: lastRequestedKeyword, page: requestedPage, limit: 20 }, Number(props.lead?.id || 0))
        if (token !== sequence || !props.show) return
        const payload = response.data || {}, incoming = Array.isArray(payload) ? payload : payload.data || []; rows.value = append ? [...new Map([...rows.value, ...incoming].map(row => [row.id, row])).values()] : incoming; page.value = requestedPage; total.value = Number(payload.total ?? rows.value.length); reason.value = payload.reason || ''; actualScope.value = payload.scope || ''
    } catch (cause) { if (token === sequence && props.show) error.value = coopError(cause, '门店读取失败，请重试') }
    finally { if (token === sequence) loading.value = false }
}
function openInventory(store: CoopRow) { openCooperationTask('inventory', { store_id: store.id }) }
function changeBusiness(key: string) { if (props.busy || loading.value || key === businessType.value) return; businessType.value = key; if (hasQuery.value) void load() }
function chooseCapability() { if (props.busy || !business.value) return; const key = businessType.value; openCooperationCatalog({ title: '筛选' + business.value.name + '能力', business_type: key, max: businessSelectionLimit(business.value), selection_mode: business.value.selection_mode, items: demands.value }, items => { capabilityChoices.value[key] = items; if (hasQuery.value) void load() }) }
function applyRegion() { if (!region.value.province_id || props.busy || loading.value) return; appliedRegion.value = { ...region.value }; void load() }
function searchStores(value?: string | number) { if (props.busy || assignmentPending.value) return; const next = String(typeof value === 'string' || typeof value === 'number' ? value : keyword.value).trim(); keyword.value = next; if (loading.value && lastRequestedKeyword === next) return; return load() }
function clearSearch() { return searchStores('') }
function choose(store: CoopRow) { if (props.busy || loading.value || assignmentPending.value) return; if (props.lead?.id) { selectedAssigneeId.value = 0; selected.value = Number(selected.value?.id) === Number(store.id) ? null : store } else openInventory(store) }
function chooseAssignee(id: number) { if (props.busy || loading.value || assignmentPending.value || !internalAssignees.value.some(row => row.member_id === id)) return; selectedAssigneeId.value = id }
function confirmAssignment() {
    if (!props.show || props.busy || loading.value || assignmentPending.value || !canAssign.value || !currentSelection.value) return
    const { assignee_member_id: _oldRecipient, ...store } = currentSelection.value
    const result = isInternal.value ? { ...store, assignee_member_id: selectedAssignee.value!.member_id } : store
    assignmentPending.value = true; emit('assign', result)
    // The parent owns the real write. Keep same-tick taps single, but allow
    // another attempt if its validation rejects before entering busy state.
    void nextTick(() => { if (!props.busy) assignmentPending.value = false })
}
watch(internalAssignees, rows => { if (!rows.some(row => row.member_id === selectedAssigneeId.value)) selectedAssigneeId.value = 0 })
watch(() => props.busy, busy => { if (!busy) assignmentPending.value = false })
watch([() => props.show, () => Number(props.lead?.id || 0), () => props.lead?.assignment_version, () => props.lead?.status], ([show]) => { clearSelection(); if (show) { businessType.value = businesses.value[0]?.key || 'purchase'; keyword.value = ''; inventoryStore.value = null; rows.value = []; total.value = 0; reason.value = ''; error.value = ''; actualScope.value = ''; region.value = blankRegion(); appliedRegion.value = blankRegion(); if (props.lead?.id) void load() } else { sequence++; loading.value = false; inventoryStore.value = null } })
onBeforeUnmount(() => { sequence++ })
defineExpose({ load, applyRegion })
</script>
<style scoped lang="scss">
.coop-store-filters { padding: 12px 14px; }
.coop-replacement-current,.coop-assignment-error{padding:20rpx 24rpx;margin-bottom:20rpx;border:1rpx solid #e4eaf1;border-radius:12rpx;background:#fff}.coop-replacement-current>text{display:block;font-size:26rpx;line-height:1.8;color:#52667e}.coop-replacement-current>text+text{font-size:22rpx;color:#8b97a7}.coop-assignment-error{background:#fff4f2;border-color:#f0dcd7;color:#b15c4c;font-size:24rpx;line-height:1.8}.coop-assignment-error>button{margin:12rpx 0 0;padding:0 20rpx;min-height:44px;line-height:44px;border:1rpx solid #e9d4cf;border-radius:10rpx;background:#fff;color:#a14f40;font-size:24rpx}.coop-assignment-error>button::after{border:0}
.coop-discovery-business { margin-bottom: 16px; background: #fff; padding: 12px; border-radius: 8px; }.coop-discovery-types { display: flex; gap: 12px; flex-wrap: wrap; }.coop-discovery-types>text { padding: 9px 5px; color: #7a8ca3; font-size: 14px; border-bottom: 2px solid transparent; }.coop-discovery-types>text.active { color: var(--primary-color,#3478cc); border-bottom-color: var(--primary-color,#3478cc); font-weight: 600; }.coop-discovery-capability { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-top: 12px; min-height: 44px; color: #57799f; font-size: 13px; }
.coop-store-picker { padding: 24rpx; }.coop-region-search { padding: 22rpx 24rpx; background: #fff; margin-bottom: 24rpx; border-radius: 14rpx; }.coop-region-search__title { display: flex; align-items: center; gap: 10rpx; font-size: 28rpx; font-weight: 600; margin-bottom: 24rpx; }.coop-region-search-action { margin-top: 24rpx; }.coop-customer-location { display: flex; align-items: center; gap: 9rpx; padding: 4rpx 4rpx 24rpx; color: #65768c; font-size: 25rpx; line-height: 1.7; }.coop-customer-location>text:nth-child(2) { flex: 1; min-width: 0; word-break: break-word; }.coop-location-scope { color: var(--primary-color, #d93833); background: var(--primary-color-light, #fff0ee); padding: 3rpx 12rpx; border-radius: 6rpx; flex: none; }.coop-store-caption { display: flex; justify-content: space-between; align-items: flex-start; gap: 18rpx; font-size: 23rpx; color: #768293; line-height: 1.7; margin: 24rpx 4rpx; }.coop-store-caption>text:last-child { flex: none; }.coop-later { display: block; text-align: center; color: #727f8e; font-size: 24rpx; padding: 22rpx 0 2rpx; }.coop-footer-note { font-size: 23rpx; color: #7d8998; line-height: 1.6; text-align: center; margin-bottom: 18rpx; }
.coop-store-more { display: flex; align-items: center; justify-content: center; min-height: 44px; padding: 12px 0; box-sizing: border-box; text-align: center; font-size: 13px; color: #788a9f; }
.coop-later { display: flex; align-items: center; justify-content: center; min-height: 44px; box-sizing: border-box; padding: 8px 0 0; font-size: 13px; }
.coop-footer-note { overflow-wrap: anywhere; font-size: 12px; line-height: 1.6; margin-bottom: 8px; }
.coop-footer-note.has-selection { color: #455e7d; }
.coop-internal-members { margin:-4rpx 0 22rpx;padding:12px 14px;border:1px solid #e4eaf2;border-radius:8px;background:#fff; }
.coop-internal-members__heading { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;font-size:14px;font-weight:600;line-height:22px;color:#2d3d52; }
.coop-internal-members__heading>text:last-child { font-size:11px;font-weight:400;color:#6c7e92; }
.coop-internal-member { display:flex;align-items:center;gap:10px;min-height:58px;width:100%;margin:0;padding:8px 0;border:0;border-radius:0;background:#fff;text-align:left;box-sizing:border-box; }
.coop-internal-member+.coop-internal-member { border-top:1px solid #edf1f5; }
.coop-internal-member::after { border:0; }
.coop-internal-member__avatar { display:flex;align-items:center;justify-content:center;flex:none;width:34px;height:34px;border-radius:50%;background:#edf3fc;color:#5b789f;font-size:15px;line-height:1; }
.coop-internal-member__copy { flex:1;min-width:0; }
.coop-internal-member__copy>text { display:block;font-size:14px;line-height:22px;color:#33465f;overflow-wrap:anywhere; }
.coop-internal-member__copy>text+text { font-size:11px;line-height:18px;color:#8391a3; }
.coop-internal-member.is-selected .coop-internal-member__copy>text:first-child { color:var(--primary-color,#3478cc);font-weight:600; }
.coop-internal-members__empty { display:block;padding:8px 0;font-size:13px;line-height:22px;color:#748398; }
.coop-discovery-types>text { display: inline-flex; align-items: center; min-height: 44px; padding: 0 6px; box-sizing: border-box; }
.coop-customer-location { padding-bottom: 20rpx; align-items: center; }.coop-location-scope { color: var(--primary-color, #3478cc); background: #eaf2ff; border: 1rpx solid #d4e3f8; border-radius: 8rpx; padding: 4rpx 14rpx; font-size: 22rpx; line-height: 1.6; font-weight: 550; white-space: nowrap; }.coop-store-caption { margin-top: 18rpx; margin-bottom: 18rpx; }
</style>
