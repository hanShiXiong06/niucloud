<template>
    <CoopTaskSheet :show="show" title="门店库存" :subtitle="store.name || '合作门店'" full @close="$emit('close')">
        <view class="coop-stock-browser">
            <view class="coop-stock-notice"><u-icon name="info-circle" size="17" color="#6b829a" /><text>库存是最近同步的参考信息，成交前请与门店确认。</text></view>
            <CooperationSearch v-model="keyword" placeholder="搜索品牌、型号或规格" :show-action="true" action-text="查询" @search="search" @custom="search" @clear="search" />
            <view class="coop-stock-summary"><text>{{ total }} 条可售库存参考</text><text v-if="snapshotAt">{{ coopDate(snapshotAt) }} 更新</text></view>
            <CoopState v-if="loading && !rows.length || error" :loading="loading" :error="error" @retry="load(false)" />
            <template v-else>
                <view v-for="row in rows" :key="row.id" class="coop-stock-row"><view class="coop-stock-row__main"><view>{{ row.model }}</view><text>{{ [row.memory, row.color, row.grade].filter(Boolean).join(' · ') || row.brand }}</text><view class="coop-stock-state"><u-icon name="checkmark-circle" size="12" color="#448266" /><text>{{ row.sale_status_label || '在售' }} · {{ row.stock_status_label || '库存供参考' }}</text></view></view><view class="coop-stock-price"><text>{{ coopMoney(row.price) }}</text><text>门店参考价</text></view></view>
                <CoopState v-if="!rows.length" :title="message || '暂无可展示的在售库存'" :hint="store.manual_model_count ? '这家门店已登记可交付型号，可在交付前联系核对具体货品' : '门店可能暂未连接 ERP，或近期没有可售库存快照'" icon="bag" />
                <view v-if="rows.length" class="coop-stock-more" @tap="!loading && canMore && load(true)">{{ loading ? '正在读取…' : canMore ? '加载更多库存' : '已显示全部库存' }}</view>
            </template>
        </view>
        <template #footer><u-button shape="circle" type="primary" text="返回门店" @click="$emit('close')" /></template>
    </CoopTaskSheet>
</template>
<script setup lang="ts">
import CooperationSearch from './CooperationSearch.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { cooperationStoreInventory, coopDate, coopError, coopMoney, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopState from './CoopState.vue'
const props = defineProps<{ show: boolean; store: CoopRow }>()
defineEmits<{ (event: 'close'): void }>()
const keyword = ref(''), rows = ref<CoopRow[]>([]), total = ref(0), page = ref(1), snapshotAt = ref(0), message = ref(''), loading = ref(false), error = ref('')
const canMore = computed(() => rows.value.length < total.value)
let sequence = 0
async function load(more = false) {
    if (!props.show || !Number(props.store.id) || loading.value) return
    const token = ++sequence, requestedPage = more ? page.value + 1 : 1
    loading.value = true; error.value = ''
    try {
        const response: any = await cooperationStoreInventory(Number(props.store.id), { keyword: keyword.value.trim(), page: requestedPage, limit: 20 })
        if (token !== sequence || !props.show) return
        const payload = response.data || {}, incoming = Array.isArray(payload.data) ? payload.data : []
        rows.value = more ? [...new Map([...rows.value, ...incoming].map(row => [row.id, row])).values()] : incoming
        total.value = Number(payload.total || 0); page.value = requestedPage; snapshotAt.value = Number(payload.snapshot_at || 0); message.value = payload.message || ''
    } catch (cause) { if (token === sequence && props.show) error.value = coopError(cause, '库存读取失败，请重试') }
    finally { if (token === sequence) loading.value = false }
}
function search() { sequence++; loading.value = false; rows.value = []; total.value = 0; void load() }
watch(() => props.show, show => { sequence++; loading.value = false; if (show) { keyword.value = ''; rows.value = []; total.value = 0; snapshotAt.value = 0; message.value = ''; page.value = 1; void load() } })
onBeforeUnmount(() => { sequence++ })
</script>
<style scoped lang="scss">
.coop-stock-browser { padding: 24rpx; }.coop-stock-notice { display: flex; align-items: flex-start; gap: 12rpx; background: #edf3fa; border-radius: 10rpx; padding: 20rpx; color: #657a92; font-size: 23rpx; line-height: 1.7; margin-bottom: 24rpx; }.coop-stock-summary { display: flex; flex-wrap: wrap; gap: 9rpx 20rpx; justify-content: space-between; font-size: 21rpx; color: #8793a2; margin: 22rpx 4rpx 15rpx; }.coop-stock-row { display: flex; gap: 20rpx; align-items: flex-start; padding: 26rpx 24rpx; background: #fff; border-bottom: 1rpx solid #edf0f4; }.coop-stock-row__main { flex: 1; min-width: 0; font-size: 28rpx; font-weight: 600; line-height: 1.6; word-break: break-word; }.coop-stock-row__main>text { display: block; margin-top: 7rpx; font-size: 23rpx; color: #87919d; font-weight: 400; }.coop-stock-state { display: flex; gap: 7rpx; align-items: center; font-size: 21rpx; color: #648171; font-weight: 400; margin-top: 13rpx; }.coop-stock-price { display: flex; flex-direction: column; align-items: flex-end; gap: 7rpx; flex: none; max-width: 35%; padding-top: 4rpx; font-size: 29rpx; color: var(--primary-color, #d93833); font-weight: 600; overflow-wrap: anywhere; }.coop-stock-price>text:last-child { font-size: 20rpx; color: #9aa2ad; font-weight: 400; }.coop-stock-more { padding: 32rpx 0; font-size: 23rpx; text-align: center; color: #8190a1; }
</style>
