<template>
    <CoopTaskSheet :page="page" :show="show" title="门店库存" :subtitle="store.name || '合作门店'" fixed-body :inactive="true" full @close="$emit('close')">
        <z-paging v-if="show" :key="pagingRevision" ref="pagingRef" :model-value="rows" class="coop-stock-paging" :fixed="false" :use-page-scroll="false" height="100%" :auto="true" :concat="false" :default-page-size="20" :auto-clean-list-when-reload="false" :safe-area-inset-bottom="false" :call-network-reject="false" :show-loading-more-no-more-line="false" loading-more-fail-text="库存读取失败，点击重试" loading-more-no-more-text="已显示全部库存" @query="pagingQuery">
            <view class="coop-stock-browser">
                <view class="coop-stock-notice"><u-icon name="info-circle" size="17" color="#6b829a" /><text>库存是最近同步的参考信息，成交前请与门店确认。</text></view>
                <view class="coop-stock-summary"><text>{{ firstPageReady ? total + ' 条可售库存参考' : loading ? '正在读取库存…' : error ? '库存尚未读取' : '等待库存数据' }}</text><text v-if="snapshotAt">{{ coopDate(snapshotAt) }} 更新</text></view>
                <view v-if="error && rows.length" class="coop-stock-error">{{ error }}，请点击列表底部重试。</view>
                <view v-for="row in rows" :key="row.id" class="coop-stock-row"><view class="coop-stock-row__main"><view>{{ row.model }}</view><text>{{ [row.memory, row.color, row.grade].filter(Boolean).join(' · ') || row.brand }}</text><view class="coop-stock-state"><u-icon name="checkmark-circle" size="12" color="#448266" /><text>{{ row.sale_status_label || '在售' }} · {{ row.stock_status_label || '库存供参考' }}</text></view></view><view class="coop-stock-price"><text>{{ coopMoney(row.price) }}</text><text>门店参考价</text></view></view>
            </view>
            <template #empty="{ isLoadFailed }"><view class="coop-stock-empty"><CoopState :error="isLoadFailed ? error || '库存读取失败，请重试' : ''" :title="message || '暂无可展示的在售库存'" :hint="store.manual_model_count ? '这家门店已登记可交付型号，可在交付前联系核对具体货品' : '门店可能暂未连接 ERP，或近期没有可售库存快照'" icon="bag" @retry="search" /></view></template>
        </z-paging>
        <template #filters><view class="coop-stock-filters"><CooperationSearch v-model="keyword" placeholder="搜索品牌、型号或规格" :show-action="true" action-text="查询" @search="search" @custom="search" @clear="search" /></view></template>
        <template #footer><u-button :custom-style="cooperationButtonStyle" shape="circle" text="返回门店" @click="$emit('close')" /></template>
    </CoopTaskSheet>
</template>
<script lang="ts">
// Match Vue's transparent layout in native Mini Programs: an extra custom
// component host otherwise breaks the page's definite-height flex chain.
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import ZPaging from 'z-paging/components/z-paging/z-paging.vue'
import CooperationSearch from './CooperationSearch.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { cooperationStoreInventory, coopDate, coopError, coopMoney, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopState from './CoopState.vue'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
const props = defineProps<{ show: boolean; page?: boolean; store: CoopRow }>()
defineEmits<{ (event: 'close'): void }>()
const pagingRef = ref<any>(null), keyword = ref(''), rows = ref<CoopRow[]>([]), total = ref(0), snapshotAt = ref(0), message = ref(''), loading = ref(false), error = ref('')
const appliedKeyword = ref(''), pagingRevision = ref(0), firstPageReady = ref(false)
let sequence = 0, appliedStoreId = 0
// Rows are request-owned; old pager model emissions must never rewrite them.
const pagingQuery = computed(() => { const revision = pagingRevision.value; return (page: number, limit: number) => queryInventory(page, limit, revision) })
async function queryInventory(page: number, limit: number, revision = pagingRevision.value) {
    if (!props.show || !Number(props.store.id) || revision !== pagingRevision.value) return
    if (page > 1 && !firstPageReady.value) return reloadInventory()
    if (page === 1) firstPageReady.value = false
    const token = ++sequence
    loading.value = true; error.value = ''
    try {
        const response: any = await cooperationStoreInventory(Number(props.store.id), { keyword: appliedKeyword.value, page, limit })
        if (token !== sequence || !props.show) return
        const payload = response.data || {}
        if (!Array.isArray(payload.data) || !Number.isFinite(Number(payload.total))) throw new Error('库存列表返回异常，请重新读取')
        const incoming = payload.data
        rows.value = page > 1 ? [...new Map([...rows.value, ...incoming].map(row => [row.id, row])).values()] : incoming
        total.value = Number(payload.total); snapshotAt.value = Number(payload.snapshot_at || 0); message.value = payload.message || ''
        firstPageReady.value = true
        await pagingRef.value?.completeByNoMore([...rows.value], rows.value.length >= total.value)
    } catch (cause) { if (token === sequence && props.show) { error.value = coopError(cause, '库存读取失败，请重试'); void pagingRef.value?.complete(false) } }
    finally { if (token === sequence) loading.value = false }
}
function reloadInventory(changed = false) {
    sequence++; loading.value = false; firstPageReady.value = false
    if (changed) { rows.value = []; total.value = 0; snapshotAt.value = 0; message.value = ''; error.value = '' }
    // A keyed instance loads once from z-paging's own mounted lifecycle. Popup
    // slots/native refs may mount after the parent's nextTick; don't drop the
    // initial read by relying on an early optional ref.reload() call.
    pagingRevision.value++
}
function search() { const next = keyword.value.trim(), changed = next !== appliedKeyword.value || appliedStoreId !== Number(props.store.id); appliedKeyword.value = next; appliedStoreId = Number(props.store.id); return reloadInventory(changed) }
watch([() => props.show, () => props.store.id], ([show]) => {
    sequence++; loading.value = false
    if (!show) return
    keyword.value = ''; appliedKeyword.value = ''; appliedStoreId = 0; rows.value = []; total.value = 0; snapshotAt.value = 0; message.value = ''; error.value = ''
    // Apply the store/search before this render creates the new keyed pager.
    search()
}, { immediate: true })
onBeforeUnmount(() => { sequence++ })
</script>
<style scoped lang="scss">
.coop-stock-filters { padding: 12px 14px; }
.coop-stock-browser { padding: 24rpx; }.coop-stock-notice { display: flex; align-items: flex-start; gap: 12rpx; background: #edf3fa; border-radius: 10rpx; padding: 20rpx; color: #657a92; font-size: 23rpx; line-height: 1.7; margin-bottom: 24rpx; }.coop-stock-summary { display: flex; flex-wrap: wrap; gap: 9rpx 20rpx; justify-content: space-between; font-size: 21rpx; color: #8793a2; margin: 22rpx 4rpx 15rpx; }.coop-stock-row { display: flex; gap: 20rpx; align-items: flex-start; padding: 26rpx 24rpx; background: #fff; border-bottom: 1rpx solid #edf0f4; }.coop-stock-row__main { flex: 1; min-width: 0; font-size: 28rpx; font-weight: 600; line-height: 1.6; word-break: break-word; }.coop-stock-row__main>text { display: block; margin-top: 7rpx; font-size: 23rpx; color: #87919d; font-weight: 400; }.coop-stock-state { display: flex; gap: 7rpx; align-items: center; font-size: 21rpx; color: #648171; font-weight: 400; margin-top: 13rpx; }.coop-stock-price { display: flex; flex-direction: column; align-items: flex-end; gap: 7rpx; flex: none; max-width: 35%; padding-top: 4rpx; font-size: 29rpx; color: var(--primary-color, #d93833); font-weight: 600; overflow-wrap: anywhere; }.coop-stock-price>text:last-child { font-size: 20rpx; color: #9aa2ad; font-weight: 400; }
</style>

<style scoped>.coop-stock-paging { flex:1 1 0;height:100%;min-height:0;min-width:0;width:100%;overflow:hidden; }.coop-stock-empty { padding: 24rpx; }.coop-stock-error { font-size: 23rpx; line-height: 1.8; color: #a3654d; padding: 12rpx 4rpx; }</style>
